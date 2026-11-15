<?php $statusLabel = ['published' => 'Aberta', 'closed' => 'Encerrada', 'draft' => 'Rascunho']; $votes = $hl['total']; ?>
<div class="page-head">
  <span class="eyebrow"><?= $poll['type'] === 'catalog' ? 'Catálogo de jogos' : 'Enquete' ?></span>
  <h1><?= e($poll['title']) ?></h1>
  <p><?= e(plural($participations, 'participação', 'participações')) ?> · <?= e(plural($votes, 'voto', 'votos')) ?></p>
  <?php if (!empty($risky)): ?><p class="linkbox-note" title="Mesmo aparelho (fingerprint) e mesma rede de uma participação anterior, mas sem o identificador do dispositivo. Pode ser fraude ou apenas aparelhos iguais na mesma rede.">⚠ <?= e(plural($risky, 'participação com sinal', 'participações com sinal')) ?> de possível duplicidade.</p><?php endif; ?>
  <span class="pill pill--<?= e($poll['status']) ?>"><?= e($statusLabel[$poll['status']] ?? '') ?></span>
  <div class="center-actions" style="margin:.6rem 0 0;gap:.5rem">
    <a class="btn btn--ghost" href="<?= e(url('/admin/enquetes')) ?>">Voltar</a>
    <a class="btn btn--outline" href="<?= e(url('/admin?e=' . $poll['id'])) ?>"><?= icon('bars', 15) ?> No dashboard</a>
    <a class="btn btn--outline" href="<?= e(url('/admin/enquetes/' . $poll['id'] . '/editar')) ?>">Editar</a>
    <?php if ($poll['status'] !== 'draft'): ?><a class="btn" href="<?= e(url('/e/' . $poll['slug'])) ?>">Abrir página pública</a><?php endif; ?>
  </div>
</div>

<div class="card" style="margin-bottom:1.25rem" data-reveal>
  <h2 class="board__title" style="margin-top:0">Destaques</h2>
  <?php include BASE_PATH . '/views/partials/highlights.php'; ?>
</div>

<?php if (!$hl['empty']): ?>
<div class="card" style="margin-bottom:1.25rem" data-reveal>
  <h2 class="board__title" style="margin-top:0">Gráficos</h2>
  <?php include BASE_PATH . '/views/partials/charts.php'; ?>
</div>
<div class="card" data-reveal>
  <h2 class="board__title" style="margin-top:0"><?= $poll['type'] === 'catalog' ? 'Classificação completa' : 'Votos por opção' ?></h2>
  <?php include BASE_PATH . '/views/partials/ranking.php'; ?>
</div>
<?php endif; ?>
