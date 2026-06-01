<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { http_response_code(400); die('ID inválido'); }

$stmt = db()->prepare('SELECT * FROM relatorios WHERE id = ?');
$stmt->execute([$id]);
$r = $stmt->fetch();
if (!$r) { http_response_code(404); die('Relatório não encontrado'); }

$problemas = json_decode($r['problemas'] ?? '[]', true) ?: [];
$acoes     = json_decode($r['acoes']     ?? '[]', true) ?: [];

// ─── Helper de cor por nota numérica ──────────────────────────
function corNota(string $v): string {
    if (!is_numeric($v)) {
        return match(strtolower(trim($v))) {
            'passing', 'a'              => '#2E7D32',
            'warning', 'b', 'c'         => '#E65100',
            default                     => '#D32F2F',
        };
    }
    $n = (int)$v;
    if ($n >= 90) return '#2E7D32';
    if ($n >= 50) return '#E65100';
    return '#D32F2F';
}
function bgNota(string $v): string {
    if (!is_numeric($v)) {
        return match(strtolower(trim($v))) {
            'passing', 'a'              => '#EDF7EE',
            'warning', 'b', 'c'         => '#FFF9E6',
            default                     => '#FDECEA',
        };
    }
    $n = (int)$v;
    if ($n >= 90) return '#EDF7EE';
    if ($n >= 50) return '#FFF9E6';
    return '#FDECEA';
}
function corStatus(string $s): string {
    return match(strtolower(trim($s))) {
        'bom'   => '#2E7D32',
        'médio' => '#E65100',
        default => '#D32F2F',
    };
}
function bgStatus(string $s): string {
    return match(strtolower(trim($s))) {
        'bom'   => '#EDF7EE',
        'médio' => '#FFF9E6',
        default => '#FDECEA',
    };
}
function corPri(string $p): string {
    return match(strtolower(trim($p))) {
        'baixa' => '#2E7D32',
        'média' => '#E65100',
        default => '#D32F2F',
    };
}
function bgPri(string $p): string {
    return match(strtolower(trim($p))) {
        'baixa' => '#EDF7EE',
        'média' => '#FFF9E6',
        default => '#FDECEA',
    };
}
function score_cell(string $v, string $label = ''): string {
    $v = $v !== '' ? $v : '–';
    if (is_numeric($v) && (int)$v >= 85) {
        return "<td align='center' bgcolor='#EDF7EE' style='color:#2E7D32;font-weight:bold;font-size:8.5pt;'>✓ Aprovado</td>";
    }
    if (strtolower(trim($v)) === 'a') {
        return "<td align='center' bgcolor='#EDF7EE' style='color:#2E7D32;font-weight:bold;font-size:8.5pt;'>✓ Aprovado</td>";
    }
    $cor = $v === '–' ? '#888888' : corNota($v);
    $bg  = $v === '–' ? '#F5F5F5' : bgNota($v);
    return "<td align='center' bgcolor='{$bg}' style='color:{$cor};font-weight:bold;'>{$v}</td>";
}
function cwv_val(string $v): string {
    return $v !== '' ? htmlspecialchars($v) : '–';
}
function deveExibirLinha(string $desk, string $mob, bool $isGtmetrix = false): bool {
    $d = trim($desk);
    $m = trim($mob);
    
    if ($isGtmetrix) {
        if ($d === '' || $d === '–') return false;
        return strtolower($d) !== 'a';
    }
    
    $deskBad = ($d !== '' && $d !== '–' && is_numeric($d) && (int)$d < 85);
    $mobBad  = ($m !== '' && $m !== '–' && is_numeric($m) && (int)$m < 85);
    
    return $deskBad || $mobBad;
}

// ─── OFUSCADORES DE PROTEÇÃO COMERCIAL PARA RELATÓRIOS EXTERNOS ───
function ofuscarAcao(string $texto, bool $bloquear): string {
    if (!$bloquear) return htmlspecialchars($texto);
    $palavras = explode(' ', $texto);
    if (count($palavras) <= 3) {
        return htmlspecialchars($texto) . " <span style='background-color:#555;color:#555;border-radius:2px;font-family:monospace;padding:0 3px;'>██████████</span>";
    }
    $visivel = implode(' ', array_slice($palavras, 0, 3));
    return htmlspecialchars($visivel) . " <span style='background-color:#555;color:#555;border-radius:2px;font-family:monospace;padding:0 3px;'>██████████████████████████</span>";
}

function formatarProblema(string $texto, bool $bloquear): string {
    if (!$bloquear) return htmlspecialchars($texto);
    
    // Mapeamento dinâmico para tom técnico extremamente premium e dependente dos serviços da Rajo
    $mapeamento = [
        'imagens sem atributo descritivo alt' => 'Inconformidade em tags de imagem (ausência de metadados ALT descritivos)',
        'imagens no site sem a propriedade alt' => 'Inconformidade em tags de imagem (ausência de metadados ALT descritivos)',
        'recursos bloqueadores de renderização' => 'Gargalo crítico no caminho de renderização (Critical Rendering Path) por recursos síncronos',
        'ausência ou má configuração das meta tags' => 'Déficit severo na indexação semântica e ausência de tags de cabeçalho prioritárias',
        'ausência do pixel do google analytics' => 'Inexistência de camada de telemetria avançada (Google Analytics GA4) na landing page',
        'ausência do pixel do facebook' => 'Inexistência de pixel de rastreamento social (Meta Pixel) para campanhas de remarketing',
        'ausência da tag do google tag manager' => 'Inexistência de arquitetura centralizada de dados de tags de marketing (GTM)',
        'tempo de resposta inicial do servidor (ttfb) muito elevado' => 'Latência severa de primeiro byte (TTFB) originada no processamento backend',
        'instabilidade visual de layout severa (layout shift)' => 'Déficit na estabilidade cumulativa do layout (CLS) com deslocamento dinâmico do DOM',
        'excesso de processamento da linha de execução principal' => 'Bloqueio severo na linha de execução principal (Main-Thread Block) por scripts terceiros',
    ];
    
    $textoLower = mb_strtolower(trim($texto));
    $textoFormatado = $texto;
    
    foreach ($mapeamento as $chave => $substituto) {
        if (str_contains($textoLower, $chave)) {
            $textoFormatado = $substituto;
            break;
        }
    }
    
    // Oculta trechos de localização para criar dependência profissional
    $partes = explode('. ', $textoFormatado);
    $principal = $partes[0];
    
    return $principal . " <span style='background-color:#555;color:#555;border-radius:2px;font-family:monospace;padding:0 3px;'>████████████████████</span>";
}

function getLightTint(string $hex): string {
    $hex = str_replace('#', '', $hex);
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (strlen($hex) !== 6) return '#E8EEF9';
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    // blend with white (90% white, 10% color)
    $mixR = round($r * 0.1 + 255 * 0.9);
    $mixG = round($g * 0.1 + 255 * 0.9);
    $mixB = round($b * 0.1 + 255 * 0.9);
    
    return sprintf("#%02x%02x%02x", $mixR, $mixG, $mixB);
}

$AZUL  = !empty($r['pdf_cor_tema']) ? $r['pdf_cor_tema'] : '#1A4FBB';
$AZUL2 = getLightTint($AZUL);
$CINZA = '#444444';

$tipo_relatorio = $_GET['formato'] ?? $r['tipo_relatorio'] ?? 'completo';
if (!in_array($tipo_relatorio, ['completo', 'compacto'])) {
    $tipo_relatorio = 'completo';
}

$plano_get = $_GET['plano'] ?? null;
if ($plano_get === 'oculto') {
    $bloquear = true;
} elseif ($plano_get === 'liberado') {
    $bloquear = false;
} else {
    $bloquear = (int)($r['bloquear_plano'] ?? 0) === 1;
}

