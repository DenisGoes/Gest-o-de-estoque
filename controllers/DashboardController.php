<?php
/**
 * CONTROLLER DO PAINEL ADMINISTRATIVO (DASHBOARD)
 * ----------------------------------------------
 * Este arquivo centraliza ações restritas, como a gestão de novos usuários
 * e aprovação de acessos ao sistema.
 */

require_once __DIR__ . '/../models/Funcionario.php';
require_once __DIR__ . '/../utils/Auth.php';

/**
 * 1. CAMADA DE AUTORIZAÇÃO (RBAC - Role Based Access Control)
 * Antes de qualquer processamento, verificamos se o usuário logado possui 
 * nível de acesso 'ADMIN'. Caso contrário, bloqueamos com Status 403 (Forbidden).
 */
if (!Auth::isAdmin()) {
    http_response_code(403);
    echo json_encode(["message" => "Acesso restrito a administradores."]);
    exit;
}

$funcionario = new Funcionario($pdo);
$method = $_SERVER['REQUEST_METHOD'];

/**
 * 2. ROTEAMENTO DINÂMICO
 * O index.php central envia a variável 'url' via GET para este Controller.
 * Com base nela, decidimos qual ação administrativa executar.
 */
$url = $_GET['url'] ?? '';

// --- AÇÃO: LISTAR PENDENTES (GET) ---
// Rota: index.php?url=admin/pendentes
if ($url === 'admin/pendentes' && $method === 'GET') {
    // Busca no banco apenas funcionários que ainda não foram aprovados (status = 'PENDENTE')
    $pendentes = $funcionario->buscarPendentes();

    // Retorna a lista em formato JSON para o Front-end renderizar
    echo json_encode($pendentes);
    exit;
}

// --- AÇÃO: APROVAR OU ALTERAR STATUS (POST) ---
// Rota: index.php?url=admin/aprovar
if ($url === 'admin/aprovar' && $method === 'POST') {
    // Recebe o corpo da requisição (ex: {"id": 5, "status": "ATIVO"})
    $json = file_get_contents("php://input");
    $dados = json_decode($json);

    /**
     * 3. VALIDAÇÃO DE ENTRADA
     * Verificamos se o Front-end enviou todos os parâmetros necessários.
     */
    if (!isset($dados->id) || !isset($dados->status)) {
        http_response_code(400);
        echo json_encode(["message" => "ID do funcionário e novo status são obrigatórios."]);
        exit;
    }

    /**
     * 4. SEGURANÇA DE DADOS (WHITELIST)
     * Para evitar que status inválidos sejam inseridos no banco, 
     * validamos contra uma lista de valores permitidos pelo sistema.
     */
    $statusPermitidos = ['ATIVO', 'INATIVO', 'REJEITADO'];
    if (!in_array($dados->status, $statusPermitidos)) {
        http_response_code(400);
        echo json_encode(["message" => "Status inválido. Use: ATIVO, INATIVO ou REJEITADO."]);
        exit;
    }

    /**
     * 5. PERSISTÊNCIA NO BANCO
     * Executa a atualização através do Model Funcionario.
     */
    if ($funcionario->atualizarStatus($dados->id, $dados->status)) {
        echo json_encode([
            "success" => true,
            "message" => "O status do funcionário foi atualizado para " . $dados->status
        ]);
    } else {
        // Status 500 indica um erro interno no servidor ou banco de dados
        http_response_code(500);
        echo json_encode(["message" => "Erro interno ao atualizar o status no banco de dados."]);
    }
    exit;
}

/**
 * FINALIZAÇÃO DE FLUXO
 * Caso a URL ou o Método HTTP não correspondam a nenhuma ação administrativa prevista.
 */
http_response_code(404);
echo json_encode(["message" => "Ação administrativa não encontrada."]);