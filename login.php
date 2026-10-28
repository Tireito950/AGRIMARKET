<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$next = safe_next(query('next') ?: post('next'), 'index.php');

if (current_user()) {
    redirect($next);
}

const MAX_ATTEMPTS = 5;
const LOCKOUT_SECS = 300;

$errors = [];
$email  = '';

// Basic brute-force throttle, tracked per session.
$attempts = $_SESSION['login_attempts'] ?? ['count' => 0, 'since' => time()];
if (time() - $attempts['since'] > LOCKOUT_SECS) {
    $attempts = ['count' => 0, 'since' => time()];
}
$locked = $attempts['count'] >= MAX_ATTEMPTS;

if (is_post()) {
    csrf_check();
    $email    = strtolower(post('email'));
    $password = $_POST['password'] ?? '';

    if ($locked) {
        $errors[] = 'Too many failed attempts. Please wait a few minutes and try again.';
    } else {
        $stmt = db()->prepare('SELECT id, password_hash, role FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        if ($row && is_string($password) && password_verify($password, $row['password_hash'])) {
            if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $row['id']]);
            }
            unset($_SESSION['login_attempts']);
            $cart = $_SESSION['cart'] ?? [];          // keep the cart across login
            login_user((int) $row['id']);
            $_SESSION['cart'] = $cart;

            $default = match ($row['role']) {
                'admin'  => 'admin.php',
                'farmer' => 'farmer.php',
                default  => 'catalogue.php',
            };
            flash('success', 'Welcome back!');
            redirect($next !== 'index.php' ? $next : $default);
        }

        $attempts['count']++;
        $_SESSION['login_attempts'] = $attempts;
        $errors[] = 'Incorrect email or password.';
    }
}

$pageTitle = 'Log in';
$active    = '';
require __DIR__ . '/header.php';
?>
<section class="py-16 px-4">
  <div class="max-w-md mx-auto card p-8 sm:p-10">
    <h1 class="text-2xl font-bold text-center mb-2">Log in</h1>
    <p class="text-sm text-center text-gray-500 mb-6">Welcome back! Access your <?= e(APP_NAME) ?> account.</p>

    <?php if ($errors): ?>
      <ul class="mb-4 p-3 rounded-md bg-red-50 border border-red-300 text-red-700 text-sm list-disc list-inside">
        <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <form method="post" class="space-y-5">
      <?= csrf_field() ?>
      <input type="hidden" name="next" value="<?= e($next) ?>">

      <div>
        <label class="label" for="email">Email address</label>
        <input class="field" id="email" type="email" name="email" required autocomplete="email" placeholder="you@example.com" value="<?= e($email) ?>">
      </div>
      <div>
        <label class="label" for="password">Password</label>
        <input class="field" id="password" type="password" name="password" required autocomplete="current-password" placeholder="••••••••">
      </div>

      <button type="submit" class="btn btn-primary w-full py-3.5 text-base" <?= $locked ? 'disabled' : '' ?>>Log in</button>
    </form>

    <p class="mt-6 text-center text-sm text-gray-600">
      Don&rsquo;t have an account?
      <a href="register.php" class="text-brand-orange font-semibold hover:underline">Create an account</a>
    </p>
  </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>
