<?php
/**
 * CONTROLLER DE AUTENTICAÇÃO (LOGIN)
 * ---------------------------------
 * Este arquivo é o portão de entrada do sistema. Ele implementa camadas
 * de segurança para proteger contra invasões e garantir que apenas usuários
 * aprovados acessem a plataforma.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Funcionario.php';
require_once __DIR__ . '/../utils/Auth.php';

// --- CONFIGURAÇÕES DE API E SEGURANÇA CORS ---
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// Resposta rápida para requisições de pré-verificação (Preflight) do navegador
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

// --- INICIALIZAÇÃO ---
$funcionario = new Funcionario($pdo);
$json = file_get_contents("php://input");
$dados = json_decode($json);
$ip = $_SERVER['REMOTE_ADDR']; // Captura o IP real do usuário para fins de auditoria

/**
 * 1. VALIDAÇÃO DE PROTOCOLO
 * Garante que a requisição seja POST e contenha dados válidos.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$dados) {
    http_response_code(400);
    echo json_encode(["message" => "Requisição inválida ou sem dados."]);
    exit;
}

/**
 * 2. PROTEÇÃO ANTI-BRUTE FORCE (BLOQUEIO POR IP)
 * Consulta o banco de dados para verificar se este IP excedeu o limite 
 * de tentativas incorretas. Retorna Status 429 (Too Many Requests).
 */
if ($funcionario->estaBloqueado($ip)) {
    http_response_code(429);
    echo json_encode(["message" => "Muitas tentativas incorretas. Acesso bloqueado temporariamente por segurança."]);
    exit;
}

$login = trim($dados->login ?? '');
$senha = trim($dados->senha ?? '');

if (empty($login) || empty($senha)) {
    http_response_code(400);
    echo json_encode(["message" => "Login e senha são obrigatórios."]);
    exit;
}

/**
 * 3. BUSCA FLEXÍVEL DE USUÁRIO
 * O sistema permite que o funcionário entre usando tanto o E-mail quanto o CPF.
 */
$user = $funcionario->buscarPorEmail($login) ?: $funcionario->buscarPorCpf($login);

if ($user) {
    /**
     * 4. VERIFICAÇÃO CRIPTOGRÁFICA
     * password_verify compara a senha digitada com o hash BCRYPT armazenado no banco.
     */
    if (password_verify($senha, $user['senha'])) {

        /**
         * 5. VALIDAÇÃO DE STATUS (WORKFLOW DE APROVAÇÃO)
         * Mesmo com senha correta, o acesso é negado se o Admin ainda não 
         * tiver ativado o funcionário (Status 'PENDENTE' ou 'INATIVO').
         */
        if (isset($user['status']) && $user['status'] !== 'ATIVO') {
            http_response_code(403); // Forbidden
            echo json_encode(["message" => "Seu cadastro aguarda aprovação administrativa."]);
            exit;
        }

        // --- SUCESSO NO LOGIN ---
        // Reseta o contador de erros do IP no banco após um login bem-sucedido
        $funcionario->limparTentativas($ip); 
        
        // Cria a sessão segura via Utils/Auth
        Auth::login($user);

        echo json_encode([
            "success" => true,
            "message" => "Autenticação realizada. Bem-vindo, " . $user['nome_completo'],
            "perfil"  => $user['nivel_acesso'] // ADMIN ou USER
        ]);
        exit;
    }
}

/**
 * 6. TRATAMENTO DE FALHA E DEFESA ATIVA
 * Se o login falhar (usuário inexistente ou senha errada), registramos o erro no banco.
 */
$funcionario->registrarTentativaLogin($ip, $login);

/**
 * 7. DELAY DE SEGURANÇA (THROTTLING)
 * Forçamos uma espera de 2 segundos. Isso torna ataques automatizados de 
 * dicionário extremamente lentos e ineficientes.
 */
sleep(2); 

http_response_code(401); // Unauthorized
echo json_encode(["message" => "Credenciais incorretas. Tente novamente."]);