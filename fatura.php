<?php
// ============================================================
//  Rajo Diagnóstico — Emissor de Fatura / PDF de Recarga
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/auth.php';

// Apenas usuários logados podem visualizar
exigir_login();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    die('ID de Fatura inválido.');
}

$usuario_id = (int)$_SESSION['usuario_id'];
$is_master = e_master();

// Blindagem de segurança: analistas comuns só acessam suas próprias faturas
if ($is_master) {
    $stmt = db()->prepare("SELECT t.*, u.nome, u.email FROM transacoes t JOIN usuarios u ON t.usuario_id = u.id WHERE t.id = ? AND t.tipo = 'recarga'");
    $stmt->execute([$id]);
} else {
    $stmt = db()->prepare("SELECT t.*, u.nome, u.email FROM transacoes t JOIN usuarios u ON t.usuario_id = u.id WHERE t.id = ? AND t.usuario_id = ? AND t.tipo = 'recarga'");
    $stmt->execute([$id, $usuario_id]);
}
$trans = $stmt->fetch();

if (!$trans) {
    http_response_code(404);
    die('Fatura/Transação não encontrada ou acesso não autorizado.');
}

// Extrai o valor líquido da descrição da transação via regex (ou fallback se não houver cupom)
$valor_bruto = (float)$trans['valor'];
$valor_liquido = $valor_bruto;
$desconto_aplicado = 0.00;
$cupom_utilizado = '';

if (preg_match('/Valor líquido: R\$ ([0-9.,]+)/', $trans['descricao'], $matches)) {
    $valor_liquido = (float)str_replace(['.', ','], ['', '.'], $matches[1]);
    $desconto_aplicado = $valor_bruto - $valor_liquido;
}
if (preg_match('/cupom ([A-Z0-9_-]+)/i', $trans['descricao'], $matchesCupom)) {
    $cupom_utilizado = strtoupper($matchesCupom['1']);
}

// Gera o código PIX Copia e Cola dinâmico e exato no padrão BR Code
$txid = 'REC' . str_pad($trans['id'], 9, '0', STR_PAD_LEFT);
$pix_copia_cola = gerarCopiaColaPix($valor_liquido, $txid);

// URL do QR Code (QR Server API)
$pix_qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($pix_copia_cola);

// Datas da Fatura
$data_emissao = date('d/m/Y H:i', strtotime($trans['criado_em']));
$data_vencimento = date('d/m/Y', strtotime($trans['criado_em'] . ' + 3 days'));

// Início do buffer HTML para renderização no mPDF
ob_start();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #334155;
            background-color: #ffffff;
            font-size: 9.5pt;
            line-height: 1.45;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #1e3c72;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .logo-text {
            font-size: 20pt;
            font-weight: bold;
            color: #1e3c72;
        }
        .logo-sub {
            font-size: 8.5pt;
            color: #64748b;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .fatura-title-box {
            text-align: right;
        }
        .fatura-title {
            font-size: 16pt;
            font-weight: bold;
            color: #1e3c72;
            margin-bottom: 4px;
        }
        .fatura-num {
            font-size: 10pt;
            font-weight: bold;
            color: #64748b;
        }
        .info-table {
            width: 100%;
            margin-bottom: 25px;
        }
        .info-col {
            width: 48%;
            vertical-align: top;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 15px;
        }
        .info-title {
            font-size: 9pt;
            font-weight: bold;
            color: #1e3c72;
            text-transform: uppercase;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
            margin-bottom: 8px;
        }
        .info-text {
            font-size: 8.5pt;
            color: #334155;
        }
        .datas-table {
            width: 100%;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 25px;
            text-align: center;
        }
        .datas-table td {
            font-size: 8.5pt;
            color: #475569;
        }
        .datas-val {
            font-weight: bold;
            color: #1e3c72;
            font-size: 9.5pt;
        }
        .itens-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .itens-table th {
            background-color: #1e3c72;
            color: #ffffff;
            font-weight: bold;
            font-size: 9pt;
            text-transform: uppercase;
            padding: 10px;
            text-align: left;
            border: 1px solid #1e3c72;
        }
        .itens-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 9pt;
        }
        .totais-table {
            width: 100%;
            margin-bottom: 30px;
        }
        .totais-label {
            text-align: right;
            font-size: 9pt;
            color: #64748b;
            padding-right: 15px;
            padding-bottom: 6px;
        }
        .totais-val {
            text-align: right;
            font-size: 9.5pt;
            font-weight: 500;
            color: #334155;
            padding-bottom: 6px;
            width: 100px;
        }
        .totais-label-final {
            text-align: right;
            font-size: 10.5pt;
            font-weight: bold;
            color: #1e3c72;
            padding-right: 15px;
            border-top: 2px solid #cbd5e1;
            padding-top: 8px;
        }
        .totais-val-final {
            text-align: right;
            font-size: 11.5pt;
            font-weight: bold;
            color: #16a34a;
            border-top: 2px solid #cbd5e1;
            padding-top: 8px;
        }
        .pix-section {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            margin-top: 10px;
        }
        .pix-title {
            font-size: 11pt;
            font-weight: bold;
            color: #1e3c72;
            margin-bottom: 8px;
        }
        .pix-desc {
            font-size: 8pt;
            color: #64748b;
            margin-bottom: 15px;
            max-width: 480px;
            margin-left: auto;
            margin-right: auto;
        }
        .pix-qr {
            width: 150px;
            height: 150px;
            margin-bottom: 15px;
            border: 1px solid #e2e8f0;
            padding: 5px;
            background: #ffffff;
            border-radius: 8px;
        }
        .copia-cola-box {
            background: #ffffff;
            border: 1px dashed #cbd5e1;
            padding: 10px;
            font-family: monospace;
            font-size: 7.5pt;
            color: #334155;
            word-wrap: break-word;
            border-radius: 6px;
            text-align: left;
            margin-top: 10px;
            line-height: 1.3;
        }
        .footer-note {
            margin-top: 30px;
            border-top: 1px solid #edf2f7;
            padding-top: 15px;
            text-align: center;
            font-size: 7.5pt;
            color: #94a3b8;
        }
    </style>
