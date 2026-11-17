<div class="notfound">
  <h1>419</h1>
  <h2>Sua sessão expirou</h2>
  <p style="color:var(--ink-soft)">Por segurança, o formulário só vale por um tempo. Nada foi salvo: volte, recarregue a página e envie de novo.</p>
  <div class="center-actions" style="gap:.5rem">
    <a class="btn" href="<?= e(url(is_admin() ? '/admin' : '/')) ?>">Recarregar o site</a>
    <?php if (!is_admin()): ?><a class="btn btn--outline" href="<?= e(url('/admin/login')) ?>">Entrar</a><?php endif; ?>
  </div>
</div>
