<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/*
 * Shared page header. Set these before requiring it:
 *   $pageTitle – text for <title>
 *   $active    – key of the highlighted nav link (home, about, catalogue, farmers, contact)
 */
$pageTitle = $pageTitle ?? APP_TAGLINE;
$active    = $active ?? '';
$user      = current_user();
$cartCount = cart_count();

$navLinks = [
    'home'      => ['index.php',     'Home'],
    'about'     => ['about.php',     'About Us'],
    'catalogue' => ['catalogue.php', 'Produce Catalogue'],
    'farmers'   => ['farmers.php',   'Farmers'],
    'contact'   => ['contact.php',   'Contact'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
  <meta name="description" content="Buy fresh produce directly from smallholder farmers in Kenya. Fair prices, transparent supply chains and direct delivery.">

  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">

  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: {
              green: '#244b36', darkgreen: '#1b3829', lightgreen: '#336148',
              orange: '#d96b27', orangehover: '#c45c1b',
              cream: '#faf6ed', creamdark: '#f0e8d5', cardbg: '#eae3d2', cardborder: '#dcd3be'
            }
          },
          fontFamily: { sans: ['Inter', 'sans-serif'], serif: ['Playfair Display', 'serif'] }
        }
      }
    };
  </script>
  <link rel="stylesheet" href="assets/style.css">
  <script defer src="assets/app.js"></script>
</head>
<body class="min-h-screen flex flex-col selection:bg-brand-orange selection:text-white">

<header class="bg-brand-green text-white sticky top-0 z-50 shadow-md no-print">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between h-20">

      <a href="index.php" class="flex items-center space-x-3">
        <span class="w-10 h-10 rounded-full bg-brand-orange flex items-center justify-center text-white text-xl shadow-sm">
          <i class="fa-solid fa-"></i>
        </span>
        <span class="text-2xl font-bold tracking-tight font-serif"><?= e(APP_NAME) ?></span>
      </a>

      <nav class="hidden md:flex space-x-8 text-sm font-medium" aria-label="Main">
        <?php foreach ($navLinks as $key => [$href, $label]): ?>
          <a href="<?= e($href) ?>" class="nav-link text-gray-200 hover:text-white py-2 transition-colors<?= $active === $key ? ' active' : '' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
      </nav>

      <div class="flex items-center space-x-3">
        <a href="cart.php" class="relative p-2 text-gray-200 hover:text-white transition-colors" title="Shopping cart" aria-label="Shopping cart">
          <i class="fa-solid fa-cart-shopping text-xl"></i>
          <span id="cart-count-badge" class="absolute -top-1 -right-1 bg-brand-orange text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center border-2 border-brand-green"><?= $cartCount ?></span>
        </a>

        <?php if ($user): ?>
          <details class="user-menu relative">
            <summary class="flex items-center space-x-2 cursor-pointer select-none text-sm font-medium px-2 py-2">
              <i class="fa-solid fa-circle-user text-xl"></i>
              <span class="hidden sm:inline"><?= e(strtok($user['name'], ' ')) ?></span>
              <i class="fa-solid fa-chevron-down text-xs"></i>
            </summary>
            <div class="absolute right-0 mt-2 w-52 bg-white text-brand-green rounded-lg shadow-lg border border-brand-cardborder py-2 text-sm">
              <?php if ($user['role'] === 'admin'): ?>
                <a class="block px-4 py-2 hover:bg-brand-cream" href="admin.php">Admin dashboard</a>
              <?php elseif ($user['role'] === 'farmer'): ?>
                <a class="block px-4 py-2 hover:bg-brand-cream" href="farmer.php">Farmer dashboard</a>
              <?php endif; ?>
              <a class="block px-4 py-2 hover:bg-brand-cream" href="orders.php">My orders</a>
              <a class="block px-4 py-2 hover:bg-brand-cream" href="profile.php">My profile</a>
              <form method="post" action="logout.php" class="border-t border-brand-cardborder mt-1 pt-1">
                <?= csrf_field() ?>
                <button type="submit" class="w-full text-left px-4 py-2 hover:bg-brand-cream">Log out</button>
              </form>
            </div>
          </details>
        <?php else: ?>
          <a href="login.php" class="hidden sm:inline-block px-3 py-2 text-sm font-medium hover:text-brand-orange transition-colors">Log in</a>
          <a href="register.php" class="px-4 py-2 text-sm font-semibold text-brand-green bg-brand-cream hover:bg-white rounded-md shadow-sm transition-all">Register</a>
        <?php endif; ?>

        <button id="menu-toggle" type="button" class="md:hidden p-2 text-gray-200 hover:text-white" aria-label="Open menu" aria-expanded="false" aria-controls="mobile-menu">
          <i class="fa-solid fa-bars text-xl"></i>
        </button>
      </div>
    </div>
  </div>

  <nav id="mobile-menu" class="hidden md:hidden border-t border-brand-lightgreen px-4 py-3 space-y-1 text-sm" aria-label="Mobile">
    <?php foreach ($navLinks as $key => [$href, $label]): ?>
      <a href="<?= e($href) ?>" class="block px-2 py-2 rounded <?= $active === $key ? 'bg-brand-lightgreen font-semibold' : 'hover:bg-brand-lightgreen' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
    <?php if (!$user): ?>
      <a href="login.php" class="block px-2 py-2 rounded hover:bg-brand-lightgreen">Log in</a>
    <?php endif; ?>
  </nav>
</header>

<main class="flex-grow">
  <?php $flashes = take_flashes(); if ($flashes): ?>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 space-y-2" id="flash-region">
      <?php foreach ($flashes as $f):
          $styles = [
              'success' => 'bg-green-50 border-green-300 text-green-800',
              'error'   => 'bg-red-50 border-red-300 text-red-700',
              'info'    => 'bg-blue-50 border-blue-300 text-blue-800',
          ];
          $style = $styles[$f['type']] ?? $styles['info']; ?>
        <div role="status" class="flash border rounded-lg px-4 py-3 text-sm <?= $style ?>"><?= e($f['message']) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
