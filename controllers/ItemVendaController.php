<?php
/**
 * CONTROLLER DE ITENS DE VENDA
 * ----------------------------
 * Gerencia os produtos específicos vinculados a uma transação de venda.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/ItemVenda.php';
require_once __DIR__ . '/../utils/Auth.php';

/**
 * 1. CAMADA DE AUTORIZAÇÃO
 * Exige que o usuário esteja autenticado (ADMIN ou USER) para visualizar ou 
 * manipular itens de uma venda.
 */
Auth::check(); 

/**
 * CONFIGURAÇÕES DE SEGURANÇA (HEADERS)
 */
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("X-Content-Type-Options: nosniff"); // Impede que o navegador execute arquivos com MIME type incorreto

$itemVenda = new ItemVenda($pdo);
$metodo = $_SERVER['REQUEST_METHOD'];

switch($metodo) {
    case 'GET':
        /**
         * LISTAGEM DE ITENS POR VENDA
         * 1. Sanitização do ID da Venda vindo da URL (?id_venda=X).
         * filter_var com FILTER_SANITIZE_NUMBER_INT bloqueia qualquer caractere que não seja número.
         */
        $id_venda = filter_var($_GET['id_venda'] ?? null, FILTER_SANITIZE_NUMBER_INT);

        if ($id_venda) {
            /**
             * SQL Injection protegido via PDO no Model.
             * Retorna todos os produtos associados à venda informada.
             */
            echo json_encode($itemVenda->readByVenda($id_venda));
        } else {
            http_response_code(400); // Bad Request
            echo json_encode(["message" => "ID da venda inválido ou não informado."]);
        }
        break;

    case 'DELETE':
        /**
         * REMOÇÃO DE ITEM ESPECÍFICO
         * 2. Sanitização do ID do Registro (Garante integridade na remoção).
         * Evita tentativas de injeção de comandos via Query String.
         */
        $id = filter_var($_GET['id'] ?? null, FILTER_SANITIZE_NUMBER_INT);

        if ($id) {
            /**
             * Executa a remoção através do Model.
             * Dependendo da sua regra de negócio, isso pode disparar uma Trigger 
             * para devolver o produto ao estoque (estorno).
             */
            if ($itemVenda->delete($id)) {
                echo json_encode(["message" => "Item removido da venda com sucesso!"]);
            } else {
                http_response_code(500); // Erro de Servidor (ex: ID não encontrado)
                echo json_encode(["message" => "Erro ao remover o item ou registro inexistente."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "ID do registro é obrigatório para remoção."]);
        }
        break;

    default:
        /**
         * TRATAMENTO DE MÉTODOS NÃO SUPORTADOS
         * Retorna 405 (Method Not Allowed) se o Front-end tentar um POST ou PUT aqui.
         */
        http_response_code(405);
        echo json_encode(["message" => "Método HTTP não permitido para esta operação."]);
        break;
}