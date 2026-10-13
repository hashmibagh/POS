<?php
// Settings (admin only): shop name + tagline shown across the app and invoices.
if (($_GET['backup'] ?? '') === '1') {
    // Admin-only is enforced by the router; double-check here too.
    require_admin();
    if (db_driver() !== 'sqlite' || !is_file(DATA_DIR . '/pos.db')) {
        flash('Database backup is only available with the built-in SQLite database.', 'error');
        redirect('index.php?page=settings');
    }
    $file = DATA_DIR . '/pos.db';
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="zangarry-pos-backup-' . date('Ymd-His') . '.db"');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['shop_name'] ?? '');
    $tag  = trim($_POST['tagline'] ?? '');
    if ($name === '') {
        flash('Shop name cannot be empty.', 'error');
    } else {
        set_setting('shop_name', $name);
        set_setting('tagline', $tag);
        flash('Settings saved.');
    }
    redirect('index.php?page=settings');
}
?>
<h1>Settings</h1>
<div class="card">
  <h3>Shop details</h3>
  <form method="post" action="index.php?page=settings" class="form-grid">
    <?= csrf_field() ?>
    <label>Shop name<input name="shop_name" required value="<?= e(setting('shop_name')) ?>"></label>
    <label>Tagline<input name="tagline" value="<?= e(setting('tagline')) ?>"></label>
    <div><button class="btn btn-primary" type="submit">Save settings</button></div>
  </form>
</div>
<div class="card">
  <h3>About</h3>
  <p class="muted">Zangarry POS · plain PHP + SQLite build. The database lives in <code>data/pos.db</code>
  and is created automatically on first run. Back it up regularly.</p>
  <?php if (db_driver() === 'sqlite'): ?>
  <p><a class="btn" href="index.php?page=settings&backup=1">Download database backup</a></p>
  <?php endif; ?>
</div>
