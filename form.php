<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
exigir_login();

$id = (int)($_GET['id'] ?? 0);
$rel = null;

if ($id > 0) {
    if (e_master()) {
        // Master tem permissão livre para carregar qualquer relatório
        $stmt = db()->prepare('SELECT * FROM relatorios WHERE id = ?');
        $stmt->execute([$id]);
    } else {
        // Analistas comuns só carregam seus próprios relatórios
        $stmt = db()->prepare('SELECT * FROM relatorios WHERE id = ? AND usuario_id = ?');
        $stmt->execute([$id, $_SESSION['usuario_id']]);
    }
    $rel = $stmt->fetch();
    if (!$rel) { header('Location: index.php'); exit; }
}

$d = $rel ?? [];
$problemas = json_decode($d['problemas'] ?? '[]', true) ?: [[
    'problema'  => '',
    'impacto'   => '',
    'prioridade'=> 'Alta',
]];
$acoes = json_decode($d['acoes'] ?? '[]', true) ?: [[
    'acao'        => '',
    'responsavel' => 'Desenvolvedor',
    'prazo'       => '',
]];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $id ? 'Editar' : 'Novo' ?> Relatório — <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="assets/style.css?v=<?= time() ?>">
</head>
<body>

<nav class="navbar navbar-dark rajo-navbar px-4">
  <a href="index.php" class="navbar-brand fw-bold fs-4 d-flex align-items-center gap-2 text-decoration-none">
    <span class="rajo-logo-icon">R</span> Rajo Diagnóstico
  </a>
  <div class="d-flex align-items-center gap-3">
    <?php if (e_master()): ?>
    <a href="admin.php" class="btn btn-sm btn-warning px-3 py-1.5 d-inline-flex align-items-center gap-1" style="border-radius: 8px; font-weight: 600; font-size: 0.85rem; color: #1e293b;">
      <i class="bi bi-shield-lock-fill"></i> Administração
    </a>
    <?php endif; ?>
    <a href="index.php" class="btn btn-outline-light btn-sm d-inline-flex align-items-center gap-1" style="border-radius: 8px;">
      <i class="bi bi-arrow-left"></i> Dashboard
    </a>
    <a href="logout.php" class="btn btn-sm btn-outline-light px-3 py-1.5 d-inline-flex align-items-center gap-1" style="border-radius: 8px; font-weight: 500; font-size: 0.85rem; border-color: rgba(255,255,255,0.25);">
      <i class="bi bi-box-arrow-right"></i> Sair
    </a>
  </div>
</nav>

