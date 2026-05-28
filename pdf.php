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
