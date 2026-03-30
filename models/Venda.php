<?php
/**
 * MODEL VENDA
 * -----------
 * Responsável por registrar as saídas de mercadorias e processar o faturamento.
 * Implementa o conceito de Atomicidade: se um item falhar, a venda toda é revertida.
 */

class Venda {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * MÉTODO: CREATE (Registro de Venda com Baixa Automática)
     * ------------------------------------------------------
     * @param int $id_funcionario O vendedor autenticado via sessão.
     * @param string $forma_pagamento Dinheiro, PIX, Cartão, etc.
     * @param array $itens Lista de objetos contendo id_produto, qtd e preço.
     */
    public function create($id_funcionario, $forma_pagamento, $itens) {
        try {
            // Inicia a transação para garantir consistência total
            $this->conn->beginTransaction();

            /**
             * 1. INSERÇÃO DO CABEÇALHO DA VENDA
             * Registra o evento principal da transação.
             */
            $queryVenda = "INSERT INTO vendas (id_funcionario, data_venda, forma_pagamento) VALUES (?, NOW(), ?)";
            $stmtVenda = $this->conn->prepare($queryVenda);
            $stmtVenda->execute([$id_funcionario, $forma_pagamento]);
            
            // Captura o ID da venda recém-criada para os itens
            $id_venda = $this->conn->lastInsertId();

            /**
             * 2. INSERÇÃO DOS ITENS DA VENDA
             * Nota Técnica: A TRIGGER 'tg_atualiza_estoque_venda' no MySQL 
             * subtrairá automaticamente a 'quantidade_venda' da tabela 'produtos'.
             */
            $queryItem = "INSERT INTO itens_venda (id_venda, id_produto, quantidade_venda, preco_venda_unitario) 
                          VALUES (?, ?, ?, ?)";
            $stmtItem = $this->conn->prepare($queryItem);

            foreach ($itens as $item) {
                $stmtItem->execute([
                    $id_venda, 
                    $item->id_produto, 
                    $item->quantidade_venda, 
                    $item->preco_venda_unitario
                ]);
            }

            /**
             * Se todos os itens foram inseridos e as Triggers de estoque 
             * não retornaram erro (ex: estoque insuficiente), confirmamos.
             */
            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            /**
             * 3. REVERSÃO (ROLLBACK)
             * Em caso de erro, desfazemos o cabeçalho e os itens inseridos.
             */
            $this->conn->rollBack();
            
            // Repassamos a exceção (pode conter a mensagem da TRIGGER de estoque)
            throw $e; 
        }
    }

    /**
     * MÉTODO: READ (Histórico de Faturamento)
     * --------------------------------------
     * Consome uma VIEW SQL que já realiza os cálculos de soma total por venda,
     * otimizando a performance da API.
     */
    public function read() {
        $query = "SELECT * FROM view_faturamento_vendas";
        $stmt = $this->conn->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}