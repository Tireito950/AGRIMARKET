<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

if (current_user()) {
    redirect('index.php');
}

$errors = [];
$old = [
    'name' => '', 'email' => '', 'phone' => '',
    'role' => query('role') === 'farmer' ? 'farmer' : 'buyer',
    'farm_name' => '', 'location' => '',
];

if (is_post()) {
    csrf_check();
    $old = [
        'name'      => post('name'),
        'email'     => strtolower(post('email')),
        'phone'     => post('phone'),
        'role'      => post('role') === 'farmer' ? 'farmer' : 'buyer',
        'farm_name' => post('farm_name'),
        'location'  => post('location'),
    ];
    $password = $_POST['password'] ?? '';
    $password = is_string($password) ? $password : '';

    if (mb_strlen($old['name']) < 2 || mb_strlen($old['name']) > 100) {
        $errors[] = 'Enter your full name.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || strlen($old['email']) > 150) {
        $errors[] = 'Enter a valid email address.';
    }
    if (!valid_phone($old['phone'])) {
        $errors[] = 'Enter a valid phone number, e.g. +254 700 000000.';
    }
    if ($msg = valid_password($password)) {
        $errors[] = $msg;
    }
    if ($old['role'] === 'farmer') {
        if (mb_strlen($old['farm_name']) < 2 || mb_strlen($old['farm_name']) > 120) {
            $errors[] = 'Enter your farm name.';
        }
        if (mb_strlen($old['location']) < 2 || mb_strlen($old['location']) > 80) {
            $errors[] = 'Enter your farm location (town or county).';
        }
    }

    if (!$errors) {
        $pdo = db();
        $exists = $pdo->prepare('SELECT 1 FROM users WHERE email = ?');
        $exists->execute([$old['email']]);

        if ($exists->fetchColumn()) {
            $errors[] = 'An account with that email already exists. Try logging in.';
        } else {
            try {
                $pdo->beginTransaction();
                $pdo->prepare('INSERT INTO users (name, email, password_hash, phone, role) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$old['name'], $old['email'], password_hash($password, PASSWORD_DEFAULT), $old['phone'], $old['role']]);
                $userId = (int) $pdo->lastInsertId();

                if ($old['role'] === 'farmer') {
                    $pdo->prepare('INSERT INTO farmers (user_id, farm_name, location) VALUES (?, ?, ?)')
                        ->execute([$userId, $old['farm_name'], $old['location']]);
                }
                $pdo->commit();

                $cart = $_SESSION['cart'] ?? [];
                login_user($userId);
                $_SESSION['cart'] = $cart;

                flash('success', 'Account created. Welcome to ' . APP_NAME . '!');
                redirect($old['role'] === 'farmer' ? 'farmer.php' : 'catalogue.php');
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Registration failed: ' . $ex->getMessage());
                $errors[] = 'We could not create your account. Please try again.';
            }
        }
    }
}

$pageTitle = 'Create an account';
$active    = '';
require __DIR__ . '/header.php';
?>
<section class="py-16 px-4">
  <div class="max-w-lg mx-auto card p-8 sm:p-10">
    <h1 class="text-2xl font-bold text-center mb-2">Create an account</h1>
    <p class="text-sm text-center text-gray-500 mb-6">Join <?= e(APP_NAME) ?> as a buyer or registered farmer.</p>

    <?php if ($errors): ?>
      <ul class="mb-4 p-3 rounded-md bg-red-50 border border-red-300 text-red-700 text-sm list-disc list-inside">
        <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <form method="post" class="space-y-4">
      <?= csrf_field() ?>

      <div><label class="label" for="name">Full name</label>
        <input class="field" id="name" name="name" required maxlength="100" autocomplete="name" placeholder="Jane Doe" value="<?= e($old['name']) ?>"></div>

      <div><label class="label" for="email">Email</label>
        <input class="field" id="email" type="email" name="email" required maxlength="150" autocomplete="email" placeholder="jane@example.com" value="<?= e($old['email']) ?>"></div>

      <div><label class="label" for="password">Password</label>
        <input class="field" id="password" type="password" name="password" required minlength="8" autocomplete="new-password" placeholder="At least 8 characters, letters and numbers"></div>

      <fieldset>
        <legend class="label">I am a</legend>
        <div class="grid grid-cols-2 gap-3">
          <label class="role-option relative">
            <input type="radio" name="role" value="buyer" <?= $old['role'] === 'buyer' ? 'checked' : '' ?>><span>Buyer</span>
          </label>
          <label class="role-option relative">
            <input type="radio" name="role" value="farmer" <?= $old['role'] === 'farmer' ? 'checked' : '' ?>><span>Farmer</span>
          </label>
        </div>
      </fieldset>

      <div id="farmer-fields" class="space-y-4 <?= $old['role'] === 'farmer' ? '' : 'hidden' ?>">
        <div><label class="label" for="farm_name">Farm name</label>
          <input class="field" id="farm_name" name="farm_name" maxlength="120" value="<?= e($old['farm_name']) ?>"></div>
        <div><label class="label" for="location">Farm location</label>
          <input class="field" id="location" name="location" maxlength="80" placeholder="e.g. Nakuru" value="<?= e($old['location']) ?>"></div>
      </div>

      <div><label class="label" for="phone">Phone number</label>
        <input class="field" id="phone" type="tel" name="phone" required autocomplete="tel" placeholder="+254 700 000000" value="<?= e($old['phone']) ?>"></div>

      <button type="submit" class="btn btn-primary w-full py-3.5 text-base mt-2">Register</button>
    </form>

    <p class="mt-6 text-center text-sm text-gray-600">
      Already have an account?
      <a href="login.php" class="text-brand-orange font-semibold hover:underline">Log in</a>
    </p>
  </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>
