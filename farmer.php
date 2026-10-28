<?php
declare(strict_types=1);

/**
 * Farmer dashboard – manage produce listings and see incoming order lines.
 */

require_once __DIR__ . '/functions.php';

$user   = require_role('farmer');
$farmer = farmer_for_user((int) $user['id']);
if (!$farmer) {
    not_found('Your farmer profile is missing. Please contact support.');
}
$farmerId   = (int) $farmer['id'];
$pdo        = db();
$categories = all_categories();

$errors  = [];
$editing = null;
$form    = [
    'title' => '', 'category_id' => '', 'price' => '', 'unit' => '', 'stock' => '',
    'location' => $farmer['location'], 'harvest_date' => date('Y-m-d'), 'image_url' => '', 'description' => '',
];

/** Load one of this farmer's products (ownership enforced in SQL). */
$ownProduct = static function (int $id) use ($pdo, $farmerId): ?array {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? AND farmer_id = ?');
    $stmt->execute([$id, $farmerId]);
    return $stmt->fetch() ?: null;
};

if (is_post()) {
    csrf_check();
    $action = post('action');
    $id     = (int) post('product_id');

    if ($action === 'save') {
        $form = [
            'title'        => post('title'),
            'category_id'  => post('category_id'),
            'price'        => post('price'),
            'unit'         => post('unit'),
            'stock'        => post('stock'),
            'location'     => post('location'),
            'harvest_date' => post('harvest_date'),
            'image_url'    => post('image_url'),
            'description'  => post('description'),
        ];
        $categoryIds = array_map('intval', array_column($categories, 'id'));

        if (mb_strlen($form['title']) < 2 || mb_strlen($form['title']) > 150) {
            $errors[] = 'Enter a product title (2–150 characters).';
        }
        if (!in_array((int) $form['category_id'], $categoryIds, true)) {
            $errors[] = 'Choose a category.';
        }
        if (!is_numeric($form['price']) || (float) $form['price'] <= 0 || (float) $form['price'] > 10000000) {
            $errors[] = 'Enter a price greater than zero.';
        }
        if (mb_strlen($form['unit']) < 1 || mb_strlen($form['unit']) > 40) {
            $errors[] = 'Enter the unit of sale, e.g. kg or 90kg bag.';
        }
        if (!ctype_digit($form['stock']) || (int) $form['stock'] > 1000000) {
            $errors[] = 'Enter the stock available as a whole number.';
        }
        if (mb_strlen($form['location']) < 2 || mb_strlen($form['location']) > 80) {
            $errors[] = 'Enter the pickup location.';
        }
        $date = DateTime::createFromFormat('Y-m-d', $form['harvest_date']);
        if (!$date || $date->format('Y-m-d') !== $form['harvest_date'] || $date > new DateTime('today')) {
            $errors[] = 'Enter a valid harvest date that is not in the future.';
        }
        if (!valid_image_url($form['image_url'])) {
            $errors[] = 'The image link must start with http:// or https://.';
        }
        if (mb_strlen($form['description']) < 10 || mb_strlen($form['description']) > 2000) {
            $errors[] = 'Describe the produce in 10–2000 characters.';
        }
        if ($id > 0 && !$ownProduct($id)) {
            $errors[] = 'That listing does not belong to your farm.';
        }

        if (!$errors) {
            $values = [
                (int) $form['category_id'], $form['title'], $form['description'], (float) $form['price'],
                $form['unit'], (int) $form['stock'], $form['location'], $form['image_url'] ?: null, $form['harvest_date'],
            ];
            if ($id > 0) {
                $pdo->prepare(
                    'UPDATE products SET category_id = ?, title = ?, description = ?, price = ?, unit = ?,
                            stock = ?, location = ?, image_url = ?, harvest_date = ?
                     WHERE id = ? AND farmer_id = ?'
                )->execute([...$values, $id, $farmerId]);
                flash('success', 'Listing updated.');
            } else {
                $pdo->prepare(
                    'INSERT INTO products (category_id, title, description, price, unit, stock, location, image_url, harvest_date, farmer_id)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([...$values, $farmerId]);
                flash('success', 'Listing published.');
            }
            redirect('farmer.php');
        }
        $editing = $id > 0 ? $ownProduct($id) : null;

    } elseif ($action === 'toggle' && $ownProduct($id)) {
        $pdo->prepare('UPDATE products SET is_active = 1 - is_active WHERE id = ? AND farmer_id = ?')->execute([$id, $farmerId]);
        flash('success', 'Listing visibility changed.');
        redirect('farmer.php');

    } elseif ($action === 'delete' && $ownProduct($id)) {
        // Past order lines keep their copied title/price; product_id becomes NULL.
        $pdo->prepare('DELETE FROM products WHERE id = ? AND farmer_id = ?')->execute([$id, $farmerId]);
        flash('success', 'Listing deleted.');
        redirect('farmer.php');
    }
}

// Pre-fill the form when editing via ?edit=ID
if (!$errors && ($editId = (int) query('edit')) > 0) {
    if ($editing = $ownProduct($editId)) {
        $form = [
            'title' => $editing['title'], 'category_id' => (string) $editing['category_id'],
            'price' => rtrim(rtrim((string) $editing['price'], '0'), '.'), 'unit' => $editing['unit'],
            'stock' => (string) $editing['stock'], 'location' => $editing['location'],
            'harvest_date' => $editing['harvest_date'] ?? date('Y-m-d'),
            'image_url' => (string) $editing['image_url'], 'description' => $editing['description'],
        ];
    }
}

$stmt = $pdo->prepare(
    'SELECT p.*, c.name AS category FROM products p
     JOIN categories c ON c.id = p.category_id
     WHERE p.farmer_id = ? ORDER BY p.created_at DESC, p.id DESC'
);
$stmt->execute([$farmerId]);
$products = $stmt->fetchAll();

$stmt = $pdo->prepare(
    'SELECT oi.*, o.id AS order_id, o.status, o.created_at, o.address, o.phone, u.name AS buyer
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     JOIN users u  ON u.id = o.user_id
     WHERE oi.farmer_id = ? ORDER BY o.created_at DESC, oi.id DESC LIMIT 50'
);
$stmt->execute([$farmerId]);
$orderLines = $stmt->fetchAll();

$sales = 0.0;
foreach ($orderLines as $l) {
    if ($l['status'] !== 'cancelled') {
        $sales += (float) $l['unit_price'] * (int) $l['quantity'];
    }
}

$pageTitle = 'Farmer dashboard';
$active    = '';
require __DIR__ . '/header.php';
?>
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-10">
  <div class="flex flex-wrap items-start justify-between gap-4">
    <div>
      <h1 class="text-3xl font-bold"><?= e($farmer['farm_name']) ?></h1>
      <p class="text-sm text-gray-600 mt-1">
        <?= e($farmer['location']) ?> &middot;
        <?= $farmer['verified'] ? '<span class="text-green-700 font-semibold">Verified farmer</span>' : '<span class="text-yellow-700 font-semibold">Awaiting verification</span>' ?>
      </p>
    </div>
    <a class="btn btn-outline" href="farmer_profile.php?id=<?= $farmerId ?>"><i class="fa-solid fa-eye"></i> View public page</a>
  </div>

  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <div class="card p-5"><div class="text-xs uppercase tracking-wider text-gray-500">Listings</div><div class="text-2xl font-bold"><?= count($products) ?></div></div>
    <div class="card p-5"><div class="text-xs uppercase tracking-wider text-gray-500">Order lines (last 50)</div><div class="text-2xl font-bold"><?= count($orderLines) ?></div></div>
    <div class="card p-5"><div class="text-xs uppercase tracking-wider text-gray-500">Sales in those lines</div><div class="text-2xl font-bold"><?= e(money($sales)) ?></div></div>
  </div>

  <!-- Add / edit listing -->
  <div class="card p-6 sm:p-8" id="listing-form">
    <h2 class="text-xl font-bold mb-4"><?= $editing ? 'Edit listing' : 'Add a new listing' ?></h2>

    <?php if ($errors): ?>
      <ul class="mb-4 p-3 rounded-md bg-red-50 border border-red-300 text-red-700 text-sm list-disc list-inside">
        <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <form method="post" class="grid sm:grid-cols-2 gap-4">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="product_id" value="<?= $editing ? (int) $editing['id'] : 0 ?>">

      <div class="sm:col-span-2"><label class="label" for="title">Title</label>
        <input class="field" id="title" name="title" required maxlength="150" value="<?= e($form['title']) ?>" placeholder="e.g. Fresh Tomatoes"></div>

      <div><label class="label" for="category_id">Category</label>
        <select class="field" id="category_id" name="category_id" required>
          <option value="">Choose…</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= (string) $c['id'] === (string) $form['category_id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select></div>

      <div><label class="label" for="location">Pickup location</label>
        <input class="field" id="location" name="location" required maxlength="80" value="<?= e($form['location']) ?>"></div>

      <div><label class="label" for="price">Price (KES)</label>
        <input class="field" id="price" type="number" name="price" required min="1" step="0.01" value="<?= e($form['price']) ?>"></div>

      <div><label class="label" for="unit">Unit of sale</label>
        <input class="field" id="unit" name="unit" required maxlength="40" value="<?= e($form['unit']) ?>" placeholder="kg, litre, 90kg bag…"></div>

      <div><label class="label" for="stock">Stock available</label>
        <input class="field" id="stock" type="number" name="stock" required min="0" step="1" value="<?= e($form['stock']) ?>"></div>

      <div><label class="label" for="harvest_date">Harvest date</label>
        <input class="field" id="harvest_date" type="date" name="harvest_date" required max="<?= date('Y-m-d') ?>" value="<?= e($form['harvest_date']) ?>"></div>

      <div class="sm:col-span-2"><label class="label" for="image_url">Image link (optional)</label>
        <input class="field" id="image_url" type="url" name="image_url" maxlength="500" value="<?= e($form['image_url']) ?>" placeholder="https://…"></div>

      <div class="sm:col-span-2"><label class="label" for="description">Description</label>
        <textarea class="field" id="description" name="description" required maxlength="2000"><?= e($form['description']) ?></textarea></div>

      <div class="sm:col-span-2 flex gap-3">
        <button class="btn btn-primary" type="submit"><?= $editing ? 'Save changes' : 'Publish listing' ?></button>
        <?php if ($editing): ?><a class="btn btn-outline" href="farmer.php">Cancel</a><?php endif; ?>
      </div>
    </form>
  </div>

  <!-- My listings -->
  <div>
    <h2 class="text-xl font-bold mb-4">My listings</h2>
    <?php if (!$products): ?>
      <div class="card p-8 text-center text-gray-600">You have no listings yet. Add your first one above.</div>
    <?php else: ?>
      <div class="card overflow-x-auto">
        <table class="data-table">
          <thead><tr><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
          <tbody>
            <?php foreach ($products as $p): ?>
              <tr>
                <td class="font-semibold"><?= e($p['title']) ?></td>
                <td><?= e($p['category']) ?></td>
                <td><?= e(money($p['price'])) ?> / <?= e($p['unit']) ?></td>
                <td><?= (int) $p['stock'] ?></td>
                <td><?= $p['is_active'] ? '<span class="badge bg-green-100 text-green-800">Live</span>' : '<span class="badge bg-gray-200 text-gray-700">Hidden</span>' ?></td>
                <td class="text-right whitespace-nowrap">
                  <a class="btn btn-outline btn-sm" href="farmer.php?edit=<?= (int) $p['id'] ?>#listing-form">Edit</a>
                  <form method="post" class="inline">
                    <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
                    <button class="btn btn-outline btn-sm" type="submit"><?= $p['is_active'] ? 'Hide' : 'Show' ?></button>
                  </form>
                  <form method="post" class="inline" data-confirm="Delete this listing permanently?">
                    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
                    <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- Orders -->
  <div>
    <h2 class="text-xl font-bold mb-4">Recent orders for your produce</h2>
    <?php if (!$orderLines): ?>
      <div class="card p-8 text-center text-gray-600">No orders yet.</div>
    <?php else: ?>
      <div class="card overflow-x-auto">
        <table class="data-table">
          <thead><tr><th>Order</th><th>Date</th><th>Item</th><th>Qty</th><th>Amount</th><th>Buyer</th><th>Deliver to</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($orderLines as $l): ?>
              <tr>
                <td class="font-semibold">#<?= (int) $l['order_id'] ?></td>
                <td><?= e(format_date($l['created_at'])) ?></td>
                <td><?= e($l['title']) ?></td>
                <td><?= (int) $l['quantity'] ?> <span class="text-xs text-gray-500"><?= e($l['unit']) ?></span></td>
                <td class="font-semibold"><?= e(money((float) $l['unit_price'] * (int) $l['quantity'])) ?></td>
                <td><?= e($l['buyer']) ?><div class="text-xs text-gray-500"><?= e($l['phone']) ?></div></td>
                <td class="max-w-[14rem]"><?= e($l['address']) ?></td>
                <td><?= status_badge($l['status']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>
