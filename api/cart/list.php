<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../auth/session.php';

header('Content-Type: application/json');

$user_id = require_login();

try {
    $db = getDB();

    $stmt = $db->prepare('SELECT c.id, c.product_id, c.quantity, p.name, p.price, p.stock, p.image, p.status
                          FROM cart_items c
                          JOIN products p ON c.product_id = p.id
                          WHERE c.user_id = ?
                          ORDER BY c.created_at DESC');
    $stmt->execute([$user_id]);
    $items = $stmt->fetchAll();

    $subtotal = 0;
    foreach ($items as &$item) {
        $item['item_total'] = round($item['price'] * $item['quantity'], 2);
        $subtotal += $item['item_total'];
    }

    json_response(true, 'Cart fetched', [
        'items' => $items,
        'subtotal' => round($subtotal, 2),
        'item_count' => count($items)
    ]);

} catch (PDOException $e) {
    json_error('Failed to fetch cart', null, 500);
}

