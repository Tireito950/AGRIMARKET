<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$stats = db()->query(
    "SELECT (SELECT COUNT(*) FROM farmers) AS farmers,
            (SELECT COUNT(*) FROM products WHERE is_active = 1) AS listings,
            (SELECT COUNT(DISTINCT location) FROM products WHERE is_active = 1) AS regions"
)->fetch();

$pageTitle = 'About Us';
$active    = 'about';
require __DIR__ . '/header.php';
?>
<section class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
  <h1 class="text-3xl sm:text-4xl font-bold font-serif mb-4">About <?= e(APP_NAME) ?></h1>
  <p class="text-gray-700 leading-relaxed mb-4">
    <?= e(APP_NAME) ?> is a digital marketplace that connects smallholder farmers directly with buyers.
    By removing unnecessary middlemen, farmers earn fairer prices and buyers receive fresher, more affordable produce.
  </p>
  <p class="text-gray-700 leading-relaxed mb-10">
    Every listing shows who grew it, where it came from and when it was harvested, so you always know the story behind your food.
  </p>

  <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-12">
    <?php foreach ([['Farmers', $stats['farmers']], ['Active listings', $stats['listings']], ['Growing regions', $stats['regions']]] as [$label, $value]): ?>
      <div class="card p-6 text-center">
        <div class="text-3xl font-bold text-brand-orange"><?= (int) $value ?></div>
        <div class="text-sm text-gray-600 mt-1"><?= e($label) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <h2 class="text-2xl font-bold mb-4">What we stand for</h2>
  <ul class="space-y-3 text-gray-700">
    <li><i class="fa-solid fa-scale-balanced text-brand-orange mr-2"></i><strong>Fair prices</strong> – farmers set their own prices and keep the margin.</li>
    <li><i class="fa-solid fa-magnifying-glass-location text-brand-orange mr-2"></i><strong>Transparency</strong> – every product is traceable to a named farm.</li>
    <li><i class="fa-solid fa-truck-fast text-brand-orange mr-2"></i><strong>Freshness</strong> – direct delivery keeps produce on its way from field to table.</li>
  </ul>

  <div class="mt-10 flex flex-wrap gap-3">
    <a href="catalogue.php" class="btn btn-primary">Explore produce</a>
    <a href="farmers.php" class="btn btn-outline">Meet our farmers</a>
  </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>
