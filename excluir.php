<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
exigir_login();
$id = (int)($_GET['id'] ?? 0);
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
