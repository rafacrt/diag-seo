<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

// Acesso: analista logado (dono ou master) via ?id=, ou cliente final via ?t=TOKEN
[$r, $acesso_publico] = carregar_relatorio_autorizado();

$problemas = json_decode($r['problemas'] ?? '[]', true) ?: [];
$acoes     = json_decode($r['acoes']     ?? '[]', true) ?: [];

$tipo_relatorio = $_GET['formato'] ?? $r['tipo_relatorio'] ?? 'completo';
if (!in_array($tipo_relatorio, ['completo', 'compacto'])) {
    $tipo_relatorio = 'completo';
}

// O override via GET (?plano=) só vale para o analista logado.
$plano_get = $acesso_publico ? null : ($_GET['plano'] ?? null);
if ($plano_get === 'oculto') {
    $bloquear_plano = 1;
} elseif ($plano_get === 'liberado') {
    $bloquear_plano = 0;
} else {
    $bloquear_plano = (int)($r['bloquear_plano'] ?? 0);
}

// Cor primária do tema do relatório
$primaryColor = !empty($r['pdf_cor_tema']) ? $r['pdf_cor_tema'] : '#3b82f6';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Diagnóstico Técnico — <?= htmlspecialchars($r['cliente']) ?></title>
  
  <!-- Fontes Google Premium -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Outfit:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  
  <!-- CSS Bootstrap & Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
  
  <style>
    :root {
      --primary-color: <?= $primaryColor ?>;
      --primary-color-rgb: <?= hexToRgb($primaryColor) ?>;
      --bg-dark: #0b0f19;
      --panel-dark: rgba(22, 28, 45, 0.7);
      --border-color: rgba(255, 255, 255, 0.08);
      --font-title: 'Outfit', sans-serif;
      --font-body: 'Plus Jakarta Sans', sans-serif;
    }

    body {
      background-color: var(--bg-dark);
      background-image: radial-gradient(circle at 10% 20%, rgba(59, 130, 246, 0.05) 0%, transparent 40%),
                        radial-gradient(circle at 90% 80%, rgba(99, 102, 241, 0.05) 0%, transparent 40%);
      color: #f1f5f9;
      font-family: var(--font-body);
      min-height: 100vh;
      -webkit-font-smoothing: antialiased;
    }

    h1, h2, h3, h4, h5, h6 {
      font-family: var(--font-title);
      font-weight: 700;
    }

    /* Cabeçalho Flutuante Premium */
    .header-floating {
      backdrop-filter: blur(12px);
      background: rgba(11, 15, 25, 0.8);
      border-bottom: 1px solid var(--border-color);
      position: sticky;
      top: 0;
      z-index: 100;
      padding: 15px 0;
    }

    .glass-panel {
      background: var(--panel-dark);
      backdrop-filter: blur(16px);
      border: 1px solid var(--border-color);
      border-radius: 20px;
      padding: 30px;
      margin-bottom: 30px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    }

    .brand-logo-circle {
      width: 42px;
      height: 42px;
      border-radius: 12px;
      background: linear-gradient(135deg, var(--primary-color), #6366f1);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 900;
      font-size: 1.5rem;
      font-family: var(--font-title);
    }

    /* Tabs de Dispositivo */
    .device-tabs {
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid var(--border-color);
      border-radius: 12px;
      padding: 6px;
      display: inline-flex;
    }

    .device-tab-btn {
      border: none;
      background: transparent;
      color: #94a3b8;
      padding: 10px 20px;
      border-radius: 8px;
      font-size: 0.9rem;
      font-weight: 600;
      transition: all 0.25s ease;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .device-tab-btn.active {
      background: var(--primary-color);
      color: #fff;
      box-shadow: 0 4px 12px rgba(var(--primary-color-rgb), 0.3);
    }

    /* Progresso Circular */
    .dial-svg-wrapper svg {
      transform: rotate(-90deg);
    }
    
    .dial-svg-wrapper circle {
      transition: stroke-dashoffset 1.2s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Cards de Web Vitals */
    .cwv-card {
      background: rgba(255, 255, 255, 0.02);
      border: 1px solid var(--border-color);
      border-radius: 16px;
      padding: 20px;
      transition: all 0.3s ease;
      height: 100%;
    }

    .cwv-card:hover {
      transform: translateY(-4px);
      border-color: rgba(var(--primary-color-rgb), 0.3);
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
    }

    .metric-badge {
      font-size: 0.72rem;
      font-weight: 800;
      padding: 5px 12px;
      border-radius: 20px;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .badge-bom {
      background: rgba(16, 185, 129, 0.1);
      color: #10b981;
      border: 1px solid rgba(16, 185, 129, 0.2);
    }

    .badge-medio {
      background: rgba(245, 158, 11, 0.1);
      color: #f59e0b;
      border: 1px solid rgba(245, 158, 11, 0.2);
    }

    .badge-ruim {
      background: rgba(239, 68, 68, 0.1);
      color: #ef4444;
      border: 1px solid rgba(239, 68, 68, 0.2);
    }

    /* Accordions customizados */
    .accordion-item-custom {
      background: rgba(255, 255, 255, 0.01) !important;
      border: 1px solid var(--border-color) !important;
      border-radius: 12px !important;
      margin-bottom: 12px;
      overflow: hidden;
    }

    .accordion-button-custom {
      background: transparent !important;
      color: #f1f5f9 !important;
      font-family: var(--font-title);
      font-weight: 600;
      font-size: 1.05rem;
      padding: 20px !important;
      box-shadow: none !important;
    }

    .accordion-button-custom:not(.collapsed) {
      border-bottom: 1px solid var(--border-color) !important;
    }

    .accordion-button-custom::after {
      filter: invert(1);
    }

    .accordion-body-custom {
      padding: 20px !important;
      color: #cbd5e1;
      font-size: 0.95rem;
      line-height: 1.6;
    }

    /* Tabela de Plano de Ação */
    .table-dark-custom {
      --bs-table-bg: transparent;
      color: #f1f5f9;
      border-color: var(--border-color);
      font-size: 0.92rem;
    }

    .table-dark-custom th {
      font-family: var(--font-title);
      font-weight: 600;
      color: #94a3b8;
      border-bottom-width: 2px;
    }

    .table-dark-custom td {
      padding: 14px 10px;
    }

    /* Toast */
    .toast-container-custom {
      position: fixed;
      bottom: 20px;
      right: 20px;
      z-index: 1050;
    }

    /* Animação dos Dials */
    .progress-circle {
      stroke-dasharray: 251.3;
      stroke-dashoffset: 251.3;
    }
  </style>
</head>
<body>

  <!-- ─── HEADER FLUTUANTE ─── -->
  <header class="header-floating">
    <div class="container d-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center gap-3">
        <?php if (!empty($r['logo_cliente'])): ?>
          <img src="<?= htmlspecialchars($r['logo_cliente']) ?>" alt="Logo Cliente" style="max-height: 38px; border-radius: 6px;">
        <?php else: ?>
          <div class="brand-logo-circle">R</div>
          <span class="fw-bold fs-5 d-none d-sm-inline" style="font-family: var(--font-title);">Rajo Diagnóstico</span>
        <?php endif; ?>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 fw-semibold d-none d-md-inline-block" style="border-radius: 30px; font-size: 0.75rem;">
          FORMATO: <strong><?= strtoupper($tipo_relatorio) ?></strong>
        </span>
        <span class="badge bg-secondary-subtle text-white border border-secondary-subtle px-3 py-2 fw-semibold d-none d-md-inline-block" style="border-radius: 30px; font-size: 0.75rem;">
          AVALIAÇÃO GERAL: <strong class="text-<?= strtolower($r['resultado_geral']) === 'bom' ? 'success' : (strtolower($r['resultado_geral']) === 'médio' ? 'warning' : 'danger') ?>"><?= $r['resultado_geral'] ?></strong>
        </span>
        <?php
        // Mantém o mesmo modo de acesso no link do PDF: token público para o
        // cliente final, ou id + plano para o analista logado.
        if ($acesso_publico) {
            $pdf_href = 'pdf.php?t=' . urlencode($r['token_publico']) . '&formato=' . $tipo_relatorio;
        } else {
            $pdf_href = 'pdf.php?id=' . (int)$r['id'] . '&formato=' . $tipo_relatorio . '&plano=' . ($bloquear_plano === 1 ? 'oculto' : 'liberado');
        }
        ?>
        <a href="<?= htmlspecialchars($pdf_href) ?>" target="_blank" class="btn btn-primary d-inline-flex align-items-center gap-2 fw-bold" style="border-radius: 30px; padding: 8px 20px; font-size: 0.85rem;">
          <i class="bi bi-file-earmark-pdf-fill"></i> Baixar PDF
        </a>
      </div>
    </div>
  </header>

  <div class="container py-5">
    
    <!-- ─── BANNER PRINCIPAL ─── -->
    <div class="glass-panel text-center py-5" style="position: relative; overflow: hidden;">
      <div style="position: relative; z-index: 2;">
        <span class="text-uppercase tracking-wider fw-bold text-white-50 small mb-2 d-block" style="letter-spacing: 0.15em;">Relatório Interativo</span>
        <h1 class="display-5 fw-extrabold mb-3" style="color: #fff; font-family: var(--font-title); font-size: calc(1.8rem + 1.5vw);"><?= htmlspecialchars($r['cliente']) ?></h1>
        <div class="d-flex align-items-center justify-content-center gap-2 mb-4">
          <i class="bi bi-globe text-primary fs-5"></i>
          <a href="https://<?= htmlspecialchars($r['dominio']) ?>" target="_blank" class="text-white hover-link text-decoration-none fs-5 fw-medium opacity-75"><?= htmlspecialchars($r['dominio']) ?></a>
        </div>
        
        <div class="device-tabs">
          <button type="button" class="device-tab-btn active" id="btnMobile" onclick="switchDevice('mobile')">
            <i class="bi bi-phone"></i> Celular (Mobile)
          </button>
          <button type="button" class="device-tab-btn" id="btnDesktop" onclick="switchDevice('desktop')">
            <i class="bi bi-laptop"></i> Computador (Desktop)
          </button>
        </div>
      </div>
    </div>

    <!-- ─── SEÇÃO DE ALERTA LIGHTHOUSE TIMEOUT ─── -->
    <?php if (str_contains(strtolower($r['gtm_nota'] ?? ''), 'erro') || str_contains(strtolower($r['gtm_nota'] ?? ''), 'timeout')): ?>
      <div class="glass-panel border-danger bg-danger-subtle bg-opacity-10 d-flex gap-3 align-items-start p-4">
        <i class="bi bi-exclamation-octagon-fill text-danger fs-1"></i>
        <div>
          <h5 class="text-danger fw-bold mb-2">Bloqueio Crítico de Desempenho (Timeout no Lighthouse/GTmetrix)</h5>
          <p class="text-white-50 mb-0 small" style="line-height: 1.6;">
            O site demorou excessivamente para atingir o tempo ocioso da CPU. O navegador travou devido à carga de processamento dos scripts e recursos externos da página. Isso causa um prejuízo direto à experiência do usuário e resulta no aumento drástico do custo por clique (CPC) em campanhas de tráfego pago devido ao baixo índice de qualidade do Google.
          </p>
        </div>
      </div>
    <?php endif; ?>

    <!-- ─── DIALS DE PERFORMANCE ─── -->
    <div class="row g-4 mb-5 justify-content-center">
      <?php
      $dials = [
        ['perf', 'Performance', $r['ps_performance_mobile'], $r['ps_performance_desktop']],
        ['seo', 'SEO Técnico', $r['ps_seo_mobile'], $r['ps_seo_desktop']],
        ['acess', 'Acessibilidade', $r['ps_acessibilidade_mobile'], $r['ps_acessibilidade_desktop']],
        ['boas', 'Boas Práticas', $r['ps_boaspraticas_mobile'], $r['ps_boaspraticas_desktop']]
      ];
      
      $exibidos = 0;
      foreach ($dials as [$idKey, $label, $mobScore, $deskScore]) {
          $isMobBad = ($mobScore !== '' && $mobScore !== null && is_numeric($mobScore) && (int)$mobScore < 85);
          $isDeskBad = ($deskScore !== '' && $deskScore !== null && is_numeric($deskScore) && (int)$deskScore < 85);
          if ($isMobBad || $isDeskBad) {
              $exibidos++;
          }
      }
      
      if ($exibidos > 0):
        foreach ($dials as [$idKey, $label, $mobScore, $deskScore]):
          $isMobBad = ($mobScore !== '' && $mobScore !== null && is_numeric($mobScore) && (int)$mobScore < 85);
          $isDeskBad = ($deskScore !== '' && $deskScore !== null && is_numeric($deskScore) && (int)$deskScore < 85);
          
          if (!$isMobBad && !$isDeskBad) {
              continue;
          }
          
          $mobScoreText = $mobScore !== '' && $mobScore !== null ? $mobScore : '–';
          $deskScoreText = $deskScore !== '' && $deskScore !== null ? $deskScore : '–';
        ?>
          <div class="col-6 col-md-3">
            <div class="glass-panel text-center py-4 px-3 h-100 d-flex flex-column justify-content-between align-items-center">
              <div class="dial-svg-wrapper position-relative" style="width: 100px; height: 100px; margin: 0 auto 12px;">
                <svg width="100" height="100" viewBox="0 0 100 100">
                  <circle cx="50" cy="50" r="40" fill="transparent" stroke="rgba(255,255,255,0.03)" stroke-width="8"></circle>
                  <circle id="circle-<?= $idKey ?>" cx="50" cy="50" r="40" fill="transparent" stroke-width="8" stroke-linecap="round" class="progress-circle"></circle>
                </svg>
                <div class="position-absolute top-50 start-50 translate-middle font-title fw-extrabold text-white fs-3" id="value-<?= $idKey ?>">
                  –
                </div>
              </div>
              <span class="text-white-50 small fw-semibold" style="letter-spacing: 0.05em;"><?= $label ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="col-12 text-center">
          <div class="glass-panel py-5">
            <i class="bi bi-shield-check text-success display-1 mb-3"></i>
            <h4 class="text-white fw-bold mb-2">Pontuações de Auditoria Excelentes</h4>
            <p class="text-white-50 mb-0 max-width-600 mx-auto">O site apresenta excelente índice de conformidade em todas as categorias de auditoria de performance do Google PageSpeed Insights (todas as pontuações estão acima de 85/100).</p>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- ─── DIAGNÓSTICO DE INFRAESTRUTURA & SEGURANÇA ─── -->
    <div class="mb-5">
      <h3 class="mb-4 d-flex align-items-center gap-2">
        <i class="bi bi-shield-check text-primary"></i> Diagnóstico de Infraestrutura & Segurança
      </h3>
      <div class="row g-4">
        <!-- Plataforma CMS -->
        <div class="col-12 col-md-6 col-lg-3">
          <div class="glass-panel py-4 px-3 text-center h-100 d-flex flex-column justify-content-between align-items-center" style="margin-bottom:0;">
            <div>
              <div class="d-flex align-items-center justify-content-center gap-2 mb-2 text-primary">
                <i class="bi bi-cpu-fill fs-4"></i>
                <h6 class="fw-bold mb-0">Plataforma / CMS</h6>
              </div>
              <p class="text-white-50 small mb-4" style="line-height: 1.4; font-size: 0.78rem;">CMS e tecnologias de desenvolvimento web ativas no endereço principal.</p>
            </div>
            <span class="badge py-2 px-4 rounded-3 border fw-bold" style="font-size: 0.85rem; <?= (!empty($r['auditoria_cms']) && $r['auditoria_cms'] !== 'Não Identificado') ? 'background-color: rgba(16, 185, 129, 0.1); border-color: rgba(16, 185, 129, 0.2); color: #10b981;' : 'background-color: rgba(245, 158, 11, 0.1); border-color: rgba(245, 158, 11, 0.2); color: #f59e0b;' ?>">
              <?= !empty($r['auditoria_cms']) ? htmlspecialchars($r['auditoria_cms']) : 'Não Identificado' ?>
            </span>
          </div>
        </div>

        <!-- Hospedagem & IP -->
        <?php
        $geo = !empty($r['auditoria_hospedagem']) ? json_decode($r['auditoria_hospedagem'], true) : null;
        ?>
        <div class="col-12 col-md-6 col-lg-3">
          <div class="glass-panel py-4 px-3 h-100 d-flex flex-column justify-content-between" style="margin-bottom:0;">
            <div>
              <div class="d-flex align-items-center gap-2 mb-2 text-primary">
                <i class="bi bi-server fs-4"></i>
                <h6 class="fw-bold mb-0">Hospedagem & IP</h6>
              </div>
              <p class="text-white-50 small mb-3" style="line-height: 1.4; font-size: 0.78rem;">Provedor físico e localização do servidor onde a página está hospedada.</p>
            </div>
            <div class="text-start small" style="line-height: 1.5; color: #cbd5e1; font-size: 0.76rem;">
              <?php if ($geo): ?>
                Provedor: <strong class="text-white"><?= htmlspecialchars($geo['provedor']) ?></strong><br>
                Local: <strong class="text-white"><?= htmlspecialchars($geo['cidade']) ?>, <?= htmlspecialchars($geo['pais']) ?></strong><br>
                Endereço IP: <span class="font-monospace opacity-75" style="font-size:0.75rem;"><?= htmlspecialchars($geo['ip']) ?></span>
              <?php else: ?>
                <span class="text-white-50 italic">Auditoria pendente ou indisponível</span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Segurança HTTP & SSL -->
        <?php
        $seg = !empty($r['auditoria_seguranca']) ? json_decode($r['auditoria_seguranca'], true) : null;
        $segAtivos = 0;
        if ($seg) {
            foreach (['hsts', 'csp', 'x_frame', 'x_content', 'referrer'] as $c) {
                if (!empty($seg[$c])) $segAtivos++;
            }
        }
        ?>
        <div class="col-12 col-md-6 col-lg-3">
          <div class="glass-panel py-4 px-3 h-100 d-flex flex-column justify-content-between" style="margin-bottom:0;">
            <div>
              <div class="d-flex align-items-center gap-2 mb-2 text-primary">
                <i class="bi bi-lock-fill fs-4"></i>
                <h6 class="fw-bold mb-0">Segurança & SSL</h6>
              </div>
              <p class="text-white-50 small mb-3" style="line-height: 1.4; font-size: 0.78rem;">Nível de proteção HTTP e status de criptografia de dados (SSL).</p>
            </div>
            <div class="text-start small" style="line-height: 1.5; color: #cbd5e1; font-size: 0.76rem;">
              <?php if ($seg): ?>
                SSL: <strong class="<?= $seg['ssl_ativo'] ? 'text-success' : 'text-danger' ?>"><?= $seg['ssl_ativo'] ? '✓ Ativo' : '✗ Sem SSL' ?></strong><br>
                Headers Proteção: <strong class="text-white"><?= $segAtivos ?>/5 ativos</strong>
              <?php else: ?>
                <span class="text-white-50 italic">Auditoria de segurança pendente</span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- DNS de E-mail -->
        <?php
        $dns = !empty($r['auditoria_dns']) ? json_decode($r['auditoria_dns'], true) : null;
        ?>
        <div class="col-12 col-md-6 col-lg-3">
          <div class="glass-panel py-4 px-3 h-100 d-flex flex-column justify-content-between" style="margin-bottom:0;">
            <div>
              <div class="d-flex align-items-center gap-2 mb-2 text-primary">
                <i class="bi bi-envelope-check-fill fs-4"></i>
                <h6 class="fw-bold mb-0">DNS de E-mail (SPAM)</h6>
              </div>
              <p class="text-white-50 small mb-3" style="line-height: 1.4; font-size: 0.78rem;">Status de chaves que protegem e-mails do domínio contra fraudes e SPAM.</p>
            </div>
            <div class="text-start small" style="line-height: 1.5; color: #cbd5e1; font-size: 0.76rem;">
              <?php if ($dns): ?>
                Chave SPF: <strong class="<?= $dns['spf_valido'] ? 'text-success' : 'text-danger' ?>"><?= $dns['spf_valido'] ? '✓ Configurada' : '✗ Ausente' ?></strong><br>
                Chave DMARC: <strong class="<?= $dns['dmarc_valido'] ? 'text-success' : 'text-danger' ?>"><?= $dns['dmarc_valido'] ? '✓ Configurada' : '✗ Ausente' ?></strong>
              <?php else: ?>
                <span class="text-white-50 italic">Auditoria de DNS pendente</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ─── SIMULAÇÃO DE MÍDIA PAGA (GOOGLE ADS) ─── -->
    <?php
    $inv = (float)($r['ads_investimento'] ?? 0);
    $cpc = (float)($r['ads_cpc'] ?? 0);
    if ($inv > 0 && $cpc > 0):
        $P = (int)($r['ps_performance_mobile'] ?? 35);
        if ($P >= 90) {
            $D = 3;
        } else if ($P >= 50) {
            $D = 30 - 0.3 * $P;
        } else {
            $D = 65 - 0.7 * $P;
        }
        $D = max(0, min(100, $D));
        $prejuizo = $inv * ($D / 100);
        $cliquesPerdidos = $cpc > 0 ? round($prejuizo / $cpc) : 0;
        $aproveitado = $inv - $prejuizo;
        $nicho_nome = match($r['ads_nicho'] ?? '') {
            'advocacia' => 'Advocacia / Jurídico',
            'saude' => 'Saúde / Clínicas / Dentistas',
            'estetica' => 'Estética / Beleza',
            'ecommerce' => 'E-commerce / Varejo',
            'educacao' => 'Educação / Cursos',
            'tecnologia' => 'SaaS / Tecnologia / B2B',
            'imobiliario' => 'Imobiliário / Corretores',
            'financas' => 'Finanças / Investimentos',
            'contabilidade' => 'Contabilidade / Assessoria',
            'turismo' => 'Turismo / Hotelaria',
            'automotivo' => 'Automotivo / Concessionárias',
            'gastronomia' => 'Gastronomia / Restaurantes',
            'servicos_locais' => 'Serviços Locais (Urgência)',
            default => 'Serviços Gerais / Outro'
        };
    ?>
    <div class="mb-5">
      <h3 class="mb-4 d-flex align-items-center gap-2">
        <i class="bi bi-google text-primary"></i> Impacto de Performance em Mídia Paga (Google Ads)
      </h3>
      <div class="glass-panel py-4 px-4">
        <div class="row g-4 align-items-center">
          <div class="col-12 col-lg-7">
            <div class="d-flex align-items-start gap-3">
              <span class="badge rounded-circle p-2 bg-danger-subtle text-danger fs-4 d-inline-flex" style="margin-top:2px;"><i class="bi bi-exclamation-octagon-fill"></i></span>
              <div>
                <h4 class="text-white fw-bold mb-2">Simulador de Desperdício Mensal</h4>
                <p class="text-white-50 small mb-3" style="line-height: 1.5; font-size: 0.8rem;">
                  Com base no desempenho mobile de <strong><?= $P ?>/100</strong>, o site apresenta lentidão no carregamento móvel em conexões móveis 3G/4G. Estima-se que <strong><?= round($D) ?>%</strong> dos visitantes vindos de anúncios móveis clicam, gastam orçamento e desistem da navegação antes do site abrir.
                </p>
                <div class="small text-white-50" style="font-size:0.75rem;">
                  Nicho de Atuação: <strong class="text-white"><?= htmlspecialchars($nicho_nome) ?></strong> &bull; CPC Estimado: <strong class="text-white">R$ <?= number_format($cpc, 2, ',', '.') ?></strong>
                </div>
              </div>
            </div>
          </div>
          <div class="col-12 col-lg-5">
            <div class="p-3 rounded-4 border border-danger-subtle text-center text-lg-start" style="background-color: rgba(220, 38, 38, 0.08); border-color: rgba(220, 53, 69, 0.25) !important;">
              <div class="row g-3">
                <div class="col-6 text-center">
                  <span class="text-white-50 small d-block mb-1" style="font-size:0.75rem;">Perda Mensal Est.</span>
                  <strong class="text-danger fs-4 fw-bold">R$ <?= number_format($prejuizo, 2, ',', '.') ?></strong>
                </div>
                <div class="col-6 text-center border-start border-danger-subtle" style="border-color: rgba(220, 53, 69, 0.2) !important;">
                  <span class="text-white-50 small d-block mb-1" style="font-size:0.75rem;">Cliques Desperdiçados</span>
                  <strong class="text-danger fs-4 fw-bold"><?= number_format($cliquesPerdidos) ?> /mês</strong>
                </div>
                <div class="col-12 text-center border-top border-danger-subtle pt-2 mt-2" style="border-color: rgba(220, 53, 69, 0.2) !important;">
                  <span class="text-white-50 small d-inline-block me-1" style="font-size:0.75rem;">Verba Aproveitada Efetivamente:</span>
                  <strong class="text-success fs-5">R$ <?= number_format($aproveitado, 2, ',', '.') ?></strong>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    </div>
    <?php endif; ?>

    <!-- ─── CONFORMIDADE E SEGURANÇA DO DOMÍNIO ─── -->
    <?php
    $adExp = trim($r['ad_experience_status'] ?? '');
    $sb = trim($r['safe_browsing_status'] ?? '');
    $ap = trim($r['ads_policy_status'] ?? '');

    $has_security_warning = false;
    $security_alerts = [];

    // Ad Experience
    $adExpClass = 'text-white-50';
    $adExpText = $adExp ?: 'Não verificado';
    $adExpIcon = '<i class="bi bi-dash-circle text-muted"></i>';
    if ($adExp) {
        if (str_contains(strtolower($adExp), 'passing')) {
            $adExpClass = 'text-success';
            $adExpIcon = '<i class="bi bi-check-circle-fill text-success"></i>';
        } else if (str_contains(strtolower($adExp), 'warning')) {
            $adExpClass = 'text-warning fw-bold';
            $adExpIcon = '<i class="bi bi-exclamation-triangle-fill text-warning"></i>';
            $has_security_warning = true;
            $security_alerts[] = [
                'titulo' => 'Ad Experience Report: Status de Atenção (Warning)',
                'desc' => 'O Google identificou desvios de layout ou anúncios abusivos na página de destino. Risco severo de suspensão técnica das campanhas de mídia paga.',
                'nivel' => 'warning'
            ];
        } else if (str_contains(strtolower($adExp), 'failing')) {
            $adExpClass = 'text-danger fw-bold';
            $adExpIcon = '<i class="bi bi-x-circle-fill text-danger"></i>';
            $has_security_warning = true;
            $security_alerts[] = [
                'titulo' => 'Ad Experience Report: Reprovado (Failing)',
                'desc' => 'REPROVADO PELO GOOGLE. O domínio foi classificado como em desconformidade grave. Anúncios vinculados a esta URL são bloqueados ou reprovados preventivamente.',
                'nivel' => 'danger'
            ];
        } else {
            $adExpClass = 'text-white';
            $adExpIcon = '<i class="bi bi-info-circle text-info"></i>';
        }
    }

    // Safe Browsing
    $sbClass = 'text-white-50';
    $sbText = $sb ?: 'Não verificado';
    $sbIcon = '<i class="bi bi-dash-circle text-muted"></i>';
    if ($sb) {
        if (str_contains(strtolower($sb), 'nenhuma')) {
            $sbClass = 'text-success';
            $sbIcon = '<i class="bi bi-shield-fill-check text-success"></i>';
        } else if (str_contains(strtolower($sb), 'parcialmente')) {
            $sbClass = 'text-warning fw-bold';
            $sbIcon = '<i class="bi bi-shield-fill-exclamation text-warning"></i>';
            $has_security_warning = true;
            $security_alerts[] = [
                'titulo' => 'Google Safe Browsing: Domínio Parcialmente Perigoso',
                'desc' => 'Foram detectados scripts maliciosos ou tentativas de engenharia social parciais. Pode gerar restrição de acessos e bloqueio silencioso do tráfego.',
                'nivel' => 'warning'
            ];
        } else if (str_contains(strtolower($sb), 'lista negra') || str_contains(strtolower($sb), 'perigoso')) {
            $sbClass = 'text-danger fw-bold';
            $sbIcon = '<i class="bi bi-shield-fill-x text-danger"></i>';
            $has_security_warning = true;
            $security_alerts[] = [
                'titulo' => 'Google Safe Browsing: Domínio em LISTA NEGRA (Perigoso)',
                'desc' => 'BLOQUEIO DE SEGURANÇA ATIVO. O site foi incluído na lista negra de malwares/phishing. Navegadores exibem a temida tela vermelha de perigo e contas de anúncios vinculadas são suspensas de forma imediata e permanente.',
                'nivel' => 'danger'
            ];
        }
    }

    // Ads Policy
    $apClass = 'text-white-50';
    $apText = $ap ?: 'Não verificado';
    $apIcon = '<i class="bi bi-dash-circle text-muted"></i>';
    if ($ap) {
        if (str_contains(strtolower($ap), 'sem restr')) {
            $apClass = 'text-success';
            $apIcon = '<i class="bi bi-check-circle-fill text-success"></i>';
        } else if (str_contains(strtolower($ap), 'restrição')) {
            $apClass = 'text-warning fw-bold';
            $apIcon = '<i class="bi bi-exclamation-circle-fill text-warning"></i>';
            $has_security_warning = true;
            $security_alerts[] = [
                'titulo' => 'Centro de Políticas Google Ads: Anúncios com Restrição',
                'desc' => 'Seus anúncios sofrem limitações de alcance por violações de políticas de destino. O tráfego pago fica mais escasso e o CPC encarece substancialmente.',
                'nivel' => 'warning'
            ];
        } else if (str_contains(strtolower($ap), 'reprovado')) {
            $apClass = 'text-danger fw-bold';
            $apIcon = '<i class="bi bi-x-circle-fill text-danger"></i>';
            $has_security_warning = true;
            $security_alerts[] = [
                'titulo' => 'Centro de Políticas Google Ads: Anúncios Reprovados',
                'desc' => 'O Google Ads reprovou e pausou anúncios devido a problemas diretos na página de destino. O fluxo comercial das campanhas foi suspenso.',
                'nivel' => 'danger'
            ];
        } else if (str_contains(strtolower($ap), 'suspensa')) {
            $apClass = 'text-danger fw-bold';
            $apIcon = '<i class="bi bi-exclamation-octagon-fill text-danger"></i>';
            $has_security_warning = true;
            $security_alerts[] = [
                'titulo' => 'Centro de Políticas Google Ads: CONTA SUSPENSA',
                'desc' => 'BLOQUEIO TOTAL E GRAVE. A conta de anúncios do cliente está suspensa por violação sistemática. Todo o investimento está paralisado e as conversões congeladas.',
                'nivel' => 'danger'
            ];
        }
    }
    ?>
    <div class="mb-5">
      <h3 class="mb-4 d-flex align-items-center gap-2">
        <i class="bi bi-shield-lock-fill text-primary"></i> Segurança do Domínio & Conformidade de Anúncios
      </h3>
      <div class="row g-4 mb-4">
        
        <!-- Ad Experience -->
        <div class="col-12 col-md-4">
          <div class="cwv-card h-100">
            <div class="d-flex justify-content-between align-items-start mb-3">
              <div>
                <h5 class="mb-0 text-white" style="font-size: 0.95rem;">Ad Experience Report</h5>
                <small class="text-white-50" style="font-size: 0.72rem;">Search Console / Better Ads</small>
              </div>
              <span class="fs-4"><?= $adExpIcon ?></span>
            </div>
            <p class="text-white-50 mb-0" style="font-size: 0.76rem; line-height: 1.4;">Avalia se o site exibe formatos de anúncios abusivos ou intrusivos que degradam a experiência do usuário móvel.</p>
            <div class="border-top pt-2 mt-3 text-end">
              <small class="text-white-50 d-block" style="font-size: 0.7rem;">Resultado Técnico</small>
              <strong class="<?= $adExpClass ?>" style="font-size: 0.85rem;"><?= htmlspecialchars($adExpText) ?></strong>
            </div>
          </div>
        </div>

        <!-- Safe Browsing -->
        <div class="col-12 col-md-4">
          <div class="cwv-card h-100">
            <div class="d-flex justify-content-between align-items-start mb-3">
              <div>
                <h5 class="mb-0 text-white" style="font-size: 0.95rem;">Google Safe Browsing</h5>
                <small class="text-white-50" style="font-size: 0.72rem;">Transparency Security Report</small>
              </div>
              <span class="fs-4"><?= $sbIcon ?></span>
            </div>
            <p class="text-white-50 mb-0" style="font-size: 0.76rem; line-height: 1.4;">Varre o site buscando malwares, vírus, scripts invasivos e ameaças ativas de roubo de dados (phishing).</p>
            <div class="border-top pt-2 mt-3 text-end">
              <small class="text-white-50 d-block" style="font-size: 0.7rem;">Status do Domínio</small>
              <strong class="<?= $sbClass ?>" style="font-size: 0.85rem;"><?= htmlspecialchars($sbText) ?></strong>
            </div>
          </div>
        </div>

        <!-- Ads Policy -->
        <div class="col-12 col-md-4">
          <div class="cwv-card h-100">
            <div class="d-flex justify-content-between align-items-start mb-3">
              <div>
                <h5 class="mb-0 text-white" style="font-size: 0.95rem;">Centro de Políticas</h5>
                <small class="text-white-50" style="font-size: 0.72rem;">Google Ads Policy Center</small>
              </div>
              <span class="fs-4"><?= $apIcon ?></span>
            </div>
            <p class="text-white-50 mb-0" style="font-size: 0.76rem; line-height: 1.4;">Identifica restrições legais, suspensões ou reprovações de anúncios ligadas diretamente à página de destino.</p>
            <div class="border-top pt-2 mt-3 text-end">
              <small class="text-white-50 d-block" style="font-size: 0.7rem;">Status no Google Ads</small>
              <strong class="<?= $apClass ?>" style="font-size: 0.85rem;"><?= htmlspecialchars($apText) ?></strong>
            </div>
          </div>
        </div>

      </div>

      <?php if ($has_security_warning): ?>
        <!-- BANNER DE ALERTA LUXUOSO DE GRAVIDADE (GATILHO COMERCIAL DE VENDA) -->
        <div class="p-4 rounded-4 border mb-4" style="background: linear-gradient(135deg, rgba(220, 38, 38, 0.15) 0%, rgba(239, 68, 68, 0.05) 100%); border-color: rgba(220, 53, 69, 0.3) !important;">
          <div class="d-flex align-items-start gap-3">
            <span class="badge rounded-circle p-2 bg-danger text-white fs-4 d-inline-flex" style="box-shadow: 0 0 15px rgba(220, 38, 38, 0.4);"><i class="bi bi-shield-fill-x"></i></span>
            <div class="w-100">
              <h4 class="text-white fw-bold mb-2" style="font-size:1.15rem;">ALERTA CRÍTICO: Vulnerabilidade e Risco Comercial no Ar</h4>
              <p class="text-white-50 small mb-3" style="line-height: 1.6; font-size: 0.82rem;">
                Foram identificados problemas graves de segurança e conformidade de anúncios que colocam o faturamento da empresa em risco extremo. Inconformidades no Google Ads ou Safe Browsing podem resultar no congelamento total das campanhas pagas, bloqueio do domínio por navegadores e destruição imediata da reputação digital do negócio.
              </p>
              
              <div class="row g-3 mt-1">
                <?php foreach ($security_alerts as $alert): ?>
                  <div class="col-12">
                    <div class="p-3 rounded-3" style="background: rgba(11, 15, 25, 0.4); border-left: 4px solid <?= $alert['nivel'] === 'danger' ? '#ef4444' : '#f59e0b' ?>;">
                      <strong class="d-block text-white" style="font-size: 0.85rem;"><i class="bi bi-exclamation-triangle-fill me-1 text-<?= $alert['nivel'] === 'danger' ? 'danger' : 'warning' ?>"></i> <?= htmlspecialchars($alert['titulo']) ?></strong>
                      <span class="text-white-50 d-block mt-1 small" style="line-height: 1.4; font-size: 0.78rem;"><?= htmlspecialchars($alert['desc']) ?></span>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>

              <div class="mt-4 d-flex flex-wrap align-items-center justify-content-between gap-3 border-top pt-3" style="border-color: rgba(255,255,255,0.06) !important;">
                <span class="text-white-50 small" style="font-size: 0.78rem;"><i class="bi bi-info-circle me-1"></i> A correção técnica destas falhas deve ser executada de forma imediata e profissional.</span>
                <a href="mailto:contato@rajo.com.br?subject=Suporte%20Urgente%20-%20Corre%C3%A7%C3%A3o%20de%20Pol%C3%ADticas%20e%20Seguran%C3%A7a%20-%20<?= urlencode($r['cliente']) ?>" class="btn btn-danger btn-sm fw-bold px-4 py-2" style="border-radius: 20px; font-size: 0.8rem; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);">
                  <i class="bi bi-patch-check-fill me-1"></i> Contratar Correção Urgente de Segurança
                </a>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>

    </div>

    <!-- ─── CORE WEB VITALS ─── -->
    <div class="mb-5">
      <h3 class="mb-4 d-flex align-items-center gap-2">
        <i class="bi bi-bar-chart-line text-primary"></i> Métricas Core Web Vitals (CWV)
      </h3>
      <div class="row g-4">
        <?php
        $vitals = [
          ['LCP', 'Largest Contentful Paint', $r['cwv_lcp_mobile'], $r['cwv_lcp_desktop'], $r['cwv_lcp_status'], 'Mede a velocidade de carregamento percebida do elemento principal.'],
          ['INP', 'Interaction to Next Paint', $r['cwv_inp_mobile'], $r['cwv_inp_desktop'], $r['cwv_inp_status'], 'Mede a responsividade da página durante interações.'],
          ['CLS', 'Cumulative Layout Shift', $r['cwv_cls_mobile'], $r['cwv_cls_desktop'], $r['cwv_cls_status'], 'Mede a estabilidade visual da página durante o carregamento.'],
          ['FCP', 'First Contentful Paint', $r['cwv_fcp_mobile'], $r['cwv_fcp_desktop'], $r['cwv_fcp_status'], 'Mede o tempo até o primeiro elemento de texto ou imagem surgir.'],
          ['TTFB', 'Time to First Byte', $r['cwv_ttfb_mobile'], $r['cwv_ttfb_desktop'], $r['cwv_ttfb_status'], 'Mede a velocidade de resposta inicial do seu servidor.'],
          ['SPEED', 'Speed Index', $r['cwv_speed_mobile'], $r['cwv_speed_desktop'], $r['cwv_speed_status'], 'Mede quão rapidamente o conteúdo visual da página é preenchido.']
        ];
        foreach ($vitals as [$sigla, $nome, $mobVal, $deskVal, $status, $desc]):
          $badgeClass = match(strtolower($status)) {
            'bom'   => 'badge-bom',
            'médio' => 'badge-medio',
            default => 'badge-ruim'
          };
        ?>
          <div class="col-12 col-md-6 col-lg-4">
            <div class="cwv-card">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                  <h5 class="mb-0 text-white"><?= $sigla ?></h5>
                  <small class="text-white-50" style="font-size: 0.8rem;"><?= $nome ?></small>
                </div>
                <span class="metric-badge <?= $badgeClass ?>"><?= $status ?></span>
              </div>
              <p class="text-white-50" style="font-size: 0.78rem; line-height: 1.5; min-height: 48px;"><?= $desc ?></p>
              <div class="d-flex justify-content-between border-top pt-3 mt-3">
                <div>
                  <small class="text-white-50 d-block" style="font-size: 0.72rem;">Valor Celular</small>
                  <strong id="val-<?= strtolower($sigla) ?>-mob" class="text-white"><?= $mobVal ?: '–' ?></strong>
                </div>
                <div class="text-end">
                  <small class="text-white-50 d-block" style="font-size: 0.72rem;">Valor Desktop</small>
                  <strong id="val-<?= strtolower($sigla) ?>-desk" class="text-white"><?= $deskVal ?: '–' ?></strong>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- ─── PROBLEMAS & PLANO DE AÇÃO ─── -->
    <div class="row g-4 mb-5">
      
      <!-- Problemas Identificados -->
      <div class="col-12 col-lg-6">
        <div class="glass-panel h-100">
          <h4 class="mb-4 d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle text-danger"></i> Problemas Técnicos Identificados
          </h4>
          
          <?php if (empty($problemas)): ?>
            <div class="alert alert-secondary bg-transparent border-dashed text-white-50">Nenhum problema registrado para este site.</div>
          <?php else: ?>
            <div class="accordion" id="accordionProblemas">
              <?php 
              $probs_exibidos = $problemas;
              if ($tipo_relatorio === 'compacto') {
                  $probs_exibidos = array_slice($problemas, 0, 4);
              }
              foreach ($probs_exibidos as $idx => $p): 
                $corPri = match(strtolower($p['prioridade'] ?? '')) {
                  'baixa' => 'success',
                  'média' => 'warning',
                  default => 'danger'
                };
              ?>
                <div class="accordion-item accordion-item-custom">
                  <h2 class="accordion-header">
                    <button class="accordion-button accordion-button-custom collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseProb-<?= $idx ?>">
                      <div class="d-flex justify-content-between align-items-center w-100 pe-3">
                        <span class="text-truncate" style="max-width: 80%;"><?= htmlspecialchars($p['problema']) ?></span>
                        <span class="badge bg-<?= $corPri ?>-subtle text-<?= $corPri ?> border border-<?= $corPri ?>-subtle py-1 px-2" style="font-size: 0.65rem; border-radius: 10px;">
                          <?= htmlspecialchars($p['prioridade'] ?? 'Alta') ?>
                        </span>
                      </div>
                    </button>
                  </h2>
                  <div id="collapseProb-<?= $idx ?>" class="accordion-collapse collapse" data-bs-parent="#accordionProblemas">
                    <div class="accordion-body accordion-body-custom">
                      <div class="mb-2"><strong>Problema:</strong><br><?= htmlspecialchars($p['problema']) ?></div>
                      <?php if (!empty($p['impacto'])): ?>
                        <div><strong>Área Impactada:</strong> <span class="badge bg-primary-subtle text-primary border border-primary-subtle py-1 px-2 small ms-1" style="border-radius: 8px; font-size: 0.72rem;"><?= htmlspecialchars($p['impacto']) ?></span></div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Plano de Ação -->
      <div class="col-12 col-lg-6">
        <div class="glass-panel h-100 position-relative overflow-hidden">
          <h4 class="mb-4 d-flex align-items-center gap-2">
            <i class="bi bi-list-check text-success"></i> Plano de Ação Recomendado
          </h4>
          
          <?php if (empty($acoes)): ?>
            <div class="alert alert-secondary bg-transparent border-dashed text-white-50">Nenhuma ação programada para este site.</div>
          <?php else: ?>
            <div class="table-responsive" <?= $bloquear_plano == 1 ? 'style="filter: blur(5px); opacity: 0.25; pointer-events: none; user-select: none;"' : '' ?>>
              <table class="table table-dark-custom align-middle">
                <thead>
                  <tr>
                    <th>Ação Recomendada</th>
                    <th>Responsável</th>
                    <th class="text-end">Prazo</th>
                  </tr>
                </thead>
                <tbody>
                  <?php 
                  $acoes_exibidas = $acoes;
                  if ($tipo_relatorio === 'compacto') {
                      $acoes_exibidas = array_slice($acoes, 0, 3);
                  }
                  foreach ($acoes_exibidas as $a): 
                  ?>
                    <tr>
                      <td class="fw-semibold text-white"><?= htmlspecialchars($a['acao']) ?></td>
                      <td><span class="text-white-50"><?= htmlspecialchars($a['responsavel'] ?? 'Desenvolvedor') ?></span></td>
                      <td class="text-end"><span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="border-radius: 6px; font-size: 0.75rem; font-weight: 600; padding: 6px 10px;"><?= htmlspecialchars($a['prazo'] ?: 'Imediato') ?></span></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <?php if ($bloquear_plano == 1): ?>
              <!-- Locked Commercial Overlay -->
              <div class="position-absolute top-50 start-50 translate-middle w-100 h-100 d-flex flex-column align-items-center justify-content-center text-center px-4" 
                   style="background: rgba(11, 15, 25, 0.45); backdrop-filter: blur(2px); z-index: 10;">
                <div class="p-4 border rounded shadow-lg bg-dark bg-opacity-75" style="max-width: 380px; border-color: rgba(245, 158, 11, 0.25) !important; border-radius: 16px;">
                  <i class="bi bi-shield-lock-fill text-warning mb-2" style="font-size: 2.8rem; display: block;"></i>
                  <h5 class="text-white fw-bold mb-2" style="font-family: var(--font-title); font-size: 1.1rem;">Plano de Ação Reservado</h5>
                  <p class="text-white-50 small mb-3" style="line-height: 1.6; font-size: 0.8rem;">
                    Para proteger a nossa propriedade intelectual e garantir a correta homologação técnica, o plano de ação detalhado está reservado para a equipe da Rajo.
                  </p>
                  <a href="mailto:contato@rajo.com.br?subject=Destravar%20Plano%20de%20A%C3%A7%C3%A3o%20-%20<?= urlencode($r['cliente']) ?>" class="btn btn-warning btn-sm fw-bold px-4 py-2" style="border-radius: 20px; font-size: 0.8rem;">
                    <i class="bi bi-chat-dots-fill me-1"></i> Fale Conosco para Destravar
                  </a>
                </div>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>

    </div>

    <!-- ─── CONCLUSÃO TÉCNICA ─── -->
    <?php if (!empty($r['conclusao'])): ?>
      <div class="glass-panel mb-5">
        <h4 class="mb-4 d-flex align-items-center gap-2">
          <i class="bi bi-chat-left-quote text-primary"></i> Conclusão Técnica e Comercial
        </h4>
        <div style="font-size: 1.05rem; line-height: 1.8; color: #cbd5e1;">
          <?php 
          $conclusao_texto = $r['conclusao'];
          if ($tipo_relatorio === 'compacto' && !empty($conclusao_texto)) {
              $paragrafos = explode("\n\n", $conclusao_texto);
              $conclusao_texto = trim($paragrafos[0] ?? '');
              if (isset($paragrafos[1]) && strlen($conclusao_texto) < 300) {
                  $conclusao_texto .= "\n\n" . trim($paragrafos[1]);
              }
          }
          foreach (explode("\n\n", $conclusao_texto) as $para): 
          ?>
            <p><?= nl2br(htmlspecialchars(trim($para))) ?></p>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- ─── CARD DE CONVERSÃO COMERCIAL LUXUOSO ─── -->
    <div class="glass-panel text-center py-5 px-4 mb-5 border border-primary border-opacity-25" style="background: radial-gradient(circle at center, rgba(var(--primary-color-rgb), 0.12) 0%, rgba(11, 15, 25, 0) 80%); border-radius: 24px;">
      <i class="bi bi-rocket-takeoff text-primary fs-1 mb-3 d-inline-block" style="filter: drop-shadow(0 0 15px rgba(var(--primary-color-rgb), 0.5));"></i>
      <h3 class="text-white fw-bold mb-2">Pronto para Otimizar Seus Resultados Comerciais?</h3>
      <p class="text-white-50 mx-auto mb-4" style="max-width: 600px; font-size: 0.88rem; line-height: 1.6;">
        Não permita que a lentidão, as inconformidades técnicas e os riscos de segurança destruam a lucratividade das suas campanhas de marketing. A equipe de engenharia e otimização avançada da <strong>Rajo Desenvolvimento</strong> está de prontidão para implementar todas as ações corretivas identificadas neste diagnóstico.
      </p>
      <div class="d-flex justify-content-center gap-3 flex-wrap">
        <a href="mailto:contato@rajo.com.br?subject=Agendar%20Reuni%C3%A3o%20de%20Otimiza%C3%A7%C3%A3o%20-%20<?= urlencode($r['cliente']) ?>" class="btn btn-primary fw-bold px-4 py-2.5 d-flex align-items-center gap-2" style="border-radius: 30px; font-size: 0.85rem; box-shadow: 0 4px 15px rgba(var(--primary-color-rgb), 0.4);">
          <i class="bi bi-calendar-event-fill"></i> Agendar Reunião de Alinhamento
        </a>
        <a href="https://wa.me/5511999999999?text=Olá,%20gostaria%20de%20saber%20mais%20sobre%20a%20correção%20dos%20problemas%20técnicos%20do%20meu%20site%20relacionados%20ao%20diagnóstico%20do%20cliente%20<?= urlencode($r['cliente']) ?>" target="_blank" class="btn btn-outline-light fw-bold px-4 py-2.5 d-flex align-items-center gap-2" style="border-radius: 30px; font-size: 0.85rem;">
          <i class="bi bi-whatsapp"></i> Falar com um Especialista
        </a>
      </div>
    </div>

    <!-- ─── FOOTER ─── -->
    <footer class="text-center py-4 border-top" style="border-color: var(--border-color) !important;">
      <p class="small text-white-50 mb-1">&copy; <?= date('Y') ?> Rajo Desenvolvimento. Todos os direitos reservados.</p>
      <p class="small text-muted">Este diagnóstico online é confidencial e destinado exclusivamente a <?= htmlspecialchars($r['cliente']) ?>.</p>
    </footer>

  </div>

  <!-- Scripts -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
  
  <script>
    // Armazena as notas Mobile e Desktop
    const scores = {
      mobile: {
        perf: <?= (int)($r['ps_performance_mobile'] ?: 0) ?>,
        seo: <?= (int)($r['ps_seo_mobile'] ?: 0) ?>,
        acess: <?= (int)($r['ps_acessibilidade_mobile'] ?: 0) ?>,
        boas: <?= (int)($r['ps_boaspraticas_mobile'] ?: 0) ?>
      },
      desktop: {
        perf: <?= (int)($r['ps_performance_desktop'] ?: 0) ?>,
        seo: <?= (int)($r['ps_seo_desktop'] ?: 0) ?>,
        acess: <?= (int)($r['ps_acessibilidade_desktop'] ?: 0) ?>,
        boas: <?= (int)($r['ps_boaspraticas_desktop'] ?: 0) ?>
      }
    };

    const hasValue = {
      mobile: {
        perf: <?= $r['ps_performance_mobile'] !== '' && $r['ps_performance_mobile'] !== null ? 'true' : 'false' ?>,
        seo: <?= $r['ps_seo_mobile'] !== '' && $r['ps_seo_mobile'] !== null ? 'true' : 'false' ?>,
        acess: <?= $r['ps_acessibilidade_mobile'] !== '' && $r['ps_acessibilidade_mobile'] !== null ? 'true' : 'false' ?>,
        boas: <?= $r['ps_boaspraticas_mobile'] !== '' && $r['ps_boaspraticas_mobile'] !== null ? 'true' : 'false' ?>
      },
      desktop: {
        perf: <?= $r['ps_performance_desktop'] !== '' && $r['ps_performance_desktop'] !== null ? 'true' : 'false' ?>,
        seo: <?= $r['ps_seo_desktop'] !== '' && $r['ps_seo_desktop'] !== null ? 'true' : 'false' ?>,
        acess: <?= $r['ps_acessibilidade_desktop'] !== '' && $r['ps_acessibilidade_desktop'] !== null ? 'true' : 'false' ?>,
        boas: <?= $r['ps_boaspraticas_desktop'] !== '' && $r['ps_boaspraticas_desktop'] !== null ? 'true' : 'false' ?>
      }
    };

    const circumference = 2 * Math.PI * 40; // ~251.3

    function switchDevice(device) {
      // Ajusta botões
      document.getElementById('btnMobile').classList.toggle('active', device === 'mobile');
      document.getElementById('btnDesktop').classList.toggle('active', device === 'desktop');
      
      // Atualiza os progressos circulares
      const keys = ['perf', 'seo', 'acess', 'boas'];
      keys.forEach(k => {
        const circle = document.getElementById('circle-' + k);
        const valueDiv = document.getElementById('value-' + k);
        if (!circle || !valueDiv) return;
        
        if (!hasValue[device][k]) {
          valueDiv.textContent = '–';
          circle.style.strokeDashoffset = circumference;
          circle.setAttribute('stroke', '#64748b');
          return;
        }

        const val = scores[device][k];
        valueDiv.textContent = val;
        
        // Cor dinâmica
        let color = '#ef4444';
        if (val >= 90) color = '#10b981';
        else if (val >= 50) color = '#f59e0b';
        
        circle.setAttribute('stroke', color);
        
        // Calcula offset do tracejado
        const offset = circumference - (val / 100) * circumference;
        circle.style.strokeDashoffset = offset;
      });
    }

    // Inicialização da página
    document.addEventListener("DOMContentLoaded", () => {
      // Dispara a visualização inicial mobile após uma breve pausa para a animação disparar
      setTimeout(() => {
        switchDevice('mobile');
      }, 300);
    });
  </script>
</body>
</html>
<?php
// Função helper para converter Hexadecimal para RGB
function hexToRgb($hex) {
    $hex = str_replace("#", "", $hex);
    if(strlen($hex) == 3) {
        $r = hexdec(substr($hex,0,1).substr($hex,0,1));
        $g = hexdec(substr($hex,1,1).substr($hex,1,1));
        $b = hexdec(substr($hex,2,1).substr($hex,2,1));
    } else {
        $r = hexdec(substr($hex,0,2));
        $g = hexdec(substr($hex,2,2));
        $b = hexdec(substr($hex,4,2));
    }
    return "$r, $g, $b";
}
?>
