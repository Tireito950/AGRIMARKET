<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$id      = (int) query('id');
$product = $id > 0 ? find_product($id) : null;
if ($product === null) {
    not_found('That produce listing is no longer available.');
}

$stock   = (int) $product['stock'];
$related = fetch_products(['farmer_id' => $product['farmer_id'], 'exclude_id' => $id], 1, 4)['items'];
$relatedHeading = 'More from this farmer';
if (!$related) {
    $related = fetch_products(['category' => $product['category_slug'], 'exclude_id' => $id], 1, 4)['items'];
    $relatedHeading = 'You may also like';
}

$pageTitle = $product['title'];
$active    = 'catalogue';
require __DIR__ . '/header.php';
?>
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
  <a href="catalogue.php" class="mb-6 inline-flex items-center space-x-2 font-semibold text-sm hover:underline">
    <i class="fa-solid fa-arrow-left"></i><span>Back to catalogue</span>
  </a>

  <div class="card p-6 sm:p-10 mb-14">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-start">
      <div class="h-80 sm:h-96 rounded-xl bg-brand-creamdark overflow-hidden border border-brand-cardborder">
        <img src="<?= e(product_image($product['image_url'])) ?>" alt="<?= e($product['title']) ?>" class="w-full h-full object-cover">
      </div>

      <div>
        <div class="text-xs font-semibold text-brand-orange uppercase tracking-wider mb-2"><?= e($product['category']) ?></div>
        <h1 class="text-3xl font-bold mb-3"><?= e($product['title']) ?></h1>

        <div class="text-2xl font-bold mb-4">
          <?= e(money($product['price'])) ?>
          <span class="text-sm font-normal text-gray-600">/ <?= e($product['unit']) ?></span>
        </div>

        <p class="text-gray-600 text-sm leading-relaxed mb-6"><?= nl2br(e($product['description'])) ?></p>

        <dl class="bg-brand-cream p-4 rounded-lg border border-brand-cardborder mb-6 space-y-2 text-sm">
          <div class="flex justify-between">
            <dt class="text-gray-500">Farmer</dt>
            <dd class="font-semibold">
              <a class="hover:underline" href="farmer_profile.php?id=<?= (int) $product['farm_id'] ?>"><?= e($product['farm_name']) ?></a>
              <?php if ($product['verified']): ?>
                <i class="fa-solid fa-circle-check text-brand-orange ml-1" title="Verified farmer"></i>
              <?php endif; ?>
            </dd>
          </div>
          <div class="flex justify-between"><dt class="text-gray-500">Location</dt><dd class="font-semibold"><?= e($product['location']) ?></dd></div>
          <div class="flex justify-between"><dt class="text-gray-500">Harvest date</dt><dd class="font-semibold"><?= e(format_date($product['harvest_date'])) ?></dd></div>
          <div class="flex justify-between"><dt class="text-gray-500">Availability</dt>
            <dd class="font-semibold <?= $stock > 0 ? '' : 'text-red-600' ?>">
              <?= $stock > 0 ? $stock . ' in stock' : 'Out of stock' ?>
            </dd>
          </div>
        </dl>

        <?php if ($stock > 0): ?>
          <form method="post" action="cart.php" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
            <input type="hidden" name="return" value="product.php?id=<?= (int) $product['id'] ?>">

            <div class="flex items-center space-x-4">
              <label class="text-sm font-semibold text-gray-700" for="qty">Quantity</label>
              <div class="stepper" data-stepper>
                <button type="button" data-step="-1" aria-label="Decrease quantity">&minus;</button>
                <input id="qty" type="number" name="qty" value="1" min="1" max="<?= $stock ?>" inputmode="numeric">
                <button type="button" data-step="1" aria-label="Increase quantity">+</button>
              </div>
            </div>

            <button type="submit" class="btn btn-primary w-full py-3.5 text-base">
              <i class="fa-solid fa-cart-shopping"></i><span>Add to cart</span>
            </button>
          </form>
        <?php else: ?>
          <button class="btn btn-primary w-full py-3.5 text-base" disabled>Out of stock</button>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if ($related): ?>
    <h2 class="text-2xl font-bold mb-6"><?= e($relatedHeading) ?></h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
      <?php foreach ($related as $p) { echo product_card_html($p); } ?>
    </div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/footer.php'; ?>
