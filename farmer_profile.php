<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$id   = (int) query('id');
$stmt = db()->prepare('SELECT * FROM farmers WHERE id = ?');
$stmt->execute([$id]);
$farmer = $stmt->fetch();

if (!$farmer) {
    not_found('That farmer could not be found.');
}

$products = fetch_products(['farmer_id' => $id], 1, 24)['items'];

$pageTitle = $farmer['farm_name'];
$active    = 'farmers';
require __DIR__ . '/header.php';
?>
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
  <a href="farmers.php" class="mb-6 inline-flex items-center space-x-2 font-semibold text-sm hover:underline">
    <i class="fa-solid fa-arrow-left"></i><span>All farmers</span>
  </a>

  <div class="card p-6 sm:p-8 mb-10">
    <div class="flex flex-wrap items-center gap-3 mb-2">
      <h1 class="text-3xl font-bold font-serif"><?= e($farmer['farm_name']) ?></h1>
      <?php if ($farmer['verified']): ?>
        <span class="badge bg-green-100 text-green-800"><i class="fa-solid fa-circle-check mr-1"></i>Verified farmer</span>
      <?php endif; ?>
    </div>
    <p class="text-sm text-gray-500 mb-4"><i class="fa-solid  fa-location-dot text-brand-orange mr-1"></i><?= e($farmer['location']) ?>
      &middot; Member since <?= e(format_date($farmer['created_at'], 'M Y')) ?></p>
    <p class="text-gray-700 leading-relaxed"><?= nl2br(e($farmer['bio'] ?: 'This farm sells fresh produce directly to buyers through ' . APP_NAME . '.')) ?></p>
  </div>

  <h2 class="text-2xl font-bold mb-6">Produce from this farm</h2>
  <?php if ($products): ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
      <?php foreach ($products as $p) { echo product_card_html($p); } ?>
    </div>
  <?php else: ?>
    <div class="card p-10 text-center text-gray-600">This farm has no active listings right now.</div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/footer.php'; ?>
