<?php
// ============================================================
//  Rajo Diagnóstico — Rastreador & Auditor de SEO Profundo (cURL Multi)
// ============================================================

header('Content-Type: application/json; charset=utf-8');

$url = trim($_GET['url'] ?? '');

if (empty($url)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'URL do site é obrigatória']);
    exit;
}

// Garante protocolo HTTP/HTTPS na URL
if (!preg_match('#^https?://#i', $url)) {
    $url = 'https://' . $url;
}

$parsedUrl = parse_url($url);
$host = $parsedUrl['host'] ?? '';
$scheme = $parsedUrl['scheme'] ?? 'https';
$baseUrl = $scheme . '://' . $host;

// ─── Passo 1: Busca a página inicial para mapear links e pixels ────────
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
curl_setopt($ch, CURLOPT_TIMEOUT, 6);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) RajoSEOAuditor/1.2');

$homepageHtml = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($homepageHtml === false || $httpCode >= 400) {
    // Tenta em HTTP puro antes de desistir
    if (str_contains($url, 'https://')) {
        $urlHttp = str_replace('https://', 'http://', $url);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $urlHttp);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'RajoSEOAuditor/1.2');
        $homepageHtml = curl_exec($ch);
        curl_close($ch);
    }
}

if ($homepageHtml === false) {
    echo json_encode([
        'ok'  => false,
        'msg' => 'Não foi possível acessar a página do cliente. Verifique se o site está no ar.'
    ]);
    exit;
}

// ─── Passo 2: Detecta pixels globais na Homepage ──────────────────────
$contemGTM      = str_contains($homepageHtml, 'googletagmanager.com/gtm.js') || preg_match('/GTM-[A-Z0-9]+/i', $homepageHtml);
$contemGA4      = str_contains($homepageHtml, 'googletagmanager.com/gtag/js') || preg_match('/G-[A-Z0-9]+/i', $homepageHtml) || str_contains($homepageHtml, 'analytics.js');
$contemFacebook = str_contains($homepageHtml, 'connect.facebook.net') || str_contains($homepageHtml, 'fbq(') || str_contains($homepageHtml, 'fbevents.js');
$contemTikTok   = str_contains($homepageHtml, 'analytics.tiktok.com') || str_contains($homepageHtml, 'ttq.load') || str_contains($homepageHtml, 'ttq.page');

// ─── Passo 3: Extrai links internos da Homepage (limite de 10) ────────
preg_match_all('/href=["\']([^"\']+)["\']/i', $homepageHtml, $matches);
$rawLinks = $matches[1] ?? [];
$internalUrls = [$url]; // A própria home é a primeira URL

foreach ($rawLinks as $link) {
    $link = trim($link);
    
    // Ignora hashes, emails, whatsapps e javascripts
    if (empty($link) || str_starts_with($link, '#') || str_starts_with($link, 'mailto:') || str_starts_with($link, 'tel:') || str_starts_with($link, 'javascript:') || str_starts_with($link, 'whatsapp:')) {
        continue;
    }
    
    // Normaliza link relativo
    $absLink = $link;
    if (!preg_match('#^https?://#i', $link)) {
        if (str_starts_with($link, '/')) {
            $absLink = $baseUrl . $link;
        } else {
            $absLink = rtrim($url, '/') . '/' . $link;
        }
    }
    
    // Filtra links externos e extensões indesejadas (imagens, PDFs, CSS, JS, etc.)
    $parsedLink = parse_url($absLink);
    $linkHost = $parsedLink['host'] ?? '';
    
    if ($linkHost !== $host) {
        continue; // link externo
    }
    
    $path = $parsedLink['path'] ?? '';
    if (preg_match('/\.(png|jpe?g|gif|webp|pdf|zip|gz|xml|css|js|svg|ico)$/i', $path)) {
        continue; // arquivo estático
    }
    
    // Remove hashtags e query strings de rastreamento para evitar duplicidade
    $cleanLink = ($parsedLink['scheme'] ?? 'https') . '://' . ($parsedLink['host'] ?? '') . ($parsedLink['path'] ?? '');
    if (!empty($parsedLink['query'])) {
        $cleanLink .= '?' . $parsedLink['query'];
    }
    
    if (!in_array($cleanLink, $internalUrls)) {
        $internalUrls[] = $cleanLink;
    }
    
    if (count($internalUrls) >= 10) {
        break; // limita a 10 links únicos
    }
}

// ─── Passo 4: Executa cURL Multi paralelo para ler os links coletados ────
$mh = curl_multi_init();
$handles = [];

foreach ($internalUrls as $crawlUrl) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $crawlUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 4); // Timeout ágil por página
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) RajoSEOAuditor/1.2');
    
    curl_multi_add_handle($mh, $ch);
    $handles[$crawlUrl] = $ch;
}

$active = null;
do {
    $mrc = curl_multi_exec($mh, $active);
} while ($mrc == CURLM_CALL_MULTI_PERFORM);

