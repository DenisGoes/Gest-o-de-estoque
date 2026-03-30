<?php
ini_set('session.cookie_samesite', 'None'); // Permite o envio de cookies de sessão em requisições entre domínios (CORS)
ini_set('session.cookie_secure', '0'); // Em ambiente de desenvolvimento (localhost), mantemos como '0'. // Em produção com HTTPS, deve ser alterado para '1'.
header("Access-Control-Allow-Origin: http://localhost:3000"); // Define qual origem pode acessar esta API (ajuste para o endereço do Front-end)
header("Access-Control-Allow-Credentials: true"); // Permite que o navegador envie credenciais (cookies, headers de autenticação)
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS"); // Métodos HTTP permitidos na API
header("Access-Control-Allow-Headers: Content-Type, Authorization"); // Cabeçalhos permitidos (necessário para aceitar Content-Type: application/json)

/**
 * TRATAMENTO DE PREFLIGHT (OPTIONS)
 * ---------------------------------
 * O navegador envia uma requisição OPTIONS antes do POST real para verificar permissões.
 * Se for OPTIONS, encerramos a execução aqui para economizar processamento.
 */
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

/**
 * CONEXÃO COM O BANCO DE DADOS (PDO)
 * ----------------------------------
 * Utilizamos PDO por ser a forma mais segura e moderna de interagir com MySQL em PHP,
 * permitindo o uso de Prepared Statements contra SQL Injection.
 */
$host = 'localhost';
$db   = 'gestao_estoque';
$user = 'root';
$pass = '';

try {
    // Instancia a conexão com charset UTF-8 para evitar erros de acentuação
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        // Lança exceções em caso de erro (ajuda no debug)
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        // Define o retorno padrão como Array Associativo ($result['coluna'])
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    // Em caso de falha, retorna um JSON de erro para não quebrar o Front-end
    http_response_code(500);
    die(json_encode(["erro" => "Falha na conexão com o banco de dados."]));
}
