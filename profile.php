<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$user   = require_login();
$farmer = $user['role'] === 'farmer' ? farmer_for_user((int) $user['id']) : null;

$errors = ['profile' => [], 'password' => []];

if (is_post()) {
    csrf_check();
    $action = post('action');

    if ($action === 'profile') {
        $name  = post('name');
        $phone = post('phone');

        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors['profile'][] = 'Enter your full name.';
        }
        if (!valid_phone($phone)) {
            $errors['profile'][] = 'Enter a valid phone number.';
        }

        $farmName = $location = $bio = '';
        if ($farmer) {
            $farmName = post('farm_name');
            $location = post('location');
            $bio      = post('bio');
            if (mb_strlen($farmName) < 2 || mb_strlen($farmName) > 120) {
                $errors['profile'][] = 'Enter your farm name.';
            }
            if (mb_strlen($location) < 2 || mb_strlen($location) > 80) {
                $errors['profile'][] = 'Enter your farm location.';
            }
            if (mb_strlen($bio) > 1000) {
                $errors['profile'][] = 'Your farm description must be 1000 characters or fewer.';
            }
        }

        if (!$errors['profile']) {
            $pdo = db();
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE users SET name = ?, phone = ? WHERE id = ?')->execute([$name, $phone, $user['id']]);
            if ($farmer) {
                $pdo->prepare('UPDATE farmers SET farm_name = ?, location = ?, bio = ? WHERE id = ?')
                    ->execute([$farmName, $location, $bio ?: null, $farmer['id']]);
            }
            $pdo->commit();
            flash('success', 'Your profile has been updated.');
            redirect('profile.php');
        }
    }

    if ($action === 'password') {
        $current = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
        $new     = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';

        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $hash = (string) $stmt->fetchColumn();

        if (!password_verify($current, $hash)) {
            $errors['password'][] = 'Your current password is incorrect.';
        }
        if ($msg = valid_password($new)) {
            $errors['password'][] = $msg;
        }

        if (!$errors['password']) {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            session_regenerate_id(true);
            flash('success', 'Your password has been changed.');
            redirect('profile.php');
        }
    }
}

// Re-read so the form reflects what is stored (and what was just saved).
$user   = current_user(true);
$farmer = $farmer ? farmer_for_user((int) $user['id']) : null;

$pageTitle = 'My profile';
$active    = '';
require __DIR__ . '/header.php';

$errorList = static function (array $list): void {
    if (!$list) { return; }
    echo '<ul class="mb-4 p-3 rounded-md bg-red-50 border border-red-300 text-red-700 text-sm list-disc list-inside">';
    foreach ($list as $err) { echo '<li>' . e($err) . '</li>'; }
    echo '</ul>';
};
?>
<section class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8">
  <h1 class="text-3xl font-bold">My profile</h1>

  <form method="post" class="card p-6 sm:p-8 space-y-4">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="profile">
    <h2 class="text-xl font-bold">Account details</h2>
    <?php $errorList($errors['profile']); ?>

    <div class="grid sm:grid-cols-2 gap-4">
      <div><label class="label" for="name">Full name</label>
        <input class="field" id="name" name="name" required maxlength="100" value="<?= e($_POST['name'] ?? $user['name']) ?>"></div>
      <div><label class="label" for="phone">Phone</label>
        <input class="field" id="phone" type="tel" name="phone" required value="<?= e($_POST['phone'] ?? $user['phone']) ?>"></div>
    </div>
    <div><label class="label" for="email">Email</label>
      <input class="field opacity-70" id="email" value="<?= e($user['email']) ?>" disabled>
      <p class="text-xs text-gray-500 mt-1">Contact support to change your email address.</p></div>

    <?php if ($farmer): ?>
      <hr class="border-brand-cardborder">
      <h3 class="font-bold">Farm details</h3>
      <div class="grid sm:grid-cols-2 gap-4">
        <div><label class="label" for="farm_name">Farm name</label>
          <input class="field" id="farm_name" name="farm_name" required maxlength="120" value="<?= e($_POST['farm_name'] ?? $farmer['farm_name']) ?>"></div>
        <div><label class="label" for="location">Location</label>
          <input class="field" id="location" name="location" required maxlength="80" value="<?= e($_POST['location'] ?? $farmer['location']) ?>"></div>
      </div>
      <div><label class="label" for="bio">About your farm</label>
        <textarea class="field" id="bio" name="bio" maxlength="1000"><?= e($_POST['bio'] ?? $farmer['bio'] ?? '') ?></textarea></div>
    <?php endif; ?>

    <button class="btn btn-primary" type="submit">Save changes</button>
  </form>

  <form method="post" class="card p-6 sm:p-8 space-y-4">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="password">
    <h2 class="text-xl font-bold">Change password</h2>
    <?php $errorList($errors['password']); ?>

    <div><label class="label" for="current_password">Current password</label>
      <input class="field" id="current_password" type="password" name="current_password" required autocomplete="current-password"></div>
    <div><label class="label" for="new_password">New password</label>
      <input class="field" id="new_password" type="password" name="new_password" required minlength="8" autocomplete="new-password"></div>

    <button class="btn btn-green" type="submit">Update password</button>
  </form>
</section>
<?php require __DIR__ . '/footer.php'; ?>
