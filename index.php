<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$latest = fetch_products([], 1, 4)['items'];

$pageTitle = APP_TAGLINE;
$active    = 'home';
require __DIR__ . '/header.php';
?>
<!-- Hero -->
 
<section class=" hero-section bg-brand-green text-white py-20 px-4 sm:px-6 lg:px-8 relative overflow-hidden">
  <div class="max-w-4xl mx-auto text-center relative z-10">
    <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight font-serif mb-6 leading-tight">
      <?= e(APP_TAGLINE) ?>
    </h1>
    <p class="text-lg sm:text-xl text-gray-200 mb-8 max-w-2xl mx-auto font-light">
      Buy directly from smallholder farmers. Fresh produce, fair prices, and transparent supply chains for everyone.
    </p>
    <div class="flex flex-col sm:flex-row justify-center gap-4">
      <a href="catalogue.php" class="btn btn-primary px-8 py-3.5 text-base">Explore produce</a>
      <a href="register.php?role=farmer" class="btn px-8 py-3.5 text-base border-2 border-white text-white hover:bg-white hover:text-brand-green">Join as a farmer</a>
    </div>
  </div>
  <div class="absolute inset-0 opacity-10 flex items-center justify-center pointer-events-none" aria-hidden="true">
    <i class="fa-solid fa-wheat-awn text-[300px]"></i>
  </div>
</section>


<!-- Latest listings -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
  <div class="flex items-center justify-between mb-8">
    <h2 class="text-2xl sm:text-3xl font-bold">Latest listings</h2>
    <a href="catalogue.php" class="text-brand-orange hover:underline font-semibold text-sm flex items-center space-x-1">
      <span>View all produce</span> <i class="fa-solid fa-arrow-right text-xs"></i>
    </a>
  </div>

  <?php if ($latest): ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
      <?php foreach ($latest as $p) { echo product_card_html($p); } ?>
    </div>
  <?php else: ?>
    <p class="text-gray-600">No produce has been listed yet. Check back soon.</p>
  <?php endif; ?>
</section>

<!-- How it works -->
<section class="bg-brand-creamdark py-16 border-y border-brand-cardborder">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <h2 class="text-2xl sm:text-3xl font-bold text-center mb-12">How it works</h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
      <?php
      $steps = [
          ['Explore &amp; select',   'Browse live listings of fresh fruits, vegetables and farm goods from registered farmers.'],
          ['Place your order',       'Choose your quantities and check out. Pay with M-Pesa or cash when your order arrives.'],
          ['Direct delivery',        'Receive transparent farm-to-table delivery, with every order traceable to the farmer who grew it.'],
      ];
      foreach ($steps as $i => [$title, $text]): ?>
        <div class="bg-brand-cream p-6 rounded-xl border border-brand-cardborder shadow-sm flex flex-col items-center text-center">
          <div class="w-12 h-12 rounded-full bg-brand-orange text-white font-bold text-xl flex items-center justify-center mb-4"><?= $i + 1 ?></div>
          <h3 class="text-lg font-bold mb-2"><?= $title ?></h3>
          <p class="text-gray-600 text-sm"><?= e($text) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>
