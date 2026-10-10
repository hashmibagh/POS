<?php
// Users (admin only): list, add cashier/admin, reset password, activate/deactivate.
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = ($_POST['role'] ?? 'cashier') === 'admin' ? 'admin' : 'cashier';
        if ($name === '' || $username === '' || strlen($password) < 4) {
            flash('Name, username and a password of at least 4 characters are required.', 'error');
        } else {
            try {
                $pdo->prepare(
                    "INSERT INTO users (name, username, password_hash, role, active, created_at)
                     VALUES (?,?,?,?,1,?)"
                )->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT), $role, date('Y-m-d H:i:s')]);
                flash('User created.');
            } catch (PDOException $ex) {
                flash('Username already exists.', 'error');
            }
        }
    } elseif ($action === 'reset') {
        $id = as_int($_POST['id'] ?? null);
        if ($id && $id !== current_user()['id']) {
            // Random temporary password, shown once to the admin.
            $tmp = substr(bin2hex(random_bytes(4)), 0, 8);
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                ->execute([password_hash($tmp, PASSWORD_DEFAULT), $id]);
            $u = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $u->execute([$id]);
            if ($u->fetchColumn() === 'admin') {
                set_setting('default_admin_pw', '0');
            }
            flash("Password reset. Temporary password: $tmp — share it with the user.", 'success');
        } elseif ($id) {
            flash('Use Settings below to change your own password.', 'error');
        }
    } elseif ($action === 'toggle') {
        $id = as_int($_POST['id'] ?? null);
        if ($id && $id !== current_user()['id']) {
            $pdo->prepare("UPDATE users SET active = 1 - active WHERE id = ?")->execute([$id]);
            flash('User status updated.');
        } else {
            flash('You cannot deactivate your own account.', 'error');
        }
    } elseif ($action === 'changeme') {
        // Admin changes their own password; clears the default-password notice.
        $pw = $_POST['new_password'] ?? '';
        if (strlen($pw) < 4) {
            flash('New password must be at least 4 characters.', 'error');
        } else {
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                ->execute([password_hash($pw, PASSWORD_DEFAULT), current_user()['id']]);
            set_setting('default_admin_pw', '0');
            flash('Your password has been changed.');
        }
    }
    redirect('index.php?page=users');
}

$users = $pdo->query("SELECT id, name, username, role, active, created_at FROM users ORDER BY id")->fetchAll();
?>
<h1>Users</h1>

<div class="card">
  <h3>Change my password</h3>
  <form method="post" action="index.php?page=users" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="changeme">
    <label>New password<input type="password" name="new_password" minlength="4" required></label>
    <div><button class="btn btn-primary" type="submit">Change password</button></div>
  </form>
</div>

<div class="card">
  <h3>Add user</h3>
  <form method="post" action="index.php?page=users" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <label>Full name<input name="name" required></label>
    <label>Username<input name="username" required></label>
    <label>Password<input type="password" name="password" minlength="4" required></label>
    <label>Role
      <select name="role"><option value="cashier">Cashier</option><option value="admin">Admin</option></select>
    </label>
    <div><button class="btn btn-primary" type="submit">Add user</button></div>
  </form>
</div>

<div class="card table-wrap">
  <table class="tbl">
    <tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th></th></tr>
    <?php foreach ($users as $u): ?>
    <tr>
      <td><?= e($u['name']) ?></td>
      <td><?= e($u['username']) ?></td>
      <td><?= e($u['role']) ?></td>
      <td><?= $u['active'] ? '✅ Active' : '⛔ Inactive' ?></td>
      <td class="actions">
        <?php if ((int)$u['id'] !== current_user()['id']): ?>
        <form method="post" action="index.php?page=users" style="display:inline" data-confirm="Reset password for <?= e($u['username']) ?>?">
          <?= csrf_field() ?><input type="hidden" name="action" value="reset">
          <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
          <button class="btn btn-sm" type="submit">Reset password</button>
        </form>
        <form method="post" action="index.php?page=users" style="display:inline" data-confirm="<?= $u['active'] ? 'Deactivate' : 'Activate' ?> <?= e($u['username']) ?>?">
          <?= csrf_field() ?><input type="hidden" name="action" value="toggle">
          <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
          <button class="btn btn-sm btn-ghost" type="submit"><?= $u['active'] ? 'Deactivate' : 'Activate' ?></button>
        </form>
        <?php else: ?><span class="muted">you</span><?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
