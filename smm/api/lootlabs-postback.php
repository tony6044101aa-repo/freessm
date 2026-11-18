<?php
declare(strict_types=1);
/*
 * Chamado pelo LootLabs (servidor → servidor) quando o usuário conclui a tarefa.
 * URL a configurar no LootLabs (Advanced → Postback):
 *   https://SEU-DOMINIO/smm/api/lootlabs-postback.php?secret=SEU_SEGREDO&click_id={CLICK_ID}&ip={IP}&unique_id={UNIQUE_ID}
 * click_id = valor do &puid enviado no link.
 */
require __DIR__ . '/../includes/lib.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$secret = (string) ($_GET['secret'] ?? '');
if (!hash_equals(LOOTLABS_POSTBACK_SECRET, $secret)) {
    http_response_code(403);
    exit('forbidden');
}

$clickId  = (string) ($_GET['click_id'] ?? '');
$ip       = (string) ($_GET['ip'] ?? '');
$uniqueId = (string) ($_GET['unique_id'] ?? '');
if (!preg_match('/^[a-f0-9]{24}$/', $clickId) || $uniqueId === '' || strlen($uniqueId) > 128) {
    http_response_code(400);
    exit('bad request');
}

$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM orders WHERE puid = ?');
$stmt->execute([$clickId]);
$order = $stmt->fetch();

$accepted = 0;
if ($order && $order['status'] === 'waiting_lootlabs' && (int) $order['created_at'] + ORDER_TTL >= time()) {
    $ipOk = !POSTBACK_CHECK_IP || $ip === '' || $ip === $order['ip'];
    if ($ipOk) {
        $upd = $pdo->prepare("UPDATE orders SET status = 'lootlabs_verified', verified_at = ? WHERE id = ? AND status = 'waiting_lootlabs'");
        $upd->execute([time(), $order['id']]);
        $accepted = $upd->rowCount();
    } else {
        error_log("[smm] postback IP mismatch for {$clickId}: {$ip} != {$order['ip']}");
    }
}

// Registra o postback (ignora repetidos com o mesmo unique_id).
$pdo->prepare('INSERT OR IGNORE INTO postbacks (unique_id, click_id, ip, accepted, at) VALUES (?, ?, ?, ?, ?)')
    ->execute([$uniqueId, $clickId, $ip, $accepted, time()]);

echo 'ok';
