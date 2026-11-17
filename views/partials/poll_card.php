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
      <?php if ($admin):
        $id = (int) $p['id'];
        $res = url('/admin/enquetes/' . $id . '/resultados');
        $edit = url('/admin/enquetes/' . $id . '/editar');
        $share = url('/admin/enquetes/' . $id . '/compartilhar');
        $pub = url('/e/' . $p['slug']);
        $postBtn = static function (string $action, string $label, string $cls, string $confirm) use ($id): string {
            return '<form method="post" action="' . e(url('/admin/enquetes/' . $id . '/' . $action)) . '" data-confirm="' . e($confirm) . '">'
                . csrf_field() . '<button class="' . $cls . '" type="submit">' . e($label) . '</button></form>';
        };
        // No máximo 2 botões à vista; o resto fica em "Mais". O principal muda conforme a situação da enquete.
        $menu = [];
        if ($status === 'draft') {
            echo '<a class="btn btn--sm" href="' . e($edit) . '">Continuar editando</a>';
            echo $postBtn('publicar', 'Publicar', 'btn btn--dark btn--sm', 'Publicar agora? O link passa a aceitar votos.');
        } else {
            echo '<a class="btn btn--sm" href="' . e($res) . '">' . icon('bars', 14) . ' Resultados</a>';
            if ($status === 'published') {
                echo '<a class="btn btn--outline btn--sm" href="' . e($share) . '" title="Link, QR Code e compartilhamento">Compartilhar</a>';
                $menu[] = ['a', 'Abrir página pública', $pub];
                $menu[] = ['copy', 'Copiar link', absolute_url('/e/' . $p['slug'])];
                $menu[] = ['a', 'Editar', $edit];
            } else {
                echo $postBtn('publicar', 'Reabrir', 'btn btn--dark btn--sm', 'Reabrir a enquete? Ela volta a aceitar votos.');
                $menu[] = ['a', 'Abrir página pública', $pub];
                $menu[] = ['a', 'Compartilhar', $share];
                $menu[] = ['a', 'Editar', $edit];
            }
        }
        if ($isCatalog) { $menu[] = ['a', '+ Adicionar jogo', url('/admin/enquetes/' . $id . '/editar?novo_jogo=1')]; }
        if ($menu || $status === 'published'): ?>
        <details class="card-menu">
          <summary class="btn btn--ghost btn--sm" aria-label="Mais ações">Mais</summary>
          <div class="card-menu__list">
            <?php foreach ($menu as [$kind, $label, $val]): ?>
              <?php if ($kind === 'copy'): ?><button type="button" data-copy="<?= e($val) ?>"><?= e($label) ?></button>
              <?php else: ?><a href="<?= e($val) ?>"><?= e($label) ?></a><?php endif; ?>
            <?php endforeach; ?>
            <?php if ($status === 'published'): ?>
              <form method="post" action="<?= e(url('/admin/enquetes/' . $id . '/encerrar')) ?>" data-confirm="Encerrar a votação? Novos votos serão recusados (você pode reabrir depois).">
                <?= csrf_field() ?><button class="is-danger" type="submit">Encerrar votação</button>
              </form>
            <?php endif; ?>
          </div>
        </details>
        <?php endif; ?>
      <?php else: ?>
        <a class="btn <?= $status === 'published' ? '' : 'btn--outline' ?>" href="<?= e($link) ?>">
          <?= $status === 'published' ? 'Participar' : 'Ver ' . ($isCatalog ? 'jogos' : 'opções') ?> <?= icon('arrow', 16) ?>
        </a>
      <?php endif; ?>
    </div>
  </div>
</article>
