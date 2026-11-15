<?php
/** Espera: $p (item de listWithMeta). Opcional: $admin (bool). */
$admin = $admin ?? false;
$isCatalog = $p['type'] === 'catalog';
$status = $p['status'];
$statusLabel = ['published' => 'Aberta', 'closed' => 'Encerrada', 'draft' => 'Rascunho'][$status] ?? $status;
$link = $status === 'draft' ? url('/admin/enquetes/' . $p['id'] . '/resultados') : url('/e/' . $p['slug']);
?>
<article class="poll-card <?= e($gridClass ?? '') ?>" data-filter-item data-filter-key="<?= e($status) ?>">
  <a class="poll-card__media" href="<?= e($link) ?>" tabindex="-1" aria-hidden="true">
    <?= collage($p['covers']) ?>
    <span class="poll-card__type"><?= icon($isCatalog ? 'gamepad' : 'list', 14) ?> <?= $isCatalog ? 'Catálogo de jogos' : 'Enquete' ?></span>
  </a>
  <div class="poll-card__body">
    <span class="pill pill--<?= e($status) ?>"><?= e($statusLabel) ?></span>
    <h3><?= e($p['title']) ?></h3>
    <?php if ($admin && is_super_admin()): ?>
      <p class="poll-card__meta">Criada por <?= e($p['owner_name'] ?? 'sem dono (anterior ao cadastro de professores)') ?></p>
    <?php endif; ?>
    <p class="poll-card__meta">
      <?= e(plural($p['items'], $isCatalog ? 'jogo' : 'opção', $isCatalog ? 'jogos' : 'opções')) ?>
      · <?= e(plural($p['votes'], 'voto', 'votos')) ?>
    </p>
    <div class="poll-card__actions">
      <?php if ($admin): ?>
        <a class="btn btn--sm" href="<?= e(url('/admin?e=' . $p['id'])) ?>"><?= icon('bars', 14) ?> Dashboard</a>
        <?php if ($status !== 'draft'): ?><a class="btn btn--outline btn--sm" href="<?= e($link) ?>">Abrir</a><?php endif; ?>
        <?php if ($status !== 'draft'): ?><button class="btn btn--outline btn--sm" type="button" data-copy="<?= e(absolute_url('/e/' . $p['slug'])) ?>" title="Copiar o link para os participantes votarem">Copiar link</button><?php endif; ?>
        <?php if ($status !== 'draft'): ?><a class="btn btn--outline btn--sm" href="<?= e(url('/admin/enquetes/' . $p['id'] . '/compartilhar')) ?>" title="QR Code e compartilhamento">QR Code</a><?php endif; ?>
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/enquetes/' . $p['id'] . '/editar')) ?>">Editar</a>
        <?php if (($p['type'] ?? '') === 'catalog'): ?><a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/enquetes/' . $p['id'] . '/editar?novo_jogo=1')) ?>"><?= icon('plus', 14) ?> Jogo</a><?php endif; ?>
        <?php if ($status === 'published'): ?>
          <form method="post" action="<?= e(url('/admin/enquetes/' . $p['id'] . '/encerrar')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn--ghost btn--sm" type="submit">Encerrar</button>
          </form>
        <?php else: ?>
          <form method="post" action="<?= e(url('/admin/enquetes/' . $p['id'] . '/publicar')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn--dark btn--sm" type="submit"><?= $status === 'closed' ? 'Reabrir' : 'Publicar' ?></button>
          </form>
        <?php endif; ?>
      <?php else: ?>
        <a class="btn <?= $status === 'published' ? '' : 'btn--outline' ?>" href="<?= e($link) ?>">
          <?= $status === 'published' ? 'Participar' : 'Ver ' . ($isCatalog ? 'jogos' : 'opções') ?> <?= icon('arrow', 16) ?>
        </a>
      <?php endif; ?>
    </div>
  </div>
</article>
