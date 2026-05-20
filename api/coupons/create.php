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

$code = isset($input['code']) ? strtoupper(trim($input['code'])) : '';
$discount_type = isset($input['discount_type']) ? $input['discount_type'] : '';
$discount_value = isset($input['discount_value']) ? floatval($input['discount_value']) : 0;
$min_order_value = isset($input['min_order_value']) ? floatval($input['min_order_value']) : 0;
$max_discount = isset($input['max_discount']) ? floatval($input['max_discount']) : null;
$expiry_date = isset($input['expiry_date']) ? $input['expiry_date'] : '';
$status = isset($input['status']) ? $input['status'] : 'active';

$errors = [];
if (empty($code)) $errors['code'] = 'Coupon code is required';
if (!in_array($discount_type, ['percentage', 'fixed'])) $errors['discount_type'] = 'Invalid discount type';
if ($discount_value <= 0) $errors['discount_value'] = 'Discount value must be greater than 0';
if (empty($expiry_date)) $errors['expiry_date'] = 'Expiry date is required';

if (!empty($errors)) {
    json_error('Validation failed', $errors, 422);
}

if ($max_discount <= 0) {
    $max_discount = null;
}

try {
    $db = getDB();

    $check = $db->prepare('SELECT id FROM coupons WHERE code = ?');
    $check->execute([$code]);
    if ($check->fetch()) {
        json_error('Coupon code already exists', null, 409);
    }

    $stmt = $db->prepare('INSERT INTO coupons (code, discount_type, discount_value, min_order_value, max_discount, expiry_date, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$code, $discount_type, $discount_value, $min_order_value, $max_discount, $expiry_date, $status]);

    json_response(true, 'Coupon created successfully', ['id' => $db->lastInsertId()], 201);

} catch (PDOException $e) {
    json_error('Failed to create coupon', null, 500);
}

