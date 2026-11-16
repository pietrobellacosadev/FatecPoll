<?php
/** Espera: $poll (type), $rows (de RankingService). */
$fmt = static fn(?float $n): string => $n === null ? '—' : number_format($n, 2, ',', '');
$labels = rating_labels();
$totalVotes = array_sum(array_column($rows, 'votes'));
?>
<?php if (!$rows || $totalVotes === 0): ?>
  <div class="empty"><?= icon('bars', 34) ?>Ainda não há votos nesta enquete.</div>
<?php elseif ($poll['type'] === 'catalog'): ?>
  <div class="rank" data-stagger="70">
    <?php foreach ($rows as $i => $r): $pct = $r['score'] === null ? 0 : max(0, min(100, ($r['score'] - 1) / 4 * 100)); ?>
      <div class="rank__row">
        <div class="rank__pos"><?= $i + 1 ?>º</div>
        <?= cover($r['name'], '', $r['cover_path'] ?? null) ?>
        <div>
          <div class="rank__name"><?= e($r['name']) ?></div>
          <div class="bar" role="img" aria-label="Pontuação <?= e($fmt($r['score'])) ?> de 5"><i style="--w:<?= round($pct) ?>%"></i></div>
          <div class="rank__sub">
            <?php $parts = []; foreach ($labels as $k => $label) { $parts[] = $label . ' ' . $fmt($r[$k]); } echo e(implode(' · ', $parts)); ?>
          </div>
        </div>
        <div class="rank__score"><span data-count="<?= e((string) round((float) $r['score'], 2)) ?>" data-decimals="2"><?= e($fmt($r['score'])) ?></span><small><?= e(plural($r['votes'], 'voto', 'votos')) ?></small></div>
      </div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="rank" data-stagger="70">
    <?php foreach ($rows as $i => $r): $pct = $totalVotes ? $r['votes'] / $totalVotes * 100 : 0; ?>
      <div class="rank__row rank__row--plain">
        <div class="rank__pos"><?= $i + 1 ?>º</div>
        <div>
          <div class="rank__name"><?= e($r['name']) ?></div>
          <div class="bar"><i style="--w:<?= round($pct) ?>%"></i></div>
        </div>
        <div class="rank__score"><span data-count="<?= round($pct) ?>"><?= round($pct) ?></span>%<small><?= e(plural($r['votes'], 'voto', 'votos')) ?></small></div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
