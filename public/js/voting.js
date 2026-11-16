// FatecPoll — votação: modal, revisão, envio ao servidor e bloqueio local. JS puro.
(function () {
  'use strict';

  var form = document.getElementById('vote-form');
  var dialog = document.getElementById('vote-dialog');
  var pollEl = document.querySelector('[data-poll-slug]');
  if (!pollEl) return;

  var slug = pollEl.dataset.pollSlug;

  // ---------- Identificação do dispositivo ----------
  // O servidor define o identificador (cookie HttpOnly assinado). Aqui só guardamos uma CÓPIA no localStorage
  // e a reenviamos no cabeçalho X-Device-Token: se os cookies forem apagados, o servidor ainda reconhece o dispositivo.
  // O servidor valida a assinatura; um valor inventado ou alterado é simplesmente ignorado.
  var DEV_KEY = 'fatecpoll:device';
  var serverToken = pollEl.dataset.deviceToken || '';
  function readStored() { try { return localStorage.getItem(DEV_KEY) || ''; } catch (e) { return ''; } }
  function store(t) { try { if (t) localStorage.setItem(DEV_KEY, t); } catch (e) {} }
  var stored = readStored();
  if (!stored) { store(serverToken); stored = serverToken; }
  function deviceToken() { return stored || serverToken; }

  function showAlreadyVoted() {
    var done = document.getElementById('already-voted'), area = document.getElementById('vote-area');
    if (done) done.hidden = false;
    if (area) area.hidden = true;
    if (dialog && dialog.open) { try { dialog.close(); } catch (e) {} }
    if (window.FatecPoll) window.FatecPoll.markDone(slug);
  }

  // Cookie apagado, mas cópia no navegador: pergunta ao servidor se este dispositivo já votou.
  if (stored && stored !== serverToken) {
    fetch(slugUrl('status'), { credentials: 'same-origin', headers: { 'X-Device-Token': stored, 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d && d.token) { stored = d.token; store(stored); }
        if (d && d.voted) showAlreadyVoted();
      }).catch(function () { /* o servidor volta a validar no envio do voto */ });
  }
  function slugUrl(suffix) {
    var base = window.location.pathname.replace(/\/+$/, '');
    return base + '/' + suffix;
  }

  // Sinal antifraude (NÃO é identidade): características gerais do aparelho. O servidor guarda só um hash
  // e usa apenas para marcar suspeitas; aparelhos iguais na mesma rede podem ter o mesmo valor.
  function hash53(str) { // cyrb53: hash rápido sem criptografia (não exige HTTPS, ao contrário do crypto.subtle)
    var h1 = 0xdeadbeef, h2 = 0x41c6ce57;
    for (var i = 0; i < str.length; i++) {
      var ch = str.charCodeAt(i);
      h1 = Math.imul(h1 ^ ch, 2654435761); h2 = Math.imul(h2 ^ ch, 1597334677);
    }
    h1 = Math.imul(h1 ^ (h1 >>> 16), 2246822507) ^ Math.imul(h2 ^ (h2 >>> 13), 3266489909);
    h2 = Math.imul(h2 ^ (h2 >>> 16), 2246822507) ^ Math.imul(h1 ^ (h1 >>> 13), 3266489909);
    return (4294967296 * (2097151 & h2) + (h1 >>> 0)).toString(36);
  }
  function fingerprint() {
    try {
      var n = navigator, s = window.screen || {}, canvasHash = '';
      try {
        var c = document.createElement('canvas'); c.width = 120; c.height = 30;
        var x = c.getContext('2d'); x.textBaseline = 'top'; x.font = '14px Arial';
        x.fillStyle = '#f60'; x.fillRect(4, 2, 60, 20); x.fillStyle = '#069'; x.fillText('FatecPoll \u2713', 6, 4);
        canvasHash = hash53(c.toDataURL());
      } catch (e) {}
      var tz = ''; try { tz = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) {}
      return [s.width, s.height, s.colorDepth, window.devicePixelRatio, tz, (n.languages || [n.language]).join(','),
              n.platform, n.hardwareConcurrency, n.deviceMemory, n.maxTouchPoints, canvasHash]
        .join('|').replace(/[^A-Za-z0-9_.:|,;\/ +-]/g, '_').slice(0, 300);
    } catch (e) { return ''; }
  }

  if (!form || !dialog) return;
  var isCatalog = form.dataset.type === 'catalog';
  var $ = function (s, c) { return (c || form).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || form).querySelectorAll(s)); };

  var stepForm = $('[data-step="form"]'), stepReview = $('[data-step="review"]');
  var btnReview = $('[data-review]'), btnBack = $('[data-back]'), btnSubmit = $('[data-submit]');
  var errBox = $('[data-error]'), summary = $('[data-summary]');
  var sending = false;

  function showError(messages) {
    errBox.innerHTML = '';
    (Array.isArray(messages) ? messages : [messages]).forEach(function (m) {
      var p = document.createElement('div'); p.textContent = m; errBox.appendChild(p);
    });
    errBox.hidden = false;
  }
  function clearError() { errBox.hidden = true; errBox.innerHTML = ''; }

  function goto(step) {
    var review = step === 'review';
    stepForm.hidden = review; stepReview.hidden = !review;
    btnReview.hidden = review; btnBack.hidden = !review; btnSubmit.hidden = !review;
    clearError();
    var body = $('.vote-body'); if (body) body.scrollTop = 0;
  }

  function open() {
    if (window.FatecPoll && window.FatecPoll.isDone(slug)) return;
    goto('form');
    if (typeof dialog.showModal === 'function') dialog.showModal(); else dialog.setAttribute('open', '');
  }
  function close() {
    if (sending) return;
    if (typeof dialog.close === 'function') dialog.close(); else dialog.removeAttribute('open');
  }

  // Valida no navegador (o servidor valida de novo) e monta o resumo
  function buildSummary() {
    summary.innerHTML = '';
    if (!isCatalog) {
      var chosen = $('input[name="option"]:checked');
      if (!chosen) return showError('Escolha uma das alternativas.'), false;
      var li = document.createElement('li'); li.innerHTML = '<strong></strong>';
      li.firstChild.textContent = chosen.dataset.label; summary.appendChild(li);
      return true;
    }
    var rated = 0, problem = false;
    $$('[data-game]').forEach(function (g) {
      var radios = $$('input[type="radio"]:checked', g);
      if (radios.length === 0) return;
      if (radios.length !== 3) { problem = true; g.classList.add('is-incomplete'); return; }
      g.classList.remove('is-incomplete');
      rated++;
      var li = document.createElement('li');
      var name = document.createElement('strong'); name.textContent = g.dataset.name;
      var notes = document.createElement('span');
      notes.textContent = radios.map(function (r) {
        var label = { q_time: 'Tempo', q_fun: 'Diversão', q_replay: 'Jogaria de novo' }[r.dataset.q];
        return label + ' ' + r.value + '\u2605';
      }).join(' · ');
      li.appendChild(name); li.appendChild(notes); summary.appendChild(li);
    });
    if (problem) return showError('Responda as 3 perguntas de cada jogo que você avaliar (jogos incompletos estão destacados).'), false;
    if (rated === 0) return showError('Avalie ao menos um jogo.'), false;
    return true;
  }

  function send(ev) {
    ev.preventDefault();
    if (sending) return;
    sending = true; btnSubmit.disabled = true; btnSubmit.textContent = 'Enviando…'; clearError();

    var body = new FormData(form);
    body.set('fp', fingerprint());
    fetch(form.action, {
      method: 'POST',
      body: body,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'fetch', 'Accept': 'application/json', 'X-Device-Token': deviceToken() }
    }).then(function (res) {
      return res.json().catch(function () { return { ok: false, errors: ['Resposta inesperada do servidor. Tente novamente.'] }; });
    }).then(function (data) {
      if (data.ok || data.code === 'already_voted') {
        // O bloqueio local só é gravado DEPOIS que o servidor confirmou (a regra de verdade vive no servidor).
        if (window.FatecPoll) window.FatecPoll.markDone(slug);
        window.location.href = data.redirect;
        return;
      }
      goto('form'); showError(data.errors && data.errors.length ? data.errors : ['Não foi possível registrar o voto.']);
    }).catch(function () {
      // Erro de rede: nada foi confirmado, então o participante pode tentar de novo.
      goto('review'); showError('Sem conexão com o servidor. Seu voto NÃO foi registrado; tente novamente.');
    }).then(function () {
      sending = false; btnSubmit.disabled = false; btnSubmit.textContent = 'Confirmar e enviar';
    });
  }


  // ---------- Avaliação por estrelas (catálogo): modal por jogo, uma pergunta por vez ----------
  // Passo 1–3: uma pergunta com 1 a 5 estrelas. Passo 4: "avaliação geral" do jogo (média + resumo, com "Alterar").
  // Ao salvar, os valores vão para os radios (ocultos) do formulário; envio, revisão e validação do servidor não mudam.
  var rateDlg = document.getElementById('rate-dialog');
  if (isCatalog && rateDlg) {
    var LBL = ['', 'Muito ruim', 'Ruim', 'Regular', 'Bom', 'Excelente'];
    var SHORT = { q_time: 'Tempo', q_fun: 'Diversão', q_replay: 'Jogaria de novo' };
    var QS = ['q_time', 'q_fun', 'q_replay'];
    var curGame = null, vals = {}, step = 0, advance = null, editing = false;
    var el = function (s) { return rateDlg.querySelector(s); };
    var btnPrev = el('[data-rate-prev]'), btnNext = el('[data-rate-next]'), btnSave = el('[data-rate-save]');
    var dots = Array.prototype.slice.call(rateDlg.querySelectorAll('.rate-dots li'));
    function gameEl(id) { return form.querySelector('[data-game][data-id="' + id + '"]'); }
    function qBox(q) { return rateDlg.querySelector('[data-rate-q="' + q + '"]'); }
    function starsOn(box, v) { // pinta as estrelas de um grupo
      Array.prototype.forEach.call(box.querySelectorAll('svg'), function (s, i) { s.classList.toggle('is-on', i < v); });
    }
    function paint(q, hover) {
      var box = qBox(q), v = hover || vals[q] || 0;
      Array.prototype.forEach.call(box.querySelectorAll('.stars__b'), function (b) {
        var n = +b.dataset.v; b.classList.toggle('is-on', n <= v); b.setAttribute('aria-checked', n === vals[q] ? 'true' : 'false');
        b.tabIndex = (vals[q] ? n === vals[q] : n === 1) ? 0 : -1;
      });
      var lb = box.querySelector('[data-rate-lbl]'); lb.textContent = v ? v + ' · ' + LBL[v] : '\u00a0'; lb.classList.toggle('is-empty', !v);
    }
    function avg() { return (vals.q_time + vals.q_fun + vals.q_replay) / 3; }
    function fmt(n) { return n.toFixed(1).replace('.', ','); }
    function render() {
      var summary = step === 3;
      QS.forEach(function (q, i) { qBox(q).hidden = summary || i !== step; paint(q); });
      el('[data-rate-summary]').hidden = !summary;
      dots.forEach(function (d, i) { d.classList.toggle('is-on', i <= step); });
      el('[data-rate-sub]').textContent = summary ? 'Avaliação geral' : 'Pergunta ' + (step + 1) + ' de 3';
      btnPrev.hidden = step === 0;
      btnNext.hidden = summary; btnSave.hidden = !summary;
      if (!summary) {
        btnNext.disabled = !vals[QS[step]];
        btnNext.textContent = step === 2 ? 'Ver avaliação geral' : 'Próxima';
      } else {
        var a = avg(); el('[data-rate-avg]').textContent = fmt(a); starsOn(el('[data-rate-avg-stars]'), Math.round(a));
        el('[data-rate-avg-lbl]').textContent = LBL[Math.round(a)] + ' · média das 3 respostas';
        QS.forEach(function (q) { var m = el('[data-rate-sum="' + q + '"]'); m.innerHTML = el('[data-rate-avg-stars]').innerHTML; starsOn(m, vals[q]); m.setAttribute('aria-label', vals[q] + ' de 5'); });
      }
    }
    function go(n) { clearTimeout(advance); step = n; render(); var f = step < 3 ? qBox(QS[step]).querySelector('.stars__b[tabindex="0"]') : btnSave; if (f) f.focus({ preventScroll: true }); }
    function openRate(id) {
      if (window.FatecPoll && window.FatecPoll.isDone(slug)) return;
      var g = gameEl(id); if (!g) return;
      if (!dialog.open) open();
      curGame = g; vals = {}; editing = false;
      QS.forEach(function (q) { var r = g.querySelector('input[data-q="' + q + '"]:checked'); if (r) vals[q] = +r.value; });
      rateDlg.querySelector('[data-rate-title]').textContent = g.dataset.name;
      step = QS.every(function (q) { return vals[q]; }) ? 3 : 0; render();
      if (typeof rateDlg.showModal === 'function') rateDlg.showModal(); else rateDlg.setAttribute('open', '');
    }
    function closeRate() { clearTimeout(advance); if (typeof rateDlg.close === 'function') rateDlg.close(); else rateDlg.removeAttribute('open'); curGame = null; }
    function choose(q, v, auto) {
      vals[q] = v; render();
      if (auto) { clearTimeout(advance); advance = setTimeout(function () { if (step < 3 && vals[QS[step]]) go(editing ? 3 : step + 1); }, 450); }
    }
    function paintRow(g) {
      var row = g.querySelector('.rate-row'), st = g.querySelector('[data-rate-status]');
      var v = QS.map(function (q) { var r = g.querySelector('input[data-q="' + q + '"]:checked'); return r ? +r.value : 0; });
      var done = v.every(Boolean), a = done ? (v[0] + v[1] + v[2]) / 3 : 0;
      row.classList.toggle('is-rated', done);
      st.textContent = done ? 'Média ' + fmt(a) + ' · ' + QS.map(function (q, i) { return SHORT[q] + ' ' + v[i]; }).join(' · ') : 'Toque para avaliar';
      Array.prototype.forEach.call(g.querySelectorAll('[data-rate-stars] svg'), function (s, i) { s.classList.toggle('is-on', done && i < Math.round(a)); });
    }
    rateDlg.addEventListener('click', function (ev) {
      var b = ev.target.closest('.stars__b');
      if (b) { choose(b.closest('[data-rate-q]').dataset.rateQ, +b.dataset.v, true); return; }
      if (ev.target.closest('[data-rate-cancel]') || ev.target === rateDlg) return closeRate();
      if (ev.target.closest('[data-rate-prev]')) return go(Math.max(0, step - 1));
      if (ev.target.closest('[data-rate-next]')) return go(editing ? 3 : step + 1);
      var ed = ev.target.closest('[data-rate-edit]'); if (ed) { editing = true; return go(QS.indexOf(ed.dataset.rateEdit)); }
      if (ev.target.closest('[data-rate-save]') && curGame) {
        QS.forEach(function (q) { $$('input[data-q="' + q + '"]', curGame).forEach(function (r) { r.checked = +r.value === vals[q]; }); });
        curGame.classList.remove('is-incomplete'); paintRow(curGame); clearError(); closeRate();
      }
    });
    rateDlg.addEventListener('mouseover', function (ev) { var b = ev.target.closest('.stars__b'); if (b) paint(b.closest('[data-rate-q]').dataset.rateQ, +b.dataset.v); });
    rateDlg.addEventListener('mouseout', function (ev) { var b = ev.target.closest('.stars__b'); if (b) paint(b.closest('[data-rate-q]').dataset.rateQ); });
    rateDlg.addEventListener('keydown', function (ev) {
      var b = ev.target.closest('.stars__b'); if (!b) return;
      var q = b.closest('[data-rate-q]').dataset.rateQ, n = +b.dataset.v, auto = false;
      if (ev.key === 'ArrowRight' || ev.key === 'ArrowUp') n = Math.min(5, n + 1);
      else if (ev.key === 'ArrowLeft' || ev.key === 'ArrowDown') n = Math.max(1, n - 1);
      else if (/^[1-5]$/.test(ev.key)) { n = +ev.key; auto = true; } else return;
      ev.preventDefault(); choose(q, n, auto); var nb = qBox(q).querySelector('.stars__b[data-v="' + n + '"]'); if (nb) nb.focus();
    });
    document.addEventListener('click', function (ev) {
      var o = ev.target.closest('[data-rate-open]'); if (o) openRate(o.dataset.rateOpen);
      var c = ev.target.closest('[data-rate-card]'); if (c) openRate(c.dataset.rateCard);
    });
  }

  document.addEventListener('click', function (ev) {
    if (ev.target.closest('[data-vote-open]')) open();
    if (ev.target.closest('[data-vote-close]')) close();
  });
  btnReview.addEventListener('click', function () { clearError(); if (buildSummary()) goto('review'); });
  btnBack.addEventListener('click', function () { goto('form'); });
  form.addEventListener('submit', send);
  form.addEventListener('change', clearError);
  dialog.addEventListener('click', function (ev) { if (ev.target === dialog) close(); }); // clique no fundo
  dialog.addEventListener('cancel', function (ev) { if (sending) ev.preventDefault(); });
})();
