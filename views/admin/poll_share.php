<div class="page-head">
  <span class="eyebrow">Administração</span>
  <h1>Compartilhar enquete</h1>
  <p><?= e($poll['title']) ?></p>
  <?php if ($poll['status'] === 'draft'): ?><p class="linkbox-note">Esta enquete ainda é um rascunho: o link e o QR Code só funcionam depois de publicar.</p><?php endif; ?>
</div>

<div class="card share" data-reveal data-share data-title="<?= e($poll['title']) ?>" data-url="<?= e($link) ?>">
  <div>
    <h2>Link exclusivo</h2>
    <p class="linkbox-note" style="margin-top:0">Qualquer aparelho que abrir este link vê a mesma enquete. Cada participante pode votar uma única vez.</p>
    <div class="linkbox" style="margin-top:.4rem">
      <input class="input" type="text" readonly value="<?= e($link) ?>" aria-label="Link público da enquete" onfocus="this.select()">
    </div>
    <div class="share__actions">
      <button class="btn" type="button" data-copy="<?= e($link) ?>">Copiar link</button>
      <button class="btn btn--outline" type="button" data-share-link hidden>Compartilhar</button>
    </div>
    <div class="share__actions">
      <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/enquetes')) ?>">Voltar às enquetes</a>
      <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/enquetes/' . $poll['id'] . '/editar')) ?>">Editar</a>
    </div>
  </div>

  <div class="share__qr">
    <h2>QR Code</h2>
    <div class="share__qr-img" data-qr role="img" aria-label="QR Code do link da enquete"></div>
    <p class="linkbox-note" data-qr-error hidden>Não foi possível gerar o QR Code. Use o link ao lado.</p>
    <div class="share__actions share__actions--qr">
      <button class="btn btn--sm" type="button" data-qr-png>Baixar PNG</button>
      <button class="btn btn--outline btn--sm" type="button" data-qr-svg>Baixar SVG</button>
      <button class="btn btn--outline btn--sm" type="button" data-qr-copy hidden>Copiar imagem</button>
      <button class="btn btn--outline btn--sm" type="button" data-qr-share hidden>Compartilhar</button>
    </div>
  </div>
</div>
