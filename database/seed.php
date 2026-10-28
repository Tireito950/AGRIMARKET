<?php
declare(strict_types=1);

/**
 * Seeds demo farmers, a demo buyer and sample produce.
 *
 * Usage (after importing database/schema.sql):
 *     php database/seed.php
 *
 * Command-line only, and it refuses to run if products already exist.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this script from the command line: php database/seed.php');
}

require_once __DIR__ . '/../functions.php';

$pdo = db();

if ((int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() > 0) {
    exit("Products already exist – nothing to seed.\n");
}

$farmerPassword = 'Farmer@123';
$buyerPassword  = 'Buyer@123';

$farms = [
    'eldoret'  => ['Eldoret Grains Co.',    'Eldoret',  'Wholesale grain growers supplying millers and feed makers across the Rift Valley.'],
    'olga'     => ['Olga Avocado Farm',     "Murang'a", 'Family orchard growing Hass avocados in rich volcanic soil.'],
    'kiambu'   => ['Kiambu Fresh Farm',     'Kiambu',   'Mixed farm delivering vegetables and fresh milk to Nairobi daily.'],
    'poultry'  => ['Nakuru Poultry',        'Nakuru',   'Free-range egg producers with grain-fed hens.'],
    'organic'  => ['Nakuru Organic',        'Nakuru',   'Certified-organic root vegetables grown without synthetic inputs.'],
    'machakos' => ['Machakos Valley Farm',  'Machakos', 'Onion and horticulture growers in the Machakos valley.'],
    'meru'     => ['Meru Highland Produce', 'Meru',     'Highland potato farmers working cool mountain soil.'],
];

$categoryIds = $pdo->query('SELECT slug, id FROM categories')->fetchAll(PDO::FETCH_KEY_PAIR);

$pdo->beginTransaction();

try {
    $insertUser = $pdo->prepare(
        'INSERT INTO users (name, email, password_hash, phone, role) VALUES (?, ?, ?, ?, ?)'
    );
    $insertFarmer = $pdo->prepare(
        'INSERT INTO farmers (user_id, farm_name, location, bio, verified) VALUES (?, ?, ?, ?, 1)'
    );

    $farmerIds = [];
    $n = 0;
    foreach ($farms as $key => [$farmName, $location, $bio]) {
        $insertUser->execute([
            $farmName . ' Owner',
            $key . '@agrimarket.test',
            password_hash($farmerPassword, PASSWORD_DEFAULT),
            '+25471000' . str_pad((string) (++$n), 4, '0', STR_PAD_LEFT),
            'farmer',
        ]);
        $insertFarmer->execute([(int) $pdo->lastInsertId(), $farmName, $location, $bio]);
        $farmerIds[$key] = (int) $pdo->lastInsertId();
    }

    $insertUser->execute([
        'Demo Buyer', 'buyer@agrimarket.test',
        password_hash($buyerPassword, PASSWORD_DEFAULT), '+254720000000', 'buyer',
    ]);

    $u = 'https://images.unsplash.com/';
    $q = '?auto=format&fit=crop&w=600&q=80';

    // farm, category, title, price, unit, stock, image, days since harvest, description
    $products = [
        ['eldoret',  'grains',        'Yellow Maize (Bulk)', 450, '90kg bag',       120, 'photo-1551754655-cd27e38d2076', 5,
            'Premium yellow maize harvested directly from Eldoret farms. Sun-dried and packed in heavy-duty 90kg bags, ready for milling or animal feed.'],
        ['olga',     'fruits',        'Ripe Avocados',       120, 'pack of 4',      80,  'photo-1523049673857-eb18f1d7b578', 2,
            'Hass avocados grown in rich volcanic soil. Naturally ripened with a creamy texture.'],
        ['kiambu',   'vegetables',    'Fresh Tomatoes',      120, 'kg',             200, 'photo-1592924357228-91a4daadcfea', 1,
            'Firm, juicy red tomatoes hand-picked every morning. Ideal for salads, sauces and cooking.'],
        ['poultry',  'dairy-poultry', 'Farm Eggs',           420, 'crate (30 pcs)', 60,  'photo-1516467508483-a7212febe31a', 1,
            'Fresh free-range brown eggs collected daily from grain-fed hens.'],
        ['organic',  'vegetables',    'Fresh Carrots',        90, 'kg',             150, 'photo-1598170845058-12ef4a457c1a', 2,
            'Crisp, sweet carrots cleaned and packed in eco-friendly bags.'],
        ['machakos', 'vegetables',    'Red Onions',          110, 'kg',             180, 'photo-1618512496248-a07fe83aa8cf', 6,
            'Aromatic red onions, carefully cured for a long shelf life and strong flavour.'],
        ['meru',     'vegetables',    'Organic Potatoes',    280, '10kg bag',       90,  'photo-1518977676601-b53f82aba655', 4,
            'Red-skinned Irish potatoes grown in cool mountain soil. Great for baking, roasting or frying.'],
        ['kiambu',   'dairy-poultry', 'Fresh Milk',           70, 'litre',          250, 'photo-1563636619-e9143da7973b', 0,
            'Pasteurised whole milk sourced directly from local dairy herds.'],
    ];

    $insertProduct = $pdo->prepare(
        'INSERT INTO products
            (farmer_id, category_id, title, description, price, unit, stock, location, image_url, harvest_date)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    foreach ($products as [$farm, $cat, $title, $price, $unit, $stock, $photo, $daysAgo, $desc]) {
        $insertProduct->execute([
            $farmerIds[$farm],
            $categoryIds[$cat],
            $title,
            $desc,
            $price,
            $unit,
            $stock,
            $farms[$farm][1],
            $u . $photo . $q,
            date('Y-m-d', strtotime("-{$daysAgo} days")),
        ]);
    }

    $pdo->commit();
} catch (Throwable $ex) {
    $pdo->rollBack();
    fwrite(STDERR, 'Seeding failed: ' . $ex->getMessage() . "\n");
    exit(1);
}

echo "Seeded " . count($farms) . " farmers and " . count($products) . " products.\n\n";
echo "Demo logins\n";
echo "  Buyer : buyer@agrimarket.test / {$buyerPassword}\n";
echo "  Farmer: kiambu@agrimarket.test / {$farmerPassword}  (also eldoret, olga, poultry, organic, machakos, meru)\n";
echo "\nCreate the administrator by opening setup_admin.php in your browser.\n";
