<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once 'db_connect.php';

function jsonError(array $errors): void {
    echo json_encode(['success' => false, 'errors' => $errors], JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonSuccess(): void {
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError(['server' => 'Invalid request method']);
    }

    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        jsonError(['email' => 'Заполните все поля']);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonError(['email' => 'Некорректный email']);
    }

    $stmt = $pdo->prepare('SELECT id, password_hash, name FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        jsonError(['email' => 'Пользователь не найден']);
    }

    if (!password_verify($password, (string)$user['password_hash'])) {
        jsonError(['password' => 'Пароль неверный']);
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    // можно сохранить имя, если нужно:
    $_SESSION['user_name'] = $user['name'] ?? '';

    jsonSuccess();

} catch (Throwable $e) {
    error_log('Login error: ' . $e->getMessage());
    jsonError(['server' => 'Ошибка сервера']);
}
