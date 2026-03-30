<?php
// controllers/CompraController.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Compra.php';
require_once __DIR__ . '/../utils/Auth.php';
Auth::check(); // Só precisa estar logado (seja ADMIN ou USER)

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("X-Content-Type-Options: nosniff");

// Função de Sanitização para Strings e XSS
function limpar($dado)
{
    return $dado ? htmlspecialchars(strip_tags(trim($dado))) : null;
}

$compra = new Compra($pdo);
$metodo = $_SERVER['REQUEST_METHOD'];
$dados = json_decode(file_get_contents("php://input"));

switch ($metodo) {
    case 'GET':
        // Retorna o histórico de compras (via View)
        echo json_encode($compra->read());
        break;

    case 'POST':
        Auth::check(); // Garante que está logado

        $id_fornecedor = $dados->id_fornecedor ?? null;
        $itens = $dados->itens ?? null; // Aqui ele pega a lista do JSON
        $id_funcionario = $_SESSION['usuario_id']; // Pega do Auth, não do JSON

        if ($id_fornecedor && is_array($itens)) {
            if ($compra->create($id_fornecedor, $id_funcionario, $itens)) {
                echo json_encode(["message" => "Compra registrada!"]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Erro no banco de dados."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Dados incompletos para registrar a compra."]);
        }
        break;

        // 3. Sanitização dos itens (dentro do array)
        $itens_limpos = [];
        foreach ($itens_brutos as $item) {
            $itens_limpos[] = (object)[
                "id_produto" => filter_var($item->id_produto, FILTER_SANITIZE_NUMBER_INT),
                "quantidade_compra" => filter_var($item->quantidade_compra, FILTER_SANITIZE_NUMBER_INT),
                "preco_compra_unitario" => filter_var($item->preco_compra_unitario, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)
            ];
        }

        // 4. Envio para o Model (Protegido contra SQL Injection via PDO no Model)
        if ($compra->create($id_fornecedor, $id_funcionario, $itens_limpos)) {
            echo json_encode(["message" => "Compra registrada e estoque atualizado via Trigger!"]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Erro ao processar compra."]);
        }
        break;
}
