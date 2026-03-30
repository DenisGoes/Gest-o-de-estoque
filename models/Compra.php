<?php

/**
 * MODEL COMPRA
 * ------------
 * Gerencia a entrada de mercadorias no estoque.
 * Implementa ACID (Atomicidade, Consistência, Isolamento e Durabilidade) 
 * através de transações PDO.
 */

class Compra
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * MÉTODO: CREATE (Registro de Transação Completa)
     * ----------------------------------------------
     * Este método realiza múltiplas inserções. Se uma falhar, 
     * todas são canceladas para manter a integridade do banco.
     */
    public function create($id_fornecedor, $id_funcionario, $itens)
    {
        try {
            // Inicia a transação: a partir daqui, nada é definitivo até o 'commit'
            $this->conn->beginTransaction();

            /**
             * 1. INSERÇÃO DO CABEÇALHO DA COMPRA
             * Registra quem comprou, de quem comprou e quando.
             */
            $queryCompra = "INSERT INTO compras (id_fornecedor, id_funcionario, data_compra) VALUES (?, ?, NOW())";
            $stmtCompra = $this->conn->prepare($queryCompra);
            $stmtCompra->execute([$id_fornecedor, $id_funcionario]);

            // Recupera o ID gerado para vincular aos itens abaixo
            $id_compra = $this->conn->lastInsertId();

            /**
             * 2. INSERÇÃO DOS ITENS DA COMPRA (Loop)
             * Prepara a query uma única vez para otimizar a performance (Prepared Statement).
             */
            $queryItem = "INSERT INTO itens_compra (id_compra, id_produto, quantidade_compra, preco_compra_unitario) 
                          VALUES (?, ?, ?, ?)";
            $stmtItem = $this->conn->prepare($queryItem);

            foreach ($itens as $item) {
                $stmtItem->execute([
                    $id_compra,
                    $item->id_produto,
                    $item->quantidade_compra,
                    $item->preco_compra_unitario
                ]);
                /**
                 * NOTA TÉCNICA: 
                 * A cada execução do $stmtItem, a TRIGGER 'tg_atualiza_estoque_compra' 
                 * configurada no MySQL será disparada automaticamente para somar 
                 * a quantidade ao saldo atual do produto.
                 */
            }

            // Se chegou aqui sem erros, confirma todas as alterações no banco
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            /**
             * 3. TRATAMENTO DE FALHA (ROLLBACK)
             * Caso ocorra qualquer erro (ex: falta de conexão ou erro de sintaxe),
             * o banco desfaz tudo o que foi feito dentro deste 'try'.
             */
            $this->conn->rollBack();
            // Para debug, você poderia registrar $e->getMessage() em um log
            return false;
        }
    }

    /**
     * MÉTODO: READ (Uso de Views)
     * ---------------------------
     * Em vez de fazer JOINs complexos no PHP, consumimos uma VIEW SQL 
     * que já traz os cálculos de valor total processados pelo banco.
     */
    public function read()
    {
        $query = "SELECT * FROM view_valor_total_compras";
        return $this->conn->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }
}
