<?php
/** Espera: $poll, $hl (RankingService::highlights). Opcional: $compact (bool). */
$compact = $compact ?? false;
$labels = rating_labels();
$questions = rating_questions();
?>
<?php if ($hl['empty']): ?>
  <div class="empty"><?= icon('trophy', 34) ?>Ainda não há votos nesta enquete.</div>
<?php elseif ($hl['type'] === 'catalog'): $o = $hl['overall']; ?>
  <div class="hl" data-stagger="110">
    <article class="hl-main">
      <span class="hl-tag"><?= icon('trophy', 14) ?> Mais bem avaliado · geral</span>
      <div class="hl-main__body">
        <?= cover($o['name'], 'cover--big', $o['cover_path']) ?>
        <div>
          <h3><?= e($o['name']) ?></h3>
          <?php if ($o['members']): ?><p class="hl-members"><?= e(implode(', ', $o['members'])) ?></p><?php endif; ?>
          <div class="hl-score"><strong data-count="<?= e((string) round((float) $o['score'], 2)) ?>" data-decimals="2"><?= e(fmt_score($o['score'])) ?></strong><span>de 5 · <?= e(plural($o['votes'], 'avaliação', 'avaliações')) ?></span></div>
        </div>
      </div>
    </article>

    <?php foreach ($hl['by_question'] as $k => $g): ?>
      <article class="hl-card">
        <span class="hl-tag"><?= e($labels[$k]) ?></span>
        <small class="hl-q"><?= e($questions[$k]) ?></small>
        <div class="hl-card__row">
          <?= cover($g['name'], '', $g['cover_path']) ?>
          <div>
            <strong class="hl-card__name"><?= e($g['name']) ?></strong>
            <div class="hl-card__score"><span data-count="<?= e((string) round((float) $g[$k], 2)) ?>" data-decimals="2"><?= e(fmt_score($g[$k])) ?></span> <small>/ 5</small></div>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php else: $w = $hl['winner']; ?>
  <div class="hl hl--option" data-stagger="110">
    <article class="hl-main hl-main--option">
      <span class="hl-tag"><?= icon('trophy', 14) ?> Resposta mais votada</span>
      <?php if (!empty($poll['question'])): ?><small class="hl-q"><?= e($poll['question']) ?></small><?php endif; ?>
      <h3><?= e($w['name']) ?></h3>
      <div class="hl-score"><strong><span data-count="<?= round($hl['percent']) ?>"><?= round($hl['percent']) ?></span>%</strong><span><?= e(plural($w['votes'], 'voto', 'votos')) ?> de <?= (int) $hl['total'] ?></span></div>
      <?php if ($hl['tied']): ?>
        <p class="hl-tie">Empate com <?= e(implode(', ', array_column($hl['tied'], 'name'))) ?>.</p>
      <?php endif; ?>
    </article>
  </div>
<?php endif; ?>
