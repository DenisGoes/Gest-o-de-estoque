<?php
/**
 * CONTROLLER DE PRODUTOS
 * ----------------------
 * Gerencia o catálogo principal de mercadorias, controlando preços,
 * descrições e o vínculo com as categorias.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Produto.php';
require_once __DIR__ . '/../utils/Auth.php';

/**
 * 1. CAMADA DE AUTORIZAÇÃO
 * Permite que qualquer usuário logado (ADMIN ou USER) visualize e 
 * gerencie produtos, conforme a regra de negócio do sistema.
 */
Auth::check(); 

/**
 * CONFIGURAÇÕES DE SEGURANÇA (HEADERS)
 */
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("X-Content-Type-Options: nosniff"); // Impede ataques de personificação de MIME type

/**
 * FUNÇÃO DE SANITIZAÇÃO (XSS)
 * Limpa entradas de texto para evitar a execução de scripts maliciosos (Cross-Site Scripting).
 */
function limpar($dado)
{
    return $dado ? htmlspecialchars(strip_tags(trim($dado))) : null;
}

$produto = new Produto($pdo);
$metodo = $_SERVER['REQUEST_METHOD'];
$dados = json_decode(file_get_contents("php://input"));

switch ($metodo) {
    case 'GET':
        /**
         * LISTAGEM GERAL
         * Retorna todos os produtos, geralmente incluindo o nome da categoria vinculada.
         */
        echo json_encode($produto->read());
        break;

    case 'POST':
        /**
         * CADASTRO DE NOVO PRODUTO
         * 1. Limpeza e Tipagem rigorosa dos dados vindos do JSON.
         */
        $id_categoria     = filter_var($dados->id_categoria ?? null, FILTER_SANITIZE_NUMBER_INT);
        $nome_produto     = limpar($dados->nome_produto ?? null);
        $descricao        = limpar($dados->descricao ?? null);
        
        // Garante que quantidades e preços sejam tratados com os filtros numéricos corretos
        $quantidade_atual = filter_var($dados->quantidade_atual ?? 0, FILTER_SANITIZE_NUMBER_INT);
        $preco_vitrine    = filter_var($dados->preco_vitrine ?? 0, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

        /**
         * 2. VALIDAÇÃO DE OBRIGATORIEDADE
         * Um produto não pode existir sem nome ou sem estar vinculado a uma categoria.
         */
        if ($id_categoria && $nome_produto) {
            // A proteção contra SQL Injection é feita via Prepared Statements no Model
            if ($produto->create($id_categoria, $nome_produto, $descricao, $quantidade_atual, $preco_vitrine)) {
                echo json_encode(["message" => "Produto cadastrado com sucesso!"]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Erro técnico ao cadastrar produto."]);
            }
        } else {
            http_response_code(400); // Bad Request
            echo json_encode(["message" => "Categoria e nome do produto são obrigatórios."]);
        }
        break;

    case 'PUT':
        /**
         * ATUALIZAÇÃO DE PRODUTO
         * 3. Sanitização do ID na URL e dos novos dados no corpo da requisição.
         */
        $id = filter_var($_GET['id'] ?? null, FILTER_SANITIZE_NUMBER_INT);

        $id_categoria     = filter_var($dados->id_categoria ?? null, FILTER_SANITIZE_NUMBER_INT);
        $nome_produto     = limpar($dados->nome_produto ?? null);
        $descricao        = limpar($dados->descricao ?? null);
        $quantidade_atual = filter_var($dados->quantidade_atual ?? null, FILTER_SANITIZE_NUMBER_INT);
        $preco_vitrine    = filter_var($dados->preco_vitrine ?? null, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

        if ($id && ($nome_produto || $id_categoria)) {
            if ($produto->update($id, $id_categoria, $nome_produto, $descricao, $quantidade_atual, $preco_vitrine)) {
                echo json_encode(["message" => "Dados do produto atualizados com sucesso!"]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "ID e ao menos um campo para atualização são necessários."]);
        }
        break;

    case 'DELETE':
        /**
         * EXCLUSÃO DE PRODUTO
         * Valida o ID via Query String antes de executar a remoção no Model.
         */
        $id = filter_var($_GET['id'] ?? null, FILTER_SANITIZE_NUMBER_INT);

        if ($id && $produto->delete($id)) {
            echo json_encode(["message" => "Produto removido com sucesso!"]);
        } else {
            http_response_code(400);
            echo json_encode(["message" => "ID inválido ou erro ao processar a remoção."]);
        }
        break;
}