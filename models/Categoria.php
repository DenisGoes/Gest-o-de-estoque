<?php

/**
 * MODEL CATEGORIA
 * --------------
 * Esta classe abstrai todas as operações de banco de dados relacionadas 
 * à organização dos produtos em categorias.
 */

class Categoria
{
    private $conn;

    /**
     * O construtor recebe a instância do PDO (conexão) 
     * garantindo o padrão de Injeção de Dependência.
     */
    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * MÉTODO: CREATE
     * Insere uma nova categoria no sistema.
     */
    public function create($nome)
    {
        $query = "INSERT INTO categorias (nome_categoria) VALUES (?)";
        $stmt = $this->conn->prepare($query);
        // O uso de Prepared Statements (?) evita ataques de SQL Injection
        return $stmt->execute([$nome]);
    }

    /**
     * MÉTODO: READ
     * Retorna todas as categorias cadastradas para popular selects ou tabelas.
     */
    public function read()
    {
        $query = "SELECT * FROM categorias ORDER BY nome_categoria ASC";
        $stmt = $this->conn->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * MÉTODO: UPDATE
     * Permite a alteração do nome de uma categoria existente via ID.
     */
    public function update($id, $nome)
    {
        $query = "UPDATE categorias SET nome_categoria = ? WHERE id_categoria = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$nome, $id]);
    }

    /**
     * MÉTODO: DELETE
     * Remove uma categoria, validando a integridade referencial do banco.
     */
    public function delete($id)
    {
        try {
            $query = "DELETE FROM categorias WHERE id_categoria = ?";
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            /**
             * TRATAMENTO DE CHAVE ESTRANGEIRA (Constraint)
             * O código 23000 indica que o banco impediu a exclusão porque
             * existem produtos vinculados a esta categoria. 
             * Isso evita inconsistência de dados no sistema.
             */
            if ($e->getCode() == '23000') {
                return false; // Retornamos false para o Controller tratar a mensagem amigável
            }
            throw $e; // Outros erros de banco são lançados para o log
        }
    }
}
