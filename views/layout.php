<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> · <?= e($shopName) ?></title>
<link rel="stylesheet" href="assets/style.css">
<script src="assets/app.js" defer></script>
</head>
<body>
<?php if ($bare): ?>
  <main class="bare-wrap">
    <?= $content ?>
  </main>
<?php else: ?>
  <header class="topbar">
    <button class="menu-btn" id="menuBtn" aria-label="Menu">☰</button>
    <div class="brand">
      <span class="brand-mark">ZM</span>
      <span class="brand-text">
        <strong><?= e($shopName) ?></strong>
        <small><?= e($shopTag) ?></small>
      </span>
    </div>
    <div class="topbar-right">
      <span class="user-chip"><?= e(current_user()['name']) ?> · <?= e(current_user()['role']) ?></span>
      <a class="btn btn-ghost" href="index.php?page=logout">Sign out</a>
    </div>
  </header>
  <div class="shell">
    <nav class="sidebar" id="sidebar">
      <a href="index.php?page=dashboard" class="<?= $page==='dashboard'?'active':'' ?>">📊 Dashboard</a>
      <a href="index.php?page=sale" class="<?= $page==='sale'?'active':'' ?>">🧾 New Sale</a>
      <a href="index.php?page=sales" class="<?= $page==='sales'?'active':'' ?>">📜 Sales History</a>
      <a href="index.php?page=products" class="<?= $page==='products'?'active':'' ?>">📦 Products</a>
      <a href="index.php?page=stock" class="<?= $page==='stock'?'active':'' ?>">🏷️ Stock</a>
      <a href="index.php?page=reports" class="<?= $page==='reports'?'active':'' ?>">📈 Reports</a>
      <?php if (is_admin()): ?>
      <a href="index.php?page=users" class="<?= $page==='users'?'active':'' ?>">👥 Users</a>
      <a href="index.php?page=settings" class="<?= $page==='settings'?'active':'' ?>">⚙️ Settings</a>
      <?php endif; ?>
    </nav>
    <main class="content">
      <?php foreach (flashes() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
      <?php endforeach; ?>
      <?php if (is_admin() && setting('default_admin_pw') === '1'): ?>
        <div class="alert alert-warn">⚠️ You are still using the default admin password. Please change it in
          <a href="index.php?page=users">Users</a>.</div>
      <?php endif; ?>
      <?= $content ?>
    </main>
  </div>
<?php endif; ?>
</body>
</html>
