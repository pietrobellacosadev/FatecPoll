<div class="page-head">
  <span class="eyebrow">Participação concluída</span>
  <h1>Voto registrado!</h1>
  <p>Obrigado por participar de <strong><?= e($poll['title']) ?></strong>. Seu voto foi salvo.</p>
</div>

<div class="card confirm" data-reveal>
  <span class="confirm__ico"><?= icon('check', 34) ?></span>
  <p>Seu voto é único e definitivo: esta enquete passa a aparecer como já respondida neste dispositivo.</p>
  <div class="center-actions" style="margin-top:1rem;gap:.6rem;flex-wrap:wrap">
    <?php if ((int) $poll['results_public']): ?>
      <a class="btn" href="<?= e(url('/resultados/' . $poll['slug'])) ?>"><?= icon('bars', 16) ?> Ver resultados</a>
    <?php endif; ?>
    <a class="btn btn--outline" href="<?= e(url('/enquetes')) ?>">Outras enquetes</a>
  </div>
</div>
<script>window.addEventListener('DOMContentLoaded', function () { if (window.FatecPoll) window.FatecPoll.markDone(<?= json_encode($poll['slug'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>); });</script>
