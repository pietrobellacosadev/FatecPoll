<?php
$counts = ['published' => 0, 'draft' => 0, 'closed' => 0];
foreach ($polls as $p) { $counts[$p['status']] = ($counts[$p['status']] ?? 0) + 1; }
?>
<div class="page-head">
  <span class="eyebrow">Administração</span>
  <h1>Enquetes</h1>
  <p><?= is_super_admin() ? 'Todas as enquetes da plataforma, de todos os professores.' : 'Suas votações: você só vê e edita as enquetes que criou.' ?></p>
  <a class="btn" href="<?= e(url('/admin/enquetes/criar')) ?>"><?= icon('plus', 16) ?> Criar enquete</a>
</div>

<?php if (!$polls): ?>
  <div class="card empty" data-reveal><?= icon('list', 34) ?>Nenhuma enquete criada ainda. <a href="<?= e(url('/admin/enquetes/criar')) ?>">Crie a primeira</a>.</div>
<?php else: ?>
  <div class="chips-bar" data-filters="#poll-list" role="group" aria-label="Filtrar enquetes">
    <button type="button" class="chip-btn" data-filter="all" aria-pressed="true"><span>Todas</span><b class="count"><?= count($polls) ?></b></button>
    <button type="button" class="chip-btn" data-filter="published" aria-pressed="false"><span>Abertas</span><b class="count"><?= $counts['published'] ?></b></button>
    <button type="button" class="chip-btn" data-filter="draft" aria-pressed="false"><span>Rascunhos</span><b class="count"><?= $counts['draft'] ?></b></button>
    <button type="button" class="chip-btn" data-filter="closed" aria-pressed="false"><span>Encerradas</span><b class="count"><?= $counts['closed'] ?></b></button>
  </div>

  <div id="poll-list">
    <div class="poll-grid even-grid" data-even-grid data-stagger="70">
      <?php foreach ($polls as $i => $p) { $admin = true; $gridClass = even_grid_class($i, count($polls)); include BASE_PATH . '/views/partials/poll_card.php'; } ?>
    </div>
    <div class="card empty" data-filter-empty hidden>Nenhuma enquete neste filtro.</div>
  </div>
<?php endif; ?>
