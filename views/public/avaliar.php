<div class="page-head">
  <span class="eyebrow">Participe</span>
  <h1>Avaliar</h1>
  <p>Escolha uma enquete para participar. Não é necessário criar conta.</p>
</div>

<?php if (!$polls): ?>
  <div class="card empty" data-reveal><?= icon('list', 34) ?>Nenhuma enquete disponível no momento.</div>
<?php else:
  $nOpen = count(array_filter($polls, fn($p) => $p['status'] === 'published'));
  $nClosed = count($polls) - $nOpen; ?>
  <div class="chips-bar" data-filters="#poll-list" role="group" aria-label="Filtrar enquetes">
    <button type="button" class="chip-btn" data-filter="all" aria-pressed="true"><span>Todas</span><b class="count"><?= count($polls) ?></b></button>
    <button type="button" class="chip-btn" data-filter="published" aria-pressed="false"><span>Abertas</span><b class="count"><?= $nOpen ?></b></button>
    <button type="button" class="chip-btn" data-filter="closed" aria-pressed="false"><span>Encerradas</span><b class="count"><?= $nClosed ?></b></button>
  </div>

  <div id="poll-list">
    <div class="poll-grid even-grid" data-even-grid data-stagger="80">
      <?php foreach ($polls as $i => $p) { $gridClass = even_grid_class($i, count($polls)); include BASE_PATH . '/views/partials/poll_card.php'; } ?>
    </div>
    <div class="card empty" data-filter-empty hidden>Nenhuma enquete neste filtro.</div>
  </div>
<?php endif; ?>
