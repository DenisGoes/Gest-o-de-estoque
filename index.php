<?php

/**
 * FRONT CONTROLLER - PONTO DE ENTRADA ÚNICO
 * -----------------------------------------
 * Este arquivo centraliza todas as requisições da API, gerencia o roteamento
 * e define políticas globais de segurança e CORS.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/utils/Auth.php';

/**
 * 1. CONFIGURAÇÕES GLOBAIS DE CABEÇALHO (CORS)
 * Permite que o seu Front-end (React/Vite/TikTok Web) se comunique com esta API.
 */
header("Access-Control-Allow-Origin: *"); // Em produção, substitua pelo domínio real
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

/**
 * Tratamento de Preflight (OPTIONS)
 * Essencial para que navegadores modernos permitam requisições POST/PUT.
 */
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * 2. CAPTURA DE ROTA E MÉTODO
 * A variável $url é preenchida pelo .htaccess (ex: index.php?url=login)
 */
$url = $_GET['url'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

/**
 * 3. ROTEADOR DINÂMICO (SWITCH CASE)
 * Mapeia os endpoints da API para seus respectivos controladores.
 */
switch ($url) {

    // ROTA: LOGIN
    case 'login':
        if ($method === 'POST') {
            require_once __DIR__ . '/controllers/LoginController.php';
        } else {
            http_response_code(405);
            echo json_encode(["message" => "Método não permitido. Use POST."]);
        }
        break;

    // ROTA: CADASTRO DE FUNCIONÁRIOS
    case 'cadastro':
        if ($method === 'POST') {
            require_once __DIR__ . '/controllers/FuncionarioController.php';
        } else {
            http_response_code(405);
            echo json_encode(["message" => "Método não permitido. Use POST."]);
        }
        break;

    // ROTA: LOGOUT
    case 'logout':
        require_once __DIR__ . '/controllers/LogoutController.php';
        break;

    // ROTA: LISTAR USUÁRIOS PENDENTES (ÁREA ADMINISTRATIVA)
    case 'admin/pendentes':
        if ($method === 'GET') {
            require_once __DIR__ . '/controllers/DashboardController.php';
        }
        break;

    // ROTA: APROVAR NOVOS USUÁRIOS (ÁREA ADMINISTRATIVA)
    case 'admin/aprovar':
        if ($method === 'POST') {
            require_once __DIR__ . '/controllers/DashboardController.php';
        }
        break;

    /**
     * FALLBACK: ROTA NÃO ENCONTRADA
     */
    default:
        http_response_code(404);
        echo json_encode(["message" => "Rota não encontrada ou endpoint inválido."]);
        break;
}
