<?php
declare(strict_types=1);
session_start();
require_once 'db_connect.php';

function validateEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    header('Location: index.php?login_error=' . urlencode('Все поля обязательны для заполнения'));
    exit;
}

if (!validateEmail($email)) {
    header('Location: index.php?login_error=' . urlencode('Введите корректный email'));
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        header('Location: index.php?login_error=' . urlencode('Неверный email или пароль'));
        exit;
    }

    // Успешно
    $_SESSION['user_id'] = (int)$user['id'];
    header('Location: index.php');
    exit;
} catch (PDOException $e) {
    error_log("Ошибка при входе: " . $e->getMessage());
    header('Location: index.php?login_error=' . urlencode('Ошибка при входе'));
    exit;
}
