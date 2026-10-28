<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

// Logging out changes state, so it is only accepted as a CSRF-protected POST.
if (!is_post()) {
    redirect('index.php');
}

csrf_check();
logout_user();

session_start();
flash('info', 'You have been logged out.');
redirect('index.php');
