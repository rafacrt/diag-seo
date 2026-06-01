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
define('SMTP_FROM', 'central@rajohost.com.br'); // Domínio verificado no Resend
define('SMTP_FROM_NAME', 'Rajo Diagnóstico');

/**
 * Registra logs detalhados do sistema em um arquivo local na pasta /logs
 */
function registrar_log(string $mensagem, string $nivel = 'INFO'): void
{
    $dir = __DIR__ . '/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $arquivo = $dir . '/sistema.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
    $logMsg = sprintf("[%s] [%s] [%s] %s\n", $timestamp, $nivel, $ip, $mensagem);
    @file_put_contents($arquivo, $logMsg, FILE_APPEND);
}

/**
 * Envia um e-mail transacional de forma segura utilizando o Resend (via PHPMailer)
 * e grava logs de depuração detalhados em caso de falha.
 */
function enviar_email(string $para, string $assunto, string $corpo_html): bool
{
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        registrar_log("Erro: PHPMailer não está instalado. Execute 'composer install'.", 'ERROR');
        return false;
    }

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $smtp_debug_log = '';

    try {
        // Habilita depuração detalhada do SMTP capturando em uma variável de texto
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function ($str, $level) use (&$smtp_debug_log) {
            $smtp_debug_log .= trim($str) . "\n";
        };

        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';
        $mail->Timeout = 10; // Evita travar a requisição do usuário em caso de timeout de rede do host

        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($para);

        $mail->isHTML(true);
        $mail->Subject = $assunto;
        $mail->Body = $corpo_html;
        $mail->AltBody = strip_tags($corpo_html);

        $mail->send();

        registrar_log(sprintf("E-mail enviado com sucesso para %s. Assunto: %s", $para, $assunto), 'INFO');
        return true;
    } catch (\Exception $e) {
        $erro_detalhado = sprintf(
            "Falha ao enviar e-mail para %s. Erro PHPMailer: %s. Exceção: %s. Handshake SMTP:\n%s",
            $para,
            $mail->ErrorInfo,
            $e->getMessage(),
            $smtp_debug_log
        );
        registrar_log($erro_detalhado, 'ERROR');
        return false;
    }
}

// Carrega configurações locais se o arquivo existir
if (file_exists(__DIR__ . '/config-local.php')) {
    require_once __DIR__ . '/config-local.php';
}

// Configurações de Banco de Dados (Produção - Fallback)
if (!defined('DB_HOST'))
    define('DB_HOST', 'localhost');
if (!defined('DB_CHARSET'))
    define('DB_CHARSET', 'utf8mb4');
if (!defined('DB_NAME'))
    define('DB_NAME', 'rafacrt_diagseo');
if (!defined('DB_USER'))
    define('DB_USER', 'rafacrt_diagseo');
if (!defined('DB_PASS'))
    define('DB_PASS', '7Mb8M1N14dvP');

// Configurações Globais do Aplicativo (Produção - Fallback)
if (!defined('APP_NAME'))
    define('APP_NAME', 'Rajo Diagnóstico');
if (!defined('APP_URL'))
    define('APP_URL', 'https://rajo.com.br/diag-seo'); // URL de produção do sistema
if (!defined('ANALISTA_PADRAO'))
    define('ANALISTA_PADRAO', 'Rafael Medeiros – Rajo Desenvolvimento');
if (!defined('PAGESPEED_API_KEY'))
    define('PAGESPEED_API_KEY', 'AIzaSyD2kDYU4tzQMVH3xRRioX-9C89GmYgLvJQ');

