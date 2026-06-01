<?php
require_once __DIR__ . '/../config.php';

try {
    $pdo = db();
    
    // Lista colunas da tabela usuarios
    $stmt = $pdo->query("SHOW COLUMNS FROM usuarios");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Colunas encontradas na tabela 'usuarios':\n";
    foreach ($columns as $col) {
        echo "- " . $col . "\n";
    }
    
    $tem_token = in_array('token_recuperacao', $columns);
    $tem_expira = in_array('token_recuperacao_expira', $columns);
    
    if ($tem_token && $tem_expira) {
        echo "\n[SUCESSO] As novas colunas de recuperação de senha foram migradas com sucesso!\n";
    } else {
        echo "\n[ERRO] Colunas ausentes no banco de dados local.\n";
    }
} catch (Throwable $e) {
    echo "Erro ao conectar ou buscar colunas: " . $e->getMessage() . "\n";
}
