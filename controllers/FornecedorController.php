<?php
/**
 * CONTROLLER DE FORNECEDORES
 * --------------------------
 * Gerencia o cadastro, listagem e remoção de empresas fornecedoras.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Fornecedor.php';
require_once __DIR__ . '/../utils/Auth.php';

/**
 * 1. CAMADA DE AUTORIZAÇÃO
 * Verifica se o usuário está logado. 
 * Caso o nível de acesso não seja suficiente (ex: 'USER' tentando deletar), 
 * o método check() interrompe a execução com erro 403.
 */
Auth::check(); 

/**
 * CONFIGURAÇÕES DE SEGURANÇA (HEADERS)
 */
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("X-Content-Type-Options: nosniff"); // Protege contra ataques de MIME Sniffing

/**
 * FUNÇÃO DE SANITIZAÇÃO (ANTIVÍRUS DE DADOS)
 * Neutraliza ataques XSS (Cross-Site Scripting) limpando tags HTML e espaços.
 */
function limpar($dado) {
    return $dado ? htmlspecialchars(strip_tags(trim($dado))) : null;
}

$fornecedor = new Fornecedor($pdo);
$metodo = $_SERVER['REQUEST_METHOD'];

// Captura o corpo da requisição JSON (ex: enviado via fetch pelo colega do Front)
$dados = json_decode(file_get_contents("php://input"));

switch($metodo) {
    case 'GET':
        /**
         * LISTAGEM DE FORNECEDORES
         * Retorna todos os registros ativos para popular tabelas ou selects no Front-end.
         */
        echo json_encode($fornecedor->read());
        break;

    case 'POST':
        /**
         * CADASTRO DE FORNECEDOR
         * 1. Sanitização dos inputs de texto recebidos via JSON.
         */
        $nome_empresa = limpar($dados->nome_empresa ?? null);
        $cnpj         = limpar($dados->cnpj ?? null);
        $telefone     = limpar($dados->telefone ?? null);

        /**
         * 2. VALIDAÇÃO DE NEGÓCIO
         * Nome e CNPJ são campos obrigatórios para a integridade do banco.
         */
        if ($nome_empresa && $cnpj) {
            // O Model Fornecedor utiliza PDO/Prepared Statements, garantindo proteção contra SQLi.
            if ($fornecedor->create($nome_empresa, $cnpj, $telefone)) {
                echo json_encode(["message" => "Fornecedor e telefone cadastrados com sucesso!"]);
            } else {
                http_response_code(500); // Erro interno no servidor ou banco
                echo json_encode(["message" => "Erro técnico ao cadastrar fornecedor."]);
            }
        } else {
            http_response_code(400); // Bad Request: Faltam campos obrigatórios
            echo json_encode(["message" => "Nome da empresa e CNPJ são obrigatórios."]);
        }
        break;

    case 'DELETE':
        /**
         * REMOÇÃO DE FORNECEDOR
         * 3. Proteção contra Injeção via URL e manipulação de parâmetros.
         * filter_var garante que o ID seja estritamente um número inteiro.
         */
        $id = filter_var($_GET['id'] ?? null, FILTER_SANITIZE_NUMBER_INT);

        if ($id) {
            if ($fornecedor->delete($id)) {
                echo json_encode(["message" => "Fornecedor removido com sucesso!"]);
            } else {
                // Caso o ID não exista ou haja uma restrição de chave estrangeira (ex: fornecedor com compras vinculadas)
                http_response_code(500);
                echo json_encode(["message" => "Erro ao remover fornecedor. Verifique se ele possui compras vinculadas."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "ID válido é obrigatório para realizar a exclusão."]);
        }
        break;
}