<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$user = require_login();
$cart = cart_summary();

if (!$cart['lines']) {
    flash('info', 'Your cart is empty.');
    redirect('catalogue.php');
}

$errors = [];
$old    = ['phone' => $user['phone'], 'address' => '', 'payment_method' => 'mpesa', 'notes' => ''];

if (is_post()) {
    csrf_check();
    $old = [
        'phone'          => post('phone'),
        'address'        => post('address'),
        'payment_method' => post('payment_method'),
        'notes'          => post('notes'),
    ];

    if (!valid_phone($old['phone'])) {
        $errors[] = 'Enter a valid phone number for the delivery contact.';
    }
    if (mb_strlen($old['address']) < 5 || mb_strlen($old['address']) > 255) {
        $errors[] = 'Enter a delivery address (5–255 characters).';
    }
    if (!in_array($old['payment_method'], ['mpesa', 'cod'], true)) {
        $errors[] = 'Choose a payment method.';
    }
    if (mb_strlen($old['notes']) > 500) {
        $errors[] = 'Order notes must be 500 characters or fewer.';
    }

    if (!$errors) {
        $pdo = db();
        try {
            $pdo->beginTransaction();

            $items    = [];
            $subtotal = 0.0;

            // Lock each product row so stock cannot be oversold by concurrent orders.
            $lock = $pdo->prepare(
                'SELECT id, farmer_id, title, unit, price, stock, is_active FROM products WHERE id = ? FOR UPDATE'
            );
            foreach ($cart['lines'] as $line) {
                $lock->execute([(int) $line['product']['id']]);
                $p = $lock->fetch();

                if (!$p || !(int) $p['is_active'] || (int) $p['stock'] < $line['qty']) {
                    $name = $p['title'] ?? $line['product']['title'];
                    throw new RuntimeException("“{$name}” no longer has enough stock. Please review your cart.");
                }
                $subtotal += (float) $p['price'] * $line['qty'];
                $items[]   = ['product' => $p, 'qty' => $line['qty']];
            }

            $delivery = (float) DELIVERY_FEE;
            $pdo->prepare(
                'INSERT INTO orders (user_id, subtotal, delivery_fee, total, payment_method, phone, address, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $user['id'], $subtotal, $delivery, $subtotal + $delivery,
                $old['payment_method'], $old['phone'], $old['address'], $old['notes'] ?: null,
            ]);
            $orderId = (int) $pdo->lastInsertId();

            $insertItem = $pdo->prepare(
                'INSERT INTO order_items (order_id, product_id, farmer_id, title, unit, unit_price, quantity)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $reduceStock = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');

            foreach ($items as $item) {
                $p = $item['product'];
                $insertItem->execute([$orderId, $p['id'], $p['farmer_id'], $p['title'], $p['unit'], $p['price'], $item['qty']]);
                $reduceStock->execute([$item['qty'], $p['id']]);
            }

            $pdo->commit();
            cart_clear();
            flash('success', 'Order #' . $orderId . ' placed. Thank you for supporting local farmers!');
            redirect('receipt.php?id=' . $orderId);
        } catch (RuntimeException $ex) {
            $pdo->rollBack();
            $errors[] = $ex->getMessage();
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Checkout failed: ' . $ex->getMessage());
            $errors[] = 'Something went wrong while placing your order. Please try again.';
        }
        $cart = cart_summary();   // refresh in case stock changed
    }
}

$pageTitle = 'Checkout';
$active    = '';
require __DIR__ . '/header.php';
?>
<section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
  <h1 class="text-3xl font-bold mb-8">Checkout</h1>

  <?php if ($errors): ?>
    <ul class="mb-6 p-4 rounded-md bg-red-50 border border-red-300 text-red-700 text-sm list-disc list-inside">
      <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <?php if (!$cart['lines']): ?>
    <div class="card p-8 text-center">
      <p class="mb-4">Your cart is now empty.</p>
      <a class="btn btn-primary" href="catalogue.php">Browse catalogue</a>
    </div>
  <?php else: ?>
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <form method="post" class="lg:col-span-2 card p-6 sm:p-8 space-y-5">
      <?= csrf_field() ?>
      <h2 class="text-xl font-bold">Delivery details</h2>

      <div>
        <label class="label" for="phone">Contact phone</label>
        <input class="field" id="phone" type="tel" name="phone" required value="<?= e($old['phone']) ?>" placeholder="+254 700 000000">
      </div>
      <div>
        <label class="label" for="address">Delivery address</label>
        <input class="field" id="address" name="address" required maxlength="255" value="<?= e($old['address']) ?>"
               placeholder="Street, estate, town">
      </div>
      <div>
        <label class="label" for="notes">Order notes (optional)</label>
        <textarea class="field" id="notes" name="notes" maxlength="500" placeholder="Gate code, preferred delivery time…"><?= e($old['notes']) ?></textarea>
      </div>

      <fieldset>
        <legend class="label">Payment method</legend>
        <div class="grid sm:grid-cols-2 gap-3">
          <?php foreach (['mpesa' => ['M-Pesa', 'Pay by M-Pesa when your order arrives'], 'cod' => ['Cash', 'Pay cash on delivery']] as $value => [$title, $hint]): ?>
            <label class="flex items-start gap-3 p-4 border rounded-lg cursor-pointer hover:bg-brand-cream <?= $old['payment_method'] === $value ? 'border-brand-green bg-brand-cream' : 'border-gray-300' ?>">
              <input type="radio" name="payment_method" value="<?= $value ?>" class="mt-1" <?= $old['payment_method'] === $value ? 'checked' : '' ?>>
              <span><span class="block font-semibold text-sm"><?= e($title) ?></span><span class="block text-xs text-gray-500"><?= e($hint) ?></span></span>
            </label>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <div class="flex flex-wrap gap-3 pt-2">
        <button type="submit" class="btn btn-primary px-8 py-3.5 text-base">Place order</button>
        <a href="cart.php" class="btn btn-outline px-6 py-3.5 text-base">Back to cart</a>
      </div>
    </form>

    <aside>
      <div class="bg-brand-creamdark p-6 rounded-xl border border-brand-cardborder shadow-sm">
        <h2 class="text-xl font-bold mb-4 border-b border-brand-cardborder pb-3">Your order</h2>
        <ul class="space-y-3 text-sm mb-4">
          <?php foreach ($cart['lines'] as $line): ?>
            <li class="flex justify-between gap-3">
              <span><?= e($line['product']['title']) ?> <span class="text-gray-500">&times; <?= $line['qty'] ?></span></span>
              <span class="font-semibold whitespace-nowrap"><?= e(money($line['line_total'])) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
        <div class="border-t border-brand-cardborder pt-3 space-y-2 text-sm">
          <div class="flex justify-between"><span>Subtotal</span><span><?= e(money($cart['subtotal'])) ?></span></div>
          <div class="flex justify-between"><span>Delivery fee</span><span><?= e(money($cart['delivery'])) ?></span></div>
          <div class="flex justify-between text-base font-bold pt-1"><span>Total</span><span><?= e(money($cart['total'])) ?></span></div>
        </div>
      </div>
    </aside>
  </div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/footer.php'; ?>
