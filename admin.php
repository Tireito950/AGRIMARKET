<?php
declare(strict_types=1);

/**
 * Administrator dashboard: overview, orders, farmers, products, users, messages.
 */

require_once __DIR__ . '/functions.php';

$admin = require_role('admin');
$pdo   = db();

$tabs = [
    'overview' => 'Overview',
    'orders'   => 'Orders',
    'farmers'  => 'Farmers',
    'products' => 'Products',
    'users'    => 'Users',
    'messages' => 'Messages',
];
$tab = query('tab', 'overview');
if (!isset($tabs[$tab])) {
    $tab = 'overview';
}

if (is_post()) {
    csrf_check();
    $action = post('action');
    $id     = (int) post('id');
    $back   = 'admin.php?tab=' . urlencode($tab);

    switch ($action) {
        case 'order_status':
            $status = post('status');
            $paid   = post('payment_status');
            if ($id > 0 && in_array($status, ['pending', 'confirmed', 'delivered', 'cancelled'], true)
                && in_array($paid, ['pending', 'paid'], true)) {
                $pdo->beginTransaction();
                $cur = $pdo->prepare('SELECT status FROM orders WHERE id = ? FOR UPDATE');
                $cur->execute([$id]);
                $previous = $cur->fetchColumn();

                if ($previous !== false) {
                    $pdo->prepare('UPDATE orders SET status = ?, payment_status = ? WHERE id = ?')->execute([$status, $paid, $id]);

                    // Return stock when an order is cancelled (and take it back if it is reinstated).
                    if ($previous !== 'cancelled' && $status === 'cancelled') {
                        $pdo->prepare('UPDATE products p JOIN order_items oi ON oi.product_id = p.id
                                       SET p.stock = p.stock + oi.quantity WHERE oi.order_id = ?')->execute([$id]);
                    } elseif ($previous === 'cancelled' && $status !== 'cancelled') {
                        $pdo->prepare('UPDATE products p JOIN order_items oi ON oi.product_id = p.id
                                       SET p.stock = GREATEST(p.stock - oi.quantity, 0) WHERE oi.order_id = ?')->execute([$id]);
                    }
                    flash('success', "Order #$id updated.");
                }
                $pdo->commit();
            }
            break;

        case 'verify_farmer':
            $pdo->prepare('UPDATE farmers SET verified = 1 - verified WHERE id = ?')->execute([$id]);
            flash('success', 'Farmer verification changed.');
            break;

        case 'toggle_product':
            $pdo->prepare('UPDATE products SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
            flash('success', 'Product visibility changed.');
            break;

        case 'delete_message':
            $pdo->prepare('DELETE FROM contact_messages WHERE id = ?')->execute([$id]);
            flash('success', 'Message deleted.');
            break;
    }
    redirect($back);
}

$pageTitle = 'Admin dashboard';
$active    = '';
require __DIR__ . '/header.php';
?>
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
  <h1 class="text-3xl font-bold mb-6">Admin dashboard</h1>

  <nav class="flex flex-wrap gap-2 mb-8" aria-label="Admin sections">
    <?php foreach ($tabs as $key => $label): ?>
      <a href="admin.php?tab=<?= e($key) ?>"
         class="px-4 py-2 rounded-md text-sm font-semibold border <?= $tab === $key ? 'bg-brand-green text-white border-brand-green' : 'bg-white border-brand-cardborder hover:bg-brand-creamdark' ?>">
        <?= e($label) ?>
      </a>
    <?php endforeach; ?>
  </nav>

<?php if ($tab === 'overview'):
    $s = $pdo->query(
        "SELECT (SELECT COUNT(*) FROM users)                              AS users,
                (SELECT COUNT(*) FROM farmers)                            AS farmers,
                (SELECT COUNT(*) FROM farmers WHERE verified = 0)         AS unverified,
                (SELECT COUNT(*) FROM products WHERE is_active = 1)       AS products,
                (SELECT COUNT(*) FROM orders)                             AS orders,
                (SELECT COUNT(*) FROM orders WHERE status = 'pending')    AS pending,
                (SELECT COALESCE(SUM(total), 0) FROM orders WHERE status <> 'cancelled') AS revenue,
                (SELECT COUNT(*) FROM contact_messages)                   AS messages"
    )->fetch();
    $cards = [
        ['Users', $s['users']], ['Farmers', $s['farmers'] . ' (' . $s['unverified'] . ' unverified)'],
        ['Live products', $s['products']], ['Orders', $s['orders'] . ' (' . $s['pending'] . ' pending)'],
        ['Revenue (excl. cancelled)', money($s['revenue'])], ['Messages', $s['messages']],
    ]; ?>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php foreach ($cards as [$label, $value]): ?>
      <div class="card p-6">
        <div class="text-xs uppercase tracking-wider text-gray-500"><?= e($label) ?></div>
        <div class="text-2xl font-bold mt-1"><?= e($value) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

<?php elseif ($tab === 'orders'):
    $orders = $pdo->query(
        'SELECT o.*, u.name AS buyer FROM orders o JOIN users u ON u.id = o.user_id
         ORDER BY o.created_at DESC, o.id DESC LIMIT 100'
    )->fetchAll(); ?>
  <div class="card overflow-x-auto">
    <table class="data-table">
      <thead><tr><th>Order</th><th>Date</th><th>Buyer</th><th>Total</th><th>Payment</th><th>Update</th></tr></thead>
      <tbody>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td class="font-semibold"><a class="hover:underline" href="receipt.php?id=<?= (int) $o['id'] ?>">#<?= (int) $o['id'] ?></a></td>
            <td><?= e(format_date($o['created_at'])) ?></td>
            <td><?= e($o['buyer']) ?></td>
            <td class="font-semibold"><?= e(money($o['total'])) ?></td>
            <td><?= e(payment_label($o['payment_method'])) ?></td>
            <td>
              <form method="post" class="flex flex-wrap items-center gap-2">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="order_status">
                <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                <select class="field !w-auto !py-1" name="status" aria-label="Order status">
                  <?php foreach (['pending', 'confirmed', 'delivered', 'cancelled'] as $st): ?>
                    <option value="<?= $st ?>" <?= $o['status'] === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                  <?php endforeach; ?>
                </select>
                <select class="field !w-auto !py-1" name="payment_status" aria-label="Payment status">
                  <?php foreach (['pending', 'paid'] as $ps): ?>
                    <option value="<?= $ps ?>" <?= $o['payment_status'] === $ps ? 'selected' : '' ?>><?= ucfirst($ps) ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn-green btn-sm" type="submit">Save</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$orders): ?><tr><td colspan="6" class="text-center text-gray-500 py-8">No orders yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

<?php elseif ($tab === 'farmers'):
    $farmers = $pdo->query(
        'SELECT f.*, u.name AS owner, u.email, u.phone,
                (SELECT COUNT(*) FROM products p WHERE p.farmer_id = f.id) AS listings
         FROM farmers f JOIN users u ON u.id = f.user_id ORDER BY f.verified, f.created_at DESC'
    )->fetchAll(); ?>
  <div class="card overflow-x-auto">
    <table class="data-table">
      <thead><tr><th>Farm</th><th>Owner</th><th>Contact</th><th>Location</th><th>Listings</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($farmers as $f): ?>
          <tr>
            <td class="font-semibold"><a class="hover:underline" href="farmer_profile.php?id=<?= (int) $f['id'] ?>"><?= e($f['farm_name']) ?></a></td>
            <td><?= e($f['owner']) ?></td>
            <td><?= e($f['email']) ?><div class="text-xs text-gray-500"><?= e($f['phone']) ?></div></td>
            <td><?= e($f['location']) ?></td>
            <td><?= (int) $f['listings'] ?></td>
            <td><?= $f['verified'] ? '<span class="badge bg-green-100 text-green-800">Verified</span>' : '<span class="badge bg-yellow-100 text-yellow-800">Unverified</span>' ?></td>
            <td class="text-right">
              <form method="post">
                <?= csrf_field() ?><input type="hidden" name="action" value="verify_farmer"><input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                <button class="btn btn-outline btn-sm" type="submit"><?= $f['verified'] ? 'Revoke' : 'Verify' ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$farmers): ?><tr><td colspan="7" class="text-center text-gray-500 py-8">No farmers yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

<?php elseif ($tab === 'products'):
    $products = $pdo->query(
        'SELECT p.*, f.farm_name, c.name AS category FROM products p
         JOIN farmers f ON f.id = p.farmer_id JOIN categories c ON c.id = p.category_id
         ORDER BY p.created_at DESC, p.id DESC LIMIT 200'
    )->fetchAll(); ?>
  <div class="card overflow-x-auto">
    <table class="data-table">
      <thead><tr><th>Product</th><th>Farm</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($products as $p): ?>
          <tr>
            <td class="font-semibold"><a class="hover:underline" href="product.php?id=<?= (int) $p['id'] ?>"><?= e($p['title']) ?></a></td>
            <td><?= e($p['farm_name']) ?></td>
            <td><?= e($p['category']) ?></td>
            <td><?= e(money($p['price'])) ?> / <?= e($p['unit']) ?></td>
            <td><?= (int) $p['stock'] ?></td>
            <td><?= $p['is_active'] ? '<span class="badge bg-green-100 text-green-800">Live</span>' : '<span class="badge bg-gray-200 text-gray-700">Hidden</span>' ?></td>
            <td class="text-right">
              <form method="post">
                <?= csrf_field() ?><input type="hidden" name="action" value="toggle_product"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <button class="btn btn-outline btn-sm" type="submit"><?= $p['is_active'] ? 'Hide' : 'Show' ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$products): ?><tr><td colspan="7" class="text-center text-gray-500 py-8">No products yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

<?php elseif ($tab === 'users'):
    $users = $pdo->query('SELECT id, name, email, phone, role, created_at FROM users ORDER BY created_at DESC, id DESC LIMIT 200')->fetchAll(); ?>
  <div class="card overflow-x-auto">
    <table class="data-table">
      <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Joined</th></tr></thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td class="font-semibold"><?= e($u['name']) ?></td>
            <td><?= e($u['email']) ?></td>
            <td><?= e($u['phone']) ?></td>
            <td><span class="badge bg-brand-creamdark text-brand-green"><?= e(ucfirst($u['role'])) ?></span></td>
            <td><?= e(format_date($u['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

<?php else:
    $messages = $pdo->query('SELECT * FROM contact_messages ORDER BY created_at DESC, id DESC LIMIT 100')->fetchAll(); ?>
  <div class="space-y-4">
    <?php foreach ($messages as $m): ?>
      <article class="card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3 mb-2">
          <div>
            <h2 class="font-bold"><?= e($m['subject']) ?></h2>
            <p class="text-xs text-gray-500">
              <?= e($m['name']) ?> &middot; <a class="hover:underline" href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a>
              &middot; <?= e(format_date($m['created_at'], 'j M Y, H:i')) ?>
            </p>
          </div>
          <form method="post" data-confirm="Delete this message?">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete_message"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
            <button class="btn btn-danger btn-sm" type="submit">Delete</button>
          </form>
        </div>
        <p class="text-sm text-gray-700 whitespace-pre-line"><?= e($m['message']) ?></p>
      </article>
    <?php endforeach; ?>
    <?php if (!$messages): ?><div class="card p-8 text-center text-gray-600">No messages.</div><?php endif; ?>
  </div>
<?php endif; ?>
</section>
<?php require __DIR__ . '/footer.php'; ?>
