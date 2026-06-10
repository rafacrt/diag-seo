<?php
// ============================================================
//  Rajo Diagnóstico — Configuração
// ============================================================

// Cabeçalhos de segurança aplicados a toda requisição web (ignorado no CLI):
// nosniff impede MIME sniffing, SAMEORIGIN bloqueia clickjacking por iframe,
// e Referrer-Policy evita vazar URLs completas (com tokens) para terceiros.
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// Autoload do Composer para bibliotecas de terceiros (mPDF, PHPMailer)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Carrega configurações locais/segredos ANTES de qualquer fallback.
// Credenciais reais (SMTP, banco, APIs) devem morar em config-local.php,
// que está no .gitignore — nunca commitar segredos neste arquivo.
if (file_exists(__DIR__ . '/config-local.php')) {
    require_once __DIR__ . '/config-local.php';
}

if (!defined('SMTP_HOST'))      define('SMTP_HOST', 'smtp.resend.com');
if (!defined('SMTP_PORT'))      define('SMTP_PORT', 587);
if (!defined('SMTP_USER'))      define('SMTP_USER', 'resend');
if (!defined('SMTP_PASS'))      define('SMTP_PASS', ''); // Definir em config-local.php
if (!defined('SMTP_FROM'))      define('SMTP_FROM', 'central@rajohost.com.br'); // Domínio verificado no Resend
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', 'Rajo Diagnóstico');

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

/**
 * Monta o HTML de um e-mail transacional com o shell visual padrão do
 * produto (logo, card centralizado, botão de ação e nota de rodapé).
 * Centraliza o markup antes duplicado em login/cadastro/recuperar.
 *
 * @param string $titulo        Cabeçalho do e-mail (h2)
 * @param string $corpo_html    Parágrafo de contexto (pode conter <strong>)
 * @param string $botao_texto   Rótulo do botão de ação
 * @param string $botao_url     Destino do botão (também exibido como link de fallback)
 * @param string $rodape_nota   Texto pequeno no rodapé (ex.: validade do link)
 */
function email_template(string $titulo, string $corpo_html, string $botao_texto, string $botao_url, string $rodape_nota = ''): string
{
    $logo_url = rtrim(APP_URL, '/') . '/logorajodiag.png';
    $titulo_e = htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8');
    $botao_e  = htmlspecialchars($botao_texto, ENT_QUOTES, 'UTF-8');
    $url_e    = htmlspecialchars($botao_url, ENT_QUOTES, 'UTF-8');
    $rodape   = $rodape_nota !== ''
        ? '<p style="font-size: 0.72rem; color: #94a3b8; margin-top: 15px;">' . htmlspecialchars($rodape_nota, ENT_QUOTES, 'UTF-8') . '</p>'
        : '';

    return '
    <div style="font-family: \'Helvetica Neue\', Helvetica, Arial, sans-serif; background-color: #f8fafc; padding: 40px 20px; color: #334155; line-height: 1.6;">
        <div style="max-width: 500px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; box-shadow: 0 10px 30px rgba(15,23,42,0.05); border: 1px solid #e2e8f0; padding: 40px; text-align: center;">
            <div style="display: inline-block; margin-bottom: 25px;">
                <img src="' . $logo_url . '" alt="Rajo Diagnóstico" style="height: 38px; width: auto; max-width: 180px; object-fit: contain;">
            </div>
            <h2 style="font-size: 1.4rem; color: #1e293b; font-weight: 700; margin-top: 0; margin-bottom: 12px;">' . $titulo_e . '</h2>
            <p style="font-size: 0.95rem; color: #64748b; margin-bottom: 30px;">' . $corpo_html . '</p>
            <a href="' . $url_e . '" style="display: inline-block; background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: #ffffff; text-decoration: none; font-weight: 600; font-size: 0.9rem; padding: 12px 30px; border-radius: 10px; box-shadow: 0 8px 16px rgba(37,99,235,0.25);">' . $botao_e . '</a>
            <p style="font-size: 0.8rem; color: #94a3b8; margin-top: 35px; border-top: 1px solid #f1f5f9; padding-top: 20px;">Se o botão não funcionar, copie e cole o link abaixo no seu navegador:<br><a href="' . $url_e . '" style="color: #2563eb; text-decoration: none; font-size: 0.82rem;">' . $url_e . '</a></p>
            ' . $rodape . '
        </div>
    </div>';
}