// Controle do fluxo de Ativação por E-mail (SaaS)
// Defina como FALSE em produção se quiser desativar a exigência de confirmação por link de e-mail temporariamente
if (!defined('EXIGIR_ATIVACAO_EMAIL'))
    define('EXIGIR_ATIVACAO_EMAIL', true);

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
            } catch (Throwable $e) {
            }

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
            } catch (Throwable $e) {
            }

            // Adicionar colunas de recuperação de senha se ainda não existirem
            try {
                $pdo->exec("ALTER TABLE usuarios ADD COLUMN token_recuperacao VARCHAR(100) DEFAULT NULL AFTER token_expira");
            } catch (Throwable $e) {
            }
            try {
                $pdo->exec("ALTER TABLE usuarios ADD COLUMN token_recuperacao_expira DATETIME DEFAULT NULL AFTER token_recuperacao");
            } catch (Throwable $e) {
            }

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
            } catch (Throwable $e) {
            }

            try {
                // Tenta adicionar a restrição de chave estrangeira
                $pdo->exec("ALTER TABLE relatorios ADD CONSTRAINT fk_relatorios_usuarios FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE");
            } catch (Throwable $e) {
            }

            // Se não existir nenhum usuário master, promove o primeiro usuário confirmado a master automaticamente
            try {
                $stmt_m = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE tipo = 'master'");
                if ((int) $stmt_m->fetchColumn() === 0) {
                    $pdo->exec("UPDATE usuarios SET tipo = 'master' WHERE confirmado = 1 ORDER BY id ASC LIMIT 1");
                }
            } catch (Throwable $e) {
            }

            // Adicionar colunas financeiras na tabela usuarios
            try {
                $pdo->exec("ALTER TABLE usuarios ADD COLUMN saldo DECIMAL(10,2) NOT NULL DEFAULT 0.00");
            } catch (Throwable $e) {
            }
            try {
                $pdo->exec("ALTER TABLE usuarios ADD COLUMN custo_relatorio DECIMAL(10,2) DEFAULT NULL");
            } catch (Throwable $e) {
            }
            try {
                $pdo->exec("ALTER TABLE usuarios ADD COLUMN bonus_relatorios INT NOT NULL DEFAULT 0");
            } catch (Throwable $e) {
            }

            // Criar tabelas financeiras
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS configuracoes (
                    chave VARCHAR(50) PRIMARY KEY,
                    valor TEXT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
            } catch (Throwable $e) {
            }
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS cupons (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    codigo VARCHAR(50) NOT NULL UNIQUE,
                    tipo VARCHAR(20) NOT NULL DEFAULT 'porcentagem',
                    valor DECIMAL(10,2) NOT NULL,
                    limite_usos INT DEFAULT NULL,
                    usos INT NOT NULL DEFAULT 0,
                    ativo TINYINT(1) NOT NULL DEFAULT 1,
                    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
            } catch (Throwable $e) {
            }
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS transacoes (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    usuario_id INT NOT NULL,
                    tipo VARCHAR(20) NOT NULL,
                    valor DECIMAL(10,2) NOT NULL,
                    descricao VARCHAR(255) NOT NULL,
                    status VARCHAR(20) NOT NULL DEFAULT 'concluido',
                    comprovante VARCHAR(255) DEFAULT NULL,
                    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    CONSTRAINT fk_transacoes_usuarios FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
            } catch (Throwable $e) {
            }

            // Garantir que a tabela configuracoes tenha o custo padrão global inicial
            try {
                $check = $pdo->prepare("SELECT COUNT(*) FROM configuracoes WHERE chave = 'custo_relatorio_padrao'");
                $check->execute();
                if ((int) $check->fetchColumn() === 0) {
                    $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('custo_relatorio_padrao', '50.00')");
                }
            } catch (Throwable $e) {
            }

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

// ─── Helpers Financeiros ─────────────────────────────────────
function obterCustoRelatorioPadrao(): float
{
    try {
        $stmt = db()->prepare("SELECT valor FROM configuracoes WHERE chave = 'custo_relatorio_padrao'");
        $stmt->execute();
        $valor = $stmt->fetchColumn();
        return $valor !== false ? (float) $valor : 50.00;
    } catch (Throwable $e) {
        return 50.00;
    }
}

function obterCustoUsuario(int $usuario_id): float
{
    try {
        $stmt = db()->prepare("SELECT tipo, custo_relatorio FROM usuarios WHERE id = ?");
        $stmt->execute([$usuario_id]);
        $user = $stmt->fetch();
        if (!$user)
            return obterCustoRelatorioPadrao();
        if ($user['tipo'] === 'master')
            return 0.00; // Master não paga
        return $user['custo_relatorio'] !== null ? (float) $user['custo_relatorio'] : obterCustoRelatorioPadrao();
    } catch (Throwable $e) {
        return obterCustoRelatorioPadrao();
    }
}

