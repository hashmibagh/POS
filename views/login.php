<?php
// Login page (bare layout, no sidebar).
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } elseif (do_login($username, $password)) {
        redirect('index.php?page=dashboard');
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<div class="login-card">
  <div class="login-brand"><span class="brand-mark lg">ZM</span></div>
  <h1><?= e($shopName) ?></h1>
  <p class="tagline"><?= e($shopTag) ?></p>
  <p class="welcome">WELCOME BACK</p>
  <h2>Sign in to your workspace</h2>
  <p class="muted">Sales, stock and shop performance in one place.</p>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="index.php?page=login">
    <?= csrf_field() ?>
    <label>Username
      <input type="text" name="username" autocomplete="username" required autofocus>
    </label>
    <label>Password
      <input type="password" name="password" autocomplete="current-password" required>
    </label>
    <button class="btn btn-primary btn-block" type="submit">Sign in</button>
  </form>
  <p class="muted small" style="margin-top:14px">Default login: <code>admin</code> / <code>admin123</code> — change it after first sign in.</p>
</div>
