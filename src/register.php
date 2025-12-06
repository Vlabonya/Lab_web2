<?php
// register.php
declare(strict_types=1);
session_start();
require_once 'db_connect.php';

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$password = $_POST['password'] ?? '';

if ($name === '' || $email === '' || $password === '') {
    // можно сделать более дружелюбный редирект с ошибкой
    header('Location: index.php');
    exit;
}

// Проверим существование почты
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    // уже есть — редирект
    header('Location: index.php');
    exit;
}

// Создаём пользователя
$hash = password_hash($password, PASSWORD_DEFAULT);
$insert = $pdo->prepare('INSERT INTO users (name, email, phone, password_hash) VALUES (?, ?, ?, ?)');
$insert->execute([$name, $email, $phone, $hash]);
$userId = (int)$pdo->lastInsertId();

// Сохраняем сессию
$_SESSION['user_id'] = $userId;

header('Location: index.php');
exit;
