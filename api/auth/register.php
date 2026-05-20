<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../response.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', null, 405);
}

$input = get_json_input();

$name = isset($input['name']) ? sanitize($input['name']) : '';
$email = isset($input['email']) ? sanitize($input['email']) : '';
$phone = isset($input['phone']) ? sanitize($input['phone']) : '';
$password = isset($input['password']) ? $input['password'] : '';
$confirm_password = isset($input['confirm_password']) ? $input['confirm_password'] : '';

$errors = [];

if (empty($name)) {
    $errors['name'] = 'Name is required';
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Valid email is required';
}

if (!empty($phone) && !preg_match('/^[0-9]{10,15}$/', $phone)) {
    $errors['phone'] = 'Phone number must be 10 to 15 digits';
}

if (strlen($password) < 8) {
    $errors['password'] = 'Password must be at least 8 characters';
}

if ($password !== $confirm_password) {
    $errors['confirm_password'] = 'Passwords do not match';
}

if (!empty($errors)) {
    json_error('Validation failed', $errors, 422);
}

try {
    $db = getDB();

    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);

    if ($stmt->fetch()) {
        json_error('Email already registered', ['email' => 'This email is already in use'], 409);
    }

    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $db->prepare('INSERT INTO users (name, email, password_hash, role, phone) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$name, $email, $password_hash, 'user', $phone]);

    json_response(true, 'Registration successful');

} catch (PDOException $e) {
    json_error('Registration failed. Please try again.', null, 500);
}

