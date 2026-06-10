<?php
// ============================================================
//  Rajo Diagnóstico — Área Financeira do Analista
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

// Apenas usuários logados acessam
exigir_login();

$usuario_id = (int)$_SESSION['usuario_id'];
$is_master = e_master();

// ─── ENDPOINT AJAX: VALIDAÇÃO DE CUPOM DE DESCONTO ───────────────────
if (isset($_POST['ajax_cupom'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $codigo = strtoupper(trim($_POST['ajax_cupom']));
        
        $stmt = db()->prepare("SELECT * FROM cupons WHERE codigo = ? AND ativo = 1");
        $stmt->execute([$codigo]);
        $cupom = $stmt->fetch();
        
        if (!$cupom) {
            echo json_encode(['ok' => false, 'msg' => 'Cupom de desconto inválido ou inativo.']);
            exit;
        }
        
        if ($cupom['limite_usos'] !== null && (int)$cupom['usos'] >= (int)$cupom['limite_usos']) {
            echo json_encode(['ok' => false, 'msg' => 'Este cupom de desconto já atingiu o limite máximo de utilizações.']);
            exit;
        }
        
        echo json_encode([
            'ok'    => true,
            'tipo'  => $cupom['tipo'],
            'valor' => (float)$cupom['valor']
        ]);
        exit;
    } catch (Throwable $e) {
        registrar_log('Erro ao processar cupom: ' . $e->getMessage(), 'ERROR');
        echo json_encode(['ok' => false, 'msg' => 'Não foi possível validar o cupom no momento.']);
        exit;
    }
}

$erro = '';
$sucesso = '';
$mostrar_pix = false;
$valor_pix = 0.00;
$valor_credito = 0.00;
$desconto_aplicado = 0.00;
$cupom_utilizado = '';

// ─── PROCESSAMENTO DE SOLICITAÇÃO DE RECARGA ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'solicitar_recarga') {
    csrf_validar();
    try {
        $valor_solicitado = (float)($_POST['valor_recarga'] ?? 0.00);
        $codigo_cupom = strtoupper(trim($_POST['cupom_codigo'] ?? ''));

        if ($valor_solicitado < 10.00) {
            $erro = 'O valor mínimo para recarga de saldo é de R$ 10,00.';
        } else {
            $valor_liquido = $valor_solicitado;
            $tipo_desconto = '';
            $valor_desconto = 0.00;

            // Valida e aplica cupom se houver
            if ($codigo_cupom !== '') {
                $stmt_c = db()->prepare("SELECT * FROM cupons WHERE codigo = ? AND ativo = 1");
                $stmt_c->execute([$codigo_cupom]);
                $cupom = $stmt_c->fetch();

                if ($cupom && ($cupom['limite_usos'] === null || (int)$cupom['usos'] < (int)$cupom['limite_usos'])) {
                    $cupom_utilizado = $cupom['codigo'];
                    if ($cupom['tipo'] === 'porcentagem') {
                        $desconto_aplicado = $valor_solicitado * ((float)$cupom['valor'] / 100);
                    } else {
                        $desconto_aplicado = min($valor_solicitado, (float)$cupom['valor']);
                    }
                    $valor_liquido = max(0.00, $valor_solicitado - $desconto_aplicado);
                    
                    // Incrementa o uso do cupom
                    $upd_c = db()->prepare("UPDATE cupons SET usos = usos + 1 WHERE id = ?");
                    $upd_c->execute([$cupom['id']]);
                }
            }

            // Cria transação pendente no banco
            $descricao = "Solicitação de recarga de saldo (R$ " . number_format($valor_solicitado, 2, ',', '.') . ")";
            if ($cupom_utilizado !== '') {
                $descricao .= " com desconto do cupom {$cupom_utilizado} (Valor líquido: R$ " . number_format($valor_liquido, 2, ',', '.') . ")";
            }

            $stmt_t = db()->prepare("INSERT INTO transacoes (usuario_id, tipo, valor, descricao, status) VALUES (?, 'recarga', ?, ?, 'pendente')");
            $stmt_t->execute([$usuario_id, $valor_solicitado, $descricao]);
            $fatura_id = db()->lastInsertId();

            $mostrar_pix = true;
            $valor_pix = $valor_liquido;
            $valor_credito = $valor_solicitado;
            $sucesso = 'Instruções de PIX e Fatura geradas com sucesso! Efetue o pagamento para liberação do saldo.';
        }
    } catch (Throwable $e) {
        registrar_log('Erro ao processar solicitação de recarga: ' . $e->getMessage(), 'ERROR');
        $erro = 'Não foi possível processar a solicitação. Tente novamente em instantes.';
    }
}

