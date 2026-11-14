<?php $domain = (string) config('teacher_domain'); ?>
<div class="auth">
  <div class="card">
    <div class="brand__mark brand__mark--logo"><img src="<?= e(url('img/logo.png')) ?>" alt="" width="54" height="54"></div>
    <h1>Cadastro de professores</h1>
    <p class="sub">Use seu e-mail institucional <strong>@<?= e($domain) ?></strong>. Enviaremos um código para confirmar.</p>

    <?php if ($errors): ?>
      <div class="alert alert--error" role="alert" style="text-align:left">
        <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/cadastro')) ?>">
      <?= csrf_field() ?>
      <div class="field">
        <label for="name">Nome completo</label>
        <input class="input" id="name" name="name" type="text" required minlength="3" maxlength="120" autocomplete="name" value="<?= e($old['name']) ?>" autofocus>
      </div>
      <div class="field">
        <label for="email">E-mail institucional</label>
        <input class="input" id="email" name="email" type="email" required maxlength="190" autocomplete="email"
               placeholder="nome@<?= e($domain) ?>" value="<?= e($old['email']) ?>"
               pattern="[A-Za-z0-9][A-Za-z0-9._%+\-]*@[Cc][Pp][Ss]\.[Ss][Pp]\.[Gg][Oo][Vv]\.[Bb][Rr]"
               title="Use um e-mail terminado em @<?= e($domain) ?>">
        <small>Somente e-mails @<?= e($domain) ?>.</small>
      </div>
      <div class="field">
        <label for="password">Senha</label>
        <input class="input" id="password" name="password" type="password" required minlength="8" maxlength="72" autocomplete="new-password">
        <small>Mínimo de 8 caracteres.</small>
      </div>
      <div class="field">
        <label for="password_confirm">Confirmar senha</label>
        <input class="input" id="password_confirm" name="password_confirm" type="password" required minlength="8" maxlength="72" autocomplete="new-password">
      </div>
      <button class="btn btn--block btn--lg" type="submit">Enviar código de verificação</button>
    </form>
    <p class="sub" style="margin-top:1.2rem">Já tem cadastro? <a href="<?= e(url('/admin/login')) ?>">Entrar</a></p>
  </div>
</div>
