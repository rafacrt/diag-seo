<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
exigir_login();

$busca = trim($_GET['q'] ?? '');
$page  = max(1, (int)($_GET['p'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

try {
    // Migração automática e silenciosa para suportar erros descritivos do GTmetrix
    try {
        db()->exec("ALTER TABLE relatorios MODIFY COLUMN gtm_nota VARCHAR(50) NULL");
    } catch (Throwable $e_migracao) {
        // Ignora silenciosamente se a tabela ainda não existir ou se a coluna já estiver alterada
    }

    // Migração para suportar cores, logos e bloqueio comercial do plano de ação (executado individualmente para compatibilidade com MySQL/MariaDB)
    try {
        db()->exec("ALTER TABLE relatorios ADD COLUMN pdf_cor_tema VARCHAR(7) DEFAULT '#1A4FBB'");
    } catch (Throwable $e) {}
    try {
        db()->exec("ALTER TABLE relatorios ADD COLUMN logo_cliente VARCHAR(255) DEFAULT NULL");
    } catch (Throwable $e) {}
    try {
        db()->exec("ALTER TABLE relatorios ADD COLUMN bloquear_plano TINYINT(1) DEFAULT 0");
    } catch (Throwable $e) {}

    $usuario_id = (int)$_SESSION['usuario_id'];
    $is_master = e_master();

    if ($is_master) {
        if ($busca) {
            $where = 'WHERE r.cliente LIKE :q OR r.dominio LIKE :q';
            $params_total = [':q' => "%$busca%"];
        } else {
            $where = '';
            $params_total = [];
        }
    } else {
        if ($busca) {
            $where = 'WHERE r.usuario_id = :usuario_id AND (r.cliente LIKE :q OR r.dominio LIKE :q)';
            $params_total = [':usuario_id' => $usuario_id, ':q' => "%$busca%"];
        } else {
            $where = 'WHERE r.usuario_id = :usuario_id';
            $params_total = [':usuario_id' => $usuario_id];
        }
    }

    $total = db()->prepare("SELECT COUNT(*) FROM relatorios r $where");
    $total->execute($params_total);
    $total = (int)$total->fetchColumn();

    $stmt = db()->prepare("SELECT r.id, r.cliente, r.dominio, r.data_relatorio, r.resultado_geral, r.criado_em, u.nome as analista_nome
                            FROM relatorios r
                            LEFT JOIN usuarios u ON r.usuario_id = u.id
                            $where
                            ORDER BY r.criado_em DESC
                            LIMIT :limit OFFSET :offset");
    if (!$is_master) {
        $stmt->bindValue(':usuario_id', $usuario_id, PDO::PARAM_INT);
    }
    if ($busca) $stmt->bindValue(':q', "%$busca%");
    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $relatorios = $stmt->fetchAll();
    $pages = (int)ceil($total / $limit);

    // Buscar avisos globais ativos no sistema
    $avisos_sistema = db()->query("SELECT titulo, mensagem, tipo FROM avisos WHERE ativo = 1 ORDER BY criado_em DESC")->fetchAll();
} catch (PDOException $e) {
    $erro = $e->getMessage();
    $relatorios = [];
    $total = 0;
    $pages = 0;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard — <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="assets/style.css?v=<?= time() ?>">
</head>
<body>

<nav class="navbar navbar-dark rajo-navbar px-4">
  <div class="container-fluid d-flex justify-content-between align-items-center">
    <span class="navbar-brand fw-bold fs-4 d-flex align-items-center gap-2" style="font-family: var(--font-title);">
      <span class="rajo-logo-icon">R</span> Rajo Diagnóstico
    </span>
    <span class="text-white-50 small d-none d-lg-block" style="font-weight: 500;">Painel de Controle e Auditoria Técnica de Sites</span>
    <div class="d-flex align-items-center gap-3">
      <?php if (e_master()): ?>
      <a href="admin.php" class="btn btn-sm btn-warning px-3 py-1.5 d-inline-flex align-items-center gap-1" style="border-radius: 8px; font-weight: 600; font-size: 0.85rem; color: #1e293b;">
        <i class="bi bi-shield-lock-fill"></i> Administração
      </a>
      <?php endif; ?>
      <div class="text-white small d-none d-md-flex align-items-center gap-2">
        <i class="bi bi-person-circle text-white-50"></i>
        <span>Olá, <strong class="text-white"><?= e($_SESSION['usuario_nome'] ?? 'Analista') ?></strong></span>
      </div>
      <a href="logout.php" class="btn btn-sm btn-outline-light px-3 py-1.5 d-inline-flex align-items-center gap-1" style="border-radius: 8px; font-weight: 500; font-size: 0.85rem; border-color: rgba(255,255,255,0.25);">
        <i class="bi bi-box-arrow-right"></i> Sair
      </a>
    </div>
  </div>
</nav>

<div class="container-fluid px-4 py-5" style="max-width: 1400px;">

  <!-- Avisos Globais Ativos -->
  <?php if (!empty($avisos_sistema)): ?>
    <?php foreach ($avisos_sistema as $aviso): ?>
      <div class="alert alert-<?= $aviso['tipo'] ?> border-0 shadow-sm d-flex align-items-start gap-3 p-4 mb-4" style="border-radius: 16px;">
        <?php 
          $icon = match($aviso['tipo']) {
            'danger' => 'exclamation-octagon-fill',
            'warning' => 'exclamation-triangle-fill',
            'success' => 'check-circle-fill',
            default => 'megaphone-fill'
          };
        ?>
        <i class="bi bi-<?= $icon ?> fs-3 text-<?= $aviso['tipo'] ?>"></i>
        <div>
          <h6 class="fw-bold mb-1 text-dark" style="font-family: var(--font-title);"><?= e($aviso['titulo']) ?></h6>
          <p class="small text-muted mb-0"><?= e($aviso['mensagem']) ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if (isset($erro)): ?>
  <div class="alert alert-danger shadow-sm border-0 d-flex align-items-center gap-3 p-4 mb-4" style="border-radius: 16px;">
    <i class="bi bi-exclamation-triangle-fill fs-3 text-danger"></i>
    <div>
      <h6 class="fw-bold mb-1">Erro de Conexão com o Banco de Dados</h6>
      <p class="small text-muted mb-0"><?= e($erro) ?></p>
      <small class="d-block mt-2">Verifique as credenciais no arquivo <code>config.php</code> e certifique-se de que a tabela foi criada executando o script <code>install.sql</code>.</small>
    </div>
  </div>
  <?php endif; ?>

  <!-- Header Section -->
  <div class="d-flex align-items-center justify-content-between mb-5 flex-wrap gap-4">
    <div>
      <h3 class="mb-1 fw-extrabold" style="color: var(--dark-bg); font-family: var(--font-title);">Relatórios Técnicos</h3>
      <p class="text-muted small mb-0"><i class="bi bi-folder-fill me-1"></i> <?= $total ?> diagnóstico<?= $total !== 1 ? 's' : '' ?> cadastrado<?= $total !== 1 ? 's' : '' ?></p>
    </div>
    <div class="d-flex gap-3 flex-wrap align-items-center">
      <form class="d-flex" method="get" style="position: relative;">
        <input class="form-control me-2 ps-4 pe-5" type="search" name="q"
               value="<?= e($busca) ?>" placeholder="Buscar por cliente ou domínio…" 
               style="min-width:280px; height: 42px; border-radius: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.015);">
        <button class="btn btn-link text-muted" type="submit" style="position: absolute; right: 15px; top: 3px; padding: 0;">
          <i class="bi bi-search"></i>
        </button>
      </form>
      <a href="form.php" class="btn btn-primary px-4 d-inline-flex align-items-center gap-2" style="height: 42px; border-radius: 30px;">
        <i class="bi bi-plus-circle-fill"></i> Novo Diagnóstico
      </a>
    </div>
  </div>

  <?php if (empty($relatorios)): ?>
  <div class="text-center py-5 mt-4">
    <div class="rajo-empty-icon mb-4"><i class="bi bi-shield-x"></i></div>
    <h5 class="fw-bold text-muted">Nenhum diagnóstico por aqui</h5>
    <p class="text-muted small max-width-400 mx-auto">Você ainda não gerou nenhum relatório de SEO e Performance. Comece a auditar seus clientes agora mesmo!</p>
    <a href="form.php" class="btn btn-primary mt-3 px-4 py-2" style="border-radius: 30px;">
      <i class="bi bi-plus-circle-fill me-2"></i>Criar Meu Primeiro Diagnóstico
    </a>
  </div>
  <?php else: ?>
  <div class="row g-4">
    <?php foreach ($relatorios as $r): ?>
    <?php
      $cor = match(strtolower($r['resultado_geral'])) {
        'bom'   => 'success',
        'médio' => 'warning',
        default => 'danger'
      };
    ?>
    <div class="col-12 col-md-6 col-xl-4">
      <div class="card rajo-card h-100 shadow-sm border-0">
        <div class="card-body p-4 d-flex flex-column justify-content-between">
          <div>
            <div class="d-flex justify-content-between align-items-start mb-4">
              <div class="rajo-client-icon">
                <?= strtoupper(substr($r['cliente'], 0, 2)) ?>
              </div>
              <span class="badge bg-<?= $cor ?>-subtle text-<?= $cor ?> border border-<?= $cor ?>-subtle px-3 py-2 small fw-bold" style="border-radius: 20px; font-size: 0.7rem; letter-spacing: 0.05em;">
                <?= e($r['resultado_geral']) ?>
              </span>
            </div>
            <h5 class="fw-bold mb-2 text-dark" style="font-family: var(--font-title); font-size: 1.15rem;"><?= e($r['cliente']) ?></h5>
            <div class="text-muted small mb-2 d-flex align-items-center gap-2">
              <i class="bi bi-globe text-primary"></i>
              <a href="https://<?= e($r['dominio']) ?>" target="_blank" class="text-muted hover-link text-truncate d-block" style="max-width: 90%;"><?= e($r['dominio']) ?></a>
            </div>
            <?php if (e_master()): ?>
            <div class="text-muted small mt-3 pt-2 border-top d-flex align-items-center gap-1.5" style="font-size: 0.74rem;">
              <i class="bi bi-person-circle"></i>
              <span>Analista: <strong class="text-dark"><?= e($r['analista_nome'] ?? 'Sem Proprietário') ?></strong></span>
            </div>
            <?php endif; ?>
          </div>
          
          <div class="mt-4 pt-3 border-top d-flex align-items-center justify-content-between text-muted small">
            <span class="d-inline-flex align-items-center gap-1">
              <i class="bi bi-calendar3 text-primary"></i>
              <?= date('d/m/Y', strtotime($r['data_relatorio'])) ?>
            </span>
            <span class="d-inline-flex align-items-center gap-1">
              <i class="bi bi-clock"></i>
              <?= date('H:i', strtotime($r['criado_em'])) ?>
            </span>
          </div>
        </div>
        <div class="card-footer bg-light border-0 py-3 px-4 d-flex gap-2">
          <a href="form.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary flex-fill d-inline-flex align-items-center justify-content-center gap-1" style="border-radius: 8px;">
            <i class="bi bi-pencil-square"></i> Editar
          </a>
          <a href="visualizar.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-dark flex-fill d-inline-flex align-items-center justify-content-center gap-1" style="border-radius: 8px;" target="_blank">
            <i class="bi bi-eye"></i> Online
          </a>
          <a href="pdf.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-danger flex-fill d-inline-flex align-items-center justify-content-center gap-1" style="border-radius: 8px;" target="_blank">
            <i class="bi bi-file-earmark-pdf-fill"></i> PDF
          </a>
          <button class="btn btn-sm btn-outline-danger d-inline-flex align-items-center justify-content-center" style="border-radius: 8px; width: 36px; height: 36px;" onclick="confirmarExclusao(<?= $r['id'] ?>, '<?= e($r['cliente']) ?>')" title="Excluir Diagnóstico">
            <i class="bi bi-trash3-fill"></i>
          </button>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if ($pages > 1): ?>
  <nav class="mt-5 d-flex justify-content-center">
    <ul class="pagination pagination-md shadow-sm" style="border-radius: 10px; overflow: hidden;">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
      <li class="page-item <?= $i === $page ? 'active' : '' ?>">
        <a class="page-link border-0 px-4 py-2" href="?p=<?= $i ?>&q=<?= urlencode($busca) ?>"><?= $i ?></a>
      </li>
      <?php endfor; ?>
    </ul>
  </nav>
  <?php endif; ?>
  <?php endif; ?>

</div>

<!-- Modal excluir -->
<div class="modal fade" id="modalExcluir" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow" style="border-radius: 16px;">
      <div class="modal-header border-0 pb-0">
        <h6 class="modal-title text-danger fw-bold d-flex align-items-center gap-2" style="font-family: var(--font-title);"><i class="bi bi-exclamation-octagon-fill text-danger fs-5"></i> Excluir Relatório</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" style="font-size: 0.8rem;"></button>
      </div>
      <div class="modal-body pt-3">
        <p class="small text-muted mb-0">Você realmente deseja excluir de forma permanente o relatório técnico da empresa <strong id="clienteExcluir" class="text-dark"></strong>?</p>
        <span class="badge bg-danger-subtle text-danger border border-danger-subtle d-block text-center mt-3 py-2 small" style="border-radius: 8px;">Esta ação não poderá ser desfeita.</span>
      </div>
      <div class="modal-footer border-0 pt-0 justify-content-end gap-2">
        <button type="button" class="btn btn-sm btn-light px-3 py-2 fw-semibold" style="border-radius: 8px;" data-bs-dismiss="modal">Voltar</button>
        <a id="btnExcluirConfirmar" href="#" class="btn btn-sm btn-danger px-3 py-2 fw-semibold" style="border-radius: 8px;">Confirmar Exclusão</a>
      </div>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script>
function confirmarExclusao(id, cliente) {
  document.getElementById('clienteExcluir').textContent = cliente;
  document.getElementById('btnExcluirConfirmar').href = 'excluir.php?id=' + id;
  new bootstrap.Modal(document.getElementById('modalExcluir')).show();
}
</script>
</body>
</html>
