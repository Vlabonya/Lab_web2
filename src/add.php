<?php
declare(strict_types=1);
session_start();
require_once "db_connect.php";

// Безопасный эскейп
function e($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Проверка авторизации
if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$currentUser = null;
try {
    $userStmt = $pdo->prepare('SELECT id, name, email, phone FROM users WHERE id = ? LIMIT 1');
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

            if (!in_array($photo['type'], $allowedTypes)) {
                $error = 'Недопустимый тип файла. Разрешены: JPEG, PNG, GIF, WebP';
            } elseif ($photo['size'] > $maxSize) {
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
                $insert = $pdo->prepare('INSERT INTO ads (ads_title, ads_description, ads_price, ads_photo, user_id) VALUES (?, ?, ?, ?, ?)');
                $insert->execute([
                    $title,
                    $description,
                    (int)$price,
                    $photoPath,
                    $currentUser['id']
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
            max-width: 600px;
            margin: 40px auto;
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 24px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #E0E0E0;
            border-radius: 8px;
            font-size: 16px;
            font-family: inherit;
        }
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #e91e63;
            box-shadow: 0 0 0 2px rgba(233, 30, 99, 0.1);
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
            color: white;
            border: none;
            padding: 16px 32px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
        }
        .submit-btn-add:hover {
            background: #c2185b;
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
                    <span class="user-welcome">Здравствуйте, <?= e($currentUser['name']) ?></span>
                    <a href="logout.php" class="auth-btn">Выход</a>
                </div>
            </div>
        </div>
    </header>

    <main class="main">
        <div class="container">
            <div class="add-form-container">
                <h1 style="margin-bottom: 32px; font-size: 32px;">Добавить объявление</h1>
                
                <?php if ($error): ?>
                    <div class="error-message"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="title">Заголовок *</label>
                        <input type="text" id="title" name="title" required 
                               value="<?= isset($_POST['title']) ? e($_POST['title']) : '' ?>">
                    </div>

                    <div class="form-group">
                        <label for="description">Описание *</label>
                        <textarea id="description" name="description" required><?= isset($_POST['description']) ? e($_POST['description']) : '' ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="price">Цена (₽) *</label>
                        <input type="number" id="price" name="price" required min="1" 
                               value="<?= isset($_POST['price']) ? e($_POST['price']) : '' ?>">
                    </div>

                    <div class="form-group">
                        <label for="photo">Фото</label>
                        <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/gif,image/webp">
                        <small style="color: #666; display: block; margin-top: 4px;">
                            Разрешены: JPEG, PNG, GIF, WebP (макс. 5MB)
                        </small>
                    </div>

                    <button type="submit" class="submit-btn-add">Добавить объявление</button>
                </form>

                <div style="margin-top: 20px; text-align: center;">
                    <a href="index.php" style="color: #666;">← Вернуться к списку</a>
                </div>
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

