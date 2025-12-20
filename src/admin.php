<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once "db_connect.php";

// Безопасный эскейп
function e($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Проверяем, является ли пользователь администратором
$currentUser = null;
try {
    $userStmt = $pdo->prepare('SELECT id, name, email, phone, role FROM users WHERE id = ? LIMIT 1');
    $userStmt->execute([$_SESSION['user_id']]);
    $currentUser = $userStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Ошибка при получении пользователя: " . $e->getMessage());
    header('Location: index.php');
    exit;
}

if (!$currentUser || ($currentUser['role'] ?? 'user') !== 'admin') {
    header('Location: index.php');
    exit;
}

// Обработка действий модерации
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['ad_id'])) {
    $adId = (int)$_POST['ad_id'];
    $action = $_POST['action'];
    
    if (in_array($action, ['approve', 'reject'])) {
        try {
            $status = $action === 'approve' ? 'approved' : 'rejected';
            $update = $pdo->prepare('UPDATE ads SET status = ? WHERE id = ?');
            $update->execute([$status, $adId]);
            
            // Редирект на эту же страницу для обновления списка
            header('Location: admin.php');
            exit;
        } catch (PDOException $e) {
            error_log("Ошибка при модерации объявления: " . $e->getMessage());
            $error = 'Ошибка при выполнении действия';
        }
    }
}

