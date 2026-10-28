<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

if (is_post()) {
    csrf_check();
    $action    = post('action');
    $productId = (int) post('product_id');
    $return    = safe_next(post('return'), 'cart.php');

    switch ($action) {
        case 'add':
            $product = $productId > 0 ? find_product($productId) : null;
            if ($product === null || (int) $product['stock'] < 1) {
                flash('error', 'Sorry, that item is not available right now.');
                redirect($return);
            }
            cart_add($productId, (int) post('qty', '1'), (int) $product['stock']);
            flash('success', 'Added ' . $product['title'] . ' to your cart.');
            redirect($return);

        case 'update':
            cart_set($productId, (int) post('qty'));
            break;

        case 'remove':
            cart_remove($productId);
            flash('info', 'Item removed from your cart.');
            break;

        case 'clear':
            cart_clear();
            flash('info', 'Your cart has been emptied.');
            break;
    }
    redirect('cart.php');
}

$cart = cart_summary();

$pageTitle = 'Your cart';
$active    = '';
require __DIR__ . '/header.php';
?>
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
  <h1 class="text-3xl font-bold mb-8">
    Your cart (<?= $cart['count'] ?> <?= $cart['count'] === 1 ? 'item' : 'items' ?>)
  </h1>

  <?php if (!$cart['lines']): ?>
    <div class="card p-10 text-center max-w-2xl mx-auto">
      <i class="fa-solid fa-basket-shopping text-5xl text-gray-300 mb-4"></i>
      <h2 class="text-xl font-bold mb-2">Your cart is empty</h2>
      <p class="text-gray-500 text-sm mb-6">Looks like you haven&rsquo;t added any fresh farm produce yet.</p>
      <a href="catalogue.php" class="btn btn-primary">Browse catalogue</a>
    </div>
  <?php else: ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <div class="lg:col-span-2 space-y-4">
        <?php foreach ($cart['lines'] as $line):
            $p = $line['product']; ?>
          <div class="card p-4 sm:p-5 flex flex-col sm:flex-row items-center justify-between gap-4">
            <a href="product.php?id=<?= (int) $p['id'] ?>" class="flex items-center space-x-4 w-full sm:w-auto">
              <img src="<?= e(product_image($p['image_url'])) ?>" alt="" class="w-16 h-16 rounded-md object-cover flex-shrink-0">
              <div>
                <h2 class="font-bold text-base"><?= e($p['title']) ?></h2>
                <p class="text-xs text-gray-500"><?= e(money($p['price'])) ?> / <?= e($p['unit']) ?> &middot; <?= e($p['farm_name']) ?></p>
              </div>
            </a>

            <div class="flex items-center justify-between sm:justify-end w-full sm:w-auto space-x-6">
              <form method="post" class="stepper">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
                <button type="submit" name="qty" value="<?= $line['qty'] - 1 ?>" aria-label="Decrease quantity">&minus;</button>
                <span class="px-3 py-1 text-sm font-semibold"><?= $line['qty'] ?></span>
                <button type="submit" name="qty" value="<?= min($line['qty'] + 1, (int) $p['stock']) ?>" aria-label="Increase quantity"
                        <?= $line['qty'] >= (int) $p['stock'] ? 'disabled' : '' ?>>+</button>
              </form>

              <div class="font-bold text-base text-right min-w-[5.5rem]"><?= e(money($line['line_total'])) ?></div>

              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
                <button type="submit" class="text-gray-400 hover:text-red-500 p-1" aria-label="Remove <?= e($p['title']) ?>">
                  <i class="fa-solid fa-trash-can"></i>
                </button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>

        <form method="post" data-confirm="Remove everything from your cart?">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="clear">
          <button type="submit" class="text-sm text-gray-500 hover:text-red-600 underline">Empty cart</button>
        </form>
      </div>

      <aside>
        <div class="bg-brand-creamdark p-6 rounded-xl border border-brand-cardborder shadow-sm lg:sticky lg:top-28">
          <h2 class="text-xl font-bold mb-4 border-b border-brand-cardborder pb-3">Order summary</h2>
          <div class="space-y-3 text-sm mb-6">
            <div class="flex justify-between text-gray-700"><span>Subtotal</span><span class="font-semibold"><?= e(money($cart['subtotal'])) ?></span></div>
            <div class="flex justify-between text-gray-700"><span>Delivery fee</span><span class="font-semibold"><?= e(money($cart['delivery'])) ?></span></div>
            <div class="border-t border-brand-cardborder pt-3 flex justify-between text-base font-bold">
              <span>Total</span><span><?= e(money($cart['total'])) ?></span>
            </div>
          </div>
          <a href="checkout.php" class="btn btn-primary w-full py-3.5 text-base">Continue to checkout</a>
          <p class="text-xs text-gray-500 text-center mt-3">
            <i class="fa-solid fa-shield-halved mr-1"></i> Prices are confirmed at checkout
          </p>
        </div>
      </aside>
    </div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/footer.php'; ?>
