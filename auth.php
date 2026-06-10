<?php
// ============================================================
//  Rajo Diagnóstico — Controle de Sessão e Autenticação
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    // Endurecimento dos cookies de sessão: inacessíveis via JS, restritos
    // a navegação same-site e marcados como secure quando servidos por HTTPS
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ─── Proteção CSRF ───────────────────────────────────────────

/**
 * Retorna o token CSRF da sessão atual, gerando-o na primeira chamada.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Campo hidden pronto para uso dentro de <form>.
 */
function csrf_campo(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

/**
 * Valida o token CSRF recebido via POST. Encerra com HTTP 403 se inválido.
 */
function csrf_validar(): void
{
    $recebido = $_POST['csrf_token'] ?? '';
    if (!is_string($recebido) || $recebido === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $recebido)) {
        http_response_code(403);
        die('Falha na validação de segurança (CSRF). Recarregue a página e tente novamente.');
    }
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