// ─── CARREGAMENTO DE DADOS DO BANCO ──────────────────────────────────
// Dados financeiros do analista logado
$stmt_u = db()->prepare("SELECT saldo, custo_relatorio, bonus_relatorios, tipo FROM usuarios WHERE id = ?");
$stmt_u->execute([$usuario_id]);
$u = $stmt_u->fetch();

$saldo_disponivel = (float)($u['saldo'] ?? 0.00);
$bonus_ativos = (int)($u['bonus_relatorios'] ?? 0);
$custo_atual = obterCustoUsuario($usuario_id);

// Histórico de transações (extrato)
$stmt_t = db()->prepare("SELECT id, tipo, valor, status, descricao, criado_em FROM transacoes WHERE usuario_id = ? ORDER BY criado_em DESC");
$stmt_t->execute([$usuario_id]);
$extrato = $stmt_t->fetchAll();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Painel Financeiro — <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="assets/style.css?v=<?= time() ?>">
<style>
    .finance-card {
        border-radius: 20px;
        border: 1px solid rgba(255,255,255,0.06);
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        color: #fff;
        padding: 24px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        position: relative;
        overflow: hidden;
    }
    .finance-card::after {
        content: '';
        position: absolute;
        width: 150px;
        height: 150px;
        background: radial-gradient(circle, rgba(59,130,246,0.08) 0%, transparent 70%);
        top: -50px;
        right: -50px;
    }
    .extrato-table th {
        font-family: var(--font-title);
        color: #64748b;
        font-weight: 600;
        background-color: #f8fafc;
        border: none;
    }
    .extrato-table td {
        border-bottom: 1px solid #f1f5f9;
        padding: 14px 10px;
        font-size: 0.88rem;
    }
</style>
</head>
<body>

<nav class="navbar navbar-dark rajo-navbar px-4">
  <div class="container-fluid d-flex justify-content-between align-items-center">
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
  </div>
</nav>

