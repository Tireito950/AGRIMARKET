<?php
declare(strict_types=1);
/* Shared page footer – closes the <main> element opened in header.php. */
?>
</main>

<footer class="bg-brand-darkgreen text-gray-300 py-8 border-t border-brand-lightgreen mt-16 no-print">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-xs space-y-3">
    <div class="flex flex-wrap justify-center gap-x-6 gap-y-2 text-sm">
      <a href="index.php" class="hover:text-white">Home</a>
      <a href="about.php" class="hover:text-white">About Us</a>
      <a href="catalogue.php" class="hover:text-white">Produce Catalogue</a>
      <a href="farmers.php" class="hover:text-white">Farmers</a>
      <a href="contact.php" class="hover:text-white">Contact</a>
    </div>
    <p>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. Connecting smallholder farmers to buyers.</p>
    <p class="text-gray-400">Fresh produce &bull; Fair prices &bull; Direct delivery</p>
  </div>
</footer>

</body>
</html>
