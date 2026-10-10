<?php
// Products: search + add / edit / delete.
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = as_int($_POST['id'] ?? null);
        if ($id) {
            // Refuse to delete a product that appears on any sale.
            $st = $pdo->prepare("SELECT COUNT(*) FROM sale_items WHERE product_id = ?");
            $st->execute([$id]);
            if ((int)$st->fetchColumn() > 0) {
                flash('Cannot delete: product has sales history.', 'error');
            } else {
                $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
                flash('Product deleted.');
            }
        }
        redirect('index.php?page=products');
    }

    // Shared validation for add/edit.
    $sku   = trim($_POST['sku'] ?? '');
    $name  = trim($_POST['name'] ?? '');
    $cat   = trim($_POST['category'] ?? '') ?: 'General';
    $cost  = as_float($_POST['cost_price'] ?? null);
    $sale  = as_float($_POST['sale_price'] ?? null);
    $stock = as_int($_POST['stock'] ?? null);
    $alert = as_int($_POST['low_stock_alert'] ?? null);
    $ok = $sku !== '' && $name !== '' && $cost !== null && $cost >= 0
       && $sale !== null && $sale >= 0 && $stock !== null && $stock >= 0
       && $alert !== null && $alert >= 0;

    if (!$ok) {
        flash('Please fill all fields with valid values.', 'error');
        redirect('index.php?page=products');
    }

    if ($action === 'add') {
        try {
            $pdo->prepare(
                "INSERT INTO products (sku,name,category,cost_price,sale_price,stock,low_stock_alert,created_at)
                 VALUES (?,?,?,?,?,?,?,?)"
            )->execute([$sku, $name, $cat, $cost, $sale, $stock, $alert, date('Y-m-d H:i:s')]);
            flash('Product added.');
        } catch (PDOException $ex) {
            flash('SKU already exists.', 'error');
        }
    } elseif ($action === 'edit') {
        $id = as_int($_POST['id'] ?? null);
        if ($id) {
            try {
                $pdo->prepare(
                    "UPDATE products SET sku=?, name=?, category=?, cost_price=?, sale_price=?,
                     stock=?, low_stock_alert=? WHERE id=?"
                )->execute([$sku, $name, $cat, $cost, $sale, $stock, $alert, $id]);
                flash('Product updated.');
            } catch (PDOException $ex) {
                flash('SKU already exists.', 'error');
            }
        }
    }
    redirect('index.php?page=products');
}

$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $like = like_op();
    $st = $pdo->prepare("SELECT * FROM products WHERE name $like ? OR sku $like ? ORDER BY name");
    $st->execute(["%$q%", "%$q%"]);
} else {
    $st = $pdo->query("SELECT * FROM products ORDER BY name");
}
$products = $st->fetchAll();
$editId = as_int($_GET['edit'] ?? null);
$editRow = null;
if ($editId) {
    $st = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $st->execute([$editId]);
    $editRow = $st->fetch();
}
?>
<h1>Products</h1>
<form class="toolbar" method="get" action="index.php">
  <input type="hidden" name="page" value="products">
  <input type="search" name="q" placeholder="Search name or SKU…" value="<?= e($q) ?>">
  <button class="btn" type="submit">Search</button>
  <?php if ($q !== ''): ?><a class="btn btn-ghost" href="index.php?page=products">Clear</a><?php endif; ?>
</form>

<div class="card">
  <h3><?= $editRow ? 'Edit product' : 'Add product' ?></h3>
  <form method="post" action="index.php?page=products" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>">
    <?php if ($editRow): ?><input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>"><?php endif; ?>
    <label>SKU<input name="sku" required value="<?= e($editRow['sku'] ?? '') ?>"></label>
    <label>Name<input name="name" required value="<?= e($editRow['name'] ?? '') ?>"></label>
    <label>Category<input name="category" value="<?= e($editRow['category'] ?? 'Accessories') ?>"></label>
    <label>Cost price<input name="cost_price" type="number" step="0.01" min="0" required value="<?= e($editRow['cost_price'] ?? '') ?>"></label>
    <label>Sale price<input name="sale_price" type="number" step="0.01" min="0" required value="<?= e($editRow['sale_price'] ?? '') ?>"></label>
    <label>Stock<input name="stock" type="number" step="1" min="0" required value="<?= e($editRow['stock'] ?? '0') ?>"></label>
    <label>Low-stock alert at<input name="low_stock_alert" type="number" step="1" min="0" required value="<?= e($editRow['low_stock_alert'] ?? '5') ?>"></label>
    <div><button class="btn btn-primary" type="submit"><?= $editRow ? 'Save changes' : 'Add product' ?></button>
    <?php if ($editRow): ?> <a class="btn btn-ghost" href="index.php?page=products">Cancel</a><?php endif; ?></div>
  </form>
</div>

<div class="card table-wrap">
  <table class="tbl">
    <tr><th>SKU</th><th>Name</th><th>Category</th><th>Cost</th><th>Price</th><th>Stock</th><th></th></tr>
    <?php foreach ($products as $p): ?>
    <tr class="<?= $p['stock'] <= $p['low_stock_alert'] ? 'low' : '' ?>">
      <td><?= e($p['sku']) ?></td>
      <td><?= e($p['name']) ?></td>
      <td><?= e($p['category']) ?></td>
      <td><?= money($p['cost_price']) ?></td>
      <td><?= money($p['sale_price']) ?></td>
      <td><?= e((string)$p['stock']) ?></td>
      <td class="actions">
        <a class="btn btn-sm" href="index.php?page=products&edit=<?= (int)$p['id'] ?>">Edit</a>
        <form method="post" action="index.php?page=products" style="display:inline" data-confirm="Delete <?= e($p['name']) ?>?">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <button class="btn btn-sm btn-danger" type="submit">Delete</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php if (!$products): ?><p class="muted">No products found.</p><?php endif; ?>
</div>
