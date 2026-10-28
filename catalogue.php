<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$filters    = catalogue_filters($_GET);
$result     = fetch_products($filters, $filters['page']);
$categories = all_categories();
$locations  = produce_locations();

$hasFilters = $filters['q'] !== '' || $filters['category'] !== '' || $filters['max_price'] > 0
    || $filters['location'] !== '' || $filters['sort'] !== 'latest';

$pageTitle = 'Produce Catalogue';
$active    = 'catalogue';
require __DIR__ . '/header.php';
?>
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
  <h1 class="text-3xl font-bold mb-6">Produce catalogue</h1>

  <form id="filter-form" method="get" action="catalogue.php"
        class="bg-brand-creamdark p-4 sm:p-6 rounded-xl border border-brand-cardborder mb-6 shadow-sm">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
      <div class="lg:col-span-2">
        <label class="label" for="q">Search produce</label>
        <div class="relative">
          <input class="field !pl-9" id="q" type="search" name="q" maxlength="80" value="<?= e($filters['q']) ?>"
                 placeholder="Search tomatoes, maize, a farm…">
          <i class="fa-solid fa-magnifying-glass absolute left-3 top-3.5 text-gray-400 text-sm"></i>
        </div>
      </div>

      <div>
        <label class="label" for="category">Category</label>
        <select class="field" id="category" name="category">
          <option value="">All categories</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= e($c['slug']) ?>" <?= $filters['category'] === $c['slug'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="label" for="max_price">Max price (KES)</label>
        <select class="field" id="max_price" name="max_price">
          <option value="">Any price</option>
          <?php foreach ([150, 300, 500, 1000] as $price): ?>
            <option value="<?= $price ?>" <?= $filters['max_price'] === $price ? 'selected' : '' ?>>Under KES <?= number_format($price) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="label" for="location">Location</label>
        <select class="field" id="location" name="location">
          <option value="">All locations</option>
          <?php foreach ($locations as $loc): ?>
            <option value="<?= e($loc) ?>" <?= $filters['location'] === $loc ? 'selected' : '' ?>><?= e($loc) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="mt-4 flex flex-wrap items-end justify-between gap-4">
      <div class="w-full sm:w-56">
        <label class="label" for="sort">Sort by</label>
        <select class="field" id="sort" name="sort">
          <option value="latest"     <?= $filters['sort'] === 'latest' ? 'selected' : '' ?>>Newest first</option>
          <option value="price_asc"  <?= $filters['sort'] === 'price_asc' ? 'selected' : '' ?>>Price: low to high</option>
          <option value="price_desc" <?= $filters['sort'] === 'price_desc' ? 'selected' : '' ?>>Price: high to low</option>
        </select>
      </div>
      <div class="flex gap-3">
        <button type="submit" class="btn btn-green">Apply filters</button>
        <?php if ($hasFilters): ?>
          <a href="catalogue.php" class="btn btn-outline">Clear</a>
        <?php endif; ?>
      </div>
    </div>
  </form>

  <p id="result-count" class="text-sm text-gray-600 mb-4" aria-live="polite">
    <?= $result['total'] ?> <?= $result['total'] === 1 ? 'listing' : 'listings' ?> found
  </p>

  <div id="catalogue-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
    <?php if ($result['items']): ?>
      <?php foreach ($result['items'] as $p) { echo product_card_html($p); } ?>
    <?php else: ?>
      <div class="col-span-full text-center py-12 bg-white rounded-xl border border-brand-cardborder">
        <i class="fa-solid fa-seedling text-4xl text-gray-400 mb-3"></i>
        <p class="text-gray-600 font-medium">No produce found matching your criteria.</p>
      </div>
    <?php endif; ?>
  </div>

  <div id="pagination"><?= pagination_html($result['page'], $result['pages'], $filters) ?></div>
</section>
<?php require __DIR__ . '/footer.php'; ?>