function verificarSaldoEmissao(int $usuario_id): bool
{
    try {
        $stmt = db()->prepare("SELECT tipo, saldo, bonus_relatorios FROM usuarios WHERE id = ?");
        $stmt->execute([$usuario_id]);
        $user = $stmt->fetch();
        if (!$user)
            return false;
        if ($user['tipo'] === 'master')
            return true; // Master tem emissão livre
        if ((int) $user['bonus_relatorios'] > 0)
            return true; // Tem bônus ativo
        $custo = obterCustoUsuario($usuario_id);
        return (float) $user['saldo'] >= $custo;
    } catch (Throwable $e) {
        return false;
    }
}

function debitarEmissaoRelatorio(int $usuario_id, string $dominio): bool
{
    try {
        $stmt = db()->prepare("SELECT tipo, saldo, bonus_relatorios FROM usuarios WHERE id = ?");
        $stmt->execute([$usuario_id]);
        $user = $stmt->fetch();
        if (!$user)
            return false;
        if ($user['tipo'] === 'master')
            return true; // Master não consome saldo nem bônus

        // 1. Consome bônus se houver
        if ((int) $user['bonus_relatorios'] > 0) {
            $upd = db()->prepare("UPDATE usuarios SET bonus_relatorios = bonus_relatorios - 1 WHERE id = ?");
            $upd->execute([$usuario_id]);

            // Logar transação
            $log = db()->prepare("INSERT INTO transacoes (usuario_id, tipo, valor, descricao, status) VALUES (?, 'bonus', 0.00, ?, 'concluido')");
            $log->execute([$usuario_id, "Emissão de diagnóstico para o domínio {$dominio} (Bônus Consumido)"]);
            return true;
        }

        // 2. Senão, consome do saldo
        $custo = obterCustoUsuario($usuario_id);
        if ((float) $user['saldo'] < $custo) {
            return false; // Saldo insuficiente
        }

        $upd = db()->prepare("UPDATE usuarios SET saldo = saldo - ? WHERE id = ?");
        $upd->execute([$custo, $usuario_id]);

        // Logar transação
        $log = db()->prepare("INSERT INTO transacoes (usuario_id, tipo, valor, descricao, status) VALUES (?, 'emissao', ?, ?, 'concluido')");
        $log->execute([$usuario_id, -$custo, "Débito por emissão de diagnóstico para o domínio {$dominio}"]);
        return true;
    } catch (Throwable $e) {
        registrar_log("Erro ao debitar relatório do usuário {$usuario_id}: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Gera o código PIX Copia e Cola (BR Code) dinâmico e compatível com as especificações do Banco Central do Brasil.
 */

function gerarCopiaColaPix(float $valor, string $txid = 'RECARGASALDO'): string
{
    $chave = '28826574000132'; // CNPJ limpo
    $beneficiario = 'RAJO DESENVOLVIMENTO';
    $cidade = 'FORTALEZA';

    $valorStr = number_format($valor, 2, '.', '');

    $gui = '0014br.gov.bcb.pix';
    $chaveFormatted = '0114' . $chave;
    $infoAdicional = '0212RAJO RECARGA';
    $merchantAccount = '26' . str_pad(strlen($gui . $chaveFormatted . $infoAdicional), 2, '0', STR_PAD_LEFT) . $gui . $chaveFormatted . $infoAdicional;

    $payload = '000201'
        . $merchantAccount
        . '52040000'
        . '5303986'
        . '54' . str_pad(strlen($valorStr), 2, '0', STR_PAD_LEFT) . $valorStr
        . '5802BR'
        . '59' . str_pad(strlen($beneficiario), 2, '0', STR_PAD_LEFT) . $beneficiario
        . '60' . str_pad(strlen($cidade), 2, '0', STR_PAD_LEFT) . $cidade;

    $txidPart = '05' . str_pad(strlen($txid), 2, '0', STR_PAD_LEFT) . $txid;
    $payload .= '62' . str_pad(strlen($txidPart), 2, '0', STR_PAD_LEFT) . $txidPart;

    $payload .= '6304';

    // Cálculo do CRC16 CCITT
    $crc = 0xFFFF;
    for ($c = 0; $c < strlen($payload); $c++) {
        $x = (($crc >> 8) ^ ord($payload[$c])) & 0xFF;
        $x ^= $x >> 4;
        $crc = (($crc << 8) ^ ($x << 12) ^ ($x << 5) ^ $x) & 0xFFFF;
    }

    $crcHex = strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    return $payload . $crcHex;
}