<div class="container py-5" style="max-width: 1200px;">

    <!-- Cabeçalho -->
    <div class="mb-5">
        <h3 class="fw-extrabold mb-1" style="color: var(--dark-bg); font-family: var(--font-title);">Meu Painel Financeiro</h3>
        <p class="text-muted small mb-0"><i class="bi bi-wallet2 text-primary"></i> Acompanhe seu saldo, confira seu bônus de relatórios e adicione novos créditos via PIX.</p>
    </div>

    <!-- Feedbacks -->
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

    <!-- CARDS DE SALDOS E DADOS -->
    <div class="row g-4 mb-5">
        <!-- Saldo -->
        <div class="col-12 col-md-4">
            <div class="finance-card">
                <span class="text-white-50 small text-uppercase fw-bold d-block mb-1" style="font-size:0.72rem; letter-spacing: 0.05em;">Saldo Disponível</span>
                <strong class="display-6 fw-bold text-white d-block mb-2">R$ <?= number_format($saldo_disponivel, 2, ',', '.') ?></strong>
                <span class="text-white-50 small d-block" style="font-size:0.75rem;"><i class="bi bi-coin text-warning me-1"></i>Crédito válido para novas emissões</span>
            </div>
        </div>
        
        <!-- Bônus -->
        <div class="col-12 col-md-4">
            <div class="finance-card" style="background: linear-gradient(135deg, #1e1b4b 0%, #311042 100%);">
                <span class="text-white-50 small text-uppercase fw-bold d-block mb-1" style="font-size:0.72rem; letter-spacing: 0.05em;">Bônus Disponíveis</span>
                <strong class="display-6 fw-bold text-white d-block mb-2"><?= $bonus_ativos ?> bônus</strong>
                <span class="text-white-50 small d-block" style="font-size:0.75rem;"><i class="bi bi-gift-fill text-danger me-1"></i>Emissões grátis sem descontar do saldo</span>
            </div>
        </div>

        <!-- Custo por Relatório -->
        <div class="col-12 col-md-4">
            <div class="finance-card" style="background: linear-gradient(135deg, #022c22 0%, #064e3b 100%);">
                <span class="text-white-50 small text-uppercase fw-bold d-block mb-1" style="font-size:0.72rem; letter-spacing: 0.05em;">Custo de Emissão</span>
                <strong class="display-6 fw-bold text-white d-block mb-2">
                    <?php if ($u['tipo'] === 'master'): ?>
                        R$ 0,00
                    <?php else: ?>
                        R$ <?= number_format($custo_atual, 2, ',', '.') ?>
                    <?php endif; ?>
                </strong>
                <span class="text-white-50 small d-block" style="font-size:0.75rem;">
                    <?php if ($u['tipo'] === 'master'): ?>
                        <i class="bi bi-shield-check text-success me-1"></i>Isenção Master ativada
                    <?php elseif ($u['custo_relatorio'] !== null): ?>
                        <i class="bi bi-star-fill text-warning me-1"></i>Custo Especial Ativo
                    <?php else: ?>
                        <i class="bi bi-info-circle text-white-50 me-1"></i>Tarifa padrão global do sistema
                    <?php endif; ?>
                </span>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- ÁREA DE RECARGA -->
        <div class="col-12 col-lg-5">
            <?php if ($mostrar_pix): 
                $txid_pix = 'REC' . str_pad($fatura_id, 9, '0', STR_PAD_LEFT);
                $pix_copia_cola_code = gerarCopiaColaPix($valor_pix, $txid_pix);
                $pix_qr_code_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($pix_copia_cola_code);
            ?>
                <!-- TELA DO PIX GERADA -->
                <div class="rajo-panel border-0 shadow-sm p-4 bg-white text-center" style="border-radius: 16px;">
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle py-2 px-3 mb-3 small" style="border-radius:20px; font-size:0.72rem;"><i class="bi bi-hourglass-split me-1"></i>AGUARDANDO PAGAMENTO</span>
                    
                    <h5 class="fw-bold text-dark mb-2" style="font-family:var(--font-title);">Efetuar Pagamento PIX</h5>
                    <p class="text-muted small mb-4" style="line-height:1.4;">Escaneie o QR Code abaixo ou utilize a linha de pagamento Pix Copia e Cola para realizar o depósito. O saldo será liberado após a confirmação administrativa.</p>
                    
                    <!-- QR Code -->
                    <div class="mb-4">
                        <img src="<?= $pix_qr_code_url ?>" alt="QR Code PIX" class="img-fluid border p-2 bg-light shadow-sm" style="width: 170px; height: 170px; border-radius: 12px;">
                    </div>

                    <div class="p-3 border rounded shadow-sm bg-light mb-4" style="border-radius:12px;">
                        <span class="text-muted small d-block mb-1">Valor Líquido a Pagar</span>
                        <strong class="text-success display-6 fw-bold">R$ <?= number_format($valor_pix, 2, ',', '.') ?></strong>
                        <?php if ($desconto_aplicado > 0): ?>
                            <span class="badge bg-success text-white py-1 px-2.5 mt-2 small" style="border-radius:6px; font-size:0.65rem;">Cupom aplicado: - R$ <?= number_format($desconto_aplicado, 2, ',', '.') ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- PIX Copia e Cola -->
                    <div class="text-start border p-3 rounded mb-4" style="border-radius: 12px; background: rgba(59,130,246,0.01);">
                        <span class="text-muted small fw-semibold d-block mb-1 text-uppercase" style="font-size:0.7rem;">Código PIX Copia e Cola</span>
                        <div class="d-flex align-items-center bg-white border p-2 rounded mb-2">
                            <input type="text" class="form-control form-control-sm font-monospace text-dark border-0 bg-transparent fw-bold" id="pixCopiaColaInput" readonly value="<?= htmlspecialchars($pix_copia_cola_code) ?>" style="font-size: 0.72rem; overflow-x: auto; box-shadow:none;">
                            <button type="button" class="btn btn-sm btn-primary px-3 py-1.5" onclick="copyPixCopiaCola()" style="border-radius: 6px; font-size: 0.8rem; font-weight:600;"><i class="bi bi-copy"></i> Copiar</button>
                        </div>
                        <small class="text-muted d-block" style="font-size:0.7rem; line-height:1.4;"><i class="bi bi-bank me-1"></i>Favorecido: <strong>Rajo Desenvolvimento</strong> &bull; CNPJ Oficial</small>
                    </div>

                    <div class="d-flex flex-column gap-2">
                        <a href="fatura.php?id=<?= $fatura_id ?>" target="_blank" class="btn btn-outline-primary btn-sm py-2 fw-semibold d-inline-flex align-items-center justify-content-center gap-1.5" style="border-radius: 8px;">
                            <i class="bi bi-file-earmark-pdf-fill"></i> Visualizar / Imprimir Fatura Completa (PDF)
                        </a>
                        <a href="financeiro.php" class="btn btn-primary btn-sm py-2 fw-bold" style="border-radius: 8px;">
                            Já Efetuei o Pagamento <i class="bi bi-check-lg ms-1"></i>
                        </a>
                        <a href="financeiro.php" class="btn btn-link text-muted small mt-1 d-inline-block text-decoration-none">Voltar ao Simulador</a>
                    </div>
                </div>
            <?php else: ?>
                <!-- SIMULADOR DE VALORES -->
                <div class="rajo-panel border-0 shadow-sm p-4 bg-white" style="border-radius: 16px;">
                    <h5 class="fw-bold mb-3 text-dark" style="font-family: var(--font-title);"><i class="bi bi-wallet2 text-primary me-2"></i>Adicionar Saldo de Créditos</h5>
                    
                    <form action="financeiro.php" method="POST" id="formRecarga">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="acao" value="solicitar_recarga">
                        
                        <div class="mb-3">
                            <label for="valor_recarga" class="form-label fw-bold small text-muted mb-1">Valor do Depósito (R$)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0 fw-bold" style="border-radius: 8px 0 0 8px;">R$</span>
                                <input type="number" step="1" min="10" id="valor_recarga" name="valor_recarga" class="form-control ps-2 border-start-0" placeholder="Ex.: 100" required oninput="calcularPrevia()" style="border-radius: 0 8px 8px 0;">
                            </div>
                            <small class="text-muted" style="font-size:0.7rem;">Valor mínimo de recarga: R$ 10,00.</small>
                        </div>

                        <!-- Cupom de Desconto -->
                        <div class="mb-4">
                            <label for="cupom" class="form-label fw-bold small text-muted mb-1">Cupom de Desconto</label>
                            <div class="input-group">
                                <input type="text" id="cupom" name="cupom_codigo" class="form-control text-uppercase" placeholder="Digite o cupom..." autocomplete="off" style="border-radius: 8px 0 0 8px;">
                                <button type="button" class="btn btn-outline-primary px-3" onclick="validarCupomAjax()" style="border-radius: 0 8px 8px 0;">Aplicar</button>
                            </div>
                            <span id="cupomMsg" class="d-block small mt-1.5"></span>
                        </div>

                        <!-- Prévia Financeira Completa -->
                        <div class="p-3 border rounded mb-4 d-none" id="previaBox" style="border-radius: 12px; background-color: #f8fafc;">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted small">Valor Solicitado</span>
                                <span class="text-dark fw-semibold" id="previaBruto">R$ 0,00</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2 text-success d-none" id="previaDescontoBox">
                                <span class="small font-weight-bold">Desconto do Cupom</span>
                                <span class="fw-bold" id="previaDesconto">- R$ 0,00</span>
                            </div>
                            <div class="d-flex justify-content-between border-top pt-2 fw-bold" style="font-size:1.02rem;">
                                <span class="text-dark">Valor Líquido (PIX)</span>
                                <span class="text-success" id="previaLiquido">R$ 0,00</span>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2.5 fw-bold" style="border-radius: 8px;">
                            Gerar Chave PIX <i class="bi bi-qr-code ms-1"></i>
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <!-- EXTRATO FINANCEIRO -->
        <div class="col-12 col-lg-7">
            <div class="rajo-panel border-0 shadow-sm p-4 bg-white h-100" style="border-radius: 16px;">
                <h5 class="fw-bold mb-3 text-dark" style="font-family: var(--font-title);"><i class="bi bi-clock-history text-primary me-2"></i>Histórico Financeiro (Extrato)</h5>
                
                <?php if (empty($extrato)): ?>
                    <div class="text-center py-5 text-muted small">
                        <i class="bi bi-journal-x fs-3 d-block mb-2"></i>
                        Nenhuma movimentação registrada no seu extrato.
                    </div>
                <?php else: ?>
                        <table class="table extrato-table align-middle mb-0" style="font-size:0.85rem;">
                            <thead>
                                <tr>
                                    <th class="ps-3">Data / Hora</th>
                                    <th>Tipo</th>
                                    <th>Descrição</th>
                                    <th class="text-end">Valor</th>
                                    <th class="text-end pe-3">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($extrato as $e): ?>
                                <tr>
                                    <td class="ps-3 text-muted font-monospace" style="font-size:0.78rem;"><?= date('d/m/Y H:i', strtotime($e['criado_em'])) ?></td>
                                    <td>
                                        <?php if ($e['tipo'] === 'recarga'): ?>
                                            <?php if ($e['status'] === 'pendente'): ?>
                                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle py-1 px-2.5" style="border-radius:8px; font-size:0.65rem;">Recarga Pendente</span>
                                            <?php elseif ($e['status'] === 'rejeitado'): ?>
                                                <span class="badge bg-secondary-subtle text-muted border border-secondary-subtle py-1 px-2.5" style="border-radius:8px; font-size:0.65rem;">Recarga Rejeitada</span>
                                            <?php else: ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle py-1 px-2.5" style="border-radius:8px; font-size:0.65rem;">Recarga Concluída</span>
                                            <?php endif; ?>
                                        <?php elseif ($e['tipo'] === 'emissao'): ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle py-1 px-2.5" style="border-radius:8px; font-size:0.65rem;">Débito Emissão</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle py-1 px-2.5" style="border-radius:8px; font-size:0.65rem;">Bônus Emitido</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted small" style="max-width: 200px; line-height: 1.45;"><?= htmlspecialchars($e['descricao']) ?></td>
                                    <td class="text-end fw-bold <?= $e['valor'] > 0 ? 'text-success' : ($e['valor'] < 0 ? 'text-danger' : 'text-primary') ?>">
                                        <?= $e['valor'] > 0 ? '+ R$ ' . number_format($e['valor'], 2, ',', '.') : ($e['valor'] < 0 ? '- R$ ' . number_format(abs($e['valor']), 2, ',', '.') : 'R$ 0,00') ?>
                                    </td>
                                    <td class="text-end pe-3">
                                        <?php if ($e['tipo'] === 'recarga'): ?>
                                            <a href="fatura.php?id=<?= $e['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary py-1 px-2" style="font-size: 0.72rem; border-radius: 6px; font-weight:500;" title="Ver Fatura / QR Code PIX">
                                                <i class="bi bi-file-earmark-pdf"></i> Fatura
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted" style="font-size:0.75rem;">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<script>
let cupomAtivo = null;

