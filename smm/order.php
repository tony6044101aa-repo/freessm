<?php
declare(strict_types=1);
require __DIR__ . '/includes/lib.php';
app_session();

$order = current_order();
if (!$order) {
    header('Location: ./');
    exit;
}
$svc = SERVICES[$order['service_key']] ?? ['name' => $order['service_key'], 'network' => ''];
$back = isset($_GET['back']);
?>
<!doctype html>
<html lang="pt-BR">
<head>
<?php render_head('Seu pedido — Painel SMM grátis'); ?>
<meta name="robots" content="noindex">
</head>
<body data-page="order"
      data-csrf="<?= h(csrf_token()) ?>"
      data-status="<?= h($order['status']) ?>"
      data-redirect-url="<?= h(lootlabs_url($order['puid'])) ?>"
      data-poll-interval="<?= POLL_INTERVAL_MS ?>"
      data-poll-max="<?= POLL_MAX_MS ?>"
      data-autostart="<?= $back ? '1' : '0' ?>">
<?php render_adblock_notice(); ?>

<header class="topbar">
  <a href="./" class="brand">Free<span>SMM</span></a>
</header>

<main class="wrap narrow">
  <section class="panel">
    <h2>Seu pedido</h2>
    <dl class="summary">
      <dt>Serviço</dt><dd><?= h($svc['name']) ?></dd>
      <dt>Quantidade</dt><dd><?= number_format((int) $order['quantity'], 0, ',', '.') ?></dd>
      <dt>Link</dt><dd class="break"><?= h($order['link']) ?></dd>
    </dl>

    <div class="steps" id="steps">
      <div class="step" data-step="waiting_lootlabs">1. Concluir a tarefa</div>
      <div class="step" data-step="lootlabs_verified">2. Tarefa confirmada</div>
      <div class="step" data-step="submitting">3. Enviando pedido</div>
      <div class="step" data-step="completed">4. Pronto</div>
    </div>

    <p id="status-msg" class="msg" role="status" aria-live="polite"></p>

    <button type="button" class="btn btn-block" id="task-btn">Fazer tarefa e liberar pedido</button>
    <button type="button" class="btn btn-ghost btn-block" id="recheck-btn" hidden>Já concluí — verificar de novo</button>
    <a href="./" class="btn btn-ghost btn-block" id="home-btn" hidden>Voltar ao início</a>
  </section>
</main>

<script src="assets/js/app.js" defer></script>
</body>
</html>
