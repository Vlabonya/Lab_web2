<?php
declare(strict_types=1);
session_start();
require_once 'db_connect.php';

// Требуем авторизованного пользователя
if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$ad_id = (int)($_POST['ad_id'] ?? 0);

if ($ad_id <= 0) {
    header('Location: index.php');
    exit;
}

try {
    // Проверяем, существует ли объявление и не является ли пользователь его автором
    $adStmt = $pdo->prepare('SELECT user_id FROM ads WHERE id = ? LIMIT 1');
    $adStmt->execute([$ad_id]);
    $ad = $adStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ad) {
        header('Location: index.php');
        exit;
    }
    
    // Проверяем, не является ли пользователь автором объявления
    if ((int)$ad['user_id'] === $user_id) {
        header("Location: detail.php?id={$ad_id}&error=" . urlencode('Вы не можете откликнуться на своё объявление'));
        exit;
    }
    
    // Берём имя и телефон пользователя из БД
    $userStmt = $pdo->prepare('SELECT name, phone FROM users WHERE id = ? LIMIT 1');
    $userStmt->execute([$user_id]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        header('Location: index.php');
        exit;
    }
    
    $name = $user['name'] ?? '';
    $phone = $user['phone'] ?? '';
    
    // Предотвращаем дубль: если уже откликался — редирект
    $chk = $pdo->prepare('SELECT id FROM responses WHERE ad_id = ? AND user_id = ? LIMIT 1');
    $chk->execute([$ad_id, $user_id]);
    if ($chk->fetch()) {
        header("Location: detail.php?id={$ad_id}&error=" . urlencode('Вы уже откликнулись на это объявление'));
        exit;
    }
    
    // Вставляем отклик: по заданию в отклике храним имя и телефон (и user_id для связи)
    $ins = $pdo->prepare('INSERT INTO responses (ad_id, name, phone, user_id) VALUES (?, ?, ?, ?)');
    $ins->execute([$ad_id, $name, $phone, $user_id]);
    
    header("Location: detail.php?id={$ad_id}&success=1");
    exit;
} catch (PDOException $e) {
    error_log("Ошибка при отклике: " . $e->getMessage());
    header("Location: detail.php?id={$ad_id}&error=" . urlencode('Ошибка при обработке отклика'));
    exit;
}
