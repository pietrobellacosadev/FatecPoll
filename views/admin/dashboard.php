<?php $statusLabel = ['published' => 'Aberta', 'closed' => 'Encerrada']; ?>
<div class="page-head">
  <span class="eyebrow">Olá, <?= e($_SESSION['admin_name'] ?? 'admin') ?> · <?= is_super_admin() ? 'Administrador' : 'Professor' ?></span>
  <h1>Dashboard</h1>
  <p><?= is_super_admin() ? 'Visão geral de todas as enquetes da plataforma.' : 'Aqui aparecem só as enquetes criadas por você.' ?> Escolha uma para ver os destaques e a classificação.</p>
</div>

<div class="stats stats--admin" style="margin-bottom:1.8rem" data-reveal>
  <div><strong data-count="<?= (int) $stats['votes'] ?>"><?= number_format($stats['votes'], 0, ',', '.') ?></strong><span>votos registrados</span></div>
  <div><strong data-count="<?= (int) $stats['open'] ?>"><?= (int) $stats['open'] ?></strong><span>enquetes abertas</span></div>
  <div><strong data-count="<?= (int) $stats['drafts'] ?>"><?= (int) $stats['drafts'] ?></strong><span>rascunhos</span></div>
  <div><strong data-count="<?= (int) $stats['today'] ?>"><?= number_format($stats['today'], 0, ',', '.') ?></strong><span>votos hoje</span></div>
</div>

<?php if (!$boards): ?>
  <div class="card empty"><?= icon('bars', 34) ?>Nenhuma enquete publicada ainda. <a href="<?= e(url('/admin/enquetes/criar')) ?>">Crie a primeira</a>.</div>
<?php else: ?>
  <?php
    $statusTxt = ['published' => 'Aberta', 'closed' => 'Encerrada'];
    $opts = [['all', 'layers', 'Todas as enquetes', count($boards) . ' no total']];
    foreach ($boards as $b) {
        $bp = $b['poll'];
        $opts[] = [(string) $bp['id'], $bp['type'] === 'catalog' ? 'gamepad' : 'list', $bp['title'],
                   ($bp['type'] === 'catalog' ? 'Catálogo de jogos' : 'Enquete') . ' · ' . ($statusTxt[$bp['status']] ?? '') . ' · ' . plural($bp['votes'], 'voto', 'votos')];
    }
    $current = array_values(array_filter($opts, fn($o) => $o[0] === $selected))[0] ?? $opts[0];
  ?>
  <div class="select" data-select data-filters="#boards" data-param="e" data-reveal>
    <span class="select__label-top" id="select-label">Analisar</span>
    <button type="button" class="select__btn" aria-haspopup="listbox" aria-expanded="false" aria-labelledby="select-label select-current">
      <span class="select__label" id="select-current" data-select-label>
        <span class="opt__ico"><?= icon($current[1], 20) ?></span>
        <span class="opt__txt"><strong><?= e($current[2]) ?></strong><small><?= e($current[3]) ?></small></span>
      </span>
      <span class="select__chev"><?= icon('chevron', 20) ?></span>
    </button>
    <ul class="select__menu" role="listbox" aria-labelledby="select-label" hidden>
      <?php foreach ($opts as $i => $o): ?>
        <li class="select__opt" role="option" tabindex="-1" data-filter="<?= e($o[0]) ?>" aria-selected="<?= $o[0] === $selected ? 'true' : 'false' ?>">
          <span class="opt__ico"><?= icon($o[1], 20) ?></span>
          <span class="opt__txt"><strong><?= e($o[2]) ?></strong><small><?= e($o[3]) ?></small></span>
          <span class="select__check"><?= icon('check', 18) ?></span>
        </li>
        <?php if ($i === 0): ?><li class="select__sep" role="separator"></li><?php endif; ?>
      <?php endforeach; ?>
    </ul>
  </div>

  <div class="boards" id="boards" style="gap:1.2rem">
    <?php foreach ($boards as $b):
      $poll = $b['poll']; $hl = $b['hl']; $rows = $hl['rows'];
      $isCatalog = $poll['type'] === 'catalog';
      $isSel = $selected === 'all' || (string) $poll['id'] === $selected; ?>
      <section class="card board" data-filter-item data-filter-key="<?= (int) $poll['id'] ?>" <?= $isSel ? '' : 'hidden' ?>>
        <header class="board__head">
          <div>
            <p class="board__sub">
              <span class="pill pill--<?= e($poll['status']) ?>"><?= e($statusLabel[$poll['status']] ?? '') ?></span>
              <?= $isCatalog ? 'Catálogo de jogos' : 'Enquete' ?><?= is_super_admin() ? ' · por ' . e($poll['owner_name'] ?? 'sem dono') : '' ?>
            </p>
            <h2><?= e($poll['title']) ?></h2>
          </div>
          <div class="board__actions">
            <a class="btn btn--outline btn--sm" href="<?= e(url('/e/' . $poll['slug'])) ?>">Página pública</a>
            <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/enquetes/' . $poll['id'] . '/resultados')) ?>">Detalhes</a>
          </div>
        </header>

        <div class="kpis">
          <div class="kpi"><strong data-count="<?= (int) $poll['participations'] ?>"><?= (int) $poll['participations'] ?></strong><span>participações</span></div>
          <div class="kpi"><strong data-count="<?= (int) $poll['votes'] ?>"><?= (int) $poll['votes'] ?></strong><span><?= $isCatalog ? 'avaliações' : 'votos' ?></span></div>
          <div class="kpi"><strong data-count="<?= (int) $poll['items'] ?>"><?= (int) $poll['items'] ?></strong><span><?= $isCatalog ? 'jogos' : 'opções' ?></span></div>
        </div>

        <?php include BASE_PATH . '/views/partials/highlights.php'; ?>

        <?php if (!$hl['empty']): ?>
          <?php include BASE_PATH . '/views/partials/charts.php'; ?>
          <h3 class="board__title"><?= icon('bars', 20) ?> <?= $isCatalog ? 'Classificação completa' : 'Votos por opção' ?></h3>
          <?php include BASE_PATH . '/views/partials/ranking.php'; ?>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
