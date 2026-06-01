<?php
/**
 * ============================================================
 *  Rajo Diagnóstico — Assistente de Migração de Banco de Dados
 * ============================================================
 * 
 * Script independente projetado para atualizar a base de dados do sistema
 * em ambiente de produção de forma 100% segura, retrocompatível e visual.
 */

// Importa configuração global do sistema a partir do diretório raiz
$config_path = dirname(__DIR__) . '/config.php';
if (!file_exists($config_path)) {
    die("Erro Fatal: O arquivo de configuracao raiz 'config.php' nao foi encontrado em: {$config_path}\n");
}
require_once $config_path;

// Definição do escopo completo de tabelas, colunas e constraints a certificar
$migracoes = [
    // 1. Estrutura SaaS e Autenticação
    [
        'id'     => 'tbl_usuarios',
        'tipo'   => 'tabela',
        'nome'   => 'usuarios',
        'sql'    => "CREATE TABLE IF NOT EXISTS usuarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            senha VARCHAR(255) NOT NULL,
            confirmado TINYINT(1) NOT NULL DEFAULT 0,
            tipo VARCHAR(20) NOT NULL DEFAULT 'comum',
            token_confirmacao VARCHAR(100) DEFAULT NULL,
            token_expira DATETIME DEFAULT NULL,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        'desc'   => "Tabela 'usuarios' para gerenciamento de analistas (SaaS e Autenticação)",
        'grupo'  => 'Segurança & Contas'
    ],
    [
        'id'     => 'col_usuarios_tipo',
        'tipo'   => 'coluna',
        'tabela' => 'usuarios',
        'coluna' => 'tipo',
        'sql'    => "ALTER TABLE usuarios ADD COLUMN tipo VARCHAR(20) NOT NULL DEFAULT 'comum' AFTER confirmado",
        'desc'   => "Coluna 'tipo' para diferenciação de privilégios (analista Master vs. Comum)",
        'grupo'  => 'Segurança & Contas'
    ],
    
    // 2. Avisos Globais no Painel
    [
        'id'     => 'tbl_avisos',
        'tipo'   => 'tabela',
        'nome'   => 'avisos',
        'sql'    => "CREATE TABLE IF NOT EXISTS avisos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            titulo VARCHAR(255) NOT NULL,
            mensagem TEXT NOT NULL,
            tipo VARCHAR(20) NOT NULL DEFAULT 'info',
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        'desc'   => "Tabela 'avisos' para exibição de comunicados globais no painel do analista",
        'grupo'  => 'Segurança & Contas'
    ],

    // 3. Auditoria Avançada de Infraestrutura & Formatos
    [
        'id'     => 'col_relatorios_cms',
        'tipo'   => 'coluna',
        'tabela' => 'relatorios',
        'coluna' => 'auditoria_cms',
        'sql'    => "ALTER TABLE relatorios ADD COLUMN auditoria_cms VARCHAR(100) DEFAULT NULL",
        'desc'   => "Armazena a plataforma de desenvolvimento / CMS identificado (ex: WordPress, Shopify)",
        'grupo'  => 'Auditoria Avançada'
    ],
    [
        'id'     => 'col_relatorios_hospedagem',
        'tipo'   => 'coluna',
        'tabela' => 'relatorios',
        'coluna' => 'auditoria_hospedagem',
        'sql'    => "ALTER TABLE relatorios ADD COLUMN auditoria_hospedagem TEXT DEFAULT NULL",
        'desc'   => "Armazena o JSON com IP, provedor do host e geolocalização física do servidor",
        'grupo'  => 'Auditoria Avançada'
    ],
    [
        'id'     => 'col_relatorios_seguranca',
        'tipo'   => 'coluna',
        'tabela' => 'relatorios',
        'coluna' => 'auditoria_seguranca',
        'sql'    => "ALTER TABLE relatorios ADD COLUMN auditoria_seguranca TEXT DEFAULT NULL",
        'desc'   => "Armazena o status de criptografia SSL e conformidade dos cabeçalhos HTTP",
        'grupo'  => 'Auditoria Avançada'
    ],
    [
        'id'     => 'col_relatorios_dns',
        'tipo'   => 'coluna',
        'tabela' => 'relatorios',
        'coluna' => 'auditoria_dns',
        'sql'    => "ALTER TABLE relatorios ADD COLUMN auditoria_dns TEXT DEFAULT NULL",
        'desc'   => "Armazena a análise de chaves de entregabilidade e antispam (SPF e DMARC)",
        'grupo'  => 'Auditoria Avançada'
    ],
    [
        'id'     => 'col_relatorios_tipo',
        'tipo'   => 'coluna',
        'tabela' => 'relatorios',
        'coluna' => 'tipo_relatorio',
        'sql'    => "ALTER TABLE relatorios ADD COLUMN tipo_relatorio VARCHAR(20) DEFAULT 'completo'",
        'desc'   => "Define o layout físico do relatório exportado (Completo vs. Compacto de 1 Página)",
        'grupo'  => 'Auditoria Avançada'
    ],

    // 4. Simulador Inteligente de Mídia Paga (Google Ads) e Screenshots
    [
        'id'     => 'col_relatorios_adsnicho',
        'tipo'   => 'coluna',
        'tabela' => 'relatorios',
        'coluna' => 'ads_nicho',
        'sql'    => "ALTER TABLE relatorios ADD COLUMN ads_nicho VARCHAR(100) DEFAULT NULL",
        'desc'   => "Identifica o nicho de mercado do cliente para associação com médias de CPC",
        'grupo'  => 'Simulador de Ads & Mídia'
    ],
    [
        'id'     => 'col_relatorios_adsinvest',
        'tipo'   => 'coluna',
        'tabela' => 'relatorios',
        'coluna' => 'ads_investimento',
        'sql'    => "ALTER TABLE relatorios ADD COLUMN ads_investimento DECIMAL(10,2) DEFAULT NULL",
        'desc'   => "Investimento mensal estimado em tráfego pago declarado no formulário",
        'grupo'  => 'Simulador de Ads & Mídia'
    ],
    [
        'id'     => 'col_relatorios_adscpc',
        'tipo'   => 'coluna',
        'tabela' => 'relatorios',
        'coluna' => 'ads_cpc',
        'sql'    => "ALTER TABLE relatorios ADD COLUMN ads_cpc DECIMAL(10,2) DEFAULT NULL",
        'desc'   => "Custo por clique (CPC) médio configurado na simulação",
        'grupo'  => 'Simulador de Ads & Mídia'
    ],
    [
        'id'     => 'col_relatorios_screenshot',
        'tipo'   => 'coluna',
        'tabela' => 'relatorios',
        'coluna' => 'screenshot_path',
        'sql'    => "ALTER TABLE relatorios ADD COLUMN screenshot_path VARCHAR(255) DEFAULT NULL",
        'desc'   => "Caminho do screenshot local do site usado como marca d'água de fundo translúcido",
        'grupo'  => 'Simulador de Ads & Mídia'
    ],

    // 5. Integração SaaS de Relatórios com Usuários
    [
        'id'     => 'col_relatorios_userid',
        'tipo'   => 'coluna',
        'tabela' => 'relatorios',
        'coluna' => 'usuario_id',
        'sql'    => "ALTER TABLE relatorios ADD COLUMN usuario_id INT DEFAULT NULL AFTER bloquear_plano",
        'desc'   => "Coluna de multitenancy vinculando o relatório ao analista proprietário",
        'grupo'  => 'Segurança & Contas'
    ],
    [
        'id'     => 'fk_relatorios_usuarios',
        'tipo'   => 'fk',
        'tabela' => 'relatorios',
        'nome'   => 'fk_relatorios_usuarios',
        'sql'    => "ALTER TABLE relatorios ADD CONSTRAINT fk_relatorios_usuarios FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE",
        'desc'   => "Chave estrangeira vinculando relatórios a usuários ativos com exclusão lógica em cascata",
        'grupo'  => 'Segurança & Contas'
    ],
    
    // 6. Controle Financeiro & Saldo
    [
        'id'     => 'col_usuarios_saldo',
        'tipo'   => 'coluna',
        'tabela' => 'usuarios',
        'coluna' => 'saldo',
        'sql'    => "ALTER TABLE usuarios ADD COLUMN saldo DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        'desc'   => "Coluna para guardar o saldo financeiro do analista",
        'grupo'  => 'Controle Financeiro'
    ],
    [
        'id'     => 'col_usuarios_custo',
        'tipo'   => 'coluna',
        'tabela' => 'usuarios',
        'coluna' => 'custo_relatorio',
        'sql'    => "ALTER TABLE usuarios ADD COLUMN custo_relatorio DECIMAL(10,2) DEFAULT NULL",
        'desc'   => "Custo personalizado por relatório (se nulo, usa o custo padrão global)",
        'grupo'  => 'Controle Financeiro'
    ],
    [
        'id'     => 'col_usuarios_bonus',
        'tipo'   => 'coluna',
        'tabela' => 'usuarios',
        'coluna' => 'bonus_relatorios',
        'sql'    => "ALTER TABLE usuarios ADD COLUMN bonus_relatorios INT NOT NULL DEFAULT 0",
        'desc'   => "Quantidade de relatórios bônus grátis disponíveis para emissão sem débito",
        'grupo'  => 'Controle Financeiro'
    ],
    [
        'id'     => 'tbl_configuracoes',
        'tipo'   => 'tabela',
        'nome'   => 'configuracoes',
        'sql'    => "CREATE TABLE IF NOT EXISTS configuracoes (
            chave VARCHAR(50) PRIMARY KEY,
            valor TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        'desc'   => "Tabela de Configurações Globais (armazena o custo global por relatório)",
        'grupo'  => 'Controle Financeiro'
    ],
    [
        'id'     => 'tbl_cupons',
        'tipo'   => 'tabela',
        'nome'   => 'cupons',
        'sql'    => "CREATE TABLE IF NOT EXISTS cupons (
            id INT AUTO_INCREMENT PRIMARY KEY,
            codigo VARCHAR(50) NOT NULL UNIQUE,
            tipo VARCHAR(20) NOT NULL DEFAULT 'porcentagem',
            valor DECIMAL(10,2) NOT NULL,
            limite_usos INT DEFAULT NULL,
            usos INT NOT NULL DEFAULT 0,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        'desc'   => "Tabela de cupons de desconto para simulação de recarga",
        'grupo'  => 'Controle Financeiro'
    ],
    [
        'id'     => 'tbl_transacoes',
        'tipo'   => 'tabela',
        'nome'   => 'transacoes',
        'sql'    => "CREATE TABLE IF NOT EXISTS transacoes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            tipo VARCHAR(20) NOT NULL,
            valor DECIMAL(10,2) NOT NULL,
            descricao VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'concluido',
            comprovante VARCHAR(255) DEFAULT NULL,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_transacoes_usuarios FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        'desc'   => "Tabela para logar o histórico financeiro (recargas, bônus e emissões debitadas)",
        'grupo'  => 'Controle Financeiro'
    ]
];

