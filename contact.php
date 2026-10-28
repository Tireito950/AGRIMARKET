<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$user   = current_user();
$errors = [];
$old    = [
    'name'    => $user['name']  ?? '',
    'email'   => $user['email'] ?? '',
    'subject' => '',
    'message' => '',
];

if (is_post()) {
    csrf_check();
    $old = [
        'name'    => post('name'),
        'email'   => strtolower(post('email')),
        'subject' => post('subject'),
        'message' => post('message'),
    ];

    if (mb_strlen($old['name']) < 2 || mb_strlen($old['name']) > 100) {
        $errors[] = 'Please enter your name.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (mb_strlen($old['subject']) < 3 || mb_strlen($old['subject']) > 150) {
        $errors[] = 'Please enter a subject (3–150 characters).';
    }
    if (mb_strlen($old['message']) < 10 || mb_strlen($old['message']) > 2000) {
        $errors[] = 'Your message must be between 10 and 2000 characters.';
    }

    if (!$errors) {
        $stmt = db()->prepare('INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)');
        $stmt->execute([$old['name'], $old['email'], $old['subject'], $old['message']]);
        flash('success', 'Thank you! Your message has been sent and we will reply soon.');
        redirect('contact.php');
    }
}

$pageTitle = 'Contact';
$active    = 'contact';
require __DIR__ . '/header.php';
?>
<section class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
  <h1 class="text-3xl font-bold font-serif mb-2">Get in touch</h1>
  <p class="text-gray-600 mb-8">Questions about an order, or want to list your produce? We would love to hear from you.</p>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
    <aside class="space-y-4 text-sm">
      <div class="card p-5">
        <div class="font-semibold mb-1"><i class="fa-solid fa-envelope text-brand-orange mr-2"></i>Email</div>
        <a class="text-gray-700 hover:underline" href="mailto:<?= e(SUPPORT_EMAIL) ?>"><?= e(SUPPORT_EMAIL) ?></a>
      </div>
      <div class="card p-5">
        <div class="font-semibold mb-1"><i class="fa-solid fa-phone text-brand-orange mr-2"></i>Phone</div>
        <a class="text-gray-700 hover:underline" href="tel:<?= e(preg_replace('/\s+/', '', SUPPORT_PHONE)) ?>"><?= e(SUPPORT_PHONE) ?></a>
      </div>
    </aside>

    <div class="md:col-span-2 card p-6 sm:p-8">
      <?php if ($errors): ?>
        <ul class="mb-4 p-3 rounded-md bg-red-50 border border-red-300 text-red-700 text-sm list-disc list-inside">
          <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <form method="post" class="space-y-4" novalidate>
        <?= csrf_field() ?>
        <div class="grid sm:grid-cols-2 gap-4">
          <div><label class="label" for="name">Name</label>
            <input class="field" id="name" name="name" required maxlength="100" value="<?= e($old['name']) ?>"></div>
          <div><label class="label" for="email">Email</label>
            <input class="field" id="email" type="email" name="email" required maxlength="150" value="<?= e($old['email']) ?>"></div>
        </div>
        <div><label class="label" for="subject">Subject</label>
          <input class="field" id="subject" name="subject" required maxlength="150" value="<?= e($old['subject']) ?>"></div>
        <div><label class="label" for="message">Message</label>
          <textarea class="field" id="message" name="message" required maxlength="2000"><?= e($old['message']) ?></textarea></div>
        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-paper-plane"></i> Send message</button>
      </form>
    </div>
  </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>
