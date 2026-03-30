<?php
/**
 * CONTROLLER DE LOGOUT (ENCERRAMENTO DE SESSÃO)
 * -------------------------------------------
 * Este arquivo garante a destruição segura da sessão do usuário, 
 * prevenindo ataques de Sequestro de Sessão (Session Hijacking) após a saída.
 */

require_once __DIR__ . '/../utils/Auth.php';

/**
 * 1. INICIALIZAÇÃO DO CONTEXTO
 * Verificamos se há uma sessão ativa para que possamos manipulá-la e destruí-la.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * 2. LIMPEZA DE MEMÓRIA (SERVIDOR)
 * Sobrescrevemos o array $_SESSION com um array vazio, removendo 
 * imediatamente dados como 'usuario_id' ou 'nivel_acesso' da RAM.
 */
$_SESSION = array();

/**
 * 3. DESTRUIÇÃO DO COOKIE (NAVEGADOR)
 * Para maior segurança, instruímos o navegador do usuário a deletar o cookie 
 * de sessão (PHPSESSID). Definimos uma data de validade no passado (time - 42000).
 */
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

/**
 * 4. FINALIZAÇÃO DA SESSÃO
 * Destrói definitivamente o arquivo de sessão armazenado no diretório temporário do servidor.
 */
session_destroy();

/**
 * 5. RESPOSTA PARA O FRONT-END
 * Retorna um JSON confirmando o encerramento. O colega do Front-end deve usar 
 * isso para redirecionar o usuário para a tela de login.
 */
header("Content-Type: application/json");
echo json_encode([
    "success" => true,
    "message" => "Sessão encerrada com sucesso. Até logo!"
]);