<?php
/**
 * MODEL PRODUTO
 * -------------
 * Gerencia a entidade principal do sistema: o catálogo de produtos.
 * Responsável pelo controle de estoque, precificação e classificação por categorias.
 */

class Produto {
    private $conn;

    /**
     * O construtor recebe a conexão PDO, mantendo o padrão de 
     * Injeção de Dependência para facilitar testes e manutenção.
     */
    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * MÉTODO: CREATE
     * --------------
     * Insere um novo item no catálogo.
     * @param int $id_cat ID da categoria vinculada.
     * @param string $nome Nome comercial do produto.
     * @param string $desc Detalhes técnicos ou descrição.
     * @param int $qtd Saldo inicial em estoque.
     * @param float $preco Valor de venda na vitrine.
     */
    public function create($id_cat, $nome, $desc, $qtd, $preco) {
        $query = "INSERT INTO produtos (id_categoria, nome_produto, descricao, quantidade_atual, preco_vitrine) 
                  VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        // Proteção contra SQL Injection via Prepared Statements
        return $stmt->execute([$id_cat, $nome, $desc, $qtd, $preco]);
    }

    /**
     * MÉTODO: READ (Otimizado para o Front-end)
     * ----------------------------------------
     * Recupera todos os produtos cadastrados.
     * SQL EXPLAIN: Realizamos um JOIN com a tabela 'categorias' para 
     * retornar o nome da categoria diretamente, poupando processamento no PHP.
     */
    public function read() {
        $query = "SELECT p.*, c.nome_categoria 
                  FROM produtos p 
                  JOIN categorias c ON p.id_categoria = c.id_categoria";
        $stmt = $this->conn->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * MÉTODO: UPDATE
     * --------------
     * Atualiza todas as informações de um produto específico.
     */
    public function update($id, $id_cat, $nome, $desc, $qtd, $preco) {
        $query = "UPDATE produtos SET id_categoria = ?, nome_produto = ?, descricao = ?, 
                  quantidade_atual = ?, preco_vitrine = ? WHERE id_produto = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$id_cat, $nome, $desc, $qtd, $preco, $id]);
    }

    /**
     * MÉTODO: DELETE
     * --------------
     * Remove um produto do sistema.
     * NOTA: Este método falhará se houver movimentações ou vendas vinculadas 
     * (Integridade Referencial), o que protege seu histórico financeiro.
     */
    public function delete($id) {
        $query = "DELETE FROM produtos WHERE id_produto = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$id]);
    }
}