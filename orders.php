<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$user = require_login();

$stmt = db()->prepare(
    'SELECT o.*, (SELECT COALESCE(SUM(quantity), 0) FROM order_items WHERE order_id = o.id) AS item_count
     FROM orders o WHERE o.user_id = ? ORDER BY o.created_at DESC, o.id DESC'
);
$stmt->execute([$user['id']]);
$orders = $stmt->fetchAll();

$pageTitle = 'My orders';
$active    = '';
require __DIR__ . '/header.php';
?>
<section class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
  <h1 class="text-3xl font-bold mb-8">My orders</h1>

  <?php if (!$orders): ?>
    <div class="card p-10 text-center">
      <i class="fa-solid fa-box-open text-5xl text-gray-300 mb-4"></i>
      <p class="text-gray-600 mb-6">You have not placed any orders yet.</p>
      <a href="catalogue.php" class="btn btn-primary">Browse produce</a>
    </div>
  <?php else: ?>
    <div class="card overflow-x-auto">
      <table class="data-table">
        <thead>
          <tr><th>Order</th><th>Date</th><th>Items</th><th>Total</th><th>Payment</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $o): ?>
            <tr>
              <td class="font-semibold">#<?= (int) $o['id'] ?></td>
              <td><?= e(format_date($o['created_at'])) ?></td>
              <td><?= (int) $o['item_count'] ?></td>
              <td class="font-semibold"><?= e(money($o['total'])) ?></td>
              <td><?= e(payment_label($o['payment_method'])) ?> <?= status_badge($o['payment_status']) ?></td>
              <td><?= status_badge($o['status']) ?></td>
              <td class="text-right"><a class="text-brand-orange font-semibold hover:underline" href="receipt.php?id=<?= (int) $o['id'] ?>">Receipt</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/footer.php'; ?>
