<?php
declare(strict_types=1);
require __DIR__ . '/../includes/lib.php';
app_session();
require_method('POST');

if (!csrf_ok(csrf_from_request())) {
    json_out(['success' => false, 'message' => 'Invalid security token'], 403);
}

$svc = service((string) ($_POST['service_key'] ?? ''));
if ($svc === null) {
    json_out(['success' => false, 'message' => 'Service not available'], 422);
}

$link = trim((string) ($_POST['link'] ?? ''));
if (!valid_link($link, $svc)) {
    json_out(['success' => false, 'message' => 'Invalid link'], 422);
}

$tz = user_tz($_POST['tz'] ?? null);
$_SESSION['tz'] = $tz;

$until = cooldown_until();
if ($until > time()) {
    json_out(['success' => false, 'message' => 'Cooldown active', 'cooldown_until' => $until], 429);
}

// Já existe um pedido em andamento? Reaproveita em vez de criar outro.
$pending = current_order();
if ($pending && in_array($pending['status'], ['waiting_lootlabs', 'lootlabs_verified', 'submitting'], true)) {
    json_out([
        'success'      => true,
        'message'      => 'You already have an order in progress',
        'redirect_url' => lootlabs_url($pending['puid']),
        'order_page'   => 'order.php',
    ]);
}

$puid = bin2hex(random_bytes(12));
db()->prepare('INSERT INTO orders (puid, session_id, ip, tz, service_key, link, quantity, status, created_at)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
    ->execute([$puid, session_id(), client_ip(), $tz, $svc['key'], $link, (int) $svc['quantity'], 'waiting_lootlabs', time()]);

$_SESSION['pending_puid'] = $puid;

json_out([
    'success'      => true,
    'message'      => 'Order created. Complete the task to release it.',
    'redirect_url' => lootlabs_url($puid),
    'order_page'   => 'order.php',
]);