// Função auxiliar para analisar e verificar se as tabelas, colunas e constraints já existem via INFORMATION_SCHEMA
function analisarEstadoItem(PDO $pdo, array $item): string {
    try {
        if ($item['tipo'] === 'tabela') {
            $stmt = $pdo->prepare("SELECT TABLE_NAME 
                                   FROM INFORMATION_SCHEMA.TABLES 
                                   WHERE TABLE_SCHEMA = DATABASE() 
                                     AND TABLE_NAME = ?");
            $stmt->execute([$item['nome']]);
            return $stmt->fetch() ? 'ja_instalado' : 'pendente';
        }
        
        if ($item['tipo'] === 'coluna') {
            // Verifica se a tabela pai existe
            $tblCheck = $pdo->prepare("SELECT TABLE_NAME 
                                       FROM INFORMATION_SCHEMA.TABLES 
                                       WHERE TABLE_SCHEMA = DATABASE() 
                                         AND TABLE_NAME = ?");
            $tblCheck->execute([$item['tabela']]);
            if (!$tblCheck->fetch()) {
                return 'indisponivel';
            }
            
            // Verifica se a coluna já existe
            $stmt = $pdo->prepare("SELECT COLUMN_NAME 
                                   FROM INFORMATION_SCHEMA.COLUMNS 
                                   WHERE TABLE_SCHEMA = DATABASE() 
                                     AND TABLE_NAME = ? 
                                     AND COLUMN_NAME = ?");
            $stmt->execute([$item['tabela'], $item['coluna']]);
            return $stmt->fetch() ? 'ja_instalado' : 'pendente';
        }
        
        if ($item['tipo'] === 'fk') {
            // Verifica se a tabela pai existe
            $tblCheck = $pdo->prepare("SELECT TABLE_NAME 
                                       FROM INFORMATION_SCHEMA.TABLES 
                                       WHERE TABLE_SCHEMA = DATABASE() 
                                         AND TABLE_NAME = ?");
            $tblCheck->execute([$item['tabela']]);
            if (!$tblCheck->fetch()) {
                return 'indisponivel';
            }
            
            // Verifica se a FK já existe via TABLE_CONSTRAINTS
            $stmt = $pdo->prepare("SELECT CONSTRAINT_NAME 
                                   FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
                                   WHERE CONSTRAINT_SCHEMA = DATABASE() 
                                     AND TABLE_NAME = ? 
                                     AND CONSTRAINT_NAME = ? 
                                     AND CONSTRAINT_TYPE = 'FOREIGN KEY'");
            $stmt->execute([$item['tabela'], $item['nome']]);
            return $stmt->fetch() ? 'ja_instalado' : 'pendente';
        }
    } catch (Throwable $e) {
        return 'erro';
    }
    return 'pendente';
}

// Inicializa a conexão PDO e executa análise prévia de integridade
$pdo = null;
$erroConexao = null;
try {
    $pdo = db();
} catch (Throwable $e) {
    $erroConexao = $e->getMessage();
}

$executou = false;
$mensagens = [];

// Modo de execução: verifica se o usuário acionou a migração
if ($pdo && (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' || PHP_SAPI === 'cli')) {
    $executou = true;
    foreach ($migracoes as $item) {
        $estado = analisarEstadoItem($pdo, $item);
        if ($estado === 'ja_instalado') {
            $mensagens[$item['id']] = [
                'ok' => true,
                'status' => 'Concluído (Já Existia)',
                'msg' => 'A estrutura solicitada já está ativa na base de dados.',
                'classe' => 'alert-info'
            ];
            continue;
        }
        
        if ($estado === 'indisponivel') {
            $mensagens[$item['id']] = [
                'ok' => false,
                'status' => 'Pendente (Dependência)',
                'msg' => 'A tabela primária não foi encontrada para aplicar esta alteração.',
                'classe' => 'alert-warning'
            ];
            continue;
        }
        
        try {
            $pdo->exec($item['sql']);
            $mensagens[$item['id']] = [
                'ok' => true,
                'status' => 'Sucesso',
                'msg' => 'Query executada e modificação aplicada com sucesso.',
                'classe' => 'alert-success'
            ];
        } catch (Throwable $e) {
            $erroMsg = $e->getMessage();
            $jaExistia = false;
            
            // Captura códigos de erros de duplicidade do MySQL (1060 = Coluna duplicada, 1050 = Tabela duplicada, 121 = Constraint duplicada)
            if (
                str_contains($erroMsg, '1060') || 
                str_contains($erroMsg, '1050') || 
                str_contains($erroMsg, 'Duplicate column') || 
                str_contains($erroMsg, 'already exists') ||
                str_contains($erroMsg, 'errno: 121') ||
                str_contains($erroMsg, 'Duplicate key')
            ) {
                $jaExistia = true;
            }
            
            if ($jaExistia) {
                $mensagens[$item['id']] = [
                    'ok' => true,
                    'status' => 'Concluído (Já Existia)',
                    'msg' => 'A estrutura solicitada já estava ativa e configurada na base de dados.',
                    'classe' => 'alert-info'
                ];
            } else {
                $mensagens[$item['id']] = [
                    'ok' => false,
                    'status' => 'Falha na Execução',
                    'msg' => 'Erro na query SQL: ' . $erroMsg,
                    'classe' => 'alert-danger'
                ];
            }
        }
    }
}

// --- MODO DE EXECUÇÃO: LINHA DE COMANDO (CLI) ---
if (PHP_SAPI === 'cli') {
    echo "============================================================\n";
    echo "  RAJO DIAGNÓSTICO — MIGRATION RUNNER (CLI)\n";
    echo "============================================================\n\n";
    
    if ($erroConexao) {
        echo "[-] ERRO FATAL DE CONEXÃO COM O BANCO DE DADOS:\n";
        echo "    {$erroConexao}\n\n";
        exit(1);
    }
    
    echo "[+] Conectado com sucesso ao banco: " . DB_NAME . "\n";
    echo "[+] Processando " . count($migracoes) . " atualizações...\n\n";
    
    foreach ($migracoes as $item) {
        $id = $item['id'];
        $res = $mensagens[$id] ?? null;
        
        if ($res) {
            $simbolo = $res['ok'] ? "[OK]" : "[-]";
            echo "{$simbolo} {$item['desc']} -> {$res['status']}\n";
            echo "     Detalhes: {$res['msg']}\n\n";
        } else {
            echo "[?] {$item['desc']} -> Não executado (necessário rodar com permissões)\n\n";
        }
    }
    
    echo "============================================================\n";
    echo "Processo de migração via CLI finalizado.\n";
    echo "============================================================\n";
    exit(0);
}

// --- MODO DE EXECUÇÃO: NAVEGADOR (WEB) ---
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migration Engine — <?= APP_NAME ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --dark-bg: #0f172a;
            --card-bg: rgba(30, 41, 59, 0.7);
            --border-color: rgba(255, 255, 255, 0.08);
            --font-family: 'Outfit', -apple-system, sans-serif;
        }

        body {
            background-color: var(--dark-bg);
            color: #f8fafc;
            font-family: var(--font-family);
            background-image: radial-gradient(circle at 10% 20%, rgba(37, 99, 235, 0.08) 0%, transparent 40%),
                              radial-gradient(circle at 90% 80%, rgba(37, 99, 235, 0.05) 0%, transparent 40%);
            background-attachment: fixed;
            min-height: 100vh;
            padding-bottom: 50px;
        }

        .header-logo {
            font-size: 26px;
            font-weight: 900;
            color: #fff;
            letter-spacing: 2px;
            text-transform: uppercase;
            background: linear-gradient(135deg, #fff 0%, #cbd5e1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .glass-panel {
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
            padding: 24px;
        }

        .migration-item-card {
            background: rgba(15, 23, 42, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.04);
            border-radius: 12px;
            padding: 16px;
            transition: border-color 0.2s ease;
        }

        .migration-item-card:hover {
            border-color: rgba(37, 99, 235, 0.25);
        }

        .badge-group {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            padding: 4px 8px;
            border-radius: 6px;
            background-color: rgba(37, 99, 235, 0.15);
            color: #60a5fa;
            border: 1px solid rgba(37, 99, 235, 0.2);
        }

        .btn-action {
            background: linear-gradient(135deg, var(--primary) 0%, #1d4ed8 100%);
            border: none;
            color: #fff !important;
            font-weight: 700;
            padding: 12px 24px;
            border-radius: 10px;
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3);
        }

        .btn-action:hover {
            filter: brightness(1.08);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
        }

        .status-badge {
            font-size: 11px;
            font-weight: 700;
            border-radius: 6px;
            padding: 3px 8px;
        }

        .status-ja-instalado {
            background-color: rgba(16, 185, 129, 0.1);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .status-pendente {
            background-color: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        .status-indisponivel {
            background-color: rgba(100, 116, 139, 0.1);
            color: #94a3b8;
            border: 1px solid rgba(100, 116, 139, 0.2);
        }

        .alert-custom {
            border-radius: 12px;
            font-size: 13px;
            border: 1px solid transparent;
            padding: 12px 16px;
        }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div class="d-flex align-items-center gap-2">
            <span class="header-logo">RAJO</span>
            <span class="text-white-50 fs-5 fw-light">| Migration System</span>
        </div>
        <a href="../index.php" class="btn btn-outline-light btn-sm rounded-3">
            <i class="bi bi-arrow-left me-1"></i> Voltar ao Painel
        </a>
    </div>

    <?php if ($erroConexao): ?>
        <div class="glass-panel text-center py-5 mb-4 border-danger">
            <i class="bi bi-x-circle-fill text-danger display-3 mb-3"></i>
            <h3 class="text-white fw-bold">Erro de Conexão com o Banco</h3>
            <p class="text-white-50 max-width-600 mx-auto mb-4">
                O assistente de migração não conseguiu se conectar ao banco de dados especificado no arquivo de configuração do sistema.
            </p>
            <div class="alert alert-danger d-inline-block text-start font-monospace small" style="max-width: 100%;">
                <strong>Mensagem do Driver:</strong> <?= htmlspecialchars($erroConexao) ?>
            </div>
            <div class="mt-4 small text-muted">
                Verifique as variáveis <code>DB_HOST</code>, <code>DB_NAME</code>, <code>DB_USER</code> e <code>DB_PASS</code> em <strong>config.php</strong>.
            </div>
        </div>
    <?php else: ?>
        
        <div class="glass-panel mb-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <h4 class="fw-bold mb-1 text-white">Assistente de Integridade de Banco de Dados</h4>
                    <p class="text-white-50 small mb-0">
                        Banco de Dados Ativo em Produção: <code class="bg-dark text-primary px-2 py-1 rounded"><?= DB_NAME ?></code> em <code class="bg-dark text-light px-2 py-1 rounded"><?= DB_HOST ?></code>
                    </p>
                </div>
                
                <?php if (!$executou): ?>
                    <form method="POST">
                        <button type="submit" class="btn btn-action w-100">
                            <i class="bi bi-database-fill-gear me-1"></i> Executar Atualização do Banco
                        </button>
                    </form>
                <?php else: ?>
                    <div class="d-flex align-items-center gap-2 text-success fw-bold">
                        <i class="bi bi-shield-fill-check fs-4"></i> Migrações Executadas
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="glass-panel">
            <h5 class="fw-bold text-white mb-4">Mapeamento e Verificação de Alterações</h5>
            
            <div class="d-flex flex-column gap-3">
                <?php 
                $grupoAtual = '';
                foreach ($migracoes as $item): 
                    $estado = analisarEstadoItem($pdo, $item);
                    $res = $mensagens[$item['id']] ?? null;
                    
                    if ($item['grupo'] !== $grupoAtual) {
                        $grupoAtual = $item['grupo'];
                        echo "<h6 class='fw-bold text-primary mt-3 mb-2 text-uppercase' style='letter-spacing:0.5px; font-size:11px;'>// {$grupoAtual}</h6>";
                    }
                ?>
                    <div class="migration-item-card">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                            <div class="w-100">
                                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                    <span class="badge-group"><?= $item['tipo'] ?></span>
                                    <strong class="text-white fs-6"><?= htmlspecialchars($item['desc']) ?></strong>
                                </div>
                                <div class="text-white-50 small font-monospace" style="font-size: 11px;">
                                    <?= htmlspecialchars($item['sql']) ?>
                                </div>
                            </div>
                            
                            <div class="text-end align-self-md-center min-width-150">
                                <?php if (!$executou): ?>
                                    <?php if ($estado === 'ja_instalado'): ?>
                                        <span class="status-badge status-ja-instalado">✓ Ativo</span>
                                    <?php elseif ($estado === 'indisponivel'): ?>
                                        <span class="status-badge status-indisponivel">✗ Bloqueado</span>
                                    <?php else: ?>
                                        <span class="status-badge status-pendente">⚠️ Pendente</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php if ($res): ?>
                                        <span class="badge px-3 py-1.5 <?= $res['ok'] ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?> rounded-3" style="font-size:10px; font-weight:700;">
                                            <?= htmlspecialchars($res['status']) ?>
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($res): ?>
                            <div class="alert-custom <?= $res['classe'] ?> mt-2 shadow-sm">
                                <i class="bi bi-info-circle-fill me-1"></i> <?= htmlspecialchars($res['msg']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($executou): ?>
                <div class="mt-4 text-center">
                    <a href="migrar.php" class="btn btn-outline-primary px-4 rounded-3">
                        <i class="bi bi-arrow-clockwise me-1"></i> Verificar Estado Atualizado
                    </a>
                </div>
            <?php endif; ?>
        </div>

    <?php endif; ?>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>
