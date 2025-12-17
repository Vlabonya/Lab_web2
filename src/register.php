<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
// Отключаем показ ошибок в ответе — логируем их
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once 'db_connect.php';

// Утилиты
function jsonError(array $errors): void {
    echo json_encode(['success' => false, 'errors' => $errors], JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonSuccess(): void {
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Принятие POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError(['server' => 'Invalid request method']);
    }

    // Обязательные поля
    $required = ['name','email','phone','password','confirm_password'];
    $input = [];
    foreach ($required as $f) {
        $input[$f] = trim((string)($_POST[$f] ?? ''));
        if ($input[$f] === '') {
            jsonError([$f => 'Поле обязательно']);
        }
    }

    // Валидация
    if (!preg_match('/^[а-яА-ЯёЁ\s-]+$/u', $input['name'])) {
        jsonError(['name' => 'Имя может содержать только русские буквы, пробелы и дефисы']);
    }

    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        jsonError(['email' => 'Некорректный email']);
    }

    $cleanPhone = preg_replace('/\D+/', '', $input['phone']);
    if (strlen($cleanPhone) !== 11) {
        jsonError(['phone' => 'Телефон должен содержать 11 цифр']);
    }
    // Проверяем, что номер начинается с 7 или 8
    if ($cleanPhone[0] !== '7' && $cleanPhone[0] !== '8') {
        jsonError(['phone' => 'Телефон должен начинаться с 7 или 8']);
    }

    if (strlen($input['password']) < 6) {
        jsonError(['password' => 'Пароль должен быть не менее 6 символов']);
    }

    if ($input['password'] !== $input['confirm_password']) {
        jsonError(['confirm_password' => 'Пароли не совпадают']);
    }

    // Проверка уникальности email
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$input['email']]);
    if ($stmt->fetch()) {
        jsonError(['email' => 'Этот email уже зарегистрирован']);
    }

    // Хешируем пароль
    $hash = password_hash($input['password'], PASSWORD_DEFAULT);

    // Вставка (используем поле password_hash в БД)
    $insert = $pdo->prepare('INSERT INTO users (name, email, phone, password_hash) VALUES (?, ?, ?, ?)');
    $insert->execute([$input['name'], $input['email'], $cleanPhone, $hash]);

    $userId = (int)$pdo->lastInsertId();

    // авторизация в сессии
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;

    jsonSuccess();

} catch (Throwable $e) {
    error_log('Register error: ' . $e->getMessage());
    jsonError(['server' => 'Ошибка сервера']);
}
