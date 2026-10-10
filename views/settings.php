<?php
// Settings (admin only): shop name + tagline shown across the app and invoices.
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
</div>
