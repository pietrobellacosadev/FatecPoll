// FatecPoll — tela de compartilhamento: QR Code (gerado no navegador por qr.js), copiar, compartilhar e baixar.
(function () {
  'use strict';

  var box = document.querySelector('[data-share]');
  if (!box) return;
  var $ = function (s) { return box.querySelector(s); };
  var url = box.dataset.url, title = box.dataset.title || 'Enquete';
  var qrBox = $('[data-qr]'), qrErr = $('[data-qr-error]');
  var notify = function (m) { if (window.FatecPoll) window.FatecPoll.notify(m); };

  if (!window.FPQr) { qrErr.hidden = false; return; }
  try {
    qrBox.innerHTML = window.FPQr.svg(url, { border: 4 });
  } catch (e) {
    qrErr.hidden = false; return;
  }

  function pngBlob() {
    return new Promise(function (resolve, reject) {
      var c = window.FPQr.canvas(url, { scale: 12, border: 4 });
      if (c.toBlob) c.toBlob(function (b) { b ? resolve(b) : reject(); }, 'image/png'); else reject();
    });
  }
  function download(blob, name) {
    var a = document.createElement('a'), href = URL.createObjectURL(blob);
    a.href = href; a.download = name; document.body.appendChild(a); a.click(); a.remove();
    setTimeout(function () { URL.revokeObjectURL(href); }, 1500);
  }

  $('[data-qr-png]').addEventListener('click', function () {
    pngBlob().then(function (b) { download(b, 'qrcode-enquete.png'); }, function () { notify('Não foi possível gerar a imagem.'); });
  });
  $('[data-qr-svg]').addEventListener('click', function () {
    download(new Blob([window.FPQr.svg(url, { border: 4 })], { type: 'image/svg+xml' }), 'qrcode-enquete.svg');
  });

  // Compartilhar o link (menu de compartilhamento do celular/navegador, quando existir)
  var shareLink = $('[data-share-link]');
  if (navigator.share) {
    shareLink.hidden = false;
    shareLink.addEventListener('click', function () {
      navigator.share({ title: title, text: title, url: url }).catch(function () { /* cancelado */ });
    });
  }

  // Compartilhar a imagem do QR Code
  var shareQr = $('[data-qr-share]');
  if (navigator.share && navigator.canShare && typeof File === 'function') {
    pngBlob().then(function (b) {
      var file = new File([b], 'qrcode-enquete.png', { type: 'image/png' });
      if (!navigator.canShare({ files: [file] })) return;
      shareQr.hidden = false;
      shareQr.addEventListener('click', function () {
        navigator.share({ files: [file], title: title, text: url }).catch(function () { /* cancelado */ });
      });
    }, function () {});
  }

  // Copiar a imagem do QR Code (exige HTTPS ou localhost)
  var copyQr = $('[data-qr-copy]');
  if (navigator.clipboard && window.ClipboardItem && window.isSecureContext) {
    copyQr.hidden = false;
    copyQr.addEventListener('click', function () {
      pngBlob().then(function (b) {
        return navigator.clipboard.write([new ClipboardItem({ 'image/png': b })]);
      }).then(function () { notify('Imagem do QR Code copiada!'); }, function () { notify('Não foi possível copiar a imagem. Use Baixar PNG.'); });
    });
  }
})();
