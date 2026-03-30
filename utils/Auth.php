<?php

/**
 * CLASSE DE UTILITÁRIO: AUTH
 * --------------------------
 * Responsável pelo gerenciamento de sessões seguras e controle de acesso (RBAC).
 * Centraliza as verificações de nível de permissão (ADMIN/USER) em toda a API.
 */

class Auth
{
    /**
     * Verifica se o usuário logado possui perfil de administrador.
     */
    public static function isAdmin()
    {
        self::initSession();
        return isset($_SESSION['usuario_nivel']) && $_SESSION['usuario_nivel'] === 'ADMIN';
    }

    /**
     * Inicializa a sessão de forma segura, evitando erros de duplicidade.
     */
    private static function initSession()
    {
        // Só inicia se já não houver uma sessão ativa no servidor
        if (session_status() === PHP_SESSION_NONE) {
            /**
             * NOTA DE SEGURANÇA: 
             * Em produção, recomenda-se configurar session.cookie_httponly aqui
             * para impedir acesso aos cookies via JavaScript (XSS).
             */
            session_start();
        }
    }

    /**
     * Encerra a sessão e limpa TODOS os vestígios no servidor e no navegador.
     */
    public static function logout()
    {
        self::initSession();

        // 1. Limpa as variáveis da superglobal $_SESSION
        $_SESSION = array();

        // 2. Destrói a sessão no servidor
        session_destroy();

        /**
         * 3. LIMPEZA DE COOKIES (HARD CLEANUP)
         * Remove o cookie da sessão no navegador do usuário, garantindo 
         * que a identificação antiga não possa ser reutilizada.
         */
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
    }

    /**
     * Preenche a sessão com os dados do funcionário após validação de senha.
     * @param array $usuario Dados vindos do Model Funcionario.
     */
    public static function login($usuario)
    {
        self::initSession();

        // Regenera o ID da sessão para prevenir Session Fixation
        session_regenerate_id(true);

        $_SESSION['usuario_id'] = $usuario['id_funcionario'];
        $_SESSION['usuario_nome'] = $usuario['nome_completo'];
        $_SESSION['usuario_nivel'] = $usuario['nivel_acesso'];
        $_SESSION['last_activity'] = time(); // Timestamp para controle de timeout
    }

    /**
     * MIDDLEWARE DE PROTEÇÃO
     * ---------------------
     * Bloqueia o acesso a rotas protegidas.
     * @param string|null $nivelRequerido 'ADMIN' ou 'USER'.
     */
    public static function check($nivelRequerido = null)
    {
        self::initSession();

        // Verifica se o usuário está autenticado
        if (!isset($_SESSION['usuario_id'])) {
            http_response_code(401); // Unauthorized
            echo json_encode(["message" => "Acesso negado. Por favor, realize o login."]);
            exit;
        }

        /**
         * CONTROLE DE ACESSO BASEADO EM FUNÇÕES (RBAC)
         * Se a rota exige 'ADMIN' e o usuário é 'USER', bloqueamos com 403.
         */
        if ($nivelRequerido && $_SESSION['usuario_nivel'] !== $nivelRequerido) {
            http_response_code(403); // Forbidden
            echo json_encode(["message" => "Permissão insuficiente para acessar este recurso."]);
            exit;
        }
    }
}
