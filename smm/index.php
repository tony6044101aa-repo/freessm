<?php
declare(strict_types=1);
require __DIR__ . '/includes/lib.php';
app_session();

$cooldown = cooldown_until();
$pending  = current_order();
$hasPending = $pending && in_array($pending['status'], ['waiting_lootlabs', 'lootlabs_verified', 'submitting'], true);
?>
<!doctype html>
<html lang="pt-BR">
<head>
<?php render_head('Painel SMM grátis — curtidas, seguidores e inscritos'); ?>
</head>
<body data-page="index"
      data-csrf="<?= h(csrf_token()) ?>"
      data-cooldown-until="<?= $cooldown ?>">
<?php render_adblock_notice(); ?>

<header class="topbar">
  <a href="./" class="brand">Free<span>SMM</span></a>
  <a href="#order" class="btn btn-sm">Pedir grátis</a>
</header>

<main class="wrap">
  <section class="hero">
    <h1>Engajamento <span class="hl">grátis</span> todos os dias</h1>
    <p>Escolha um serviço, cole o link e conclua uma tarefa rápida. 1 pedido a cada 24h — renova às 08:00 no seu horário.</p>
  </section>

  <?php if ($hasPending): ?>
  <div class="notice">
    Você tem um pedido em andamento. <a href="order.php">Continuar pedido</a>
  </div>
  <?php endif; ?>

  <div id="cooldown" class="notice notice-warn" hidden>
    Você já fez seu pedido grátis. Próximo pedido liberado em <strong id="cooldown-left">--</strong>
    (<span id="cooldown-at"></span>).
  </div>

  <section class="grid" aria-label="Serviços">
    <?php foreach (SERVICES as $key => $svc): $enabled = (int) $svc['service_id'] > 0; ?>
    <button type="button"
            class="card<?= $enabled ? '' : ' is-off' ?>"
            data-service="<?= h($key) ?>"
            data-placeholder="<?= h($svc['placeholder']) ?>"
            <?= $enabled ? '' : 'disabled' ?>>
      <span class="net"><?= h($svc['network']) ?></span>
      <span class="name"><?= h($svc['name']) ?></span>
      <span class="qty"><?= number_format((int) $svc['quantity'], 0, ',', '.') ?> grátis</span>
      <?php if (!$enabled): ?><span class="badge">Indisponível</span><?php endif; ?>
    </button>
    <?php endforeach; ?>
  </section>

  <section id="order" class="panel">
    <form id="order-form" novalidate>
      <h2>Fazer pedido</h2>
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="tz" id="tz" value="">

      <label for="service_key">Serviço</label>
      <select id="service_key" name="service_key" required>
        <?php foreach (SERVICES as $key => $svc): if ((int) $svc['service_id'] <= 0) continue; ?>
        <option value="<?= h($key) ?>" data-placeholder="<?= h($svc['placeholder']) ?>">
          <?= h($svc['name']) ?> — <?= number_format((int) $svc['quantity'], 0, ',', '.') ?>
        </option>
        <?php endforeach; ?>
      </select>

      <label for="link">Link</label>
      <input id="link" name="link" type="url" inputmode="url" autocomplete="off" required maxlength="500"
             placeholder="https://">

      <button type="submit" class="btn btn-block" id="order-btn">Order</button>
      <p id="form-msg" class="msg" role="status" aria-live="polite"></p>
    </form>
  </section>

  <section class="how">
    <h2>Como funciona</h2>
    <ol>
      <li>Escolha o serviço e cole o link do perfil ou da publicação.</li>
      <li>Conclua a tarefa patrocinada que abre em uma nova aba.</li>
      <li>Quando a tarefa for confirmada, o pedido é enviado automaticamente.</li>
    </ol>
  </section>
</main>

<footer class="foot">
  <p>Serviço grátis mantido por anúncios. Não temos ligação com Instagram, Facebook, YouTube ou TikTok.</p>
</footer>

<script src="assets/js/app.js" defer></script>
</body>
</html>
