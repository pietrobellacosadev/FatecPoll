<?php
$stateLabel = ['active' => 'Ativo', 'inactive' => 'Inativo', 'pending' => 'Aguardando e-mail'];
$statePill  = ['active' => 'pill--published', 'inactive' => 'pill--closed', 'pending' => 'pill--draft'];
$pollLabel  = ['published' => 'Aberta', 'draft' => 'Rascunho', 'closed' => 'Encerrada'];
$counts = ['active' => 0, 'inactive' => 0, 'pending' => 0];
foreach ($teachers as $t) { $counts[$t['state']]++; }
?>
<div class="page-head">
  <span class="eyebrow">Painel</span>
  <h1>Professores</h1>
  <p>Controle quem acessa o painel e acompanhe as enquetes de cada um. Inativar bloqueia o acesso, mas preserva enquetes e votos.</p>
</div>

<?php if (!$teachers): ?>
  <div class="card empty" data-reveal><?= icon('list', 34) ?>Nenhum professor cadastrado ainda. Eles se cadastram em <a href="<?= e(url('/cadastro')) ?>">/cadastro</a> com o e-mail institucional.</div>
<?php else: ?>
  <div class="tools">
    <div class="chips-bar" role="group" aria-label="Filtrar por situação">
      <button type="button" class="chip-btn" data-t-filter="all" aria-pressed="true"><span>Todos</span><b class="count"><?= count($teachers) ?></b></button>
      <button type="button" class="chip-btn" data-t-filter="active" aria-pressed="false"><span>Ativos</span><b class="count"><?= $counts['active'] ?></b></button>
      <button type="button" class="chip-btn" data-t-filter="inactive" aria-pressed="false"><span>Inativos</span><b class="count"><?= $counts['inactive'] ?></b></button>
      <?php if ($counts['pending']): ?><button type="button" class="chip-btn" data-t-filter="pending" aria-pressed="false"><span>Aguardando</span><b class="count"><?= $counts['pending'] ?></b></button><?php endif; ?>
    </div>
    <div class="tools__search">
      <input class="input" type="search" placeholder="Buscar por nome ou e-mail" aria-label="Buscar professor" data-teacher-search autocomplete="off">
    </div>
  </div>

  <div class="card tlist" data-teacher-list data-reveal>
    <?php foreach ($teachers as $t): $st = $t['state']; $n = count($t['polls']); ?>
      <article class="trow" data-teacher-row data-state="<?= e($st) ?>" data-search="<?= e($t['name'] . ' ' . $t['email']) ?>">
        <div class="trow__main">
          <div class="trow__id">
            <strong><?= e($t['name']) ?></strong>
            <small><?= e($t['email']) ?></small>
          </div>
          <div class="trow__nums">
            <span class="pill <?= e($statePill[$st]) ?>"><?= e($stateLabel[$st]) ?></span>
            <span><b><?= $n ?></b> <?= $n === 1 ? 'enquete' : 'enquetes' ?></span>
            <span><b><?= (int) $t['open_count'] ?></b> <?= (int) $t['open_count'] === 1 ? 'aberta' : 'abertas' ?></span>
          </div>
          <div class="trow__actions">
            <?php if ($st === 'pending'): ?>
              <small class="trow__none" style="margin:0">Só ativa depois de confirmar o e-mail.</small>
            <?php else: ?>
              <form method="post" action="<?= e(url('/admin/professores/' . (int) $t['id'] . '/status')) ?>"
                    data-confirm="<?= e($st === 'active' ? 'Inativar ' . $t['name'] . '? O acesso ao painel é bloqueado na hora, mas as enquetes e os votos continuam preservados.' : 'Reativar o acesso de ' . $t['name'] . '?') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $st === 'active' ? 'deactivate' : 'activate' ?>">
                <button type="submit" class="btn btn--sm <?= $st === 'active' ? 'btn--ghost' : '' ?>"><?= $st === 'active' ? 'Inativar' : 'Reativar' ?></button>
              </form>
            <?php endif; ?>
          </div>
        </div>
        <?php if ($n): ?>
          <details class="trow__polls">
            <summary>Ver enquetes</summary>
            <ul>
              <?php foreach ($t['polls'] as $poll): ?>
                <li>
                  <div>
                    <strong><?= e($poll['title']) ?></strong>
                    <small><?= e($pollLabel[$poll['status']] ?? $poll['status']) ?> · <?= e(plural((int) $poll['participants'], 'participação', 'participações')) ?></small>
                  </div>
                  <span>
                    <a class="btn btn--sm" href="<?= e(url('/admin/enquetes/' . (int) $poll['id'] . '/resultados')) ?>">Resultados</a>
                    <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/enquetes/' . (int) $poll['id'] . '/editar')) ?>">Editar</a>
                  </span>
                </li>
              <?php endforeach; ?>
            </ul>
          </details>
        <?php else: ?>
          <p class="trow__none">Ainda não criou enquetes.</p>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
    <p class="empty" data-teacher-empty hidden>Nenhum professor encontrado com esse filtro.</p>
  </div>
<?php endif; ?>
