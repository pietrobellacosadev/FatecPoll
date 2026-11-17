<?php
$isCatalog = $poll['type'] === 'catalog';
$items = $items ?: ($isCatalog ? [[]] : [[], []]);
while (!$isCatalog && count($items) < 2) { $items[] = []; }
require __DIR__ . '/_item_blocks.php';
?>
<?php $tab = 'edit'; include BASE_PATH . '/views/partials/poll_tabs.php'; ?>
<div class="page-head">
  <span class="eyebrow">Painel</span>
  <h1><?= $isCatalog ? 'Editar catálogo' : 'Editar enquete' ?></h1>
  <p>O link público desta enquete não muda ao editar.</p>
  <?php $publicLink = absolute_url('/e/' . $poll['slug']); ?>
  <div class="linkbox">
    <input class="input" type="text" readonly value="<?= e($publicLink) ?>" aria-label="Link público da enquete" onfocus="this.select()">
    <button class="btn" type="button" data-copy="<?= e($publicLink) ?>">Copiar link</button>
    <a class="btn btn--outline" href="<?= e(url('/admin/enquetes/' . $poll['id'] . '/compartilhar')) ?>">QR Code</a>
  </div>
  <?php if ($poll['status'] === 'draft'): ?><p class="linkbox-note">Esta enquete ainda é um rascunho: o link só funciona depois de publicar.</p><?php endif; ?>
</div>

<?php if ($errors): ?>
  <div class="alert alert--error form-wide" role="alert">
    <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
    <?php if ($isCatalog): ?><div><small>Por segurança, as imagens novas precisam ser escolhidas de novo.</small></div><?php endif; ?>
  </div>
<?php endif; ?>

<form class="form-wide" method="post" action="<?= e(url('/admin/enquetes/' . $poll['id'] . '/editar')) ?>" enctype="multipart/form-data" novalidate>
  <?= csrf_field() ?>

  <div class="card form-section" data-reveal>
    <div class="form-step"><span class="form-step__n">1</span><h2>Informações</h2></div>
    <div class="field">
      <label for="title">Título</label>
      <input class="input" id="title" name="title" maxlength="190" value="<?= e($poll['title']) ?>" required>
    </div>
    <div class="field">
      <label for="description">Descrição (opcional)</label>
      <textarea class="input" id="description" name="description" rows="2"><?= e($poll['description'] ?? '') ?></textarea>
    </div>
    <label class="switch">
      <input type="checkbox" name="results_public" value="1" <?= (int) $poll['results_public'] ? 'checked' : '' ?>>
      <span class="switch__track"></span>
      <span><strong>Resultados públicos</strong><small>Quando ligado, a página /resultados/<?= e($poll['slug']) ?> fica visível a todos.</small></span>
    </label>
  </div>

  <?php if ($participations > 0): ?>
    <div class="alert form-wide" role="note" style="margin-top:1.4rem">
      Esta <?= $isCatalog ? 'votação' : 'enquete' ?> já tem <strong><?= (int) $participations ?></strong> participaç<?= $participations === 1 ? 'ão' : 'ões' ?>.
      Os votos ficam guardados: você pode corrigir textos e imagens e adicionar itens. Se remover um item que já foi votado,
      ele sai da votação e dos resultados, mas os votos não são apagados.
    </div>
  <?php endif; ?>

  <?php if ($isCatalog): ?>
    <fieldset class="section-block" id="section-catalog">
      <div class="form-step" style="margin-top:1.4rem"><span class="form-step__n">2</span><h2>Jogos</h2><p>Edite, remova ou adicione jogos</p></div>
      <div class="item-list" id="games-list" data-min="1">
        <?php foreach ($items as $i => $g) { echo $gameBlock($i, $g); } ?>
      </div>
      <button type="button" class="btn btn--outline add-btn" id="add-game"><?= icon('plus', 14) ?> Adicionar jogo</button>
      <template id="game-template"><?= $gameBlock('__i__', []) ?></template>
    </fieldset>
  <?php else: ?>
    <fieldset class="section-block" id="section-options">
      <div class="card form-section" style="margin-top:1.4rem">
        <div class="form-step"><span class="form-step__n">2</span><h2>Pergunta e opções</h2></div>
        <div class="field">
          <label for="question">Pergunta</label>
          <input class="input" id="question" name="question" maxlength="255" value="<?= e($poll['question'] ?? '') ?>" required>
        </div>
        <div class="field" style="margin-bottom:0">
          <label>Opções de resposta</label>
          <div class="item-list" id="options-list" data-min="2" style="gap:.6rem">
            <?php foreach ($items as $o) { echo $optBlock($o); } ?>
          </div>
          <button type="button" class="btn btn--outline add-btn" id="add-option"><?= icon('plus', 14) ?> Adicionar opção</button>
        </div>
      </div>
      <template id="option-template"><?= $optBlock([]) ?></template>
    </fieldset>
  <?php endif; ?>

  <div class="form-actions">
    <a class="btn btn--ghost" href="<?= e(url('/admin/enquetes')) ?>">Cancelar</a>
    <button class="btn btn--lg" type="submit"><?= icon('check', 18) ?> Salvar alterações</button>
  </div>
</form>
