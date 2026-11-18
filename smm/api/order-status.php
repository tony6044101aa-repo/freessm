<?php
declare(strict_types=1);
require __DIR__ . '/../includes/lib.php';
app_session();
require_method('GET');

$order = current_order();
session_write_close(); // libera a sessão para os outros pedidos do polling

if (!$order) {
    json_out(['status' => 'none', 'cooldown_until' => cooldown_until()]);
}

json_out([
    'status'         => $order['status'],
    'message'        => $order['message'],
    'provider_order' => $order['status'] === 'completed' ? $order['provider_order'] : null,
    'expires_at'     => (int) $order['created_at'] + ORDER_TTL,
    'cooldown_until' => cooldown_until(),
]);
