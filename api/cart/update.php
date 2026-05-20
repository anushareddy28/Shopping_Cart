<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../auth/session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', null, 405);
}

$user_id = require_login();
$input = get_json_input();

$cart_id = isset($input['cart_id']) ? intval($input['cart_id']) : 0;
$quantity = isset($input['quantity']) ? intval($input['quantity']) : 0;

if ($cart_id <= 0 || $quantity <= 0) {
    json_error('Valid cart item ID and quantity are required', null, 400);
}

try {
    $db = getDB();

    $stmt = $db->prepare('SELECT c.id, c.product_id, p.stock FROM cart_items c JOIN products p ON c.product_id = p.id WHERE c.id = ? AND c.user_id = ?');
    $stmt->execute([$cart_id, $user_id]);
    $item = $stmt->fetch();

    if (!$item) {
        json_error('Cart item not found', null, 404);
    }

    if ($quantity > $item['stock']) {
        json_error('Only ' . $item['stock'] . ' items available in stock', null, 400);
    }

    $update = $db->prepare('UPDATE cart_items SET quantity = ? WHERE id = ?');
    $update->execute([$quantity, $cart_id]);

    json_response(true, 'Cart updated');

} catch (PDOException $e) {
    json_error('Failed to update cart', null, 500);
}

