<?php
// login.php
declare(strict_types=1);
session_start();
require_once 'db_connect.php';

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($password, $user['password_hash'])) {
    // неверные данные
    header('Location: index.php');
    exit;
}

// Успешно
$_SESSION['user_id'] = (int)$user['id'];
header('Location: index.php');
exit;
