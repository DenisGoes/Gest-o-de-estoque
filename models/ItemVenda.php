<?php
/**
 * MODEL ITEMVENDA
 * ---------------
 * Gerencia os itens individuais associados a uma transação de venda.
 * Este model é essencial para a geração de relatórios detalhados e 
 * para a visualização do histórico de itens por pedido.
 */

class ItemVenda {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * MÉTODO: READBYVENDA
     * ------------------
     * Recupera todos os produtos vinculados a uma venda específica.
     * * @param int $id_venda ID da venda pai.
     * @return array Lista de itens com nomes dos produtos.
     */
    public function readByVenda($id_venda) {
        /**
         * SQL EXPLAIN:
         * Realizamos um JOIN com a tabela 'produtos' para retornar o 
         * 'nome_produto' em vez de apenas o 'id_produto'. Isso evita
         * requisições extras do Front-end para buscar nomes.
         */
        $query = "SELECT iv.*, p.nome_produto 
                  FROM itens_venda iv
                  JOIN produtos p ON iv.id_produto = p.id_produto
                  WHERE iv.id_venda = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id_venda]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * MÉTODO: DELETE
     * --------------
     * Remove um item específico de uma venda.
     * * NOTA DE SEGURANÇA: Em sistemas de auditoria fiscal, a exclusão 
     * de itens de venda deve ser usada com cautela para não gerar 
     * inconsistências no valor total da venda pai.
     */
    public function delete($id_item) {
        $query = "DELETE FROM itens_venda WHERE id_item_venda = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$id_item]);
    }
}