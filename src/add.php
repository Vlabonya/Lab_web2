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

if (!$currentUser) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = false;

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $photo = $_FILES['photo'] ?? null;

    // Валидация
    if (empty($title)) {
        $error = 'Заголовок обязателен для заполнения';
    } elseif (empty($description)) {
        $error = 'Описание обязательно для заполнения';
    } elseif (empty($price) || !is_numeric($price) || (int)$price <= 0) {
        $error = 'Введите корректную цену';
    } else {
        $photoPath = '';
        
        // Обработка загрузки фото
        if ($photo && $photo['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 5 * 1024 * 1024; // 5MB

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($photo['tmp_name']);
            if (!in_array($mime, $allowedTypes)) {
                $error = 'Недопустимый тип файла.';
            }elseif ($photo['size'] > $maxSize) {
                $error = 'Размер файла не должен превышать 5MB';
            } else {
                $extension = pathinfo($photo['name'], PATHINFO_EXTENSION);
                $fileName = uniqid('ad_', true) . '.' . $extension;
                $uploadDir = __DIR__ . '/uploads/';
                
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $targetPath = $uploadDir . $fileName;
                if (move_uploaded_file($photo['tmp_name'], $targetPath)) {
                    $photoPath = 'uploads/' . $fileName;
                } else {
                    $error = 'Ошибка при загрузке файла';
                }
            }
        }

        if (empty($error)) {
            try {
                // Администратор публикует объявления сразу (approved), обычный пользователь — на модерацию (pending)
                $initialStatus = (($currentUser['role'] ?? 'user') === 'admin') ? 'approved' : 'pending';

                $insert = $pdo->prepare('INSERT INTO ads (ads_title, ads_description, ads_price, ads_photo, user_id, status) VALUES (?, ?, ?, ?, ?, ?)');
                $insert->execute([
                    $title,
                    $description,
                    (int)$price,
                    $photoPath,
                    $currentUser['id'],
                    $initialStatus
                ]);
                
                $adId = (int)$pdo->lastInsertId();
                header('Location: detail.php?id=' . $adId);
                exit;
            } catch (PDOException $e) {
                error_log("Ошибка при добавлении объявления: " . $e->getMessage());
                $error = 'Ошибка при добавлении объявления';
            }
        }
    }
}

