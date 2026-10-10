<?php
// Printable invoice for one sale.
$pdo = db();
$id = as_int($_GET['id'] ?? null);
$st = $pdo->prepare(
    "SELECT s.*, u.name AS cashier FROM sales s JOIN users u ON u.id = s.user_id WHERE s.id = ?"
);
$st->execute([$id]);
$sale = $st->fetch();
if (!$sale) {
    echo '<div class="alert alert-error">Invoice not found.</div>';
    return;
}
$ist = $pdo->prepare(
    "SELECT si.*, p.name, p.sku FROM sale_items si JOIN products p ON p.id = si.product_id
     WHERE si.sale_id = ?"
);
$ist->execute([$id]);
$items = $ist->fetchAll();
?>
<div class="invoice">
  <div class="inv-head">
    <h2><?= e(setting('shop_name')) ?></h2>
    <p class="muted"><?= e(setting('tagline')) ?></p>
  </div>
  <div class="inv-meta">
    <div><strong>Invoice:</strong> <?= e($sale['invoice_no']) ?></div>
    <div><strong>Date:</strong> <?= e($sale['created_at']) ?></div>
    <div><strong>Cashier:</strong> <?= e($sale['cashier']) ?></div>
  </div>
  <table class="tbl">
    <tr><th>#</th><th>Item</th><th>Qty</th><th>Price</th><th>Total</th></tr>
    <?php foreach ($items as $i => $it): ?>
    <tr>
      <td><?= $i + 1 ?></td>
      <td><?= e($it['name']) ?><br><small class="muted"><?= e($it['sku']) ?></small></td>
      <td><?= e((string)$it['qty']) ?></td>
      <td><?= money($it['price']) ?></td>
      <td><?= money($it['total']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <div class="inv-totals">
    <div>Subtotal: <strong><?= money($sale['subtotal']) ?></strong></div>
    <div>Discount: <strong><?= money($sale['discount']) ?></strong></div>
    <div class="grand">Total: <strong><?= money($sale['total']) ?></strong></div>
  </div>
  <p class="muted center">Thank you for shopping with us!</p>
  <div class="btn-row no-print">
    <button class="btn btn-primary" onclick="window.print()">🖨️ Print</button>
    <a class="btn" href="index.php?page=sales">Back to sales</a>
    <a class="btn btn-ghost" href="index.php?page=sale">New sale</a>
  </div>
</div>
