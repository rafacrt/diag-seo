<?php
// ============================================================
//  Rajo Diagnóstico — Painel de Administração Master
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

// Bloqueio rigoroso: apenas analistas Master podem acessar
exigir_master();

$erro = '';
$sucesso = '';

// Recupera mensagens persistidas na sessão (padrão PRG)
if (isset($_SESSION['admin_erro'])) {
    $erro = $_SESSION['admin_erro'];
    unset($_SESSION['admin_erro']);
}
if (isset($_SESSION['admin_sucesso'])) {
    $sucesso = $_SESSION['admin_sucesso'];
    unset($_SESSION['admin_sucesso']);
}

$aba_ativa = $_POST['aba'] ?? $_GET['aba'] ?? 'usuarios';

// ID do administrador logado (para impedir auto-exclusão e auto-rebaixamento)
$meu_id = (int)$_SESSION['usuario_id'];

// ─── PROCESSAMENTO DE AÇÕES VIA POST ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar(); // Bloqueia requisições forjadas contra o painel master
    $acao = $_POST['acao'] ?? '';

    try {
        // 1. ALTERAR TIPO DE USUÁRIO
        if ($acao === 'atualizar_tipo') {
            $user_id = (int)($_POST['usuario_id'] ?? 0);
            $novo_tipo = trim($_POST['tipo'] ?? 'comum');

            if (!in_array($novo_tipo, ['comum', 'master'])) {
                $erro = 'Tipo de usuário inválido.';
            } elseif ($user_id === $meu_id) {
                $erro = 'Você não pode alterar o seu próprio tipo de acesso.';
            } else {
                $stmt = db()->prepare("UPDATE usuarios SET tipo = :tipo WHERE id = :id");
                $stmt->execute([':tipo' => $novo_tipo, ':id' => $user_id]);
                $sucesso = 'Nível de acesso do usuário atualizado com sucesso!';
            }
        }

        // 2. RESETAR SENHA DO USUÁRIO
        elseif ($acao === 'resetar_senha') {
            $user_id = (int)($_POST['usuario_id'] ?? 0);
            $nova_senha = $_POST['nova_senha'] ?? '';
            $confirmar_senha = $_POST['confirmar_senha'] ?? '';

            if ($nova_senha === '' || $confirmar_senha === '') {
                $erro = 'Preencha a nova senha e a confirmação.';
            } elseif ($nova_senha !== $confirmar_senha) {
                $erro = 'As senhas informadas não correspondem.';
            } else {
                // Validação de força de senha
                $tem_maiuscula = preg_match('/[A-Z]/', $nova_senha);
                $tem_minuscula = preg_match('/[a-z]/', $nova_senha);
                $tem_numero    = preg_match('/[0-9]/', $nova_senha);
                $tem_especial  = preg_match('/[^A-Za-z0-9]/', $nova_senha);
                $tamanho_ok    = strlen($nova_senha) >= 8;

                if (!$tamanho_ok || !$tem_maiuscula || !$tem_minuscula || !$tem_numero || !$tem_especial) {
                    $erro = 'A nova senha informada não atende aos requisitos mínimos de segurança (8 dígitos, maiúscula, minúscula, número e caractere especial).';
                } else {
                    $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                    $stmt = db()->prepare("UPDATE usuarios SET senha = :senha WHERE id = :id");
                    $stmt->execute([':senha' => $senha_hash, ':id' => $user_id]);
                    $sucesso = 'Senha do analista redefinida com sucesso!';
                }
            }
            $aba_ativa = 'usuarios';
        }

        // 3. EXCLUIR USUÁRIO
        elseif ($acao === 'excluir_usuario') {
            $user_id = (int)($_POST['usuario_id'] ?? 0);

            if ($user_id === $meu_id) {
                $erro = 'Você não pode excluir a sua própria conta de administrador.';
            } else {
                // Exclui o usuário. Por ter constraint ON DELETE CASCADE, deleta todos os seus relatórios
                $stmt = db()->prepare("DELETE FROM usuarios WHERE id = ?");
                $stmt->execute([$user_id]);
                $sucesso = 'Conta do analista e todos os relatórios vinculados foram excluídos permanentemente!';
            }
            $aba_ativa = 'usuarios';
        }

        // 4. CRIAR NOVO AVISO GLOBAL
        elseif ($acao === 'criar_aviso') {
            $titulo = trim($_POST['titulo'] ?? '');
            $mensagem = trim($_POST['mensagem'] ?? '');
            $tipo = trim($_POST['tipo'] ?? 'info');
            $ativo = isset($_POST['ativo']) ? 1 : 0;

            if ($titulo === '' || $mensagem === '') {
                $erro = 'Por favor, preencha o título e o conteúdo do aviso.';
            } elseif (!in_array($tipo, ['info', 'warning', 'danger', 'success'])) {
                $erro = 'Tipo de aviso inválido.';
            } else {
                $stmt = db()->prepare("INSERT INTO avisos (titulo, mensagem, tipo, ativo) VALUES (:titulo, :mensagem, :tipo, :ativo)");
                $stmt->execute([
                    ':titulo'   => $titulo,
                    ':mensagem' => $mensagem,
                    ':tipo'     => $tipo,
                    ':ativo'    => $ativo
                ]);
                $sucesso = 'Novo aviso global publicado com sucesso!';
            }
            $aba_ativa = 'avisos';
        }

        // 5. ALTERAR STATUS DO AVISO (ATIVAR/DESATIVAR)
        elseif ($acao === 'alterar_status_aviso') {
            $aviso_id = (int)($_POST['aviso_id'] ?? 0);
            $novo_status = (int)($_POST['ativo'] ?? 0);

            $stmt = db()->prepare("UPDATE avisos SET ativo = ? WHERE id = ?");
            $stmt->execute([$novo_status, $aviso_id]);
            $sucesso = 'Status da notificação atualizado com sucesso!';
            $aba_ativa = 'avisos';
        }

        // 6. EXCLUIR AVISO
        elseif ($acao === 'excluir_aviso') {
            $aviso_id = (int)($_POST['aviso_id'] ?? 0);

            $stmt = db()->prepare("DELETE FROM avisos WHERE id = ?");
            $stmt->execute([$aviso_id]);
            $sucesso = 'Notificação global removida do painel!';
            $aba_ativa = 'avisos';
        }

        // 7. ATUALIZAR DADOS FINANCEIROS DO USUÁRIO
        elseif ($acao === 'atualizar_financeiro_usuario') {
            $user_id = (int)($_POST['usuario_id'] ?? 0);
            $novo_saldo = (float)($_POST['saldo'] ?? 0.00);
            $custo_usuario = $_POST['custo_relatorio'] !== '' ? (float)$_POST['custo_relatorio'] : null;
            $novos_bonus = (int)($_POST['bonus_relatorios'] ?? 0);

            // Busca saldo antigo para auditoria e log de ajuste
            $stmt_u = db()->prepare("SELECT saldo FROM usuarios WHERE id = ?");
            $stmt_u->execute([$user_id]);
            $saldo_antigo = (float)$stmt_u->fetchColumn();

            $stmt = db()->prepare("UPDATE usuarios SET saldo = :saldo, custo_relatorio = :custo, bonus_relatorios = :bonus WHERE id = :id");
            $stmt->execute([
                ':saldo' => $novo_saldo,
                ':custo' => $custo_usuario,
                ':bonus' => $novos_bonus,
                ':id'    => $user_id
            ]);

            // Registra transação de ajuste administrativo se houve mudança no saldo
            if (abs($novo_saldo - $saldo_antigo) > 0.001) {
                $dif = $novo_saldo - $saldo_antigo;
                $desc_dif = $dif > 0 
                    ? "Crédito manual lançado pelo Administrador (R$ " . number_format($dif, 2, ',', '.') . ")" 
                    : "Ajuste de débito lançado pelo Administrador (R$ " . number_format(abs($dif), 2, ',', '.') . ")";
                
                $log = db()->prepare("INSERT INTO transacoes (usuario_id, tipo, valor, descricao, status) VALUES (?, 'recarga', ?, ?, 'concluido')");
                $log->execute([$user_id, $dif, $desc_dif]);
            }

            $sucesso = 'Dados financeiros do analista atualizados com sucesso!';
            $aba_ativa = 'financeiro';
        }

        // 8. ATUALIZAR CUSTO PADRÃO GLOBAL
        elseif ($acao === 'atualizar_custo_padrao') {
            $novo_custo_global = (float)($_POST['custo_relatorio_padrao'] ?? 50.00);
            
            $stmt = db()->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'custo_relatorio_padrao'");
            $stmt->execute([$novo_custo_global]);
            
            $sucesso = 'Custo padrão global do relatório atualizado com sucesso!';
            $aba_ativa = 'financeiro';
        }

        // 9. CRIAR CUPOM DE DESCONTO
        elseif ($acao === 'criar_cupom') {
            $codigo = strtoupper(trim($_POST['codigo'] ?? ''));
            $tipo = trim($_POST['tipo'] ?? 'porcentagem');
            $valor = (float)($_POST['valor'] ?? 0.00);
            $limite_usos = $_POST['limite_usos'] !== '' ? (int)$_POST['limite_usos'] : null;

            if ($codigo === '') {
                $erro = 'O código do cupom é obrigatório.';
            } elseif (!in_array($tipo, ['porcentagem', 'fixo'])) {
                $erro = 'Tipo de cupom inválido.';
            } elseif ($valor <= 0) {
                $erro = 'O valor do cupom deve ser maior que zero.';
            } else {
                $stmt = db()->prepare("INSERT INTO cupons (codigo, tipo, valor, limite_usos) VALUES (?, ?, ?, ?)");
                $stmt->execute([$codigo, $tipo, $valor, $limite_usos]);
                $sucesso = "Cupom de desconto {$codigo} criado com sucesso!";
            }
            $aba_ativa = 'cupons';
        }

        // 10. EXCLUIR CUPOM
        elseif ($acao === 'excluir_cupom') {
            $cupom_id = (int)($_POST['cupom_id'] ?? 0);
            
            $stmt = db()->prepare("DELETE FROM cupons WHERE id = ?");
            $stmt->execute([$cupom_id]);
            $sucesso = 'Cupom de desconto excluído com sucesso!';
            $aba_ativa = 'cupons';
        }

        // 11. APROVAR SOLICITAÇÃO DE RECARGA PENDENTE (APROVAR COMPROVANTE PIX)
        elseif ($acao === 'aprovar_recarga') {
            $transacao_id = (int)($_POST['transacao_id'] ?? 0);
            
            $stmt_t = db()->prepare("SELECT usuario_id, valor, status FROM transacoes WHERE id = ?");
            $stmt_t->execute([$transacao_id]);
            $trans = $stmt_t->fetch();

            if (!$trans) {
                $erro = 'Transação não encontrada.';
            } elseif ($trans['status'] !== 'pendente') {
                $erro = 'Esta transação já foi processada anteriormente.';
            } else {
                db()->beginTransaction();
                try {
                    $upd_t = db()->prepare("UPDATE transacoes SET status = 'concluido' WHERE id = ?");
                    $upd_t->execute([$transacao_id]);
                    
                    $valor_recarga = (float)$trans['valor'];
                    $upd_u = db()->prepare("UPDATE usuarios SET saldo = saldo + ? WHERE id = ?");
                    $upd_u->execute([$valor_recarga, $trans['usuario_id']]);
                    
                    db()->commit();
                    $sucesso = 'Solicitação de recarga confirmada e saldo creditado com sucesso!';
                } catch (Throwable $ex) {
                    db()->rollBack();
                    throw $ex;
                }
            }
            $aba_ativa = 'financeiro';
        }

        // 12. REJEITAR SOLICITAÇÃO DE RECARGA
        elseif ($acao === 'rejeitar_recarga') {
            $transacao_id = (int)($_POST['transacao_id'] ?? 0);
            
            $upd = db()->prepare("UPDATE transacoes SET status = 'rejeitado' WHERE id = ?");
            $upd->execute([$transacao_id]);
            
            $sucesso = 'Solicitação de recarga rejeitada com sucesso.';
            $aba_ativa = 'financeiro';
        }

        // Salva na sessão para persistir no padrão PRG e redireciona
        if ($erro !== '') {
            $_SESSION['admin_erro'] = $erro;
        }
        if ($sucesso !== '') {
            $_SESSION['admin_sucesso'] = $sucesso;
        }
        header("Location: admin.php?aba=" . urlencode($aba_ativa));
        exit;

    } catch (Throwable $e) {
        registrar_log('Erro no painel admin: ' . $e->getMessage(), 'ERROR');
        $_SESSION['admin_erro'] = 'Não foi possível concluir a operação. Tente novamente em instantes.';
        header("Location: admin.php?aba=" . urlencode($aba_ativa));
        exit;
    }
}

