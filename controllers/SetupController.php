<?php

/**
 * SCRIPT DE INICIALIZAÇÃO (SEEDER) - PRIMEIRO ADMINISTRADOR
 * --------------------------------------------------------
 * ATENÇÃO: Este arquivo deve ser executado apenas UMA VEZ para criar o 
 * administrador mestre do sistema. Após o uso, recomenda-se deletá-lo
 * ou restringir o acesso para evitar criações não autorizadas.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Funcionario.php';

// Instância do Model para aproveitar a conexão PDO configurada
$funcionario = new Funcionario($pdo);

/**
 * DADOS DO ADMINISTRADOR MESTRE
 */
$nome = "Denis Goes";
$email = "denisg@gmail.com";
$senhaRaw = "BruteGosth"; // Senha temporária para o primeiro acesso
$cpfRaw = "1354268790";   // CPF base para o login inicial

/**
 * 1. SEGURANÇA DE DADOS (HASHING)
 * Mesmo sendo um script de setup, mantemos o padrão de segurança:
 * - password_hash (BCRYPT): Protege a senha contra leitura direta no banco.
 * - hash (SHA256): Anonimiza o CPF para buscas seguras.
 */
$senhaHash = password_hash($senhaRaw, PASSWORD_BCRYPT);
$cpfHash = hash('sha256', $cpfRaw);

try {
    /**
     * 2. INSERÇÃO DIRETA (BYPASS DE PENDÊNCIA)
     * Diferente do RegisterController, aqui forçamos o nível 'ADMIN' 
     * e o status 'ATIVO' (caso seu banco exija) para permitir o acesso imediato.
     */
    $query = "INSERT INTO funcionarios (nome_completo, email, senha, cpf_hash, nivel_acesso, status) 
              VALUES (?, ?, ?, ?, ?, 'ATIVO')";

    $stmt = $pdo->prepare($query);

    // O uso de Prepared Statements evita qualquer erro de sintaxe ou injeção acidental
    $sucesso = $stmt->execute([
        $nome,
        $email,
        $senhaHash,
        $cpfHash,
        'ADMIN'
    ]);

    if ($sucesso) {
        // Feedback limpo em JSON para o desenvolvedor
        echo json_encode([
            "success" => true,
            "message" => "O Administrador Mestre ($nome) foi criado com sucesso!",
            "aviso" => "Lembre-se de remover este script do servidor após a validação."
        ]);
    }
} catch (Exception $e) {
    /**
     * TRATAMENTO DE ERROS
     * Captura exceções como 'Duplicate Entry' (se o e-mail já existir no banco).
     */
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Falha na criação do Admin: " . $e->getMessage()
    ]);
}
