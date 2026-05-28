<?php
// ============================================================
//  Rajo Diagnóstico — Configuração
// ============================================================

// Autoload do Composer para bibliotecas de terceiros (mPDF, PHPMailer)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

define('SMTP_HOST', 'smtp.resend.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'resend');
define('SMTP_PASS', 're_jHKVor49_DPowZNW8LBm3uLeubq4J5VDT');
define('SMTP_FROM', 'onboarding@resend.dev'); // Domínio de teste padrão do Resend
define('SMTP_FROM_NAME', 'Rajo Diagnóstico');

/**
 * Envia um e-mail transacional de forma segura utilizando o Resend (via PHPMailer)
 */
function enviar_email(string $para, string $assunto, string $corpo_html): bool
{
    // Se o autoloader do PHPMailer não estiver disponível, registra o erro e retorna falso
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        error_log("Erro: PHPMailer não está instalado. Execute 'composer install'.");
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($para);

        $mail->isHTML(true);
        $mail->Subject = $assunto;
        $mail->Body    = $corpo_html;
        $mail->AltBody = strip_tags($corpo_html);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Erro no PHPMailer: " . $mail->ErrorInfo);
        return false;
    }
}

define('DB_HOST', 'localhost');
define('DB_NAME', 'rajo_diagnostico');
define('DB_USER', 'root');        // ← altere
define('DB_PASS', '');            // ← altere
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'Rajo Diagnóstico');
define('APP_URL', 'http://localhost/rajo-diagnostico'); // ← altere
define('ANALISTA_PADRAO', 'Rafael Medeiros – Rajo Desenvolvimento');

// Chave da API do Google PageSpeed Insights (Opcional, mas recomendada para evitar limites de cota)
// Obtenha uma chave gratuita em: https://developers.google.com/speed/docs/insights/v5/get-started#key
define('PAGESPEED_API_KEY', 'AIzaSyD2kDYU4tzQMVH3xRRioX-9C89GmYgLvJQ');

// ─── Conexão PDO ─────────────────────────────────────────────
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        // Criar ou atualizar tabela de usuários para a versão SaaS
        try {
            // Verifica se a tabela usuarios tem o formato antigo (coluna usuario)
            try {
                $check = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'email'");
                if (!$check->fetch()) {
                    // Remove tabela antiga para atualizar para a estrutura SaaS
                    $pdo->exec("DROP TABLE IF EXISTS usuarios");
                }
            } catch (Throwable $e) {}

            $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL UNIQUE,
                senha VARCHAR(255) NOT NULL,
                confirmado TINYINT(1) NOT NULL DEFAULT 0,
                tipo VARCHAR(20) NOT NULL DEFAULT 'comum',
                token_confirmacao VARCHAR(100) DEFAULT NULL,
                token_expira DATETIME DEFAULT NULL,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            // Caso a tabela usuários já existisse sem a coluna tipo, adicionamos silenciosamente
            try {
                $pdo->exec("ALTER TABLE usuarios ADD COLUMN tipo VARCHAR(20) NOT NULL DEFAULT 'comum' AFTER confirmado");
            } catch (Throwable $e) {}

            // Criar tabela de avisos globais no painel
            $pdo->exec("CREATE TABLE IF NOT EXISTS avisos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                titulo VARCHAR(255) NOT NULL,
                mensagem TEXT NOT NULL,
                tipo VARCHAR(20) NOT NULL DEFAULT 'info',
                ativo TINYINT(1) NOT NULL DEFAULT 1,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            // Garantir que a tabela relatorios tenha a coluna usuario_id para multitenancy
            try {
                $pdo->exec("ALTER TABLE relatorios ADD COLUMN usuario_id INT DEFAULT NULL AFTER bloquear_plano");
            } catch (Throwable $e) {}

            try {
                // Tenta adicionar a restrição de chave estrangeira
                $pdo->exec("ALTER TABLE relatorios ADD CONSTRAINT fk_relatorios_usuarios FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE");
            } catch (Throwable $e) {}

            // Se não existir nenhum usuário master, promove o primeiro usuário confirmado a master automaticamente
            try {
                $stmt_m = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE tipo = 'master'");
                if ((int)$stmt_m->fetchColumn() === 0) {
                    $pdo->exec("UPDATE usuarios SET tipo = 'master' WHERE confirmado = 1 ORDER BY id ASC LIMIT 1");
                }
            } catch (Throwable $e) {}

        } catch (Throwable $e) {
            // Ignora silenciosamente em caso de erro na migração automática
        }
    }
    return $pdo;
}

// ─── Helpers ─────────────────────────────────────────────────
function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function nota_cor(string $nota): string
{
    if (!is_numeric($nota)) {
        return match (strtolower(trim($nota))) {
            'passing', 'aprovado', 'a' => '#2E7D32',
            'warning', 'atenção', 'b', 'c' => '#E65100',
            default => '#D32F2F'
        };
    }
    $v = (int) $nota;
    if ($v >= 90)
        return '#2E7D32';
    if ($v >= 50)
        return '#E65100';
    return '#D32F2F';
}

function status_cor(string $status): string
{
    return match (strtolower(trim($status))) {
        'bom' => '#2E7D32',
        'médio' => '#E65100',
        default => '#D32F2F',
    };
}

function prioridade_cor(string $p): string
{
    return match (strtolower(trim($p))) {
        'baixa' => '#2E7D32',
        'média' => '#E65100',
        default => '#D32F2F',
    };
}
