<section class="hero">
  <div class="hero__orbs" aria-hidden="true"><span class="orb orb--1"></span><span class="orb orb--2"></span><span class="orb orb--3"></span></div>
  <span class="float float--1" aria-hidden="true"><?= icon('thumbs-up', 24) ?></span>
  <span class="float float--2" aria-hidden="true"><?= icon('trophy', 24) ?></span>
  <span class="float float--3" aria-hidden="true"><?= icon('gamepad', 22) ?></span>
  <span class="float float--4" aria-hidden="true"><?= icon('bars', 22) ?></span>

  <div class="container">
    <span class="hero__badge anim" style="--i:0"><i></i> Votação aberta para a comunidade Fatec</span>
    <h1 class="anim" style="--i:1">Sua opinião <em>faz o resultado</em></h1>
    <p class="lead anim" style="--i:2">Avalie jogos criados pelos estudantes ou responda às perguntas da comunidade. Sem criar conta, direto do celular.</p>
    <div class="hero__actions anim" style="--i:3">
      <a class="btn btn--light btn--lg" href="<?= e(url('/enquetes')) ?>"><?= icon('thumbs-up', 20) ?> Participar agora</a>
      <a class="btn btn--glass btn--lg" href="#como-funciona">Como funciona</a>
    </div>
  </div>
  <a class="hero__cue" href="#abertas" aria-label="Rolar para baixo"><?= icon('chevron', 28) ?></a>
</section>

<div class="container">
  <div class="stats" data-reveal>
    <div><strong data-count="<?= (int) $stats['votes'] ?>"><?= number_format($stats['votes'], 0, ',', '.') ?></strong><span>votos registrados</span></div>
    <div><strong data-count="<?= (int) $stats['items'] ?>"><?= (int) $stats['items'] ?></strong><span>itens em votação</span></div>
    <div><strong data-count="<?= (int) $stats['open'] ?>"><?= (int) $stats['open'] ?></strong><span>enquetes abertas</span></div>
    <div><strong data-count="<?= (int) $stats['today'] ?>"><?= number_format($stats['today'], 0, ',', '.') ?></strong><span>votos hoje</span></div>
  </div>

  <section class="section" id="abertas">
    <div class="section-head" data-reveal>
      <span class="eyebrow">Em votação agora</span>
      <h2>Escolha onde participar</h2>
      <p>Jogos para avaliar e perguntas para responder. Leva menos de um minuto.</p>
    </div>
    <?php if (!$open): ?>
      <div class="card empty" data-reveal><?= icon('list', 34) ?>Nenhuma enquete aberta no momento. Volte em breve!</div>
    <?php else: ?>
      <div class="poll-grid even-grid" data-even-grid data-stagger="90">
        <?php foreach ($open as $i => $p) { $gridClass = even_grid_class($i, count($open)); include BASE_PATH . '/views/partials/poll_card.php'; } ?>
      </div>
      <div class="center-actions" data-reveal>
        <a class="btn btn--outline" href="<?= e(url('/enquetes')) ?>">Ver todas as enquetes <?= icon('arrow', 16) ?></a>
      </div>
    <?php endif; ?>
  </section>

  <section class="section" id="como-funciona">
    <div class="section-head" data-reveal>
      <span class="eyebrow">Passo a passo</span>
      <h2>Como funciona</h2>
    </div>
    <div class="steps" data-stagger="120">
      <div class="card step card-lift">
        <div class="step__icon"><?= icon('pointer', 28) ?><span class="step__n">1</span></div>
        <h3>Escolha a enquete</h3>
        <p>Abra uma votação aberta: pode ser um catálogo de jogos ou uma pergunta com opções.</p>
      </div>
      <div class="card step card-lift">
        <div class="step__icon"><?= icon('layers', 28) ?><span class="step__n">2</span></div>
        <h3>Dê a sua resposta</h3>
        <p>Nos jogos, notas de 1 a 5 em <?= e(implode(', ', array_slice(rating_labels(), 0, 2))) ?> e <?= e(rating_labels()['q_replay']) ?>. Nas perguntas, escolha uma opção.</p>
      </div>
      <div class="card step card-lift">
        <div class="step__icon"><?= icon('trophy', 28) ?><span class="step__n">3</span></div>
        <h3>Acompanhe o resultado</h3>
        <p>Quem organiza vê o jogo mais bem avaliado de cada pergunta ou a resposta mais votada.</p>
      </div>
    </div>
  </section>

  <section class="cta" data-reveal>
    <h2>Pronto para opinar?</h2>
    <p>Cada voto ajuda a destacar os melhores projetos da Fatec.</p>
    <a class="btn btn--light btn--lg" href="<?= e(url('/enquetes')) ?>">Ver enquetes <?= icon('arrow', 18) ?></a>
  </section>
</div>
