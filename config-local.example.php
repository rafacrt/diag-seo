<?php
// ============================================================
//  Rajo Diagnóstico — Configuração Local (MODELO)
//
//  Copie este arquivo para config-local.php e preencha com as
//  credenciais reais. O config-local.php está no .gitignore e
//  NUNCA deve ser commitado.
// ============================================================

// ─── Banco de Dados ──────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_CHARSET', 'utf8mb4');
define('DB_NAME', 'nome_do_banco');
define('DB_USER', 'usuario_do_banco');
define('DB_PASS', 'senha_do_banco');

// ─── Aplicação ───────────────────────────────────────────────
define('APP_URL', 'https://seudominio.com.br/diag-seo');
define('EXIGIR_ATIVACAO_EMAIL', true);

// ─── E-mail transacional (Resend via SMTP) ───────────────────
define('SMTP_HOST', 'smtp.resend.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'resend');
define('SMTP_PASS', 're_SUA_CHAVE_AQUI');
define('SMTP_FROM', 'central@seudominio.com.br');
define('SMTP_FROM_NAME', 'Rajo Diagnóstico');

// ─── APIs externas ───────────────────────────────────────────
define('PAGESPEED_API_KEY', 'SUA_CHAVE_GOOGLE_AQUI');
