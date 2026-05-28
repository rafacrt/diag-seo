<?php
// ============================================================
//  Rajo Diagnóstico — Encerramento de Sessão (Logout)
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Limpa todas as variáveis de sessão
$_SESSION = [];

// Destrói o cookie da sessão se estiver ativo
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destrói a sessão em si
session_destroy();

// Redireciona para a página de login
header('Location: login.php');
exit;