if ($tipo_relatorio === 'compacto') {
    // RENDERIZA O RELATÓRIO COMPACTO DE 1 PÁGINA
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
    <meta charset="UTF-8">
    <style>
      * { margin:0; padding:0; box-sizing:border-box; }
      body { font-family: Arial, sans-serif; font-size:8pt; color:#1e293b; line-height:1.35; background-color:#fff; }
      
      /* Cabeçalho Luxuoso Compacto */
      .header-compact { border-bottom:3.5px solid <?= $AZUL ?>; padding-bottom:6pt; margin-bottom:8pt; }
      .header-table { width:100%; border:none; margin:0; }
      .header-table td { border:none; padding:0; vertical-align:middle; }
      .header-logo { font-size:22pt; font-weight:900; color:<?= $AZUL ?>; letter-spacing:2px; text-transform:uppercase; font-family:'Outfit', sans-serif; }
      .header-meta { font-size:7.5pt; color:#475569; text-align:right; font-weight:500; }
      
      .title-banner { background:<?= $AZUL ?>; color:#fff; padding:7pt 12pt; border-radius:8px; margin-bottom:8pt; text-align:center; font-family:'Outfit', sans-serif; }
      .title-banner h1 { font-size:11.8pt; font-weight:bold; text-transform:uppercase; letter-spacing:1px; margin:0; padding:0; border:none; color:#fff; }
      
      /* Grades compactas */
      .grid-table { width:100%; border:none; margin-bottom:6pt; }
      .grid-table td { border:none; padding:0; vertical-align:top; }
      
      /* Painéis Modernos */
      .panel-compact { border:1px solid #e2e8f0; border-radius:8px; padding:7pt 9pt; background-color:#f8fafc; margin-bottom:6pt; }
      .panel-title { font-size:9pt; font-weight:bold; color:<?= $AZUL ?>; margin-bottom:4pt; border-bottom:1.5px solid #e2e8f0; padding-bottom:3pt; text-transform:uppercase; letter-spacing:0.5px; }
      
      /* Tabelas ultra-compactas */
      table { width:100%; border-collapse:collapse; margin-bottom:0pt; }
      th, td { padding:3.5pt 4.5pt; border:1px solid #e2e8f0; font-size:7.5pt; text-align:left; color:#334155; }
      thead th { background:<?= $AZUL ?>; color:#fff; font-weight:bold; font-size:7.5pt; border:1px solid <?= $AZUL ?>; text-transform:uppercase; }
      
      .badge-std { display:inline-block; padding:1.5pt 3pt; border-radius:4px; font-size:6.8pt; font-weight:bold; text-align:center; }
      .badge-ok { background-color:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
      .badge-warn { background-color:#fef3c7; color:#9a3412; border:1px solid #fde68a; }
      .badge-danger { background-color:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }
      
      .text-bold { font-weight:bold; color:#0f172a; }
      .font-mono { font-family:monospace; font-size:7pt; }
    </style>
    </head>
    <body>
    
    <!-- HEADER -->
    <div class="header-compact">
      <table class="header-table">
        <tr>
          <td>
            <?php if (!empty($r['logo_cliente'])): ?>
              <img src="<?= htmlspecialchars($r['logo_cliente']) ?>" style="max-height:22pt; max-width:120pt;" />
            <?php else: ?>
              <div class="header-logo">RAJO</div>
            <?php endif; ?>
          </td>
          <td class="header-meta">
            <strong>DIAGNÓSTICO TÉCNICO COMPACTO</strong><br>
            Cliente: <?= htmlspecialchars($r['cliente']) ?><br>
            Domínio: <?= htmlspecialchars($r['dominio']) ?><br>
            Data: <?= date('d/m/Y', strtotime($r['data_relatorio'])) ?>
          </td>
        </tr>
      </table>
    </div>
    
    <!-- BANNER TÍTULO -->
    <div class="title-banner">
      <h1>Relatório Executivo de Auditoria & Otimização Web</h1>
    </div>
    
    <!-- GRID 1: NOTAS E INFRAESTRUTURA -->
    <table class="grid-table" style="margin-bottom:4pt;">
      <tr>
        <!-- Seção de Pontuações -->
        <td style="width:49%; padding-right:5pt;">
          <div class="panel-compact" style="height:190pt;">
            <div class="panel-title">Pontuações de Auditoria</div>
            <table>
              <thead>
                <tr>
                  <th>Métrica / Categoria</th>
                  <th align="center">Desktop</th>
                  <th align="center">Mobile</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="text-bold">Performance Geral</td>
                  <?= score_cell($r['ps_performance_desktop']) ?>
                  <?= score_cell($r['ps_performance_mobile']) ?>
                </tr>
                <tr>
                  <td class="text-bold">SEO Técnico On-Page</td>
                  <?= score_cell($r['ps_seo_desktop']) ?>
                  <?= score_cell($r['ps_seo_mobile']) ?>
                </tr>
                <tr>
                  <td class="text-bold">Melhores Práticas Web</td>
                  <?= score_cell($r['ps_boaspraticas_desktop']) ?>
                  <?= score_cell($r['ps_boaspraticas_mobile']) ?>
                </tr>
                <tr>
                  <td class="text-bold">Acessibilidade Geral</td>
                  <?= score_cell($r['ps_acessibilidade_desktop']) ?>
                  <?= score_cell($r['ps_acessibilidade_mobile']) ?>
                </tr>
                <?php if ($r['gtm_nota']): ?>
                <tr>
                  <td class="text-bold">Performance GTmetrix</td>
                  <td align="center" colspan="2" bgcolor="<?= bgNota($r['gtm_nota']) ?>" style="color:<?= corNota($r['gtm_nota']) ?>; font-weight:bold;"><?= htmlspecialchars($r['gtm_nota']) ?></td>
                </tr>
                <?php endif; ?>
              </tbody>
            </table>
            
            <div style="margin-top:5pt; font-size:7pt; color:#475569;">
              <strong>Resultado Geral da Auditoria:</strong> 
              <span class="badge-std badge-<?= $r['resultado_geral'] === 'BOM' ? 'ok' : ($r['resultado_geral'] === 'MÉDIO' ? 'warn' : 'danger') ?>" style="padding:1pt 4pt; font-size:7.5pt; text-transform:uppercase;">
                <?= htmlspecialchars($r['resultado_geral']) ?>
              </span>
            </div>
            
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
            ?>
              <div style="margin-top:5pt; border-top:1px dashed #cbd5e1; padding-top:3.5pt; font-size:6.6pt; color:#581c87; line-height:1.25;">
                <strong>Perda Estimada em Google Ads:</strong><br>
                Lentidão móvel gera <strong><?= round($D) ?>% de desperdício</strong>. <span style="color:#b91c1c; font-weight:bold;">Perda: R$ <?= number_format($prejuizo, 2, ',', '.') ?>/mês</span> (≈<?= $cliquesPerdidos ?> cliques perdidos).
              </div>
            <?php endif; ?>
          </div>
        </td>
        
        <!-- Seção de Infraestrutura e Segurança -->
        <td style="width:51%; padding-left:5pt;">
          <div class="panel-compact" style="height:190pt;">
            <div class="panel-title">Infraestrutura & Segurança</div>
            
            <!-- CMS -->
            <div style="margin-bottom:3.5pt; border-bottom:1px dashed #e2e8f0; padding-bottom:3.5pt; font-size:7.5pt;">
              <strong>Plataforma CMS:</strong> 
              <span class="badge-std <?= ($r['auditoria_cms'] ?? 'Não Identificado') !== 'Não Identificado' ? 'badge-ok' : 'badge-warn' ?>">
                <?= htmlspecialchars($r['auditoria_cms'] ?? 'Não Identificado') ?>
              </span>
            </div>
            
            <!-- IP/Hospedagem -->
            <?php
            $geo = !empty($r['auditoria_hospedagem']) ? json_decode($r['auditoria_hospedagem'], true) : null;
            ?>
            <div style="margin-bottom:3.5pt; border-bottom:1px dashed #e2e8f0; padding-bottom:3.5pt; font-size:7.2pt;">
              <strong>Hospedagem:</strong> 
              <?php if ($geo): ?>
                <span class="text-bold"><?= htmlspecialchars($geo['provedor']) ?></span> <span style="color:#718096; font-size:6.8pt;">(<?= htmlspecialchars($geo['pais']) ?>)</span>
              <?php else: ?>
                <span class="text-muted">Não Auditada</span>
              <?php endif; ?>
            </div>
            
            <!-- Segurança HTTP & SSL -->
            <?php
            $seg = !empty($r['auditoria_seguranca']) ? json_decode($r['auditoria_seguranca'], true) : null;
            ?>
            <div style="margin-bottom:3.5pt; border-bottom:1px dashed #e2e8f0; padding-bottom:3.5pt; font-size:7.2pt;">
              <strong>SSL / HTTPS:</strong> 
              <?php if ($seg): ?>
                <span class="badge-std <?= $seg['ssl_ativo'] ? 'badge-ok' : 'badge-danger' ?>"><?= $seg['ssl_ativo'] ? '✓ Ativo' : '✗ Inativo' ?></span>
                <?php
                $ativos = 0;
                foreach (['hsts', 'csp', 'x_frame', 'x_content', 'referrer'] as $c) {
                    if (!empty($seg[$c])) $ativos++;
                }
                ?>
                <span style="color:#718096; font-size:6.8pt;">&bull; <strong>Headers:</strong> <?= $ativos ?>/5</span>
              <?php else: ?>
                <span class="text-muted">Não Auditada</span>
              <?php endif; ?>
            </div>
            
            <!-- Segurança de E-mail (DNS) -->
            <?php
            $dns = !empty($r['auditoria_dns']) ? json_decode($r['auditoria_dns'], true) : null;
            ?>
            <div style="margin-bottom:4pt; border-bottom:1px dashed #cbd5e1; padding-bottom:3.5pt; font-size:7.2pt;">
              <strong>DNS de E-mail (SPAM):</strong>
              <?php if ($dns): ?>
                <span class="badge-std <?= $dns['spf_valido'] ? 'badge-ok' : 'badge-danger' ?>" style="padding:1px 2px; font-size:6.5pt;"><?= $dns['spf_valido'] ? '✓ SPF' : '✗ SPF' ?></span>
                <span class="badge-std <?= $dns['dmarc_valido'] ? 'badge-ok' : 'badge-danger' ?>" style="padding:1px 2px; font-size:6.5pt;"><?= $dns['dmarc_valido'] ? '✓ DMARC' : '✗ DMARC' ?></span>
              <?php else: ?>
                <span class="text-muted">Não Auditada</span>
              <?php endif; ?>
            </div>

            <!-- Google Ads & Segurança de Anúncios (Nova Seção Integrada) -->
            <?php
            $adExpC = trim($r['ad_experience_status'] ?? '');
            $sbC = trim($r['safe_browsing_status'] ?? '');
            $apC = trim($r['ads_policy_status'] ?? '');
            
            $adExpCompactBadge = 'badge-ok';
            if (str_contains(strtolower($adExpC), 'warning')) $adExpCompactBadge = 'badge-warn';
            else if (str_contains(strtolower($adExpC), 'failing')) $adExpCompactBadge = 'badge-danger';
            
            $sbCompactBadge = 'badge-ok';
            if (str_contains(strtolower($sbC), 'parcialmente')) $sbCompactBadge = 'badge-warn';
            else if (str_contains(strtolower($sbC), 'lista negra') || str_contains(strtolower($sbC), 'perigoso')) $sbCompactBadge = 'badge-danger';
            
            $apCompactBadge = 'badge-ok';
            if (str_contains(strtolower($apC), 'restrição')) $apCompactBadge = 'badge-warn';
            else if (str_contains(strtolower($apC), 'reprovado') || str_contains(strtolower($apC), 'suspensa')) $apCompactBadge = 'badge-danger';
            
            $alertaSegCompacto = '';
            if ($adExpCompactBadge === 'badge-danger' || $sbCompactBadge === 'badge-danger' || $apCompactBadge === 'badge-danger') {
                $alertaSegCompacto = '✗ RISCO DE BLOQUEIO ATIVO';
            } else if ($adExpCompactBadge === 'badge-warn' || $sbCompactBadge === 'badge-warn' || $apCompactBadge === 'badge-warn') {
                $alertaSegCompacto = '⚠ ALERTA DE CONFORMIDADE ADS';
            }
            ?>
            <div style="font-size:7.2pt;">
              <strong>Conformidade Google Ads &amp; Segurança:</strong>
              <table style="border:none; margin-top:2px; width:100%; border-collapse:collapse;">
                <tr style="border:none;">
                  <td style="border:none; padding:1px; font-size:6.8pt; width:33%;">Ad Exp: <span class="badge-std <?= $adExpCompactBadge ?>" style="font-size:6pt; padding:0 2px;"><?= $adExpC ? (str_contains(strtolower($adExpC), 'passing') ? 'OK' : (str_contains(strtolower($adExpC), 'warning') ? 'WARN' : 'FAIL')) : 'N/A' ?></span></td>
                  <td style="border:none; padding:1px; font-size:6.8pt; width:33%;">Safe Br: <span class="badge-std <?= $sbCompactBadge ?>" style="font-size:6pt; padding:0 2px;"><?= $sbC ? (str_contains(strtolower($sbC), 'nenhuma') ? 'OK' : 'FAIL') : 'N/A' ?></span></td>
                  <td style="border:none; padding:1px; font-size:6.8pt; width:34%;">Policies: <span class="badge-std <?= $apCompactBadge ?>" style="font-size:6pt; padding:0 2px;"><?= $apC ? (str_contains(strtolower($apC), 'sem restr') ? 'OK' : 'FAIL') : 'N/A' ?></span></td>
                </tr>
              </table>
              <?php if ($alertaSegCompacto): ?>
                <div style="margin-top:3pt; background:#fee2e2; border:1px solid #fca5a5; border-radius:4px; padding:1pt 2pt; color:#b91c1c; font-weight:bold; font-size:6.5pt; text-align:center; text-transform:uppercase; letter-spacing:0.2px;">
                  <?= $alertaSegCompacto ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </td>
      </tr>
    </table>
    
    <!-- SEÇÃO 2: MAIORES FALHAS CRÍTICAS MAPEADAS -->
    <div class="panel-compact" style="margin-bottom:6pt;">
      <div class="panel-title">Principais Falhas e Pontos Críticos Detectados</div>
      <?php 
      // Mantém a lógica global de bloqueio já calculada no topo
      if (!empty($problemas)): ?>
        <table style="border:none;">
          <tbody>
            <?php 
            $i = 0;
            foreach ($problemas as $p): 
              $i++;
              if ($i > 4) break; // Limita a no máximo 4 problemas no compacto para caber exatamente em 1 página
            ?>
            <tr>
              <td style="width:3%; text-align:center; border:none; padding:2.5pt 0; color:#b91c1c;">✗</td>
              <td style="border:none; padding:2.5pt 4pt; font-size:7.6pt; line-height:1.3;">
                <strong style="color:#1e293b;"><?= htmlspecialchars($p['impacto'] ?? 'Geral') ?>:</strong> 
                <?= formatarProblema($p['problema'], $bloquear) ?>
              </td>
              <td style="width:12%; border:none; padding:2.5pt 0; text-align:right;">
                <span class="badge-std badge-<?= strtolower(trim($p['prioridade'])) === 'alta' ? 'danger' : (strtolower(trim($p['prioridade'])) === 'média' ? 'warn' : 'ok') ?>" style="font-size:6.5pt; text-transform:uppercase; padding:1pt 3pt;">
                  <?= htmlspecialchars($p['prioridade']) ?>
                </span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="badge-std badge-ok py-1.5 px-3">✓ Nenhuma falha crítica mapeada para o site.</div>
      <?php endif; ?>
    </div>
    
    <!-- SEÇÃO 3: CRONOGRAMA DE INTERVENÇÃO MACRO -->
    <div class="panel-compact" style="margin-bottom:6pt;">
      <div class="panel-title">Plano de Ação & Intervenções Recomendadas</div>
      <?php if (!empty($acoes)): ?>
        <table>
          <thead>
            <tr>
              <th style="width:58%">Intervenção Estrutural Sugerida</th>
              <th style="width:22%">Responsabilidade</th>
              <th style="width:20%" align="center">Prazo Recomendado</th>
            </tr>
          </thead>
          <tbody>
            <?php 
            $i = 0;
            foreach ($acoes as $a): 
              $i++;
              if ($i > 3) break; // Limita a no máximo 3 no compacto
            ?>
            <tr>
              <td class="text-bold" style="font-size:7.4pt;"><?= ofuscarAcao($a['acao'], $bloquear) ?></td>
              <td style="color:#4a5568; font-size:7.4pt;"><?= htmlspecialchars($a['responsavel']) ?></td>
              <td align="center" bgcolor="<?= $AZUL2 ?>" style="color:<?= $AZUL ?>; font-weight:bold; font-size:7.4pt;"><?= htmlspecialchars($a['prazo']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="badge-std badge-ok py-1 px-3">Nenhum plano de ação necessário.</div>
      <?php endif; ?>
    </div>
    
    <!-- SEÇÃO 4: CONTEXTO E CONCLUSÃO COMERCIAL -->
    <div class="panel-compact" style="margin-bottom:8pt;">
      <div class="panel-title">Resumo Técnico & Parecer Comercial</div>
      <?php if ($r['conclusao']): 
        // Pega apenas o primeiro ou os dois primeiros parágrafos curtos para caber no compacto
        $paragrafos = explode("\n\n", $r['conclusao']);
        $resumo_conclusao = trim($paragrafos[0] ?? '');
        if (isset($paragrafos[1]) && strlen($resumo_conclusao) < 300) {
            $resumo_conclusao .= "\n\n" . trim($paragrafos[1]);
        }
      ?>
        <p style="text-align:justify; font-size:7.6pt; color:#334155; line-height:1.35;"><?= nl2br(htmlspecialchars($resumo_conclusao)) ?></p>
      <?php else: ?>
        <p class="text-muted small">Sem parecer registrado para este relatório.</p>
      <?php endif; ?>
      <div style="margin-top:5pt; border-top:1px dashed #cbd5e1; padding-top:4pt; font-size:7.2pt; color:<?= $AZUL ?>; font-weight:bold; text-align:center;">
        🎯 Quer eliminar estes gargalos de conversão imediatamente? Entre em contato com a Rajo Desenvolvimento.
      </div>
    </div>
    
    <!-- RODAPÉ CORPORATIVO -->
    <div style="border-top:2px solid <?= $AZUL ?>; padding-top:4pt; text-align:center;">
      <p style="font-size:8.2pt; color:<?= $AZUL ?>; font-weight:bold; margin-bottom:1pt;">
        <?= htmlspecialchars($r['analista']) ?> &bull; Rajo Desenvolvimento
      </p>
      <p style="font-size:7pt; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">
        rajo.com.br &bull; contato@rajo.com.br &bull; Engenharia de Conversão e Otimização Avançada
      </p>
    </div>
    
    </body>
    </html>
    <?php
    $html = ob_get_clean();
    
    $mpdf = new \Mpdf\Mpdf([
        'mode'        => 'utf-8',
        'format'      => 'A4',
        'margin_top'  => 10,
        'margin_right'=> 10,
        'margin_bottom'=> 10,
        'margin_left' => 10,
        'default_font' => 'arial',
        'tempDir'     => sys_get_temp_dir() . '/mpdf',
    ]);
    
    $screenshot_local = $r['screenshot_path'] ?? null;
    if ($screenshot_local && file_exists(__DIR__ . '/' . $screenshot_local)) {
        $mpdf->SetWatermarkImage(__DIR__ . '/' . $screenshot_local, 0.05, 'F');
        $mpdf->showWatermarkImage = true;
    }
    
    $mpdf->WriteHTML($html);
    
    $filename = 'Diagnostico_Compacto_' . preg_replace('/[^a-zA-Z0-9]/', '_', $r['cliente']) . '_' . date('Ymd', strtotime($r['data_relatorio'])) . '.pdf';
    $mpdf->Output($filename, 'I');
    exit;
}

// ─── HTML do relatório ────────────────────────────────────────
ob_start();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: Arial, sans-serif; font-size:9.5pt; color:#2d3748; line-height:1.45; }
  
  /* Cabeçalhos modernos e elegantes */
  h1 { font-size:15pt; color:<?= $AZUL ?>; font-weight:bold; margin-top:14pt; margin-bottom:8pt; text-transform:uppercase; letter-spacing:0.5px; border-bottom:2px solid <?= $AZUL ?>; padding-bottom:3pt; }
  h2 { font-size:12pt; color:<?= $AZUL ?>; font-weight:bold; margin-top:12pt; margin-bottom:6pt; }
  h3 { font-size:10pt; color:<?= $AZUL ?>; font-weight:bold; margin-top:10pt; margin-bottom:4pt; }
  p  { margin-bottom:6pt; line-height:1.45; color:#4a5568; }
  hr { border:none; border-top:1px solid #edf2f7; margin:10pt 0; }
  .small { font-size:8pt; }
  .muted { color:#718096; }
  
  /* Tabelas corporativas modernas e limpas */
  table  { width:100%; border-collapse:collapse; margin-bottom:8pt; margin-top:4pt; }
  th, td { padding:5pt 6pt; border:1px solid #e2e8f0; font-size:8.5pt; text-align:left; }
  thead th { background:<?= $AZUL ?>; color:#fff; font-weight:bold; font-size:8.5pt; border:1px solid <?= $AZUL ?>; text-transform:uppercase; letter-spacing:0.5px; }
  .th-sub  { background:<?= $AZUL2 ?>; color:<?= $AZUL ?>; font-weight:bold; }
  
  /* Alertas premium com bordas arredondadas */
  .box-warn  { background:#fffaf0; border-left:4px solid #dd6b20; border-top:1px solid #feebc8; border-right:1px solid #feebc8; border-bottom:1px solid #feebc8; padding:8pt; margin:6pt 0; font-size:8.5pt; border-radius:4px; color:#c05621; }
  .box-error { background:#fff5f5; border-left:4px solid #e53e3e; border-top:1px solid #fed7d7; border-right:1px solid #fed7d7; border-bottom:1px solid #fed7d7; padding:8pt; margin:6pt 0; font-size:8.5pt; border-radius:4px; color:#c53030; }
  .box-ok    { background:#f0fff4; border-left:4px solid #38a169; border-top:1px solid #c6f6d5; border-right:1px solid #c6f6d5; border-bottom:1px solid #c6f6d5; padding:8pt; margin:6pt 0; font-size:8.5pt; border-radius:4px; color:#276749; }

  /* Capa Minimalista e Premium */
  .capa-container { padding: 40pt 20pt; text-align:center; }
  .capa-logo-box { margin-bottom:30pt; }
  .capa-logo  { font-size:38pt; font-weight:900; color:<?= $AZUL ?>; letter-spacing:6pt; margin-bottom:5pt; }
  .capa-sub   { font-size:10.5pt; color:#718096; letter-spacing:1px; text-transform:uppercase; margin-bottom:40pt; }
  .capa-title { font-size:24pt; font-weight:bold; color:<?= $AZUL ?>; margin-bottom:8pt; letter-spacing:-0.5px; line-height:1.2; text-transform:uppercase; }
  .capa-desc  { font-size:11pt; color:#4a5568; margin-bottom:50pt; font-weight:300; }
  .capa-table { width:80%; margin:0 auto; border:none; }
  .capa-table td { padding:6pt 8pt; border:none; border-bottom:1px solid #edf2f7; font-size:9.5pt; }
  .capa-table tr:last-child td { border-bottom:none; }
  .capa-rodape { font-size:8pt; color:#a0aec0; font-style:italic; margin-top:60pt; text-transform:uppercase; letter-spacing:1px; }

  /* Legenda de Pontuação */
  .legenda span { font-size:8pt; font-weight:bold; margin-right:12pt; }

  /* Quebras de página controladas */
  .page-break { page-break-before:always; }
</style>
</head>
<body>

<!-- ═══════════════════════════ CAPA ════════════════════════════ -->
<div class="capa-container">
  <div class="capa-logo-box">
    <?php if (!empty($r['logo_cliente'])): ?>
      <img src="<?= htmlspecialchars($r['logo_cliente']) ?>" style="max-height:80px; margin-bottom:10pt;">
    <?php else: ?>
      <div class="capa-logo">RAJO</div>
    <?php endif; ?>
    <div class="capa-sub">Desenvolvimento &amp; Consultoria Digital</div>
  </div>
  
  <div style="margin: 40pt 0;">
    <div class="capa-title">DIAGNÓSTICO TÉCNICO DE SITE</div>
    <div style="width:60px; height:3px; background:<?= $AZUL ?>; margin: 12pt auto;"></div>
    <div class="capa-desc">Auditoria de Velocidade, SEO On-Page e Otimização para Mídia Paga</div>
  </div>

  <table class="capa-table">
    <tr>
      <td style="width:40%; color:#718096; font-weight:bold;">Cliente</td>
      <td style="color:#2d3748; font-weight:bold;"><?= htmlspecialchars($r['cliente']) ?></td>
    </tr>
    <tr>
      <td style="color:#718096; font-weight:bold;">Domínio Analisado</td>
      <td style="color:<?= $AZUL ?>; font-weight:bold;"><?= htmlspecialchars($r['dominio']) ?></td>
    </tr>
    <tr>
      <td style="color:#718096; font-weight:bold;">Data de Emissão</td>
      <td style="color:#2d3748;"><?= date('d/m/Y', strtotime($r['data_relatorio'])) ?></td>
    </tr>
    <tr>
      <td style="color:#718096; font-weight:bold;">Responsável Técnico</td>
      <td style="color:#2d3748;"><?= htmlspecialchars($r['analista']) ?></td>
    </tr>
    <tr>
      <td style="color:#718096; font-weight:bold;">Status do Documento</td>
      <td style="color:#e53e3e; font-weight:bold;">Confidencial (Revisão Técnico-Comercial)</td>
    </tr>
  </table>

  <div class="capa-rodape">
    rajo.com.br &bull; contato@rajo.com.br &bull; Documento Oficial
  </div>
</div>

<!-- ═══════════════════════ SEÇÃO 1 — RESUMO E CWV ════════════════════ -->
<div class="page-break"></div>
<h1>1. Resumo Executivo &amp; Diagnóstico Geral</h1>

<p>Este documento apresenta a auditoria técnica de engenharia de software e performance de conversão do site <strong><?= htmlspecialchars($r['dominio']) ?></strong>. A análise foi realizada com base nas métricas oficiais recomendadas pelo ecossistema do Google, visando atestar a viabilidade e a eficiência do tráfego pago e orgânico.</p>

<?php
$resGeral = strtoupper(trim($r['resultado_geral'] ?? 'CRÍTICO'));
$classeBox = match($resGeral) {
    'BOM' => 'box-ok',
    'MÉDIO' => 'box-warn',
    default => 'box-error',
};
$resultadoTexto = match($resGeral) {
    'BOM' => 'O site apresenta excelente índice de conformidade técnica, necessitando apenas de melhoria contínua.',
    'MÉDIO' => 'O site possui estabilidade parcial, porém apresenta gargalos e perdas constantes de eficiência comercial.',
    'RUIM' => 'ATENÇÃO CRÍTICA: Desempenho geral insatisfatório, gerando alto índice de rejeição de leads.',
    default => 'BLOQUEIO COMERCIAL GRAVE: O site falha nos requisitos de indexação e velocidade mínima.',
};
?>
<div class="<?= $classeBox ?>">
  <strong>DIAGNÓSTICO FINAL: <?= htmlspecialchars($r['resultado_geral']) ?></strong> — <?= $resultadoTexto ?>
</div>

<?php if (str_contains(strtolower($r['gtm_nota'] ?? ''), 'erro') || str_contains(strtolower($r['gtm_nota'] ?? ''), 'timeout')): ?>
<div class="box-error" style="background:#fff5f5; border-left:4px solid #e53e3e; padding:8pt; margin:6pt 0; border-radius:4px;">
  <strong>🚨 IMPEDIMENTO TÉCNICO DE PROCESSAMENTO (TIMEOUT):</strong><br>
  A tentativa de auditoria automatizada do Lighthouse falhou devido a um excesso de processamento e latência de scripts, gerando estouro de tempo limite (Timeout). Isso atesta um travamento severo de CPU que causa o abandono imediato de visitantes e punições severas no ranking do Google Ads.
</div>
<?php endif; ?>

<h3 style="margin-top:10pt;">Pontuações de Auditoria (Google PageSpeed / GTmetrix)</h3>
<table>
  <thead>
    <tr>
      <th style="width:30%;">Categoria</th>
      <th style="width:34%;">Ferramenta de Origem</th>
      <th style="width:18%; text-align:center;">Desktop</th>
      <th style="width:18%; text-align:center;">Mobile</th>
    </tr>
  </thead>
  <tbody>
    <?php
    $scores = [
      ['Performance Geral',    'PageSpeed Insights', $r['ps_performance_desktop'], $r['ps_performance_mobile']],
      ['SEO On-Page Técnico',  'PageSpeed Insights', $r['ps_seo_desktop'],          $r['ps_seo_mobile']],
      ['Acessibilidade de Interface', 'PageSpeed Insights', $r['ps_acessibilidade_desktop'], $r['ps_acessibilidade_mobile']],
      ['Melhores Práticas Web',  'PageSpeed Insights', $r['ps_boaspraticas_desktop'],  $r['ps_boaspraticas_mobile']],
      ['Performance Bruta',    'GTmetrix (Desktop)', ($r['gtm_nota'] === 'Erro (Lighthouse Timeout)' ? '⚠ Erro' : $r['gtm_nota']), '–'],
    ];
    
    $scoresFiltrados = [];
    foreach ($scores as $s) {
        $isGt = (str_contains(strtolower($s[1]), 'gtmetrix'));
        if (deveExibirLinha((string)$s[2], (string)$s[3], $isGt)) {
            $scoresFiltrados[] = $s;
        }
    }
    
    if (!empty($scoresFiltrados)):
      foreach ($scoresFiltrados as [$cat, $tool, $desk, $mob]):
        $dv = (string)($desk ?? '–');
        $mv = (string)($mob  ?? '–');
        $dv = $dv !== '' ? $dv : '–';
        $mv = $mv !== '' ? $mv : '–';
      ?>
      <tr>
        <td style="font-weight:bold;"><?= htmlspecialchars($cat) ?></td>
        <td style="color:#718096;"><?= htmlspecialchars($tool) ?></td>
        <?= score_cell($dv) ?>
        <?= score_cell($mv) ?>
      </tr>
      <?php endforeach; ?>
    <?php else: ?>
      <tr>
        <td colspan="4" align="center" style="color:#2E7D32; font-weight:bold; padding:15pt; font-size:9pt; background:#f0fff4;">
          ✓ Excelente Conformidade: Todas as pontuações do site encontram-se excelentes (acima de 85/100).
        </td>
      </tr>
    <?php endif; ?>
  </tbody>
</table>

<p class="small legenda" style="margin-bottom:8pt;">
  <span style="color:#2E7D32">■ 90–100 = Bom</span>
  <span style="color:#E65100">■ 50–89 = Intermediário</span>
  <span style="color:#D32F2F">■ 0–49 = Crítico</span>
</p>

<?php if ($r['obs_pagespeed']): ?>
<div class="box-warn" style="margin-bottom:8pt;"><strong>Anotação Técnica:</strong> <?= nl2br(htmlspecialchars($r['obs_pagespeed'])) ?></div>
<?php endif; ?>

<h3 style="margin-top:12pt; margin-bottom:6pt;">Diagnóstico Avançado de Infraestrutura & Segurança</h3>
<p>Coletamos automaticamente os parâmetros estruturais do site e servidores para mapear deficiências de segurança cibernética e entregabilidade de canais comerciais:</p>
<table style="margin-bottom:12pt;">
  <thead>
    <tr>
      <th style="width:25%;">Parâmetro de Auditoria</th>
      <th style="width:50%;">Resultado / Métrica Identificada</th>
      <th style="width:25%; text-align:center;">Status Técnico</th>
    </tr>
  </thead>
  <tbody>
    <!-- CMS -->
    <tr>
      <td style="font-weight:bold;">Plataforma / CMS</td>
      <td>Detecção de assinaturas de desenvolvimento e tecnologias no site inicial.</td>
      <td align="center" bgcolor="<?= ($r['auditoria_cms'] ?? 'Não Identificado') !== 'Não Identificado' ? '#EDF7EE' : '#FFF9E6' ?>" style="color:<?= ($r['auditoria_cms'] ?? 'Não Identificado') !== 'Não Identificado' ? '#2E7D32' : '#E65100' ?>; font-weight:bold;">
        <?= htmlspecialchars($r['auditoria_cms'] ?? 'Não Identificado') ?>
      </td>
    </tr>
    
    <!-- Hospedagem -->
    <?php
    $geo = !empty($r['auditoria_hospedagem']) ? json_decode($r['auditoria_hospedagem'], true) : null;
    ?>
    <tr>
      <td style="font-weight:bold;">Hospedagem & Geolocalização</td>
      <td>
        <?php if ($geo): ?>
          Provedor: <strong><?= htmlspecialchars($geo['provedor']) ?></strong><br>
          Local do Servidor: <strong><?= htmlspecialchars($geo['cidade']) ?>, <?= htmlspecialchars($geo['pais']) ?></strong> &bull; IP: <span style="font-family:monospace;"><?= htmlspecialchars($geo['ip']) ?></span>
        <?php else: ?>
          Dados de IP e servidores não mapeados.
        <?php endif; ?>
      </td>
      <td align="center" bgcolor="<?= $geo ? '#EDF7EE' : '#FDECEA' ?>" style="color:<?= $geo ? '#2E7D32' : '#D32F2F' ?>; font-weight:bold;">
        <?= $geo ? '✓ Mapeado' : '✗ Ausente' ?>
      </td>
    </tr>
    
    <!-- SSL / HTTPS -->
    <?php
    $seg = !empty($r['auditoria_seguranca']) ? json_decode($r['auditoria_seguranca'], true) : null;
    $segAtivos = 0;
    if ($seg) {
        foreach (['hsts', 'csp', 'x_frame', 'x_content', 'referrer'] as $c) {
            if (!empty($seg[$c])) $segAtivos++;
        }
    }
    ?>
    <tr>
      <td style="font-weight:bold;">Segurança HTTP & SSL</td>
      <td>
        <?php if ($seg): ?>
          Certificado SSL/HTTPS: <strong><?= $seg['ssl_ativo'] ? 'Ativo (Seguro)' : 'Inativo (Inseguro)' ?></strong><br>
          Nível de Proteção do Host: <strong><?= $segAtivos ?> de 5 cabeçalhos modernos ativos</strong>
        <?php else: ?>
          Verificação de segurança HTTP pendente.
        <?php endif; ?>
      </td>
      <td align="center" bgcolor="<?= ($seg && $seg['ssl_ativo'] && $segAtivos >= 2) ? '#EDF7EE' : (($seg && $seg['ssl_ativo']) ? '#FFF9E6' : '#FDECEA') ?>" style="color:<?= ($seg && $seg['ssl_ativo'] && $segAtivos >= 2) ? '#2E7D32' : (($seg && $seg['ssl_ativo']) ? '#E65100' : '#D32F2F') ?>; font-weight:bold;">
        <?= ($seg && $seg['ssl_ativo']) ? ($segAtivos >= 2 ? '✓ Forte' : '⚠ Médio') : '✗ Inseguro' ?>
      </td>
    </tr>
    
    <!-- DNS de E-mail (SPF / DMARC) -->
    <?php
    $dns = !empty($r['auditoria_dns']) ? json_decode($r['auditoria_dns'], true) : null;
    ?>
    <tr>
      <td style="font-weight:bold;">DNS de E-mail (SPAM/Fraud)</td>
      <td>
        <?php if ($dns): ?>
          Entrada SPF: <strong><?= $dns['spf_valido'] ? '✓ Configurado' : '✗ Ausente/Inválido' ?></strong><br>
          Entrada DMARC: <strong><?= $dns['dmarc_valido'] ? '✓ Configurado' : '✗ Ausente' ?></strong>
        <?php else: ?>
          Verificação de chaves DNS pendente.
        <?php endif; ?>
      </td>
      <td align="center" bgcolor="<?= ($dns && $dns['spf_valido'] && $dns['dmarc_valido']) ? '#EDF7EE' : (($dns && ($dns['spf_valido'] || $dns['dmarc_valido'])) ? '#FFF9E6' : '#FDECEA') ?>" style="color:<?= ($dns && $dns['spf_valido'] && $dns['dmarc_valido']) ? '#2E7D32' : (($dns && ($dns['spf_valido'] || $dns['dmarc_valido'])) ? '#E65100' : '#D32F2F') ?>; font-weight:bold;">
        <?= ($dns && $dns['spf_valido'] && $dns['dmarc_valido']) ? '✓ Seguro' : (($dns && ($dns['spf_valido'] || $dns['dmarc_valido'])) ? '⚠ Vulnerável' : '✗ Crítico') ?>
      </td>
    </tr>
  </tbody>
</table>

<h2 style="margin-top:10pt;">2. Core Web Vitals (Lab Metrics do Google)</h2>
<p>Os Core Web Vitals representam a experiência real do usuário em tempo de interação. Baixo desempenho nestas métricas inflaciona diretamente o custo de campanhas e degrada a indexação orgânica.</p>

<table>
  <thead>
    <tr>
      <th style="width:10%; text-align:center;">Métrica</th>
      <th style="width:34%;">Descrição e Nome Técnico</th>
      <th style="width:14%; text-align:center;">Referência</th>
      <th style="width:14%; text-align:center;">Desktop</th>
      <th style="width:14%; text-align:center;">Mobile</th>
      <th style="width:14%; text-align:center;">Status</th>
    </tr>
  </thead>
  <tbody>
    <?php
    $cwv_rows = [
      ['lcp',   'LCP',   'Largest Contentful Paint (Renderização)', '&lt; 2,5 s'],
      ['inp',   'INP',   'Interaction to Next Paint (Interatividade)', '&lt; 200 ms'],
      ['cls',   'CLS',   'Cumulative Layout Shift (Estabilidade Visual)', '&lt; 0,1'],
      ['fcp',   'FCP',   'First Contentful Paint (Tempo de Resposta)', '&lt; 1,8 s'],
      ['ttfb',  'TTFB',  'Time to First Byte (Tempo de Resposta do Host)', '&lt; 600 ms'],
      ['speed', 'Speed', 'Speed Index (Índice de Velocidade Visual)', '&lt; 3,4 s'],
    ];
    foreach ($cwv_rows as [$key, $sigla, $nome, $ref]):
      $status = $r["cwv_{$key}_status"] ?? 'Ruim';
      $desk   = cwv_val((string)($r["cwv_{$key}_desktop"] ?? ''));
      $mob    = cwv_val((string)($r["cwv_{$key}_mobile"]  ?? ''));
    ?>
    <tr>
      <td bgcolor="<?= $AZUL2 ?>" style="color:<?= $AZUL ?>;font-weight:bold;text-align:center;"><?= $sigla ?></td>
      <td style="font-size:8pt;"><?= $nome ?></td>
      <td style="color:#2E7D32;font-weight:bold;text-align:center;font-size:8pt;"><?= $ref ?></td>
      <td align="center" style="font-weight:bold;"><?= $desk ?></td>
      <td align="center" style="font-weight:bold;"><?= $mob ?></td>
      <td align="center" bgcolor="<?= bgStatus($status) ?>" style="color:<?= corStatus($status) ?>;font-weight:bold;"><?= htmlspecialchars($status) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<!-- ═══════════════════════ SEÇÃO 3 — PROBLEMAS E ADS ════════════════ -->
<div class="page-break"></div>
<h1>3. Análise de Gargalos Técnicos Mapeados</h1>
<p>Os seguintes gargalos estruturais de programação e acessibilidade foram detectados, exigindo intervenção especializada corporativa:</p>

<?php 
$bloquear = ($r['bloquear_plano'] ?? 0) == 1;
if (!empty($problemas)): 
?>
<table>
  <thead>
    <tr>
      <th style="width:62%">Gargalo Técnico Identificado</th>
      <th style="width:20%">Canal de Impacto</th>
      <th style="width:18%" align="center">Severidade</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($problemas as $prob): ?>
    <tr>
      <td style="line-height:1.4;"><?= formatarProblema($prob['problema'], $bloquear) ?></td>
      <td style="color:#718096; font-size:8pt;"><?= htmlspecialchars($prob['impacto']) ?></td>
      <td align="center"
          bgcolor="<?= bgPri($prob['prioridade']) ?>"
          style="color:<?= corPri($prob['prioridade']) ?>;font-weight:bold; font-size:8pt;">
        <?= htmlspecialchars($prob['prioridade']) ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php else: ?>
<div class="box-warn">Nenhum problema registrado no banco de dados.</div>
<?php endif; ?>

<h2 style="margin-top:10pt;">4. Impacto Estratégico no Tráfego Pago &amp; Conversão</h2>
<p>Abaixo estão detalhados os canais onde os gargalos técnicos mapeados exercem maior atrito comercial:</p>

<p><strong>1. Retorno sobre Investimento (Google Ads ROI):</strong><br>
O algoritmo do Google Ads avalia severamente a <em>Landing Page Experience</em>. Páginas com instabilidade visual recebem notas baixas no <strong>Quality Score (Índice de Qualidade)</strong>. Isso inflaciona diretamente o valor pago por clique (CPC) e reduz a entrega de anúncios, destruindo a lucratividade das campanhas.</p>

<p><strong>2. Rastreamento e Pixel de Metas (Data Layer):</strong><br>
A ausência de tags estruturadas de conversão impede que o algoritmo de lances inteligentes (Smart Bidding) funcione corretamente, resultando em desperdício de verba de mídia por falta de dados para otimização.</p>

<p><strong>3. Retenção de Tráfego Móvel (Mobile Friction):</strong><br>
Comportamentos instáveis no celular e lentidão excessiva elevam a taxa de rejeição silenciosa: o usuário clica no anúncio, mas desiste antes do carregamento completo do site. A verba é consumida pelo clique, sem chance real de conversão.</p>

<h3 style="margin-top:8pt;">Verificações Alternativas de Segurança e Políticas</h3>
<table>
  <thead>
    <tr>
      <th style="width:30%;">Verificação Executada</th>
      <th style="width:40%;">Tecnologia Utilizada</th>
      <th style="width:30%; text-align:center;">Resultado e Status</th>
    </tr>
  </thead>
  <tbody>
    <?php
    $adExp = trim($r['ad_experience_status'] ?? '');
    $adExpLabel = $adExp ?: 'Não verificado';
    $adExpCor   = $adExp ? corNota(str_contains(strtolower($adExp), 'passing') ? '95' : (str_contains(strtolower($adExp), 'warning') ? '60' : '30')) : '#718096';
    $adExpBg    = $adExp ? bgNota(str_contains(strtolower($adExp), 'passing') ? '95' : (str_contains(strtolower($adExp), 'warning') ? '60' : '30')) : '#f8fafc';

    $sb = trim($r['safe_browsing_status'] ?? '');
    $sbLabel = $sb ?: 'Não verificado';
    $sbCor = $sb ? corNota(str_contains(strtolower($sb), 'nenhuma') ? '95' : '30') : '#718096';
    $sbBg  = $sb ? bgNota(str_contains(strtolower($sb), 'nenhuma') ? '95' : '30') : '#f8fafc';

    $ap = trim($r['ads_policy_status'] ?? '');
    $apLabel = $ap ?: 'Não verificado';
    $apCor = $ap ? corNota(str_contains(strtolower($ap), 'sem restr') ? '95' : '30') : '#718096';
    $apBg  = $ap ? bgNota(str_contains(strtolower($ap), 'sem restr') ? '95' : '30') : '#f8fafc';
    ?>
    <tr>
      <td style="font-weight:bold;">Ad Experience Standards</td>
      <td>Google Search Console / Better Ads Standards</td>
      <td align="center" bgcolor="<?= $adExpBg ?>" style="color:<?= $adExpCor ?>;font-weight:bold;"><?= htmlspecialchars($adExpLabel) ?></td>
    </tr>
    <tr>
      <td style="font-weight:bold;">Google Safe Browsing</td>
      <td>Transparency Security Report (Filtro Anti-Malware/Phishing)</td>
      <td align="center" bgcolor="<?= $sbBg ?>" style="color:<?= $sbCor ?>;font-weight:bold;"><?= htmlspecialchars($sbLabel) ?></td>
    </tr>
    <tr>
      <td style="font-weight:bold;">Centro de Políticas Google Ads</td>
      <td>Auditoria Interna de Anúncios e Restrições de Domínio</td>
      <td align="center" bgcolor="<?= $apBg ?>" style="color:<?= $apCor ?>;font-weight:bold;"><?= htmlspecialchars($apLabel) ?></td>
    </tr>
  </tbody>
</table>

<?php
$temAlertaSeg = false;
$alertasHTML = '';

if ($adExp) {
    if (str_contains(strtolower($adExp), 'warning')) {
        $temAlertaSeg = true;
        $alertasHTML .= '<div style="background:#FFFDF5; border-left:4px solid #D69E2E; padding:8pt; margin-bottom:6pt; border-radius:4px;"><strong style="color:#B7791F; font-size:8.5pt;">⚠️ AD EXPERIENCE REPORT — STATUS DE ATENÇÃO:</strong><span style="display:block; font-size:8pt; color:#4a5568; margin-top:2px;">O Google identificou inconformidades e desvios de layout. Risco alto de bloqueio de anúncios se não for saneado imediatamente.</span></div>';
    } else if (str_contains(strtolower($adExp), 'failing')) {
        $temAlertaSeg = true;
        $alertasHTML .= '<div style="background:#FFF5F5; border-left:4px solid #E53E3E; padding:8pt; margin-bottom:6pt; border-radius:4px;"><strong style="color:#C53030; font-size:8.5pt;">🛑 AD EXPERIENCE REPORT — REPROVAÇÃO CRÍTICA:</strong><span style="display:block; font-size:8pt; color:#4a5568; margin-top:2px;">BLOQUEIO ATIVO DE ANÚNCIOS. O site foi banido pelo Google Search Console. Campanhas ativas não conseguem rodar tráfego para este domínio.</span></div>';
    }
}

if ($sb) {
    if (str_contains(strtolower($sb), 'parcialmente')) {
        $temAlertaSeg = true;
        $alertasHTML .= '<div style="background:#FFFDF5; border-left:4px solid #D69E2E; padding:8pt; margin-bottom:6pt; border-radius:4px;"><strong style="color:#B7791F; font-size:8.5pt;">⚠️ GOOGLE SAFE BROWSING — PARCIALMENTE PERIGOSO:</strong><span style="display:block; font-size:8pt; color:#4a5568; margin-top:2px;">Foram detectados trechos ou scripts nocivos parciais. Campanhas sofrem restrição de tráfego orgânico e pago.</span></div>';
    } else if (str_contains(strtolower($sb), 'lista negra') || str_contains(strtolower($sb), 'perigoso')) {
        $temAlertaSeg = true;
        $alertasHTML .= '<div style="background:#FFF5F5; border-left:4px solid #E53E3E; padding:8pt; margin-bottom:6pt; border-radius:4px;"><strong style="color:#C53030; font-size:8.5pt;">🛑 GOOGLE SAFE BROWSING — DOMÍNIO EM LISTA NEGRA:</strong><span style="display:block; font-size:8pt; color:#4a5568; margin-top:2px;">RISCO MÁXIMO DE SEGURANÇA. O domínio está categorizado como distribuidor de malware/phishing. O Chrome exibe uma tela de bloqueio vermelha impeditiva aos visitantes e o Google Ads cancela as veiculações e suspende as contas vinculadas.</span></div>';
    }
}

if ($ap) {
    if (str_contains(strtolower($ap), 'restrição')) {
        $temAlertaSeg = true;
        $alertasHTML .= '<div style="background:#FFFDF5; border-left:4px solid #D69E2E; padding:8pt; margin-bottom:6pt; border-radius:4px;"><strong style="color:#B7791F; font-size:8.5pt;">⚠️ GOOGLE ADS — ANÚNCIOS COM RESTRIÇÃO DE ALCANCE:</strong><span style="display:block; font-size:8pt; color:#4a5568; margin-top:2px;">Existem problemas técnicos ou de política limitando a entrega dos seus anúncios. O CPC encarece drasticamente e o ROI da campanha despenca.</span></div>';
    } else if (str_contains(strtolower($ap), 'reprovado')) {
        $temAlertaSeg = true;
        $alertasHTML .= '<div style="background:#FFF5F5; border-left:4px solid #E53E3E; padding:8pt; margin-bottom:6pt; border-radius:4px;"><strong style="color:#C53030; font-size:8.5pt;">🛑 GOOGLE ADS — ANÚNCIOS REPROVADOS POR POLÍTICA:</strong><span style="display:block; font-size:8pt; color:#4a5568; margin-top:2px;">Anúncios ativos foram recusados pelo robô do Google Ads devido a violações graves na página de destino. O canal de aquisição de vendas pago está interrompido.</span></div>';
    } else if (str_contains(strtolower($ap), 'suspensa')) {
        $temAlertaSeg = true;
        $alertasHTML .= '<div style="background:#FFF5F5; border-left:4px solid #E53E3E; padding:8pt; margin-bottom:6pt; border-radius:4px;"><strong style="color:#C53030; font-size:8.5pt;">🚨 GOOGLE ADS — CONTA SUSPENSA DE FORMA GRAVE:</strong><span style="display:block; font-size:8pt; color:#4a5568; margin-top:2px;">SUSPENSÃO MÁXIMA. Toda a estrutura de marketing de busca do cliente foi banida pelo Google Ads. Campanhas pausadas por tempo indeterminado até a readequação do site e aprovação da apelação técnica.</span></div>';
    }
}
?>

<?php if ($temAlertaSeg): ?>
  <div style="background:#FEE2E2; border:1px solid #FCA5A5; border-radius:6px; padding:10pt 12pt; margin-top:10pt; margin-bottom:12pt;">
    <h3 style="color:#991B1B; font-size:9.5pt; font-weight:bold; margin-top:0; margin-bottom:6pt; text-transform:uppercase;">Alerta Estratégico: Riscos Críticos de Segurança e Bloqueios Financeiros</h3>
    <p style="font-size:8pt; line-height:1.4; color:#7F1D1D; margin-bottom:8pt;">As falhas de conformidade identificadas abaixo agem como um gargalo destrutivo para a captação de clientes. Sites com restrições de segurança ou em desconformidade de anúncios perdem verba de mídia instantaneamente e correm o risco iminente de suspensão permanente da conta de marketing:</p>
    <?= $alertasHTML ?>
    <div style="margin-top:6pt; border-top:1px dashed #FCA5A5; padding-top:6pt; font-size:7.5pt; color:#7F1D1D; text-align:justify;">
      <strong>Recomendação Técnica:</strong> É imperativo proceder com o saneamento de segurança e readequação de layout de acordo com os manuais de política do Google. A equipe da <strong>Rajo Desenvolvimento</strong> está homologada para conduzir a auditoria fina de código e o protocolo de liberação junto aos times de suporte do Google.
    </div>
  </div>
<?php endif; ?>

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
<div class="page-break"></div>
<h1>Simulação de Impacto Financeiro em Anúncios (Google Ads)</h1>
<p>Abaixo apresentamos uma simulação técnica baseada no desempenho mobile do site (velocidade de carregamento móvel), revelando o prejuízo estimado em mídia paga devido à lentidão e rejeição de tráfego:</p>

<div style="background:#FFF5F5; border-left:5px solid #E53E3E; padding:10pt 12pt; margin-bottom:12pt; border-radius:6px;">
  <table width="100%" style="border:none; margin:0;">
    <tr style="border:none;">
      <td style="border:none; padding:0; width:70%; vertical-align:top;">
        <strong style="color:#C53030; font-size:10.5pt; text-transform:uppercase;">🚨 Alerta de Desperdício de Verba de Marketing</strong><br>
        <p style="margin:4pt 0 0 0; font-size:8.5pt; color:#2d3748; line-height:1.45;">
          Devido ao tempo de carregamento insatisfatório nos dispositivos móveis (nota de performance móvel de <strong><?= $P ?>/100</strong>), estima-se que <strong><?= round($D) ?>%</strong> do seu orçamento de anúncios esteja sendo desperdiçado em cliques de usuários que abandonam o site antes do carregamento da página.
        </p>
      </td>
      <td style="border:none; padding:0; width:30%; text-align:right; vertical-align:middle;">
        <div style="background:#fff; border:2px solid #E53E3E; padding:6pt 8pt; border-radius:6px; text-align:center;">
          <span style="font-size:7pt; color:#718096; text-transform:uppercase; font-weight:bold;">Perda Mensal</span><br>
          <strong style="font-size:12pt; color:#E53E3E; font-weight:900;">R$ <?= number_format($prejuizo, 2, ',', '.') ?></strong>
        </div>
      </td>
    </tr>
  </table>
</div>

<table style="width:100%; border-collapse:collapse; margin-bottom:12pt;">
  <thead>
    <tr>
      <th colspan="2" bgcolor="<?= $AZUL ?>" style="color:#fff; font-weight:bold; font-size:8.5pt; text-transform:uppercase; padding:5pt 6pt;">Métricas de Simulação de Ads</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td style="width:50%; font-weight:bold; font-size:8.5pt;">Nicho de Atuação Declarado:</td>
      <td style="font-size:8.5pt; color:#2d3748;"><?= htmlspecialchars($nicho_nome) ?></td>
    </tr>
    <tr>
      <td style="font-weight:bold; font-size:8.5pt;">Investimento Mensal Estimado em Anúncios:</td>
      <td style="font-size:8.5pt; font-weight:bold; color:<?= $AZUL ?>;">R$ <?= number_format($inv, 2, ',', '.') ?> / mês</td>
    </tr>
    <tr>
      <td style="font-weight:bold; font-size:8.5pt;">Custo por Clique (CPC) Médio Recomendado:</td>
      <td style="font-size:8.5pt; color:#2d3748;">R$ <?= number_format($cpc, 2, ',', '.') ?></td>
    </tr>
    <tr>
      <td style="font-weight:bold; font-size:8.5pt;">Média de Cliques Adquiridos Mensalmente:</td>
      <td style="font-size:8.5pt; color:#2d3748;"><?= number_format(round($inv / $cpc)) ?> cliques</td>
    </tr>
    <tr bgcolor="#FDECEA">
      <td style="font-weight:bold; font-size:8.5pt; color:#C53030;">Perda Estimada por Lentidão Mobile (<?= round($D) ?>%):</td>
      <td style="font-size:8.5pt; font-weight:bold; color:#C53030;">R$ <?= number_format($prejuizo, 2, ',', '.') ?> / mês</td>
    </tr>
    <tr bgcolor="#FDECEA">
      <td style="font-weight:bold; font-size:8.5pt; color:#C53030;">Cliques Desperdiçados (Sem Carregamento):</td>
      <td style="font-size:8.5pt; font-weight:bold; color:#C53030;"><?= number_format($cliquesPerdidos) ?> cliques perdidos / mês</td>
    </tr>
    <tr bgcolor="#EDF7EE">
      <td style="font-weight:bold; font-size:8.5pt; color:#2E7D32;">Orçamento Efetivamente Aproveitado:</td>
      <td style="font-size:8.5pt; font-weight:bold; color:#2E7D32;">R$ <?= number_format($aproveitado, 2, ',', '.') ?> / mês</td>
    </tr>
  </tbody>
</table>

<p style="font-size:8.2pt; line-height:1.4; text-align:justify; color:#4a5568;">
  <strong>Conclusão Técnica sobre Anúncios:</strong> O algoritmo do Google Ads avalia a qualidade da sua landing page como um dos fatores mais pesados no Índice de Qualidade do anúncio. Sites lentos são punidos com um CPC (Custo por Clique) muito mais caro do que o dos seus concorrentes diretos. Ao otimizar o tempo de carregamento para a faixa verde (acima de 90), além de eliminar a perda imediata de R$ <?= number_format($prejuizo, 2, ',', '.') ?> por abandono de página, o Índice de Qualidade do seu domínio tende a subir, permitindo que a <strong>Rajo</strong> reduza o seu CPC real e gere muito mais cliques qualificados pelo mesmo valor investido.
</p>
<?php endif; ?>

<!-- ═══════════════════════ SEÇÃO 5 — PLANO E CONCLUSÃO ════════════════ -->
<div class="page-break"></div>
<h1>5. Planejamento Tático e Resolução Técnica</h1>
<p>Abaixo estruturamos a matriz de responsabilidades e as intervenções necessárias no código e arquitetura da landing page:</p>

<?php if ($bloquear): ?>
  <div class="box-warn" style="background:#fffdf5; border-left:4px solid #d97706; padding:10pt; margin-bottom:12pt; border-radius:4px;">
    <strong style="color:#b45309; font-size:9.5pt;">🔒 CRONOGRAMA TÉCNICO DE RESOLUÇÃO RESTRITO (MÉTODO PROTEGIDO)</strong><br>
    <p style="margin:4pt 0 0 0; font-size:8.5pt; color:#4a5568; line-height:1.4;">
      Para resguardar nossa propriedade intelectual de engenharia e garantir a correta homologação do site junto ao Google, o detalhamento das diretivas de código e parâmetros exatos de otimização estão <strong>temporariamente reservados e protegidos comercialmente</strong>.
      <br><br>
      As tarefas macro estão listadas abaixo com a respectiva sinalização de complexidade. Contrate os serviços técnicos especializados da <strong>Rajo Desenvolvimento</strong> para destravar o roteiro completo e executar a regularização imediata do site.
    </p>
  </div>
<?php endif; ?>

<?php if (!empty($acoes)): ?>
<table>
  <thead>
    <tr>
      <th style="width:55%">Intervenção Estrutural Sugerida</th>
      <th style="width:23%">Responsabilidade Executiva</th>
      <th style="width:22%" align="center">Prazo Recomendado</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($acoes as $acao): ?>
    <tr>
      <td style="font-weight:500; font-size:8pt; line-height:1.4;"><?= ofuscarAcao($acao['acao'], $bloquear) ?></td>
      <td style="color:#4a5568; font-size:8pt;"><?= htmlspecialchars($acao['responsavel']) ?></td>
      <td align="center" bgcolor="<?= $AZUL2 ?>" style="color:<?= $AZUL ?>; font-weight:bold; font-size:8pt;">
        <?= htmlspecialchars($acao['prazo']) ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php else: ?>
<div class="box-warn">Nenhuma ação registrada no plano de ação.</div>
<?php endif; ?>

<h2 style="margin-top:10pt;">6. Parecer Técnico e Conclusão Geral</h2>

<?php if ($r['conclusao']): ?>
  <?php foreach (explode("\n\n", $r['conclusao']) as $para): ?>
  <p style="text-align:justify; line-height:1.45; margin-bottom:6pt; font-size:8.5pt; color:#2d3748;"><?= nl2br(htmlspecialchars(trim($para))) ?></p>
  <?php endforeach; ?>
<?php else: ?>
<p>Sem conclusão técnico-comercial registrada.</p>
<?php endif; ?>

<div style="margin-top:20pt; border-top:1px solid #edf2f7; padding-top:10pt; text-align:center;">
  <p style="font-size:9.5pt; color:<?= $AZUL ?>; font-weight:bold; margin-bottom:2pt;">
    <?= htmlspecialchars($r['analista']) ?> &bull; Rajo Desenvolvimento
  </p>
  <p style="font-size:7.5pt; color:#a0aec0; letter-spacing:0.5px; text-transform:uppercase;">
    rajo.com.br &bull; contato@rajo.com.br &bull; Engenharia de Conversão e Otimização Avançada
  </p>
</div>

</body>
</html>
<?php
$html = ob_get_clean();

// ─── Gerar PDF com mPDF ───────────────────────────────────────
$mpdf = new \Mpdf\Mpdf([
    'mode'        => 'utf-8',
    'format'      => 'A4',
    'margin_top'  => 15,
    'margin_right'=> 15,
    'margin_bottom'=> 20,
    'margin_left' => 15,
    'margin_header'=> 8,
    'margin_footer'=> 8,
    'default_font' => 'arial',
    'tempDir'     => sys_get_temp_dir() . '/mpdf',
]);

$screenshot_local = $r['screenshot_path'] ?? null;
if ($screenshot_local && file_exists(__DIR__ . '/' . $screenshot_local)) {
    $mpdf->SetWatermarkImage(__DIR__ . '/' . $screenshot_local, 0.05, 'F');
    $mpdf->showWatermarkImage = true;
}

$mpdf->SetHTMLHeader('
  <table width="100%" style="border-bottom:1px solid ' . $AZUL . ';padding-bottom:4px;border:none;">
    <tr>
      <td style="font-size:8.5pt;color:' . $AZUL . ';font-weight:bold;border:none;padding:0;">RAJO</td>
      <td style="font-size:8pt;color:#718096;text-align:right;border:none;padding:0;">
        Diagnóstico Técnico — ' . htmlspecialchars($r['cliente']) . '
      </td>
    </tr>
  </table>
');
$mpdf->SetHTMLFooter('
  <table width="100%" style="border-top:1px solid #edf2f7;padding-top:4px;border:none;">
    <tr>
      <td style="font-size:7.5pt;color:#a0aec0;border:none;padding:0;">rajo.com.br &bull; Confidencial</td>
      <td style="font-size:7.5pt;color:#a0aec0;text-align:right;border:none;padding:0;">Página {PAGENO} de {nbpg}</td>
    </tr>
  </table>
');

$mpdf->WriteHTML($html);

$filename = 'Diagnostico_' . preg_replace('/[^a-zA-Z0-9]/', '_', $r['cliente']) . '_' . date('Ymd', strtotime($r['data_relatorio'])) . '.pdf';
$mpdf->Output($filename, 'I');
