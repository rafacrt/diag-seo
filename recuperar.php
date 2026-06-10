<?php
// ============================================================
//  Rajo Diagnóstico — Solicitação de Recuperação de Senha
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

// Se já estiver logado, redireciona diretamente para o painel
if (esta_logado()) {
    header('Location: index.php');
    exit;
}

$erro = '';
$sucesso = '';
$email_digitado = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_digitado = trim($_POST['email'] ?? '');

    if ($email_digitado === '') {
        $erro = 'Por favor, insira o seu e-mail comercial.';
    } else {
        try {
            // Busca o usuário pelo e-mail
            $stmt = db()->prepare("SELECT id, nome, email FROM usuarios WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email_digitado]);
            $user = $stmt->fetch();

            // Sempre define mensagem de sucesso para evitar User Enumeration
            $sucesso = 'Se este e-mail estiver cadastrado em nosso sistema, um link seguro para redefinição de senha foi enviado. Verifique sua caixa de entrada e pasta de spam.';

            if ($user) {
                // E-mail existe! Gera token e expiração (+1 hora)
                $token = bin2hex(random_bytes(32));
                $expira_em = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // Salva o token de recuperação no banco de dados
                $upd = db()->prepare("UPDATE usuarios SET token_recuperacao = :token, token_recuperacao_expira = :expira WHERE id = :id");
                $upd->execute([
                    ':token'   => $token,
                    ':expira'  => $expira_em,
                    ':id'      => $user['id']
                ]);

                // Constrói o link de recuperação
                $link_recuperacao = rtrim(APP_URL, '/') . "/redefinir.php?token=" . $token;

                // Envia o e-mail transacional via Resend (shell padrão em config.php)
                $assunto = "Recuperação de Senha — Rajo Diagnóstico";
                $corpo_html = email_template(
                    'Recuperação de Senha',
                    'Olá, <strong>' . htmlspecialchars($user['nome']) . '</strong>. Recebemos uma solicitação para redefinir a senha da sua conta de analista. Clique no botão abaixo para criar uma nova senha forte.',
                    'Redefinir Minha Senha',
                    $link_recuperacao,
                    'Este link de segurança expira automaticamente em 1 hora.'
                );

                enviar_email($user['email'], $assunto, $corpo_html);
            }
        } catch (Throwable $e) {
            registrar_log('Erro na recuperação de senha: ' . $e->getMessage(), 'ERROR');
            $erro = 'Não foi possível processar a solicitação. Tente novamente em instantes.';
            $sucesso = ''; // Limpa sucesso em caso de erro real de banco
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha — <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            --background-dark: #0f172a;
            --card-bg: rgba(255, 255, 255, 0.95);
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
            overflow-x: hidden;
        }

        /* Efeito de fundo decorativo premium */
        body::before {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(37, 99, 235, 0.15) 0%, rgba(37, 99, 235, 0) 70%);
            top: -100px;
            right: -100px;
            z-index: 0;
        }

        body::after {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(29, 78, 216, 0.1) 0%, rgba(29, 78, 216, 0) 70%);
            bottom: -150px;
            left: -150px;
            z-index: 0;
        }

        .login-container {
            width: 100%;
            max-width: 440px;
            z-index: 10;
        }

        .login-card {
            background: var(--card-bg);
            border-radius: 24px;
            box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            padding: 40px 35px;
        }

        .brand-logo-container {
            display: flex;
            justify-content: center;
            margin-bottom: 25px;
        }

        .login-title {
            font-family: var(--font-title);
            font-weight: 800;
            color: #1e293b;
            text-align: center;
            margin-bottom: 8px;
            font-size: 1.6rem;
            letter-spacing: -0.02em;
        }

        .login-subtitle {
            text-align: center;
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 30px;
            line-height: 1.4;
        }

        .form-label {
            font-weight: 600;
            font-size: 0.85rem;
            color: #475569;
            margin-bottom: 8px;
        }

        .input-group-custom {
            position: relative;
            margin-bottom: 20px;
        }

        .input-group-custom i.input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1.1rem;
            z-index: 10;
        }

        .form-control-custom {
            width: 100%;
            padding: 12px 20px 12px 46px;
            border-radius: 12px;
            border: 1.5px solid #cbd5e1;
            font-size: 0.95rem;
            background-color: #f8fafc;
            color: #1e293b;
            transition: all 0.25s ease;
        }

        .form-control-custom:focus {
            background-color: #ffffff;
            border-color: #2563eb;
            outline: none;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }

        .form-control-custom:focus + i.input-icon {
            color: #2563eb;
        }

        .btn-submit {
            background: var(--primary-gradient);
            border: none;
            color: #ffffff !important;
            font-weight: 600;
            padding: 13px;
            border-radius: 12px;
            width: 100%;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            box-shadow: 0 8px 16px -4px rgba(37, 99, 235, 0.3);
            margin-top: 10px;
        }

        .btn-submit:hover,
        .btn-submit:focus,
        .btn-submit:active {
            transform: translateY(-2px);
            box-shadow: 0 12px 20px -4px rgba(37, 99, 235, 0.4);
            filter: brightness(1.05);
            color: #ffffff !important;
        }

        .alert-custom {
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 0.85rem;
            margin-bottom: 20px;
            border: none;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            line-height: 1.4;
        }

        .alert-danger-custom {
            background-color: #fef2f2;
            color: #991b1b;
        }

        .footer-text {
            text-align: center;
            font-size: 0.78rem;
            color: #64748b;
            margin-top: 25px;
        }

        .footer-text a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s ease;
        }

        .footer-text a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="login-card">
        <div class="brand-logo-container">
            <img src="logorajodiag.png" alt="Rajo Diagnóstico" style="height: 50px; width: auto; object-fit: contain;">
        </div>
        <h4 class="login-title">Recuperação de Senha</h4>
        <p class="login-subtitle">Insira o e-mail comercial associado à sua conta de analista para receber o link seguro de redefinição.</p>

        <?php if ($erro !== ''): ?>
            <div class="alert-custom alert-danger-custom shadow-sm">
                <i class="bi bi-exclamation-circle-fill" style="margin-top:2px;"></i>
                <div><?= htmlspecialchars($erro) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($sucesso !== ''): ?>
            <div class="alert alert-success border-0 shadow-sm d-flex align-items-start gap-2 p-3 mb-4" style="border-radius: 12px; background-color: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; font-size: 0.82rem; line-height: 1.4;">
                <i class="bi bi-check-circle-fill fs-5 text-success" style="margin-top:1px;"></i>
                <div class="fw-bold"><?= htmlspecialchars($sucesso) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($sucesso === ''): ?>
        <form action="recuperar.php" method="POST" autocomplete="off">
            <div class="mb-3">
                <label for="email" class="form-label">Seu E-mail Comercial</label>
                <div class="input-group-custom">
                    <input type="email" id="email" name="email" 
                           class="form-control-custom" placeholder="exemplo@empresa.com"
                           value="<?= htmlspecialchars($email_digitado) ?>" required autofocus>
                    <i class="bi bi-envelope input-icon"></i>
                </div>
            </div>

            <button type="submit" class="btn btn-submit">
                Enviar Link de Redefinição <i class="bi bi-send ms-2"></i>
            </button>
        </form>
        <?php endif; ?>

        <div class="footer-text mt-4">
            Lembrou sua senha? <a href="login.php">Voltar para o Login</a>
        </div>
    </div>
    
    <div class="footer-text" style="color: #94a3b8; font-size: 0.75rem;">
        &copy; <?= date('Y') ?> Rajo Diagnóstico &bull; Todos os direitos reservados.
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>
