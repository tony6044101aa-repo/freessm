<?php
declare(strict_types=1);

/*
 * Configuração do painel.
 * Prefira definir as chaves como variáveis de ambiente no servidor
 * (ORCA_API_KEY, LOOTLABS_LINK, LOOTLABS_POSTBACK_SECRET, AD_ZONE_ID).
 * Nunca envie este arquivo com chaves reais para um repositório público.
 */

// ---------- OrcaSMM (SMM Panel API v2) ----------
const ORCA_API_URL = 'https://api.orcasmm.com/api/v2';
define('ORCA_API_KEY', getenv('ORCA_API_KEY') ?: 'COLE_SUA_CHAVE_ORCASMM_AQUI');

// ---------- LootLabs ----------
// Crie UM link no painel do LootLabs com destino:
//   https://SEU-DOMINIO/smm/order.php?back=1
// O painel acrescenta &puid=... automaticamente em cada pedido.
define('LOOTLABS_LINK', getenv('LOOTLABS_LINK') ?: 'https://loot-link.com/s?XXXXXX');
// Segredo que vai na URL de postback configurada no LootLabs (aba Advanced):
//   https://SEU-DOMINIO/smm/api/lootlabs-postback.php?secret=ESTE_VALOR&click_id={CLICK_ID}&ip={IP}&unique_id={UNIQUE_ID}
define('LOOTLABS_POSTBACK_SECRET', getenv('LOOTLABS_POSTBACK_SECRET') ?: 'TROQUE_POR_UM_TEXTO_LONGO_E_ALEATORIO');
// Recusa a verificação se o IP enviado pelo LootLabs for diferente do IP de quem fez o pedido.
const POSTBACK_CHECK_IP = true;

// ---------- Anúncios ----------
const AD_TAG_SRC = 'https://zovidree.com/tag.min.js';
define('AD_ZONE_ID', getenv('AD_ZONE_ID') ?: ''); // vazio = não carrega o script de anúncios
const ADBLOCK_BLOCK_PAGE = true;                   // true = cobre a página até o adblock ser desativado

// ---------- Regras ----------
const RESET_HOUR       = 8;      // cooldown termina às 08:00 no fuso do usuário
const ORDER_TTL        = 1800;   // segundos para concluir a tarefa antes de expirar
const POLL_INTERVAL_MS = 2000;   // polling a cada 2s
const POLL_MAX_MS      = 45000;  // máximo de 45s por rodada de polling
const TRUST_CLOUDFLARE = false;  // true se o site estiver atrás do Cloudflare (usa CF-Connecting-IP)

define('DATA_DIR', getenv('SMM_DATA_DIR') ?: __DIR__ . '/../data');

/*
 * Serviços fixos. "service_id" = ID do serviço na OrcaSMM
 * (veja a lista com action=services). service_id 0 = desativado.
 */
const SERVICES = [
    'ig_likes' => [
        'network' => 'Instagram', 'name' => 'Curtidas Instagram', 'quantity' => 300,
        'service_id' => 0, 'hosts' => ['instagram.com'], 'placeholder' => 'https://www.instagram.com/p/XXXXX/',
    ],
    'fb_followers' => [
        'network' => 'Facebook', 'name' => 'Seguidores Facebook', 'quantity' => 25,
        'service_id' => 0, 'hosts' => ['facebook.com', 'fb.com'], 'placeholder' => 'https://www.facebook.com/seuperfil',
    ],
    'fb_loves' => [
        'network' => 'Facebook', 'name' => 'Reações "Amei" Facebook', 'quantity' => 10,
        'service_id' => 0, 'hosts' => ['facebook.com', 'fb.com', 'fb.watch'], 'placeholder' => 'https://www.facebook.com/.../posts/...',
    ],
    'yt_subscribers' => [
        'network' => 'YouTube', 'name' => 'Inscritos YouTube', 'quantity' => 500,
        'service_id' => 0, 'hosts' => ['youtube.com', 'youtu.be'], 'placeholder' => 'https://www.youtube.com/@seucanal',
    ],
    'tt_followers' => [
        'network' => 'TikTok', 'name' => 'Seguidores TikTok', 'quantity' => 10,
        'service_id' => 0, 'hosts' => ['tiktok.com'], 'placeholder' => 'https://www.tiktok.com/@seuperfil',
    ],
];
