<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../auth/session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', null, 405);
}

$user_id = require_login();

try {
    $db = getDB();
    $stmt = $db->prepare('DELETE FROM cart_items WHERE user_id = ?');
    $stmt->execute([$user_id]);

    json_response(true, 'Cart cleared');

} catch (PDOException $e) {
    json_error('Failed to clear cart', null, 500);
}

