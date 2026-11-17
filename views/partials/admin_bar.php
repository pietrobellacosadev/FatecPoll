<?php /** Barra do painel: onde estou e o que posso fazer. Só aparece logado e em /admin/*, exceto login. */
if (!is_admin() || current_path() === '/admin/login') { return; } ?>
<div class="admin-bar">
  <nav class="admin-bar__tabs" aria-label="Painel">
    <a href="<?= e(url('/admin')) ?>"<?= nav_active('/admin', true) ?>>Visão geral</a>
    <a href="<?= e(url('/admin/enquetes')) ?>"<?= nav_active('/admin/enquetes') ?>><?= is_super_admin() ? 'Todas as enquetes' : 'Minhas enquetes' ?></a>
    <?php if (is_super_admin()): ?><a href="<?= e(url('/admin/professores')) ?>"<?= nav_active('/admin/professores') ?>>Professores</a><?php endif; ?>
  </nav>
  <a class="btn btn--sm" href="<?= e(url('/admin/enquetes/criar')) ?>"><?= icon('plus', 16) ?> Criar enquete</a>
</div>