</head>
<body>

    <!-- CABEÇALHO DA FATURA -->
    <table class="header-table">
        <tr>
            <td style="border: none; padding: 0;">
                <span class="logo-text">Rajo Diagnóstico</span><br>
                <span class="logo-sub">Engine &amp; Conversão Avançada</span>
            </td>
            <td class="fatura-title-box" style="border: none; padding: 0;">
                <div class="fatura-title">FATURA DE SERVIÇO</div>
                <div class="fatura-num">Nº #FAT-<?= str_pad($trans['id'], 6, '0', STR_PAD_LEFT) ?></div>
            </td>
        </tr>
    </table>

    <!-- DATAS DA FATURA -->
    <table class="datas-table">
        <tr>
            <td>Data de Emissão: <span class="datas-val"><?= $data_emissao ?></span></td>
            <td style="border-left: 1px solid #cbd5e1;">Data de Vencimento: <span class="datas-val"><?= $data_vencimento ?></span></td>
            <td style="border-left: 1px solid #cbd5e1;">Status da Cobrança: 
                <span class="datas-val" style="color: <?= $trans['status'] === 'concluido' ? '#16a34a' : ($trans['status'] === 'rejeitado' ? '#dc2626' : '#ea580c') ?>;">
                    <?= $trans['status'] === 'concluido' ? 'PAGO / CONFIRMADO' : ($trans['status'] === 'rejeitado' ? 'REJEITADO' : 'AGUARDANDO PAGAMENTO') ?>
                </span>
            </td>
        </tr>
    </table>

    <!-- INFORMAÇÕES DE EMISSOR E CLIENTE -->
    <table class="info-table">
        <tr>
            <td class="info-col">
                <div class="info-title">Prestador / Emissor</div>
                <div class="info-text">
                    <strong>Rajo Desenvolvimento Comercial LTDA</strong><br>
                    CNPJ: 28.826.574/0001-32<br>
                    E-mail: central@rajohost.com.br<br>
                    Website: rajo.com.br
                </div>
            </td>
            <td style="width: 4%; border: none;"></td>
            <td class="info-col">
                <div class="info-title">Cliente / Beneficiário</div>
                <div class="info-text">
                    <strong><?= htmlspecialchars($trans['nome']) ?></strong><br>
                    E-mail: <?= htmlspecialchars($trans['email']) ?><br>
                    Vínculo: Analista Credenciado<br>
                    Sistema: Rajo Diagnóstico
                </div>
            </td>
        </tr>
    </table>

    <!-- ITENS DA COBRANÇA -->
    <table class="itens-table">
        <thead>
            <tr>
                <th style="width: 60%; padding-left: 10px;">Descrição do Serviço</th>
                <th style="width: 10%; text-align: center;">Qtd</th>
                <th style="width: 15%; text-align: right;">Preço Unit.</th>
                <th style="width: 15%; text-align: right; padding-right: 10px;">Total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="padding-left: 10px;">
                    <strong>Recarga de Saldo Comercial — Sistema Rajo Diagnóstico</strong><br>
                    <span style="font-size: 8pt; color: #64748b;">Liberação de créditos para emissão e personalização de relatórios completos de auditoria técnica.</span>
                </td>
                <td align="center">1</td>
                <td align="right">R$ <?= number_format($valor_bruto, 2, ',', '.') ?></td>
                <td align="right" style="padding-right: 10px;">R$ <?= number_format($valor_bruto, 2, ',', '.') ?></td>
            </tr>
        </tbody>
    </table>

    <!-- TOTAIS -->
    <table width="100%" class="totais-table">
        <tr>
            <td style="border: none;"></td>
            <td class="totais-label">Subtotal Bruto:</td>
            <td class="totais-val">R$ <?= number_format($valor_bruto, 2, ',', '.') ?></td>
        </tr>
        <?php if ($desconto_aplicado > 0): ?>
        <tr>
            <td style="border: none;"></td>
            <td class="totais-label" style="color: #16a34a;">Cupom de Desconto (<?= htmlspecialchars($cupom_utilizado) ?>):</td>
            <td class="totais-val" style="color: #16a34a;">- R$ <?= number_format($desconto_aplicado, 2, ',', '.') ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <td style="border: none;"></td>
            <td class="totais-label-final">VALOR TOTAL LÍQUIDO:</td>
            <td class="totais-val-final">R$ <?= number_format($valor_liquido, 2, ',', '.') ?></td>
        </tr>
    </table>

    <!-- SEÇÃO PIX PARA PAGAMENTO -->
    <?php if ($trans['status'] === 'pendente'): ?>
    <div class="pix-section">
        <div class="pix-title">Pague com PIX para Liberação de Saldo</div>
        <div class="pix-desc">Escaneie o QR Code abaixo com o aplicativo do seu banco ou utilize a linha "Copia e Cola" fornecida na caixa. Após concluir o pagamento, envie o comprovante para que o administrador faça a liberação do seu saldo.</div>
        
        <img class="pix-qr" src="<?= $pix_qr_url ?>" alt="QR Code PIX"><br>
        
        <strong style="font-size: 8pt; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">Linha PIX Copia e Cola</strong>
        <div class="copia-cola-box"><?= $pix_copia_cola ?></div>
    </div>
    <?php else: ?>
    <div class="pix-section" style="background-color: #f0fdf4; border-color: #bbf7d0; text-align: center; padding: 25px;">
        <span style="font-size: 32pt; color: #16a34a; line-height: 1;">✓</span>
        <div class="pix-title" style="color: #15803d; font-size: 13pt; margin-top: 10px;">Fatura Quitada com Sucesso</div>
        <div class="pix-desc" style="color: #166534; font-size: 8.5pt;">Esta transação foi confirmada e o saldo correspondente de R$ <?= number_format($valor_bruto, 2, ',', '.') ?> foi creditado com sucesso em sua conta de analista no sistema Rajo Diagnóstico. Obrigado!</div>
    </div>
    <?php endif; ?>

    <!-- RODAPÉ DA FATURA -->
    <div class="footer-note">
        Este documento é uma fatura de simulação comercial interna para fins de controle e recarga de saldo no sistema Rajo Diagnóstico.<br>
        <strong>Rajo Desenvolvimento</strong> &bull; contato@rajo.com.br &bull; Todos os direitos reservados.
    </div>

</body>
</html>
<?php
$html = ob_get_clean();

// Inicializa o mPDF para gerar a Fatura
$mpdf = new \Mpdf\Mpdf([
    'mode'          => 'utf-8',
    'format'        => 'A4',
    'margin_top'    => 15,
    'margin_right'  => 15,
    'margin_bottom' => 15,
    'margin_left'   => 15,
    'default_font'  => 'arial',
    'tempDir'       => sys_get_temp_dir() . '/mpdf',
]);

$mpdf->SetHTMLFooter('
  <table width="100%" style="border-top:1px solid #edf2f7;padding-top:4px;border:none;">
    <tr>
      <td style="font-size:7.5pt;color:#a0aec0;border:none;padding:0;">Rajo Diagnóstico &bull; Fatura Simulação</td>
      <td style="font-size:7.5pt;color:#a0aec0;text-align:right;border:none;padding:0;">Página {PAGENO} de {nbpg}</td>
    </tr>
  </table>
');

$mpdf->WriteHTML($html);

$filename = 'Fatura_Rajo_' . str_pad($trans['id'], 6, '0', STR_PAD_LEFT) . '_' . date('Ymd', strtotime($trans['criado_em'])) . '.pdf';
$mpdf->Output($filename, 'I');