function calcularPrevia() {
    const input = document.getElementById('valor_recarga');
    const valor = parseFloat(input.value) || 0.00;
    const box = document.getElementById('previaBox');

    if (valor <= 0) {
        box.classList.add('d-none');
        return;
    }

    box.classList.remove('d-none');
    document.getElementById('previaBruto').textContent = 'R$ ' + valor.toFixed(2).replace('.', ',');
    
    let desconto = 0.00;
    const descBox = document.getElementById('previaDescontoBox');
    
    if (cupomAtivo) {
        descBox.classList.remove('d-none');
        if (cupomAtivo.tipo === 'porcentagem') {
            desconto = valor * (cupomAtivo.valor / 100);
        } else {
            desconto = Math.min(valor, cupomAtivo.valor);
        }
        document.getElementById('previaDesconto').textContent = '- R$ ' + desconto.toFixed(2).replace('.', ',');
    } else {
        descBox.classList.add('d-none');
    }

    const liquido = Math.max(0.00, valor - desconto);
    document.getElementById('previaLiquido').textContent = 'R$ ' + liquido.toFixed(2).replace('.', ',');
}

function validarCupomAjax() {
    const cupomInput = document.getElementById('cupom');
    const codigo = cupomInput.value.trim();
    const msg = document.getElementById('cupomMsg');

    if (codigo === '') {
        msg.className = 'd-block small mt-1.5 text-danger';
        msg.textContent = 'Digite o código do cupom.';
        return;
    }

    // Ajax POST
    const formData = new FormData();
    formData.append('ajax_cupom', codigo);

    fetch('financeiro.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.ok) {
            cupomAtivo = {
                tipo: data.tipo,
                valor: data.valor
            };
            msg.className = 'd-block small mt-1.5 text-success';
            const texto_desconto = data.tipo === 'porcentagem' ? parseInt(data.valor) + '%' : 'R$ ' + data.valor.toFixed(2).replace('.', ',');
            msg.textContent = '✓ Cupom aplicado com sucesso: Desconto de ' + texto_desconto;
            calcularPrevia();
        } else {
            cupomAtivo = null;
            msg.className = 'd-block small mt-1.5 text-danger';
            msg.textContent = '✗ ' + data.msg;
            calcularPrevia();
        }
    })
    .catch(error => {
        cupomAtivo = null;
        msg.className = 'd-block small mt-1.5 text-danger';
        msg.textContent = 'Erro ao validar cupom de desconto.';
        calcularPrevia();
    });
}

function copyPixKey() {
    navigator.clipboard.writeText("28826574000132");
    alert("Chave CNPJ PIX copiada para a área de transferência!");
}

function copyPixCopiaCola() {
    const input = document.getElementById("pixCopiaColaInput");
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value);
    alert("Código PIX Copia e Cola copiado para a área de transferência!");
}
</script>
</body>
</html>
