<div class="page-head" data-poll-slug="<?= e($poll['slug']) ?>" data-device-token="<?= e($deviceToken ?? '') ?>">
  <span class="eyebrow"><?= $poll['type'] === 'catalog' ? 'Catálogo de jogos' : 'Enquete' ?></span>
  <h1><?= e($poll['title']) ?></h1>
  <?php if (!empty($poll['description'])): ?><p><?= e($poll['description']) ?></p><?php endif; ?>
  <span class="pill pill--<?= e($poll['status']) ?>"><?= $isOpen ? 'Aberta' : 'Encerrada' ?></span>
</div>

<?php if (!$isOpen): ?>
  <div class="alert alert--info" style="max-width:640px;margin-inline:auto">Esta enquete está encerrada e não aceita novos votos.</div>
<?php endif; ?>

<div id="already-voted" class="alert alert--success" style="max-width:640px;margin-inline:auto"<?= $hasVoted ? '' : ' hidden' ?>>
  Você já votou nesta enquete neste aparelho. Obrigado!
</div>
<?php if ($hasVoted && (int) $poll['results_public']): ?>
  <div class="center-actions"><a class="btn btn--outline" href="<?= e(url('/resultados/' . $poll['slug'])) ?>"><?= icon('bars', 16) ?> Ver resultados</a></div>
<?php endif; ?>

<section id="vote-area" aria-labelledby="items-title"<?= $hasVoted ? ' hidden' : '' ?>>
  <h2 id="items-title" class="visually-hidden">Itens da enquete</h2>

  <?php if ($poll['type'] === 'options' && !empty($poll['question'])): ?>
    <h2 class="question" data-reveal><?= e($poll['question']) ?></h2>
  <?php endif; ?>

  <?php if (!$items): ?>
    <div class="card empty">Nenhum item cadastrado ainda.</div>
  <?php elseif ($poll['type'] === 'catalog'): ?>
    <div class="game-grid" data-stagger="90">
      <?php foreach ($items as $gi => $it): ?>
        <article class="card game-card card-lift">
          <?= cover($it['name'], 'cover--big', $it['cover_path']) ?>
          <div class="game-card__body">
            <h3><?= e($it['name']) ?></h3>
            <?php if (!empty($it['description'])): ?><p><?= e($it['description']) ?></p><?php endif; ?>
            <?php if ($it['members']): ?>
              <div class="members" aria-label="Integrantes">
                <?php foreach ($it['members'] as $m): ?><span class="chip"><?= e($m) ?></span><?php endforeach; ?>
              </div>
            <?php endif; ?>
            <?php if ($isOpen && !$hasVoted): ?>
              <button type="button" class="btn btn--outline btn--sm game-card__rate" data-rate-card="<?= (int) $it['id'] ?>"><?= icon('thumbs-up', 15) ?> Avaliar este jogo</button>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="option-list" data-stagger="90">
      <?php foreach ($items as $i => $it): ?>
        <div class="card option-card card-lift"><span class="option-card__n"><?= $i + 1 ?></span><h3><?= e($it['name']) ?></h3></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($isOpen && $items && !$hasVoted): ?>
    <div class="center-actions">
      <?php if ($poll['type'] === 'catalog'): ?>
        <button class="btn btn--lg" type="button" data-vote-open data-catalog-final hidden><?= icon('thumbs-up', 18) ?> Revisar e enviar votação</button>
        <p data-catalog-progress role="status" aria-live="polite">Avalie todos os jogos para liberar o envio final.</p>
      <?php else: ?>
        <button class="btn btn--lg" type="button" data-vote-open><?= icon('thumbs-up', 18) ?> Iniciar votação</button>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if ((int) $poll['results_public'] && !$isOpen): ?>
    <div class="center-actions"><a class="btn btn--outline" href="<?= e(url('/resultados/' . $poll['slug'])) ?>"><?= icon('bars', 16) ?> Ver resultados</a></div>
  <?php endif; ?>
</section>

