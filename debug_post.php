<?php
// ============================================================
//  Rajo Diagnóstico — Script de Diagnóstico e Depuração de Produção
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

// Apenas analistas Master podem acessar — a checagem anterior
// (esta_logado() && !e_master()) deixava visitantes anônimos passarem
exigir_master();

$mensagem_banco = '';
$mensagem_post = '';
$sucesso_banco = false;

// 1. PROCESSAMENTO DE POST DE TESTE Independent
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao_teste'] ?? '';
    if ($acao === 'testar_post') {
        $mensagem_post = "POST recebido com sucesso no servidor! Dados recebidos: " . json_encode($_POST);
    } elseif ($acao === 'testar_escrita_banco') {
        try {
            db()->beginTransaction();
            
            // Tenta inserir um aviso temporário de teste
            $stmt = db()->prepare("INSERT INTO avisos (titulo, mensagem, tipo, ativo) VALUES ('TESTE_DEBUG_TEMP', 'Aviso temporário para depuração de escrita', 'info', 0)");
            $stmt->execute();
            $id_gerado = db()->lastInsertId();
            
            // Tenta selecionar o aviso inserido
            $stmt_s = db()->prepare("SELECT id FROM avisos WHERE id = ?");
            $stmt_s->execute([$id_gerado]);
            $busca = $stmt_s->fetch();
            
            if ($busca) {
                // Tenta deletar o aviso inserido
                $stmt_d = db()->prepare("DELETE FROM avisos WHERE id = ?");
                $stmt_d->execute([$id_gerado]);
                $sucesso_banco = true;
                $mensagem_banco = "Conexão e operações de Banco de Dados (INSERT, SELECT, DELETE) operando perfeitamente!";
            } else {
                $mensagem_banco = "Erro: O registro inserido no teste de banco não pôde ser recuperado.";
            }
            
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            $mensagem_banco = "Erro ao executar operações de teste no banco: " . $e->getMessage() . " | Linha: " . $e->getLine();
        }
    }
}

// 2. DIAGNÓSTICO GERAL DE SISTEMA
$status_logs = is_writable(__DIR__ . '/logs') || is_writable(__DIR__ . '/logs/sistema.log') 
    ? "Permissão de escrita OK na pasta de logs." 
    : "AVISO: Sem permissão de escrita no diretório de logs.";

