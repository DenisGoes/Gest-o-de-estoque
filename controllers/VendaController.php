<?php

/**
 * CONTROLLER DE VENDAS
 * -------------------
 * Gerencia o registro de novas transações comerciais e a listagem do histórico.
 * Este controller foca na integridade do vendedor e na validação dos itens vendidos.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Venda.php';
require_once __DIR__ . '/../utils/Auth.php';

/**
 * 1. CAMADA DE AUTORIZAÇÃO
 * Garante que apenas usuários autenticados (ADMIN ou USER) acessem as rotas de venda.
 */
Auth::check();

// Configurações de CORS para integração com o Front-end (ex: React/Vite)
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Credentials: true");

$venda = new Venda($pdo);
$metodo = $_SERVER['REQUEST_METHOD'];
$dados = json_decode(file_get_contents("php://input"));

switch ($metodo) {
    case 'GET':
        /**
         * HISTÓRICO DE VENDAS
         * Retorna a lista de todas as vendas realizadas.
         */
        echo json_encode($venda->read());
        break;

    case 'POST':
        /**
         * REGISTRO DE NOVA VENDA
         */
        try {
            /**
             * 2. SEGURANÇA DE IDENTIDADE (PULO DO GATO)
             * Em vez de confiar no ID enviado pelo JSON (que poderia ser manipulado), 
             * extraímos o ID do funcionário diretamente da $_SESSION segura.
             * Isso garante que ninguém registre uma venda em nome de outro colega.
             */
            $id_funcionario_logado = $_SESSION['usuario_id'];

            // Sanitização da forma de pagamento
            $forma_pagamento = htmlspecialchars(strip_tags($dados->forma_pagamento ?? 'Dinheiro'));
            $itens_brutos = $dados->itens ?? [];

            // Validação: Uma venda deve conter ao menos um item
            if (empty($itens_brutos)) {
                throw new Exception("A lista de itens não pode estar vazia.");
            }

            /**
             * 3. SANITIZAÇÃO E VALIDAÇÃO DE ITENS
             * Percorremos a lista de produtos vendidos para garantir tipos de dados
             * corretos e prevenir valores negativos (que quebrariam o estoque).
             */
            $itens_limpos = [];
            foreach ($itens_brutos as $item) {
                $qtd = filter_var($item->quantidade_venda, FILTER_SANITIZE_NUMBER_INT);

                if ($qtd <= 0) {
                    throw new Exception("Quantidade inválida para o produto ID: " . $item->id_produto);
                }

                $itens_limpos[] = (object)[
                    "id_produto" => filter_var($item->id_produto, FILTER_SANITIZE_NUMBER_INT),
                    "quantidade_venda" => $qtd,
                    "preco_venda_unitario" => filter_var($item->preco_venda_unitario, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)
                ];
            }

            /**
             * 4. EXECUÇÃO DA TRANSAÇÃO
             * O Model Venda deve processar isso preferencialmente dentro de uma TRANSACTION SQL
             * para garantir que a venda só seja salva se o estoque puder ser baixado.
             */
            if ($venda->create($id_funcionario_logado, $forma_pagamento, $itens_limpos)) {
                echo json_encode([
                    "success" => true,
                    "message" => "Venda registrada com sucesso por " . $_SESSION['usuario_nome'] . "!",
                    "vendedor_id" => $id_funcionario_logado
                ]);
            }
        } catch (Exception $e) {
            /**
             * TRATAMENTO DE EXCEÇÕES
             * Captura erros de validação ou falta de estoque e retorna Status 400.
             */
            http_response_code(400);
            echo json_encode(["message" => "Erro no processamento da venda: " . $e->getMessage()]);
        }
        break;

    default:
        /**
         * RESPOSTA PARA MÉTODOS NÃO IMPLEMENTADOS
         */
        http_response_code(405);
        echo json_encode(["message" => "Método não permitido."]);
        break;
}
