<?php

/**
 * MODEL FORNECEDOR
 * ----------------
 * Gerencia o cadastro de parceiros comerciais e seus múltiplos contatos.
 * Implementa lógica de transação para garantir que o fornecedor e seu 
 * primeiro telefone sejam criados de forma atômica.
 */

class Fornecedor
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * MÉTODO: CREATE
     * ----------------
     * Insere o fornecedor e, opcionalmente, um telefone inicial.
     * Utiliza transações para garantir que não existam fornecedores sem telefone (se enviado).
     */
    public function create($nome_empresa, $cnpj, $numero_telefone = null)
    {
        try {
            $this->conn->beginTransaction();

            // 1. INSERÇÃO DO FORNECEDOR
            $query = "INSERT INTO fornecedores (nome_empresa, cnpj) VALUES (?, ?)";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$nome_empresa, $cnpj]);

            // Recupera o ID gerado para vincular o telefone
            $id_fornecedor = $this->conn->lastInsertId();

            // 2. INSERÇÃO DO TELEFONE (RELACIONAMENTO 1:N)
            if ($numero_telefone) {
                $queryTelefone = "INSERT INTO telefone_fornecedores (id_fornecedor, numero) VALUES (?, ?)";
                $stmtTelefone = $this->conn->prepare($queryTelefone);
                $stmtTelefone->execute([$id_fornecedor, $numero_telefone]);
            }

            // Confirma as duas inserções como uma única unidade de trabalho
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            // Em caso de erro (ex: CNPJ duplicado), desfaz qualquer inserção parcial
            $this->conn->rollBack();
            return false;
        }
    }

    /**
     * MÉTODO: READ (Otimizado)
     * ------------------------
     * Busca os fornecedores e concatena seus múltiplos telefones em um único campo.
     * Técnica: GROUP_CONCAT evita o problema de 'N+1 queries'.
     */
    public function read()
    {
        /**
         * SQL EXPLAIN: 
         * Usamos LEFT JOIN para garantir que fornecedores sem telefone 
         * também apareçam na lista. O GROUP_CONCAT transforma as linhas de 
         * telefones em uma string separada por vírgula.
         */
        $query = "SELECT f.*, GROUP_CONCAT(t.numero) as telefones 
                  FROM fornecedores f 
                  LEFT JOIN telefone_fornecedores t ON f.id_fornecedor = t.id_fornecedor 
                  GROUP BY f.id_fornecedor";

        $stmt = $this->conn->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * MÉTODO: DELETE
     * --------------
     * Remove o fornecedor e seus vínculos.
     * Obs: Se o banco estiver configurado com ON DELETE CASCADE nas FKs, 
     * a remoção dos telefones é automática pelo MySQL.
     */
    public function delete($id)
    {
        try {
            // 1. Remove os telefones associados primeiro (Segurança de Integridade)
            $queryTelefones = "DELETE FROM telefone_fornecedores WHERE id_fornecedor = ?";
            $this->conn->prepare($queryTelefones)->execute([$id]);

            // 2. Remove o registro mestre do fornecedor
            $queryFornecedor = "DELETE FROM fornecedores WHERE id_fornecedor = ?";
            return $this->conn->prepare($queryFornecedor)->execute([$id]);
        } catch (PDOException $e) {
            // Log de erro pode ser adicionado aqui
            return false;
        }
    }
}