// Buscar quantidade de avisos e transações pendentes para relatar
$total_avisos = 0;
$total_transacoes = 0;
try {
    $total_avisos = (int)db()->query("SELECT COUNT(*) FROM avisos")->fetchColumn();
    $total_transacoes = (int)db()->query("SELECT COUNT(*) FROM transacoes WHERE tipo = 'recarga' AND status = 'pendente'")->fetchColumn();
} catch (Throwable $e) {
    $total_avisos = "Erro ao buscar: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Diagnóstico Técnico — Rajo Diagnóstico</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
    <style>
        body { background-color: #0f172a; color: #e2e8f0; font-family: system-ui, -apple-system, sans-serif; }
        .card-debug { background: #1e293b; border: 1px solid #334155; border-radius: 12px; }
        pre { background: #020617; padding: 12px; border-radius: 8px; color: #38bdf8; font-size: 0.85rem; }
    </style>
</head>
<body class="py-5">
<div class="container" style="max-width: 900px;">
    
    <div class="d-flex align-items-center justify-content-between mb-4 border-bottom pb-3 border-secondary">
        <div>
            <h3 class="fw-bold mb-1">Painel de Diagnóstico e Depuração</h3>
            <p class="text-muted mb-0">Use esta ferramenta para validar se o servidor de produção aceita requisições e executa comandos de escrita.</p>
        </div>
        <a href="admin.php" class="btn btn-outline-light btn-sm">Voltar ao Admin</a>
    </div>

    <!-- 1. STATUS DO SERVIDOR E VARIÁVEIS -->
    <div class="card card-debug p-4 mb-4">
        <h5 class="fw-bold mb-3 border-bottom pb-2 border-secondary text-info">1. Informações de Ambiente</h5>
        <div class="row g-3">
            <div class="col-md-6">
                <strong>Método Atual de Requisição:</strong> 
                <span class="badge bg-primary fs-6"><?= htmlspecialchars($_SERVER['REQUEST_METHOD']) ?></span>
            </div>
            <div class="col-md-6">
                <strong>Versão do PHP:</strong> 
                <span class="badge bg-secondary fs-6"><?= phpversion() ?></span>
            </div>
            <div class="col-md-6">
                <strong>Status dos Logs:</strong> <br>
                <small class="text-muted"><?= htmlspecialchars($status_logs) ?></small>
            </div>
            <div class="col-md-6">
                <strong>Sessão Iniciada:</strong> 
                <span class="badge bg-<?= session_status() === PHP_SESSION_ACTIVE ? 'success' : 'danger' ?>">
                    <?= session_status() === PHP_SESSION_ACTIVE ? 'Sim (Ativa)' : 'Não' ?>
                </span>
            </div>
        </div>
    </div>

    <!-- 2. DIAGNÓSTICO DO BANCO DE DADOS -->
    <div class="card card-debug p-4 mb-4">
        <h5 class="fw-bold mb-3 border-bottom pb-2 border-secondary text-info">2. Teste de Operações no Banco</h5>
        <div class="mb-3">
            <strong>Total de Avisos no Banco:</strong> <span class="badge bg-secondary"><?= $total_avisos ?></span> <br>
            <strong>Transações Pendentes:</strong> <span class="badge bg-warning text-dark"><?= $total_transacoes ?></span>
        </div>
        
        <form action="debug_post.php" method="POST" class="mb-3">
            <input type="hidden" name="acao_teste" value="testar_escrita_banco">
            <button type="submit" class="btn btn-warning btn-sm fw-bold">Testar Escrita e Exclusão no Banco</button>
        </form>

        <?php if ($mensagem_banco !== ''): ?>
            <div class="alert alert-<?= $sucesso_banco ? 'success' : 'danger' ?> mb-0 border-0 p-3" style="border-radius: 8px;">
                <strong>Resultado:</strong> <?= htmlspecialchars($mensagem_banco) ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- 3. TESTE DE SUBMISSÃO POST -->
    <div class="card card-debug p-4 mb-4">
        <h5 class="fw-bold mb-3 border-bottom pb-2 border-secondary text-info">3. Teste de Submissão de Formulário (POST)</h5>
        <p class="small text-muted">Clique no botão abaixo para verificar se o servidor de produção recebe e processa o envio POST sem perdas ou redirecionamentos.</p>
        
        <form action="debug_post.php" method="POST" class="mb-3">
            <input type="hidden" name="acao_teste" value="testar_post">
            <input type="hidden" name="campo_aleatorio" value="RajoDev_<?= time() ?>">
            <button type="submit" class="btn btn-primary btn-sm fw-bold">Enviar POST de Teste</button>
        </form>

        <?php if ($mensagem_post !== ''): ?>
            <div class="alert alert-success mb-3 border-0 p-3" style="border-radius: 8px;">
                <?= htmlspecialchars($mensagem_post) ?>
            </div>
        <?php endif; ?>

        <strong>Parâmetros Globais Recebidos nesta requisição:</strong>
        <div class="mt-2">
            <small class="text-muted">$_POST:</small>
            <pre><?= htmlspecialchars(print_r($_POST, true)) ?></pre>
            <small class="text-muted">$_GET:</small>
            <pre><?= htmlspecialchars(print_r($_GET, true)) ?></pre>
            <small class="text-muted">$_SESSION:</small>
            <pre><?= htmlspecialchars(print_r($_SESSION, true)) ?></pre>
        </div>
    </div>

</div>
</body>
</html>