// Configurações de Banco de Dados — credenciais reais em config-local.php
if (!defined('DB_HOST'))
    define('DB_HOST', 'localhost');
if (!defined('DB_CHARSET'))
    define('DB_CHARSET', 'utf8mb4');
if (!defined('DB_NAME'))
    define('DB_NAME', '');
if (!defined('DB_USER'))
    define('DB_USER', '');
if (!defined('DB_PASS'))
    define('DB_PASS', '');

// Configurações Globais do Aplicativo (Produção - Fallback)
if (!defined('APP_NAME'))
    define('APP_NAME', 'Rajo Diagnóstico');
if (!defined('APP_URL'))
    define('APP_URL', 'https://rajo.com.br/diag-seo'); // URL de produção do sistema
if (!defined('ANALISTA_PADRAO'))
    define('ANALISTA_PADRAO', 'Rafael Medeiros – Rajo Desenvolvimento');
if (!defined('PAGESPEED_API_KEY'))
    define('PAGESPEED_API_KEY', ''); // Definir em config-local.php

// Controle do fluxo de Ativação por E-mail (SaaS)
// Defina como FALSE em produção se quiser desativar a exigência de confirmação por link de e-mail temporariamente
if (!defined('EXIGIR_ATIVACAO_EMAIL'))
    define('EXIGIR_ATIVACAO_EMAIL', true);

// ─── Conexão PDO ─────────────────────────────────────────────

// Versão atual do schema. Incremente este número sempre que adicionar
// uma nova migração em executar_migracoes() — elas só rodam quando a
// versão gravada no banco for menor, eliminando dezenas de DDLs por request.
const SCHEMA_VERSION = 2;

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

        // Checagem barata: 1 SELECT por request. Migrações só rodam quando necessário.
        $versao_atual = 0;
        try {
            $stmt = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'schema_version'");
            $versao_atual = (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            // Tabela configuracoes ainda não existe — instalação nova
        }

        if ($versao_atual < SCHEMA_VERSION) {
            executar_migracoes($pdo);
            try {
                $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('schema_version', '" . SCHEMA_VERSION . "')
                            ON DUPLICATE KEY UPDATE valor = '" . SCHEMA_VERSION . "'");
            } catch (Throwable $e) {
            }
        }
    }
    return $pdo;
}

/**
 * Migrações idempotentes do schema. Executadas uma única vez por versão
 * (controlado por SCHEMA_VERSION + configuracoes.schema_version).
 */
