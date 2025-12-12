<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once 'db_connect.php';

function error($field, $msg) {
    echo json_encode([
        'success' => false,
        'errors' => [$field => $msg]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (!$email || !$password) {
    error('email', 'Заполните все поля');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    error('email', 'Некорректный email');
}

// ПОЛУЧАЕМ ПОЛЬЗОВАТЕЛЯ
$stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    error('email', 'Пользователь не найден');
}

if (!password_verify($password, $user['password_hash'])) {
    error('password', 'Пароль неверный');
}

$_SESSION['user_id'] = (int)$user['id'];

echo json_encode([
    'success' => true
], JSON_UNESCAPED_UNICODE);
