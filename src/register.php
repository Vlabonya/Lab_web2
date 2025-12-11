<?php
declare(strict_types=1);
session_start();
require_once 'db_connect.php';

// Функции валидации
function validateName(string $name): bool {
    return preg_match('/^[а-яА-ЯёЁ\s\-]+$/u', $name) === 1;
}

function validateEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePhone(string $phone): bool {
    $cleaned = preg_replace('/\D/', '', $phone);
    return strlen($cleaned) === 11 && ($cleaned[0] === '7' || $cleaned[0] === '8');
}

function validatePassword(string $password): bool {
    if (strlen($password) < 6) {
        return false;
    }
    // Пароль не может состоять только из цифр
    return !preg_match('/^\d+$/', $password);
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? $_POST['regConfirmPassword'] ?? '';
$agree = isset($_POST['agree']);

$errors = [];

// Валидация полей
if (empty($name)) {
    $errors[] = 'Имя обязательно для заполнения';
} elseif (!validateName($name)) {
    $errors[] = 'Имя может содержать только русские буквы, пробелы и дефисы';
}

if (empty($email)) {
    $errors[] = 'Email обязателен для заполнения';
} elseif (!validateEmail($email)) {
    $errors[] = 'Введите корректный email';
}

if (empty($phone)) {
    $errors[] = 'Телефон обязателен для заполнения';
} elseif (!validatePhone($phone)) {
    $errors[] = 'Введите корректный мобильный телефон';
}

if (empty($password)) {
    $errors[] = 'Пароль обязателен для заполнения';
} elseif (!validatePassword($password)) {
    $errors[] = 'Пароль должен быть не менее 6 символов и не состоять только из цифр';
}

if ($password !== $confirmPassword) {
    $errors[] = 'Пароли не совпадают';
}

if (!$agree) {
    $errors[] = 'Необходимо согласие на обработку персональных данных';
}

// Если есть ошибки, возвращаем на форму
if (!empty($errors)) {
    $errorMsg = implode('. ', $errors);
    header('Location: index.php?reg_error=' . urlencode($errorMsg));
    exit;
}

// Проверяем существование email
try {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        header('Location: index.php?reg_error=' . urlencode('Пользователь с таким email уже зарегистрирован'));
        exit;
    }
} catch (PDOException $e) {
    error_log("Ошибка при проверке email: " . $e->getMessage());
    header('Location: index.php?reg_error=' . urlencode('Ошибка при регистрации'));
    exit;
}

// Создаём пользователя
try {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $insert = $pdo->prepare('INSERT INTO users (name, email, phone, password_hash) VALUES (?, ?, ?, ?)');
    $insert->execute([$name, $email, $phone, $hash]);
    $userId = (int)$pdo->lastInsertId();

    // Сохраняем сессию
    $_SESSION['user_id'] = $userId;

    header('Location: index.php');
    exit;
} catch (PDOException $e) {
    error_log("Ошибка при регистрации: " . $e->getMessage());
    header('Location: index.php?reg_error=' . urlencode('Ошибка при регистрации'));
    exit;
}
