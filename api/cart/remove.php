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

if ($cart_id <= 0) {
    json_error('Cart item ID is required', null, 400);
}

try {
    $db = getDB();

    $stmt = $db->prepare('DELETE FROM cart_items WHERE id = ? AND user_id = ?');
    $stmt->execute([$cart_id, $user_id]);

    if ($stmt->rowCount() === 0) {
        json_error('Cart item not found', null, 404);
    }

    json_response(true, 'Item removed from cart');

} catch (PDOException $e) {
    json_error('Failed to remove item', null, 500);
}

