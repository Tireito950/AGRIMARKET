<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/* =====================================================================
 * Database
 * ===================================================================== */

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $ex) {
            error_log('AgriMarket DB connection failed: ' . $ex->getMessage());
            http_response_code(500);
            exit('Database connection failed. Check the settings in config.php.');
        }
    }
    return $pdo;
}

/* =====================================================================
 * Request / output helpers
 * ===================================================================== */

/** Escape a value for safe HTML output. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Trimmed string from $_POST. */
function post(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

/** Trimmed string from $_GET. */
function query(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

/** Only allow redirects to local pages such as "product.php?id=3". */
function safe_next(?string $next, string $fallback = 'index.php'): string
{
    if ($next !== null && preg_match('/^[a-z_]+\.php(\?[A-Za-z0-9_=&%.\-]*)?$/', $next)) {
        return $next;
    }
    return $fallback;
}

function not_found(string $message = 'The page you are looking for does not exist.'): never
{
    http_response_code(404);
    $pageTitle = 'Not found';
    $active    = '';
    require __DIR__ . '/header.php';
    echo '<section class="max-w-xl mx-auto px-4 py-20 text-center">'
        . '<i class="fa-solid fa-seedling text-5xl text-gray-400 mb-4"></i>'
        . '<h1 class="text-3xl font-bold mb-3">Nothing here</h1>'
        . '<p class="text-gray-600 mb-6">' . e($message) . '</p>'
        . '<a class="btn btn-primary" href="catalogue.php">Browse produce</a>'
        . '</section>';
    require __DIR__ . '/footer.php';
    exit;
}

/* =====================================================================
 * CSRF protection and flash messages
 * ===================================================================== */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('Your session expired or the form was invalid. Please go back, refresh and try again.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Return and clear queued flash messages. */
function take_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/* =====================================================================
 * Authentication and authorisation
 * ===================================================================== */

function current_user(bool $refresh = false): ?array
{
    static $user = false;
    if ($refresh) {
        $user = false;
    }
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            $stmt = db()->prepare('SELECT id, name, email, phone, role FROM users WHERE id = ?');
            $stmt->execute([(int) $_SESSION['user_id']]);
            $user = $stmt->fetch() ?: null;
            if ($user === null) {
                unset($_SESSION['user_id']);
            }
        }
    }
    return $user;
}

function login_user(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    current_user(true);
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        $next = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
        if (!empty($_SERVER['QUERY_STRING'])) {
            $next .= '?' . $_SERVER['QUERY_STRING'];
        }
        flash('info', 'Please log in to continue.');
        redirect('login.php?next=' . urlencode($next));
    }
    return $user;
}

function require_role(string ...$roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        $pageTitle = 'Access denied';
        $active    = '';
        require __DIR__ . '/header.php';
        echo '<section class="max-w-xl mx-auto px-4 py-20 text-center">'
            . '<i class="fa-solid fa-lock text-5xl text-gray-400 mb-4"></i>'
            . '<h1 class="text-3xl font-bold mb-3">Access denied</h1>'
            . '<p class="text-gray-600 mb-6">Your account does not have permission to view this page.</p>'
            . '<a class="btn btn-primary" href="index.php">Back to home</a>'
            . '</section>';
        require __DIR__ . '/footer.php';
        exit;
    }
    return $user;
}

