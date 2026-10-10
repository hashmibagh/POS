<?php
// Stock: inventory table with low-stock highlighting + quick adjust with reason.
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'adjust') {
    verify_csrf();
    $pid = as_int($_POST['product_id'] ?? null);
    $delta = as_int($_POST['delta'] ?? null);
    $reason = trim($_POST['reason'] ?? '');
    if ($pid && $delta !== null && $delta !== 0) {
        try {
            $pdo->beginTransaction();
            $st = $pdo->prepare("SELECT stock, name FROM products WHERE id = ?");
            $st->execute([$pid]);
            $p = $st->fetch();
            if (!$p) throw new RuntimeException('Product not found.');
            if ((int)$p['stock'] + $delta < 0) {
                throw new RuntimeException('Adjustment would make stock negative.');
            }
            $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?")
                ->execute([$delta, $pid]);
            $pdo->prepare(
                "INSERT INTO stock_log (product_id, delta, reason, user_id, created_at)
                 VALUES (?,?,?,?,?)"
            )->execute([$pid, $delta, $reason, current_user()['id'], date('Y-m-d H:i:s')]);
            $pdo->commit();
            flash('Stock updated for ' . $p['name'] . '.');
        } catch (Exception $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('Adjustment failed: ' . $ex->getMessage(), 'error');
        }
    } else {
        flash('Enter a non-zero quantity.', 'error');
    }
    redirect('index.php?page=stock');
}

$products = $pdo->query("SELECT * FROM products ORDER BY name")->fetchAll();
$log = $pdo->query(
    "SELECT sl.*, p.name AS product, u.name AS username FROM stock_log sl
     JOIN products p ON p.id = sl.product_id
     JOIN users u ON u.id = sl.user_id
     ORDER BY sl.id DESC LIMIT 15"
)->fetchAll();
?>
<h1>Stock</h1>
<div class="card table-wrap">
  <table class="tbl">
    <tr><th>SKU</th><th>Product</th><th>Stock</th><th>Alert at</th><th>Adjust</th></tr>
    <?php foreach ($products as $p): ?>
    <tr class="<?= $p['stock'] <= $p['low_stock_alert'] ? 'low' : '' ?>">
      <td><?= e($p['sku']) ?></td>
      <td><?= e($p['name']) ?></td>
      <td><strong><?= e((string)$p['stock']) ?></strong></td>
      <td><?= e((string)$p['low_stock_alert']) ?></td>
      <td>
        <form method="post" action="index.php?page=stock" class="inline-form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="adjust">
          <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
          <input type="number" name="delta" placeholder="+/-" class="qty" required>
          <input type="text" name="reason" placeholder="Reason" class="reason">
          <button class="btn btn-sm" type="submit">Apply</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<div class="card">
  <h3>Recent adjustments</h3>
  <?php if (!$log): ?><p class="muted">No adjustments yet.</p><?php else: ?>
  <table class="tbl">
    <tr><th>When</th><th>Product</th><th>Change</th><th>Reason</th><th>By</th></tr>
    <?php foreach ($log as $r): ?>
    <tr>
      <td><?= e($r['created_at']) ?></td>
      <td><?= e($r['product']) ?></td>
      <td><?= $r['delta'] > 0 ? '+' : '' ?><?= e((string)$r['delta']) ?></td>
      <td><?= e($r['reason']) ?></td>
      <td><?= e($r['username']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>
