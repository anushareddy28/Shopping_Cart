<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../response.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', null, 405);
}

$input = get_json_input();

$email = isset($input['email']) ? sanitize($input['email']) : '';
$password = isset($input['password']) ? $input['password'] : '';

if (empty($email) || empty($password)) {
    json_error('Email and password are required', null, 422);
}

try {
    $db = getDB();

    $stmt = $db->prepare('SELECT id, name, email, password_hash, role FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        json_error('Invalid email or password', null, 401);
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];

    json_response(true, 'Login successful', [
        'id' => $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role']
    ]);

} catch (PDOException $e) {
    json_error('Login failed. Please try again.', null, 500);
}