<div class="container py-4" style="max-width:820px">

  <div class="mb-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <h5 class="fw-bold mb-0" style="color:#1A4FBB">
        <?= $id ? 'Editar Relatório' : 'Novo Relatório de Diagnóstico' ?>
      </h5>
      <?php if ($id): ?>
      <small class="text-muted">Cliente: <strong><?= e($d['cliente']) ?></strong> — <?= e($d['dominio']) ?></small>
      <?php endif; ?>
    </div>
    <button type="button" class="btn btn-outline-warning btn-sm d-inline-flex align-items-center gap-1 shadow-sm px-3 py-2 fw-semibold" style="border-radius: 20px;" onclick="salvarRascunho()">
      <i class="bi bi-floppy-fill text-warning"></i> Salvar Rascunho
    </button>
  </div>

  <!-- Progress bar -->
  <div class="rajo-steps mb-4">
    <?php
    $passos = [
      1 => ['icon'=>'person-badge',      'label'=>'Cliente'],
      2 => ['icon'=>'speedometer2',       'label'=>'Pontuações'],
      3 => ['icon'=>'bar-chart-line',     'label'=>'Web Vitals'],
      4 => ['icon'=>'exclamation-triangle','label'=>'Problemas'],
      5 => ['icon'=>'list-check',         'label'=>'Plano'],
      6 => ['icon'=>'chat-left-text',     'label'=>'Conclusão'],
      7 => ['icon'=>'check2-circle',      'label'=>'Revisão'],
    ];
    foreach ($passos as $n => $p): ?>
    <div class="rajo-step" data-step="<?= $n ?>" onclick="goStep(<?= $n ?>)">
      <div class="rajo-step-icon"><i class="bi bi-<?= $p['icon'] ?>"></i></div>
      <span class="rajo-step-label"><?= $p['label'] ?></span>
    </div>
    <?php endforeach; ?>
    <div class="rajo-progress-line"><div id="progressFill" class="rajo-progress-fill"></div></div>
  </div>

  <!-- Seção de Modelos Rápidos (Presets) -->
  <div class="rajo-presets-panel mb-4 shadow-sm">
    <div class="small fw-bold text-muted mb-2 d-flex align-items-center gap-1">
      <i class="bi bi-lightning-charge-fill text-warning"></i> Modelos Rápidos (Preenchimento Completo em 1-Clique)
    </div>
    <div class="d-flex flex-wrap gap-2">
      <div class="preset-chip" onclick="aplicarPreset('timeout')">
        <i class="bi bi-cpu text-danger"></i> Lentidão Extrema (Timeout GTmetrix)
      </div>
      <div class="preset-chip" onclick="aplicarPreset('seo')">
        <i class="bi bi-search text-primary"></i> SEO On-Page Básico Incompleto
      </div>
      <div class="preset-chip" onclick="aplicarPreset('ads')">
        <i class="bi bi-google text-success"></i> Falta de Pixel & Tags Google Ads
      </div>
    </div>
  </div>

  <form id="formRelatorio" novalidate>
    <input type="hidden" name="id" value="<?= $id ?>">

    <!-- ══ STEP 1: Dados do Cliente ════════════════════════════ -->
    <div class="step-panel" id="step1">
      <div class="rajo-panel shadow-sm">
        <div class="rajo-panel-title"><i class="bi bi-person-badge me-2 text-primary"></i>Dados do Cliente</div>
        <div class="row g-3">
          <div class="col-12 col-md-6">
            <label class="form-label fw-semibold">Nome do Cliente <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="cliente" required
                   value="<?= e($d['cliente'] ?? '') ?>" placeholder="Ex.: Empresa ABC Ltda.">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label fw-semibold">Domínio do Site <span class="text-danger">*</span></label>
            <div class="input-group">
              <input type="text" class="form-control" name="dominio" required
                     value="<?= e($d['dominio'] ?? '') ?>" placeholder="Ex.: www.empresa.com.br">
              <button type="button" class="btn btn-pagespeed-trigger d-inline-flex align-items-center gap-2" onclick="analisarPageSpeed()" title="Analisar site automaticamente via Google PageSpeed Insights">
                <i class="bi bi-stars"></i> <span class="d-none d-sm-inline">Auditar via PageSpeed</span>
              </button>
            </div>
            <small class="text-muted d-block mt-1" style="font-size: 0.72rem;">Insira o domínio e clique em <strong>Auditar via PageSpeed</strong> para importar notas automaticamente do Google!</small>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label fw-semibold">Data do Relatório <span class="text-danger">*</span></label>
            <input type="date" class="form-control" name="data_relatorio" required
                   value="<?= e($d['data_relatorio'] ?? date('Y-m-d')) ?>">
          </div>
          <div class="col-12 col-md-5">
            <label class="form-label fw-semibold">Analista Responsável</label>
            <input type="text" class="form-control" name="analista"
                   value="<?= e($d['analista'] ?? ANALISTA_PADRAO) ?>">
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label fw-semibold">Versão</label>
            <input type="text" class="form-control" name="versao"
                   value="<?= e($d['versao'] ?? '1.0') ?>" placeholder="1.0">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Cor do Tema (PDF & Link)</label>
            <select class="form-select" name="pdf_cor_tema">
              <?php
              $temas = [
                '#1A4FBB' => '🔵 Rajo Indigo (Padrão)',
                '#10B981' => '🟢 Emerald (Verde)',
                '#F59E0B' => '🟡 Amber (Laranja)',
                '#6366F1' => '🟣 Violet (Roxo)',
                '#EF4444' => '🔴 Crimson (Vermelho)',
              ];
              foreach ($temas as $hex => $label): ?>
              <option value="<?= $hex ?>" <?= ($d['pdf_cor_tema'] ?? '#1A4FBB') === $hex ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Link do Logotipo do Cliente <span class="text-muted fw-normal">(Opcional, URL de imagem externa)</span></label>
            <input type="text" class="form-control" name="logo_cliente" 
                   value="<?= e($d['logo_cliente'] ?? '') ?>" placeholder="Ex.: https://site.com/logo.png">
            <small class="text-muted" style="font-size: 0.72rem;">Se preenchido, esta logo será exibida com destaque na capa do PDF e no Painel Online.</small>
          </div>
        </div>
      </div>
      <div class="d-flex justify-content-end mt-3">
        <button type="button" class="btn btn-primary px-4" onclick="nextStep()">
          Próximo <i class="bi bi-arrow-right ms-1"></i>
        </button>
      </div>
    </div>

    <!-- ══ STEP 2: Pontuações ═══════════════════════════════════ -->
    <div class="step-panel d-none" id="step2">
      <div class="rajo-panel">
        <div class="rajo-panel-title"><i class="bi bi-speedometer2 me-2"></i>Pontuações — PageSpeed Insights</div>
        <p class="text-muted small mb-3">Acesse <a href="https://pagespeed.web.dev" target="_blank">pagespeed.web.dev</a> e insira as notas (0–100) para cada categoria.</p>
        <div class="table-responsive">
          <table class="table table-bordered align-middle rajo-table-scores">
            <thead>
              <tr>
                <th>Categoria</th>
                <th class="text-center">Desktop</th>
                <th class="text-center">Mobile</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $metrics = [
                ['ps_performance',    'Performance'],
                ['ps_seo',            'SEO Técnico'],
                ['ps_acessibilidade', 'Acessibilidade'],
                ['ps_boaspraticas',   'Boas Práticas'],
              ];
              foreach ($metrics as [$key, $label]): ?>
              <tr>
                <td class="fw-semibold"><?= $label ?></td>
                <td class="text-center">
                  <input type="number" min="0" max="100" class="form-control form-control-sm score-input text-center"
                         name="<?= $key ?>_desktop"
                         value="<?= e($d["{$key}_desktop"] ?? '') ?>"
                         placeholder="0–100">
                </td>
                <td class="text-center">
                  <input type="number" min="0" max="100" class="form-control form-control-sm score-input text-center"
                         name="<?= $key ?>_mobile"
                         value="<?= e($d["{$key}_mobile"] ?? '') ?>"
                         placeholder="0–100">
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>


      <div class="rajo-panel mt-3">
        <div class="rajo-panel-title"><i class="bi bi-bar-chart me-2"></i>GTmetrix</div>
        <div class="row g-3 mt-1">
          <div class="col-12 col-md-4">
            <label class="form-label fw-semibold">Nota GTmetrix (Desktop)</label>
            <select class="form-select" name="gtm_nota">
              <?php foreach (['','A','B','C','D','E','F','Erro (Lighthouse Timeout)'] as $n): ?>
              <option value="<?= $n ?>" <?= ($d['gtm_nota'] ?? '') === $n ? 'selected' : '' ?>>
                <?= $n === 'Erro (Lighthouse Timeout)' ? '⚠ Erro: Lighthouse Timeout (Sem CPU Idle)' : ($n ?: '— selecione —') ?>
              </option>
              <?php endforeach; ?>
            </select>
            <small class="text-muted">Acesse <a href="https://gtmetrix.com" target="_blank">gtmetrix.com</a> e analise o site.</small>
          </div>
        </div>
      </div>

      <!-- Ad Experience (opcional) -->
      <div class="rajo-panel mt-3">
        <div class="rajo-panel-title d-flex align-items-center justify-content-between flex-wrap gap-2">
          <span><i class="bi bi-shield-check me-2"></i>Ad Experience Report
            <span class="badge bg-secondary ms-2" style="font-size:.65rem;font-weight:500">Opcional</span>
          </span>
          <button type="button" class="btn btn-sm btn-outline-secondary"
                  data-bs-toggle="collapse" data-bs-target="#collapseAdExp">
            <i class="bi bi-question-circle me-1"></i>Como acessar?
          </button>
        </div>
        <div class="alert alert-info d-flex gap-2 py-2 px-3 mb-3" style="font-size:.82rem;border-radius:8px">
          <i class="bi bi-info-circle-fill mt-1 flex-shrink-0"></i>
          <div>
            O <strong>Ad Experience Report</strong> exige que o domínio do cliente esteja
            <em>verificado</em> no <strong>Google Search Console</strong>.
            Se não tiver acesso, preencha as <strong>verificações alternativas abaixo</strong>
            — elas cobrem os mesmos riscos de bloqueio de anúncios.
          </div>
        </div>
        <div class="collapse mb-3" id="collapseAdExp">
          <div class="card card-body border-0" style="background:#f0f4ff;border-radius:10px">
            <p class="fw-bold mb-2" style="color:#1A4FBB;font-size:.85rem">
              <i class="bi bi-person-check me-1"></i>Como o cliente autoriza seu acesso ao Search Console:
            </p>
            <ol class="small text-muted mb-2 ps-3" style="line-height:2">
              <li>Cliente acessa <a href="https://search.google.com/search-console" target="_blank">search.google.com/search-console</a></li>
              <li>Seleciona a propriedade do site (ou adiciona, se não tiver)</li>
              <li>Vai em <strong>Configurações → Usuários e permissões</strong></li>
              <li>Clica em <strong>Adicionar usuário</strong> e insere seu e-mail Google</li>
              <li>Define permissão <strong>Proprietário delegado</strong> ou <strong>Completo</strong></li>
              <li>Após aceite, você acessa: <strong>Search Console → Experiência</strong> → <strong>Ad Experience Report</strong></li>
            </ol>
            <p class="small text-muted mb-0">
              <i class="bi bi-lightbulb text-warning me-1"></i>
              <strong>Dica:</strong> Se o domínio ainda não está verificado, você mesmo pode inserir
              a metatag de verificação no <code>&lt;head&gt;</code> durante o trabalho — o cliente só
              precisa confirmar por e-mail.
            </p>
          </div>
        </div>
        <div class="row g-3">
          <div class="col-12 col-md-7">
            <label class="form-label fw-semibold">Status Ad Experience Report
              <span class="text-muted fw-normal small">(se tiver acesso)</span>
            </label>
            <select class="form-select" name="ad_experience_status">
              <?php foreach ([
                ''                          => '— sem acesso / não verificado —',
                'Passing (Aprovado)'        => '✓  Passing — Aprovado',
                'Warning (Atenção)'         => '⚠  Warning — Atenção',
                'Failing (Reprovado)'       => '✗  Failing — Reprovado (bloqueia anúncios)',
                'Domínio não verificado'    => '—  Domínio não verificado no Search Console',
              ] as $val => $label): ?>
              <option value="<?= e($val) ?>" <?= ($d['ad_experience_status'] ?? '') === $val ? 'selected' : '' ?>>
                <?= e($label) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <!-- Verificações alternativas -->
      <div class="rajo-panel mt-3">
        <div class="rajo-panel-title">
          <i class="bi bi-kanban me-2"></i>Verificações Alternativas ao Ad Experience
          <span class="badge bg-primary ms-2" style="font-size:.65rem">Recomendadas</span>
        </div>
        <p class="text-muted small mb-3">
          Nenhuma dessas ferramentas exige Search Console. Juntas cobrem os mesmos riscos de bloqueio de anúncios.
        </p>
        <div class="row g-3">

          <!-- Safe Browsing -->
          <div class="col-12">
            <div class="rajo-alternativa-card">
              <div class="d-flex align-items-start gap-3">
                <div class="rajo-alt-icon bg-danger-subtle text-danger"><i class="bi bi-shield-x"></i></div>
                <div class="flex-fill">
                  <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                    <strong style="font-size:.9rem">Google Safe Browsing</strong>
                    <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size:.65rem">Sem login</span>
                  </div>
                  <p class="text-muted small mb-2">
                    Verifica se o domínio está na lista negra do Google (malware, phishing, engenharia social).
                    Sites na lista têm anúncios automaticamente bloqueados.
                  </p>
                  <?php
                    $dom   = preg_replace('#^https?://#', '', trim($d['dominio'] ?? ''));
                    $sbUrl = 'https://transparencyreport.google.com/safe-browsing/search?url=' . urlencode('https://' . $dom);
                  ?>
                  <a href="<?= e($sbUrl) ?>" target="_blank" class="btn btn-sm btn-outline-danger mb-2">
                    <i class="bi bi-box-arrow-up-right me-1"></i>Verificar agora
                  </a>
                  <select class="form-select form-select-sm" name="safe_browsing_status" style="max-width:340px">
                    <?php foreach ([
                      ''                              => '— não verificado —',
                      'Nenhuma ameaça detectada'      => '✓  Nenhuma ameaça detectada',
                      'Site parcialmente perigoso'    => '⚠  Site parcialmente perigoso',
                      'Site perigoso (lista negra)'   => '✗  Site perigoso — na lista negra do Google',
                    ] as $v => $l): ?>
                    <option value="<?= e($v) ?>" <?= ($d['safe_browsing_status'] ?? '') === $v ? 'selected' : '' ?>>
                      <?= e($l) ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>
          </div>

          <!-- Ads Policy Center -->
          <div class="col-12">
            <div class="rajo-alternativa-card">
              <div class="d-flex align-items-start gap-3">
                <div class="rajo-alt-icon bg-warning-subtle text-warning"><i class="bi bi-google"></i></div>
                <div class="flex-fill">
                  <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                    <strong style="font-size:.9rem">Google Ads — Centro de Políticas</strong>
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle" style="font-size:.65rem">Precisa da conta Ads do cliente</span>
                  </div>
                  <p class="text-muted small mb-2">
                    Dentro da conta de Google Ads em <strong>Ferramentas → Centro de Políticas</strong>,
                    mostra anúncios reprovados, restrições e problemas diretos no domínio.
                    É a fonte mais precisa sobre o bloqueio.
                  </p>
                  <a href="https://ads.google.com/aw/policycenter" target="_blank" class="btn btn-sm btn-outline-warning mb-2">
                    <i class="bi bi-box-arrow-up-right me-1"></i>Abrir no Google Ads
                  </a>
                  <select class="form-select form-select-sm" name="ads_policy_status" style="max-width:340px">
                    <?php foreach ([
                      ''                          => '— não verificado —',
                      'Sem restrições'            => '✓  Sem restrições de política',
                      'Anúncios com restrição'    => '⚠  Anúncios com restrição de alcance',
                      'Anúncios reprovados'       => '✗  Anúncios reprovados por política',
                      'Conta suspensa'            => '✗  Conta suspensa',
                    ] as $v => $l): ?>
                    <option value="<?= e($v) ?>" <?= ($d['ads_policy_status'] ?? '') === $v ? 'selected' : '' ?>>
                      <?= e($l) ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>
          </div>

          <!-- Lighthouse Best Practices -->
          <div class="col-12">
            <div class="rajo-alternativa-card">
              <div class="d-flex align-items-start gap-3">
                <div class="rajo-alt-icon bg-primary-subtle text-primary"><i class="bi bi-lamp"></i></div>
                <div class="flex-fill">
                  <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                    <strong style="font-size:.9rem">Lighthouse — Boas Práticas</strong>
                    <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size:.65rem">Sem login</span>
                  </div>
                  <p class="text-muted small mb-0">
                    A aba <em>Best Practices</em> do Lighthouse (Chrome → F12 → Lighthouse) detecta
                    conteúdo misto HTTP/HTTPS, APIs depreciadas e problemas de segurança que acionam
                    políticas do Google Ads. A nota já aparece no campo <strong>Boas Práticas</strong>
                    do PageSpeed Insights acima — use ela aqui também. Nota abaixo de 90 requer atenção.
                  </p>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>


      <div class="d-flex justify-content-between mt-3">
        <button type="button" class="btn btn-outline-secondary px-4" onclick="prevStep()">
          <i class="bi bi-arrow-left me-1"></i> Anterior
        </button>
        <button type="button" class="btn btn-primary px-4" onclick="nextStep()">
          Próximo <i class="bi bi-arrow-right ms-1"></i>
        </button>
      </div>
    </div>

    <!-- ══ STEP 3: Core Web Vitals ════════════════════════════════ -->
    <div class="step-panel d-none" id="step3">
      <div class="rajo-panel">
        <div class="rajo-panel-title"><i class="bi bi-bar-chart-line me-2"></i>Core Web Vitals</div>
        <p class="text-muted small mb-3">Insira os valores medidos pelo PageSpeed Insights. Use o formato exibido pela ferramenta (ex.: <code>3.2 s</code>, <code>450 ms</code>, <code>0.35</code>).</p>
        <div class="table-responsive">
          <table class="table table-bordered align-middle">
            <thead>
              <tr>
                <th>Sigla</th>
                <th>Métrica</th>
                <th>Referência</th>
                <th class="text-center">Desktop</th>
                <th class="text-center">Mobile</th>
                <th class="text-center">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $cwv = [
                ['lcp',   'LCP',   'Largest Contentful Paint',   '< 2,5 s'],
                ['inp',   'INP',   'Interaction to Next Paint',   '< 200 ms'],
                ['cls',   'CLS',   'Cumulative Layout Shift',     '< 0,1'],
                ['fcp',   'FCP',   'First Contentful Paint',      '< 1,8 s'],
                ['ttfb',  'TTFB',  'Time to First Byte',          '< 600 ms'],
                ['speed', 'Speed', 'Speed Index',                 '< 3,4 s'],
              ];
              foreach ($cwv as [$key, $sigla, $nome, $ref]): ?>
              <tr>
                <td><span class="badge bg-primary"><?= $sigla ?></span></td>
                <td class="small"><?= $nome ?></td>
                <td class="small text-success fw-semibold"><?= $ref ?></td>
                <td class="text-center">
                  <input type="text" class="form-control form-control-sm text-center"
                         name="cwv_<?= $key ?>_desktop"
                         value="<?= e($d["cwv_{$key}_desktop"] ?? '') ?>"
                         placeholder="Ex.: 3.2 s">
                </td>
                <td class="text-center">
                  <input type="text" class="form-control form-control-sm text-center"
                         name="cwv_<?= $key ?>_mobile"
                         value="<?= e($d["cwv_{$key}_mobile"] ?? '') ?>"
                         placeholder="Ex.: 6.1 s">
                </td>
                <td class="text-center">
                  <select class="form-select form-select-sm" name="cwv_<?= $key ?>_status">
                    <?php foreach (['Ruim','Médio','Bom'] as $s): ?>
                    <option value="<?= $s ?>" <?= ($d["cwv_{$key}_status"] ?? 'Ruim') === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="d-flex justify-content-between mt-3">
        <button type="button" class="btn btn-outline-secondary px-4" onclick="prevStep()">
          <i class="bi bi-arrow-left me-1"></i> Anterior
        </button>
        <button type="button" class="btn btn-primary px-4" onclick="nextStep()">
          Próximo <i class="bi bi-arrow-right ms-1"></i>
        </button>
      </div>
    </div>

    <!-- ══ STEP 4: Problemas Identificados ═══════════════════════ -->
    <div class="step-panel d-none" id="step4">
      <div class="rajo-panel shadow-sm">
        <div class="rajo-panel-title"><i class="bi bi-exclamation-triangle me-2 text-danger"></i>Problemas Identificados</div>
        <p class="text-muted small mb-3">Estes problemas podem ser <strong>preenchidos 100% de forma automática</strong> pela auditoria do PageSpeed no Passo 1 ou por um dos Modelos Rápidos no topo. Sinta-se livre para editar, adicionar ou excluir itens.</p>

        <!-- NOVO PAINEL DE RESULTADOS DO CRAWLER DE SEO PROFUNDO -->
        <div id="crawlerResultsPanel" class="d-none mb-4 p-4 border rounded shadow-sm bg-light bg-opacity-10" style="border-color: rgba(0,0,0,0.08) !important; background: rgba(0,0,0,0.015);">
          <h6 class="fw-bold mb-3 text-primary d-flex align-items-center gap-2" style="font-family: var(--font-title); font-size: 0.95rem;">
            <i class="bi bi-search text-primary fs-5"></i> Relatório do Rastreamento Profundo &amp; Auditoria On-Page
          </h6>
          <div class="row g-3 mb-3 text-dark" id="crawlerStatsGrid" style="font-size: 0.82rem;">
            <!-- Estatísticas serão injetadas via Javascript -->
          </div>
          <button type="button" class="btn btn-sm btn-outline-primary w-100" data-bs-toggle="collapse" data-bs-target="#crawlerCollapsePages" style="border-radius: 8px;">
            <i class="bi bi-list-nested me-1"></i> Ver Detalhes das Páginas Rastreadas
          </button>
          <div class="collapse mt-3" id="crawlerCollapsePages">
            <div class="table-responsive" style="max-height: 250px; overflow-y: auto; border-radius: 8px;">
              <table class="table table-sm table-bordered align-middle text-dark" style="font-size: 0.78rem;">
                <thead>
                  <tr class="table-light">
                    <th>URL</th>
                    <th>Título</th>
                    <th>Meta Description</th>
                    <th class="text-center">ALT Ausente</th>
                    <th class="text-center">URL Amig.</th>
                    <th class="text-center">Mobile</th>
                  </tr>
                </thead>
                <tbody id="crawlerPagesTableBody">
                  <!-- Linhas das páginas internas injetadas via Javascript -->
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div id="listaProblemas">
          <?php foreach ($problemas as $i => $prob): ?>
          <div class="rajo-row-dinamica mb-2" data-index="<?= $i ?>">
            <div class="row g-2 align-items-center">
              <div class="col-12 col-md-5">
                <input type="text" class="form-control form-control-sm"
                       name="problemas[<?= $i ?>][problema]"
                       value="<?= e($prob['problema']) ?>"
                       placeholder="Descreva o problema…">
              </div>
              <div class="col-12 col-md-4">
                <input type="text" class="form-control form-control-sm"
                       name="problemas[<?= $i ?>][impacto]"
                       value="<?= e($prob['impacto']) ?>"
                       placeholder="Ex.: Ads + SEO + UX">
              </div>
              <div class="col-8 col-md-2">
                <select class="form-select form-select-sm" name="problemas[<?= $i ?>][prioridade]">
                  <?php foreach (['Alta','Média','Baixa'] as $p): ?>
                  <option <?= $prob['prioridade'] === $p ? 'selected' : '' ?>><?= $p ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-4 col-md-1 text-end">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removerLinha(this)">
                  <i class="bi bi-trash"></i>
                </button>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="adicionarProblema()">
          <i class="bi bi-plus-circle me-1"></i> Adicionar Problema
        </button>
      </div>

      <div class="d-flex justify-content-between mt-3">
        <button type="button" class="btn btn-outline-secondary px-4" onclick="prevStep()">
          <i class="bi bi-arrow-left me-1"></i> Anterior
        </button>
        <button type="button" class="btn btn-primary px-4" onclick="nextStep()">
          Próximo <i class="bi bi-arrow-right ms-1"></i>
        </button>
      </div>
    </div>

    <!-- ══ STEP 5: Plano de Ação ══════════════════════════════════ -->
    <div class="step-panel d-none" id="step5">
      <div class="rajo-panel shadow-sm">
        <div class="rajo-panel-title"><i class="bi bi-list-check me-2 text-success"></i>Plano de Ação Recomendado</div>
        <p class="text-muted small mb-3">Este plano de ação pode ser <strong>gerado automaticamente</strong> em conjunto com as auditorias do Passo 1. Edite ou adicione novos prazos e responsáveis conforme a necessidade.</p>

        <!-- NOVO: PROTEÇÃO COMERCIAL DO PLANO DE AÇÃO -->
        <div class="mb-4 p-3 border rounded shadow-sm" style="background: rgba(245, 158, 11, 0.03); border-color: rgba(245, 158, 11, 0.15) !important;">
          <label class="form-label fw-bold d-block mb-1 text-dark" style="font-size: 0.88rem;">
            <i class="bi bi-shield-lock-fill text-warning me-1"></i> Proteção Comercial do Plano de Ação (Bloqueio Estratégico)
          </label>
          <p class="text-muted small mb-3" style="font-size: 0.76rem; line-height: 1.5;">Configure se o plano de ação detalhado (o "como fazer") deve ser exibido na íntegra para o cliente final. Útil para evitar que o cliente use seu diagnóstico como guia com outro fornecedor.</p>
          <div class="d-flex flex-wrap gap-3">
            <div class="form-check">
              <input class="form-check-input" type="radio" name="bloquear_plano" id="bloquear_plano_0" value="0" <?= ($d['bloquear_plano'] ?? 0) == 0 ? 'checked' : '' ?>>
              <label class="form-check-label fw-semibold text-success small" for="bloquear_plano_0" style="cursor: pointer;">
                <i class="bi bi-eye"></i> Exibir Completo (Plano Liberado)
              </label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="bloquear_plano" id="bloquear_plano_1" value="1" <?= ($d['bloquear_plano'] ?? 0) == 1 ? 'checked' : '' ?>>
              <label class="form-check-label fw-semibold text-danger small" for="bloquear_plano_1" style="cursor: pointer;">
                <i class="bi bi-eye-slash-fill"></i> Bloquear Plano (Embaçado Online / Omitido no PDF)
              </label>
            </div>
          </div>
        </div>

        <div id="listaAcoes">
          <?php foreach ($acoes as $i => $acao): ?>
          <div class="rajo-row-dinamica mb-2" data-index="<?= $i ?>">
            <div class="row g-2 align-items-center">
              <div class="col-12 col-md-5">
                <input type="text" class="form-control form-control-sm"
                       name="acoes[<?= $i ?>][acao]"
                       value="<?= e($acao['acao']) ?>"
                       placeholder="Descreva a ação…">
              </div>
              <div class="col-12 col-md-3">
                <input type="text" class="form-control form-control-sm"
                       name="acoes[<?= $i ?>][responsavel]"
                       value="<?= e($acao['responsavel']) ?>"
                       placeholder="Ex.: Desenvolvedor">
              </div>
              <div class="col-8 col-md-3">
                <input type="text" class="form-control form-control-sm"
                       name="acoes[<?= $i ?>][prazo]"
                       value="<?= e($acao['prazo']) ?>"
                       placeholder="Ex.: 1–3 dias">
              </div>
              <div class="col-4 col-md-1 text-end">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removerLinha(this)">
                  <i class="bi bi-trash"></i>
                </button>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="adicionarAcao()">
          <i class="bi bi-plus-circle me-1"></i> Adicionar Ação
        </button>
      </div>

      <div class="d-flex justify-content-between mt-3">
        <button type="button" class="btn btn-outline-secondary px-4" onclick="prevStep()">
          <i class="bi bi-arrow-left me-1"></i> Anterior
        </button>
        <button type="button" class="btn btn-primary px-4" onclick="nextStep()">
          Próximo <i class="bi bi-arrow-right ms-1"></i>
        </button>
      </div>
    </div>

    <!-- ══ STEP 6: Conclusão ══════════════════════════════════════ -->
    <div class="step-panel d-none" id="step6">
      <div class="rajo-panel shadow-sm">
        <div class="rajo-panel-title d-flex justify-content-between align-items-center flex-wrap gap-2">
          <span><i class="bi bi-chat-left-text me-2 text-primary"></i>Conclusão do Relatório</span>
          <button type="button" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1" onclick="gerarConclusaoInteligente()" title="Gerar conclusão técnico-comercial automatizada com base nos problemas encontrados">
            <i class="bi bi-magic text-primary"></i> Gerar Conclusão Inteligente
          </button>
        </div>
        <div class="row g-3 mb-3">
          <div class="col-12">
            <label class="form-label fw-semibold">Resultado Geral (Diagnóstico Final)</label>
            <select class="form-select" name="resultado_geral">
              <?php foreach (['CRÍTICO','RUIM','MÉDIO','BOM'] as $opt): ?>
              <option value="<?= $opt ?>" <?= ($d['resultado_geral'] ?? 'CRÍTICO') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
              <?php endforeach; ?>
            </select>
            <small class="text-muted" style="font-size: 0.72rem;">Esta classificação será auto-calculada após a auditoria do PageSpeed, mas você pode personalizá-la.</small>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Texto de Conclusão</label>
          <textarea class="form-control" name="conclusao" rows="6"
                    placeholder="Descreva a conclusão geral do diagnóstico, os impactos no negócio e as recomendações finais…"><?= e($d['conclusao'] ?? 'O site ' . ($d['dominio'] ?? '[URL DO SITE]') . ' apresenta problemas técnicos críticos que comprometem tanto o desempenho orgânico quanto a viabilidade de campanhas de mídia paga. Os dados coletados com as ferramentas do próprio Google demonstram de forma objetiva que o site não oferece a experiência mínima exigida para a aprovação e bom desempenho de anúncios.

As correções listadas neste relatório são necessárias para que qualquer investimento em Google Ads gere retorno real. Sem elas, o budget de mídia será consumido com baixíssima eficiência.

A Rajo está preparada para executar todas as correções indicadas com agilidade e transparência, entregando um site tecnicamente saudável e pronto para campanhas de alta performance.') ?></textarea>
        </div>
        <div>
          <label class="form-label fw-semibold">Observações Adicionais <span class="text-muted fw-normal">(opcional)</span></label>
          <textarea class="form-control" name="obs_pagespeed" rows="3"
                    placeholder="Observações técnicas extras, contexto adicional, notas internas…"><?= e($d['obs_pagespeed'] ?? '') ?></textarea>
        </div>
      </div>

      <div class="d-flex justify-content-between mt-3">
        <button type="button" class="btn btn-outline-secondary px-4" onclick="prevStep()">
          <i class="bi bi-arrow-left me-1"></i> Anterior
        </button>
        <button type="button" class="btn btn-primary px-4" onclick="nextStep()">
          Próximo <i class="bi bi-arrow-right ms-1"></i>
        </button>
      </div>
    </div>

    <!-- ══ STEP 7: Revisão & Salvar ═══════════════════════════════ -->
    <div class="step-panel d-none" id="step7">
      <div class="rajo-panel">
        <div class="rajo-panel-title"><i class="bi bi-check2-circle me-2"></i>Revisão Final</div>
        <p class="text-muted small mb-3">Verifique os dados antes de salvar. Você pode voltar a qualquer etapa para corrigir.</p>
        <div id="resumoRevisao" class="rajo-resumo"></div>
      </div>

      <div id="alertaSalvar" class="d-none"></div>

      <div class="d-flex justify-content-between mt-3 gap-2 flex-wrap">
        <button type="button" class="btn btn-outline-secondary px-4" onclick="prevStep()">
          <i class="bi bi-arrow-left me-1"></i> Anterior
        </button>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-success px-4" id="btnSalvar" onclick="salvarRelatorio()">
            <i class="bi bi-floppy me-1"></i> Salvar Relatório
          </button>
          <a href="visualizar.php?id=<?= $id ?>" id="btnVerOnline" class="btn btn-outline-dark px-4 <?= $id ? '' : 'd-none' ?>" target="_blank">
            <i class="bi bi-eye me-1"></i> Ver Online
          </a>
          <a href="pdf.php?id=<?= $id ?>" id="btnGerarPdf" class="btn btn-danger px-4 <?= $id ? '' : 'd-none' ?>" target="_blank">
            <i class="bi bi-file-earmark-pdf me-1"></i> Gerar PDF
          </a>
        </div>
      </div>
    </div>

  </form>
</div>

<!-- Overlay de Carregamento da API do PageSpeed -->
<div class="api-loading-overlay" id="apiLoadingOverlay">
  <div class="api-loading-box shadow">
    <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status"></div>
    <h5 class="fw-bold mb-1" style="font-family: var(--font-title); color: var(--dark-bg);">Auditoria Inteligente Google</h5>
    <p class="small text-muted mb-4" id="apiLoadingText">Iniciando análise técnica...</p>
    
    <div class="text-start border-top pt-3 mx-auto" style="max-width: 340px;">
      <div class="api-progress-step text-muted mb-2" id="stepMobile" data-text="Auditar velocidade em dispositivos Móveis">
        <i class="bi bi-circle me-2"></i> Auditar velocidade em dispositivos Móveis
      </div>
      <div class="api-progress-step text-muted mb-2" id="stepDesktop" data-text="Auditar velocidade em Computadores">
        <i class="bi bi-circle me-2"></i> Auditar velocidade em Computadores
      </div>
      <div class="api-progress-step text-muted" id="stepProcessing" data-text="Processar dados e preencher Core Web Vitals">
        <i class="bi bi-circle me-2"></i> Processar dados e preencher Core Web Vitals
      </div>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script>
const PAGESPEED_API_KEY = "<?= defined('PAGESPEED_API_KEY') ? PAGESPEED_API_KEY : '' ?>";
</script>
<script src="assets/app.js?v=<?= time() ?>"></script>
<script>
// Inicia no step correto
const EDIT_ID = <?= $id ?>;
initWizard(1);
</script>
</body>
</html>
