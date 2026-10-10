<?php
// Dashboard: KPIs + low stock + recent sales.
$pdo = db();
$t = today();
$m = date('Y-m-01');

$todayTotal = (float)$pdo->query(
    "SELECT COALESCE(SUM(total),0) FROM sales WHERE date(created_at) = '$t'"
)->fetchColumn();
$monthTotal = (float)$pdo->query(
    "SELECT COALESCE(SUM(total),0) FROM sales WHERE date(created_at) >= '$m'"
)->fetchColumn();
$productCount = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$lowStock = $pdo->query(
    "SELECT * FROM products WHERE stock <= low_stock_alert ORDER BY stock ASC LIMIT 10"
)->fetchAll();
$recent = $pdo->query(
    "SELECT s.*, u.name AS cashier FROM sales s
     JOIN users u ON u.id = s.user_id
     ORDER BY s.id DESC LIMIT 5"
)->fetchAll();
?>
<h1>Dashboard</h1>
<div class="cards">
  <div class="card stat"><span class="stat-label">Today's sales</span><span class="stat-value"><?= money($todayTotal) ?></span></div>
  <div class="card stat"><span class="stat-label">This month</span><span class="stat-value"><?= money($monthTotal) ?></span></div>
  <div class="card stat"><span class="stat-label">Products</span><span class="stat-value"><?= e((string)$productCount) ?></span></div>
  <div class="card stat warn"><span class="stat-label">Low stock items</span><span class="stat-value"><?= e((string)count($lowStock)) ?></span></div>
</div>

<div class="grid-2">
  <div class="card">
    <h3>⚠️ Low stock alerts</h3>
    <?php if (!$lowStock): ?><p class="muted">All stocked up.</p><?php else: ?>
    <table class="tbl">
      <tr><th>Product</th><th>Stock</th><th>Alert at</th></tr>
      <?php foreach ($lowStock as $p): ?>
      <tr class="low">
        <td><?= e($p['name']) ?></td><td><?= e((string)$p['stock']) ?></td><td><?= e((string)$p['low_stock_alert']) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>
  <div class="card">
    <h3>🧾 Recent sales</h3>
    <?php if (!$recent): ?><p class="muted">No sales yet.</p><?php else: ?>
    <table class="tbl">
      <tr><th>Invoice</th><th>Total</th><th>By</th></tr>
      <?php foreach ($recent as $s): ?>
      <tr>
        <td><a href="index.php?page=invoice&id=<?= (int)$s['id'] ?>"><?= e($s['invoice_no']) ?></a></td>
        <td><?= money($s['total']) ?></td>
        <td><?= e($s['cashier']) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>
</div>
