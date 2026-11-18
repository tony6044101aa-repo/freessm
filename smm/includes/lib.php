<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/* ---------------- Sessão + CSRF ---------------- */

function app_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name('PHPSESSID');
        session_set_cookie_params([
            'lifetime' => 60 * 60 * 24 * 30,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
}

function csrf_token(): string
{
    return (string) $_SESSION['csrf_token'];
}

function csrf_ok(?string $token): bool
{
    return is_string($token) && $token !== '' && hash_equals(csrf_token(), $token);
}

function csrf_from_request(): ?string
{
    return $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_GET['csrf_token'] ?? null;
}

/* ---------------- Helpers ---------------- */

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_out(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function require_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method) {
        json_out(['success' => false, 'message' => 'Method not allowed'], 405);
    }
}

function client_ip(): string
{
    if (TRUST_CLOUDFLARE && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return (string) $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/* ---------------- Banco (SQLite) ---------------- */

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0750, true);
    }
    $pdo = new PDO('sqlite:' . DATA_DIR . '/smm.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('CREATE TABLE IF NOT EXISTS orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        puid TEXT NOT NULL UNIQUE,
        session_id TEXT NOT NULL,
        ip TEXT NOT NULL,
        tz TEXT NOT NULL,
        service_key TEXT NOT NULL,
        link TEXT NOT NULL,
        quantity INTEGER NOT NULL,
        status TEXT NOT NULL,
        provider_order TEXT,
        message TEXT,
        created_at INTEGER NOT NULL,
        verified_at INTEGER,
        finished_at INTEGER
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS cooldowns (scope TEXT PRIMARY KEY, until INTEGER NOT NULL)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS postbacks (
        unique_id TEXT PRIMARY KEY, click_id TEXT, ip TEXT, accepted INTEGER NOT NULL, at INTEGER NOT NULL
    )');
    return $pdo;
}

/* ---------------- Serviços e links ---------------- */

function service(string $key): ?array
{
    $svc = SERVICES[$key] ?? null;
    if ($svc === null || (int) $svc['service_id'] <= 0) {
        return null;
    }
    return $svc + ['key' => $key];
}

function valid_link(string $link, array $svc): bool
{
    if (strlen($link) > 500 || !filter_var($link, FILTER_VALIDATE_URL)) {
        return false;
    }
    $parts = parse_url($link);
    if (!in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
        return false;
    }
    $host = strtolower($parts['host'] ?? '');
    foreach ($svc['hosts'] as $allowed) {
        if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
            return true;
        }
    }
    return false;
}

/* ---------------- Fuso horário e cooldown ---------------- */

function user_tz(?string $tz): string
{
    if (is_string($tz) && in_array($tz, timezone_identifiers_list(), true)) {
        return $tz;
    }
    return 'America/Sao_Paulo';
}

/** Próximas 08:00 (RESET_HOUR) no fuso do usuário. */
function next_reset(string $tz): int
{
    $zone = new DateTimeZone($tz);
    $now = new DateTimeImmutable('now', $zone);
    $reset = $now->setTime(RESET_HOUR, 0, 0);
    if ($reset <= $now) {
        $reset = $reset->modify('+1 day');
    }
    return $reset->getTimestamp();
}

function cooldown_until(): int
{
    $stmt = db()->prepare('SELECT MAX(until) FROM cooldowns WHERE scope IN (?, ?) AND until > ?');
    $stmt->execute(['sess:' . session_id(), 'ip:' . client_ip(), time()]);
    return (int) $stmt->fetchColumn();
}

