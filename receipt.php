<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$user    = require_login();
$orderId = (int) query('id');

$stmt = db()->prepare(
    'SELECT o.*, u.name AS buyer_name, u.email AS buyer_email
     FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?'
);
$stmt->execute([$orderId]);
$order = $stmt->fetch();

// Buyers may only open their own receipts; administrators may open any.
if (!$order || ((int) $order['user_id'] !== (int) $user['id'] && $user['role'] !== 'admin')) {
    not_found('We could not find that order.');
}

$items = db()->prepare(
    'SELECT oi.*, f.farm_name FROM order_items oi
     JOIN farmers f ON f.id = oi.farmer_id WHERE oi.order_id = ? ORDER BY oi.id'
);
$items->execute([$orderId]);
$items = $items->fetchAll();

$pageTitle = 'Receipt #' . $orderId;
$active    = '';
require __DIR__ . '/header.php';
?>
<section class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
  <div class="flex items-center justify-between mb-6 no-print">
    <a href="orders.php" class="text-sm font-semibold hover:underline"><i class="fa-solid fa-arrow-left mr-1"></i> My orders</a>
    <button type="button" class="btn btn-outline btn-sm" onclick="window.print()"><i class="fa-solid fa-print"></i> Print receipt</button>
  </div>

  <div class="card p-6 sm:p-10">
    <div class="flex flex-wrap items-start justify-between gap-4 mb-8">
      <div>
        <div class="text-2xl font-bold font-serif"><?= e(APP_NAME) ?></div>
        <div class="text-xs text-gray-500"><?= e(SUPPORT_EMAIL) ?> &middot; <?= e(SUPPORT_PHONE) ?></div>
      </div>
      <div class="text-right text-sm">
        <div class="font-bold text-lg">Receipt #<?= $orderId ?></div>
        <div class="text-gray-500"><?= e(format_date($order['created_at'], 'j M Y, H:i')) ?></div>
        <div class="mt-1"><?= status_badge($order['status']) ?></div>
      </div>
    </div>

    <div class="grid sm:grid-cols-2 gap-6 text-sm mb-8">
      <div>
        <div class="label">Billed to</div>
        <div class="font-semibold"><?= e($order['buyer_name']) ?></div>
        <div class="text-gray-600"><?= e($order['buyer_email']) ?></div>
        <div class="text-gray-600"><?= e($order['phone']) ?></div>
      </div>
      <div>
        <div class="label">Deliver to</div>
        <div class="text-gray-700"><?= e($order['address']) ?></div>
        <?php if ($order['notes']): ?><div class="text-gray-500 text-xs mt-1">Note: <?= e($order['notes']) ?></div><?php endif; ?>
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="data-table">
        <thead><tr><th>Item</th><th>Farm</th><th class="text-right">Price</th><th class="text-right">Qty</th><th class="text-right">Total</th></tr></thead>
        <tbody>
          <?php foreach ($items as $it): ?>
            <tr>
              <td class="font-semibold"><?= e($it['title']) ?> <span class="text-xs font-normal text-gray-500">/ <?= e($it['unit']) ?></span></td>
              <td class="text-gray-600"><?= e($it['farm_name']) ?></td>
              <td class="text-right"><?= e(money($it['unit_price'])) ?></td>
              <td class="text-right"><?= (int) $it['quantity'] ?></td>
              <td class="text-right font-semibold"><?= e(money((float) $it['unit_price'] * (int) $it['quantity'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="mt-6 ml-auto max-w-xs space-y-2 text-sm">
      <div class="flex justify-between"><span>Subtotal</span><span><?= e(money($order['subtotal'])) ?></span></div>
      <div class="flex justify-between"><span>Delivery fee</span><span><?= e(money($order['delivery_fee'])) ?></span></div>
      <div class="flex justify-between text-base font-bold border-t border-brand-cardborder pt-2"><span>Total</span><span><?= e(money($order['total'])) ?></span></div>
    </div>

    <div class="mt-8 pt-4 border-t border-brand-cardborder text-sm text-gray-600 flex flex-wrap justify-between gap-2">
      <span>Payment: <strong><?= e(payment_label($order['payment_method'])) ?></strong> (<?= e($order['payment_status']) ?>)</span>
      <span>Thank you for buying directly from farmers.</span>
    </div>
  </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>
