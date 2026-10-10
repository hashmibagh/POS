<?php
// Sales history with date filter; admin can void a sale (restores stock).
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'void') {
    verify_csrf();
    require_admin();
    $id = as_int($_POST['id'] ?? null);
    if ($id) {
        try {
            $pdo->beginTransaction();
            $items = $pdo->prepare("SELECT product_id, qty FROM sale_items WHERE sale_id = ?");
            $items->execute([$id]);
            $ust = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
            foreach ($items->fetchAll() as $it) {
                $ust->execute([$it['qty'], $it['product_id']]);
            }
            $pdo->prepare("DELETE FROM sale_items WHERE sale_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM sales WHERE id = ?")->execute([$id]);
            $pdo->commit();
            flash('Sale voided and stock restored.');
        } catch (Exception $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('Void failed: ' . $ex->getMessage(), 'error');
        }
    }
    redirect('index.php?page=sales');
}

$d = trim($_GET['d'] ?? '');
if ($d !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
    $st = $pdo->prepare(
        "SELECT s.*, u.name AS cashier FROM sales s JOIN users u ON u.id = s.user_id
         WHERE date(s.created_at) = ? ORDER BY s.id DESC"
    );
    $st->execute([$d]);
} else {
    $st = $pdo->query(
        "SELECT s.*, u.name AS cashier FROM sales s JOIN users u ON u.id = s.user_id
         ORDER BY s.id DESC LIMIT 100"
    );
}
$sales = $st->fetchAll();
?>
<h1>Sales History</h1>
<form class="toolbar" method="get" action="index.php">
  <input type="hidden" name="page" value="sales">
  <input type="date" name="d" value="<?= e($d) ?>">
  <button class="btn" type="submit">Filter</button>
  <?php if ($d !== ''): ?><a class="btn btn-ghost" href="index.php?page=sales">Clear</a><?php endif; ?>
</form>
<div class="card table-wrap">
  <table class="tbl">
    <tr><th>Invoice</th><th>Date</th><th>Cashier</th><th>Total</th><th></th></tr>
    <?php foreach ($sales as $s): ?>
    <tr>
      <td><a href="index.php?page=invoice&id=<?= (int)$s['id'] ?>"><?= e($s['invoice_no']) ?></a></td>
      <td><?= e($s['created_at']) ?></td>
      <td><?= e($s['cashier']) ?></td>
      <td><?= money($s['total']) ?></td>
      <td class="actions">
        <a class="btn btn-sm" href="index.php?page=invoice&id=<?= (int)$s['id'] ?>">View</a>
        <?php if (is_admin()): ?>
        <form method="post" action="index.php?page=sales" style="display:inline" data-confirm="Void <?= e($s['invoice_no']) ?> and restore stock?">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="void">
          <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
          <button class="btn btn-sm btn-danger" type="submit">Void</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php if (!$sales): ?><p class="muted">No sales found.</p><?php endif; ?>
</div>
