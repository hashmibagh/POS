<?php
// Reports: date-range sales summary + top products.
$pdo = db();
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to = today();
if ($from > $to) { [$from, $to] = [$to, $from]; }

$sum = $pdo->prepare(
    "SELECT COUNT(*) AS bills, COALESCE(SUM(subtotal),0) AS subtotal,
            COALESCE(SUM(discount),0) AS discount, COALESCE(SUM(total),0) AS revenue
     FROM sales WHERE date(created_at) BETWEEN ? AND ?"
);
$sum->execute([$from, $to]);
$s = $sum->fetch();

$top = $pdo->prepare(
    "SELECT p.name, SUM(si.qty) AS qty, SUM(si.total) AS revenue
     FROM sale_items si
     JOIN sales s ON s.id = si.sale_id
     JOIN products p ON p.id = si.product_id
     WHERE date(s.created_at) BETWEEN ? AND ?
     GROUP BY p.id ORDER BY qty DESC LIMIT 5"
);
$top->execute([$from, $to]);
$topRows = $top->fetchAll();
?>
<h1>Reports</h1>
<form class="toolbar" method="get" action="index.php">
  <input type="hidden" name="page" value="reports">
  <label>From <input type="date" name="from" value="<?= e($from) ?>"></label>
  <label>To <input type="date" name="to" value="<?= e($to) ?>"></label>
  <button class="btn" type="submit">Show</button>
</form>

<div class="cards">
  <div class="card stat"><span class="stat-label">Revenue</span><span class="stat-value"><?= money($s['revenue']) ?></span></div>
  <div class="card stat"><span class="stat-label">Bills</span><span class="stat-value"><?= e((string)$s['bills']) ?></span></div>
  <div class="card stat"><span class="stat-label">Avg. bill</span><span class="stat-value"><?= $s['bills'] ? money($s['revenue'] / $s['bills']) : money(0) ?></span></div>
  <div class="card stat"><span class="stat-label">Discounts given</span><span class="stat-value"><?= money($s['discount']) ?></span></div>
</div>

<div class="card">
  <h3>🏆 Top products (<?= e($from) ?> → <?= e($to) ?>)</h3>
  <?php if (!$topRows): ?><p class="muted">No sales in this period.</p><?php else: ?>
  <table class="tbl">
    <tr><th>Product</th><th>Qty sold</th><th>Revenue</th></tr>
    <?php foreach ($topRows as $r): ?>
    <tr><td><?= e($r['name']) ?></td><td><?= e((string)$r['qty']) ?></td><td><?= money($r['revenue']) ?></td></tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>