// Получаем объявления на модерации
$pendingAds = [];
try {
    $adsStmt = $pdo->prepare('
        SELECT a.*, u.name AS user_name, u.email AS user_email 
        FROM ads a 
        LEFT JOIN users u ON a.user_id = u.id 
        WHERE a.status = \'pending\' 
        ORDER BY a.id DESC
    ');
    $adsStmt->execute();
    $pendingAds = $adsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Ошибка при получении объявлений на модерации: " . $e->getMessage());
    $pendingAds = [];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель модерации - Сайт объявлений</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .admin-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 24px;
        }
        .admin-header {
            margin-bottom: 32px;
        }
        .admin-header h1 {
            font-size: 36px;
            font-weight: 400;
            color: #181818;
            margin-bottom: 8px;
        }
        .admin-header p {
            color: #666;
            font-size: 16px;
        }
        .moderation-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .moderation-table th {
            background: #f9f9f9;
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #eee;
        }
        .moderation-table td {
            padding: 16px;
            border-bottom: 1px solid #eee;
        }
        .moderation-table tr:last-child td {
            border-bottom: none;
        }
        .moderation-table tr:hover {
            background: #f9f9f9;
        }
        .ad-preview {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .ad-preview-img {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            object-fit: cover;
            background: #f0f0f0;
        }
        .ad-preview-info {
            flex: 1;
        }
        .ad-preview-title {
            font-weight: 500;
            color: #181818;
            margin-bottom: 4px;
            font-size: 16px;
        }
        .ad-preview-author {
            color: #666;
            font-size: 14px;
        }
        .ad-preview-price {
            color: #181818;
            font-weight: 500;
            font-size: 18px;
        }
        .moderation-actions {
            display: flex;
            gap: 8px;
        }
        .btn-edit {
            background: #ff9800;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            transition: background 0.2s;
        }
        .btn-edit:hover {
            background: #fb8c00;
        }
        .btn-approve {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            transition: background 0.2s;
        }
        .btn-approve:hover {
            background: #45a049;
        }
        .btn-reject {
            background: #f44336;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            transition: background 0.2s;
        }
        .btn-reject:hover {
            background: #da190b;
        }
        .btn-view {
            background: #2196F3;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            transition: background 0.2s;
        }
        .btn-view:hover {
            background: #0b7dda;
        }
        .no-pending {
            text-align: center;
            padding: 60px 20px;
            color: #999;
            font-size: 18px;
        }
        .error-message {
            background: #ffe6e6;
            color: #d32f2f;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        /* Адаптивность панели модерации */
        @media (max-width: 1024px) {
            .admin-container {
                margin: 24px auto;
                padding: 0 16px;
            }

            .admin-header h1 {
                font-size: 30px;
            }

            .moderation-table th,
            .moderation-table td {
                padding: 12px;
            }

            .ad-preview-img {
                width: 70px;
                height: 70px;
            }
        }

        @media (max-width: 768px) {
            .admin-container {
                margin: 20px auto;
                padding: 0 12px;
            }

            .admin-header h1 {
                font-size: 26px;
            }

            .moderation-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }

            .moderation-actions {
                flex-direction: column;
                align-items: flex-start;
            }

            .btn-view,
            .btn-edit,
            .btn-approve,
            .btn-reject {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="header-top">
                <div class="logo">
                    <a href="index.php"><img src="images/logo.svg" alt="Логотип" class="logo-image"></a>
                </div>
                <div class="auth-buttons">
                    <a href="index.php" class="auth-btn">Главная</a>
                    <span class="user-welcome">Админ: <?= e($currentUser['name']) ?></span>
                    <a href="logout.php" class="auth-btn">Выход</a>
                </div>
            </div>
        </div>
    </header>

    <main class="main">
        <div class="admin-container">
            <div class="admin-header">
                <h1>Панель модерации</h1>
                <p>Объявления, ожидающие проверки: <?= count($pendingAds) ?></p>
            </div>

            <?php if (isset($error)): ?>
                <div class="error-message"><?= e($error) ?></div>
            <?php endif; ?>

            <?php if (empty($pendingAds)): ?>
                <div class="no-pending">
                    Нет объявлений на модерации. Все объявления проверены!
                </div>
            <?php else: ?>
                <table class="moderation-table">
                    <thead>
                        <tr>
                            <th>Объявление</th>
                            <th>Автор</th>
                            <th>Цена</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingAds as $ad): 
                            $photo = !empty($ad['ads_photo']) ? trim($ad['ads_photo']) : '';
                            if ($photo) {
                                $photoPath = (strpos($photo, '/') !== false || strpos($photo, '\\') !== false) 
                                    ? e($photo) 
                                    : e($photo);
                            } else {
                                $photoPath = '';
                            }
                            $price = isset($ad['ads_price']) && is_numeric($ad['ads_price']) 
                                ? number_format((int)$ad['ads_price'], 0, '', ' ') 
                                : '0';
                        ?>
                            <tr>
                                <td>
                                    <div class="ad-preview">
                                        <?php if ($photoPath): ?>
                                            <img src="<?= $photoPath ?>" alt="<?= e($ad['ads_title']) ?>" class="ad-preview-img">
                                        <?php else: ?>
                                            <div class="ad-preview-img" style="display: flex; align-items: center; justify-content: center; color: #999; font-size: 12px;">Нет фото</div>
                                        <?php endif; ?>
                                        <div class="ad-preview-info">
                                            <div class="ad-preview-title"><?= e($ad['ads_title']) ?></div>
                                            <div class="ad-preview-author"><?= e($ad['user_name'] ?? '—') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div><?= e($ad['user_name'] ?? '—') ?></div>
                                    <div style="color: #999; font-size: 14px;"><?= e($ad['user_email'] ?? '—') ?></div>
                                </td>
                                <td>
                                    <div class="ad-preview-price"><?= $price ?> ₽</div>
                                </td>
                                <td>
                                    <div class="moderation-actions">
                                        <a href="detail.php?id=<?= (int)$ad['id'] ?>&moderation=1" class="btn-view">Просмотр</a>
                                        <a href="edit_ad.php?id=<?= (int)$ad['id'] ?>" class="btn-edit">Редактировать</a>
                                        <form method="post" style="display: inline;" onsubmit="return confirm('Одобрить это объявление?');">
                                            <input type="hidden" name="ad_id" value="<?= (int)$ad['id'] ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn-approve">Одобрить</button>
                                        </form>
                                        <form method="post" style="display: inline;" onsubmit="return confirm('Отклонить это объявление?');">
                                            <input type="hidden" name="ad_id" value="<?= (int)$ad['id'] ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn-reject">Отклонить</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>

    <footer class="footer">
        <div class="container footer-inner">
            <div class="footer-email">info@gmail.com</div>
            <div class="footer-links">
                <a href="#">Информация о разработчике</a>
            </div>
        </div>
    </footer>
</body>
</html>

