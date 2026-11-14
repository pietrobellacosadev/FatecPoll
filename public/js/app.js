// FatecPoll — comportamentos e animações (JS puro, sem bibliotecas)
(function () {
  'use strict';

  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var $  = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  // ---------- Aviso próprio (no lugar do alert do navegador) ----------
  var noticeTimer;
  function notify(msg) {
    var n = $('.notice');
    if (!n) {
      n = document.createElement('div'); n.className = 'notice'; n.setAttribute('role', 'status');
      document.body.appendChild(n);
    }
    n.textContent = msg;
    void n.offsetWidth; n.classList.add('is-on');
    clearTimeout(noticeTimer);
    noticeTimer = setTimeout(function () { n.classList.remove('is-on'); }, 4200);
  }

  // ---------- Progresso de rolagem e botão "voltar ao topo" ----------
  // ---------- Menu mobile ----------
  var toggle = $('.nav-toggle'), menu = $('#header-menu');
  if (toggle && menu) {
    toggle.addEventListener('click', function () {
      var open = menu.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', String(open));
    });
  }

  // ---------- Cabeçalho com sombra ao rolar ----------
  var header = $('.site-header');
  if (header) {
    var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 8); };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  // ---------- Números que contam até o valor final ----------
  var fmt = function (n, dec) {
    return new Intl.NumberFormat('pt-BR', { minimumFractionDigits: dec, maximumFractionDigits: dec }).format(n);
  };
  function countUp(el) {
    var to = parseFloat(el.dataset.count), dec = parseInt(el.dataset.decimals || '0', 10);
    if (isNaN(to)) return;
    if (reduced) { el.textContent = fmt(to, dec); return; }
    var start = null, dur = 1200;
    el.textContent = fmt(0, dec);
    (function step(t) {
      if (start === null) start = t;
      var p = Math.min(1, (t - start) / dur);
      el.textContent = fmt(to * (1 - Math.pow(1 - p, 3)), dec);
      if (p < 1) requestAnimationFrame(step);
    })(performance.now());
  }

  // ---------- Revelar ao rolar (IntersectionObserver) ----------
  var io = null;
  function reveal(el) {
    if (el.hasAttribute('data-reveal')) {
      el.classList.add('is-in');
      setTimeout(function () { el.classList.add('is-done'); }, 1100);
    }
    if (el.hasAttribute('data-count')) countUp(el);
  }
  if ('IntersectionObserver' in window && !reduced) {
    io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { reveal(en.target); io.unobserve(en.target); }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
  }
  // Escalonamento: filhos diretos de [data-stagger] entram em sequência
  $$('[data-stagger]').forEach(function (box) {
    var step = parseInt(box.dataset.stagger, 10) || 80;
    Array.prototype.forEach.call(box.children, function (child, i) {
      child.setAttribute('data-reveal', '');
      child.style.setProperty('--d', (i * step) + 'ms');
    });
  });
  function watch(el) { if (io) io.observe(el); else reveal(el); }
  $$('[data-reveal], [data-count]').forEach(watch);

  // Reinicia as animações de um trecho que acabou de aparecer (ex.: troca de enquete no dashboard)
  function replay(container) {
    $$('[data-reveal]', container).concat(container.hasAttribute('data-reveal') ? [container] : []).forEach(function (el) {
      el.classList.remove('is-in', 'is-done'); watch(el);
    });
    $$('[data-count]', container).forEach(watch);
  }

  // ---------- Ripple nos botões ----------
  document.addEventListener('pointerdown', function (ev) {
    var btn = ev.target.closest && ev.target.closest('.btn');
    if (!btn || btn.disabled || reduced) return;
    var r = btn.getBoundingClientRect(), s = document.createElement('span');
    s.className = 'ripple';
    s.style.left = (ev.clientX - r.left) + 'px';
    s.style.top = (ev.clientY - r.top) + 'px';
    btn.appendChild(s);
    s.addEventListener('animationend', function () { s.remove(); });
  });

  // ---------- Hero: parallax suave dos blobs ----------
  var hero = $('.hero');
  if (hero && !reduced) {
    hero.addEventListener('mousemove', function (ev) {
      var r = hero.getBoundingClientRect();
      hero.style.setProperty('--mx', ((ev.clientX - r.left) / r.width * 2 - 1).toFixed(3));
      hero.style.setProperty('--my', ((ev.clientY - r.top) / r.height * 2 - 1).toFixed(3));
    });
    hero.addEventListener('mouseleave', function () { hero.style.setProperty('--mx', 0); hero.style.setProperty('--my', 0); });
  }

  // ---------- Copiar link (botões com data-copy) ----------
  function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) { return navigator.clipboard.writeText(text); }
    return new Promise(function (resolve, reject) { // http (ex.: IP da rede local): usa o método antigo
      var ta = document.createElement('textarea');
      ta.value = text; ta.setAttribute('readonly', ''); ta.style.cssText = 'position:fixed;top:-1000px;opacity:0';
      document.body.appendChild(ta); ta.select();
      try { document.execCommand('copy') ? resolve() : reject(); } catch (e) { reject(e); }
      document.body.removeChild(ta);
    });
  }
  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest && ev.target.closest('[data-copy]');
    if (!btn) return;
    ev.preventDefault();
    copyText(btn.getAttribute('data-copy')).then(
      function () { notify('Link copiado!'); },
      function () { notify('Não foi possível copiar. Selecione o link e copie manualmente.'); }
    );
  });

  // ---------- Toast (mensagem flash): some sozinho ----------
  $$('.toast').forEach(function (t) {
    var close = function () { t.classList.add('is-out'); setTimeout(function () { t.remove(); }, 450); };
    t.addEventListener('click', close);
    setTimeout(close, 5200);
  });

  // ---------- Grade em linhas cheias (mesma regra de even_grid_class() no PHP) ----------
  // Máximo por fileira conforme a largura: 4 (desktop), 3, 2 e 1 (celular).
  function maxPerRow() {
    var w = window.innerWidth;
    return w >= 1100 ? 4 : w >= 760 ? 3 : w >= 560 ? 2 : 1;
  }
  // 3 → [3] | 4 → [4] (ou [2,2] em telas menores) | 6 → [3,3] | 7 → [4,3] | 5 → [3,2]
  function rowSizes(n, max) {
    var rows = Math.ceil(n / max), base = Math.floor(n / rows), extra = n % rows, out = [];
    for (var i = 0; i < rows; i++) out.push(i < extra ? base + 1 : base);
    return out;
  }
  function evenGrid(grid) {
    var items = Array.prototype.filter.call(grid.children, function (c) { return !c.hidden; });
    if (!items.length) return;
    var idx = 0;
    rowSizes(items.length, maxPerRow()).forEach(function (size) {
      for (var k = 0; k < size; k++, idx++) {
        items[idx].classList.remove('s3', 's4', 's6', 's12');
        items[idx].classList.add('s' + (12 / size));
      }
    });
  }
  function layoutGrids() { $$('[data-even-grid]').forEach(evenGrid); }
  layoutGrids();
  var resizeTimer;
  window.addEventListener('resize', function () { clearTimeout(resizeTimer); resizeTimer = setTimeout(layoutGrids, 80); });

  // ---------- Filtros (chips de status e seletor do dashboard) ----------
  $$('[data-filters]').forEach(function (bar) {
    var scope = $(bar.dataset.filters);
    if (!scope) return;
    var param = bar.dataset.param;
    var items = $$('[data-filter-item]', scope);
    var empty = $('[data-filter-empty]', scope);
    var controls = $$('[data-filter]', bar);
    var label = $('[data-select-label]', bar);

    function apply(value, animate) {
      var shown = 0;
      controls.forEach(function (c) {
        var on = c.dataset.filter === value;
        c.setAttribute(c.getAttribute('role') === 'option' ? 'aria-selected' : 'aria-pressed', String(on));
        if (on && label) {  // espelha a opção escolhida no botão do dropdown
          label.innerHTML = '';
          ['.opt__ico', '.opt__txt'].forEach(function (sel) { var n = $(sel, c); if (n) label.appendChild(n.cloneNode(true)); });
        }
      });
      items.forEach(function (it) {
        var show = value === 'all' || it.dataset.filterKey === value;
        if (show) shown++;
        if (show && it.hidden) {
          it.hidden = false;
          if (animate && !reduced) { it.classList.remove('pop-in'); void it.offsetWidth; it.classList.add('pop-in'); }
          replay(it);
        } else if (!show) { it.hidden = true; }
      });
      $$('[data-even-grid]', scope).forEach(evenGrid);
      if (empty) empty.hidden = shown !== 0;
      if (param && window.history.replaceState) {
        var u = new URL(window.location.href); u.searchParams.set(param, value);
        window.history.replaceState(null, '', u.toString());
      }
    }
    bar.addEventListener('click', function (ev) {
      var c = ev.target.closest('[data-filter]');
      if (c) apply(c.dataset.filter, true);
    });
    bar._apply = apply;
  });

  // ---------- Dropdown personalizado (teclado, clique fora, Esc) ----------
  $$('[data-select]').forEach(function (sel) {
    var btn = $('.select__btn', sel), menu = $('.select__menu', sel);
    var opts = $$('[role="option"]', menu);
    function isOpen() { return !menu.hidden; }
    function focusOpt(i) { i = Math.max(0, Math.min(opts.length - 1, i)); opts[i].focus(); }
    function open() {
      menu.hidden = false; sel.classList.add('is-open'); btn.setAttribute('aria-expanded', 'true');
      var cur = opts.findIndex(function (o) { return o.getAttribute('aria-selected') === 'true'; });
      focusOpt(cur < 0 ? 0 : cur);
    }
    function close(refocus) {
      menu.hidden = true; sel.classList.remove('is-open'); btn.setAttribute('aria-expanded', 'false');
      if (refocus) btn.focus();
    }
    function choose(o) { sel._apply && sel._apply(o.dataset.filter, true); close(true); }
    sel._apply = sel._apply || null;
    btn.addEventListener('click', function () { isOpen() ? close(false) : open(); });
    btn.addEventListener('keydown', function (ev) {
      if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') { ev.preventDefault(); open(); }
    });
    menu.addEventListener('click', function (ev) { if (ev.target.closest('[role="option"]')) close(true); }); // o filtro aplica a escolha
    menu.addEventListener('keydown', function (ev) {
      var i = opts.indexOf(document.activeElement);
      if (ev.key === 'ArrowDown') { ev.preventDefault(); focusOpt(i + 1); }
      else if (ev.key === 'ArrowUp') { ev.preventDefault(); focusOpt(i - 1); }
      else if (ev.key === 'Home') { ev.preventDefault(); focusOpt(0); }
      else if (ev.key === 'End') { ev.preventDefault(); focusOpt(opts.length - 1); }
      else if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); if (i >= 0) choose(opts[i]); }
      else if (ev.key === 'Escape') { ev.preventDefault(); close(true); }
      else if (ev.key === 'Tab') { close(false); }
    });
    document.addEventListener('pointerdown', function (ev) { if (isOpen() && !sel.contains(ev.target)) close(false); });
  });

  // ---------- Tema claro/escuro (salvo no navegador; os gráficos acompanham) ----------
  var themeBtn = $('[data-theme-toggle]');
  if (themeBtn) {
    themeBtn.addEventListener('click', function () {
      var root = document.documentElement;
      var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', next);
      try { localStorage.setItem('fatecpoll:theme', next); } catch (e) {}
      document.dispatchEvent(new CustomEvent('fatecpoll:theme', { detail: next }));
    });
  }

  // ---------- Bloqueio local por enquete (UX apenas — não é segurança) ----------
  window.FatecPoll = {
    notify: notify,
    reduced: reduced,
    storageKey: function (slug) { return 'fatecpoll:done:' + slug; },
    markDone: function (slug) { try { localStorage.setItem(this.storageKey(slug), '1'); } catch (e) {} },
    isDone: function (slug) { try { return localStorage.getItem(this.storageKey(slug)) === '1'; } catch (e) { return false; } }
  };
  var pollEl = $('[data-poll-slug]');
  if (pollEl && window.FatecPoll.isDone(pollEl.dataset.pollSlug)) {
    var done = $('#already-voted'), area = $('#vote-area');
    if (done) done.hidden = false;
    if (area) area.hidden = true;
  }
})();