function executar_migracoes(PDO $pdo): void
{
        // Tabela de configurações primeiro (guarda a própria versão do schema)
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS configuracoes (
                chave VARCHAR(50) PRIMARY KEY,
                valor TEXT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        } catch (Throwable $e) {
        }

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

            // ── Colunas da tabela relatorios (antes espalhadas pelo index.php) ──
            foreach ([
                "ALTER TABLE relatorios MODIFY COLUMN gtm_nota VARCHAR(50) NULL",
                "ALTER TABLE relatorios ADD COLUMN pdf_cor_tema VARCHAR(7) DEFAULT '#1A4FBB'",
                "ALTER TABLE relatorios ADD COLUMN logo_cliente VARCHAR(255) DEFAULT NULL",
                "ALTER TABLE relatorios ADD COLUMN bloquear_plano TINYINT(1) DEFAULT 0",
                "ALTER TABLE relatorios ADD COLUMN auditoria_cms VARCHAR(100) DEFAULT NULL",
                "ALTER TABLE relatorios ADD COLUMN auditoria_hospedagem TEXT DEFAULT NULL",
                "ALTER TABLE relatorios ADD COLUMN auditoria_seguranca TEXT DEFAULT NULL",
                "ALTER TABLE relatorios ADD COLUMN auditoria_dns TEXT DEFAULT NULL",
                "ALTER TABLE relatorios ADD COLUMN tipo_relatorio VARCHAR(20) DEFAULT 'completo'",
                "ALTER TABLE relatorios ADD COLUMN ads_nicho VARCHAR(100) DEFAULT NULL",
                "ALTER TABLE relatorios ADD COLUMN ads_investimento DECIMAL(10,2) DEFAULT NULL",
                "ALTER TABLE relatorios ADD COLUMN ads_cpc DECIMAL(10,2) DEFAULT NULL",
                "ALTER TABLE relatorios ADD COLUMN screenshot_path VARCHAR(255) DEFAULT NULL",
            ] as $ddl) {
                try { $pdo->exec($ddl); } catch (Throwable $e) {}
            }

            // ── Token público de compartilhamento por relatório ──
            // Permite enviar o relatório ao cliente final sem login e sem expor IDs sequenciais
            try {
                $pdo->exec("ALTER TABLE relatorios ADD COLUMN token_publico VARCHAR(64) DEFAULT NULL");
            } catch (Throwable $e) {
            }
            try {
                $pdo->exec("CREATE UNIQUE INDEX idx_relatorios_token ON relatorios (token_publico)");
            } catch (Throwable $e) {
            }
            // Backfill: gera token para relatórios antigos sem um
            try {
                $sem_token = $pdo->query("SELECT id FROM relatorios WHERE token_publico IS NULL OR token_publico = ''")->fetchAll();
                if ($sem_token) {
                    $upd = $pdo->prepare("UPDATE relatorios SET token_publico = ? WHERE id = ?");
                    foreach ($sem_token as $row) {
                        $upd->execute([bin2hex(random_bytes(24)), $row['id']]);
                    }
                }
            } catch (Throwable $e) {
            }

            // ── Controle de tentativas de login (proteção contra força bruta) ──
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS login_tentativas (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    email VARCHAR(100) NOT NULL,
                    ip VARCHAR(45) NOT NULL,
                    tentado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_login_email_ip (email, ip, tentado_em)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
            } catch (Throwable $e) {
            }

        } catch (Throwable $e) {
            // Ignora silenciosamente em caso de erro na migração automática
        }
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

        // 1. Consome bônus se houver — UPDATE condicional atômico evita
        // que dois requests simultâneos consumam o mesmo bônus
        if ((int) $user['bonus_relatorios'] > 0) {
            $upd = db()->prepare("UPDATE usuarios SET bonus_relatorios = bonus_relatorios - 1 WHERE id = ? AND bonus_relatorios > 0");
            $upd->execute([$usuario_id]);
            if ($upd->rowCount() === 0) {
                return false; // Outro request consumiu o bônus primeiro
            }

            // Logar transação
            $log = db()->prepare("INSERT INTO transacoes (usuario_id, tipo, valor, descricao, status) VALUES (?, 'bonus', 0.00, ?, 'concluido')");
            $log->execute([$usuario_id, "Emissão de diagnóstico para o domínio {$dominio} (Bônus Consumido)"]);
            return true;
        }

        // 2. Senão, consome do saldo — a condição saldo >= custo no próprio
        // UPDATE garante que o débito nunca deixe o saldo negativo
        $custo = obterCustoUsuario($usuario_id);
        $upd = db()->prepare("UPDATE usuarios SET saldo = saldo - ? WHERE id = ? AND saldo >= ?");
        $upd->execute([$custo, $usuario_id, $custo]);
        if ($upd->rowCount() === 0) {
            return false; // Saldo insuficiente
        }

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

// ─── Proteção contra força bruta no login ────────────────────

const LOGIN_MAX_TENTATIVAS = 5;   // Tentativas falhas permitidas
const LOGIN_JANELA_MINUTOS = 15;  // Janela de contagem/bloqueio

