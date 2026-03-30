<?php

/**
 * CONTROLLER DE CATEGORIAS
 * ------------------------
 * Este arquivo gerencia todas as operações de categorias de produtos.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Categoria.php';
require_once __DIR__ . '/../utils/Auth.php';

// Camada de Autorização: Verifica se existe uma sessão ativa antes de prosseguir
Auth::check();

/**
 * CONFIGURAÇÕES DE SEGURANÇA (HEADERS)
 */
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");

// Impede que o navegador tente "adivinhar" o tipo de conteúdo (protege contra uploads maliciosos)
header("X-Content-Type-Options: nosniff");

/**
 * FUNÇÃO DE SANITIZAÇÃO (ANTIVÍRUS DE DADOS)
 * Protege contra XSS (Cross-Site Scripting) limpando tags HTML e espaços inúteis.
 */
function limpar($dado)
{
    // trim: remove espaços / strip_tags: remove <script> / htmlspecialchars: converte p/ texto seguro
    return $dado ? htmlspecialchars(strip_tags(trim($dado))) : null;
}

$categoria = new Categoria($pdo);
$metodo = $_SERVER['REQUEST_METHOD'];

// Captura o corpo da requisição (JSON vindo do React/Front-end)
$dados = json_decode(file_get_contents("php://input"));

/**
 * ROTEAMENTO INTERNO (CRUD)
 */
switch ($metodo) {
    case 'GET':
        // Lista todas as categorias cadastradas
        echo json_encode($categoria->read());
        break;

    case 'POST':
        // Higieniza o nome antes de enviar para o banco de dados
        $nome_limpo = limpar($dados->nome_categoria ?? null);

        if (!empty($nome_limpo)) {
            // O uso de PDO no Model garante proteção contra SQL Injection aqui
            if ($categoria->create($nome_limpo)) {
                echo json_encode(["message" => "Categoria criada com sucesso!"]);
            }
        } else {
            http_response_code(400); // Erro do cliente (falta de dado)
            echo json_encode(["message" => "Nome da categoria é obrigatório."]);
        }
        break;

    case 'PUT':
        // Captura o ID via URL (query string) e garante que seja um número inteiro
        $id = filter_var($_GET['id'] ?? null, FILTER_SANITIZE_NUMBER_INT);
        $nome_limpo = limpar($dados->nome_categoria ?? null);

        if ($id && $nome_limpo) {
            if ($categoria->update($id, $nome_limpo)) {
                echo json_encode(["message" => "Categoria atualizada!"]);
            }
        }
        break;

    case 'DELETE':
        // Higienização rigorosa do ID para evitar deleção acidental ou injeção na URL
        $id = filter_var($_GET['id'] ?? null, FILTER_SANITIZE_NUMBER_INT);
        $resultado = $categoria->delete($id);

        if ($resultado === true) {
            echo json_encode(["message" => "Categoria removida!"]);
        } else {
            // Se houver erro (ex: categoria vinculada a produtos), o Model retorna a mensagem
            http_response_code(400);
            echo json_encode(["message" => $resultado]);
        }
        break;
}
