<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/vendor/autoload.php';

// Acesso: analista logado (dono ou master) via ?id=, ou cliente final via ?t=TOKEN
[$r, $acesso_publico] = carregar_relatorio_autorizado();

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

    $partes = explode('. ', $textoFormatado);
    $principal = $partes[0];

    return $principal . " <span style='background-color:#555;color:#555;border-radius:2px;font-family:monospace;padding:0 3px;'>████████████████████</span>";
}

// Traduz problema técnico para linguagem de negócio
function impactoNegocio(string $problema, string $impacto): string {
    $p = mb_strtolower($problema);
    if (str_contains($p, 'performance') || str_contains($p, 'lento') || str_contains($p, 'velocidade') || str_contains($p, 'ttfb') || str_contains($p, 'carregamento')) {
        return 'Visitantes abandonam o site antes de ver sua oferta, desperdiçando investimento em anúncios.';
    }
    if (str_contains($p, 'meta') || str_contains($p, 'seo') || str_contains($p, 'title') || str_contains($p, 'description')) {
        return 'O Google não consegue entender seu site corretamente, reduzindo sua visibilidade nas buscas.';
    }
    if (str_contains($p, 'analytics') || str_contains($p, 'gtm') || str_contains($p, 'tag manager')) {
        return 'Sem dados de conversão, é impossível medir o retorno real dos seus anúncios.';
    }
    if (str_contains($p, 'pixel') || str_contains($p, 'facebook') || str_contains($p, 'remarketing')) {
        return 'Você perde a capacidade de impactar novamente visitantes que não converteram.';
    }
    if (str_contains($p, 'ssl') || str_contains($p, 'https') || str_contains($p, 'segurança')) {
        return 'Visitantes veem alertas de segurança e desconfiam do site, prejudicando conversões.';
    }
    if (str_contains($p, 'mobile') || str_contains($p, 'responsivo') || str_contains($p, 'celular')) {
        return 'Mais de 70% das visitas vêm do celular — problemas móveis afetam a maioria dos seus clientes.';
    }
    if (str_contains($p, 'layout shift') || str_contains($p, 'cls')) {
        return 'O conteúdo se move durante o carregamento, frustrando o usuário e aumentando a rejeição.';
    }
    return !empty($impacto) ? htmlspecialchars($impacto) : 'Prejudica a experiência do usuário e o desempenho nas buscas.';
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

// O override via GET (?plano=) só vale para o analista logado.
// Quem acessa pelo link público recebe sempre a configuração salva no relatório.
$plano_get = $acesso_publico ? null : ($_GET['plano'] ?? null);
if ($plano_get === 'oculto') {
    $bloquear = true;
} elseif ($plano_get === 'liberado') {
    $bloquear = false;
} else {
    $bloquear = (int)($r['bloquear_plano'] ?? 0) === 1;
}

if ($tipo_relatorio === 'compacto') {
    // ── Pré-calcular dados compacto ──
    $geo_c  = !empty($r['auditoria_hospedagem']) ? json_decode($r['auditoria_hospedagem'], true) : null;
    $seg_c  = !empty($r['auditoria_seguranca'])  ? json_decode($r['auditoria_seguranca'],  true) : null;
    $dns_c  = !empty($r['auditoria_dns'])        ? json_decode($r['auditoria_dns'],         true) : null;
    $inv_c  = (float)($r['ads_investimento'] ?? 0);
    $cpc_c  = (float)($r['ads_cpc'] ?? 0);
    $temAds_c = $inv_c > 0 && $cpc_c > 0;
    if ($temAds_c) {
        $P_c = (int)($r['ps_performance_mobile'] ?? 35);
        $D_c = $P_c >= 90 ? 3 : ($P_c >= 50 ? 30 - 0.3 * $P_c : 65 - 0.7 * $P_c);
        $D_c = max(0, min(100, $D_c));
        $prejuizo_c = $inv_c * ($D_c / 100);
        $cliques_c  = $cpc_c > 0 ? round($prejuizo_c / $cpc_c) : 0;
    }

    $adExpC = trim($r['ad_experience_status'] ?? '');
    $sbC    = trim($r['safe_browsing_status'] ?? '');
    $apC    = trim($r['ads_policy_status'] ?? '');

    // Status geral
    $resC = strtoupper(trim($r['resultado_geral'] ?? 'CRÍTICO'));
    if ($resC === 'BOM')       { $stCor = '#2E7D32'; $stBg = '#EDF7EE'; $stBorder = '#86efac'; $stIcon = '✓'; }
    elseif ($resC === 'MÉDIO') { $stCor = '#E65100'; $stBg = '#FFF9E6'; $stBorder = '#fde68a'; $stIcon = '!'; }
    else                       { $stCor = '#D32F2F'; $stBg = '#FDECEA'; $stBorder = '#fca5a5'; $stIcon = '✗'; }

    // Alerta ads
    $alertaAds = '';
    $alertaAdsCor = '';
    if (str_contains(strtolower($adExpC), 'failing') || (str_contains(strtolower($sbC), 'lista negra') || str_contains(strtolower($sbC), 'perigoso')) || str_contains(strtolower($apC), 'suspensa') || str_contains(strtolower($apC), 'reprovado')) {
        $alertaAds = 'BLOQUEIO DE ANUNCIOS DETECTADO'; $alertaAdsCor = '#D32F2F';
    } elseif (str_contains(strtolower($adExpC), 'warning') || str_contains(strtolower($sbC), 'parcialmente') || str_contains(strtolower($apC), 'restrição')) {
        $alertaAds = 'ALERTA: CONFORMIDADE ADS'; $alertaAdsCor = '#E65100';
    }

    ob_start();
    // Pré-montar score cards para ter contagem antes do HTML
    $metricas_c = [];
    foreach ([
        ['Perf.',     'ps_performance_desktop',  'ps_performance_mobile'],
        ['SEO',       'ps_seo_desktop',           'ps_seo_mobile'],
        ['Práticas',  'ps_boaspraticas_desktop',  'ps_boaspraticas_mobile'],
        ['Acesso',    'ps_acessibilidade_desktop', 'ps_acessibilidade_mobile'],
    ] as [$lbl, $fd, $fm]) {
        $dsk = (string)($r[$fd] ?? '');
        $mob = (string)($r[$fm] ?? '');
        $val = $dsk !== '' ? $dsk : $mob;
        if (!is_numeric($val)) continue;
        $metricas_c[] = [$lbl, $dsk, $mob, (int)$val];
    }
    $temGtm_c = !empty($r['gtm_nota']) && is_numeric($r['gtm_nota']);
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: Arial, sans-serif; font-size:7.8pt; color:#1e293b; line-height:1.4; background:#fff; }
  /* Tabelas com bordas visíveis */
  table  { border-collapse:collapse; }
  /* Tabelas de layout (sem bordas) */
  .lt    { border-collapse:collapse; width:100%; }
  .lt td { border:none; padding:0; }
  /* Tabelas de dados */
  .dt    { border-collapse:collapse; width:100%; }
  .dt th { background:<?= $AZUL ?>; color:#fff; font-weight:bold; font-size:7pt; border:1px solid <?= $AZUL ?>; text-transform:uppercase; padding:3pt 5pt; }
  .dt td { border:1px solid #e2e8f0; font-size:7pt; padding:3pt 5pt; color:#334155; }
  .dt tbody tr:nth-child(even) td { background:#f8fafc; }
  /* Painel */
  .panel  { border:1px solid #e2e8f0; border-radius:6px; padding:6pt 8pt; background:#f8fafc; }
  .ptitle { font-size:7pt; font-weight:bold; color:<?= $AZUL ?>; text-transform:uppercase; letter-spacing:0.4px; margin-bottom:4pt; padding-bottom:3pt; border-bottom:1px solid #e2e8f0; }
</style>
</head>
<body>

<!-- ══ CABEÇALHO ══ -->
<table class="lt" bgcolor="<?= $AZUL ?>" style="padding:7pt 12pt; border-radius:0;">
  <tr>
    <td width="55%" style="padding:7pt 0 7pt 12pt; vertical-align:middle;">
      <?php if (!empty($r['logo_cliente'])): ?>
        <img src="<?= htmlspecialchars($r['logo_cliente']) ?>" style="max-height:18pt; max-width:90pt;" /><br>
      <?php else: ?>
        <span style="font-size:16pt; font-weight:900; color:#fff; letter-spacing:2px;">RAJO</span><br>
      <?php endif; ?>
      <span style="font-size:6.5pt; color:rgba(255,255,255,0.7); text-transform:uppercase; letter-spacing:0.8px;">Diagnóstico Estratégico de SEO, Performance e Conversão</span>
    </td>
    <td style="text-align:right; font-size:7pt; color:rgba(255,255,255,0.85); padding:7pt 12pt 7pt 0; vertical-align:middle;">
      <strong style="color:#fff; font-size:8pt;"><?= htmlspecialchars($r['cliente']) ?></strong><br>
      <?= htmlspecialchars($r['dominio']) ?><br>
      <?= date('d/m/Y', strtotime($r['data_relatorio'])) ?>
    </td>
  </tr>
</table>

<!-- ══ LINHA 1: STATUS + SCORE CARDS ══ -->
<table class="lt" style="margin:5pt 0 4pt; table-layout:fixed;">
  <tr>

    <!-- Status geral -->
    <td width="26mm" style="padding-right:3mm; vertical-align:top;">
      <div style="background:<?= $stBg ?>; border:1.5px solid <?= $stBorder ?>; border-radius:6px; padding:5pt 4pt; text-align:center;">
        <div style="font-size:20pt; font-weight:900; color:<?= $stCor ?>; line-height:1;"><?= $stIcon ?></div>
        <div style="font-size:7pt; font-weight:bold; color:<?= $stCor ?>; text-transform:uppercase; margin-top:2pt;"><?= htmlspecialchars($r['resultado_geral']) ?></div>
        <div style="font-size:6pt; color:#64748b; margin-top:1pt;">Status Geral</div>
      </div>
    </td>

    <!-- Score cards -->
    <?php foreach ($metricas_c as [$lbl, $dsk, $mob, $n]):
      $cor = $n >= 90 ? '#2E7D32' : ($n >= 50 ? '#E65100' : '#D32F2F');
      $bg  = $n >= 90 ? '#EDF7EE' : ($n >= 50 ? '#FFF9E6' : '#FDECEA');
    ?>
    <td width="24mm" style="padding:0 1.5mm; vertical-align:top;">
      <div style="background:<?= $bg ?>; border:1px solid <?= $cor ?>; border-radius:6px; padding:5pt 3pt; text-align:center;">
        <div style="font-size:16pt; font-weight:900; color:<?= $cor ?>; line-height:1.1;"><?= $n ?></div>
        <div style="background:#e2e8f0; border-radius:3px; height:4pt; margin:3pt 2pt;"><div style="background:<?= $cor ?>; width:<?= max(4,$n) ?>%; height:4pt; border-radius:3px;"></div></div>
        <div style="font-size:6pt; font-weight:bold; color:<?= $cor ?>; text-transform:uppercase; margin-top:2pt;"><?= $lbl ?></div>
        <?php if ($dsk !== '' && $mob !== '' && $dsk !== $mob): ?>
          <div style="font-size:5.5pt; color:#94a3b8; margin-top:1pt;">D:<?= $dsk ?> M:<?= $mob ?></div>
        <?php endif; ?>
      </div>
    </td>
    <?php endforeach; ?>

    <!-- GTmetrix -->
    <?php if ($temGtm_c): $gtn = (int)$r['gtm_nota']; $gtCor = $gtn >= 90 ? '#2E7D32' : ($gtn >= 50 ? '#E65100' : '#D32F2F'); $gtBg = $gtn >= 90 ? '#EDF7EE' : ($gtn >= 50 ? '#FFF9E6' : '#FDECEA'); ?>
    <td width="24mm" style="padding:0 1.5mm; vertical-align:top;">
      <div style="background:<?= $gtBg ?>; border:1px solid <?= $gtCor ?>; border-radius:6px; padding:5pt 3pt; text-align:center;">
        <div style="font-size:16pt; font-weight:900; color:<?= $gtCor ?>; line-height:1.1;"><?= $gtn ?></div>
        <div style="background:#e2e8f0; border-radius:3px; height:4pt; margin:3pt 2pt;"><div style="background:<?= $gtCor ?>; width:<?= max(4,$gtn) ?>%; height:4pt; border-radius:3px;"></div></div>
        <div style="font-size:6pt; font-weight:bold; color:<?= $gtCor ?>; text-transform:uppercase; margin-top:2pt;">GTmetrix</div>
      </div>
    </td>
    <?php endif; ?>

    <!-- Ads loss -->
    <?php if ($temAds_c && $D_c > 5): ?>
    <td width="28mm" style="padding-left:3mm; vertical-align:top;">
      <div style="background:#fff5f5; border:1.5px solid #fca5a5; border-radius:6px; padding:5pt 4pt; text-align:center;">
        <div style="font-size:11pt; font-weight:900; color:#dc2626; line-height:1.15;">R$<?= number_format($prejuizo_c, 0, ',', '.') ?></div>
        <div style="font-size:5.5pt; font-weight:bold; color:#dc2626; text-transform:uppercase; margin-top:2pt;">Perda / mês</div>
        <div style="font-size:5.5pt; color:#718096; margin-top:2pt;"><?= round($D_c) ?>% desperd. &bull; <?= number_format($cliques_c) ?> cliques</div>
      </div>
    </td>
    <?php endif; ?>

  </tr>
</table>

<!-- ══ LINHA 2: OPORTUNIDADES + INFRAESTRUTURA ══ -->
<table class="lt" style="margin-bottom:4pt; table-layout:fixed;">
  <tr>

    <!-- OPORTUNIDADES -->
    <td width="108mm" style="padding-right:4mm; vertical-align:top;">
      <div class="panel">
        <div class="ptitle">Principais Oportunidades Identificadas</div>
        <?php if (!empty($problemas)):
          $cnt = 0;
          foreach ($problemas as $p):
            $cnt++; if ($cnt > 4) break;
            $pri    = strtolower(trim($p['prioridade'] ?? 'alta'));
            $priCor = $pri === 'alta' ? '#D32F2F' : ($pri === 'média' ? '#E65100' : '#2E7D32');
            $priBg  = $pri === 'alta' ? '#FDECEA' : ($pri === 'média' ? '#FFF9E6' : '#EDF7EE');
            $priLbl = $pri === 'alta' ? 'ALTA' : ($pri === 'média' ? 'MED' : 'BAI');
            // Truncar texto do problema para não estourar a célula
            $textoProb = formatarProblema($p['problema'], $bloquear);
        ?>
        <table class="lt" style="margin:0; padding:3pt 0; border-bottom:1px dashed #e2e8f0; <?= $cnt === min(count($problemas),4) ? 'border-bottom:none;' : '' ?>">
          <tr>
            <td width="8pt" style="vertical-align:top; color:<?= $priCor ?>; font-size:9pt; font-weight:bold; padding-top:1pt;">&#9658;</td>
            <td style="vertical-align:top; font-size:7pt; line-height:1.35; color:#334155; padding:0 4pt;">
              <?= $textoProb ?>
              <span style="font-size:6pt; color:#94a3b8;"> — <?= htmlspecialchars(mb_strimwidth($p['impacto'] ?? '', 0, 35, '…')) ?></span>
            </td>
            <td width="20pt" style="vertical-align:top; text-align:right; white-space:nowrap;">
              <span style="background:<?= $priBg ?>; color:<?= $priCor ?>; border:1px solid <?= $priCor ?>; font-size:5.5pt; font-weight:bold; padding:1pt 3pt; border-radius:3px;"><?= $priLbl ?></span>
            </td>
          </tr>
        </table>
        <?php endforeach;
        else: ?>
          <div style="padding:8pt; text-align:center; font-size:7pt; color:#2E7D32; font-weight:bold;">&#10003; Nenhuma falha critica identificada</div>
        <?php endif; ?>
      </div>
    </td>

    <!-- INFRAESTRUTURA & SEGURANÇA -->
    <td width="78mm" style="vertical-align:top;">
      <div class="panel">
        <div class="ptitle">Infraestrutura &amp; Segurança</div>

        <?php
        // Helper de badge inline (sem classe, 100% inline para mPDF)
        function badge_c(string $txt, string $bg, string $cor, string $border): string {
            return "<span style='background:{$bg};color:{$cor};border:1px solid {$border};font-size:6.5pt;font-weight:bold;padding:1pt 4pt;border-radius:3px;'>{$txt}</span>";
        }
        ?>

        <!-- CMS -->
        <?php if (!empty($r['auditoria_cms'])): ?>
        <table class="lt" style="margin:0; padding:2.5pt 0; border-bottom:1px dashed #e2e8f0;">
          <tr>
            <td style="font-size:6.8pt; color:#64748b; width:55pt;">Plataforma</td>
            <td style="text-align:right;">
              <?= badge_c(htmlspecialchars($r['auditoria_cms']), $r['auditoria_cms'] !== 'Não Identificado' ? '#dcfce7' : '#fef3c7', $r['auditoria_cms'] !== 'Não Identificado' ? '#166534' : '#92400e', $r['auditoria_cms'] !== 'Não Identificado' ? '#bbf7d0' : '#fde68a') ?>
            </td>
          </tr>
        </table>
        <?php endif; ?>

        <!-- Hospedagem -->
        <?php if ($geo_c): ?>
        <table class="lt" style="margin:0; padding:2.5pt 0; border-bottom:1px dashed #e2e8f0;">
          <tr>
            <td style="font-size:6.8pt; color:#64748b; width:55pt;">Hospedagem</td>
            <td style="text-align:right; font-size:7pt; font-weight:bold; color:#1e293b;">
              <?= htmlspecialchars(mb_strimwidth($geo_c['provedor'], 0, 22, '…')) ?>
              <span style="font-weight:normal; color:#94a3b8; font-size:6.5pt;">(<?= htmlspecialchars($geo_c['pais']) ?>)</span>
            </td>
          </tr>
        </table>
        <?php endif; ?>

        <!-- SSL -->
        <?php if ($seg_c): $ativos_c = 0; foreach (['hsts','csp','x_frame','x_content','referrer'] as $ck) { if (!empty($seg_c[$ck])) $ativos_c++; } ?>
        <table class="lt" style="margin:0; padding:2.5pt 0; border-bottom:1px dashed #e2e8f0;">
          <tr>
            <td style="font-size:6.8pt; color:#64748b; width:55pt;">SSL / HTTPS</td>
            <td style="text-align:right;">
              <?= badge_c($seg_c['ssl_ativo'] ? '&#10003; Ativo' : '&#10007; Inativo', $seg_c['ssl_ativo'] ? '#dcfce7' : '#fee2e2', $seg_c['ssl_ativo'] ? '#166534' : '#991b1b', $seg_c['ssl_ativo'] ? '#bbf7d0' : '#fca5a5') ?>
              <span style="font-size:6pt; color:#94a3b8;"> Hdrs: <?= $ativos_c ?>/5</span>
            </td>
          </tr>
        </table>
        <?php endif; ?>

        <!-- DNS -->
        <?php if ($dns_c): ?>
        <table class="lt" style="margin:0; padding:2.5pt 0; <?= ($adExpC || $sbC || $apC) ? 'border-bottom:1px dashed #e2e8f0;' : '' ?>">
          <tr>
            <td style="font-size:6.8pt; color:#64748b; width:55pt;">E-mail (DNS)</td>
            <td style="text-align:right;">
              <?= badge_c($dns_c['spf_valido'] ? '&#10003; SPF' : '&#10007; SPF', $dns_c['spf_valido'] ? '#dcfce7' : '#fee2e2', $dns_c['spf_valido'] ? '#166534' : '#991b1b', $dns_c['spf_valido'] ? '#bbf7d0' : '#fca5a5') ?>
              &nbsp;<?= badge_c($dns_c['dmarc_valido'] ? '&#10003; DMARC' : '&#10007; DMARC', $dns_c['dmarc_valido'] ? '#dcfce7' : '#fee2e2', $dns_c['dmarc_valido'] ? '#166534' : '#991b1b', $dns_c['dmarc_valido'] ? '#bbf7d0' : '#fca5a5') ?>
            </td>
          </tr>
        </table>
        <?php endif; ?>

        <!-- Google Ads -->
        <?php if ($adExpC || $sbC || $apC):
          $ad_ok = $adExpC && str_contains(strtolower($adExpC), 'passing');
          $sb_ok = $sbC    && str_contains(strtolower($sbC), 'nenhuma');
          $ap_ok = $apC    && str_contains(strtolower($apC), 'sem restr');
        ?>
        <table class="lt" style="margin:0; padding:2.5pt 0;">
          <tr>
            <td style="font-size:6.8pt; color:#64748b; width:55pt;">Google Ads</td>
            <td style="text-align:right; font-size:6pt;">
              <?php if ($adExpC): echo badge_c('AdExp:'.($ad_ok?'OK':(str_contains(strtolower($adExpC),'warning')?'WARN':'FAIL')), $ad_ok?'#dcfce7':(str_contains(strtolower($adExpC),'warning')?'#fef3c7':'#fee2e2'), $ad_ok?'#166534':(str_contains(strtolower($adExpC),'warning')?'#92400e':'#991b1b'), $ad_ok?'#bbf7d0':(str_contains(strtolower($adExpC),'warning')?'#fde68a':'#fca5a5')); endif; ?>
              <?php if ($sbC): echo ' '.badge_c('SB:'.($sb_ok?'OK':'FAIL'), $sb_ok?'#dcfce7':'#fee2e2', $sb_ok?'#166534':'#991b1b', $sb_ok?'#bbf7d0':'#fca5a5'); endif; ?>
              <?php if ($apC): echo ' '.badge_c('Pol:'.($ap_ok?'OK':(str_contains(strtolower($apC),'restrição')?'WARN':'FAIL')), $ap_ok?'#dcfce7':(str_contains(strtolower($apC),'restrição')?'#fef3c7':'#fee2e2'), $ap_ok?'#166534':(str_contains(strtolower($apC),'restrição')?'#92400e':'#991b1b'), $ap_ok?'#bbf7d0':(str_contains(strtolower($apC),'restrição')?'#fde68a':'#fca5a5')); endif; ?>
            </td>
          </tr>
        </table>
        <?php if ($alertaAds): ?>
          <div style="margin-top:3pt; background:<?= $alertaAdsCor === '#D32F2F' ? '#fee2e2' : '#fef3c7' ?>; border:1px solid <?= $alertaAdsCor === '#D32F2F' ? '#fca5a5' : '#fde68a' ?>; border-radius:4px; padding:2pt 4pt; color:<?= $alertaAdsCor ?>; font-weight:bold; font-size:6pt; text-align:center; text-transform:uppercase;"><?= $alertaAds ?></div>
        <?php endif; ?>
        <?php endif; ?>

      </div>
    </td>
  </tr>
</table>

<!-- ══ PLANO DE AÇÃO ══ -->
<?php if (!empty($acoes)): ?>
<div class="panel" style="margin-bottom:4pt;">
  <div class="ptitle">Plano de Ação Recomendado</div>
  <table class="dt">
    <thead>
      <tr>
        <th style="width:55%;">Ação</th>
        <th style="width:22%;">Responsável</th>
        <th style="width:13%; text-align:center;">Prazo</th>
        <th style="width:10%; text-align:center;">Prior.</th>
      </tr>
    </thead>
    <tbody>
      <?php $i = 0; foreach ($acoes as $a): $i++; if ($i > 3) break;
        $pri    = strtolower(trim($a['prioridade'] ?? 'alta'));
        $priCor = $pri === 'alta' ? '#D32F2F' : ($pri === 'média' ? '#E65100' : '#2E7D32');
        $priBg  = $pri === 'alta' ? '#FDECEA' : ($pri === 'média' ? '#FFF9E6' : '#EDF7EE');
        $priLbl = $pri === 'alta' ? 'ALTA'    : ($pri === 'média' ? 'MED'    : 'BAIXA');
      ?>
      <tr>
        <td style="font-size:7pt; line-height:1.3;"><?= ofuscarAcao($a['acao'], $bloquear) ?></td>
        <td style="font-size:7pt; color:#4a5568;"><?= htmlspecialchars($a['responsavel'] ?? '—') ?></td>
        <td align="center" bgcolor="<?= $AZUL2 ?>" style="color:<?= $AZUL ?>; font-weight:bold; font-size:7pt; white-space:nowrap;"><?= htmlspecialchars($a['prazo'] ?? '—') ?></td>
        <td align="center" bgcolor="<?= $priBg ?>" style="color:<?= $priCor ?>; font-weight:bold; font-size:6.5pt;"><?= $priLbl ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- ══ PARECER EXECUTIVO ══ -->
<?php if ($r['conclusao']): ?>
<div class="panel" style="margin-bottom:5pt;">
  <div class="ptitle">Parecer Executivo</div>
  <?php
    $pars_c  = explode("\n\n", $r['conclusao']);
    $res_c   = trim($pars_c[0] ?? '');
    if (isset($pars_c[1]) && strlen($res_c) < 280) $res_c .= ' ' . trim($pars_c[1]);
    $res_c   = mb_strimwidth($res_c, 0, 430, '…');
  ?>
  <p style="text-align:justify; font-size:7pt; color:#475569; line-height:1.4; margin:0;"><?= nl2br(htmlspecialchars($res_c)) ?></p>
</div>
<?php endif; ?>

<!-- ══ RODAPÉ ══ -->
<table class="lt" bgcolor="<?= $AZUL ?>" style="border-radius:6px; padding:5pt 10pt;">
  <tr>
    <td style="font-size:7.5pt; font-weight:bold; color:#fff; padding:5pt 10pt;"><?= htmlspecialchars($r['analista']) ?> &bull; Rajo Desenvolvimento</td>
    <td style="text-align:right; font-size:6.5pt; color:rgba(255,255,255,0.7); padding:5pt 10pt;">rajo.com.br &bull; contato@rajo.com.br</td>
  </tr>
</table>

</body>
</html>
    <?php
    $html = ob_get_clean();

    $mpdf = new \Mpdf\Mpdf([
        'mode'         => 'utf-8',
        'format'       => 'A4',
        'margin_top'   => 8,
        'margin_right' => 10,
        'margin_bottom'=> 8,
        'margin_left'  => 10,
        'default_font' => 'arial',
        'tempDir'      => sys_get_temp_dir() . '/mpdf',
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

// ═══════════════════════════════════════════════════════════════
// RELATÓRIO COMPLETO — LAYOUT PREMIUM
// ═══════════════════════════════════════════════════════════════
ob_start();

// Pré-calcular dados para uso no template
$resGeral = strtoupper(trim($r['resultado_geral'] ?? 'CRÍTICO'));
$geo  = !empty($r['auditoria_hospedagem']) ? json_decode($r['auditoria_hospedagem'], true) : null;
$seg  = !empty($r['auditoria_seguranca']  ) ? json_decode($r['auditoria_seguranca'],   true) : null;
$dns  = !empty($r['auditoria_dns']        ) ? json_decode($r['auditoria_dns'],          true) : null;
$segAtivos = 0;
if ($seg) {
    foreach (['hsts', 'csp', 'x_frame', 'x_content', 'referrer'] as $c) {
        if (!empty($seg[$c])) $segAtivos++;
    }
}

$scoreGeral = null;
$scores_num = [];
foreach (['ps_performance_desktop','ps_performance_mobile','ps_seo_desktop','ps_seo_mobile','ps_acessibilidade_desktop','ps_acessibilidade_mobile','ps_boaspraticas_desktop','ps_boaspraticas_mobile'] as $f) {
    if (!empty($r[$f]) && is_numeric($r[$f])) $scores_num[] = (int)$r[$f];
}
if (!empty($scores_num)) $scoreGeral = round(array_sum($scores_num) / count($scores_num));

$inv = (float)($r['ads_investimento'] ?? 0);
$cpc = (float)($r['ads_cpc'] ?? 0);
$temAds = $inv > 0 && $cpc > 0;
if ($temAds) {
    $P = (int)($r['ps_performance_mobile'] ?? 35);
    $D = $P >= 90 ? 3 : ($P >= 50 ? 30 - 0.3 * $P : 65 - 0.7 * $P);
    $D = max(0, min(100, $D));
    $prejuizo = $inv * ($D / 100);
    $cliquesPerdidos = $cpc > 0 ? round($prejuizo / $cpc) : 0;
    $aproveitado = $inv - $prejuizo;
}

$adExp = trim($r['ad_experience_status'] ?? '');
$sb    = trim($r['safe_browsing_status'] ?? '');
$ap    = trim($r['ads_policy_status'] ?? '');

// Detectar se há comparativo visual possível
$temComparativo = $scoreGeral !== null;

// Score card helper
function scoreCard(string $label, string $valDesk, string $valMob, string $AZUL2, string $AZUL): string {
    $dv = $valDesk !== '' ? $valDesk : null;
    $mv = $valMob  !== '' ? $valMob  : null;
    if ($dv === null && $mv === null) return '';
    $val = $dv ?? $mv;
    if (!is_numeric($val)) return '';
    $n   = (int)$val;
    if ($n >= 90)      { $cor = '#2E7D32'; $bg = '#EDF7EE'; $icon = '✓'; }
    elseif ($n >= 50)  { $cor = '#E65100'; $bg = '#FFF9E6'; $icon = '!'; }
    else               { $cor = '#D32F2F'; $bg = '#FDECEA'; $icon = '✗'; }

    $deskHtml = $dv !== null ? "<span style='font-size:7pt;color:#718096;'>Desktop: <strong style='color:{$cor};'>{$dv}</strong></span>" : '';
    $mobHtml  = $mv !== null ? "<span style='font-size:7pt;color:#718096;'>Mobile: <strong style='color:{$cor};'>{$mv}</strong></span>" : '';
    $sep = ($dv !== null && $mv !== null) ? " &nbsp;|&nbsp; " : '';

    return "
    <td style='width:25%; padding:4pt; vertical-align:top;'>
      <div style='background:{$bg}; border:1.5px solid {$cor}; border-radius:8px; padding:8pt 6pt; text-align:center; height:60pt;'>
        <div style='font-size:18pt; font-weight:900; color:{$cor}; line-height:1;'>{$n}</div>
        <div style='font-size:6.5pt; font-weight:bold; color:{$cor}; text-transform:uppercase; letter-spacing:0.3px; margin-top:2pt;'>{$icon} {$label}</div>
        <div style='margin-top:3pt;'>{$deskHtml}{$sep}{$mobHtml}</div>
      </div>
    </td>";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: Arial, sans-serif; font-size:9.5pt; color:#1e293b; line-height:1.5; background:#fff; }

  /* ── Tipografia ── */
  h1 { font-size:13pt; color:#fff; font-weight:bold; text-transform:uppercase; letter-spacing:0.5px; margin:0; padding:0; }
  h2 { font-size:11pt; color:<?= $AZUL ?>; font-weight:bold; margin:0 0 4pt 0; }
  h3 { font-size:9.5pt; color:<?= $AZUL ?>; font-weight:bold; margin:0 0 4pt 0; }
  p  { margin-bottom:6pt; line-height:1.5; color:#475569; font-size:9pt; }

  /* ── Section header ── */
  .section-header { background:<?= $AZUL ?>; color:#fff; padding:8pt 14pt; border-radius:8px; margin-bottom:14pt; margin-top:4pt; }
  .section-header .section-num { font-size:8pt; font-weight:bold; letter-spacing:1px; opacity:0.8; text-transform:uppercase; margin-bottom:1pt; }

  /* ── Cards ── */
  .card { border:1px solid #e2e8f0; border-radius:8px; padding:10pt 12pt; background:#f8fafc; margin-bottom:8pt; }
  .card-title { font-size:8pt; font-weight:bold; color:<?= $AZUL ?>; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6pt; border-bottom:1px solid #e2e8f0; padding-bottom:4pt; }

  /* ── Tabelas ── */
  table  { width:100%; border-collapse:collapse; margin-bottom:8pt; }
  th, td { padding:6pt 8pt; border:1px solid #e2e8f0; font-size:8.5pt; text-align:left; color:#334155; vertical-align:middle; }
  thead th { background:<?= $AZUL ?>; color:#fff; font-weight:bold; font-size:8.5pt; border:1px solid <?= $AZUL ?>; text-transform:uppercase; letter-spacing:0.3px; }
  tbody tr:nth-child(even) { background:#f8fafc; }

  /* ── Badges ── */
  .badge { display:inline-block; padding:2pt 7pt; border-radius:12px; font-size:7.5pt; font-weight:bold; text-align:center; }
  .badge-ok     { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
  .badge-warn   { background:#fef3c7; color:#92400e; border:1px solid #fde68a; }
  .badge-danger { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }
  .badge-info   { background:<?= $AZUL2 ?>; color:<?= $AZUL ?>; border:1px solid <?= $AZUL ?>; }

  /* ── Alert boxes ── */
  .alert-danger { background:#fff5f5; border-left:4px solid #e53e3e; border-top:1px solid #fed7d7; border-right:1px solid #fed7d7; border-bottom:1px solid #fed7d7; padding:9pt 12pt; margin:6pt 0; border-radius:0 6px 6px 0; }
  .alert-warn   { background:#fffaf0; border-left:4px solid #dd6b20; border-top:1px solid #feebc8; border-right:1px solid #feebc8; border-bottom:1px solid #feebc8; padding:9pt 12pt; margin:6pt 0; border-radius:0 6px 6px 0; }
  .alert-ok     { background:#f0fff4; border-left:4px solid #38a169; border-top:1px solid #c6f6d5; border-right:1px solid #c6f6d5; border-bottom:1px solid #c6f6d5; padding:9pt 12pt; margin:6pt 0; border-radius:0 6px 6px 0; }
  .alert-info   { background:<?= $AZUL2 ?>; border-left:4px solid <?= $AZUL ?>; padding:9pt 12pt; margin:6pt 0; border-radius:0 6px 6px 0; }

  /* ── Capa ── */
  .cover-bg    { background:<?= $AZUL ?>; padding:40pt 30pt 30pt 30pt; }
  .cover-white { background:#fff; padding:26pt 30pt 30pt 30pt; }
  .cover-title { font-size:22pt; font-weight:900; color:#fff; letter-spacing:-0.5px; line-height:1.2; margin-bottom:8pt; }
  .cover-sub   { font-size:10pt; color:rgba(255,255,255,0.8); letter-spacing:0.5px; }
  .cover-divider { width:50pt; height:3pt; background:#fff; margin:14pt 0; opacity:0.5; }
  .cover-info  { background:rgba(255,255,255,0.12); border-radius:8px; padding:14pt 18pt; margin-top:20pt; }
  .cover-info-row { font-size:9pt; color:#fff; padding:4pt 0; border-bottom:1px solid rgba(255,255,255,0.15); }
  .cover-info-row:last-child { border-bottom:none; }
  .cover-info-label { color:rgba(255,255,255,0.65); font-size:8.5pt; }
  .cover-footer { font-size:8pt; color:rgba(255,255,255,0.5); text-align:center; margin-top:30pt; text-transform:uppercase; letter-spacing:0.5px; }

  /* ── Oportunidade card ── */
  .opp-card { border:1px solid #e2e8f0; border-radius:8px; padding:10pt 12pt; margin-bottom:8pt; background:#fff; }
  .opp-card-header { font-size:9pt; font-weight:bold; color:#1e293b; margin-bottom:4pt; }
  .opp-card-desc { font-size:8.5pt; color:#475569; margin-bottom:6pt; line-height:1.45; }
  .opp-card-footer { font-size:8pt; }

  /* ── Score bar visual ── */
  .score-bar-wrap { background:#e2e8f0; border-radius:4px; height:8pt; margin-top:3pt; }
  .score-bar-fill { height:8pt; border-radius:4px; }

  /* ── Page break ── */
  .page-break { page-break-before:always; }

  /* ── Próximos passos ── */
  .step-item { padding:8pt 0; border-bottom:1px solid #f1f5f9; }
  .step-item:last-child { border-bottom:none; }
  .step-num { display:inline-block; width:18pt; height:18pt; border-radius:50%; background:<?= $AZUL ?>; color:#fff; text-align:center; font-size:9pt; font-weight:bold; line-height:18pt; margin-right:6pt; }
</style>
</head>
<body>

<!-- ═══════════════════════════════════════ CAPA ═══════════════════════════════════════ -->
<div class="cover-bg">
  <table style="border:none; margin:0; width:100%;">
    <tr>
      <td style="border:none; padding:0; width:60%; vertical-align:top;">
        <?php if (!empty($r['logo_cliente'])): ?>
          <img src="<?= htmlspecialchars($r['logo_cliente']) ?>" style="max-height:50pt; max-width:140pt; margin-bottom:20pt; filter:brightness(10);" />
        <?php else: ?>
          <div style="font-size:24pt; font-weight:900; color:#fff; letter-spacing:3px; margin-bottom:20pt;">RAJO</div>
        <?php endif; ?>

        <div style="font-size:8pt; color:rgba(255,255,255,0.6); text-transform:uppercase; letter-spacing:1.5px; margin-bottom:8pt;">Diagnóstico Estratégico</div>
        <div class="cover-title">Diagnóstico<br>Estratégico de SEO,<br>Performance e<br>Conversão</div>
        <div class="cover-divider"></div>
        <div class="cover-sub">Auditoria técnica e análise de impacto comercial</div>
      </td>
      <td style="border:none; padding:0; width:40%; vertical-align:top; text-align:right;">
        <div style="font-size:7.5pt; color:rgba(255,255,255,0.5); text-transform:uppercase; letter-spacing:1px; margin-bottom:6pt;">Emitido em</div>
        <div style="font-size:11pt; font-weight:bold; color:#fff;"><?= date('d/m/Y', strtotime($r['data_relatorio'])) ?></div>
      </td>
    </tr>
  </table>

  <div class="cover-info">
    <div class="cover-info-row">
      <table style="border:none; margin:0; width:100%;">
        <tr>
          <td style="border:none; padding:3pt 0; width:35%;"><span class="cover-info-label">Cliente</span></td>
          <td style="border:none; padding:3pt 0; font-weight:bold; font-size:9.5pt; color:#fff;"><?= htmlspecialchars($r['cliente']) ?></td>
        </tr>
        <tr>
          <td style="border:none; padding:3pt 0;"><span class="cover-info-label">Domínio Analisado</span></td>
          <td style="border:none; padding:3pt 0; font-weight:bold; color:#fff;"><?= htmlspecialchars($r['dominio']) ?></td>
        </tr>
        <tr>
          <td style="border:none; padding:3pt 0;"><span class="cover-info-label">Responsável Técnico</span></td>
          <td style="border:none; padding:3pt 0; color:#fff;"><?= htmlspecialchars($r['analista']) ?></td>
        </tr>
        <tr>
          <td style="border:none; padding:3pt 0;"><span class="cover-info-label">Classificação</span></td>
          <td style="border:none; padding:3pt 0; color:rgba(255,200,200,0.9); font-size:8.5pt;">Confidencial — Uso Exclusivo do Cliente</td>
        </tr>
      </table>
    </div>
  </div>

  <div class="cover-footer">
    rajo.com.br &bull; contato@rajo.com.br &bull; Engenharia de Conversão &amp; Otimização Digital
  </div>
</div>

<!-- ═══════════════════════════ RESUMO EXECUTIVO ═══════════════════════════ -->
<div class="page-break"></div>

<div class="section-header">
  <div class="section-num">Seção 01</div>
  <h1>Resumo Executivo</h1>
</div>

<?php
$resGeral = strtoupper(trim($r['resultado_geral'] ?? 'CRÍTICO'));
if ($resGeral === 'BOM') {
    $statusIcon = '✓'; $statusCor = '#2E7D32'; $statusBg = '#EDF7EE'; $statusBorder = '#86efac';
    $statusTexto = 'O site apresenta boa conformidade técnica. Existem oportunidades de melhoria contínua para maximizar resultados.';
} elseif ($resGeral === 'MÉDIO') {
    $statusIcon = '!'; $statusCor = '#E65100'; $statusBg = '#FFF9E6'; $statusBorder = '#fde68a';
    $statusTexto = 'O site funciona, mas apresenta gargalos que estão limitando vendas, leads e o retorno sobre os seus investimentos em marketing.';
} else {
    $statusIcon = '✗'; $statusCor = '#D32F2F'; $statusBg = '#FDECEA'; $statusBorder = '#fca5a5';
    $statusTexto = 'O site apresenta problemas críticos que prejudicam diretamente a captação de clientes e o desempenho nas buscas do Google.';
}
?>

<!-- Status geral em destaque -->
<div style="background:<?= $statusBg ?>; border:2px solid <?= $statusBorder ?>; border-radius:10px; padding:14pt 18pt; margin-bottom:16pt;">
  <table style="border:none; margin:0; width:100%;">
    <tr>
      <td style="border:none; padding:0; width:70pt; text-align:center; vertical-align:middle;">
        <div style="font-size:28pt; font-weight:900; color:<?= $statusCor ?>; line-height:1;"><?= $statusIcon ?></div>
        <div style="font-size:8pt; font-weight:bold; color:<?= $statusCor ?>; text-transform:uppercase; margin-top:2pt;"><?= htmlspecialchars($r['resultado_geral']) ?></div>
      </td>
      <td style="border:none; padding:0 0 0 14pt; vertical-align:middle;">
        <div style="font-size:11pt; font-weight:bold; color:<?= $statusCor ?>; margin-bottom:4pt;">Status Geral do Site</div>
        <p style="font-size:9pt; color:#475569; margin:0; line-height:1.5;"><?= $statusTexto ?></p>
      </td>
      <?php if ($scoreGeral !== null): ?>
      <td style="border:none; padding:0 0 0 14pt; width:80pt; text-align:center; vertical-align:middle;">
        <div style="font-size:28pt; font-weight:900; color:<?= corNota((string)$scoreGeral) ?>; line-height:1;"><?= $scoreGeral ?></div>
        <div style="font-size:7.5pt; color:#718096; text-transform:uppercase; font-weight:bold;">Score Médio</div>
        <div style="font-size:7pt; color:#a0aec0;">de 100</div>
      </td>
      <?php endif; ?>
    </tr>
  </table>
</div>

<!-- Score cards das métricas -->
<?php
$scoreCards = [];
if (!empty($r['ps_performance_desktop']) || !empty($r['ps_performance_mobile'])) {
    $scoreCards[] = ['Performance', (string)($r['ps_performance_desktop'] ?? ''), (string)($r['ps_performance_mobile'] ?? '')];
}
if (!empty($r['ps_seo_desktop']) || !empty($r['ps_seo_mobile'])) {
    $scoreCards[] = ['SEO', (string)($r['ps_seo_desktop'] ?? ''), (string)($r['ps_seo_mobile'] ?? '')];
}
if (!empty($r['ps_acessibilidade_desktop']) || !empty($r['ps_acessibilidade_mobile'])) {
    $scoreCards[] = ['Acessibilidade', (string)($r['ps_acessibilidade_desktop'] ?? ''), (string)($r['ps_acessibilidade_mobile'] ?? '')];
}
if (!empty($r['ps_boaspraticas_desktop']) || !empty($r['ps_boaspraticas_mobile'])) {
    $scoreCards[] = ['Boas Práticas', (string)($r['ps_boaspraticas_desktop'] ?? ''), (string)($r['ps_boaspraticas_mobile'] ?? '')];
}
if (!empty($scoreCards)):
?>
<div style="margin-bottom:16pt;">
  <div style="font-size:8pt; font-weight:bold; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8pt;">Pontuações por Categoria (Google PageSpeed Insights)</div>
  <table style="border:none; margin:0; border-collapse:separate; border-spacing:6pt;">
    <tr>
      <?php foreach ($scoreCards as [$lbl, $dsk, $mob]): ?>
        <?php
        $val = $dsk !== '' ? $dsk : $mob;
        if (!is_numeric($val)) continue;
        $n = (int)$val;
        $cor = $n >= 90 ? '#2E7D32' : ($n >= 50 ? '#E65100' : '#D32F2F');
        $bg  = $n >= 90 ? '#EDF7EE' : ($n >= 50 ? '#FFF9E6' : '#FDECEA');
        $barColor = $cor;
        $barWidth = max(4, $n);
        ?>
        <td style="width:25%; padding:0; border:none; vertical-align:top;">
          <div style="background:<?= $bg ?>; border:1.5px solid <?= $cor ?>; border-radius:8px; padding:10pt 8pt; text-align:center;">
            <div style="font-size:24pt; font-weight:900; color:<?= $cor ?>; line-height:1.1;"><?= $n ?></div>
            <div style="font-size:7.5pt; font-weight:bold; color:<?= $cor ?>; text-transform:uppercase; letter-spacing:0.3px; margin-top:3pt;"><?= htmlspecialchars($lbl) ?></div>
            <div style="background:#e2e8f0; border-radius:4px; height:5pt; margin-top:6pt;">
              <div style="background:<?= $barColor ?>; width:<?= $barWidth ?>%; height:5pt; border-radius:4px;"></div>
            </div>
            <?php if ($dsk !== '' && $mob !== '' && $dsk !== $mob): ?>
              <div style="font-size:6.5pt; color:#718096; margin-top:3pt;">D: <strong><?= $dsk ?></strong> &nbsp;M: <strong><?= $mob ?></strong></div>
            <?php elseif ($dsk !== ''): ?>
              <div style="font-size:6.5pt; color:#718096; margin-top:3pt;">Desktop &amp; Mobile</div>
            <?php endif; ?>
          </div>
        </td>
      <?php endforeach; ?>
    </tr>
  </table>
</div>
<?php endif; ?>

<div style="font-size:8pt; color:#94a3b8; margin-bottom:16pt;">
  <span style="margin-right:12pt; font-weight:bold; color:#2E7D32;">&#9632; 90–100 = Excelente</span>
  <span style="margin-right:12pt; font-weight:bold; color:#E65100;">&#9632; 50–89 = Atenção</span>
  <span style="font-weight:bold; color:#D32F2F;">&#9632; 0–49 = Crítico</span>
</div>

<?php if (str_contains(strtolower($r['gtm_nota'] ?? ''), 'erro') || str_contains(strtolower($r['gtm_nota'] ?? ''), 'timeout')): ?>
<div class="alert-danger" style="margin-bottom:12pt;">
  <strong style="color:#c53030;">Impedimento Técnico Detectado:</strong>
  <span style="display:block; font-size:8.5pt; color:#4a5568; margin-top:3pt; line-height:1.5;">A análise automatizada falhou por sobrecarga de processamento no site. Isso indica lentidão severa que causa abandono imediato de visitantes e penalidades no Google Ads.</span>
</div>
<?php endif; ?>


<!-- ═══════════════════════ PRINCIPAIS OPORTUNIDADES ═══════════════════════ -->
<?php if (!empty($problemas)): ?>

<div class="section-header">
  <div class="section-num">Seção 02</div>
  <h1>Principais Oportunidades Encontradas</h1>
</div>

<p>Identificamos os seguintes pontos de melhoria que estão impactando diretamente os resultados do site:</p>

<?php foreach ($problemas as $i => $prob):
    $pri = strtolower(trim($prob['prioridade'] ?? 'alta'));
    if ($pri === 'alta')       { $priBg = '#FDECEA'; $priCor = '#D32F2F'; $priBorder = '#fca5a5'; $priLabel = 'Alta Prioridade'; }
    elseif ($pri === 'média')  { $priBg = '#FFF9E6'; $priCor = '#E65100'; $priBorder = '#fde68a'; $priLabel = 'Média Prioridade'; }
    else                       { $priBg = '#EDF7EE'; $priCor = '#2E7D32'; $priBorder = '#bbf7d0'; $priLabel = 'Baixa Prioridade'; }
    $impactoTexto = impactoNegocio($prob['problema'] ?? '', $prob['impacto'] ?? '');
?>
<div style="border:1px solid #e2e8f0; border-left:4px solid <?= $priCor ?>; border-radius:0 8px 8px 0; padding:12pt 14pt; margin-bottom:10pt; background:#fff;">
  <table style="border:none; margin:0; width:100%;">
    <tr>
      <td style="border:none; padding:0; vertical-align:top;">
        <div style="font-size:9.5pt; font-weight:bold; color:#1e293b; margin-bottom:4pt;"><?= formatarProblema($prob['problema'], $bloquear) ?></div>
        <div style="font-size:8.5pt; color:#475569; line-height:1.5; margin-bottom:6pt;"><?= $impactoTexto ?></div>
        <div style="font-size:8pt;">
          <span style="color:#64748b; font-weight:bold;">Canal impactado:</span>
          <span style="color:#475569;"> <?= htmlspecialchars($prob['impacto'] ?? '—') ?></span>
        </div>
      </td>
      <td style="border:none; padding:0 0 0 12pt; width:80pt; text-align:center; vertical-align:middle;">
        <div style="background:<?= $priBg ?>; border:1px solid <?= $priBorder ?>; border-radius:20px; padding:4pt 10pt; font-size:7.5pt; font-weight:bold; color:<?= $priCor ?>; text-transform:uppercase; white-space:nowrap;"><?= $priLabel ?></div>
      </td>
    </tr>
  </table>
</div>
<?php endforeach; ?>
<?php endif; ?>


<!-- ═══════════════════════ IMPACTO COMERCIAL ═══════════════════════ -->
<div style="margin-top:14pt;"></div>

<div class="section-header">
  <div class="section-num">Seção 03</div>
  <h1>Impacto Comercial</h1>
</div>

<p>Veja como os problemas técnicos encontrados se traduzem em consequências reais para o seu negócio:</p>

<table style="margin-bottom:16pt;">
  <thead>
    <tr>
      <th style="width:35%;">Problema Identificado</th>
      <th style="width:45%;">Consequência para o Negócio</th>
      <th style="width:20%; text-align:center;">Impacto</th>
    </tr>
  </thead>
  <tbody>
    <?php
    // Gerar linhas de impacto com base nos problemas + dados técnicos
    $linhasImpacto = [];

    foreach ($problemas as $prob) {
        $p = mb_strtolower($prob['problema'] ?? '');
        $pri = strtolower(trim($prob['prioridade'] ?? 'alta'));
        if ($pri === 'alta') { $impCor = '#D32F2F'; $impBg = '#FDECEA'; $impLabel = 'Alto'; }
        elseif ($pri === 'média') { $impCor = '#E65100'; $impBg = '#FFF9E6'; $impLabel = 'Médio'; }
        else { $impCor = '#2E7D32'; $impBg = '#EDF7EE'; $impLabel = 'Baixo'; }
        $linhasImpacto[] = [
            'problema' => formatarProblema($prob['problema'], $bloquear),
            'consequencia' => impactoNegocio($prob['problema'], $prob['impacto'] ?? ''),
            'cor' => $impCor, 'bg' => $impBg, 'label' => $impLabel,
        ];
    }

    // Adicionar linhas de infraestrutura se problemáticas
    if ($seg && !$seg['ssl_ativo']) {
        $linhasImpacto[] = ['problema' => 'Certificado de segurança (SSL) inativo', 'consequencia' => 'Navegadores exibem aviso de "site não seguro", afastando clientes antes mesmo de ver sua oferta.', 'cor' => '#D32F2F', 'bg' => '#FDECEA', 'label' => 'Alto'];
    }
    if ($dns && !$dns['spf_valido']) {
        $linhasImpacto[] = ['problema' => 'Configuração de e-mail (SPF) ausente', 'consequencia' => 'E-mails enviados pelo domínio podem cair no spam, comprometendo a comunicação com clientes.', 'cor' => '#E65100', 'bg' => '#FFF9E6', 'label' => 'Médio'];
    }
    if ($dns && !$dns['dmarc_valido']) {
        $linhasImpacto[] = ['problema' => 'Proteção de domínio (DMARC) ausente', 'consequencia' => 'Terceiros mal-intencionados podem enviar e-mails falsos usando o nome do seu domínio.', 'cor' => '#E65100', 'bg' => '#FFF9E6', 'label' => 'Médio'];
    }

    foreach ($linhasImpacto as $li):
    ?>
    <tr>
      <td style="font-size:8.5pt; font-weight:500;"><?= $li['problema'] ?></td>
      <td style="font-size:8.5pt; color:#475569; line-height:1.45;"><?= htmlspecialchars($li['consequencia']) ?></td>
      <td align="center" bgcolor="<?= $li['bg'] ?>" style="color:<?= $li['cor'] ?>; font-weight:bold; font-size:8pt;"><?= $li['label'] ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($linhasImpacto)): ?>
    <tr>
      <td colspan="3" align="center" style="color:#2E7D32; font-weight:bold; padding:12pt;">
        ✓ Nenhum impacto comercial crítico identificado.
      </td>
    </tr>
    <?php endif; ?>
  </tbody>
</table>

<?php if ($temAds): ?>
<!-- Bloco de impacto financeiro em Ads -->
<div style="background:#fff5f5; border:2px solid #fca5a5; border-radius:10px; padding:14pt 18pt; margin-bottom:16pt;">
  <div style="font-size:10pt; font-weight:bold; color:#c53030; margin-bottom:8pt;">Estimativa de Desperdício em Anúncios Pagos</div>
  <table style="border:none; margin:0; width:100%;">
    <tr>
      <td style="border:none; padding:0; width:65%; vertical-align:top;">
        <p style="font-size:8.5pt; color:#4a5568; margin:0; line-height:1.5;">
          Com base na velocidade de carregamento no celular (nota <?= $P ?>/100), estima-se que <strong style="color:#c53030;"><?= round($D) ?>% do seu orçamento</strong> em anúncios está sendo desperdiçado — cliques são cobrados, mas os usuários abandonam o site antes de ver sua oferta.
        </p>
        <div style="margin-top:8pt; font-size:8pt; color:#718096;">
          Investimento mensal: R$ <?= number_format($inv, 2, ',', '.') ?> &nbsp;&bull;&nbsp;
          Aproveitado efetivamente: <strong style="color:#2E7D32;">R$ <?= number_format($aproveitado, 2, ',', '.') ?></strong>
        </div>
      </td>
      <td style="border:none; padding:0 0 0 16pt; width:35%; text-align:center; vertical-align:middle;">
        <div style="background:#fff; border:2px solid #e53e3e; border-radius:8px; padding:10pt;">
          <div style="font-size:8pt; color:#718096; text-transform:uppercase; font-weight:bold; margin-bottom:3pt;">Perda Estimada / Mês</div>
          <div style="font-size:20pt; font-weight:900; color:#e53e3e; line-height:1.1;">R$ <?= number_format($prejuizo, 2, ',', '.') ?></div>
          <div style="font-size:7.5pt; color:#e53e3e; margin-top:2pt;"><?= number_format($cliquesPerdidos) ?> cliques perdidos</div>
        </div>
      </td>
    </tr>
  </table>
</div>
<?php endif; ?>


<!-- ═══════════════════════ INDICADORES TÉCNICOS ═══════════════════════ -->
<div class="page-break"></div>

<div class="section-header">
  <div class="section-num">Seção 04</div>
  <h1>Indicadores Técnicos</h1>
</div>

<!-- Core Web Vitals -->
<?php
$cwv_rows = [
    ['lcp',   'LCP',   'Renderização do Maior Elemento', '&lt; 2,5 s'],
    ['inp',   'INP',   'Velocidade de Resposta ao Clique', '&lt; 200 ms'],
    ['cls',   'CLS',   'Estabilidade Visual da Página', '&lt; 0,1'],
    ['fcp',   'FCP',   'Tempo até Primeiro Conteúdo Visível', '&lt; 1,8 s'],
    ['ttfb',  'TTFB',  'Tempo de Resposta do Servidor', '&lt; 600 ms'],
    ['speed', 'Speed', 'Velocidade Visual Geral', '&lt; 3,4 s'],
];
$cwvTemDados = false;
foreach ($cwv_rows as [$key]) {
    if (!empty($r["cwv_{$key}_desktop"]) || !empty($r["cwv_{$key}_mobile"])) { $cwvTemDados = true; break; }
}
if ($cwvTemDados):
?>
<h2 style="margin-bottom:8pt;">Velocidade e Experiência do Usuário (Core Web Vitals)</h2>
<p>Métricas que o Google usa para avaliar a qualidade da experiência de navegação. Baixo desempenho aqui aumenta o custo de anúncios e reduz o posicionamento orgânico.</p>

<table style="margin-bottom:16pt;">
  <thead>
    <tr>
      <th style="width:10%; text-align:center;">Sigla</th>
      <th style="width:38%;">O que mede</th>
      <th style="width:14%; text-align:center;">Meta</th>
      <th style="width:14%; text-align:center;">Desktop</th>
      <th style="width:14%; text-align:center;">Mobile</th>
      <th style="width:10%; text-align:center;">Status</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($cwv_rows as [$key, $sigla, $nome, $ref]):
      $status = $r["cwv_{$key}_status"] ?? '';
      $desk   = cwv_val((string)($r["cwv_{$key}_desktop"] ?? ''));
      $mob    = cwv_val((string)($r["cwv_{$key}_mobile"]  ?? ''));
      if ($desk === '–' && $mob === '–') continue;
    ?>
    <tr>
      <td bgcolor="<?= $AZUL2 ?>" style="color:<?= $AZUL ?>;font-weight:bold;text-align:center;font-size:8pt;"><?= $sigla ?></td>
      <td style="font-size:8pt;"><?= $nome ?></td>
      <td style="color:#2E7D32;font-weight:bold;text-align:center;font-size:8pt;"><?= $ref ?></td>
      <td align="center" style="font-weight:bold;font-size:8.5pt;"><?= $desk ?></td>
      <td align="center" style="font-weight:bold;font-size:8.5pt;"><?= $mob ?></td>
      <?php if ($status !== ''): ?>
      <td align="center" bgcolor="<?= bgStatus($status) ?>" style="color:<?= corStatus($status) ?>;font-weight:bold;font-size:8pt;"><?= htmlspecialchars($status) ?></td>
      <?php else: ?>
      <td align="center" style="color:#718096;font-size:8pt;">—</td>
      <?php endif; ?>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<!-- Infraestrutura e Segurança -->
<?php if ($geo || $seg || $dns || $r['auditoria_cms']): ?>
<h2 style="margin-bottom:8pt; margin-top:6pt;">Infraestrutura e Segurança</h2>

<table style="margin-bottom:16pt;">
  <thead>
    <tr>
      <th style="width:28%;">Item Verificado</th>
      <th style="width:48%;">Resultado</th>
      <th style="width:24%; text-align:center;">Status</th>
    </tr>
  </thead>
  <tbody>
    <?php if (!empty($r['auditoria_cms'])): ?>
    <tr>
      <td style="font-weight:bold;">Plataforma do Site</td>
      <td>Sistema de gerenciamento utilizado para construir e administrar o site.</td>
      <td align="center" bgcolor="<?= $r['auditoria_cms'] !== 'Não Identificado' ? '#EDF7EE' : '#FFF9E6' ?>" style="color:<?= $r['auditoria_cms'] !== 'Não Identificado' ? '#2E7D32' : '#E65100' ?>; font-weight:bold;">
        <?= htmlspecialchars($r['auditoria_cms']) ?>
      </td>
    </tr>
    <?php endif; ?>

    <?php if ($geo): ?>
    <tr>
      <td style="font-weight:bold;">Hospedagem &amp; Servidor</td>
      <td>
        Provedor: <strong><?= htmlspecialchars($geo['provedor']) ?></strong><br>
        Localização: <?= htmlspecialchars($geo['cidade'] ?? '') ?><?= !empty($geo['cidade']) ? ', ' : '' ?><?= htmlspecialchars($geo['pais'] ?? '') ?>
      </td>
      <td align="center" bgcolor="#EDF7EE" style="color:#2E7D32; font-weight:bold;">✓ Mapeado</td>
    </tr>
    <?php endif; ?>

    <?php if ($seg): ?>
    <tr>
      <td style="font-weight:bold;">Certificado de Segurança (SSL)</td>
      <td>
        <?= $seg['ssl_ativo'] ? 'Certificado ativo — conexão criptografada e segura para os visitantes.' : 'Certificado inativo — visitantes veem aviso de "site não seguro".' ?>
        <?php if ($seg['ssl_ativo']): ?>
          <br><span style="font-size:8pt; color:#718096;">Proteções adicionais ativas: <?= $segAtivos ?> de 5</span>
        <?php endif; ?>
      </td>
      <td align="center" bgcolor="<?= $seg['ssl_ativo'] ? ($segAtivos >= 2 ? '#EDF7EE' : '#FFF9E6') : '#FDECEA' ?>" style="color:<?= $seg['ssl_ativo'] ? ($segAtivos >= 2 ? '#2E7D32' : '#E65100') : '#D32F2F' ?>; font-weight:bold;">
        <?= $seg['ssl_ativo'] ? ($segAtivos >= 2 ? '✓ Seguro' : '⚠ Básico') : '✗ Inativo' ?>
      </td>
    </tr>
    <?php endif; ?>

    <?php if ($dns): ?>
    <tr>
      <td style="font-weight:bold;">Proteção de E-mail (Anti-Spam)</td>
      <td>
        SPF: <strong><?= $dns['spf_valido'] ? 'Configurado' : 'Ausente' ?></strong> &nbsp;&bull;&nbsp;
        DMARC: <strong><?= $dns['dmarc_valido'] ? 'Configurado' : 'Ausente' ?></strong><br>
        <span style="font-size:8pt; color:#718096;">Protege o domínio contra uso indevido em e-mails falsos (phishing).</span>
      </td>
      <td align="center" bgcolor="<?= ($dns['spf_valido'] && $dns['dmarc_valido']) ? '#EDF7EE' : (($dns['spf_valido'] || $dns['dmarc_valido']) ? '#FFF9E6' : '#FDECEA') ?>" style="color:<?= ($dns['spf_valido'] && $dns['dmarc_valido']) ? '#2E7D32' : (($dns['spf_valido'] || $dns['dmarc_valido']) ? '#E65100' : '#D32F2F') ?>; font-weight:bold;">
        <?= ($dns['spf_valido'] && $dns['dmarc_valido']) ? '✓ Protegido' : (($dns['spf_valido'] || $dns['dmarc_valido']) ? '⚠ Parcial' : '✗ Exposto') ?>
      </td>
    </tr>
    <?php endif; ?>
  </tbody>
</table>
<?php endif; ?>

<!-- Conformidade Google Ads -->
<?php if ($adExp || $sb || $ap): ?>
<h2 style="margin-bottom:8pt; margin-top:6pt;">Conformidade com Google Ads</h2>
<table style="margin-bottom:16pt;">
  <thead>
    <tr>
      <th style="width:30%;">Verificação</th>
      <th style="width:45%;">O que significa</th>
      <th style="width:25%; text-align:center;">Resultado</th>
    </tr>
  </thead>
  <tbody>
    <?php if ($adExp):
      $adExpCor = str_contains(strtolower($adExp), 'passing') ? '#2E7D32' : (str_contains(strtolower($adExp), 'warning') ? '#E65100' : '#D32F2F');
      $adExpBg  = str_contains(strtolower($adExp), 'passing') ? '#EDF7EE' : (str_contains(strtolower($adExp), 'warning') ? '#FFF9E6' : '#FDECEA');
    ?>
    <tr>
      <td style="font-weight:bold;">Experiência de Anúncios</td>
      <td style="font-size:8.5pt; color:#475569;">Avaliação do Google sobre a qualidade da experiência do usuário com os anúncios exibidos no site.</td>
      <td align="center" bgcolor="<?= $adExpBg ?>" style="color:<?= $adExpCor ?>; font-weight:bold; font-size:8.5pt;"><?= htmlspecialchars($adExp) ?></td>
    </tr>
    <?php endif; ?>
    <?php if ($sb):
      $sbCor = str_contains(strtolower($sb), 'nenhuma') ? '#2E7D32' : (str_contains(strtolower($sb), 'parcialmente') ? '#E65100' : '#D32F2F');
      $sbBg  = str_contains(strtolower($sb), 'nenhuma') ? '#EDF7EE' : (str_contains(strtolower($sb), 'parcialmente') ? '#FFF9E6' : '#FDECEA');
    ?>
    <tr>
      <td style="font-weight:bold;">Navegação Segura</td>
      <td style="font-size:8.5pt; color:#475569;">Verificação se o domínio está na lista de sites perigosos do Google (malware, phishing).</td>
      <td align="center" bgcolor="<?= $sbBg ?>" style="color:<?= $sbCor ?>; font-weight:bold; font-size:8.5pt;"><?= htmlspecialchars($sb) ?></td>
    </tr>
    <?php endif; ?>
    <?php if ($ap):
      $apCor = str_contains(strtolower($ap), 'sem restr') ? '#2E7D32' : (str_contains(strtolower($ap), 'restrição') ? '#E65100' : '#D32F2F');
      $apBg  = str_contains(strtolower($ap), 'sem restr') ? '#EDF7EE' : (str_contains(strtolower($ap), 'restrição') ? '#FFF9E6' : '#FDECEA');
    ?>
    <tr>
      <td style="font-weight:bold;">Políticas do Google Ads</td>
      <td style="font-size:8.5pt; color:#475569;">Status de conformidade com as políticas de publicidade do Google para este domínio.</td>
      <td align="center" bgcolor="<?= $apBg ?>" style="color:<?= $apCor ?>; font-weight:bold; font-size:8.5pt;"><?= htmlspecialchars($ap) ?></td>
    </tr>
    <?php endif; ?>
  </tbody>
</table>

<?php
// Alertas críticos de ads
$alertasHTML = '';
if ($adExp && str_contains(strtolower($adExp), 'failing')) {
    $alertasHTML .= '<div class="alert-danger" style="margin-bottom:8pt;"><strong style="color:#c53030;">Bloqueio de Anúncios Ativo:</strong><span style="display:block; font-size:8.5pt; color:#4a5568; margin-top:3pt; line-height:1.5;">O Google identificou problemas graves na experiência do site. As campanhas de anúncios não estão sendo exibidas neste domínio.</span></div>';
} elseif ($adExp && str_contains(strtolower($adExp), 'warning')) {
    $alertasHTML .= '<div class="alert-warn" style="margin-bottom:8pt;"><strong style="color:#b7791f;">Alerta de Experiência de Anúncios:</strong><span style="display:block; font-size:8.5pt; color:#4a5568; margin-top:3pt; line-height:1.5;">O Google identificou problemas na experiência do site. Risco de bloqueio de anúncios se não corrigido.</span></div>';
}
if ($sb && (str_contains(strtolower($sb), 'lista negra') || str_contains(strtolower($sb), 'perigoso'))) {
    $alertasHTML .= '<div class="alert-danger" style="margin-bottom:8pt;"><strong style="color:#c53030;">Domínio em Lista de Risco:</strong><span style="display:block; font-size:8.5pt; color:#4a5568; margin-top:3pt; line-height:1.5;">O site foi marcado como potencialmente perigoso pelo Google. O Chrome exibe aviso de bloqueio para visitantes e os anúncios são automaticamente suspensos.</span></div>';
}
if ($ap && str_contains(strtolower($ap), 'suspensa')) {
    $alertasHTML .= '<div class="alert-danger" style="margin-bottom:8pt;"><strong style="color:#c53030;">Conta do Google Ads Suspensa:</strong><span style="display:block; font-size:8.5pt; color:#4a5568; margin-top:3pt; line-height:1.5;">Toda a estrutura de anúncios pagos foi bloqueada pelo Google. É necessária intervenção técnica especializada para reativação.</span></div>';
}
echo $alertasHTML;
?>
<?php endif; ?>


<!-- ═══════════════════════ COMPARATIVO VISUAL ═══════════════════════ -->
<?php if ($temComparativo): ?>
<div style="margin-top:14pt;"></div>

<div class="section-header">
  <div class="section-num">Seção 05</div>
  <h1>Situação Atual x Potencial de Melhoria</h1>
</div>

<p>Veja o que é possível alcançar após a implementação das melhorias recomendadas:</p>

<table style="margin-bottom:16pt;">
  <thead>
    <tr>
      <th style="width:30%;">Indicador</th>
      <th style="width:30%; text-align:center; background:#fee2e2; color:#991b1b;">Situação Atual</th>
      <th style="width:10%; text-align:center; background:#f8fafc; color:#64748b;"></th>
      <th style="width:30%; text-align:center; background:#dcfce7; color:#166534;">Após Otimização</th>
    </tr>
  </thead>
  <tbody>
    <?php
    $comparativos = [];
    if (!empty($r['ps_performance_desktop']) && is_numeric($r['ps_performance_desktop'])) {
        $v = (int)$r['ps_performance_desktop'];
        if ($v < 90) $comparativos[] = ['Performance Desktop', $v, '90+', $v < 50 ? '#D32F2F' : '#E65100'];
    }
    if (!empty($r['ps_performance_mobile']) && is_numeric($r['ps_performance_mobile'])) {
        $v = (int)$r['ps_performance_mobile'];
        if ($v < 90) $comparativos[] = ['Performance Mobile', $v, '90+', $v < 50 ? '#D32F2F' : '#E65100'];
    }
    if (!empty($r['ps_seo_desktop']) && is_numeric($r['ps_seo_desktop'])) {
        $v = (int)$r['ps_seo_desktop'];
        if ($v < 90) $comparativos[] = ['SEO Técnico', $v, '95+', $v < 50 ? '#D32F2F' : '#E65100'];
    }
    if ($seg && !$seg['ssl_ativo']) {
        $comparativos[] = ['Segurança SSL', 'Inativo', 'Ativo', '#D32F2F'];
    }
    if ($dns && (!$dns['spf_valido'] || !$dns['dmarc_valido'])) {
        $comparativos[] = ['Proteção de E-mail', 'Vulnerável', 'Protegido', '#E65100'];
    }
    if ($temAds && $D > 5) {
        $comparativos[] = ['Aproveitamento de Ads', round(100 - $D) . '%', '97%+', $D > 30 ? '#D32F2F' : '#E65100'];
    }

    foreach ($comparativos as [$ind, $atual, $meta, $corAtual]):
    ?>
    <tr>
      <td style="font-weight:bold; font-size:8.5pt;"><?= htmlspecialchars($ind) ?></td>
      <td align="center" bgcolor="#fff5f5" style="color:<?= $corAtual ?>; font-weight:bold; font-size:11pt;"><?= is_int($atual) ? $atual : htmlspecialchars($atual) ?></td>
      <td align="center" style="color:#94a3b8; font-size:14pt; font-weight:bold;">→</td>
      <td align="center" bgcolor="#f0fff4" style="color:#2E7D32; font-weight:bold; font-size:11pt;"><?= htmlspecialchars($meta) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($comparativos)): ?>
    <tr>
      <td colspan="4" align="center" style="color:#2E7D32; font-weight:bold; padding:12pt;">
        ✓ Site já está em excelente situação!
      </td>
    </tr>
    <?php endif; ?>
  </tbody>
</table>

<div class="alert-info" style="margin-bottom:16pt;">
  <strong style="color:<?= $AZUL ?>;">O que essas melhorias representam para o seu negócio:</strong>
  <p style="font-size:8.5pt; color:#475569; margin:4pt 0 0 0; line-height:1.5;">Sites com alta performance recebem mais visitantes do Google de forma orgânica, têm menor custo por clique em anúncios, e convertem mais porque oferecem uma experiência de navegação superior. Cada ponto de melhoria no score representa uma vantagem competitiva direta.</p>
</div>
<?php endif; ?>


<!-- ═══════════════════════ PLANO DE AÇÃO ═══════════════════════ -->
<?php if (!empty($acoes)): ?>
<div class="page-break"></div>

<div class="section-header">
  <div class="section-num">Seção <?= $temComparativo ? '06' : '05' ?></div>
  <h1>Plano de Ação Recomendado</h1>
</div>

<?php if ($bloquear): ?>
<div class="alert-warn" style="margin-bottom:14pt;">
  <strong style="color:#b45309;">Cronograma Técnico Protegido</strong>
  <p style="font-size:8.5pt; color:#4a5568; margin:4pt 0 0 0; line-height:1.5;">Para garantir a correta execução e proteger a metodologia proprietária, os detalhamentos técnicos completos são disponibilizados exclusivamente após contratação dos serviços da <strong>Rajo Desenvolvimento</strong>.</p>
</div>
<?php endif; ?>

<table style="margin-bottom:16pt;">
  <thead>
    <tr>
      <th style="width:38%;">Ação Recomendada</th>
      <th style="width:28%;">Benefício Esperado</th>
      <th style="width:16%;">Responsável</th>
      <th style="width:10%; text-align:center;">Prazo</th>
      <th style="width:8%; text-align:center;">Prior.</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($acoes as $i => $acao):
      $pri = strtolower(trim($acao['prioridade'] ?? 'alta'));
      if ($pri === 'alta')      { $priCor = '#D32F2F'; $priBg = '#FDECEA'; $priLabel = 'Alta'; }
      elseif ($pri === 'média') { $priCor = '#E65100'; $priBg = '#FFF9E6'; $priLabel = 'Média'; }
      else                      { $priCor = '#2E7D32'; $priBg = '#EDF7EE'; $priLabel = 'Baixa'; }

      // Gerar benefício automaticamente se não houver campo dedicado
      $beneficio = $acao['beneficio'] ?? '';
      if (empty($beneficio)) {
          $ac = mb_strtolower($acao['acao'] ?? '');
          if (str_contains($ac, 'performance') || str_contains($ac, 'velocidade') || str_contains($ac, 'otimiz')) {
              $beneficio = 'Redução do custo por clique e maior aproveitamento do orçamento de anúncios.';
          } elseif (str_contains($ac, 'seo') || str_contains($ac, 'meta')) {
              $beneficio = 'Melhor posicionamento no Google e aumento do tráfego orgânico.';
          } elseif (str_contains($ac, 'ssl') || str_contains($ac, 'segurança')) {
              $beneficio = 'Aumento da confiança dos visitantes e conformidade com o Google.';
          } elseif (str_contains($ac, 'analytics') || str_contains($ac, 'pixel') || str_contains($ac, 'gtm')) {
              $beneficio = 'Dados precisos para otimização de campanhas e medição de ROI.';
          } else {
              $beneficio = 'Melhoria da experiência do usuário e do desempenho geral.';
          }
      }
    ?>
    <tr>
      <td style="font-size:8.5pt; font-weight:500; line-height:1.4;"><?= ofuscarAcao($acao['acao'], $bloquear) ?></td>
      <td style="font-size:8pt; color:#475569; line-height:1.4;"><?= htmlspecialchars($beneficio) ?></td>
      <td style="font-size:8.5pt; color:#4a5568;"><?= htmlspecialchars($acao['responsavel'] ?? '—') ?></td>
      <td align="center" bgcolor="<?= $AZUL2 ?>" style="color:<?= $AZUL ?>; font-weight:bold; font-size:8pt; white-space:nowrap;"><?= htmlspecialchars($acao['prazo'] ?? '—') ?></td>
      <td align="center" bgcolor="<?= $priBg ?>" style="color:<?= $priCor ?>; font-weight:bold; font-size:7.5pt;"><?= $priLabel ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>


<!-- ═══════════════════════ PARECER EXECUTIVO ═══════════════════════ -->
<?php if ($r['conclusao']): ?>
<div style="margin-top:14pt;"></div>

<div class="section-header">
  <div class="section-num">Seção <?= $temComparativo ? (!empty($acoes) ? '07' : '06') : (!empty($acoes) ? '06' : '05') ?></div>
  <h1>Parecer Executivo</h1>
</div>

<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:16pt 20pt; margin-bottom:16pt;">
  <div style="font-size:8.5pt; font-weight:bold; color:<?= $AZUL ?>; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:10pt; border-bottom:1px solid #e2e8f0; padding-bottom:6pt;">
    Análise e Recomendação — <?= htmlspecialchars($r['analista']) ?>
  </div>
  <?php foreach (explode("\n\n", $r['conclusao']) as $para): ?>
  <p style="text-align:justify; line-height:1.55; margin-bottom:8pt; font-size:9pt; color:#334155;"><?= nl2br(htmlspecialchars(trim($para))) ?></p>
  <?php endforeach; ?>
</div>
<?php endif; ?>


<!-- ═══════════════════════ PRÓXIMOS PASSOS ═══════════════════════ -->
<div style="<?= $r['conclusao'] ? '' : 'page-break-before:always;' ?> margin-top:6pt;">

<div class="section-header">
  <div class="section-num">Seção Final</div>
  <h1>Próximos Passos</h1>
</div>

<p>Para transformar as oportunidades identificadas em resultados reais, recomendamos seguir o roteiro abaixo:</p>

<div style="border:1px solid #e2e8f0; border-radius:10px; padding:14pt 18pt; background:#f8fafc; margin-bottom:16pt;">

  <div style="padding:10pt 0; border-bottom:1px solid #e2e8f0;">
    <table style="border:none; margin:0; width:100%;">
      <tr>
        <td style="border:none; padding:0; width:28pt; vertical-align:middle;">
          <div style="width:22pt; height:22pt; background:<?= $AZUL ?>; border-radius:50%; text-align:center; font-size:10pt; font-weight:bold; color:#fff; line-height:22pt;">1</div>
        </td>
        <td style="border:none; padding:0 0 0 8pt; vertical-align:middle;">
          <div style="font-size:9.5pt; font-weight:bold; color:#1e293b;">Aprovação da Proposta</div>
          <div style="font-size:8.5pt; color:#64748b; margin-top:2pt;">Análise e validação do escopo de trabalho e investimento necessário.</div>
        </td>
      </tr>
    </table>
  </div>

  <div style="padding:10pt 0; border-bottom:1px solid #e2e8f0;">
    <table style="border:none; margin:0; width:100%;">
      <tr>
        <td style="border:none; padding:0; width:28pt; vertical-align:middle;">
          <div style="width:22pt; height:22pt; background:<?= $AZUL ?>; border-radius:50%; text-align:center; font-size:10pt; font-weight:bold; color:#fff; line-height:22pt;">2</div>
        </td>
        <td style="border:none; padding:0 0 0 8pt; vertical-align:middle;">
          <div style="font-size:9.5pt; font-weight:bold; color:#1e293b;">Implementação das Correções</div>
          <div style="font-size:8.5pt; color:#64748b; margin-top:2pt;">Execução técnica das melhorias priorizadas pela equipe especializada da Rajo Desenvolvimento.</div>
        </td>
      </tr>
    </table>
  </div>

  <div style="padding:10pt 0; border-bottom:1px solid #e2e8f0;">
    <table style="border:none; margin:0; width:100%;">
      <tr>
        <td style="border:none; padding:0; width:28pt; vertical-align:middle;">
          <div style="width:22pt; height:22pt; background:<?= $AZUL ?>; border-radius:50%; text-align:center; font-size:10pt; font-weight:bold; color:#fff; line-height:22pt;">3</div>
        </td>
        <td style="border:none; padding:0 0 0 8pt; vertical-align:middle;">
          <div style="font-size:9.5pt; font-weight:bold; color:#1e293b;">Monitoramento dos Resultados</div>
          <div style="font-size:8.5pt; color:#64748b; margin-top:2pt;">Acompanhamento das métricas para validar o impacto real das melhorias implementadas.</div>
        </td>
      </tr>
    </table>
  </div>

  <div style="padding:10pt 0;">
    <table style="border:none; margin:0; width:100%;">
      <tr>
        <td style="border:none; padding:0; width:28pt; vertical-align:middle;">
          <div style="width:22pt; height:22pt; background:<?= $AZUL ?>; border-radius:50%; text-align:center; font-size:10pt; font-weight:bold; color:#fff; line-height:22pt;">4</div>
        </td>
        <td style="border:none; padding:0 0 0 8pt; vertical-align:middle;">
          <div style="font-size:9.5pt; font-weight:bold; color:#1e293b;">Reavaliação do Desempenho</div>
          <div style="font-size:8.5pt; color:#64748b; margin-top:2pt;">Nova rodada de diagnóstico para medir a evolução e identificar as próximas oportunidades de crescimento.</div>
        </td>
      </tr>
    </table>
  </div>

</div>

<!-- CTA final -->
<div style="background:<?= $AZUL ?>; border-radius:10px; padding:16pt 20pt; text-align:center; margin-bottom:16pt;">
  <div style="font-size:11pt; font-weight:bold; color:#fff; margin-bottom:6pt;">Pronto para transformar seu site em uma máquina de resultados?</div>
  <div style="font-size:9pt; color:rgba(255,255,255,0.8); margin-bottom:10pt;">Entre em contato com a equipe da Rajo Desenvolvimento e dê o próximo passo.</div>
  <div style="font-size:9pt; font-weight:bold; color:#fff;">rajo.com.br &nbsp;&bull;&nbsp; contato@rajo.com.br</div>
</div>

</div>

<!-- Rodapé final -->
<div style="border-top:1.5px solid #e2e8f0; padding-top:8pt; text-align:center; margin-top:4pt;">
  <p style="font-size:9pt; color:<?= $AZUL ?>; font-weight:bold; margin-bottom:2pt;">
    <?= htmlspecialchars($r['analista']) ?> &bull; Rajo Desenvolvimento
  </p>
  <p style="font-size:7.5pt; color:#a0aec0; letter-spacing:0.3px; text-transform:uppercase;">
    rajo.com.br &bull; contato@rajo.com.br &bull; Engenharia de Conversão e Otimização Avançada
  </p>
</div>

</body>
</html>
<?php
$html = ob_get_clean();

// ─── Gerar PDF com mPDF ───────────────────────────────────────
$mpdf = new \Mpdf\Mpdf([
    'mode'         => 'utf-8',
    'format'       => 'A4',
    'margin_top'   => 14,
    'margin_right' => 14,
    'margin_bottom'=> 20,
    'margin_left'  => 14,
    'margin_header'=> 7,
    'margin_footer'=> 7,
    'default_font' => 'arial',
    'tempDir'      => sys_get_temp_dir() . '/mpdf',
]);

$screenshot_local = $r['screenshot_path'] ?? null;
if ($screenshot_local && file_exists(__DIR__ . '/' . $screenshot_local)) {
    $mpdf->SetWatermarkImage(__DIR__ . '/' . $screenshot_local, 0.05, 'F');
    $mpdf->showWatermarkImage = true;
}

$mpdf->SetHTMLHeader('
  <table width="100%" style="border-bottom:1px solid ' . $AZUL . '; padding-bottom:4px; border:none; margin:0;">
    <tr>
      <td style="font-size:8pt; color:' . $AZUL . '; font-weight:bold; border:none; padding:0;">RAJO</td>
      <td style="font-size:7.5pt; color:#718096; text-align:right; border:none; padding:0;">
        Diagnóstico Estratégico de SEO &mdash; ' . htmlspecialchars($r['cliente']) . '
      </td>
    </tr>
  </table>
');
$mpdf->SetHTMLFooter('
  <table width="100%" style="border-top:1px solid #e2e8f0; padding-top:4px; border:none; margin:0;">
    <tr>
      <td style="font-size:7pt; color:#a0aec0; border:none; padding:0;">rajo.com.br &bull; Documento Confidencial</td>
      <td style="font-size:7pt; color:#a0aec0; text-align:right; border:none; padding:0;">Página {PAGENO} de {nbpg}</td>
    </tr>
  </table>
');

$mpdf->WriteHTML($html);

$filename = 'Diagnostico_' . preg_replace('/[^a-zA-Z0-9]/', '_', $r['cliente']) . '_' . date('Ymd', strtotime($r['data_relatorio'])) . '.pdf';
$mpdf->Output($filename, 'I');
