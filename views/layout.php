<?php $hero = $hero ?? false; $f = flash(); ?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0b1220">
  <link rel="icon" href="<?= e(url('favicon.ico')) ?>" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="<?= e(url('img/favicon-32.png')) ?>">
  <link rel="icon" type="image/png" sizes="16x16" href="<?= e(url('img/favicon-16.png')) ?>">
  <link rel="apple-touch-icon" href="<?= e(url('img/apple-touch-icon.png')) ?>">
  <title><?= e($title ?? 'FatecPoll') ?> · FatecPoll</title>
  <script>
    document.documentElement.classList.add('js');
    (function () { // tema salvo (ou do sistema) aplicado antes da pintura, sem "piscar"
      try {
        var t = localStorage.getItem('fatecpoll:theme');
        if (!t && window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches) t = 'dark';
        if (t) document.documentElement.setAttribute('data-theme', t);
      } catch (e) {}
    })();
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700&amp;family=Barlow:wght@400;500;600&amp;display=swap">
  <link rel="stylesheet" href="<?= e(url('css/variables.css')) ?>">
  <link rel="stylesheet" href="<?= e(url('css/base.css')) ?>">
  <link rel="stylesheet" href="<?= e(url('css/components.css')) ?>">
  <link rel="stylesheet" href="<?= e(url('css/dashboard.css')) ?>">
  <link rel="stylesheet" href="<?= e(url('css/admin-panel.css')) ?>">
  <link rel="stylesheet" href="<?= e(url('css/animations.css')) ?>">
</head>
<body>
<header class="site-header">
  <div class="container site-header__inner">
    <a class="brand" href="<?= e(url('/')) ?>">
      <span class="brand__mark brand__mark--logo"><img src="<?= e(url('img/logo.png')) ?>" alt="" width="36" height="36"></span>
      <span>Fatec<b>Poll</b></span>
    </a>
    <button class="btn btn--ghost nav-toggle" type="button" aria-controls="header-menu" aria-expanded="false">Menu</button>

    <div class="site-header__menu" id="header-menu">
      <nav class="nav" aria-label="Principal">
        <a href="<?= e(url('/')) ?>"<?= nav_active('/', true) ?>>Início</a>
        <a href="<?= e(url('/enquetes')) ?>"<?= nav_active('/enquetes') ?>>Enquetes</a>
        <?php if (is_admin()): ?>
          <a href="<?= e(url('/admin')) ?>"<?= nav_active('/admin') ?>>Painel</a>
        <?php endif; ?>
      </nav>
      <div class="header-actions">
        <button class="theme-toggle" type="button" data-theme-toggle aria-label="Alternar tema claro/escuro" title="Alternar tema">
          <span class="theme-toggle__moon"><?= icon('moon', 18) ?></span><span class="theme-toggle__sun"><?= icon('sun', 18) ?></span>
        </button>
        <?php if (is_admin()): ?>
          <form method="post" action="<?= e(url('/admin/logout')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn--ghost btn--sm" type="submit">Sair</button>
          </form>
        <?php else: ?>
          <a class="btn btn--sm" href="<?= e(url('/admin/login')) ?>">Entrar</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</header>

<?php if ($f): ?>
  <div class="toast<?= $f['type'] === 'error' ? ' toast--error' : '' ?>" role="status"><?= icon($f['type'] === 'error' ? 'x' : 'check', 18) ?> <?= e($f['message']) ?></div>
<?php endif; ?>

<?php $panel = is_admin() && str_starts_with(current_path(), '/admin'); ?>
<?php if ($hero): ?>
<main><?= $content ?></main>
<?php else: ?>
<main class="container page">
<?php if ($panel) { include BASE_PATH . '/views/partials/admin_bar.php'; } ?>
<?= $content ?></main>
<?php endif; ?>

<footer class="site-footer">
  <div class="container">
    <a class="brand" href="<?= e(url('/')) ?>"><span class="brand__mark brand__mark--logo"><img src="<?= e(url('img/logo.png')) ?>" alt="" width="36" height="36"></span><span>Fatec<b>Poll</b></span></a>
    <div class="site-footer__bottom">
      <small class="copyright">&copy; FP Softwares. Todos os direitos reservados. <span aria-hidden="true">&middot;</span> <a href="<?= e(url('/autores')) ?>">Autores</a></small>
      <small class="copyright">Votos anônimos: o voto não é ligado a você. Para limitar votos repetidos, guardamos só um identificador anônimo do aparelho.</small>
    </div>
  </div>
</footer>

<?php if (!empty($withCharts)): ?>
<!-- Apache ECharts (open source, Apache-2.0), versão fixa via CDN -->
<script src="https://cdn.jsdelivr.net/npm/echarts@5.5.1/dist/echarts.min.js" defer></script>
<?php endif; ?>
<script src="<?= e(url('js/app.js')) ?>"></script>
<?php if (!empty($withCharts)): ?><script src="<?= e(url('js/charts.js')) ?>" defer></script><?php endif; ?>
<?php if (in_admin_area()): ?><script src="<?= e(url('js/admin-actions.js')) ?>"></script><?php endif; ?>
<?php if (!empty($extraJs)) { foreach ($extraJs as $js) { echo '<script src="' . e(url('js/' . $js)) . '"></script>' . "\n"; } } ?>
</body>
</html>
