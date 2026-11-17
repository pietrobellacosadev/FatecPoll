<?php /** Abas de uma enquete. Espera $poll e $tab ('results' | 'share' | 'edit'). */
$base = '/admin/enquetes/' . (int) $poll['id']; ?>
<div class="poll-tabs">
  <nav class="poll-tabs__list" aria-label="Seções da enquete">
    <a href="<?= e(url($base . '/resultados')) ?>"<?= $tab === 'results' ? ' aria-current="page"' : '' ?>>Resultados</a>
    <a href="<?= e(url($base . '/compartilhar')) ?>"<?= $tab === 'share' ? ' aria-current="page"' : '' ?>>Compartilhar</a>
    <a href="<?= e(url($base . '/editar')) ?>"<?= $tab === 'edit' ? ' aria-current="page"' : '' ?>>Editar</a>
  </nav>
  <a class="poll-tabs__back" href="<?= e(url('/admin/enquetes')) ?>">← Todas as enquetes</a>
</div>
