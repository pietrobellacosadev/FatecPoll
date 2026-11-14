<div class="auth">
  <div class="card">
    <div class="brand__mark brand__mark--logo"><img src="<?= e(url('img/logo.png')) ?>" alt="" width="54" height="54"></div>
    <h1>Administração</h1>
    <p class="sub">Entre para criar enquetes e acompanhar os resultados.</p>
    <form method="post" action="<?= e(url('/admin/login')) ?>" novalidate>
      <?= csrf_field() ?>
      <div class="field">
        <label for="email">E-mail</label>
        <input class="input" id="email" name="email" type="email" required autocomplete="username" autofocus>
      </div>
      <div class="field">
        <label for="password">Senha</label>
        <input class="input" id="password" name="password" type="password" required autocomplete="current-password">
      </div>
      <button class="btn btn--block btn--lg" type="submit">Entrar</button>
    </form>
    <p class="sub" style="margin-top:1.2rem">É professor(a)? <a href="<?= e(url('/cadastro')) ?>">Crie seu cadastro</a></p>
  </div>
</div>
