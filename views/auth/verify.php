<?php use App\Services\TeacherRegistration as Reg; ?>
<div class="auth">
  <div class="card">
    <div class="brand__mark brand__mark--logo"><img src="<?= e(url('img/logo.png')) ?>" alt="" width="54" height="54"></div>
    <h1>Confirme seu e-mail</h1>
    <p class="sub">Digite o código de 6 dígitos enviado para <strong><?= e(Reg::maskEmail($email)) ?></strong>. Ele vale por <?= (int) config('verify.ttl_minutes') ?> minutos.</p>

    <?php if ($errors): ?>
      <div class="alert alert--error" role="alert" style="text-align:left">
        <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/cadastro/verificar')) ?>">
      <?= csrf_field() ?>
      <div class="field">
        <label for="code">Código de verificação</label>
        <input class="input" id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code"
               pattern="[0-9 ]{6,7}" maxlength="7" required autofocus placeholder="000000"
               style="text-align:center;font-size:1.8rem;letter-spacing:.45rem;font-weight:700">
      </div>
      <button class="btn btn--block btn--lg" type="submit">Confirmar</button>
    </form>

    <form method="post" action="<?= e(url('/cadastro/reenviar')) ?>" style="margin-top:.8rem">
      <?= csrf_field() ?>
      <button class="btn btn--ghost btn--block" type="submit">Não recebi — reenviar código</button>
    </form>
    <p class="sub" style="margin-top:1rem"><a href="<?= e(url('/cadastro')) ?>">Corrigir meus dados</a></p>
  </div>
</div>
