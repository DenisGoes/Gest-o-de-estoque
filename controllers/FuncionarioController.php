<?php
/**
 * CONTROLLER DE FUNCIONÁRIOS
 * --------------------------
 * Gerencia o ciclo de vida dos usuários: Cadastro, Listagem, Edição e Aprovação.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Funcionario.php';
require_once __DIR__ . '/../utils/Auth.php';

/**
 * 1. CAMADA DE AUTORIZAÇÃO (RESTRIÇÃO ADMIN)
 * Esta rota é sensível, portanto, bloqueamos qualquer acesso que não venha de um ADMIN logado.
 *
 */
Auth::check('ADMIN');

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("X-Content-Type-Options: nosniff");

/**
 * FUNÇÃO DE SANITIZAÇÃO (XSS)
 */
function limpar($dado)
{
    return $dado ? htmlspecialchars(strip_tags(trim($dado))) : null;
}

$funcionario = new Funcionario($pdo);
$metodo = $_SERVER['REQUEST_METHOD'];
$dados = json_decode(file_get_contents("php://input"));

switch ($metodo) {
    case 'GET':
        /**
         * LISTAGEM DINÂMICA
         * Permite listar todos ou filtrar apenas os que aguardam aprovação via Query String (?status=PENDENTE).
         */
        if (isset($_GET['status']) && $_GET['status'] === 'PENDENTE') {
            echo json_encode($funcionario->buscarPendentes());
        } else {
            echo json_encode($funcionario->read());
        }
        break;

    case 'POST':
        /**
         * CADASTRO DE NOVO FUNCIONÁRIO
         */
        $nome  = limpar($dados->nome_completo ?? null);
        $email = filter_var($dados->email ?? null, FILTER_SANITIZE_EMAIL);
        $senha_pura = $dados->senha ?? null;
        $cpf_puro   = $dados->cpf ?? null;
        $nivel = limpar($dados->nivel_acesso ?? 'USER');

        if ($nome && $email && $senha_pura && $cpf_puro) {

            // 1. VALIDAÇÃO DE DUPLICIDADE
            // Evita que o mesmo e-mail ou CPF seja cadastrado duas vezes.
            if ($funcionario->buscarPorEmail($email)) {
                http_response_code(409); // Conflict
                echo json_encode(["message" => "Este e-mail já está cadastrado!"]);
                break;
            }

            // Remove caracteres especiais do CPF antes de validar/salvar
            $cpf_somente_numeros = preg_replace('/[^0-9]/', '', $cpf_puro);
            if ($funcionario->buscarPorCpf($cpf_somente_numeros)) {
                http_response_code(409);
                echo json_encode(["message" => "Este CPF já está em uso!"]);
                break;
            }

            // 2. CRIPTOGRAFIA (SEGURANÇA DE DADOS)
            // CPF é transformado em Hash SHA256 e Senha em BCRYPT (padrão de mercado).
            $cpfHash = hash('sha256', $cpf_somente_numeros);
            $senhaHash = password_hash($senha_pura, PASSWORD_BCRYPT);

            if ($funcionario->create($nome, $email, $senhaHash, $cpfHash, $nivel)) {
                echo json_encode(["message" => "Funcionário criado com sucesso!"]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Erro interno ao criar funcionário."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Todos os campos são obrigatórios."]);
        }
        break;

    case 'PUT':
        /**
         * ATUALIZAÇÃO E APROVAÇÃO
         */
        $id = filter_var($_GET['id'] ?? null, FILTER_SANITIZE_NUMBER_INT);

        if (!$id) {
            http_response_code(400);
            echo json_encode(["message" => "ID do funcionário é obrigatório."]);
            break;
        }

        // Lógica A: Se o JSON enviar 'status', tratamos como Aprovação/Bloqueio
        if (isset($dados->status)) {
            $novoStatus = limpar($dados->status);
            if ($funcionario->atualizarStatus($id, $novoStatus)) {
                echo json_encode(["message" => "O funcionário agora está $novoStatus!"]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Erro ao mudar status."]);
            }
            break; 
        }

        // Lógica B: Se não enviou status, tratamos como edição de dados de perfil
        $nome  = limpar($dados->nome_completo ?? null);
        $email = filter_var($dados->email ?? null, FILTER_SANITIZE_EMAIL);

        if ($nome || $email) {
            if ($funcionario->update($id, $nome, $email)) {
                echo json_encode(["message" => "Dados atualizados com sucesso!"]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Erro ao atualizar dados."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Envie o status ou campos para alteração."]);
        }
        break;

    case 'DELETE':
        /**
         * REMOÇÃO DE REGISTRO
         */
        $id = filter_var($_GET['id'] ?? null, FILTER_SANITIZE_NUMBER_INT);
        if ($id && $funcionario->delete($id)) {
            echo json_encode(["message" => "Removido com sucesso!"]);
        } else {
            http_response_code(400);
            echo json_encode(["message" => "ID inválido ou erro ao remover."]);
        }
        break;
}