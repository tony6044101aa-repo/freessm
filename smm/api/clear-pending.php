<?php
declare(strict_types=1);
require __DIR__ . '/../includes/lib.php';
app_session();
require_method('GET');

if (!csrf_ok(csrf_from_request())) {
    json_out(['success' => false, 'message' => 'Invalid security token'], 403);
}

$order = current_order();
if ($order) {
    if ($order['status'] === 'waiting_lootlabs') {
        db()->prepare("UPDATE orders SET status = 'expired', finished_at = ? WHERE id = ? AND status = 'waiting_lootlabs'")
            ->execute([time(), $order['id']]);
    }
    // Pedidos já verificados ou em envio não são descartados.
    if (in_array($order['status'], ['lootlabs_verified', 'submitting'], true)) {
        json_out(['success' => false, 'message' => 'Order is already being processed'], 409);
    }
}
unset($_SESSION['pending_puid']);
json_out(['success' => true]);
