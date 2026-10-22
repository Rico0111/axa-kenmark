<section class="admin-login-card" aria-labelledby="login-title">
  <p class="admin-eyebrow">SECURE SIGN-IN</p>
  <h1 id="login-title">Administrator access</h1>
  <p class="admin-login-intro">Sign in to manage the public website, products, advisor details, and media. There are no default credentials.</p>

  <form class="admin-form" method="post" action="<?= e(admin_url('login')) ?>">
    <?= csrf_field() ?>

    <div class="admin-field">
      <label for="email">Email address</label>
      <input id="email" name="email" type="email" autocomplete="username" required maxlength="190" inputmode="email" autofocus>
    </div>

    <div class="admin-field">
      <label for="password">Password</label>
      <input id="password" name="password" type="password" autocomplete="current-password" required>
    </div>

    <button class="admin-button admin-button-primary admin-button-full" type="submit">Sign in</button>
  </form>

  <p class="admin-login-help">If you have lost access, an administrator with server access can provision a new account using the command-line tool.</p>
  <p class="admin-login-back"><a href="<?= e(url('/')) ?>">← Return to the public website</a></p>
</section>