<?php if ($isOpen && $items && !$hasVoted): $isCatalog = $poll['type'] === 'catalog'; $questions = rating_questions(); ?>
<dialog class="vote-dialog" id="vote-dialog" aria-labelledby="vote-title">
  <form id="vote-form" method="post" action="<?= e(url('/e/' . $poll['slug'] . '/votar')) ?>" novalidate data-type="<?= e($poll['type']) ?>">
    <?= csrf_field() ?>
    <header class="vote-head">
      <h2 id="vote-title"><?= $isCatalog ? 'Avalie os jogos' : e($poll['question'] ?? 'Escolha uma alternativa') ?></h2>
      <button type="button" class="btn btn--ghost btn--sm" data-vote-close aria-label="Fechar"><?= icon('x', 16) ?></button>
    </header>

    <div class="vote-body">
      <div data-step="form">
        <?php if ($isCatalog): ?>
          <p class="vote-hint">Todos os jogos são obrigatórios. Escolha cada jogo na lista, responda às três perguntas e confirme. Depois revise o resumo geral.</p>
          <ul class="rate-list">
          <?php foreach ($items as $it): ?>
            <li>
              <fieldset class="vote-game" data-game data-name="<?= e($it['name']) ?>" data-id="<?= (int) $it['id'] ?>">
                <legend class="visually-hidden"><?= e($it['name']) ?></legend>
                <button type="button" class="rate-row" data-rate-open="<?= (int) $it['id'] ?>">
                  <?= cover($it['name'], 'cover--thumb', $it['cover_path']) ?>
                  <span class="rate-row__txt"><strong><?= e($it['name']) ?></strong><small data-rate-status>Toque para avaliar</small></span>
                  <span class="rate-row__stars" data-rate-stars aria-hidden="true"><svg viewBox="0 0 24 24" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg><svg viewBox="0 0 24 24" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg><svg viewBox="0 0 24 24" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg><svg viewBox="0 0 24 24" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg><svg viewBox="0 0 24 24" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></span>
                </button>
                <div hidden>
                  <?php foreach ($questions as $qk => $qt): ?>
                    <?php for ($n = 1; $n <= 5; $n++): ?>
                      <input type="radio" name="ratings[<?= (int) $it['id'] ?>][<?= e($qk) ?>]" value="<?= $n ?>" data-q="<?= e($qk) ?>">
                    <?php endfor; ?>
                  <?php endforeach; ?>
                </div>
              </fieldset>
            </li>
          <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="vote-hint">Escolha uma alternativa.</p>
          <div class="vote-options" role="radiogroup" aria-labelledby="vote-title">
            <?php foreach ($items as $it): ?>
              <label class="vote-option"><input type="radio" name="option" value="<?= (int) $it['id'] ?>" data-label="<?= e($it['name']) ?>"><span><?= e($it['name']) ?></span></label>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div data-step="review" hidden>
        <p class="vote-hint">Confira antes de enviar. Depois de confirmado, o voto não poderá ser alterado.</p>
        <ul class="vote-summary" data-summary></ul>
      </div>

      <p class="alert alert--error vote-error" role="alert" data-error hidden></p>
    </div>

    <footer class="vote-foot">
      <button type="button" class="btn btn--ghost" data-vote-close>Cancelar</button>
      <button type="button" class="btn btn--ghost" data-back hidden>Voltar</button>
      <button type="button" class="btn" data-review>Revisar</button>
      <button type="submit" class="btn" data-submit hidden>Confirmar e enviar</button>
    </footer>
  </form>
</dialog>

<?php if ($isCatalog): ?>
<dialog class="rate-dialog" id="rate-dialog" aria-labelledby="rate-title">
  <div class="rate-box">
    <div class="rate-hero">
      <span class="rate-hero__star"><svg class="star-ico star-ico--lg" viewBox="0 0 24 24" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></span>
      <h2 id="rate-title" data-rate-title>Avaliar jogo</h2>
      <p data-rate-sub>Pergunta 1 de 3</p>
      <ol class="rate-dots" aria-hidden="true"><li></li><li></li><li></li><li></li></ol>
    </div>
    <div class="rate-qs">
      <?php foreach ($questions as $qk => $qt): ?>
        <div class="rate-q" data-rate-q="<?= e($qk) ?>" hidden>
          <span class="rate-q__n">Pergunta <?= array_search($qk, array_keys($questions)) + 1 ?></span>
          <span class="rate-q__t"><?= e($qt) ?></span>
          <div class="stars" role="radiogroup" aria-label="<?= e($qt) ?>">
            <?php for ($n = 1; $n <= 5; $n++): ?>
              <button type="button" class="stars__b" role="radio" aria-checked="false" data-v="<?= $n ?>" aria-label="<?= $n ?> <?= $n === 1 ? 'estrela' : 'estrelas' ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></button>
            <?php endfor; ?>
          </div>
          <div class="stars-ends" aria-hidden="true"><span>Muito ruim</span><span>Excelente</span></div>
          <small class="rate-q__lbl" data-rate-lbl>&nbsp;</small>
        </div>
      <?php endforeach; ?>
      <div class="rate-summary" data-rate-summary hidden aria-hidden="true">
        <div class="rate-summary__score">
          <strong data-rate-avg>0,0</strong>
          <span class="rate-summary__stars" data-rate-avg-stars><svg viewBox="0 0 24 24" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg><svg viewBox="0 0 24 24" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg><svg viewBox="0 0 24 24" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg><svg viewBox="0 0 24 24" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg><svg viewBox="0 0 24 24" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></span>
          <small data-rate-avg-lbl></small>
        </div>
        <ul class="rate-summary__list">
          <?php foreach ($questions as $qk => $qt): ?>
            <li><span><?= e($qt) ?></span><span class="rate-summary__mini" data-rate-sum="<?= e($qk) ?>"></span><button type="button" class="btn btn--ghost btn--sm" data-rate-edit="<?= e($qk) ?>">Alterar</button></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
    <footer class="rate-foot">
      <button type="button" class="btn btn--ghost" data-rate-cancel>Cancelar</button>
      <button type="button" class="btn btn--ghost" data-rate-prev hidden>Voltar</button>
      <button type="button" class="btn" data-rate-next disabled>Próxima</button>
      <button type="button" class="btn" data-rate-save hidden>Salvar avaliação</button>
    </footer>
  </div>
</dialog>
<?php endif; ?>
<?php endif; ?>