// ─── CARREGAMENTO DE DADOS DO BANCO ──────────────────────────────────
// Buscar todos os usuários
$usuarios = db()->query("SELECT id, nome, email, confirmado, tipo, saldo, custo_relatorio, bonus_relatorios, criado_em FROM usuarios ORDER BY criado_em DESC")->fetchAll();

// Buscar todos os avisos
$avisos = db()->query("SELECT id, titulo, mensagem, tipo, ativo, criado_em FROM avisos ORDER BY criado_em DESC")->fetchAll();

// Custo padrão global
$custo_padrao_global = obterCustoRelatorioPadrao();

// Buscar todos os cupons
$cupons = db()->query("SELECT id, codigo, tipo, valor, limite_usos, usos, ativo, criado_em FROM cupons ORDER BY criado_em DESC")->fetchAll();

// Buscar transações de recarga pendentes
$transacoes_pendentes = db()->query("SELECT t.id, t.usuario_id, t.valor, t.descricao, t.criado_em, u.nome as usuario_nome, u.email as usuario_email 
                                      FROM transacoes t 
                                      JOIN usuarios u ON t.usuario_id = u.id 
                                      WHERE t.tipo = 'recarga' AND t.status = 'pendente' 
                                      ORDER BY t.criado_em ASC")->fetchAll();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administração Master — <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/style.css?v=<?= time() ?>">
    <style>
        .admin-nav-tabs .nav-link {
            border: none;
            color: #64748b;
            font-weight: 600;
            padding: 12px 24px;
            border-bottom: 3px solid transparent;
            transition: all 0.25s ease;
            font-size: 0.95rem;
        }
        .admin-nav-tabs .nav-link.active {
            color: #1A4FBB;
            border-bottom: 3px solid #1A4FBB;
            background: none;
        }
        .admin-nav-tabs .nav-link:hover {
            color: #1A4FBB;
            border-bottom-color: rgba(26, 79, 187, 0.25);
        }
        .table-responsive {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.015);
        }
        .btn-action-sm {
            width: 32px;
            height: 32px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-dark rajo-navbar px-4">
  <a href="index.php" class="navbar-brand fw-bold fs-4 d-flex align-items-center gap-2 text-decoration-none">
    <img src="logorajodiag.png" alt="Rajo Diagnóstico" style="height: 36px; width: auto; object-fit: contain;">
  </a>
  <div class="d-flex align-items-center gap-3">
    <a href="index.php" class="btn btn-outline-light btn-sm d-inline-flex align-items-center gap-1" style="border-radius: 8px;">
      <i class="bi bi-arrow-left"></i> Voltar ao Painel
    </a>
    <a href="logout.php" class="btn btn-sm btn-outline-light px-3 py-1.5 d-inline-flex align-items-center gap-1" style="border-radius: 8px; font-weight: 500; font-size: 0.85rem; border-color: rgba(255,255,255,0.25);">
      <i class="bi bi-box-arrow-right"></i> Sair
    </a>
  </div>
</nav>

<div class="container py-5" style="max-width: 1200px;">

    <!-- Cabeçalho de Controle -->
    <div class="mb-5">
        <h3 class="fw-extrabold mb-1" style="color: var(--dark-bg); font-family: var(--font-title);">Administração Master</h3>
        <p class="text-muted small mb-0"><i class="bi bi-shield-lock-fill text-primary"></i> Painel exclusivo do proprietário. Gerencie acessos, altere senhas e configure avisos.</p>
    </div>

    <!-- Feedbacks de Ações -->
    <?php if ($erro !== ''): ?>
        <div class="alert alert-danger border-0 shadow-sm d-flex align-items-center gap-2 p-3 mb-4" style="border-radius: 12px;">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <div class="small font-weight-bold"><?= htmlspecialchars($erro) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($sucesso !== ''): ?>
        <div class="alert alert-success border-0 shadow-sm d-flex align-items-center gap-2 p-3 mb-4" style="border-radius: 12px; background-color: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d;">
            <i class="bi bi-check-circle-fill fs-5 text-success"></i>
            <div class="small font-weight-bold"><?= htmlspecialchars($sucesso) ?></div>
        </div>
    <?php endif; ?>

    <!-- Abas de Navegação -->
    <ul class="nav admin-nav-tabs border-bottom mb-4" id="adminTab" role="tablist">
        <li class="nav-item">
            <button class="nav-link <?= $aba_ativa === 'usuarios' ? 'active' : '' ?>" id="usuarios-tab" data-bs-toggle="tab" data-bs-target="#usuarios-pane" type="button" role="tab"><i class="bi bi-people me-2"></i>Controle de Analistas</button>
        </li>
        <li class="nav-item">
            <button class="nav-link <?= $aba_ativa === 'financeiro' ? 'active' : '' ?>" id="financeiro-tab" data-bs-toggle="tab" data-bs-target="#financeiro-pane" type="button" role="tab"><i class="bi bi-wallet2 me-2"></i>Financeiro &amp; Saldos</button>
        </li>
        <li class="nav-item">
            <button class="nav-link <?= $aba_ativa === 'cupons' ? 'active' : '' ?>" id="cupons-tab" data-bs-toggle="tab" data-bs-target="#cupons-pane" type="button" role="tab"><i class="bi bi-tag me-2"></i>Cupons de Desconto</button>
        </li>
        <li class="nav-item">
            <button class="nav-link <?= $aba_ativa === 'avisos' ? 'active' : '' ?>" id="avisos-tab" data-bs-toggle="tab" data-bs-target="#avisos-pane" type="button" role="tab"><i class="bi bi-megaphone me-2"></i>Avisos do Sistema</button>
        </li>
    </ul>

    <div class="tab-content" id="adminTabContent">

        <!-- ══ ABA 1: GERENCIAMENTO DE USUÁRIOS ════════════════════════════════ -->
        <div class="tab-pane fade <?= $aba_ativa === 'usuarios' ? 'show active' : '' ?>" id="usuarios-pane" role="tabpanel">
            <div class="rajo-panel border-0 shadow-sm p-4 bg-white" style="border-radius: 16px;">
                <div class="table-responsive">
                    <table class="table align-middle mb-0 text-dark" style="font-size: 0.88rem;">
                        <thead class="table-light text-muted">
                            <tr>
                                <th class="ps-3">Nome</th>
                                <th>E-mail Comercial</th>
                                <th>Status de Confirmação</th>
                                <th>Nível de Acesso</th>
                                <th>Data de Cadastro</th>
                                <th class="text-end pe-3">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $u): ?>
                            <tr>
                                <td class="ps-3 fw-bold text-dark"><?= htmlspecialchars($u['nome']) ?></td>
                                <td class="font-monospace text-muted"><?= htmlspecialchars($u['email']) ?></td>
                                <td>
                                    <?php if ((int)$u['confirmado'] === 1): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1.5" style="border-radius: 12px; font-size: 0.72rem;">✓ Confirmado</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-1.5" style="border-radius: 12px; font-size: 0.72rem;">⚠ Aguardando</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($u['tipo'] === 'master'): ?>
                                        <span class="badge bg-primary px-3 py-1.5" style="border-radius: 12px; font-size: 0.72rem;"><i class="bi bi-shield-lock-fill me-1"></i>Master</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary px-3 py-1.5" style="border-radius: 12px; font-size: 0.72rem;">Comum (Analista)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted"><?= date('d/m/Y H:i', strtotime($u['criado_em'])) ?></td>
                                <td class="text-end pe-3">
                                    <div class="d-flex justify-content-end gap-2">
                                        <!-- Botão Atualizar Perfil -->
                                        <?php if ((int)$u['id'] !== $meu_id): ?>
                                        <button class="btn btn-sm btn-outline-primary btn-action-sm" 
                                                onclick="abrirModalTipo(<?= $u['id'] ?>, '<?= htmlspecialchars($u['nome']) ?>', '<?= $u['tipo'] ?>')" 
                                                title="Alterar Nível de Acesso">
                                            <i class="bi bi-shield-exclamation"></i>
                                        </button>
                                        <?php endif; ?>

                                        <!-- Botão Redefinir Senha -->
                                        <button class="btn btn-sm btn-outline-warning btn-action-sm" 
                                                onclick="abrirModalSenha(<?= $u['id'] ?>, '<?= htmlspecialchars($u['nome']) ?>')" 
                                                title="Resetar Senha Comercial">
                                            <i class="bi bi-key-fill text-warning"></i>
                                        </button>

                                        <!-- Botão Excluir Usuário -->
                                        <?php if ((int)$u['id'] !== $meu_id): ?>
                                        <button class="btn btn-sm btn-outline-danger btn-action-sm" 
                                                onclick="abrirModalExcluir(<?= $u['id'] ?>, '<?= htmlspecialchars($u['nome']) ?>')" 
                                                title="Excluir Conta por Completo">
                                            <i class="bi bi-trash3-fill"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ══ ABA 2: GERENCIAMENTO DE AVISOS GLOBAIS ══════════════════════════ -->
        <div class="tab-pane fade <?= $aba_active = $aba_ativa === 'avisos' ? 'show active' : '' ?>" id="avisos-pane" role="tabpanel">
            <div class="row g-4">
                <!-- Formulário de cadastro de avisos -->
                <div class="col-12 col-lg-4">
                    <div class="rajo-panel border-0 shadow-sm p-4 bg-white" style="border-radius: 16px;">
                        <h6 class="fw-bold mb-3 text-dark" style="font-family: var(--font-title);"><i class="bi bi-megaphone-fill text-primary me-2"></i>Publicar Aviso Global</h6>
                        <form action="admin.php" method="POST">
                        <?= csrf_campo() ?>
                            <input type="hidden" name="aba" value="avisos">
                            <input type="hidden" name="acao" value="criar_aviso">
                            
                            <div class="mb-3">
                                <label for="titulo" class="form-label">Título da Notificação</label>
                                <input type="text" id="titulo" name="titulo" class="form-control form-control-sm" placeholder="Ex.: Manutenção no Banco de Dados" required style="border-radius: 8px;">
                            </div>

                            <div class="mb-3">
                                <label for="mensagem" class="form-label">Conteúdo da Notificação</label>
                                <textarea id="mensagem" name="mensagem" class="form-control form-control-sm" rows="3" placeholder="Mensagem detalhada..." required style="border-radius: 8px;"></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="tipo" class="form-label">Cor do Alerta (Severidade)</label>
                                <select id="tipo" name="tipo" class="form-select form-select-sm" style="border-radius: 8px;">
                                    <option value="info">🔵 Azul (Informação Geral)</option>
                                    <option value="warning">🟡 Laranja (Atenção / Manutenção)</option>
                                    <option value="danger">🔴 Vermelho (Impedimentos Críticos)</option>
                                    <option value="success">🟢 Verde (Agradecimentos / Eventos)</option>
                                </select>
                            </div>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="ativo" id="ativo" value="1" checked style="cursor: pointer;">
                                <label class="form-check-label fw-semibold text-muted small" for="ativo" style="cursor: pointer;">Ativar Imediatamente</label>
                            </div>

                            <button type="submit" class="btn btn-primary btn-sm w-100 py-2 fw-semibold" style="border-radius: 8px;">
                                Publicar Aviso <i class="bi bi-send ms-1"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Lista de avisos já criados -->
                <div class="col-12 col-lg-8">
                    <div class="rajo-panel border-0 shadow-sm p-4 bg-white" style="border-radius: 16px;">
                        <h6 class="fw-bold mb-3 text-dark" style="font-family: var(--font-title);"><i class="bi bi-list-task text-primary me-2"></i>Avisos Publicados</h6>
                        
                        <?php if (empty($avisos)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-megaphone text-muted fs-2 mb-2 d-block"></i>
                                <span class="text-muted small">Nenhum aviso global cadastrado no sistema.</span>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table align-middle mb-0 text-dark" style="font-size: 0.88rem;">
                                    <thead class="table-light text-muted">
                                        <tr>
                                            <th class="ps-3">Aviso</th>
                                            <th>Estilo</th>
                                            <th>Status</th>
                                            <th>Criado Em</th>
                                            <th class="text-end pe-3">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($avisos as $a): ?>
                                        <tr>
                                            <td class="ps-3">
                                                <strong class="text-dark d-block mb-0.5"><?= htmlspecialchars($a['titulo']) ?></strong>
                                                <span class="text-muted small d-block" style="max-width: 320px; line-height: 1.4;"><?= htmlspecialchars($a['mensagem']) ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $a['tipo'] ?>-subtle text-<?= $a['tipo'] ?> border border-<?= $a['tipo'] ?>-subtle px-2.5 py-1 text-uppercase" style="font-size: 0.65rem; border-radius: 8px;">
                                                    <?= $a['tipo'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <form action="admin.php" method="POST" class="d-inline">
                        <?= csrf_campo() ?>
                                                    <input type="hidden" name="aba" value="avisos">
                                                    <input type="hidden" name="acao" value="alterar_status_aviso">
                                                    <input type="hidden" name="aviso_id" value="<?= $a['id'] ?>">
                                                    <input type="hidden" name="ativo" value="<?= $a['ativo'] == 1 ? '0' : '1' ?>">
                                                    <button type="submit" class="btn btn-link p-0 text-decoration-none small border-0 bg-transparent">
                                                        <?php if ($a['ativo'] == 1): ?>
                                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1" style="font-size: 0.68rem; border-radius: 8px;">Ativo</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary-subtle text-muted border border-secondary-subtle px-2.5 py-1" style="font-size: 0.68rem; border-radius: 8px;">Desativado</span>
                                                        <?php endif; ?>
                                                    </button>
                                                </form>
                                            </td>
                                            <td class="text-muted font-monospace" style="font-size: 0.78rem;"><?= date('d/m/Y H:i', strtotime($a['criado_em'])) ?></td>
                                            <td class="text-end pe-3">
                                                <form action="admin.php" method="POST" onsubmit="return confirm('Deseja realmente remover esta notificação global do sistema?')" class="d-inline">
                        <?= csrf_campo() ?>
                                                    <input type="hidden" name="aba" value="avisos">
                                                    <input type="hidden" name="acao" value="excluir_aviso">
                                                    <input type="hidden" name="aviso_id" value="<?= $a['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger btn-action-sm">
                                                        <i class="bi bi-trash-fill"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div> <!-- fecha rajo-panel -->
                </div> <!-- fecha col-12 col-lg-8 -->
            </div> <!-- fecha row -->
        </div> <!-- fecha avisos-pane -->

        <!-- ══ ABA 3: GERENCIAMENTO FINANCEIRO & SALDOS ════════════════════════ -->
        <div class="tab-pane fade <?= $aba_active = $aba_ativa === 'financeiro' ? 'show active' : '' ?>" id="financeiro-pane" role="tabpanel">
            <div class="row g-4">
                
                <!-- Ajuste de Custo Padrão Global -->
                <div class="col-12 col-md-4">
                    <div class="rajo-panel border-0 shadow-sm p-4 bg-white" style="border-radius: 16px;">
                        <h6 class="fw-bold mb-3 text-dark" style="font-family: var(--font-title);"><i class="bi bi-gear-fill text-primary me-2"></i>Custo Global do Relatório</h6>
                        <form action="admin.php" method="POST">
                        <?= csrf_campo() ?>
                                                            <input type="hidden" name="aba" value="financeiro">
                                                            <input type="hidden" name="acao" value="atualizar_custo_padrao">
                            
                            <div class="mb-3">
                                <label for="custo_relatorio_padrao" class="form-label fw-semibold">Valor Padrão por Emissão (R$)</label>
                                <input type="number" step="0.01" min="0" id="custo_relatorio_padrao" name="custo_relatorio_padrao" class="form-control form-control-sm" value="<?= htmlspecialchars($custo_padrao_global) ?>" required style="border-radius: 8px;">
                                <small class="text-muted" style="font-size:0.7rem;">Valor debitado de analistas comuns que não possuem custo personalizado configurado.</small>
                            </div>

                            <button type="submit" class="btn btn-primary btn-sm w-100 py-2 fw-semibold" style="border-radius: 8px;">
                                Salvar Custo Padrão <i class="bi bi-check-circle ms-1"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Solicitações de Recarga Pendentes -->
                <div class="col-12 col-md-8">
                    <div class="rajo-panel border-0 shadow-sm p-4 bg-white h-100" style="border-radius: 16px;">
                        <h6 class="fw-bold mb-3 text-dark" style="font-family: var(--font-title);"><i class="bi bi-hourglass-split text-warning me-2"></i>Solicitações de Recarga Pendentes (Aprovar PIX)</h6>
                        
                        <?php if (empty($transacoes_pendentes)): ?>
                            <div class="text-center py-4 text-muted small">
                                <i class="bi bi-emoji-smile fs-4 d-block mb-2 text-success"></i>
                                Nenhuma solicitação de recarga pendente no momento.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table align-middle mb-0 text-dark" style="font-size: 0.82rem;">
                                    <thead class="table-light text-muted">
                                        <tr>
                                            <th class="ps-3">Analista</th>
                                            <th>Valor</th>
                                            <th>Detalhes</th>
                                            <th>Data</th>
                                            <th class="text-end pe-3">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($transacoes_pendentes as $t): ?>
                                        <tr>
                                            <td class="ps-3">
                                                <strong class="text-dark d-block"><?= htmlspecialchars($t['usuario_nome']) ?></strong>
                                                <span class="text-muted small"><?= htmlspecialchars($t['usuario_email']) ?></span>
                                            </td>
                                            <td class="fw-bold text-success">R$ <?= number_format($t['valor'], 2, ',', '.') ?></td>
                                            <td class="text-muted" style="max-width: 180px; font-size:0.75rem;"><?= htmlspecialchars($t['descricao']) ?></td>
                                            <td class="text-muted font-monospace" style="font-size: 0.75rem;"><?= date('d/m/Y H:i', strtotime($t['criado_em'])) ?></td>
                                            <td class="text-end pe-3">
                                                <div class="d-flex justify-content-end gap-2">
                                                    <form action="admin.php" method="POST" class="d-inline" onsubmit="return confirm('Deseja realmente APROVAR esta recarga e creditar o saldo na conta do analista?')">
                        <?= csrf_campo() ?>
                                                        <input type="hidden" name="aba" value="financeiro">
                                                        <input type="hidden" name="acao" value="aprovar_recarga">
                                                        <input type="hidden" name="transacao_id" value="<?= $t['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-success btn-action-sm" title="Confirmar Recebimento e Creditar Saldo">
                                                            <i class="bi bi-check-lg"></i>
                                                        </button>
                                                    </form>
                                                    <form action="admin.php" method="POST" class="d-inline" onsubmit="return confirm('Deseja REJEITAR esta solicitação de recarga?')">
                        <?= csrf_campo() ?>
                                                        <input type="hidden" name="aba" value="financeiro">
                                                        <input type="hidden" name="acao" value="rejeitar_recarga">
                                                        <input type="hidden" name="transacao_id" value="<?= $t['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger btn-action-sm" title="Rejeitar Solicitação">
                                                            <i class="bi bi-x-lg"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Gestão de Saldo de Usuários -->
                <div class="col-12 mt-4">
                    <div class="rajo-panel border-0 shadow-sm p-4 bg-white" style="border-radius: 16px;">
                        <h6 class="fw-bold mb-3 text-dark" style="font-family: var(--font-title);"><i class="bi bi-wallet-fill text-primary me-2"></i>Controle Financeiro de Analistas</h6>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0 text-dark" style="font-size: 0.88rem;">
                                <thead class="table-light text-muted">
                                    <tr>
                                        <th class="ps-3">Analista</th>
                                        <th>Tipo</th>
                                        <th>Saldo Disponível</th>
                                        <th>Bônus Disponíveis</th>
                                        <th>Custo por Emissão</th>
                                        <th class="text-end pe-3">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($usuarios as $u): ?>
                                    <tr>
                                        <td class="ps-3">
                                            <strong class="text-dark d-block"><?= htmlspecialchars($u['nome']) ?></strong>
                                            <span class="text-muted small"><?= htmlspecialchars($u['email']) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($u['tipo'] === 'master'): ?>
                                                <span class="badge bg-primary px-2.5 py-1" style="border-radius: 8px; font-size:0.68rem;">Master</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary px-2.5 py-1" style="border-radius: 8px; font-size:0.68rem;">Comum</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="fw-bold <?= $u['saldo'] > 0 ? 'text-success' : 'text-danger' ?>">
                                            R$ <?= number_format($u['saldo'], 2, ',', '.') ?>
                                        </td>
                                        <td class="fw-bold <?= $u['bonus_relatorios'] > 0 ? 'text-primary' : 'text-muted' ?>">
                                            <?= (int)$u['bonus_relatorios'] ?> bônus
                                        </td>
                                        <td>
                                            <?php if ($u['tipo'] === 'master'): ?>
                                                <span class="text-muted italic">Isento (R$ 0,00)</span>
                                            <?php else: ?>
                                                <?php if ($u['custo_relatorio'] !== null): ?>
                                                    <span class="text-success fw-bold">R$ <?= number_format($u['custo_relatorio'], 2, ',', '.') ?></span> 
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle ms-1" style="font-size:0.6rem; border-radius:6px;">Personalizado</span>
                                                <?php else: ?>
                                                    <span class="text-dark">R$ <?= number_format($custo_padrao_global, 2, ',', '.') ?></span>
                                                    <span class="text-muted small ms-1">(Padrão Global)</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-3">
                                            <button class="btn btn-sm btn-outline-primary btn-action-sm" 
                                                    onclick="abrirModalFinanceiro(<?= $u['id'] ?>, '<?= htmlspecialchars($u['nome']) ?>', <?= $u['saldo'] ?>, '<?= $u['custo_relatorio'] ?>', <?= $u['bonus_relatorios'] ?>)" 
                                                    title="Editar Saldo e Bônus">
                                                <i class="bi bi-wallet2 text-primary"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- ══ ABA 4: GERENCIAMENTO DE CUPONS DE DESCONTO ══════════════════════ -->
        <div class="tab-pane fade <?= $aba_active = $aba_ativa === 'cupons' ? 'show active' : '' ?>" id="cupons-pane" role="tabpanel">
            <div class="row g-4">
                
                <!-- Cadastrar Novo Cupom -->
                <div class="col-12 col-lg-4">
                    <div class="rajo-panel border-0 shadow-sm p-4 bg-white" style="border-radius: 16px;">
                        <h6 class="fw-bold mb-3 text-dark" style="font-family: var(--font-title);"><i class="bi bi-tag-fill text-primary me-2"></i>Criar Novo Cupom</h6>
                        <form action="admin.php" method="POST">
                        <?= csrf_campo() ?>
                            <input type="hidden" name="aba" value="cupons">
                            <input type="hidden" name="acao" value="criar_cupom">
                            
                            <div class="mb-3">
                                <label for="codigo" class="form-label">Código do Cupom</label>
                                <input type="text" id="codigo" name="codigo" class="form-control form-control-sm text-uppercase" placeholder="Ex.: PROMO30" required style="border-radius: 8px;">
                            </div>

                            <div class="mb-3">
                                <label for="tipo_cupom" class="form-label">Tipo de Desconto</label>
                                <select id="tipo_cupom" name="tipo" class="form-select form-select-sm" style="border-radius: 8px;">
                                    <option value="porcentagem">Porcentagem (%)</option>
                                    <option value="fixo">Valor Fixo (R$)</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="valor_cupom" class="form-label">Valor do Desconto</label>
                                <input type="number" step="0.01" min="0.01" id="valor_cupom" name="valor" class="form-control form-control-sm" placeholder="Ex.: 15.00" required style="border-radius: 8px;">
                            </div>

                            <div class="mb-3">
                                <label for="limite_usos" class="form-label">Limite de Usos <span class="text-muted fw-normal">(opcional)</span></label>
                                <input type="number" min="1" id="limite_usos" name="limite_usos" class="form-control form-control-sm" placeholder="Sem limite" style="border-radius: 8px;">
                            </div>

                            <button type="submit" class="btn btn-primary btn-sm w-100 py-2 fw-semibold" style="border-radius: 8px;">
                                Criar Cupom <i class="bi bi-tag ms-1"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Lista de Cupons Ativos -->
                <div class="col-12 col-lg-8">
                    <div class="rajo-panel border-0 shadow-sm p-4 bg-white h-100" style="border-radius: 16px;">
                        <h6 class="fw-bold mb-3 text-dark" style="font-family: var(--font-title);"><i class="bi bi-list-stars text-primary me-2"></i>Cupons de Desconto Criados</h6>
                        
                        <?php if (empty($cupons)): ?>
                            <div class="text-center py-5 text-muted small">
                                <i class="bi bi-tag fs-3 d-block mb-2"></i>
                                Nenhum cupom de desconto criado até o momento.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table align-middle mb-0 text-dark" style="font-size: 0.88rem;">
                                    <thead class="table-light text-muted">
                                        <tr>
                                            <th class="ps-3">Código</th>
                                            <th>Tipo</th>
                                            <th>Desconto</th>
                                            <th>Utilização</th>
                                            <th>Criado Em</th>
                                            <th class="text-end pe-3">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cupons as $c): ?>
                                        <tr>
                                            <td class="ps-3 fw-bold text-dark font-monospace"><?= htmlspecialchars($c['codigo']) ?></td>
                                            <td class="text-uppercase" style="font-size:0.75rem;"><?= htmlspecialchars($c['tipo']) ?></td>
                                            <td class="fw-bold text-primary">
                                                <?= $c['tipo'] === 'porcentagem' ? (int)$c['valor'] . '%' : 'R$ ' . number_format($c['valor'], 2, ',', '.') ?>
                                            </td>
                                            <td>
                                                <span class="fw-semibold"><?= (int)$c['usos'] ?></span> 
                                                <span class="text-muted">/ <?= $c['limite_usos'] !== null ? (int)$c['limite_usos'] : '∞' ?> usos</span>
                                            </td>
                                            <td class="text-muted font-monospace" style="font-size: 0.78rem;"><?= date('d/m/Y H:i', strtotime($c['criado_em'])) ?></td>
                                            <td class="text-end pe-3">
                                                <form action="admin.php" method="POST" onsubmit="return confirm('Deseja realmente remover este cupom de desconto?')" class="d-inline">
                        <?= csrf_campo() ?>
                                                    <input type="hidden" name="aba" value="cupons">
                                                    <input type="hidden" name="acao" value="excluir_cupom">
                                                    <input type="hidden" name="cupom_id" value="<?= $c['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger btn-action-sm">
                                                        <i class="bi bi-trash-fill"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

<!-- ══ MODAL FINANCEIRO DO USUÁRIO ════════════════════════════════════ -->
<div class="modal fade" id="modalFinanceiro" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow" style="border-radius: 16px;">
      <form action="admin.php" method="POST">
                        <?= csrf_campo() ?>
        <input type="hidden" name="aba" value="financeiro">
        <input type="hidden" name="acao" value="atualizar_financeiro_usuario">
        <input type="hidden" name="usuario_id" id="fin_usuario_id">
        
        <div class="modal-header border-0 pb-0">
          <h6 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" style="font-family: var(--font-title);"><i class="bi bi-wallet2 text-primary fs-5"></i> Configurações Financeiras</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" style="font-size: 0.8rem;"></button>
        </div>
        <div class="modal-body pt-3">
          <p class="small text-muted mb-3">Ajuste o saldo, configure bônus ou custo personalizado para <strong id="fin_nome_usuario" class="text-dark"></strong>:</p>
          
          <div class="mb-3">
            <label for="fin_saldo" class="form-label small fw-bold">Saldo Financeiro (R$)</label>
            <input type="number" step="0.01" min="0" id="fin_saldo" name="saldo" class="form-control form-control-sm" required style="border-radius: 8px;">
            <small class="text-muted" style="font-size:0.7rem;">Saldo disponível para débito na emissão de relatórios.</small>
          </div>

          <div class="mb-3">
            <label for="fin_custo" class="form-label small fw-bold">Custo Personalizado por Relatório (R$)</label>
            <input type="number" step="0.01" min="0" id="fin_custo" name="custo_relatorio" class="form-control form-control-sm" placeholder="Deixe vazio para usar o custo padrão global" style="border-radius: 8px;">
            <small class="text-muted" style="font-size:0.7rem;">Custo especial cobrado deste analista. Se nulo, usa o custo padrão global.</small>
          </div>

          <div class="mb-3">
            <label for="fin_bonus" class="form-label small fw-bold">Relatórios Bônus Grátis</label>
            <input type="number" min="0" id="fin_bonus" name="bonus_relatorios" class="form-control form-control-sm" required style="border-radius: 8px;">
            <small class="text-muted" style="font-size:0.7rem;">Quantidade de emissões gratuitas restantes para este usuário.</small>
          </div>

        </div>
        <div class="modal-footer border-0 pt-0 justify-content-end gap-2">
          <button type="button" class="btn btn-sm btn-light px-3 py-2 fw-semibold" style="border-radius: 8px;" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-sm btn-primary px-3 py-2 fw-semibold" style="border-radius: 8px;">Salvar Alterações</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ══ MODAL 1: EDITAR TIPO/NÍVEL DE ACESSO ════════════════════════════ -->
<div class="modal fade" id="modalTipo" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow" style="border-radius: 16px;">
      <form action="admin.php" method="POST">
                        <?= csrf_campo() ?>
        <input type="hidden" name="aba" value="usuarios">
        <input type="hidden" name="acao" value="atualizar_tipo">
        <input type="hidden" name="usuario_id" id="tipo_usuario_id">
        
        <div class="modal-header border-0 pb-0">
          <h6 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" style="font-family: var(--font-title);"><i class="bi bi-shield-exclamation text-primary fs-5"></i> Nível de Acesso</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" style="font-size: 0.8rem;"></button>
        </div>
        <div class="modal-body pt-3">
          <p class="small text-muted mb-3">Selecione o nível de permissões comerciais para o analista <strong id="tipo_nome_usuario" class="text-dark"></strong>:</p>
          <select name="tipo" id="tipo_select" class="form-select form-select-sm" style="border-radius: 8px;">
              <option value="comum">Comum (Acesso aos próprios diagnósticos)</option>
              <option value="master">Master (Controle administrativo global)</option>
          </select>
        </div>
        <div class="modal-footer border-0 pt-0 justify-content-end gap-2">
          <button type="button" class="btn btn-sm btn-light px-3 py-2 fw-semibold" style="border-radius: 8px;" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-sm btn-primary px-3 py-2 fw-semibold" style="border-radius: 8px;">Confirmar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ══ MODAL 2: RESETAR SENHA DO ANALISTA ═════════════════════════════ -->
<div class="modal fade" id="modalSenha" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow" style="border-radius: 16px;">
      <form action="admin.php" method="POST" autocomplete="off" onsubmit="return validarSenhaReset(event)">
                        <?= csrf_campo() ?>
        <input type="hidden" name="aba" value="usuarios">
        <input type="hidden" name="acao" value="resetar_senha">
        <input type="hidden" name="usuario_id" id="senha_usuario_id">
        
        <div class="modal-header border-0 pb-0">
          <h6 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" style="font-family: var(--font-title);"><i class="bi bi-key-fill text-warning fs-5"></i> Redefinir Senha Comercial</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" style="font-size: 0.8rem;"></button>
        </div>
        <div class="modal-body pt-3">
          <p class="small text-muted mb-3">Cadastre uma nova senha forte para o analista <strong id="senha_nome_usuario" class="text-dark"></strong>:</p>
          
          <div class="mb-3">
              <label for="modal_nova_senha" class="form-label small mb-1 fw-bold">Nova Senha Forte</label>
              <div class="input-group">
                  <input type="password" id="modal_nova_senha" name="nova_senha" class="form-control form-control-sm" placeholder="Mínimo 8 caracteres..." required onkeyup="analisarSenhaReset(this.value)" style="border-radius: 8px 0 0 8px;">
                  <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleSenhaModal('modal_nova_senha', this)" style="border-radius: 0 8px 8px 0;"><i class="bi bi-eye"></i></button>
              </div>
          </div>

          <div class="mb-3">
              <label for="modal_confirmar_senha" class="form-label small mb-1 fw-bold">Confirme a Nova Senha</label>
              <div class="input-group">
                  <input type="password" id="modal_confirmar_senha" name="confirmar_senha" class="form-control form-control-sm" placeholder="Repita a nova senha..." required style="border-radius: 8px 0 0 8px;">
                  <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleSenhaModal('modal_confirmar_senha', this)" style="border-radius: 0 8px 8px 0;"><i class="bi bi-eye"></i></button>
              </div>
          </div>

          <!-- Box de validação dinâmica -->
          <div class="password-strength-box p-3 bg-light border" style="border-radius: 12px; font-size: 0.74rem;">
                <div class="strength-title font-weight-bold mb-2">Requisitos exigidos:</div>
                <div class="strength-rule" id="m-rule-len" style="font-size:0.75rem;"><i class="bi bi-circle"></i> Mínimo de 8 caracteres</div>
                <div class="strength-rule" id="m-rule-upper" style="font-size:0.75rem;"><i class="bi bi-circle"></i> Pelo menos 1 maiúscula (A-Z)</div>
                <div class="strength-rule" id="m-rule-lower" style="font-size:0.75rem;"><i class="bi bi-circle"></i> Pelo menos 1 minúscula (a-z)</div>
                <div class="strength-rule" id="m-rule-num" style="font-size:0.75rem;"><i class="bi bi-circle"></i> Pelo menos 1 número (0-9)</div>
                <div class="strength-rule" id="m-rule-special" style="font-size:0.75rem;"><i class="bi bi-circle"></i> Pelo menos 1 caractere especial (!@#$...)</div>
          </div>

        </div>
        <div class="modal-footer border-0 pt-0 justify-content-end gap-2">
          <button type="button" class="btn btn-sm btn-light px-3 py-2 fw-semibold" style="border-radius: 8px;" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-sm btn-warning px-3 py-2 fw-semibold text-dark" style="border-radius: 8px;">Redefinir Senha</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ══ MODAL 3: CONFIRMAR EXCLUSÃO DE CONTA ════════════════════════════ -->
<div class="modal fade" id="modalExcluirUsuario" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow" style="border-radius: 16px;">
      <form action="admin.php" method="POST">
                        <?= csrf_campo() ?>
        <input type="hidden" name="aba" value="usuarios">
        <input type="hidden" name="acao" value="excluir_usuario">
        <input type="hidden" name="usuario_id" id="excluir_usuario_id">
        
        <div class="modal-header border-0 pb-0">
          <h6 class="modal-title fw-bold text-danger d-flex align-items-center gap-2" style="font-family: var(--font-title);"><i class="bi bi-exclamation-octagon-fill text-danger fs-5"></i> Excluir Conta</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" style="font-size: 0.8rem;"></button>
        </div>
        <div class="modal-body pt-3">
          <p class="small text-muted mb-0">Você realmente deseja excluir permanentemente o analista <strong id="excluir_nome_usuario" class="text-dark"></strong>?</p>
          <span class="badge bg-danger-subtle text-danger border border-danger-subtle d-block text-center mt-3 py-2 small" style="border-radius: 8px; font-size: 0.72rem; line-height: 1.4;">Esta ação excluirá por cascata todos os diagnósticos vinculados à conta dele.</span>
        </div>
        <div class="modal-footer border-0 pt-0 justify-content-end gap-2">
          <button type="button" class="btn btn-sm btn-light px-3 py-2 fw-semibold" style="border-radius: 8px;" data-bs-dismiss="modal">Voltar</button>
          <button type="submit" class="btn btn-sm btn-danger px-3 py-2 fw-semibold" style="border-radius: 8px;">Excluir Conta</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script>
// Abertura dinâmica dos modais
function abrirModalTipo(id, nome, tipo) {
    document.getElementById('tipo_usuario_id').value = id;
    document.getElementById('tipo_nome_usuario').textContent = nome;
    document.getElementById('tipo_select').value = tipo;
    new bootstrap.Modal(document.getElementById('modalTipo')).show();
}

function abrirModalSenha(id, nome) {
    document.getElementById('senha_usuario_id').value = id;
    document.getElementById('senha_nome_usuario').textContent = nome;
    document.getElementById('modal_nova_senha').value = '';
    document.getElementById('modal_confirmar_senha').value = '';
    analisarSenhaReset('');
    new bootstrap.Modal(document.getElementById('modalSenha')).show();
}

function abrirModalExcluir(id, nome) {
    document.getElementById('excluir_usuario_id').value = id;
    document.getElementById('excluir_nome_usuario').textContent = nome;
    new bootstrap.Modal(document.getElementById('modalExcluirUsuario')).show();
}

function abrirModalFinanceiro(id, nome, saldo, custo, bonus) {
    document.getElementById('fin_usuario_id').value = id;
    document.getElementById('fin_nome_usuario').textContent = nome;
    document.getElementById('fin_saldo').value = parseFloat(saldo).toFixed(2);
    document.getElementById('fin_custo').value = custo !== '' && custo !== null ? parseFloat(custo).toFixed(2) : '';
    document.getElementById('fin_bonus').value = parseInt(bonus);
    new bootstrap.Modal(document.getElementById('modalFinanceiro')).show();
}

// Ocultar/mostrar senha no modal
function toggleSenhaModal(inputId, button) {
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

// Validador de senha do modal em tempo real
let resetSenhaValida = false;

function analisarSenhaReset(senha) {
    const rules = {
        len: senha.length >= 8,
        upper: /[A-Z]/.test(senha),
        lower: /[a-z]/.test(senha),
        num: /[0-9]/.test(senha),
        special: /[^A-Za-z0-9]/.test(senha)
    };

    atualizarRegraModal('m-rule-len', rules.len);
    atualizarRegraModal('m-rule-upper', rules.upper);
    atualizarRegraModal('m-rule-lower', rules.lower);
    atualizarRegraModal('m-rule-num', rules.num);
    atualizarRegraModal('m-rule-special', rules.special);

    resetSenhaValida = rules.len && rules.upper && rules.lower && rules.num && rules.special;
}

function atualizarRegraModal(elementId, met) {
    const element = document.getElementById(elementId);
    const icon = element.querySelector('i');
    if (met) {
        element.style.color = '#16a34a';
        icon.className = 'bi bi-check-circle-fill';
    } else {
        element.style.color = '#94a3b8';
        icon.className = 'bi bi-circle';
    }
}

function validarSenhaReset(e) {
    const senha = document.getElementById('modal_nova_senha').value;
    const confirmar = document.getElementById('modal_confirmar_senha').value;

    if (!resetSenhaValida) {
        alert('A senha informada não atende aos requisitos de segurança.');
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