// Получаем объявления текущего пользователя для отображения истории
$userAds = [];
try {
    $adsStmt = $pdo->prepare('SELECT id, ads_title, ads_photo, status FROM ads WHERE user_id = ? ORDER BY id DESC LIMIT 20');
    $adsStmt->execute([$currentUser['id']]);
    $userAds = $adsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Ошибка при получении объявлений пользователя: " . $e->getMessage());
    $userAds = [];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Добавить объявление - Сайт объявлений</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .add-form-container {
            max-width: 1200px;
            margin: 40px auto 56px;
            background: #ffffff;
            padding: 40px 32px;
            border-radius: 18px;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
        }

        .add-form-layout {
            display: flex;
            gap: 72px;
            align-items: flex-start;
        }

        .add-form-left {
            flex: 0 0 420px;
        }

        .add-form-right {
            flex: 1;
            padding-top: 12px;
        }

        .form-group {
            margin-bottom: 28px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 400;
            font-size: 32px;
            line-height: 1.2;
            color: #b3b3b3;
        }
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 4px 0;
            border: none;
            border-bottom: 1px solid #e0e0e0;
            border-radius: 0;
            font-size: 18px;
            font-family: inherit;
            color: #333;
            background: transparent;
        }

        .form-group textarea {
            min-height: 90px;
            resize: vertical;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #ff006b;
            box-shadow: 0 1px 0 0 #ff006b;
        }
        .error-message {
            color: red;
            margin-bottom: 20px;
            padding: 12px;
            background: #ffe6e6;
            border-radius: 8px;
        }
        .submit-btn-add {
            background: #FF006B;
            color: #ffffff;
            border: none;
            padding: 16px 32px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s, transform 0.15s, box-shadow 0.15s;
        }
        .submit-btn-add:hover {
            background: #c2185b;
            transform: translateY(-1px);
            box-shadow: 0 8px 18px rgba(233, 30, 99, 0.35);
        }
        .submit-btn-add:active {
            transform: translateY(0);
            box-shadow: none;
        }
        .user-ads-history {
            margin-top: 40px;
            padding-top: 40px;
            border-top: 1px solid #eee;
        }
        .user-ads-history h2 {
            font-size: 24px;
            font-weight: 500;
            margin-bottom: 24px;
            color: #181818;
        }
        .user-ads-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 16px;
        }
        .user-ad-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            background: #f9f9f9;
            aspect-ratio: 1;
        }
        .user-ad-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .user-ad-status {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: bold;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        .user-ad-status.approved {
            background: #4CAF50;
            color: white;
        }
        .user-ad-status.rejected {
            background: #f44336;
            color: white;
        }
        .user-ad-status.pending {
            background: #ff9800;
            color: white;
        }
        .no-photo-small {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 12px;
            text-align: center;
            padding: 8px;
        }

        /* Кастомный инпут файла */
        .custom-file-wrapper {
            position: relative;
        }
        .custom-file-wrapper input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }
        .custom-file-box {
            border-radius: 24px;
            border: 2px dashed #b3b3b3;
            background: #fafafa;
            padding: 32px 16px;
            height: 420px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: #777;
            font-size: 16px;
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s, color 0.2s, box-shadow 0.2s;
        }
        .custom-file-box:hover {
            border-color: #ff006b;
            background: #fff5fb;
            color: #ff006b;
            box-shadow: 0 0 0 1px rgba(255, 0, 107, 0.06);
        }
        .custom-file-plus {
            font-size: 36px;
            line-height: 1;
            margin-bottom: 18px;
            color: #ff006b;
        }
        .custom-file-text {
            pointer-events: none;
        }

        .add-form-footer {
            margin-top: 40px;
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .add-form-note {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            font-size: 14px;
        }

        .add-form-note-icon {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 1px solid #999;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: #999;
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
                    <?php if (($currentUser['role'] ?? 'user') === 'admin'): ?>
                        <a href="admin.php" class="auth-btn">Панель модерации</a>
                    <?php endif; ?>
                    <span class="user-welcome">Здравствуйте, <?= e($currentUser['name']) ?></span>
                    <a href="logout.php" class="auth-btn">Выход</a>
                </div>
            </div>
        </div>
    </header>

    <main class="main">
        <div class="container">
            <div class="add-form-container">
                <?php if ($error): ?>
                    <div class="error-message"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="post" enctype="multipart/form-data">
                    <div class="add-form-layout">
                        <div class="add-form-left">
                            <div class="form-group">
                                <div class="custom-file-wrapper">
                                    <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/gif,image/webp">
                                    <div class="custom-file-box" onclick="document.getElementById('photo').click(); return false;">
                                        <div class="custom-file-plus">+</div>
                                        <span class="custom-file-text">Загрузите изображение</span>
                                    </div>
                                </div>
                                <small style="color: #666; display: block; margin-top: 8px;">
                                    Разрешены форматы: JPEG, PNG, GIF, WebP (до 5 МБ)
                                </small>
                            </div>
                        </div>

                        <div class="add-form-right">
                            <div class="form-group">
                                <label for="title">Название</label>
                                <input type="text" id="title" name="title" required 
                                       value="<?= isset($_POST['title']) ? e($_POST['title']) : '' ?>">
                            </div>

                            <div class="form-group">
                                <label for="price">Цена</label>
                                <input type="number" id="price" name="price" required min="1" 
                                       value="<?= isset($_POST['price']) ? e($_POST['price']) : '' ?>">
                            </div>

                            <div class="form-group">
                                <label for="description">Описание</label>
                                <textarea id="description" name="description" required><?= isset($_POST['description']) ? e($_POST['description']) : '' ?></textarea>
                            </div>

                            <div class="add-form-footer">
                                <button type="submit" class="submit-btn-add">Опубликовать объявление</button>
                                <div class="add-form-note">
                                    <div class="add-form-note-icon">i</div>
                                    <span>Все поля обязательны для заполнения</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <?php if (!empty($userAds)): ?>
                <div class="user-ads-history">
                    <h2>Мои объявления</h2>
                    <div class="user-ads-grid">
                        <?php foreach ($userAds as $ad): 
                            $photo = !empty($ad['ads_photo']) ? trim($ad['ads_photo']) : '';
                            if ($photo) {
                                $photoPath = (strpos($photo, '/') !== false || strpos($photo, '\\') !== false) 
                                    ? e($photo) 
                                    : e($photo);
                            } else {
                                $photoPath = '';
                            }
                            $status = $ad['status'] ?? 'pending';
                            $statusIcon = '';
                            if ($status === 'approved') {
                                $statusIcon = '✓';
                            } elseif ($status === 'rejected') {
                                $statusIcon = '✕';
                            } else {
                                $statusIcon = '⏳';
                            }
                        ?>
                            <a href="detail.php?id=<?= (int)$ad['id'] ?>" class="user-ad-item" title="<?= e($ad['ads_title']) ?>">
                                <?php if ($photoPath): ?>
                                    <img src="<?= $photoPath ?>" alt="<?= e($ad['ads_title']) ?>">
                                <?php else: ?>
                                    <div class="no-photo-small">Нет фото</div>
                                <?php endif; ?>
                                <div class="user-ad-status <?= e($status) ?>"><?= $statusIcon ?></div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
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

