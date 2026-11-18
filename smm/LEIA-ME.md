# Painel SMM grátis (PHP puro)

## Requisitos
- PHP 8.1+ com as extensões `pdo_sqlite` e `curl`
- Hospedagem com PHP (Apache, Nginx + PHP-FPM, Hostinger, cPanel etc.)

## Instalação
1. Envie a pasta `smm/` para a raiz do site, para ela abrir em `https://SEU-DOMINIO/smm/`.
2. Dê permissão de escrita para o PHP na pasta `smm/data/` (onde fica o banco SQLite).
3. Edite `includes/config.php`, ou defina estas variáveis de ambiente no servidor:
   - `ORCA_API_KEY`: sua chave da OrcaSMM (Conta → API).
   - `LOOTLABS_LINK`: o link criado no LootLabs (ex: `https://loot-link.com/s?abcd`).
   - `LOOTLABS_POSTBACK_SECRET`: um texto longo e aleatório inventado por você.
   - `AD_ZONE_ID`: o ID da zona de anúncios do `zovidree.com/tag.min.js` (deixe vazio para não carregar anúncios).
4. Em `SERVICES` (no `config.php`), coloque em cada serviço o `service_id` da OrcaSMM.
   Para ver a lista de IDs:
   `curl -X POST https://api.orcasmm.com/api/v2 -d key=SUA_CHAVE -d action=services`
   Um serviço com `service_id` 0 aparece como "Indisponível".

## LootLabs
1. Crie **um** link no painel do LootLabs com destino `https://SEU-DOMINIO/smm/order.php?back=1`.
   O ideal é usar 1 tarefa por link (`number_of_tasks = 1`).
2. Em **Advanced → Postback**, ative o postback com esta URL:
   `https://SEU-DOMINIO/smm/api/lootlabs-postback.php?secret=SEU_SEGREDO&click_id={CLICK_ID}&ip={IP}&unique_id={UNIQUE_ID}`
3. O painel acrescenta `&puid=...` ao link em cada pedido. O LootLabs devolve esse valor como `click_id`,
   e é isso que libera o pedido. Sem postback válido, nada é enviado para a OrcaSMM.

## Fluxo
```
index.php → POST api/order.php → order.php → (nova aba) LootLabs
                                    │ polling a cada 2s em api/order-status.php (rodadas de até 45s)
LootLabs → api/lootlabs-postback.php → status lootlabs_verified
order.php → api/process-order.php → OrcaSMM (action=add) → completed / failed
expirado → api/clear-pending.php
```
Status: `waiting_lootlabs → lootlabs_verified → submitting → completed / failed / expired`

## Regras
- 1 pedido a cada 24h por sessão **e** por IP. O prazo termina às 08:00 no fuso do usuário.
- O prazo só começa quando o pedido é enviado de verdade. Se falhar, o usuário pode tentar de novo.
- A quantidade é fixa por serviço (definida em `config.php`).
- O pedido expira se a tarefa não for confirmada em 30 min (`ORDER_TTL`).
- O postback é recusado se o IP informado pelo LootLabs for diferente do IP de quem fez o pedido (`POSTBACK_CHECK_IP`).
- Se usar Cloudflare, ligue `TRUST_CLOUDFLARE` para ler o IP real do visitante.

## Segurança
- `includes/` e `data/` vêm com `.htaccess` bloqueando acesso. No **Nginx**, adicione:
  `location ~ ^/smm/(includes|data)/ { deny all; }`
- O token CSRF é gerado com `bin2hex(random_bytes(16))` e guardado na sessão.
- A chave da OrcaSMM fica só no servidor e nunca é enviada ao navegador.

## Custos
Mesmo com o site grátis para os visitantes, a OrcaSMM cobra do seu saldo em cada pedido.
Acompanhe o saldo com `action=balance` e confira se o que você ganha no LootLabs e com os anúncios cobre esse gasto.
