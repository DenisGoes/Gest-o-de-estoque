<?php
/**
 * MODEL MOVIMENTACAO
 * ------------------
 * Este Model é responsável por gerenciar a leitura dos logs de estoque.
 * Ele fornece os dados para auditoria, permitindo rastrear cada alteração
 * de saldo (Entrada/Saída) realizada pelo sistema.
 */

class Movimentacao {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * MÉTODO: READALL
     * ---------------
     * Recupera o histórico completo de movimentações de todos os produtos.
     * @return array Lista cronológica de eventos de estoque.
     */
    public function readAll() {
        /**
         * SQL EXPLAIN:
         * Unimos a tabela de movimentações com a de produtos para que o 
         * relatório seja legível (exibindo o nome do item e não apenas o ID).
         * Ordenamos pela data mais recente (DESC) para facilitar a auditoria.
         */
        $query = "SELECT m.*, p.nome_produto 
                  FROM movimentacoes_estoque m
                  JOIN produtos p ON m.id_produto = p.id_produto
                  ORDER BY m.data_movimentacao DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * MÉTODO: READBYPRODUTO
     * ---------------------
     * Filtra o extrato de movimentação para um item específico.
     * Útil para funcionalidades de "Ver Detalhes" ou "Cardex" do produto.
     * @param int $id_produto
     * @return array Histórico filtrado.
     */
    public function readByProduto($id_produto) {
        $query = "SELECT m.*, p.nome_produto 
                  FROM movimentacoes_estoque m
                  JOIN produtos p ON m.id_produto = p.id_produto
                  WHERE m.id_produto = ?
                  ORDER BY m.data_movimentacao DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id_produto]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}