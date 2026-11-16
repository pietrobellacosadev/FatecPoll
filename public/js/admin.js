// FatecPoll — formulário de cadastro de enquetes (admin). JS puro.
(function () {
  'use strict';

  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var $  = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var notify = function (m) { if (window.FatecPoll) window.FatecPoll.notify(m); };

  // ---------- Formulário de cadastro ----------
  var anchor = $('#section-catalog') || $('#section-options');
  var form = anchor && anchor.closest('form');
  if (form) {
    var MAX_BYTES = 2 * 1024 * 1024;
    var secCatalog = $('#section-catalog'), secOptions = $('#section-options');
    var gamesList = $('#games-list'), optionsList = $('#options-list');
    var nextIndex = gamesList ? $$('[data-item]', gamesList).length : 0;

    // Na criação há o seletor de tipo; na edição o tipo é fixo e só uma das seções existe.
    var syncType = function (animate) {
      if (!secCatalog || !secOptions) return;
      var isOptions = $('input[name="type"]:checked', form).value === 'options';
      var show = isOptions ? secOptions : secCatalog, hide = isOptions ? secCatalog : secOptions;
      hide.hidden = true; hide.disabled = true;
      var wasHidden = show.hidden;
      show.hidden = false; show.disabled = false;
      if (animate && wasHidden && !reduced) { show.classList.remove('pop-in'); void show.offsetWidth; show.classList.add('pop-in'); }
    };
    $$('input[name="type"]', form).forEach(function (r) { r.addEventListener('change', function () { syncType(true); }); });

    var renumber = function (list, label) {
      var its = $$('[data-item]', list);
      its.forEach(function (it, i) {
        var t = $('[data-item-title]', it);
        if (t) t.textContent = label ? label + ' ' + (i + 1) : String(i + 1);
        var rm = $('[data-remove]', it);
        if (rm) rm.hidden = its.length <= Number(list.dataset.min);
      });
    };

    var setPreview = function (item, src) {
      var box = $('[data-preview]', item);
      box.innerHTML = '';
      if (!src) { box.innerHTML = box.dataset.empty; return; }
      var img = document.createElement('img');
      img.alt = 'Pré-visualização';
      img.onerror = function () { box.innerHTML = '<span>Não foi possível mostrar a imagem</span>'; };
      img.src = src;
      box.appendChild(img);
    };

    var addFrom = function (tplId, list, label, replaceIndex) {
      var html = document.getElementById(tplId).innerHTML;
      if (replaceIndex) html = html.split('__i__').join(String(nextIndex++));
      var wrap = document.createElement('div');
      wrap.innerHTML = html.trim();
      var node = wrap.firstElementChild;
      list.appendChild(node);
      renumber(list, label);
      node.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'center' });
      var first = $('input:not([type=file]):not([hidden]), textarea', node);
      if (first) setTimeout(function () { first.focus({ preventScroll: true }); }, 250);
    };
    var addGame = $('#add-game'), addOption = $('#add-option');
    if (addGame) addGame.addEventListener('click', function () { addFrom('game-template', gamesList, 'Jogo', true); });
    // Atalho "+ Jogo" dos cards: abre a edição já com um jogo novo em branco, pronto para preencher.
    if (addGame && /[?&]novo_jogo=1/.test(window.location.search)) {
      addGame.click();
    }
    if (addOption) addOption.addEventListener('click', function () { addFrom('option-template', optionsList, '', false); });

    form.addEventListener('click', function (ev) {
      var rm = ev.target.closest('[data-remove]');
      if (!rm) return;
      var list = rm.closest('[data-min]'), item = rm.closest('[data-item]');
      var votes = Number(item.dataset.votes || 0);
      if (votes > 0 && !window.confirm('Este item já recebeu ' + votes + (votes === 1 ? ' voto' : ' votos') +
          '. Ao salvar, ele sai da votação e dos resultados (os votos continuam guardados). Remover mesmo assim?')) return;
      var done = function () { item.remove(); renumber(list, list === gamesList ? 'Jogo' : ''); };
      if (reduced) return done();
      item.classList.add('is-leaving');
      item.addEventListener('animationend', done, { once: true });
      setTimeout(function () { if (item.parentNode) done(); }, 400);
    });

    var pickFile = function (item, f) {
      if (f && f.size > MAX_BYTES) {
        notify('A imagem "' + f.name + '" passa de 2 MB. Escolha uma menor ou use o link.');
        $('[data-file]', item).value = ''; setPreview(item, ''); return;
      }
      if (f) { $('[data-url]', item).value = ''; setPreview(item, URL.createObjectURL(f)); }
    };
    form.addEventListener('change', function (ev) {
      var item = ev.target.closest('[data-item]');
      if (!item) return;
      if (ev.target.matches('[data-file]')) {
        pickFile(item, ev.target.files[0]);
      } else if (ev.target.matches('[data-url]')) {
        var v = ev.target.value.trim();
        if (v) $('[data-file]', item).value = '';
        setPreview(item, v);
      }
    });

    // Arrastar e soltar a imagem sobre a pré-visualização
    ['dragenter', 'dragover'].forEach(function (n) {
      form.addEventListener(n, function (ev) { var b = ev.target.closest('[data-preview]'); if (b) { ev.preventDefault(); b.classList.add('is-drag'); } });
    });
    ['dragleave', 'drop'].forEach(function (n) {
      form.addEventListener(n, function (ev) { var b = ev.target.closest('[data-preview]'); if (b) b.classList.remove('is-drag'); });
    });
    form.addEventListener('drop', function (ev) {
      var b = ev.target.closest('[data-preview]');
      if (!b) return;
      ev.preventDefault();
      var f = ev.dataTransfer.files[0], item = b.closest('[data-item]');
      if (f && /^image\//.test(f.type)) {
        var input = $('[data-file]', item), dt = new DataTransfer();
        dt.items.add(f); input.files = dt.files; pickFile(item, f);
      }
    });

    if (gamesList) $$('[data-item]', gamesList).forEach(function (it) {
      var u = $('[data-url]', it); if (u && u.value) setPreview(it, u.value);
    });
    if (gamesList) renumber(gamesList, 'Jogo');
    if (optionsList) renumber(optionsList, '');
    syncType(false);
  }

})();
