<?php
// ============================================================
//  Rajo Diagnóstico — Controle de Sessão e Autenticação
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Verifica se o usuário atual está autenticado no sistema.
 */
function esta_logado(): bool
{
    return isset($_SESSION['usuario_id']) && !empty($_SESSION['usuario_id']);
}

/**
 * Exige que o usuário esteja logado.
 * Caso contrário, encerra a execução e redireciona ou responde com erro JSON/HTTP 401.
 */
function exigir_login(): void
{
    if (!esta_logado()) {
        // Identifica se a requisição espera um retorno JSON ou se é o arquivo salvar.php
        $url_atual = $_SERVER['PHP_SELF'] ?? '';
        $is_json = (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
                || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
                || (basename($url_atual) === 'salvar.php');

        if ($is_json) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok'  => false,
                'msg' => 'Sessão expirada ou não autorizada. Por favor, recarregue a página e faça login.'
            ]);
            exit;
        }

        header('Location: login.php');
        exit;
    }
}

/**
 * Verifica se o usuário logado possui perfil 'master' (administrador).
 */
function e_master(): bool
{
    return esta_logado() && isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo'] === 'master';
}

/**
 * Exige que o usuário atual possua perfil 'master'.
 * Caso contrário, encerra a execução com erro HTTP 403 ou redireciona para o dashboard.
 */
function exigir_master(): void
{
    exigir_login();

    if (!e_master()) {
        $url_atual = $_SERVER['PHP_SELF'] ?? '';
        $is_json = (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
                || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
                || (basename($url_atual) === 'salvar.php');

        if ($is_json) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok'  => false,
                'msg' => 'Acesso negado. Apenas usuários master possuem permissão para esta ação.'
            ]);
            exit;
        }

        header('Location: index.php');
        exit;
    }
}
