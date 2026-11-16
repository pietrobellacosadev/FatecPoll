<?php
$type  = $old['type'] ?? 'catalog';
$games = $old['games'] ?? [];
$opts  = $old['options'] ?? [];
if (!$games) { $games = [[]]; }            // começa com 1 jogo em branco
while (count($opts) < 2) { $opts[] = []; } // e 2 opções em branco
require __DIR__ . '/_item_blocks.php';
?>
<div class="page-head">
  <span class="eyebrow">Administração</span>
  <h1>Nova enquete</h1>
  <p>Crie um catálogo de jogos para avaliar ou uma pergunta com várias respostas.</p>
</div>

<?php if ($errors): ?>
  <div class="alert alert--error form-wide" role="alert">
    <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
    <?php if ($type === 'catalog' && !empty($old['games'])): ?><div><small>Por segurança, os arquivos de imagem precisam ser escolhidos de novo.</small></div><?php endif; ?>
  </div>
<?php endif; ?>

<form class="form-wide" method="post" action="<?= e(url('/admin/enquetes')) ?>" enctype="multipart/form-data" novalidate>
  <?= csrf_field() ?>

  <div class="card form-section" data-reveal>
    <div class="form-step"><span class="form-step__n">1</span><h2>Tipo</h2><p>O que você quer criar?</p></div>
    <fieldset class="type-pick">
      <legend class="visually-hidden">Tipo</legend>
      <label class="type-card"><input type="radio" name="type" value="catalog" <?= $type === 'catalog' ? 'checked' : '' ?>>
        <span class="type-card__icon"><?= icon('gamepad', 26) ?></span><strong>Catálogo de jogos</strong>
        <small>Cada jogo recebe notas de 1 a 5 em três perguntas.</small></label>
      <label class="type-card"><input type="radio" name="type" value="options" <?= $type === 'options' ? 'checked' : '' ?>>
        <span class="type-card__icon"><?= icon('list', 26) ?></span><strong>Enquete</strong>
        <small>Uma pergunta com várias respostas; o participante escolhe uma.</small></label>
    </fieldset>
  </div>

  <div class="card form-section" data-reveal>
    <div class="form-step"><span class="form-step__n">2</span><h2>Informações</h2></div>
    <div class="field">
      <label for="title">Título</label>
      <input class="input" id="title" name="title" maxlength="190" value="<?= e($old['title'] ?? '') ?>" placeholder="Ex.: Feira de Jogos Fatec 2026">
    </div>
    <div class="field" style="margin-bottom:0">
      <label for="description">Descrição (opcional)</label>
      <input class="input" id="description" name="description" value="<?= e($old['description'] ?? '') ?>" placeholder="Uma frase para apresentar a votação">
    </div>
  </div>

  <!-- Catálogo -->
  <fieldset class="section-block" id="section-catalog" <?= $type === 'catalog' ? '' : 'hidden disabled' ?>>
    <div class="form-step" style="margin-top:1.4rem"><span class="form-step__n">3</span><h2>Jogos</h2><p>Cadastre cada jogo do catálogo</p></div>
    <div class="item-list" id="games-list" data-min="1">
      <?php foreach ($games as $i => $g) { echo $gameBlock($i, $g); } ?>
    </div>
    <button type="button" class="btn btn--outline add-btn" id="add-game"><?= icon('plus', 14) ?> Adicionar jogo</button>
    <template id="game-template"><?= $gameBlock('__i__', []) ?></template>
  </fieldset>

  <!-- Enquete -->
  <fieldset class="section-block" id="section-options" <?= $type === 'options' ? '' : 'hidden disabled' ?>>
    <div class="card form-section" style="margin-top:1.4rem">
      <div class="form-step"><span class="form-step__n">3</span><h2>Pergunta e opções</h2></div>
      <div class="field">
        <label for="question">Pergunta</label>
        <input class="input" id="question" name="question" maxlength="255" value="<?= e($old['question'] ?? '') ?>" placeholder="Ex.: Qual gênero devemos expandir no próximo semestre?">
      </div>
      <div class="field" style="margin-bottom:0">
        <label>Opções de resposta</label>
        <div class="item-list" id="options-list" data-min="2" style="gap:.6rem">
          <?php foreach ($opts as $o) { echo $optBlock($o); } ?>
        </div>
        <button type="button" class="btn btn--outline add-btn" id="add-option"><?= icon('plus', 14) ?> Adicionar opção</button>
      </div>
    </div>
    <template id="option-template"><?= $optBlock([]) ?></template>
  </fieldset>

  <div class="card form-section" data-reveal>
    <div class="form-step"><span class="form-step__n">4</span><h2>Publicação</h2></div>
    <label class="switch">
      <input type="checkbox" name="publish" value="1" <?= !empty($old['publish']) ? 'checked' : '' ?>>
      <span class="switch__track"></span>
      <span><strong>Publicar agora</strong><small>Desligado, fica como rascunho e não aparece para o público.</small></span>
    </label>
  </div>

  <div class="form-actions">
    <a class="btn btn--ghost" href="<?= e(url('/admin/enquetes')) ?>">Cancelar</a>
    <button class="btn btn--lg" type="submit"><?= icon('check', 18) ?> Salvar</button>
  </div>
</form>