function farmer_for_user(int $userId): ?array
{
    $stmt = db()->prepare('SELECT * FROM farmers WHERE user_id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}

/* =====================================================================
 * Formatting
 * ===================================================================== */

function money(float|int|string $amount): string
{
    $amount = (float) $amount;
    return CURRENCY . ' ' . number_format($amount, floor($amount) == $amount ? 0 : 2);
}

function format_date(?string $date, string $format = 'j M Y'): string
{
    return $date ? date($format, strtotime($date)) : '—';
}

function product_image(?string $url): string
{
     return $url ?: '';//'https://images.unsplash.com/photo-1488459716781-31db52582fe9?auto=format&fit=crop&w=600&q=80'//
            //'https://images.unsplash.com/photo-1506806732259-39c2d0268443?auto=format&fit=crop&w=600&q=80'//;

}

function status_badge(string $status): string
{
    $classes = [
        'pending'   => 'bg-yellow-100 text-yellow-800',
        'confirmed' => 'bg-blue-100 text-blue-800',
        'delivered' => 'bg-green-100 text-green-800',
        'cancelled' => 'bg-red-100 text-red-800',
        'paid'      => 'bg-green-100 text-green-800',
    ];
    $class = $classes[$status] ?? 'bg-gray-100 text-gray-700';
    return '<span class="badge ' . $class . '">' . e(ucfirst($status)) . '</span>';
}

function payment_label(string $method): string
{
    return $method === 'mpesa' ? 'M-Pesa on delivery' : 'Cash on delivery';
}

/* =====================================================================
 * Catalogue
 * ===================================================================== */

function all_categories(): array
{
    return db()->query('SELECT id, name, slug FROM categories ORDER BY id')->fetchAll();
}

function produce_locations(): array
{
    return db()->query('SELECT DISTINCT location FROM products WHERE is_active = 1 ORDER BY location')
        ->fetchAll(PDO::FETCH_COLUMN);
}

/** Normalise catalogue filters coming from a query string. */
function catalogue_filters(array $src): array
{
    $str = static fn(string $k): string => isset($src[$k]) && is_string($src[$k]) ? trim($src[$k]) : '';
    $sort = $str('sort');

    return [
        'q'         => mb_substr($str('q'), 0, 80),
        'category'  => preg_match('/^[a-z0-9-]{1,60}$/', $str('category')) ? $str('category') : '',
        'max_price' => ctype_digit($str('max_price')) ? (int) $str('max_price') : 0,
        'location'  => mb_substr($str('location'), 0, 80),
        'sort'      => in_array($sort, ['price_asc', 'price_desc'], true) ? $sort : 'latest',
        'page'      => max(1, (int) ($src['page'] ?? 1)),
    ];
}

/**
 * Fetch a page of active products.
 *
 * Supported filters: q, category (slug), max_price, location,
 * farmer_id, exclude_id, sort (latest|price_asc|price_desc).
 *
 * @return array{items: array, total: int, page: int, pages: int}
 */
function fetch_products(array $f = [], int $page = 1, int $perPage = PER_PAGE): array
{
    $where  = ['p.is_active = 1'];
    $params = [];

    if (($f['q'] ?? '') !== '') {
        $like     = '%' . addcslashes($f['q'], '%_\\') . '%';
        $where[]  = '(p.title LIKE :q1 OR f.farm_name LIKE :q2 OR p.description LIKE :q3)';
        $params[':q1'] = $params[':q2'] = $params[':q3'] = $like;
    }
    if (($f['category'] ?? '') !== '') {
        $where[] = 'c.slug = :category';
        $params[':category'] = $f['category'];
    }
    if (($f['max_price'] ?? 0) > 0) {
        $where[] = 'p.price <= :max_price';
        $params[':max_price'] = $f['max_price'];
    }
    if (($f['location'] ?? '') !== '') {
        $where[] = 'p.location = :location';
        $params[':location'] = $f['location'];
    }
    if (!empty($f['farmer_id'])) {
        $where[] = 'p.farmer_id = :farmer_id';
        $params[':farmer_id'] = (int) $f['farmer_id'];
    }
    if (!empty($f['exclude_id'])) {
        $where[] = 'p.id <> :exclude_id';
        $params[':exclude_id'] = (int) $f['exclude_id'];
    }

    $order = match ($f['sort'] ?? 'latest') {
        'price_asc'  => 'p.price ASC, p.id DESC',
        'price_desc' => 'p.price DESC, p.id DESC',
        default      => 'p.created_at DESC, p.id DESC',
    };

    $from = 'FROM products p
             JOIN categories c ON c.id = p.category_id
             JOIN farmers f    ON f.id = p.farmer_id';
    $whereSql = implode(' AND ', $where);

    $count = db()->prepare("SELECT COUNT(*) $from WHERE $whereSql");
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    $pages  = max(1, (int) ceil($total / $perPage));
    $page   = min(max(1, $page), $pages);
    $offset = ($page - 1) * $perPage;

    $stmt = db()->prepare(
        "SELECT p.*, c.name AS category, c.slug AS category_slug, f.farm_name, f.verified
         $from WHERE $whereSql ORDER BY $order LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);

    return ['items' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
}

function find_product(int $id, bool $onlyActive = true): ?array
{
    $sql = 'SELECT p.*, c.name AS category, c.slug AS category_slug,
                   f.farm_name, f.verified, f.id AS farm_id
            FROM products p
            JOIN categories c ON c.id = p.category_id
            JOIN farmers f    ON f.id = p.farmer_id
            WHERE p.id = ?' . ($onlyActive ? ' AND p.is_active = 1' : '');
    $stmt = db()->prepare($sql);
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/** HTML for one produce card (mirrors the card template in assets/app.js). */
function product_card_html(array $p): string
{
    $out = (int) $p['stock'] <= 0;
    ob_start(); ?>
    <article class="product-card bg-brand-cream rounded-xl border border-brand-cardborder shadow-sm overflow-hidden flex flex-col justify-between hover:shadow-md transition-shadow">
      <div>
        <a href="product.php?id=<?= (int) $p['id'] ?>" class="block h-44 bg-gray-200 relative overflow-hidden">
          <img src="<?= e(product_image($p['image_url'])) ?>" alt="<?= e($p['title']) ?>" loading="lazy" class="w-full h-full object-cover">
          <span class="absolute top-3 left-3 bg-white/90 text-brand-green text-xs font-semibold px-2.5 py-1 rounded-full backdrop-blur-sm"><?= e($p['location']) ?></span>
          <?php if ($out): ?>
            <span class="absolute top-3 right-3 bg-red-600 text-white text-xs font-semibold px-2.5 py-1 rounded-full">Out of stock</span>
          <?php endif; ?>
        </a>
        <div class="p-4">
          <div class="text-xs text-brand-orange font-semibold uppercase tracking-wider mb-1"><?= e($p['category']) ?></div>
          <h3 class="font-bold text-lg text-brand-green mb-1 line-clamp-1"><?= e($p['title']) ?></h3>
          <p class="text-xs text-gray-500 mb-3"><?= e($p['farm_name']) ?></p>
          <div class="text-base font-bold text-brand-green">
            <?= e(money($p['price'])) ?> <span class="text-xs font-normal text-gray-600">/ <?= e($p['unit']) ?></span>
          </div>
        </div>
      </div>
      <div class="p-4 pt-0">
        <a href="product.php?id=<?= (int) $p['id'] ?>" class="btn btn-outline w-full">View details</a>
      </div>
    </article>
    <?php
    return (string) ob_get_clean();
}

/** Numbered pagination links that preserve the current filters. */
function pagination_html(int $page, int $pages, array $query = []): string
{
    if ($pages <= 1) {
        return '';
    }
    unset($query['page']);
    $query = array_filter($query, static fn($v) => $v !== '' && $v !== 0 && $v !== 'latest');
    $link  = static fn(int $n): string => 'catalogue.php?' . http_build_query($query + ['page' => $n]);

    $base = 'px-3.5 py-2 rounded-md border border-brand-cardborder text-sm font-medium';
    $html = '<nav class="flex flex-wrap items-center justify-center gap-2" aria-label="Pagination">';

    $html .= $page > 1
        ? '<a class="' . $base . ' bg-white text-gray-700 hover:bg-gray-50" href="' . e($link($page - 1)) . '">Previous</a>'
        : '<span class="' . $base . ' bg-white text-gray-400 opacity-60">Previous</span>';

    for ($i = 1; $i <= $pages; $i++) {
        $html .= $i === $page
            ? '<span aria-current="page" class="' . $base . ' bg-brand-green text-white font-semibold">' . $i . '</span>'
            : '<a class="' . $base . ' bg-white text-gray-700 hover:bg-gray-50" href="' . e($link($i)) . '">' . $i . '</a>';
    }

    $html .= $page < $pages
        ? '<a class="' . $base . ' bg-white text-gray-700 hover:bg-gray-50" href="' . e($link($page + 1)) . '">Next</a>'
        : '<span class="' . $base . ' bg-white text-gray-400 opacity-60">Next</span>';

    return $html . '</nav>';
}

/* =====================================================================
 * Shopping cart (stored in the session as product_id => quantity)
 * ===================================================================== */

function cart_count(): int
{
    return array_sum($_SESSION['cart'] ?? []);
}

function cart_add(int $productId, int $qty, int $maxStock): void
{
    $current = (int) ($_SESSION['cart'][$productId] ?? 0);
    $_SESSION['cart'][$productId] = min($current + max(1, $qty), max(1, $maxStock));
}

function cart_set(int $productId, int $qty): void
{
    if ($qty <= 0) {
        unset($_SESSION['cart'][$productId]);
    } elseif (isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId] = min($qty, 999);
    }
}

function cart_remove(int $productId): void
{
    unset($_SESSION['cart'][$productId]);
}

function cart_clear(): void
{
    unset($_SESSION['cart']);
}

/**
 * Cart contents priced from the database (never from client data).
 * Unavailable products are dropped and quantities are capped at stock.
 *
 * @return array{lines: array, subtotal: float, delivery: float, total: float, count: int}
 */
function cart_summary(): array
{
    $cart  = $_SESSION['cart'] ?? [];
    $lines = [];

    if ($cart) {
        $ids = array_map('intval', array_keys($cart));
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $stmt = db()->prepare(
            "SELECT p.*, f.farm_name FROM products p
             JOIN farmers f ON f.id = p.farmer_id
             WHERE p.is_active = 1 AND p.stock > 0 AND p.id IN ($in)"
        );
        $stmt->execute($ids);

        $clean = [];
        foreach ($stmt->fetchAll() as $p) {
            $qty = min((int) $cart[(int) $p['id']], (int) $p['stock']);
            if ($qty < 1) {
                continue;
            }
            $clean[(int) $p['id']] = $qty;
            $lines[] = ['product' => $p, 'qty' => $qty, 'line_total' => (float) $p['price'] * $qty];
        }
        $_SESSION['cart'] = $clean;
    }

    $subtotal = array_sum(array_column($lines, 'line_total'));
    $delivery = $lines ? (float) DELIVERY_FEE : 0.0;

    return [
        'lines'    => $lines,
        'subtotal' => $subtotal,
        'delivery' => $delivery,
        'total'    => $subtotal + $delivery,
        'count'    => array_sum(array_column($lines, 'qty')),
    ];
}

/* =====================================================================
 * Validation helpers
 * ===================================================================== */

function valid_phone(string $phone): bool
{
    return (bool) preg_match('/^\+?[0-9 ]{9,15}$/', $phone);
}

function valid_password(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return 'Password must contain at least one letter and one number.';
    }
    return null;
}

function valid_image_url(string $url): bool
{
    return $url === '' || (bool) preg_match('#^https?://[^\s]+$#i', $url) && strlen($url) <= 500;
}
