<?php
// ============================================================
//  Rajo Diagnóstico — Tela de Login (SaaS)
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
    $senha = $_POST['senha'] ?? '';

    if ($email_digitado === '' || $senha === '') {
        $erro = 'Por favor, insira o seu e-mail e a senha.';
    } else {
        try {
            $stmt = db()->prepare("SELECT * FROM usuarios WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email_digitado]);
            $user = $stmt->fetch();

            if ($user && password_verify($senha, $user['senha'])) {
                // Credenciais válidas! Agora verifica se a conta está ativada (dupla confirmação)
                if ((int)$user['confirmado'] === 0) {
                    // Reenviar o link de ativação automaticamente
                    $token = bin2hex(random_bytes(32));
                    $expira_em = date('Y-m-d H:i:s', strtotime('+24 hours'));

                    // Atualizar tokens no banco
                    $upd = db()->prepare("UPDATE usuarios SET token_confirmacao = :token, token_expira = :expira WHERE id = :id");
                    $upd->execute([
                        ':token'   => $token,
                        ':expira'  => $expira_em,
                        ':id'      => $user['id']
                    ]);

                    $link_confirmacao = rtrim(APP_URL, '/') . "/confirmar.php?token=" . $token;

                    // Enviar o e-mail via Resend
                    $assunto = "Ative sua conta — Rajo Diagnóstico";
                    $corpo_html = '
                    <div style="font-family: \'Helvetica Neue\', Helvetica, Arial, sans-serif; background-color: #f8fafc; padding: 40px 20px; color: #334155; line-height: 1.6;">
                        <div style="max-width: 500px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; box-shadow: 0 10px 30px rgba(15,23,42,0.05); border: 1px solid #e2e8f0; padding: 40px; text-align: center;">
                            <div style="display: inline-block; width: 44px; height: 44px; background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: #ffffff; font-weight: 900; font-size: 1.5rem; line-height: 44px; border-radius: 10px; margin-bottom: 20px;">R</div>
                            <h2 style="font-size: 1.5rem; color: #1e293b; font-weight: 700; margin-top: 0; margin-bottom: 10px;">Ative sua Conta de Analista</h2>
                            <p style="font-size: 0.95rem; color: #64748b; margin-bottom: 30px;">Identificamos que você tentou realizar o login, mas sua conta ainda não foi ativada. Clique no botão abaixo para concluir a ativação.</p>
                            <a href="' . $link_confirmacao . '" style="display: inline-block; background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: #ffffff; text-decoration: none; font-weight: 600; font-size: 0.9rem; padding: 12px 30px; border-radius: 10px; box-shadow: 0 8px 16px rgba(37,99,235,0.25); transition: background 0.3s ease;">Ativar Minha Conta</a>
                            <p style="font-size: 0.8rem; color: #94a3b8; margin-top: 35px; border-top: 1px solid #f1f5f9; padding-top: 20px;">Se o botão não funcionar, copie e cole o link no seu navegador:<br><a href="' . $link_confirmacao . '" style="color: #2563eb; text-decoration: none;">' . $link_confirmacao . '</a></p>
                            <p style="font-size: 0.75rem; color: #94a3b8; margin-top: 15px;">Este link é válido por 24 horas.</p>
                        </div>
                    </div>';

                    enviar_email($user['email'], $assunto, $corpo_html);

                    $erro = 'Sua conta ainda não está ativada. Um novo link de confirmação foi enviado para o seu e-mail.';
                } else {
                    // Autenticação concluída com sucesso
                    $_SESSION['usuario_id'] = $user['id'];
                    $_SESSION['usuario_nome'] = $user['nome'] ?? $user['email'];
                    $_SESSION['usuario_login'] = $user['email'];
                    $_SESSION['usuario_tipo'] = $user['tipo'] ?? 'comum';
                    
                    header('Location: index.php');
                    exit;
                }
            } else {
                $erro = 'E-mail ou senha incorretos.';
            }
        } catch (Throwable $e) {
            $erro = 'Erro ao processar login: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar — <?= APP_NAME ?></title>
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
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .login-card:hover {
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.15);
        }

        .brand-logo-container {
            display: flex;
            justify-content: center;
            margin-bottom: 25px;
        }

        .brand-logo {
            width: 50px;
            height: 50px;
            background: var(--primary-gradient);
            color: #ffffff;
            font-family: var(--font-title);
            font-weight: 900;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            box-shadow: 0 10px 20px -5px rgba(37, 99, 235, 0.4);
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
            transition: color 0.2s ease;
        }

        .input-group-custom .btn-toggle-pass {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #94a3b8;
            font-size: 1.1rem;
            padding: 0;
            cursor: pointer;
            z-index: 12;
            transition: color 0.2s ease;
        }

        .input-group-custom .btn-toggle-pass:hover {
            color: #2563eb;
        }

        .form-control-custom {
            width: 100%;
            padding: 12px 46px 12px 46px;
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

        .btn-login {
            background: var(--primary-gradient);
            border: none;
            color: white;
            font-weight: 600;
            padding: 13px;
            border-radius: 12px;
            width: 100%;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            box-shadow: 0 8px 16px -4px rgba(37, 99, 235, 0.3);
            margin-top: 10px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 20px -4px rgba(37, 99, 235, 0.4);
            filter: brightness(1.05);
        }

        .btn-login:active {
            transform: translateY(0);
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
            <div class="brand-logo">R</div>
        </div>
        <h4 class="login-title">Rajo Diagnóstico</h4>
        <p class="login-subtitle">Entre com a sua conta SaaS de analista</p>

        <?php if ($erro !== ''): ?>
            <div class="alert-custom alert-danger-custom shadow-sm">
                <i class="bi bi-exclamation-circle-fill" style="margin-top:2px;"></i>
                <div><?= htmlspecialchars($erro) ?></div>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" autocomplete="off">
            <div class="mb-3">
                <label for="email" class="form-label">E-mail Comercial</label>
                <div class="input-group-custom">
                    <input type="email" id="email" name="email" 
                           class="form-control-custom" placeholder="exemplo@empresa.com"
                           value="<?= htmlspecialchars($email_digitado) ?>" required autofocus>
                    <i class="bi bi-envelope input-icon"></i>
                </div>
            </div>

            <div class="mb-3">
                <label for="senha" class="form-label">Sua Senha</label>
                <div class="input-group-custom">
                    <input type="password" id="senha" name="senha" 
                           class="form-control-custom" placeholder="Digite sua senha..." required>
                    <i class="bi bi-lock input-icon"></i>
                    <button type="button" class="btn-toggle-pass" onclick="toggleSenha(this)" title="Exibir/ocultar senha">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-login">
                Entrar no Painel <i class="bi bi-arrow-right ms-2"></i>
            </button>
        </form>

        <div class="footer-text mt-4">
            Ainda não tem cadastro comercial? <a href="cadastro.php">Crie uma conta</a>
        </div>
    </div>
    
    <div class="footer-text" style="color: #94a3b8; font-size: 0.75rem;">
        &copy; <?= date('Y') ?> Rajo Diagnóstico &bull; Todos os direitos reservados.
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script>
function toggleSenha(button) {
    const input = document.getElementById('senha');
    const icon = button.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}
</script>
</body>
</html>
