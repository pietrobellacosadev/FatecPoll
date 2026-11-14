<?php $statusLabel = ['published' => 'Aberta', 'closed' => 'Encerrada']; ?>
<div class="page-head">
  <span class="eyebrow"><?= $poll['type'] === 'catalog' ? 'Catálogo de jogos' : 'Enquete' ?> · Resultados</span>
  <h1><?= e($poll['title']) ?></h1>
  <p><?= e(plural((int) $hl['total'], 'voto', 'votos')) ?></p>
  <span class="pill pill--<?= e($poll['status']) ?>"><?= e($statusLabel[$poll['status']] ?? '') ?></span>
  <div class="center-actions" style="margin:.6rem 0 0"><a class="btn btn--ghost" href="<?= e(url('/e/' . $poll['slug'])) ?>">Voltar à enquete</a></div>
</div>

<div class="card" style="margin-bottom:1.25rem" data-reveal>
  <h2 class="board__title" style="margin-top:0">Destaques</h2>
  <?php include BASE_PATH . '/views/partials/highlights.php'; ?>
</div>

<?php if (!$hl['empty']): $rows = $hl['rows']; ?>
<div class="card" style="margin-bottom:1.25rem" data-reveal>
  <h2 class="board__title" style="margin-top:0">Gráficos</h2>
  <?php include BASE_PATH . '/views/partials/charts.php'; ?>
</div>
<div class="card" data-reveal>
  <h2 class="board__title" style="margin-top:0"><?= $poll['type'] === 'catalog' ? 'Classificação completa' : 'Votos por opção' ?></h2>
  <?php include BASE_PATH . '/views/partials/ranking.php'; ?>
</div>
<?php endif; ?>
