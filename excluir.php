<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
exigir_login();

// Exclusão é ação destrutiva: só via POST com token CSRF válido
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Método não permitido. A exclusão deve ser feita pelo painel.');
}
csrf_validar();

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    if (e_master()) {
        // Master tem permissão livre para excluir qualquer diagnóstico
        $stmt = db()->prepare('DELETE FROM relatorios WHERE id = ?');
        $stmt->execute([$id]);
    } else {
        // Analistas comuns só excluem seus próprios relatórios
        $stmt = db()->prepare('DELETE FROM relatorios WHERE id = ? AND usuario_id = ?');
        $stmt->execute([$id, $_SESSION['usuario_id']]);
    }
}
header('Location: index.php');
exit;
