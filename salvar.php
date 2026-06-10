<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
exigir_login();

// Configurações de tratamento e gravação de logs
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/sistema.log');

// Manipulador de erros personalizado para registrar warnings/notices no arquivo de log
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return;
    }
    $log_message = sprintf(
        "[%s] [ERROR_HANDLER] %s em %s na linha %d\n",
        date('Y-m-d H:i:s'),
        $message,
        $file,
        $line
    );
    @file_put_contents(__DIR__ . '/logs/sistema.log', $log_message, FILE_APPEND);
});

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método não permitido']);
    exit;
}

try {
    $id = (int)($_POST['id'] ?? 0);
    $usuario_id = (int)$_SESSION['usuario_id'];

    // Validação estrita de saldo/bônus para analistas comuns na criação de novos relatórios
    if ($id === 0 && !e_master()) {
        if (!verificarSaldoEmissao($usuario_id)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'msg' => 'Saldo insuficiente para a emissão deste relatório de diagnóstico. Adicione saldo na sua conta.']);
            exit;
        }
    }

    // Tratamento de posse e multitenancy do SaaS
    if ($id > 0) {
        // Busca proprietário atual do relatório
        $chk = db()->prepare("SELECT usuario_id FROM relatorios WHERE id = ?");
        $chk->execute([$id]);
        $rel_original = $chk->fetch();

        if (!$rel_original) {
            echo json_encode(['ok' => false, 'msg' => 'Relatório não encontrado.']);
            exit;
        }

        // Se for analista comum, restringe acesso apenas aos seus relatórios
        if (!e_master() && (int)$rel_original['usuario_id'] !== $usuario_id) {
            echo json_encode(['ok' => false, 'msg' => 'Acesso não autorizado. Este relatório pertence a outro analista.']);
            exit;
        }

        // Mantém o proprietário original do relatório na edição
        $usuario_id_relatorio = $rel_original['usuario_id'] !== null ? (int)$rel_original['usuario_id'] : $usuario_id;
    } else {
        // Inserção: vincula o relatório ao usuário ativo
        $usuario_id_relatorio = $usuario_id;
    }

    // Decodifica campos complexos que podem vir codificados em Base64 para contornar restrições de WAF/ModSecurity de produção
    $conclusao = isset($_POST['conclusao_b64']) ? base64_decode($_POST['conclusao_b64']) : ($_POST['conclusao'] ?? '');
    $obs_pagespeed = isset($_POST['obs_pagespeed_b64']) ? base64_decode($_POST['obs_pagespeed_b64']) : ($_POST['obs_pagespeed'] ?? '');
    
    $auditoria_cms = isset($_POST['auditoria_cms_b64']) ? base64_decode($_POST['auditoria_cms_b64']) : ($_POST['auditoria_cms'] ?? null);
    $auditoria_hospedagem = isset($_POST['auditoria_hospedagem_b64']) ? base64_decode($_POST['auditoria_hospedagem_b64']) : ($_POST['auditoria_hospedagem'] ?? null);
    $auditoria_seguranca = isset($_POST['auditoria_seguranca_b64']) ? base64_decode($_POST['auditoria_seguranca_b64']) : ($_POST['auditoria_seguranca'] ?? null);
    $auditoria_dns = isset($_POST['auditoria_dns_b64']) ? base64_decode($_POST['auditoria_dns_b64']) : ($_POST['auditoria_dns'] ?? null);
    $screenshot_path = isset($_POST['screenshot_path_b64']) ? base64_decode($_POST['screenshot_path_b64']) : ($_POST['screenshot_path'] ?? null);

    // Sanitiza o caminho do screenshot: este valor é aberto pelo mPDF no servidor,
    // então não pode conter path traversal, caminhos absolutos ou extensão não-imagem
    if ($screenshot_path !== null && trim($screenshot_path) !== '') {
        $screenshot_path = str_replace('\\', '/', trim($screenshot_path));
        $eh_relativa  = $screenshot_path[0] !== '/' && !preg_match('/^[A-Za-z]:/', $screenshot_path);
        $sem_travessia = !str_contains($screenshot_path, '..');
        $eh_imagem    = (bool) preg_match('/\.(png|jpe?g|webp|gif)$/i', $screenshot_path);
        if (!$eh_relativa || !$sem_travessia || !$eh_imagem) {
            $screenshot_path = null;
        }
    }
    
    $ads_nicho = isset($_POST['ads_nicho_b64']) ? base64_decode($_POST['ads_nicho_b64']) : ($_POST['ads_nicho'] ?? null);

    // ─── Campos simples ────────────────────────────────────────
    $campos = [
        'cliente'                   => trim($_POST['cliente'] ?? ''),
        'dominio'                   => trim($_POST['dominio'] ?? ''),
        'data_relatorio'            => $_POST['data_relatorio'] ?? date('Y-m-d'),
        'analista'                  => trim($_POST['analista'] ?? ANALISTA_PADRAO),
        'versao'                    => trim($_POST['versao'] ?? '1.0'),
        'resultado_geral'           => trim($_POST['resultado_geral'] ?? 'CRÍTICO'),
        // PageSpeed
        'ps_performance_desktop'    => intOrNull($_POST['ps_performance_desktop']  ?? ''),
        'ps_performance_mobile'     => intOrNull($_POST['ps_performance_mobile']   ?? ''),
        'ps_seo_desktop'            => intOrNull($_POST['ps_seo_desktop']           ?? ''),
        'ps_seo_mobile'             => intOrNull($_POST['ps_seo_mobile']            ?? ''),
        'ps_acessibilidade_desktop' => intOrNull($_POST['ps_acessibilidade_desktop'] ?? ''),
        'ps_acessibilidade_mobile'  => intOrNull($_POST['ps_acessibilidade_mobile']  ?? ''),
        'ps_boaspraticas_desktop'   => intOrNull($_POST['ps_boaspraticas_desktop']  ?? ''),
        'ps_boaspraticas_mobile'    => intOrNull($_POST['ps_boaspraticas_mobile']   ?? ''),
        // GTmetrix / Ads
        'gtm_nota'                  => trim($_POST['gtm_nota'] ?? ''),
        'ad_experience_status'      => trim($_POST['ad_experience_status'] ?? ''),
        'safe_browsing_status'      => trim($_POST['safe_browsing_status']  ?? ''),
        'ads_policy_status'         => trim($_POST['ads_policy_status']     ?? ''),
        // CWV
        'cwv_lcp_desktop'   => substr(trim($_POST['cwv_lcp_desktop']   ?? ''), 0, 20),
        'cwv_inp_desktop'   => substr(trim($_POST['cwv_inp_desktop']   ?? ''), 0, 20),
        'cwv_cls_desktop'   => substr(trim($_POST['cwv_cls_desktop']   ?? ''), 0, 20),
        'cwv_fcp_desktop'   => substr(trim($_POST['cwv_fcp_desktop']   ?? ''), 0, 20),
        'cwv_ttfb_desktop'  => substr(trim($_POST['cwv_ttfb_desktop']  ?? ''), 0, 20),
        'cwv_speed_desktop' => substr(trim($_POST['cwv_speed_desktop'] ?? ''), 0, 20),
        'cwv_lcp_mobile'    => substr(trim($_POST['cwv_lcp_mobile']    ?? ''), 0, 20),
        'cwv_inp_mobile'    => substr(trim($_POST['cwv_inp_mobile']    ?? ''), 0, 20),
        'cwv_cls_mobile'    => substr(trim($_POST['cwv_cls_mobile']    ?? ''), 0, 20),
        'cwv_fcp_mobile'    => substr(trim($_POST['cwv_fcp_mobile']    ?? ''), 0, 20),
        'cwv_ttfb_mobile'   => substr(trim($_POST['cwv_ttfb_mobile']   ?? ''), 0, 20),
        'cwv_speed_mobile'  => substr(trim($_POST['cwv_speed_mobile']  ?? ''), 0, 20),
        'cwv_lcp_status'    => trim($_POST['cwv_lcp_status']    ?? 'Ruim'),
        'cwv_inp_status'    => trim($_POST['cwv_inp_status']    ?? 'Ruim'),
        'cwv_cls_status'    => trim($_POST['cwv_cls_status']    ?? 'Ruim'),
        'cwv_fcp_status'    => trim($_POST['cwv_fcp_status']    ?? 'Ruim'),
        'cwv_ttfb_status'   => trim($_POST['cwv_ttfb_status']   ?? 'Ruim'),
        'cwv_speed_status'  => trim($_POST['cwv_speed_status']  ?? 'Ruim'),
        'conclusao'         => trim($conclusao),
        'obs_pagespeed'     => trim($obs_pagespeed),
        'pdf_cor_tema'      => trim($_POST['pdf_cor_tema']  ?? '#1A4FBB'),
        'logo_cliente'      => trim($_POST['logo_cliente']  ?? ''),
        'bloquear_plano'    => (int)($_POST['bloquear_plano'] ?? 0),
        'auditoria_cms'        => ($auditoria_cms !== null && trim($auditoria_cms) !== '') ? trim($auditoria_cms) : null,
        'auditoria_hospedagem' => ($auditoria_hospedagem !== null && trim($auditoria_hospedagem) !== '') ? trim($auditoria_hospedagem) : null,
        'auditoria_seguranca'  => ($auditoria_seguranca !== null && trim($auditoria_seguranca) !== '') ? trim($auditoria_seguranca) : null,
        'auditoria_dns'        => ($auditoria_dns !== null && trim($auditoria_dns) !== '') ? trim($auditoria_dns) : null,
        'tipo_relatorio'       => trim($_POST['tipo_relatorio'] ?? 'completo'),
        'ads_nicho'            => ($ads_nicho !== null && trim($ads_nicho) !== '') ? trim($ads_nicho) : null,
        'ads_investimento'     => (isset($_POST['ads_investimento']) && trim($_POST['ads_investimento']) !== '') ? (float)$_POST['ads_investimento'] : null,
        'ads_cpc'              => (isset($_POST['ads_cpc']) && trim($_POST['ads_cpc']) !== '') ? (float)$_POST['ads_cpc'] : null,
        'screenshot_path'      => ($screenshot_path !== null && trim($screenshot_path) !== '') ? trim($screenshot_path) : null,
        'usuario_id'        => $usuario_id_relatorio,
    ];

    if (empty($campos['cliente'])) {
        echo json_encode(['ok' => false, 'msg' => 'Nome do cliente é obrigatório']);
        exit;
    }

    // ─── Arrays dinâmicos ──────────────────────────────────────
    $problemas = [];
    if (isset($_POST['problemas_b64'])) {
        $prob_json = base64_decode($_POST['problemas_b64']);
        $problemas = json_decode($prob_json, true) ?: [];
    } else {
        foreach (($_POST['problemas'] ?? []) as $p) {
            if (!empty(trim($p['problema'] ?? ''))) {
                $problemas[] = [
                    'problema'   => trim($p['problema']),
                    'impacto'    => trim($p['impacto'] ?? ''),
                    'prioridade' => trim($p['prioridade'] ?? 'Alta'),
                ];
            }
        }
    }
    $campos['problemas'] = json_encode($problemas, JSON_UNESCAPED_UNICODE);

    $acoes = [];
    if (isset($_POST['acoes_b64'])) {
        $acao_json = base64_decode($_POST['acoes_b64']);
        $acoes = json_decode($acao_json, true) ?: [];
    } else {
        foreach (($_POST['acoes'] ?? []) as $a) {
            if (!empty(trim($a['acao'] ?? ''))) {
                $acoes[] = [
                    'acao'        => trim($a['acao']),
                    'responsavel' => trim($a['responsavel'] ?? 'Desenvolvedor'),
                    'prazo'       => trim($a['prazo'] ?? ''),
                ];
            }
        }
    }
    $campos['acoes'] = json_encode($acoes, JSON_UNESCAPED_UNICODE);

    // ─── INSERT ou UPDATE ──────────────────────────────────────
    if ($id > 0) {
        $sets = implode(', ', array_map(fn($k) => "`$k` = :$k", array_keys($campos)));
        $sql  = "UPDATE relatorios SET $sets WHERE id = :__id";
        $campos['__id'] = $id;
        $stmt = db()->prepare($sql);
        $stmt->execute($campos);
        $newId = $id;
    } else {
        // Novo relatório recebe um token público aleatório para o link de compartilhamento
        $campos['token_publico'] = bin2hex(random_bytes(24));

        $cols = implode(', ', array_map(fn($k) => "`$k`", array_keys($campos)));
        $vals = implode(', ', array_map(fn($k) => ":$k", array_keys($campos)));
        $sql  = "INSERT INTO relatorios ($cols) VALUES ($vals)";
        $stmt = db()->prepare($sql);
        $stmt->execute($campos);
        $newId = (int)db()->lastInsertId();

        // Realiza o débito financeiro ou consome o bônus se for um novo relatório e usuário comum
        if (!e_master()) {
            $debitou = debitarEmissaoRelatorio($usuario_id, $campos['dominio']);
            if (!$debitou) {
                // Se falhar o débito de forma inesperada, remove o relatório criado preventivamente
                $del = db()->prepare("DELETE FROM relatorios WHERE id = ?");
                $del->execute([$newId]);
                echo json_encode(['ok' => false, 'msg' => 'Erro ao processar cobrança da emissão. Verifique seu saldo.']);
                exit;
            }
        }
    }

    // --- NOVO: CAPTURA AUTOMÁTICA DE SCREENSHOT VIA API MSHOTS DO WORDPRESS ---
    if (!empty($campos['dominio'])) {
        $dom = $campos['dominio'];
        // Limpa o domínio (remove http:// ou https:// e barras)
        $dom_limpo = preg_replace('#^https?://#i', '', $dom);
        $dom_limpo = rtrim(explode('/', $dom_limpo)[0], '/');
        
        $screenshot_dir = __DIR__ . '/uploads/screenshots';
        if (!is_dir($screenshot_dir)) {
            @mkdir($screenshot_dir, 0755, true);
        }
        
        $local_file = "uploads/screenshots/screenshot_{$newId}.jpg";
        $local_path = __DIR__ . '/' . $local_file;
        
        // Faz o download se o arquivo local não existe, ou se foi solicitado um novo diagnóstico
        if (!file_exists($local_path) || (int)($_POST['id'] ?? 0) === 0) {
            $mshots_url = "https://public-api.wordpress.com/rest/v1.1/mshots/v1/" . urlencode("https://" . $dom_limpo) . "?w=1024";
            
            // Faz o download de forma resiliente via cURL com timeout curto de 6s para não travar o salvar
            $ch = curl_init($mshots_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 6);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) RajoSEOAuditor/1.2');
            $img_data = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($img_data && $http_code === 200 && strlen($img_data) > 1000) {
                // Salva no disco
                @file_put_contents($local_path, $img_data);
                
                // Atualiza o banco de dados com o caminho
                $upd = db()->prepare("UPDATE relatorios SET screenshot_path = ? WHERE id = ?");
                $upd->execute([$local_file, $newId]);
            }
        }
    }

    echo json_encode([
        'ok'     => true,
        'id'     => $newId,
        'msg'    => 'Relatório salvo com sucesso!',
        'pdf_url'=> "pdf.php?id=$newId",
    ]);

} catch (Throwable $e) {
    $log_message = sprintf(
        "[%s] [FATAL_EXCEPTION] %s em %s na linha %d\nStack Trace:\n%s\n",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    @file_put_contents(__DIR__ . '/logs/sistema.log', $log_message, FILE_APPEND);
    
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}

function intOrNull(string $v): ?int {
    $v = trim($v);
    return $v === '' ? null : (int)$v;
}
