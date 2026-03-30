<?php

/**
 * CONTROLLER DE MOVIMENTAÇÃO DE ESTOQUE (LOG DE AUDITORIA)
 * ------------------------------------------------------
 * Este arquivo gerencia a leitura do histórico de entradas e saídas.
 * Por segurança, este controller é "Read-Only" (Apenas Leitura).
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Movimentacao.php';
require_once __DIR__ . '/../utils/Auth.php';

/**
 * 1. CAMADA DE AUTORIZAÇÃO (RESTRITO)
 * O histórico de movimentação é um dado sensível para auditoria.
 * Somente usuários com nível 'ADMIN' podem visualizar estes logs.
 */
Auth::check('ADMIN');

/**
 * CONFIGURAÇÕES DE SEGURANÇA (HEADERS)
 */
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("X-Content-Type-Options: nosniff"); // Proteção contra Sniffing de conteúdo

$movimentacao = new Movimentacao($pdo);
$metodo = $_SERVER['REQUEST_METHOD'];

/**
 * 2. BLINDAGEM DE INTEGRIDADE
 * Movimentações de estoque são geradas automaticamente por Triggers no MySQL
 * durante Compras ou Vendas. Portanto, a API bloqueia qualquer tentativa 
 * de inserção ou alteração manual (POST/PUT/DELETE).
 */
if ($metodo === 'GET') {

    /**
     * 3. FILTRAGEM E SANITIZAÇÃO
     * O sistema permite visualizar o log de um produto específico ou o log geral.
     * filter_var garante que o ID seja estritamente numérico, prevenindo injeções via URL.
     */
    $id_produto = filter_var($_GET['id_produto'] ?? null, FILTER_SANITIZE_NUMBER_INT);

    if ($id_produto) {
        /**
         * SQL Injection protegido via PDO/Prepared Statements no Model.
         * Retorna o extrato de movimentação de um item específico.
         */
        echo json_encode($movimentacao->readByProduto($id_produto));
    } else {
        /**
         * Retorna o histórico completo de movimentações do sistema (Auditoria Geral).
         */
        echo json_encode($movimentacao->readAll());
    }
} else {
    /**
     * 4. RESPOSTA DE MÉTODO NÃO PERMITIDO
     * Se o Front-end ou um invasor tentar enviar um POST/PUT para esta rota,
     * retornamos o Status 405, informando que a operação é proibida por regra de negócio.
     */
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Operação negada. O histórico de movimentação é gerado automaticamente pelo banco de dados e não pode ser alterado manualmente via API."
    ]);
}
