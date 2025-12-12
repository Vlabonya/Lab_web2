<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

$limit = 10;
$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : PHP_INT_MAX;

$stmt = $pdo->prepare('SELECT * FROM ads WHERE id < :last_id ORDER BY id DESC LIMIT :limit'); // сортировка, ASC - сначала старые (ID 1,2,3..), DESC - новые
$stmt->bindValue(':last_id', $last_id, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
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
