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

$product_id = isset($input['product_id']) ? intval($input['product_id']) : 0;
$quantity = isset($input['quantity']) ? intval($input['quantity']) : 1;

if ($product_id <= 0) {
    json_error('Product ID is required', null, 400);
}

if ($quantity <= 0) {
    $quantity = 1;
}

try {
    $db = getDB();

    $stmt = $db->prepare("SELECT id, stock, status FROM products WHERE id = ? AND status = 'active'");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if (!$product) {
        json_error('Product not found or unavailable', null, 404);
    }

    $cartStmt = $db->prepare('SELECT id, quantity FROM cart_items WHERE user_id = ? AND product_id = ?');
    $cartStmt->execute([$user_id, $product_id]);
    $existing = $cartStmt->fetch();

    $newQty = $existing ? $existing['quantity'] + $quantity : $quantity;

    if ($newQty > $product['stock']) {
        json_error('Only ' . $product['stock'] . ' items available in stock', null, 400);
    }

    if ($existing) {
        $update = $db->prepare('UPDATE cart_items SET quantity = ? WHERE id = ?');
        $update->execute([$newQty, $existing['id']]);
    } else {
        $insert = $db->prepare('INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)');
        $insert->execute([$user_id, $product_id, $quantity]);
    }

    $countStmt = $db->prepare('SELECT SUM(quantity) as total FROM cart_items WHERE user_id = ?');
    $countStmt->execute([$user_id]);
    $cartCount = $countStmt->fetch()['total'] ?: 0;

    json_response(true, 'Product added to cart', ['cart_count' => intval($cartCount)]);

} catch (PDOException $e) {
    json_error('Failed to add product to cart', null, 500);
}

