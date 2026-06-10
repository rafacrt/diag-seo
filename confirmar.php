<?php
// ============================================================
//  Rajo Diagnóstico — Confirmação de E-mail (SaaS)
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

$token = trim($_REQUEST['token'] ?? '');
$erro = '';
$sucesso = '';
$mostrar_confirmacao = false;
$user = null;

if ($token === '') {
    $erro = 'Parâmetro de ativação inválido ou ausente.';
} else {
    try {
        // Buscar usuário com o token
        $stmt = db()->prepare("SELECT * FROM usuarios WHERE token_confirmacao = :token LIMIT 1");
        $stmt->execute([':token' => $token]);
        $user = $stmt->fetch();

        if (!$user) {
            $erro = 'O link de ativação é inválido ou já foi utilizado anteriormente.';
        } else {
            // Verificar expiração do token (24 horas)
            $agora = new DateTime();
            $expira = new DateTime($user['token_expira']);

            if ($agora > $expira) {
                $erro = 'Este link de ativação expirou (validade de 24 horas). Por favor, realize o cadastro novamente para receber um novo e-mail.';
                // Opcional: remover registro expirado não confirmado para permitir novo cadastro
                db()->prepare("DELETE FROM usuarios WHERE id = ? AND confirmado = 0")->execute([$user['id']]);
            } else {
                // Se for requisição POST, realiza a ativação
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    // Confirmar conta
                    $upd = db()->prepare("UPDATE usuarios SET confirmado = 1, token_confirmacao = NULL, token_expira = NULL WHERE id = ?");
                    $upd->execute([$user['id']]);
                    $sucesso = 'Sua conta de analista foi ativada com sucesso! Você já pode entrar no sistema.';
                } else {
                    // Se for GET, exibe a tela intermediária
                    $mostrar_confirmacao = true;
                }
            }
        }
    } catch (Throwable $e) {
        registrar_log('Erro ao validar ativação: ' . $e->getMessage(), 'ERROR');
        $erro = 'Erro interno ao validar a ativação. Tente novamente em instantes.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ativação de Conta — <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            --font-title: 'Outfit', 'Plus Jakarta Sans', sans-serif;
            --font-body: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            font-family: var(--font-body);
            background: linear-gradient(180deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #334155;
            position: relative;
        }

        .container-box {
            width: 100%;
            max-width: 460px;
            z-index: 10;
        }

        .status-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 24px;
            box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            padding: 45px 35px;
            text-align: center;
        }

        .icon-circle {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px auto;
            font-size: 2.2rem;
            box-shadow: 0 10px 20px -5px rgba(0,0,0,0.05);
        }

        .icon-success {
            background-color: #f0fdf4;
            color: #16a34a;
            box-shadow: 0 10px 20px -5px rgba(22, 163, 74, 0.2);
        }

        .icon-danger {
            background-color: #fef2f2;
            color: #dc2626;
            box-shadow: 0 10px 20px -5px rgba(220, 38, 38, 0.2);
        }

        .icon-warning {
            background-color: #fffbeb;
            color: #d97706;
            box-shadow: 0 10px 20px -5px rgba(217, 119, 6, 0.2);
        }

        .status-title {
            font-family: var(--font-title);
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 12px;
            font-size: 1.4rem;
        }

        .status-text {
            color: #64748b;
            font-size: 0.92rem;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .btn-action {
            background: var(--primary-gradient);
            border: none;
            color: #ffffff !important;
            font-weight: 600;
            padding: 12px 30px;
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            box-shadow: 0 8px 16px -4px rgba(37, 99, 235, 0.3);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-action:hover,
        .btn-action:focus,
        .btn-action:active {
            transform: translateY(-2px);
            box-shadow: 0 12px 20px -4px rgba(37, 99, 235, 0.4);
            filter: brightness(1.05);
            color: #ffffff !important;
        }

        .btn-secondary-action {
            background-color: #f1f5f9;
            color: #475569;
            box-shadow: none;
            border: 1px solid #cbd5e1;
        }

        .btn-secondary-action:hover {
            background-color: #e2e8f0;
            color: #1e293b;
            transform: translateY(-2px);
            box-shadow: none;
        }

        .footer-text {
            text-align: center;
            font-size: 0.75rem;
            color: #94a3b8;
            margin-top: 25px;
        }
    </style>
</head>
<body>

<div class="container-box">
    <div class="status-card shadow">
        <?php if ($sucesso !== ''): ?>
            <div class="icon-circle icon-success">
                <i class="bi bi-shield-check"></i>
            </div>
            <h4 class="status-title">Ativação Concluída</h4>
            <p class="status-text"><?= htmlspecialchars($sucesso) ?></p>
            <a href="login.php" class="btn btn-action">
                Ir para o Login <i class="bi bi-arrow-right"></i>
            </a>
        <?php elseif ($mostrar_confirmacao): ?>
            <div class="icon-circle icon-warning">
                <i class="bi bi-shield-lock-fill"></i>
            </div>
            <h4 class="status-title">Confirme sua Ativação</h4>
            <p class="status-text text-start px-2">
                Olá, <strong><?= htmlspecialchars($user['nome'] ?? 'analista') ?></strong>. Para concluir a ativação de sua conta de analista no <?= htmlspecialchars(APP_NAME) ?>, por favor clique no botão abaixo.
            </p>
            <form method="POST" action="confirmar.php?token=<?= urlencode($token) ?>" class="px-2">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <button type="submit" class="btn btn-action w-100 py-3">
                    Confirmar Ativação de Conta <i class="bi bi-check-circle-fill"></i>
                </button>
            </form>
        <?php else: ?>
            <div class="icon-circle icon-danger">
                <i class="bi bi-shield-exclamation"></i>
            </div>
            <h4 class="status-title">Falha na Ativação</h4>
            <p class="status-text"><?= htmlspecialchars($erro) ?></p>
            <a href="cadastro.php" class="btn btn-action btn-secondary-action">
                <i class="bi bi-arrow-left"></i> Voltar ao Cadastro
            </a>
        <?php endif; ?>
    </div>
    
    <div class="footer-text">
        &copy; <?= date('Y') ?> Rajo Diagnóstico &bull; Todos os direitos reservados.
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>
