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

$required = ['name', 'email', 'phone', 'password', 'confirm_password'];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        error($field, "Поле обязательно");
    }
}

// ДАННЫЕ
$name = trim($_POST['name']);
$email = trim($_POST['email']);
$phone = trim($_POST['phone']);
$password = $_POST['password'];
$confirm = $_POST['confirm_password'];

if (!preg_match('/^[а-яА-ЯёЁ\s-]+$/u', $name)) {
    error('name', 'Имя может содержать только русские буквы, пробелы и дефисы');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    error('email', 'Некорректный email');
}

$cleanPhone = preg_replace('/\D/', '', $phone);
if (strlen($cleanPhone) !== 11) {
    error('phone', 'Телефон должен содержать 11 цифр');
}

if (strlen($password) < 6) {
    error('password', 'Пароль должен быть не меньше 6 символов');
}

if ($password !== $confirm) {
    error('confirm_password', 'Пароли не совпадают');
}

// ПРОВЕРКА EMAIL НА УНИКАЛЬНОСТЬ
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    error('email', 'Этот email уже зарегистрирован');
}

// ХЕШ ПАРОЛЯ
$hash = password_hash($password, PASSWORD_DEFAULT);

// ЗАПИСЬ В БД
$stmt = $pdo->prepare("
    INSERT INTO users (name, email, phone, password_hash)
    VALUES (?, ?, ?, ?)
");

$stmt->execute([$name, $email, $phone, $hash]);

$userId = (int)$pdo->lastInsertId();
$_SESSION['user_id'] = $userId;

echo json_encode([
    'success' => true
], JSON_UNESCAPED_UNICODE);
