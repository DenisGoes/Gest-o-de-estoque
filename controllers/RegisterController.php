<?php
/**
 * CONTROLLER DE AUTO-CADASTRO (REGISTRO)
 * --------------------------------------
 * Permite que novos funcionários solicitem acesso ao sistema.
 * Por segurança, todo novo cadastro nasce com privilégios mínimos.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Funcionario.php';

/**
 * NOTA DE ARQUITETURA:
 * Este é o único controller de escrita que NÃO utiliza Auth::check().
 * Isso permite que visitantes (futuros funcionários) enviem seus dados.
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("X-Content-Type-Options: nosniff");

/**
 * FUNÇÃO DE SANITIZAÇÃO (XSS)
 */
function limpar($dado) {
    return $dado ? htmlspecialchars(strip_tags(trim($dado))) : null;
}

$funcionario = new Funcionario($pdo);
$metodo = $_SERVER['REQUEST_METHOD'];
$dados = json_decode(file_get_contents("php://input"));

if ($metodo === 'POST') {
    // 1. Captura e Limpeza de Dados
    $nome  = limpar($dados->nome_completo ?? null);
    $email = filter_var($dados->email ?? null, FILTER_SANITIZE_EMAIL);
    $senha_pura = $dados->senha ?? null;
    $cpf_puro   = $dados->cpf ?? null;

    if ($nome && $email && $senha_pura && $cpf_puro) {
        
        /**
         * 2. VALIDAÇÃO DE DUPLICIDADE (REGRA DE NEGÓCIO)
         * Verifica se as credenciais únicas já existem no banco antes de prosseguir.
         */
        if ($funcionario->buscarPorEmail($email)) {
            http_response_code(409); // Status de Conflito
            echo json_encode(["message" => "Este e-mail já está em uso por outro colaborador."]);
            exit;
        }

        $cpf_somente_numeros = preg_replace('/[^0-9]/', '', $cpf_puro);
        if ($funcionario->buscarPorCpf($cpf_somente_numeros)) {
            http_response_code(409);
            echo json_encode(["message" => "Este CPF já possui uma solicitação de cadastro."]);
            exit;
        }

        /**
         * 3. CRIPTOGRAFIA E ANONIMIZAÇÃO
         * Aplicamos o Hash SHA256 no CPF (para busca segura sem expor o dado real)
         * e BCRYPT na senha (padrão de segurança recomendado).
         */
        $cpfHash = hash('sha256', $cpf_somente_numeros);
        $senhaHash = password_hash($senha_pura, PASSWORD_BCRYPT);

        /**
         * 4. HARDCODED SECURITY (BLINDAGEM DE NÍVEL)
         * Ignoramos qualquer tentativa do JSON de definir o 'nivel' ou 'status'.
         * Todo cadastro via formulário público é forçado para 'USER' e 'PENDENTE'.
         * Isso evita que um usuário mal-intencionado se cadastre como 'ADMIN' via Postman.
         */
        $nivel = 'USER';
        $status = 'PENDENTE';

        // Executa a criação no banco de dados via Model
        if ($funcionario->create($nome, $email, $senhaHash, $cpfHash, $nivel, $status)) {
            echo json_encode([
                "success" => true,
                "message" => "Cadastro realizado com sucesso! Sua conta será revisada por um administrador."
            ]);
        } else {
            http_response_code(500); // Erro interno do servidor
            echo json_encode(["message" => "Erro técnico ao processar sua solicitação de cadastro."]);
        }
    } else {
        http_response_code(400); // Bad Request
        echo json_encode(["message" => "Certifique-se de preencher todos os campos obrigatórios."]);
    }
} else {
    /**
     * Resposta para métodos não autorizados (ex: GET no formulário de registro)
     */
    http_response_code(405);
    echo json_encode(["message" => "Método HTTP não permitido para esta operação."]);
}