while ($active && $mrc == CURLM_OK) {
    if (curl_multi_select($mh) == -1) {
        usleep(100);
    }
    do {
        $mrc = curl_multi_exec($mh, $active);
    } while ($mrc == CURLM_CALL_MULTI_PERFORM);
}

// ─── Passo 5: Analisa o HTML de cada página e executa auditoria técnica ─
$pagesAudited = [];
$totImgs = 0;
$totMissingAlt = 0;
$unfriendlyUrls = 0;
$nonMobileFriendly = 0;
$missingTitles = 0;
$missingDescriptions = 0;

foreach ($handles as $crawlUrl => $ch) {
    $htmlContent = curl_multi_getcontent($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);
    
    if ($httpCode >= 400 || empty($htmlContent)) {
        continue; // ignora páginas quebradas no rastreamento
    }
    
    // A. Análise de Title Tag
    $title = 'Ausente';
    $titleStatus = 'Ausente';
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $htmlContent, $titleMatch)) {
        $title = trim(strip_tags($titleMatch[1]));
        $len = mb_strlen($title);
        if ($len === 0) {
            $titleStatus = 'Ausente';
        } elseif ($len < 25) {
            $titleStatus = 'Curto';
            $missingTitles++;
        } elseif ($len > 70) {
            $titleStatus = 'Longo';
            $missingTitles++;
        } else {
            $titleStatus = 'OK';
        }
    } else {
        $missingTitles++;
    }
    
    // B. Análise de Meta Description
    $metaDesc = 'Ausente';
    $metaStatus = 'Ausente';
    if (preg_match('/<meta\s+[^>]*name=["\']description["\'][^>]*content=["\']([^"\']*)["\']/is', $htmlContent, $descMatch) ||
        preg_match('/<meta\s+[^>]*content=["\']([^"\']*)["\'][^>]*name=["\']description["\']/is', $htmlContent, $descMatch)) {
        $metaDesc = trim($descMatch[1]);
        $len = mb_strlen($metaDesc);
        if ($len === 0) {
            $metaStatus = 'Ausente';
            $missingDescriptions++;
        } elseif ($len < 70) {
            $metaStatus = 'Curto';
            $missingDescriptions++;
        } elseif ($len > 165) {
            $metaStatus = 'Longo';
            $missingDescriptions++;
        } else {
            $metaStatus = 'OK';
        }
    } else {
        $missingDescriptions++;
    }
    
    // C. URLs Amigáveis
    $parsed = parse_url($crawlUrl);
    $query = $parsed['query'] ?? '';
    $friendly = true;
    if (!empty($query)) {
        // Ignora query strings comuns de rastreamento de anúncios (utm, gclid, fbclid)
        $params = [];
        parse_str($query, $params);
        $cleanParams = array_filter(array_keys($params), function($k) {
            return !in_array($k, ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid', 'gclsrc']);
        });
        if (count($cleanParams) > 0) {
            $friendly = false;
            $unfriendlyUrls++;
        }
    }
    
    // D. Viewport / Mobile Friendly
    $mobileFriendly = (bool)preg_match('/<meta\s+[^>]*name=["\']viewport["\']/is', $htmlContent);
    if (!$mobileFriendly) {
        $nonMobileFriendly++;
    }
    
    // E. Alt Text de Imagens
    $imgCount = preg_match_all('/<img\s+[^>]*>/is', $htmlContent, $imgMatches);
    $missingAlt = 0;
    if ($imgCount > 0) {
        $totImgs += $imgCount;
        foreach ($imgMatches[0] as $imgTag) {
            if (!preg_match('/\balt\s*=\s*["\']/is', $imgTag) || preg_match('/\balt\s*=\s*["\']\s*["\']/is', $imgTag)) {
                $missingAlt++;
            }
        }
        $totMissingAlt += $missingAlt;
    }
    
    // Adiciona ao array de páginas analisadas
    $pagesAudited[] = [
        'url'             => str_replace($baseUrl, '', $crawlUrl) ?: '/',
        'title'           => $title,
        'title_status'    => $titleStatus,
        'description'     => mb_strimwidth($metaDesc, 0, 80, '...'),
        'desc_status'     => $metaStatus,
        'friendly_url'    => $friendly,
        'mobile_friendly' => $mobileFriendly,
        'total_images'    => $imgCount,
        'missing_alt'     => $missingAlt
    ];
}

curl_multi_close($mh);

// ─── Passo 6: Retorna o resultado final estruturado ──────────────────
echo json_encode([
    'ok'       => true,
    'gtm'      => $contemGTM,
    'ga4'      => $contemGA4,
    'facebook' => $contemFacebook,
    'tiktok'   => $contemTikTok,
    
    // Estatísticas da Auditoria Profunda
    'audit_summary' => [
        'pages_crawled'        => count($pagesAudited),
        'total_images'         => $totImgs,
        'missing_alt_images'   => $totMissingAlt,
        'unfriendly_urls'      => $unfriendlyUrls,
        'non_mobile_friendly'  => $nonMobileFriendly,
        'missing_titles'       => $missingTitles,
        'missing_descriptions' => $missingDescriptions
    ],
    'pages' => $pagesAudited
]);
