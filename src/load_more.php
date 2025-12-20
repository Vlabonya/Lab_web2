<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

$limit = 10;
$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : PHP_INT_MAX;
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;

if ($category === 0) {
    $stmt = $pdo->prepare('SELECT * FROM ads WHERE status = \'approved\' AND id < :last_id ORDER BY id DESC LIMIT :limit');
    $stmt->bindValue(':last_id', $last_id, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
} else {
    $stmt = $pdo->prepare('SELECT * FROM ads WHERE status = \'approved\' AND category = :category AND id < :last_id ORDER BY id DESC LIMIT :limit');
    $stmt->bindValue(':category', $category, PDO::PARAM_INT);
    $stmt->bindValue(':last_id', $last_id, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
}
$stmt->execute();

$ads = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($ads) > 0) {
    $ids = array_column($ads, 'id');
    $new_last_id = min($ids);
} else {
    $new_last_id = null;
}

$has_more = count($ads) === $limit;

echo json_encode([
    'success' => true,
    'ads' => $ads,
    'last_id' => $new_last_id,
    'has_more' => $has_more,
], JSON_UNESCAPED_UNICODE);
