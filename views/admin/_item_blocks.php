<?php
/**
 * Blocos reutilizados pelos formulários de criação e de edição de enquetes.
 * Define $gameBlock($i, $g) e $optBlock($opt) no escopo de quem inclui.
 *
 * Jogo existente: $g['id'] (hidden) e $g['cover_path'] (mantida se nenhuma imagem nova for enviada).
 * Alternativa existente: $opt = ['id' => int, 'label' => string]; o id vai em option_ids[] (mesma ordem de options[]).
 */
$emptyPreview = icon('image', 26) . '<span>Arraste uma imagem aqui<br>ou escolha abaixo</span>';

/** Bloco de um jogo. $i = índice (ou __i__ no <template>). */
$gameBlock = static function ($i, array $g) use ($emptyPreview) {
    $id    = (int) ($g['id'] ?? 0);
    $cover = (string) ($g['cover_path'] ?? '');
    $votes = (int) ($g['votes'] ?? 0);
    ob_start(); ?>
  <fieldset class="item-card" data-item data-votes="<?= $votes ?>">
    <input type="hidden" name="games[<?= e((string) $i) ?>][id]" value="<?= $id ?: '' ?>">
    <div class="item-card__head">
      <strong data-item-title>Jogo</strong>
      <button type="button" class="btn btn--ghost btn--sm" data-remove><?= icon('x', 14) ?> Remover</button>
    </div>
    <div class="item-card__grid">
      <div class="img-pick">
        <div class="img-pick__preview" data-preview data-empty="<?= e($emptyPreview) ?>"><?php
            if ($cover !== '') { ?><img src="<?= e(url($cover)) ?>" alt="Capa atual"><?php } else { echo $emptyPreview; }
        ?></div>
        <label class="btn btn--outline img-pick__btn"><?= $cover !== '' ? 'Trocar imagem' : 'Escolher imagem' ?>
          <input type="file" name="games[<?= e((string) $i) ?>][image]" accept="image/png,image/jpeg,image/webp,image/gif" hidden data-file>
        </label>
        <input class="input" type="url" name="games[<?= e((string) $i) ?>][image_url]" value="<?= e($g['image_url'] ?? '') ?>" placeholder="ou cole um link" data-url>
        <small>JPG, PNG, WEBP ou GIF · até 2 MB. Se usar link, a imagem é baixada e guardada no servidor.<?= $cover !== '' ? ' Sem nova imagem, a atual é mantida.' : '' ?></small>
      </div>
      <div>
        <div class="field">
          <label>Nome do jogo</label>
          <input class="input" name="games[<?= e((string) $i) ?>][name]" maxlength="160" value="<?= e($g['name'] ?? '') ?>" placeholder="Ex.: Neon Drift">
        </div>
        <div class="field">
          <label>Descrição</label>
          <textarea class="input" rows="3" name="games[<?= e((string) $i) ?>][description]" placeholder="Do que se trata o jogo?"><?= e($g['description'] ?? '') ?></textarea>
        </div>
        <div class="field" style="margin-bottom:0">
          <label>Integrantes</label>
          <textarea class="input" rows="2" name="games[<?= e((string) $i) ?>][members]" placeholder="Ana Souza, Bruno Lima, Carla Dias"><?= e($g['members'] ?? '') ?></textarea>
          <small>Separe os nomes por vírgula ou um por linha.</small>
        </div>
      </div>
    </div>
  </fieldset>
<?php return ob_get_clean(); };

/** Bloco de uma alternativa. $opt = ['id' => int, 'label' => string, 'votes' => int] (ou [] para uma nova). */
$optBlock = static function (array $opt) {
    $id    = (int) ($opt['id'] ?? 0);
    $votes = (int) ($opt['votes'] ?? 0);
    ob_start(); ?>
  <div class="option-item" data-item data-votes="<?= $votes ?>">
    <span class="option-item__n" data-item-title>1</span>
    <input type="hidden" name="option_ids[]" value="<?= $id ?: '' ?>">
    <input class="input" name="options[]" maxlength="190" value="<?= e($opt['label'] ?? '') ?>" placeholder="Digite uma opção">
    <button type="button" class="btn btn--ghost btn--sm" data-remove aria-label="Remover opção"><?= icon('x', 14) ?></button>
  </div>
<?php return ob_get_clean(); };