function set_cooldown(string $sessionId, string $ip, int $until): void
{
    $stmt = db()->prepare('INSERT INTO cooldowns (scope, until) VALUES (?, ?)
        ON CONFLICT(scope) DO UPDATE SET until = excluded.until');
    $stmt->execute(['sess:' . $sessionId, $until]);
    $stmt->execute(['ip:' . $ip, $until]);
}

/* ---------------- Pedido pendente da sessão ---------------- */

function current_order(): ?array
{
    $puid = $_SESSION['pending_puid'] ?? null;
    if (!is_string($puid)) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM orders WHERE puid = ? AND session_id = ?');
    $stmt->execute([$puid, session_id()]);
    $order = $stmt->fetch();
    if (!$order) {
        unset($_SESSION['pending_puid']);
        return null;
    }
    // Expira se a tarefa não foi verificada a tempo.
    if ($order['status'] === 'waiting_lootlabs' && (int) $order['created_at'] + ORDER_TTL < time()) {
        db()->prepare("UPDATE orders SET status = 'expired', finished_at = ? WHERE id = ? AND status = 'waiting_lootlabs'")
            ->execute([time(), $order['id']]);
        $order['status'] = 'expired';
    }
    return $order;
}

function lootlabs_url(string $puid): string
{
    $sep = str_contains(LOOTLABS_LINK, '?') ? '&' : '?';
    return LOOTLABS_LINK . $sep . 'puid=' . rawurlencode($puid);
}

/* ---------------- OrcaSMM ---------------- */

/** @return array{ok:bool, order?:string, error?:string} */
function orca_add_order(int $serviceId, string $link, int $quantity): array
{
    $ch = curl_init(ORCA_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; SMMPanel/1.0)',
        CURLOPT_POSTFIELDS     => http_build_query([
            'key'      => ORCA_API_KEY,
            'action'   => 'add',
            'service'  => $serviceId,
            'link'     => $link,
            'quantity' => $quantity,
        ]),
    ]);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        error_log('[smm] OrcaSMM curl error: ' . $err);
        return ['ok' => false, 'error' => 'Provider unavailable'];
    }
    $json = json_decode((string) $body, true);
    if (is_array($json) && isset($json['order'])) {
        return ['ok' => true, 'order' => (string) $json['order']];
    }
    error_log('[smm] OrcaSMM response: ' . substr((string) $body, 0, 500));
    return ['ok' => false, 'error' => is_array($json) && isset($json['error']) ? (string) $json['error'] : 'Provider error'];
}

/* ---------------- Layout ---------------- */

function render_head(string $title): void
{
    ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?></title>
<meta name="description" content="Curtidas, seguidores e inscritos grátis para Instagram, Facebook, YouTube e TikTok.">
<link rel="stylesheet" href="assets/css/style.css">
<?php if (AD_ZONE_ID !== ''): ?>
<script src="<?= h(AD_TAG_SRC) ?>" data-zone="<?= h(AD_ZONE_ID) ?>" async data-cfasync="false"></script>
<?php endif; ?>
<script>
/* Detector de adblock: elemento "isca" + tentativa de carregar um script de anúncio. */
(function () {
  var block = <?= ADBLOCK_BLOCK_PAGE ? 'true' : 'false' ?>;
  function flag() {
    if (document.documentElement.classList.contains('adblock')) return;
    document.documentElement.classList.add('adblock');
    if (block) document.documentElement.classList.add('adblock-lock');
  }
  window.addEventListener('DOMContentLoaded', function () {
    var bait = document.createElement('div');
    bait.className = 'adsbox ad-banner ad-placement pub_300x250 textads';
    bait.style.cssText = 'position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;';
    bait.innerHTML = '&nbsp;';
    document.body.appendChild(bait);
    setTimeout(function () {
      var s = window.getComputedStyle(bait);
      if (!bait.offsetParent || bait.offsetHeight === 0 || s.display === 'none' || s.visibility === 'hidden') flag();
      bait.remove();
    }, 150);
    var probe = document.createElement('script');
    probe.src = '<?= h(AD_TAG_SRC) ?>?probe=' + Date.now();
    probe.async = true;
    probe.onerror = flag;
    probe.onload = function () { probe.remove(); };
    <?php if (AD_ZONE_ID !== ''): ?>document.head.appendChild(probe);<?php endif; ?>
  });
})();
</script>
<?php
}

function render_adblock_notice(): void
{
    ?>
<div class="adblock-notice" role="alertdialog" aria-labelledby="ab-title">
  <div class="adblock-card">
    <h2 id="ab-title">Bloqueador de anúncios detectado</h2>
    <p>Este site é grátis porque é mantido por anúncios. Desative o bloqueador para este site e recarregue a página.</p>
    <button type="button" class="btn" onclick="location.reload()">Já desativei, recarregar</button>
  </div>
</div>
<?php
}
