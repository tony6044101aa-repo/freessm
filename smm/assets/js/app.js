(function () {
  'use strict';

  var body = document.body;
  var page = body.getAttribute('data-page');
  var csrf = body.getAttribute('data-csrf') || '';

  var MESSAGES = {
    'Invalid security token': 'Sessão expirada. Recarregue a página e tente de novo.',
    'Service not available': 'Este serviço está indisponível no momento.',
    'Invalid link': 'Link inválido para este serviço. Confira o endereço.',
    'Cooldown active': 'Você já usou seu pedido grátis de hoje.',
    'Order not verified': 'A tarefa ainda não foi confirmada.'
  };
  function t(msg) { return MESSAGES[msg] || msg || 'Algo deu errado. Tente novamente.'; }

  function getJSON(url) {
    return fetch(url, {
      credentials: 'same-origin',
      headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
      cache: 'no-store'
    }).then(function (r) { return r.json(); });
  }

  function formatLeft(sec) {
    sec = Math.max(0, Math.floor(sec));
    var h = Math.floor(sec / 3600), m = Math.floor((sec % 3600) / 60), s = sec % 60;
    return (h ? h + 'h ' : '') + (h || m ? m + 'min ' : '') + s + 's';
  }

  /* ------------------------- Página inicial ------------------------- */
  function initIndex() {
    var form = document.getElementById('order-form');
    var select = document.getElementById('service_key');
    var link = document.getElementById('link');
    var msg = document.getElementById('form-msg');
    var btn = document.getElementById('order-btn');
    var tz = document.getElementById('tz');

    try { tz.value = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) {}

    function syncPlaceholder() {
      var opt = select.options[select.selectedIndex];
      if (opt) link.placeholder = opt.getAttribute('data-placeholder') || 'https://';
    }
    select.addEventListener('change', syncPlaceholder);
    syncPlaceholder();

    document.querySelectorAll('.card[data-service]').forEach(function (card) {
      card.addEventListener('click', function () {
        select.value = card.getAttribute('data-service');
        syncPlaceholder();
        document.getElementById('order').scrollIntoView({ behavior: 'smooth' });
        link.focus({ preventScroll: true });
      });
    });

    // Cooldown
    var until = parseInt(body.getAttribute('data-cooldown-until') || '0', 10);
    var box = document.getElementById('cooldown');
    var left = document.getElementById('cooldown-left');
    var at = document.getElementById('cooldown-at');
    var timer = null;
    function showCooldown(ts) {
      until = ts;
      if (!until || until * 1000 <= Date.now()) { box.hidden = true; btn.disabled = false; return; }
      box.hidden = false;
      btn.disabled = true;
      at.textContent = new Date(until * 1000).toLocaleString('pt-BR', { weekday: 'short', hour: '2-digit', minute: '2-digit' });
      var tick = function () {
        var s = until - Date.now() / 1000;
        if (s <= 0) { clearInterval(timer); box.hidden = true; btn.disabled = false; return; }
        left.textContent = formatLeft(s);
      };
      clearInterval(timer);
      tick();
      timer = setInterval(tick, 1000);
    }
    showCooldown(until);

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      msg.className = 'msg';
      if (!link.value.trim()) { msg.textContent = 'Cole o link.'; msg.classList.add('err'); return; }
      btn.disabled = true;
      msg.textContent = 'Criando pedido…';
      fetch('api/order.php', { method: 'POST', body: new FormData(form), credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (res.success) {
            window.location.href = res.order_page || 'order.php';
            return;
          }
          msg.textContent = t(res.message);
          msg.classList.add('err');
          if (res.cooldown_until) showCooldown(res.cooldown_until); else btn.disabled = false;
        })
        .catch(function () {
          msg.textContent = 'Falha de conexão. Tente novamente.';
          msg.classList.add('err');
          btn.disabled = false;
        });
    });
  }

  /* ------------------------- Página do pedido ------------------------- */
  function initOrder() {
    var redirectUrl = body.getAttribute('data-redirect-url');
    var interval = parseInt(body.getAttribute('data-poll-interval') || '2000', 10);
    var maxMs = parseInt(body.getAttribute('data-poll-max') || '45000', 10);
    var taskBtn = document.getElementById('task-btn');
    var recheckBtn = document.getElementById('recheck-btn');
    var homeBtn = document.getElementById('home-btn');
    var msg = document.getElementById('status-msg');
    var steps = document.querySelectorAll('.step');
    var ORDER = ['waiting_lootlabs', 'lootlabs_verified', 'submitting', 'completed'];

    var pollTimer = null, startedAt = 0, processing = false, finished = false;

    function setMsg(text, kind) { msg.textContent = text; msg.className = 'msg' + (kind ? ' ' + kind : ''); }

    function paint(status) {
      var idx = ORDER.indexOf(status);
      steps.forEach(function (el, i) {
        el.classList.toggle('active', i === idx && status !== 'completed');
        el.classList.toggle('done', status === 'completed' || (idx >= 0 && i < idx));
      });
    }

    function stop() { clearTimeout(pollTimer); pollTimer = null; }

    function end(text, kind) {
      finished = true;
      stop();
      setMsg(text, kind);
      taskBtn.hidden = true;
      recheckBtn.hidden = true;
      homeBtn.hidden = false;
    }

    function processOrder() {
      if (processing) return;
      processing = true;
      setMsg('Tarefa confirmada! Enviando seu pedido…');
      getJSON('api/process-order.php').then(function (res) {
        processing = false;
        // Sucesso ou falha: o status final chega no próximo poll.
        schedule();
      }).catch(function () { processing = false; schedule(); });
    }

    function handle(res) {
      var status = res.status;
      paint(status);
      switch (status) {
        case 'waiting_lootlabs':
          if (Date.now() - startedAt >= maxMs) {
            stop();
            setMsg('Ainda não recebemos a confirmação da tarefa. Termine a tarefa na outra aba e clique em "verificar de novo".');
            recheckBtn.hidden = false;
            return;
          }
          setMsg('Aguardando você concluir a tarefa na outra aba…');
          schedule();
          return;
        case 'lootlabs_verified':
          processOrder();
          return;
        case 'submitting':
          setMsg('Enviando seu pedido…');
          schedule();
          return;
        case 'completed':
          end('Pedido enviado! A entrega começa em alguns minutos.' + (res.provider_order ? ' Nº ' + res.provider_order : ''), 'ok');
          return;
        case 'failed':
          getJSON('api/clear-pending.php');
          end('Não foi possível enviar o pedido agora. Seu pedido grátis não foi gasto — tente mais tarde.', 'err');
          return;
        case 'expired':
          getJSON('api/clear-pending.php');
          end('O tempo para concluir a tarefa acabou. Faça um novo pedido.', 'err');
          return;
        default:
          end('Nenhum pedido em andamento.', 'err');
      }
    }

    function poll() {
      getJSON('api/order-status.php').then(handle).catch(schedule);
    }
    function schedule() { if (!finished) { stop(); pollTimer = setTimeout(poll, interval); } }

    function startPolling() {
      if (finished) return;
      startedAt = Date.now();
      recheckBtn.hidden = true;
      stop();
      poll();
    }

    taskBtn.addEventListener('click', function () {
      window.open(redirectUrl, '_blank', 'noopener');
      taskBtn.textContent = 'Abrir a tarefa de novo';
      startPolling();
    });
    recheckBtn.addEventListener('click', startPolling);

    // Ao voltar para a aba, verifica imediatamente.
    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'visible' && !finished && !pollTimer && startedAt) startPolling();
    });

    var initial = body.getAttribute('data-status');
    paint(initial);
    if (initial !== 'waiting_lootlabs' || body.getAttribute('data-autostart') === '1') {
      startPolling();
    } else {
      setMsg('Clique no botão abaixo, conclua a tarefa e volte para esta página.');
    }
  }

  if (page === 'index') initIndex();
  if (page === 'order') initOrder();
})();