function login_bloqueado(string $email, string $ip): bool
{
    try {
        $stmt = db()->prepare("SELECT COUNT(*) FROM login_tentativas
                               WHERE email = ? AND ip = ?
                                 AND tentado_em > DATE_SUB(NOW(), INTERVAL " . LOGIN_JANELA_MINUTOS . " MINUTE)");
        $stmt->execute([$email, $ip]);
        return (int) $stmt->fetchColumn() >= LOGIN_MAX_TENTATIVAS;
    } catch (Throwable $e) {
        return false; // Falha na checagem não deve travar o login legítimo
    }
}

function login_registrar_falha(string $email, string $ip): void
{
    try {
        $stmt = db()->prepare("INSERT INTO login_tentativas (email, ip) VALUES (?, ?)");
        $stmt->execute([$email, $ip]);
        // Limpeza oportunista de registros antigos (mantém a tabela enxuta)
        db()->exec("DELETE FROM login_tentativas WHERE tentado_em < DATE_SUB(NOW(), INTERVAL 1 DAY)");
    } catch (Throwable $e) {
    }
}

function login_limpar_tentativas(string $email, string $ip): void
{
    try {
        $stmt = db()->prepare("DELETE FROM login_tentativas WHERE email = ? AND ip = ?");
        $stmt->execute([$email, $ip]);
    } catch (Throwable $e) {
    }
}

// ─── Página de erro amigável ─────────────────────────────────

/**
 * Renderiza uma página de erro estilizada e encerra a requisição.
 * Substitui os die() em texto cru por algo com a identidade visual do produto.
 */
function pagina_erro(int $codigo, string $titulo, string $mensagem): void
{
    http_response_code($codigo);
    $titulo_e   = htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8');
    $mensagem_e = htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8');
    $logado     = function_exists('esta_logado') && esta_logado();
    $destino    = $logado ? 'index.php' : 'login.php';
    $rotulo     = $logado ? 'Voltar ao painel' : 'Ir para o login';
    echo <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$titulo_e} — Rajo Diagnóstico</title>
<style>
  *{margin:0;padding:0;box-sizing:border-box}
  body{font-family:'Segoe UI',Arial,sans-serif;background:#0b0f19;color:#e2e8f0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
  .card{background:rgba(22,28,45,.7);border:1px solid rgba(148,163,184,.15);border-radius:18px;padding:48px 40px;max-width:440px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.4)}
  .logo{display:inline-block;width:48px;height:48px;background:linear-gradient(135deg,#1e3c72,#2a5298);color:#fff;font-weight:900;font-size:1.6rem;line-height:48px;border-radius:12px;margin-bottom:24px}
  .codigo{font-size:3.2rem;font-weight:800;color:#3b82f6;line-height:1;margin-bottom:8px}
  h1{font-size:1.15rem;font-weight:700;color:#f1f5f9;margin-bottom:12px}
  p{font-size:.92rem;color:#94a3b8;line-height:1.6;margin-bottom:28px}
  a{display:inline-block;background:linear-gradient(135deg,#1e3c72,#2a5298);color:#fff;text-decoration:none;font-weight:600;font-size:.9rem;padding:12px 28px;border-radius:10px}
</style>
</head>
<body>
  <div class="card">
    <div class="logo">R</div>
    <div class="codigo">{$codigo}</div>
    <h1>{$titulo_e}</h1>
    <p>{$mensagem_e}</p>
    <a href="{$destino}">{$rotulo}</a>
  </div>
</body>
</html>
HTML;
    exit;
}

// ─── Controle de acesso a relatórios ─────────────────────────

/**
 * Carrega um relatório validando o acesso. Aceita duas formas:
 *  - ?t=TOKEN  → acesso público (link de compartilhamento enviado ao cliente)
 *  - ?id=N     → exige login; analista comum só acessa os próprios relatórios
 *
 * Retorna [relatorio, acesso_publico]. Encerra a requisição em caso de
 * acesso negado ou relatório inexistente.
 */
function carregar_relatorio_autorizado(): array
{
    $token = trim($_GET['t'] ?? '');
    if ($token !== '') {
        $stmt = db()->prepare('SELECT * FROM relatorios WHERE token_publico = ?');
        $stmt->execute([$token]);
        $r = $stmt->fetch();
        if (!$r) {
            pagina_erro(404, 'Relatório não encontrado', 'Este link de relatório é inválido ou expirou. Solicite um novo link ao responsável pela auditoria.');
        }
        return [$r, true];
    }

    // Sem token: exige sessão autenticada
    exigir_login();

    $id = (int) ($_GET['id'] ?? 0);
    if (!$id) {
        pagina_erro(400, 'Requisição inválida', 'O identificador do relatório não foi informado corretamente.');
    }

    $stmt = db()->prepare('SELECT * FROM relatorios WHERE id = ?');
    $stmt->execute([$id]);
    $r = $stmt->fetch();
    if (!$r) {
        pagina_erro(404, 'Relatório não encontrado', 'O relatório solicitado não existe ou foi removido.');
    }

    if (!e_master() && (int) ($r['usuario_id'] ?? 0) !== (int) $_SESSION['usuario_id']) {
        pagina_erro(403, 'Acesso negado', 'Este relatório pertence a outro analista e não pode ser acessado pela sua conta.');
    }

    return [$r, false];
}
