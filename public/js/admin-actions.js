/* Painel do FatecPoll: confirmação de ações, trava de duplo clique, menus "Mais" e filtro de professores. */
(function () {
  'use strict';
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

  // ---------- Confirmação (data-confirm) + trava de duplo clique em formulários POST ----------
  document.addEventListener('submit', function (ev) {
    var f = ev.target;
    if (!(f instanceof HTMLFormElement)) return;
    var msg = f.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) { ev.preventDefault(); return; }
    if (ev.defaultPrevented) return;                       // outra validação já barrou o envio
    if ((f.method || '').toLowerCase() !== 'post' || f.hasAttribute('data-no-lock')) return;
    if (f.dataset.locked === '1') { ev.preventDefault(); return; }   // segundo clique: ignora
    f.dataset.locked = '1';
    setTimeout(function () {                               // depois do envio, para o botão clicado ainda ir no POST
      $$('button[type="submit"], button:not([type]), input[type="submit"]', f).forEach(function (b) { b.disabled = true; b.dataset.wasLocked = '1'; });
    }, 0);
  });
  // Voltar pelo histórico (bfcache) não pode deixar o formulário travado
  window.addEventListener('pageshow', function () {
    $$('form[data-locked="1"]').forEach(function (f) {
      delete f.dataset.locked;
      $$('[data-was-locked="1"]', f).forEach(function (b) { b.disabled = false; delete b.dataset.wasLocked; });
    });
  });

  // ---------- Menu "Mais" dos cards: fecha ao clicar fora, ao escolher um item e com Esc ----------
  function closeMenus(except) {
    $$('details.card-menu[open]').forEach(function (d) { if (d !== except) d.removeAttribute('open'); });
  }
  document.addEventListener('click', function (ev) {
    var inside = ev.target.closest && ev.target.closest('details.card-menu');
    closeMenus(inside);
    if (inside && ev.target.closest('.card-menu__list a, .card-menu__list [data-copy]')) { inside.removeAttribute('open'); }
  });
  document.addEventListener('keydown', function (ev) {
    if (ev.key !== 'Escape') return;
    var open = document.querySelector('details.card-menu[open]');
    if (open) { open.removeAttribute('open'); var s = open.querySelector('summary'); if (s) s.focus(); }
  });

  // ---------- Professores: busca (sem acento) + filtro por situação ----------
  var list = document.querySelector('[data-teacher-list]');
  if (list) {
    var rows = $$('[data-teacher-row]', list);
    var input = document.querySelector('[data-teacher-search]');
    var chips = $$('[data-t-filter]');
    var empty = document.querySelector('[data-teacher-empty]');
    var state = 'all';
    var norm = function (t) { return String(t || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim(); };
    var apply = function () {
      var q = norm(input && input.value), shown = 0;
      rows.forEach(function (r) {
        var ok = (state === 'all' || r.dataset.state === state) && (!q || norm(r.dataset.search).indexOf(q) !== -1);
        r.hidden = !ok;
        if (ok) shown++;
      });
      if (empty) empty.hidden = shown !== 0;
    };
    chips.forEach(function (c) {
      c.addEventListener('click', function () {
        state = c.dataset.tFilter;
        chips.forEach(function (o) { o.setAttribute('aria-pressed', String(o === c)); });
        apply();
      });
    });
    if (input) input.addEventListener('input', apply);
  }
})();
