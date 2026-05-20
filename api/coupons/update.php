<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../auth/session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', null, 405);
}

require_admin();

$input = get_json_input();
$id = isset($input['id']) ? intval($input['id']) : 0;
$status = isset($input['status']) ? $input['status'] : '';

if ($id <= 0) {
    json_error('Coupon ID is required', null, 400);
}

if (!in_array($status, ['active', 'inactive'])) {
    json_error('Invalid status', null, 400);
}

try {
    $db = getDB();

    $stmt = $db->prepare('UPDATE coupons SET status = ? WHERE id = ?');
    $stmt->execute([$status, $id]);

    if ($stmt->rowCount() === 0) {
        json_error('Coupon not found', null, 404);
    }

    json_response(true, 'Coupon updated successfully');

} catch (PDOException $e) {
    json_error('Failed to update coupon', null, 500);
}

