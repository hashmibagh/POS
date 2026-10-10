<?php
// New Sale (POS): session cart, per-line qty, bill discount, atomic checkout.
$pdo = db();
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = []; // product_id => qty

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $pid = as_int($_POST['product_id'] ?? null);
        $qty = as_int($_POST['qty'] ?? null) ?? 1;
        if ($pid && $qty > 0) {
            $st = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
            $st->execute([$pid]);
            $stock = $st->fetchColumn();
            if ($stock === false) {
                flash('Product not found.', 'error');
            } elseif ($qty + ($_SESSION['cart'][$pid] ?? 0) > (int)$stock) {
                flash('Not enough stock available.', 'error');
            } else {
                $_SESSION['cart'][$pid] = ($_SESSION['cart'][$pid] ?? 0) + $qty;
            }
        }
    } elseif ($action === 'remove') {
        unset($_SESSION['cart'][as_int($_POST['product_id'] ?? null) ?? 0]);
    } elseif ($action === 'clear') {
        $_SESSION['cart'] = [];
    } elseif ($action === 'complete') {
        $cart = $_SESSION['cart'];
        if (!$cart) {
            flash('Cart is empty.', 'error');
            redirect('index.php?page=sale');
        }
        $discount = as_float($_POST['discount'] ?? '0') ?? 0;
        if ($discount < 0) $discount = 0;

        try {
            $pdo->beginTransaction();
            // Re-read prices server-side; never trust client-submitted prices.
            $lines = [];
            $subtotal = 0.0;
            $pst = $pdo->prepare("SELECT id, name, sale_price, stock FROM products WHERE id = ?");
            foreach ($cart as $pid => $qty) {
                $pst->execute([$pid]);
                $p = $pst->fetch();
                if (!$p) throw new RuntimeException('A product in the cart no longer exists.');
                if ($qty > (int)$p['stock']) {
                    throw new RuntimeException('Insufficient stock for ' . $p['name'] . '.');
                }
                $lineTotal = $qty * (float)$p['sale_price'];
                $lines[] = ['id' => $p['id'], 'qty' => $qty, 'price' => (float)$p['sale_price'], 'total' => $lineTotal];
                $subtotal += $lineTotal;
            }
            $discount = min($discount, $subtotal);
            $total = $subtotal - $discount;
            $invoice = next_invoice_no($pdo);

            $saleId = insert_get_id($pdo,
                "INSERT INTO sales (invoice_no, user_id, subtotal, discount, total, created_at)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$invoice, current_user()['id'], $subtotal, $discount, $total, date('Y-m-d H:i:s')]);

            $ist = $pdo->prepare(
                "INSERT INTO sale_items (sale_id, product_id, qty, price, total) VALUES (?,?,?,?,?)"
            );
            $ust = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
            foreach ($lines as $l) {
                $ist->execute([$saleId, $l['id'], $l['qty'], $l['price'], $l['total']]);
                $ust->execute([$l['qty'], $l['id']]);
            }
            $pdo->commit();
            $_SESSION['cart'] = [];
            redirect('index.php?page=invoice&id=' . $saleId);
        } catch (Exception $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('Sale failed: ' . $ex->getMessage(), 'error');
            redirect('index.php?page=sale');
        }
    }
    redirect('index.php?page=sale');
}

// Render cart with fresh prices.
$cart = $_SESSION['cart'];
$cartLines = [];
$subtotal = 0.0;
if ($cart) {
    $pst = $pdo->prepare("SELECT id, name, sku, sale_price, stock FROM products WHERE id = ?");
    foreach ($cart as $pid => $qty) {
        $pst->execute([$pid]);
        if ($p = $pst->fetch()) {
            $t = $qty * (float)$p['sale_price'];
            $subtotal += $t;
            $cartLines[] = ['p' => $p, 'qty' => $qty, 'total' => $t];
        }
    }
}

$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $like = like_op();
    $st = $pdo->prepare(
        "SELECT * FROM products WHERE (name $like ? OR sku $like ?) AND stock > 0 ORDER BY name LIMIT 30"
    );
    $st->execute(["%$q%", "%$q%"]);
} else {
    $st = $pdo->query("SELECT * FROM products WHERE stock > 0 ORDER BY name LIMIT 30");
}
$catalog = $st->fetchAll();
?>
<h1>New Sale</h1>
<div class="pos-grid">
  <div class="card">
    <h3>Products</h3>
    <form class="toolbar" method="get" action="index.php">
      <input type="hidden" name="page" value="sale">
      <input type="search" name="q" placeholder="Search products…" value="<?= e($q) ?>">
      <button class="btn" type="submit">Search</button>
    </form>
    <table class="tbl">
      <tr><th>Product</th><th>Price</th><th>Stock</th><th></th></tr>
      <?php foreach ($catalog as $p): ?>
      <tr>
        <td><?= e($p['name']) ?><br><small class="muted"><?= e($p['sku']) ?></small></td>
        <td><?= money($p['sale_price']) ?></td>
        <td><?= e((string)$p['stock']) ?></td>
        <td>
          <form method="post" action="index.php?page=sale" class="inline-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
            <input type="number" name="qty" value="1" min="1" max="<?= (int)$p['stock'] ?>" class="qty">
            <button class="btn btn-sm btn-primary" type="submit">Add</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php if (!$catalog): ?><p class="muted">No products in stock.</p><?php endif; ?>
  </div>

  <div class="card">
    <h3>🧾 Current bill</h3>
    <?php if (!$cartLines): ?>
      <p class="muted">Cart is empty. Add products from the list.</p>
    <?php else: ?>
      <table class="tbl">
        <tr><th>Item</th><th>Qty</th><th>Total</th><th></th></tr>
        <?php foreach ($cartLines as $l): ?>
        <tr>
          <td><?= e($l['p']['name']) ?><br><small class="muted"><?= money($l['p']['sale_price']) ?> each</small></td>
          <td><?= e((string)$l['qty']) ?></td>
          <td><?= money($l['total']) ?></td>
          <td>
            <form method="post" action="index.php?page=sale" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="remove">
              <input type="hidden" name="product_id" value="<?= (int)$l['p']['id'] ?>">
              <button class="btn btn-sm btn-ghost" type="submit">✕</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
      <p class="total-line">Subtotal: <strong><?= money($subtotal) ?></strong></p>
      <form method="post" action="index.php?page=sale">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="complete">
        <label>Bill discount (Rs)
          <input type="number" name="discount" value="0" min="0" step="0.01" class="qty">
        </label>
        <div class="btn-row">
          <button class="btn btn-primary" type="submit" data-confirm="Complete this sale?">Complete Sale</button>
        </div>
      </form>
      <form method="post" action="index.php?page=sale" style="margin-top:8px">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="clear">
        <button class="btn btn-ghost" type="submit">Clear cart</button>
      </form>
    <?php endif; ?>
  </div>
</div>
