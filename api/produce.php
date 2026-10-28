<?php
declare(strict_types=1);

/**
 * GET /api/produce.php
 *
 * JSON listing of active produce, used by the live catalogue filters.
 * Query parameters: q, category (slug), max_price, location, sort, page.
 */

require_once __DIR__ . '/../functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $filters = catalogue_filters($_GET);
    $result  = fetch_products($filters, $filters['page']);

    $items = array_map(static fn(array $p): array => [
        'id'        => (int) $p['id'],
        'title'     => $p['title'],
        'category'  => $p['category'],
        'price'     => (float) $p['price'],
        'unit'      => $p['unit'],
        'location'  => $p['location'],
        'farm_name' => $p['farm_name'],
        'image_url' => product_image($p['image_url']),
        'in_stock'  => (int) $p['stock'] > 0,
        'url'       => 'product.php?id=' . (int) $p['id'],
    ], $result['items']);

    echo json_encode([
        'ok'    => true,
        'items' => $items,
        'total' => $result['total'],
        'page'  => $result['page'],
        'pages' => $result['pages'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $ex) {
    error_log('produce API error: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}
