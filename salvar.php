<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
exigir_login();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método não permitido']);
    exit;
}

try {
    $id = (int)($_POST['id'] ?? 0);
    $usuario_id = (int)$_SESSION['usuario_id'];

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
        'cwv_lcp_desktop'   => trim($_POST['cwv_lcp_desktop']   ?? ''),
        'cwv_inp_desktop'   => trim($_POST['cwv_inp_desktop']   ?? ''),
        'cwv_cls_desktop'   => trim($_POST['cwv_cls_desktop']   ?? ''),
        'cwv_fcp_desktop'   => trim($_POST['cwv_fcp_desktop']   ?? ''),
        'cwv_ttfb_desktop'  => trim($_POST['cwv_ttfb_desktop']  ?? ''),
        'cwv_speed_desktop' => trim($_POST['cwv_speed_desktop'] ?? ''),
        'cwv_lcp_mobile'    => trim($_POST['cwv_lcp_mobile']    ?? ''),
        'cwv_inp_mobile'    => trim($_POST['cwv_inp_mobile']    ?? ''),
        'cwv_cls_mobile'    => trim($_POST['cwv_cls_mobile']    ?? ''),
        'cwv_fcp_mobile'    => trim($_POST['cwv_fcp_mobile']    ?? ''),
        'cwv_ttfb_mobile'   => trim($_POST['cwv_ttfb_mobile']   ?? ''),
        'cwv_speed_mobile'  => trim($_POST['cwv_speed_mobile']  ?? ''),
        'cwv_lcp_status'    => trim($_POST['cwv_lcp_status']    ?? 'Ruim'),
        'cwv_inp_status'    => trim($_POST['cwv_inp_status']    ?? 'Ruim'),
        'cwv_cls_status'    => trim($_POST['cwv_cls_status']    ?? 'Ruim'),
        'cwv_fcp_status'    => trim($_POST['cwv_fcp_status']    ?? 'Ruim'),
        'cwv_ttfb_status'   => trim($_POST['cwv_ttfb_status']   ?? 'Ruim'),
        'cwv_speed_status'  => trim($_POST['cwv_speed_status']  ?? 'Ruim'),
        'conclusao'         => trim($_POST['conclusao']    ?? ''),
        'obs_pagespeed'     => trim($_POST['obs_pagespeed'] ?? ''),
        'pdf_cor_tema'      => trim($_POST['pdf_cor_tema']  ?? '#1A4FBB'),
        'logo_cliente'      => trim($_POST['logo_cliente']  ?? ''),
        'bloquear_plano'    => (int)($_POST['bloquear_plano'] ?? 0),
        'usuario_id'        => $usuario_id_relatorio,
    ];

    if (empty($campos['cliente'])) {
        echo json_encode(['ok' => false, 'msg' => 'Nome do cliente é obrigatório']);
        exit;
    }

    // ─── Arrays dinâmicos ──────────────────────────────────────
    $problemas = [];
    foreach (($_POST['problemas'] ?? []) as $p) {
        if (!empty(trim($p['problema'] ?? ''))) {
            $problemas[] = [
                'problema'   => trim($p['problema']),
                'impacto'    => trim($p['impacto'] ?? ''),
                'prioridade' => trim($p['prioridade'] ?? 'Alta'),
            ];
        }
    }
    $campos['problemas'] = json_encode($problemas, JSON_UNESCAPED_UNICODE);

    $acoes = [];
    foreach (($_POST['acoes'] ?? []) as $a) {
        if (!empty(trim($a['acao'] ?? ''))) {
            $acoes[] = [
                'acao'        => trim($a['acao']),
                'responsavel' => trim($a['responsavel'] ?? 'Desenvolvedor'),
                'prazo'       => trim($a['prazo'] ?? ''),
            ];
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
        $cols = implode(', ', array_map(fn($k) => "`$k`", array_keys($campos)));
        $vals = implode(', ', array_map(fn($k) => ":$k", array_keys($campos)));
        $sql  = "INSERT INTO relatorios ($cols) VALUES ($vals)";
        $stmt = db()->prepare($sql);
        $stmt->execute($campos);
        $newId = (int)db()->lastInsertId();
    }

    echo json_encode([
        'ok'     => true,
        'id'     => $newId,
        'msg'    => 'Relatório salvo com sucesso!',
        'pdf_url'=> "pdf.php?id=$newId",
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}

function intOrNull(string $v): ?int {
    $v = trim($v);
    return $v === '' ? null : (int)$v;
}
