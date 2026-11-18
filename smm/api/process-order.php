<?php
declare(strict_types=1);
require __DIR__ . '/../includes/lib.php';
app_session();
require_method('GET');

if (!csrf_ok(csrf_from_request())) {
    json_out(['success' => false, 'message' => 'Invalid security token'], 403);
}

$order = current_order();
if (!$order) {
    json_out(['success' => false, 'message' => 'Order not verified'], 404);
}
if ($order['status'] === 'completed') {
    json_out(['success' => true]);
}

// Trava atômica: só um processo consegue passar de lootlabs_verified para submitting.
$lock = db()->prepare("UPDATE orders SET status = 'submitting' WHERE id = ? AND status = 'lootlabs_verified'");
$lock->execute([$order['id']]);
if ($lock->rowCount() !== 1) {
    json_out(['success' => false, 'message' => 'Order not verified'], 409);
}
session_write_close();

$svc = SERVICES[$order['service_key']] ?? null;
if ($svc === null || (int) $svc['service_id'] <= 0) {
    db()->prepare("UPDATE orders SET status = 'failed', message = ?, finished_at = ? WHERE id = ?")
        ->execute(['Service not available', time(), $order['id']]);
    json_out(['success' => false, 'message' => 'Service not available'], 422);
}

$result = orca_add_order((int) $svc['service_id'], $order['link'], (int) $order['quantity']);

if ($result['ok']) {
    db()->prepare("UPDATE orders SET status = 'completed', provider_order = ?, message = NULL, finished_at = ? WHERE id = ?")
        ->execute([$result['order'], time(), $order['id']]);
    // Cooldown só começa quando o pedido foi realmente enviado.
    set_cooldown($order['session_id'], $order['ip'], next_reset($order['tz']));
    json_out(['success' => true]);
}

db()->prepare("UPDATE orders SET status = 'failed', message = ?, finished_at = ? WHERE id = ?")
    ->execute([$result['error'] ?? 'Provider error', time(), $order['id']]);
json_out(['success' => false, 'message' => 'Could not submit order, please try again later'], 502);
