<?php
// ============================================================
//  Rajo Diagnóstico — Cadastro de Usuário (SaaS)
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
$nome_digitado = '';
$email_digitado = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome_digitado = trim($_POST['nome'] ?? '');
    $email_digitado = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';

    // Validações básicas
    if ($nome_digitado === '' || $email_digitado === '' || $senha === '' || $confirmar_senha === '') {
        $erro = 'Por favor, preencha todos os campos.';
    } elseif (!filter_var($email_digitado, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Por favor, informe um endereço de e-mail válido.';
    } elseif ($senha !== $confirmar_senha) {
        $erro = 'As senhas digitadas não coincidem.';
    } else {
        // Validação rigorosa de senha forte
        $tem_maiuscula = preg_match('/[A-Z]/', $senha);
        $tem_minuscula = preg_match('/[a-z]/', $senha);
        $tem_numero = preg_match('/[0-9]/', $senha);
        $tem_especial = preg_match('/[^A-Za-z0-9]/', $senha);
        $tamanho_ok = strlen($senha) >= 8;

        if (!$tamanho_ok || !$tem_maiuscula || !$tem_minuscula || !$tem_numero || !$tem_especial) {
            $erro = 'A senha não atende aos requisitos mínimos de segurança.';
        } else {
            try {
                // Verificar se o e-mail já existe
                $stmt = db()->prepare("SELECT id FROM usuarios WHERE email = :email LIMIT 1");
                $stmt->execute([':email' => $email_digitado]);
                if ($stmt->fetch()) {
                    $erro = 'Este endereço de e-mail já está cadastrado no sistema.';
                } else {
                    // Criptografar a senha com padrão seguro de produção (PASSWORD_DEFAULT usa bcrypt robusto)
                    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

                    // Gerar tokens de confirmação de e-mail
                    $token = bin2hex(random_bytes(32));
                    $expira_em = date('Y-m-d H:i:s', strtotime('+24 hours'));

                    // Gravar usuário no banco. Se a constante exigir ativação for false, cria já confirmado.
                    $confirmado_inicial = defined('EXIGIR_ATIVACAO_EMAIL') && !EXIGIR_ATIVACAO_EMAIL ? 1 : 0;

                    $ins = db()->prepare("INSERT INTO usuarios (nome, email, senha, confirmado, token_confirmacao, token_expira, bonus_relatorios) 
                                           VALUES (:nome, :email, :senha, :confirmado, :token, :expira, 5)");
                    $ins->execute([
                        ':nome' => $nome_digitado,
                        ':email' => $email_digitado,
                        ':senha' => $senha_hash,
                        ':confirmado' => $confirmado_inicial,
                        ':token' => $confirmado_inicial ? null : $token,
                        ':expira' => $confirmado_inicial ? null : $expira_em
                    ]);

                    $usuario_id = db()->lastInsertId();

                    // Registrar transação de bônus de boas-vindas no extrato
                    $stmt_log = db()->prepare("INSERT INTO transacoes (usuario_id, tipo, valor, descricao, status) VALUES (?, 'bonus', 0.00, ?, 'concluido')");
                    $stmt_log->execute([$usuario_id, "Bônus de boas-vindas: 5 relatórios grátis"]);

                    if ($confirmado_inicial) {
                        $sucesso = 'Cadastro realizado e ativado com sucesso! Você já pode realizar o login na tela abaixo.';
                        $nome_digitado = '';
                        $email_digitado = '';
                    } else {
                        // Montar link de confirmação usando a URL do aplicativo
                        $link_confirmacao = rtrim(APP_URL, '/') . "/confirmar.php?token=" . $token;

                        // Corpo do e-mail transacional (shell padrão em config.php)
                        $assunto = "Ative sua conta — Rajo Diagnóstico";
                        $corpo_html = email_template(
                            'Ativação de Conta',
                            'Olá, <strong>' . htmlspecialchars($nome_digitado) . '</strong>! Obrigado por se cadastrar no Rajo Diagnóstico. Por favor, clique no botão abaixo para ativar a sua conta de analista.',
                            'Ativar Minha Conta',
                            $link_confirmacao,
                            'Este link é válido por 24 horas.'
                        );

                        // Enviar e-mail de ativação
                        if (enviar_email($email_digitado, $assunto, $corpo_html)) {
                            $sucesso = 'Cadastro realizado com sucesso! Enviamos um link de confirmação para o seu e-mail. Por favor, verifique sua caixa de entrada (ou spam).';
                            $nome_digitado = '';
                            $email_digitado = '';
                        } else {
                            // Exceção ou falha no envio de e-mail, mas o usuário foi gravado. Exclui para que tente novamente.
                            db()->prepare("DELETE FROM usuarios WHERE email = :email")->execute([':email' => $email_digitado]);
                            $erro = 'Não foi possível enviar o e-mail de ativação. Verifique se o endereço de e-mail está correto e tente novamente.';
                        }
                    }
                }
            } catch (Throwable $e) {
                registrar_log('Erro no cadastro: ' . $e->getMessage(), 'ERROR');
                $erro = 'Erro interno ao concluir o cadastro. Tente novamente em instantes.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro — <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Outfit:wght@300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
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
            padding: 40px 20px;
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
            max-width: 480px;
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

        .brand-logo-container {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }

        .brand-logo {
            width: 46px;
            height: 46px;
            background: var(--primary-gradient);
            color: #ffffff;
            font-family: var(--font-title);
            font-weight: 900;
            font-size: 1.6rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            box-shadow: 0 10px 20px -5px rgba(37, 99, 235, 0.4);
        }

        .login-title {
            font-family: var(--font-title);
            font-weight: 800;
            color: #1e293b;
            text-align: center;
            margin-bottom: 6px;
            font-size: 1.5rem;
            letter-spacing: -0.02em;
        }

        .login-subtitle {
            text-align: center;
            color: #64748b;
            font-size: 0.85rem;
            margin-bottom: 25px;
        }

        .form-label {
            font-weight: 600;
            font-size: 0.85rem;
            color: #475569;
            margin-bottom: 6px;
        }

        .input-group-custom {
            position: relative;
            margin-bottom: 16px;
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
            padding: 11px 46px 11px 46px;
            border-radius: 12px;
            border: 1.5px solid #cbd5e1;
            font-size: 0.9rem;
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

        .form-control-custom:focus+i.input-icon {
            color: #2563eb;
        }

        /* Indicador de senha forte */
        .password-strength-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 20px;
            font-size: 0.76rem;
        }

        .strength-title {
            font-weight: 700;
            color: #475569;
            margin-bottom: 6px;
        }

        .strength-rule {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #94a3b8;
            margin-bottom: 4px;
            font-weight: 500;
            transition: color 0.2s ease;
        }

        .strength-rule.met {
            color: #16a34a;
        }

        .strength-rule i {
            font-size: 0.85rem;
        }

        .btn-register {
            background: var(--primary-gradient);
            border: none;
            color: #ffffff !important;
            font-weight: 600;
            padding: 12px;
            border-radius: 12px;
            width: 100%;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            box-shadow: 0 8px 16px -4px rgba(37, 99, 235, 0.3);
            margin-top: 5px;
        }

        .btn-register:hover,
        .btn-register:focus,
        .btn-register:active {
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
        }

        .alert-danger-custom {
            background-color: #fef2f2;
            color: #991b1b;
        }

        .alert-success-custom {
            background-color: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .footer-text {
            text-align: center;
            font-size: 0.78rem;
            color: #64748b;
            margin-top: 20px;
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
            <h4 class="login-title">Crie sua Conta</h4>
            <p class="login-subtitle">Junte-se à plataforma e comece a emitir diagnósticos técnicos</p>

            <?php if ($erro !== ''): ?>
                <div class="alert-custom alert-danger-custom">
                    <i class="bi bi-exclamation-circle-fill" style="margin-top:2px;"></i>
                    <div><?= htmlspecialchars($erro) ?></div>
                </div>
            <?php endif; ?>

            <?php if ($sucesso !== ''): ?>
                <div class="alert-custom alert-success-custom">
                    <i class="bi bi-check-circle-fill" style="margin-top:2px;"></i>
                    <div><?= htmlspecialchars($sucesso) ?></div>
                </div>
            <?php endif; ?>

            <?php if ($sucesso === ''): ?>
                <form action="cadastro.php" method="POST" autocomplete="off" onsubmit="return validarEnvio(event)">
                    <div class="mb-2">
                        <label for="nome" class="form-label">Nome Completo</label>
                        <div class="input-group-custom">
                            <input type="text" id="nome" name="nome" class="form-control-custom"
                                placeholder="Como você quer ser chamado..." value="<?= htmlspecialchars($nome_digitado) ?>"
                                required autofocus>
                            <i class="bi bi-person input-icon"></i>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label for="email" class="form-label">E-mail Comercial</label>
                        <div class="input-group-custom">
                            <input type="email" id="email" name="email" class="form-control-custom"
                                placeholder="exemplo@empresa.com" value="<?= htmlspecialchars($email_digitado) ?>" required>
                            <i class="bi bi-envelope input-icon"></i>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label for="senha" class="form-label">Crie uma Senha Forte</label>
                        <div class="input-group-custom">
                            <input type="password" id="senha" name="senha" class="form-control-custom"
                                placeholder="Mínimo 8 caracteres..." required onkeyup="analisarSenha(this.value)">
                            <i class="bi bi-lock input-icon"></i>
                            <button type="button" class="btn-toggle-pass" onclick="toggleSenha('senha', this)"
                                title="Exibir/ocultar senha">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label for="confirmar_senha" class="form-label">Confirme a Senha</label>
                        <div class="input-group-custom">
                            <input type="password" id="confirmar_senha" name="confirmar_senha" class="form-control-custom"
                                placeholder="Repita a senha criada..." required>
                            <i class="bi bi-lock-fill input-icon"></i>
                            <button type="button" class="btn-toggle-pass" onclick="toggleSenha('confirmar_senha', this)"
                                title="Exibir/ocultar senha">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Box Dinâmico de Força de Senha -->
                    <div class="password-strength-box">
                        <div class="strength-title">A senha deve conter:</div>
                        <div class="strength-rule" id="rule-len"><i class="bi bi-circle"></i> No mínimo 8 caracteres</div>
                        <div class="strength-rule" id="rule-upper"><i class="bi bi-circle"></i> Pelo menos uma letra
                            maiúscula (A-Z)</div>
                        <div class="strength-rule" id="rule-lower"><i class="bi bi-circle"></i> Pelo menos uma letra
                            minúscula (a-z)</div>
                        <div class="strength-rule" id="rule-num"><i class="bi bi-circle"></i> Pelo menos um número (0-9)
                        </div>
                        <div class="strength-rule" id="rule-special"><i class="bi bi-circle"></i> Pelo menos um caractere
                            especial (!@#$...)</div>
                    </div>

                    <button type="submit" class="btn btn-register">
                        Registrar e Enviar Ativação <i class="bi bi-arrow-right ms-2"></i>
                    </button>
                </form>
            <?php endif; ?>

            <div class="footer-text mt-4">
                Já possui uma conta de analista? <a href="login.php">Fazer Login</a>
            </div>
        </div>

        <div class="footer-text" style="color: #94a3b8; font-size: 0.75rem;">
            &copy; <?= date('Y') ?> Rajo Diagnóstico &bull; Todos os direitos reservados.
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
        let senhaValida = false;

        function toggleSenha(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'bi bi-eye';
            }
        }

        function analisarSenha(senha) {
            const rules = {
                len: senha.length >= 8,
                upper: /[A-Z]/.test(senha),
                lower: /[a-z]/.test(senha),
                num: /[0-9]/.test(senha),
                special: /[^A-Za-z0-9]/.test(senha)
            };

            atualizarRegra('rule-len', rules.len);
            atualizarRegra('rule-upper', rules.upper);
            atualizarRegra('rule-lower', rules.lower);
            atualizarRegra('rule-num', rules.num);
            atualizarRegra('rule-special', rules.special);

            senhaValida = rules.len && rules.upper && rules.lower && rules.num && rules.special;
        }

        function atualizarRegra(elementId, met) {
            const element = document.getElementById(elementId);
            const icon = element.querySelector('i');
            if (met) {
                element.className = 'strength-rule met';
                icon.className = 'bi bi-check-circle-fill';
            } else {
                element.className = 'strength-rule';
                icon.className = 'bi bi-circle';
            }
        }

        function validarEnvio(e) {
            const senha = document.getElementById('senha').value;
            const confirmar = document.getElementById('confirmar_senha').value;

            if (!senhaValida) {
                alert('Sua senha não atende aos critérios mínimos de segurança corporativa.');
                return false;
            }

            if (senha !== confirmar) {
                alert('As senhas informadas não correspondem.');
                return false;
            }

            return true;
        }
    </script>
</body>

</html>