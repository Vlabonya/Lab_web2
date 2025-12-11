<?php
declare(strict_types=1);
require_once "db_connect.php";

// Безопасный эскейп
function e($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

if ($lastId <= 0) {
    exit;
}

// Получаем следующие 10 записей после last_id (без OFFSET для оптимизации)
$limit = 10;
$sql = "SELECT * FROM ads WHERE id < :last_id ORDER BY id DESC LIMIT :limit";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':last_id', $lastId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $ads = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($ads as $row) {
        $adId = (int)$row['id'];
        $photo = !empty($row['ads_photo']) ? e(basename($row['ads_photo'])) : '';
        $title = e($row['ads_title'] ?? '');
        $price = isset($row['ads_price']) && is_numeric($row['ads_price']) 
            ? number_format((int)$row['ads_price'], 0, '', ' ') 
            : '0';
        ?>
        <div class="ad-card">
            <div class="ad-img">
                <a href="detail.php?id=<?= $adId ?>">
                    <?php if ($photo): ?>
                        <img src="images/<?= $photo ?>"
                             alt="<?= $title ?>"
                             class="ad-image">
                    <?php else: ?>
                        <div class="no-photo-placeholder">Нет изображения</div>
                    <?php endif; ?>
                </a>
            </div>

            <div class="ad-price"><?= $price ?> ₽</div>
            <div class="ad-title"><?= $title ?></div>
        </div>
        <?php
    }
} catch (PDOException $e) {
    error_log("Ошибка при загрузке объявлений: " . $e->getMessage());
}

