<?php
// ============================================================
//  Rajo Diagnóstico — Redefinição de Senha
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
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$token_valido = false;
$usuario = null;

if ($token !== '') {
    try {
        // Valida se o token existe e não está expirado
        $stmt = db()->prepare("SELECT id, nome, email, token_recuperacao_expira FROM usuarios WHERE token_recuperacao = :token LIMIT 1");
        $stmt->execute([':token' => $token]);
        $usuario = $stmt->fetch();

        if ($usuario) {
            $data_expira = strtotime($usuario['token_recuperacao_expira']);
            $data_atual = time();

            if ($data_atual <= $data_expira) {
                $token_valido = true;
            } else {
                $erro = 'Este link de recuperação de senha expirou. Por favor, solicite um novo link.';
            }
        } else {
            $erro = 'Link de recuperação de senha inválido ou já utilizado.';
        }
    } catch (Throwable $e) {
        registrar_log('Erro ao validar link de redefinição: ' . $e->getMessage(), 'ERROR');
        $erro = 'Não foi possível validar o link. Tente novamente em instantes.';
    }
} else {
    $erro = 'Código de segurança de redefinição ausente.';
}

// Processa gravação da nova senha
if ($token_valido && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nova_senha'])) {
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';

    if ($nova_senha === '' || $confirmar_senha === '') {
        $erro = 'Preencha a nova senha e a confirmação.';
    } elseif ($nova_senha !== $confirmar_senha) {
        $erro = 'As senhas informadas não correspondem.';
    } else {
        // Validação estrita de força da senha
        $tem_maiuscula = preg_match('/[A-Z]/', $nova_senha);
        $tem_minuscula = preg_match('/[a-z]/', $nova_senha);
        $tem_numero    = preg_match('/[0-9]/', $nova_senha);
        $tem_especial  = preg_match('/[^A-Za-z0-9]/', $nova_senha);
        $tamanho_ok    = strlen($nova_senha) >= 8;

        if (!$tamanho_ok || !$tem_maiuscula || !$tem_minuscula || !$tem_numero || !$tem_especial) {
            $erro = 'A nova senha informada não atende aos requisitos mínimos de segurança.';
        } else {
            try {
                // Criptografa a nova senha
                $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);

                // Salva no banco de dados e limpa os tokens de recuperação
                $upd = db()->prepare("UPDATE usuarios SET senha = :senha, token_recuperacao = NULL, token_recuperacao_expira = NULL WHERE id = :id");
                $upd->execute([
                    ':senha' => $senha_hash,
                    ':id'    => $usuario['id']
                ]);

                // Grava auditoria de segurança no log
                registrar_log("Senha redefinida com sucesso para o usuário: " . $usuario['email']);

                // Redireciona via PRG com mensagem amigável no GET
                $msg_sucesso = "Sua senha comercial foi redefinida com sucesso! Faça login com as novas credenciais.";
                header("Location: login.php?sucesso=" . urlencode($msg_sucesso));
                exit;

            } catch (Throwable $ex) {
                registrar_log('Erro ao salvar nova senha: ' . $ex->getMessage(), 'ERROR');
                $erro = 'Erro técnico ao salvar a nova senha. Tente novamente em instantes.';
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
    <title>Redefinir Senha — <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/auth.css?v=1">
    <style>
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
            padding: 12px 46px 12px 46px;
            border-radius: 12px;
            border: 1.5px solid #cbd5e1;
            font-size: 0.95rem;
            background-color: #f8fafc;
            color: #1e293b;
            transition: all 0.25s ease;
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

        .password-strength-box {
            border-radius: 12px;
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 0.74rem;
            color: #475569;
        }

        .strength-rule {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 5px;
            transition: color 0.25s ease;
        }

        .strength-rule:last-child {
            margin-bottom: 0;
        }

        .footer-text {
            text-align: center;
            font-size: 0.78rem;
            color: #64748b;
            margin-top: 25px;
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="login-card">
        <div class="brand-logo-container">
            <img src="logorajodiag.png" alt="Rajo Diagnóstico" style="height: 50px; width: auto; object-fit: contain;">
        </div>

        <?php if (!$token_valido): ?>
            <h4 class="login-title text-danger">Erro de Segurança</h4>
            <p class="login-subtitle">O código de segurança informado é inválido ou já expirou.</p>
            <div class="alert-custom alert-danger-custom shadow-sm mb-4">
                <i class="bi bi-exclamation-octagon-fill" style="margin-top:2px;"></i>
                <div><?= htmlspecialchars($erro) ?></div>
            </div>
            <a href="recuperar.php" class="btn btn-submit text-center d-block text-decoration-none">Solicitar Novo Link</a>
        <?php else: ?>
            <h4 class="login-title">Nova Senha Forte</h4>
            <p class="login-subtitle">Olá, <strong><?= htmlspecialchars($usuario['nome']) ?></strong>. Cadastre uma nova senha de alta segurança abaixo.</p>

            <?php if ($erro !== ''): ?>
                <div class="alert-custom alert-danger-custom shadow-sm">
                    <i class="bi bi-exclamation-circle-fill" style="margin-top:2px;"></i>
                    <div><?= htmlspecialchars($erro) ?></div>
                </div>
            <?php endif; ?>

            <form action="redefinir.php" method="POST" autocomplete="off" onsubmit="return validarSenhaRedefinir(event)">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                
                <div class="mb-3">
                    <label for="nova_senha" class="form-label">Nova Senha Forte</label>
                    <div class="input-group-custom">
                        <input type="password" id="nova_senha" name="nova_senha" 
                               class="form-control-custom" placeholder="Mínimo 8 caracteres..." required
                               onkeyup="analisarSenhaRedefinir(this.value)">
                        <i class="bi bi-key input-icon"></i>
                        <button type="button" class="btn-toggle-pass" onclick="toggleSenhaField('nova_senha', this)" title="Exibir/ocultar senha">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="confirmar_senha" class="form-label">Confirme a Nova Senha</label>
                    <div class="input-group-custom">
                        <input type="password" id="confirmar_senha" name="confirmar_senha" 
                               class="form-control-custom" placeholder="Repita a nova senha..." required>
                        <i class="bi bi-key-fill input-icon"></i>
                        <button type="button" class="btn-toggle-pass" onclick="toggleSenhaField('confirmar_senha', this)" title="Exibir/ocultar senha">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Painel dinâmico de força de senha -->
                <div class="password-strength-box shadow-sm">
                    <div class="fw-bold mb-2">Requisitos de Segurança:</div>
                    <div class="strength-rule" id="rule-len"><i class="bi bi-circle"></i> Mínimo de 8 caracteres</div>
                    <div class="strength-rule" id="rule-upper"><i class="bi bi-circle"></i> Pelo menos 1 maiúscula (A-Z)</div>
                    <div class="strength-rule" id="rule-lower"><i class="bi bi-circle"></i> Pelo menos 1 minúscula (a-z)</div>
                    <div class="strength-rule" id="rule-num"><i class="bi bi-circle"></i> Pelo menos 1 número (0-9)</div>
                    <div class="strength-rule" id="rule-special"><i class="bi bi-circle"></i> Pelo menos 1 especial (!@#$...)</div>
                </div>

                <button type="submit" class="btn btn-submit">
                    Gravar Nova Senha <i class="bi bi-shield-check ms-2"></i>
                </button>
            </form>
        <?php endif; ?>

        <div class="footer-text mt-4">
            <a href="login.php">Voltar para o Login</a>
        </div>
    </div>
    
    <div class="footer-text" style="color: #94a3b8; font-size: 0.75rem;">
        &copy; <?= date('Y') ?> Rajo Diagnóstico &bull; Todos os direitos reservados.
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script>
let senhaValida = false;

function toggleSenhaField(inputId, button) {
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

function analisarSenhaRedefinir(senha) {
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

function atualizarRegra(elementId, valida) {
    const element = document.getElementById(elementId);
    if (!element) return;
    const icon = element.querySelector('i');
    if (valida) {
        element.style.color = '#16a34a';
        icon.className = 'bi bi-check-circle-fill';
    } else {
        element.style.color = '#64748b';
        icon.className = 'bi bi-circle';
    }
}

function validarSenhaRedefinir(e) {
    const senha = document.getElementById('nova_senha').value;
    const confirmar = document.getElementById('confirmar_senha').value;

    if (!senhaValida) {
        alert('A senha informada não atende aos requisitos mínimos de segurança.');
        e.preventDefault();
        return false;
    }

    if (senha !== confirmar) {
        alert('As senhas informadas não correspondem.');
        e.preventDefault();
        return false;
    }

    return true;
}
</script>
</body>
</html>
