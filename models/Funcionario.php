<?php
/**
 * MODEL FUNCIONARIO
 * ----------------
 * Gerencia a identidade, permissões e a segurança de acesso dos usuários.
 * Implementa proteções contra ataques de Força Bruta (Brute Force) e
 * anonimização de dados sensíveis (CPF).
 */

class Funcionario
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * --- MÉTODOS DE BUSCA E AUTENTICAÇÃO ---
     */

    public function buscarPorEmail($email)
    {
        $query = "SELECT * FROM funcionarios WHERE email = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC); 
    }

    public function buscarPorCpf($cpf)
    {
        /**
         * PRIVACIDADE (DATA MASKING):
         * Transformamos o CPF em um hash SHA256 antes da busca.
         * Isso permite verificar se o CPF existe sem precisar armazenar o 
         * número real do documento no banco (Conformidade com LGPD).
         */
        $cpf_busca = hash('sha256', $cpf);

        $query = "SELECT * FROM funcionarios WHERE cpf_hash = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$cpf_busca]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * --- CAMADA DE DEFESA ATIVA (ANTI-BRUTE FORCE) ---
     */

    public function registrarTentativaLogin($ip, $email)
    {
        /**
         * Registra cada falha de login vinculando o IP e o e-mail tentado.
         */
        $query = "INSERT INTO login_attempts (ip_address, email, tentativa_timestamp) VALUES (?, ?, NOW())";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$ip, $email]);
    }

    public function estaBloqueado($ip)
    {
        /**
         * REGRAS DE BLOQUEIO:
         * Se o IP registrar 5 ou mais erros em um intervalo de 10 minutos,
         * o sistema nega qualquer tentativa de autenticação vinda desta origem.
         */
        $query = "SELECT COUNT(*) FROM login_attempts 
                  WHERE ip_address = ? 
                  AND tentativa_timestamp > (NOW() - INTERVAL 10 MINUTE)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$ip]);
        return $stmt->fetchColumn() >= 5;
    }

    public function limparTentativas($ip)
    {
        /**
         * Reset do contador: executado após um login bem-sucedido para 
         * evitar que erros antigos penalizem o usuário legítimo futuramente.
         */
        $query = "DELETE FROM login_attempts WHERE ip_address = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$ip]);
    }

    /**
     * --- WORKFLOW ADMINISTRATIVO (RBAC) ---
     */

    public function buscarPendentes()
    {
        /**
         * Retorna apenas usuários que se cadastraram mas ainda não 
         * foram validados por um Administrador.
         */
        $query = "SELECT id_funcionario, nome_completo, email, nivel_acesso, status 
                  FROM funcionarios 
                  WHERE status = 'PENDENTE'";
        $stmt = $this->conn->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function atualizarStatus($id, $novoStatus)
    {
        /**
         * Permite que o ADMIN aprove ('ATIVO') ou bloqueie ('INATIVO') contas.
         */
        $query = "UPDATE funcionarios SET status = ? WHERE id_funcionario = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$novoStatus, $id]);
    }

    /**
     * --- OPERAÇÕES CRUD PADRÃO ---
     */

    public function create($nome, $email, $senha, $cpf, $nivel = 'USER', $status = 'PENDENTE')
    {
        /**
         * CRIPTOGRAFIA DE SENHA:
         * Utilizamos BCRYPT, que gera um salt aleatório para cada senha,
         * tornando impossível a descriptografia mesmo em caso de vazamento do banco.
         */
        $hash_senha = password_hash($senha, PASSWORD_BCRYPT); 
        $cpf_hash = hash('sha256', $cpf);

        $query = "INSERT INTO funcionarios (nome_completo, email, senha, cpf_hash, nivel_acesso, status) 
                  VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$nome, $email, $hash_senha, $cpf_hash, $nivel, $status]);
    }

    public function read()
    {
        // Nunca retornamos o campo 'senha' ou 'cpf_hash' em listagens comuns
        $query = "SELECT id_funcionario, nome_completo, email, nivel_acesso, status FROM funcionarios";
        $stmt = $this->conn->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function update($id, $nome, $email)
    {
        $query = "UPDATE funcionarios SET nome_completo = ?, email = ? WHERE id_funcionario = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$nome, $email, $id]);
    }

    public function delete($id)
    {
        $query = "DELETE FROM funcionarios WHERE id_funcionario = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$id]);
    }
}