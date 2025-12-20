<?php
declare(strict_types=1);
session_start();

require_once "db_connect.php";

// Безопасный эскейп
function e($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Создаём таблицу categories, если её нет
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Заполняем таблицу начальными данными, если она пустая
    $checkCategories = $pdo->query("SELECT COUNT(*) as cnt FROM categories");
    $count = $checkCategories->fetch(PDO::FETCH_ASSOC)['cnt'];
    if ($count == 0) {
        $pdo->exec("INSERT INTO categories (name) VALUES 
            ('Мебель'), 
            ('Одежда'), 
            ('Электроника'), 
            ('Разное')");
    }
} catch (PDOException $e) {
    error_log("Ошибка при создании/заполнении таблицы categories: " . $e->getMessage());
}

// Загружаем категории из базы данных
$categories = [];
try {
    $categoriesStmt = $pdo->query("SELECT id, name FROM categories ORDER BY id");
    $categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Ошибка при загрузке категорий: " . $e->getMessage());
    $categories = [];
}

// Проверяем авторизацию
if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Получаем текущего пользователя
$currentUser = null;
try {
    $userStmt = $pdo->prepare('SELECT id, name, email, phone, role FROM users WHERE id = ? LIMIT 1');
    $userStmt->execute([$_SESSION['user_id']]);
    $currentUser = $userStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Ошибка при получении пользователя (edit_ad.php): " . $e->getMessage());
    header('Location: index.php');
    exit;
}

// Только админ
if (!$currentUser || ($currentUser['role'] ?? 'user') !== 'admin') {
    header('Location: index.php');
    exit;
}

// Проверяем ID объявления
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: admin.php');
    exit;
}

$adId = (int)$_GET['id'];

// Получаем объявление
try {
    $adStmt = $pdo->prepare('SELECT * FROM ads WHERE id = ? LIMIT 1');
    $adStmt->execute([$adId]);
    $ad = $adStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Ошибка при получении объявления для редактирования: " . $e->getMessage());
    $ad = null;
}

if (!$ad) {
    header('Location: admin.php');
    exit;
}

$error = '';

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $categoryId = isset($_POST['category']) ? (int)$_POST['category'] : 0;
    $status = $_POST['status'] ?? ($ad['status'] ?? 'pending');
    $photo = $_FILES['photo'] ?? null;

    if (empty($title)) {
        $error = 'Заголовок обязателен для заполнения';
    } elseif (empty($description)) {
        $error = 'Описание обязательно для заполнения';
    } elseif (empty($price) || !is_numeric($price) || (int)$price <= 0) {
        $error = 'Введите корректную цену';
    } elseif ($categoryId <= 0) {
        $error = 'Выберите категорию';
    } else {
        // Проверяем, что категория существует в БД
        $categoryExists = false;
        foreach ($categories as $cat) {
            if ((int)$cat['id'] === $categoryId) {
                $categoryExists = true;
                break;
            }
        }
        if (!$categoryExists) {
            $error = 'Выберите корректную категорию';
        }
    }
    
    if (empty($error)) {
    } elseif (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
        $error = 'Некорректный статус объявления';
        $photoPath = $ad['ads_photo'] ?? '';

        // Если загружено новое фото — обрабатываем как в add.php
        if ($photo && $photo['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 5 * 1024 * 1024; // 5MB

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($photo['tmp_name']);
            if (!in_array($mime, $allowedTypes, true)) {
                $error = 'Недопустимый тип файла.';
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
                // Проверяем наличие поля category в таблице, если нет - добавляем
                try {
                    $checkColumn = $pdo->query("SHOW COLUMNS FROM ads LIKE 'category'");
                    if ($checkColumn->rowCount() == 0) {
                        $pdo->exec("ALTER TABLE ads ADD COLUMN category INT DEFAULT NULL");
                    } else {
                        // Если поле существует как VARCHAR, конвертируем его в INT
                        $columnInfo = $pdo->query("SHOW COLUMNS FROM ads WHERE Field = 'category'")->fetch(PDO::FETCH_ASSOC);
                        if ($columnInfo && strpos(strtolower($columnInfo['Type']), 'varchar') !== false) {
                            // Получаем ID категории "Разное" для установки по умолчанию
                            $otherCategory = $pdo->query("SELECT id FROM categories WHERE name = 'Разное' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                            $defaultCategoryId = $otherCategory ? (int)$otherCategory['id'] : null;
                            
                            if ($defaultCategoryId) {
                                // Обновляем все существующие значения на ID категории "Разное"
                                $pdo->exec("UPDATE ads SET category = " . $defaultCategoryId . " WHERE category IS NULL OR category = '' OR category NOT REGEXP '^[0-9]+$'");
                            }
                            // Изменяем тип колонки на INT
                            $pdo->exec("ALTER TABLE ads MODIFY COLUMN category INT DEFAULT NULL");
                        }
                    }
                } catch (PDOException $e) {
                    error_log("Ошибка при проверке/изменении поля category: " . $e->getMessage());
                }

                $update = $pdo->prepare('
                    UPDATE ads 
                    SET ads_title = ?, ads_description = ?, ads_price = ?, ads_photo = ?, status = ?, category = ?
                    WHERE id = ?
                ');
                $update->execute([
                    $title,
                    $description,
                    (int)$price,
                    $photoPath,
                    $status,
                    $categoryId,
                    $adId
                ]);

                header('Location: detail.php?id=' . $adId);
                exit;
            } catch (PDOException $e) {
                error_log("Ошибка при обновлении объявления: " . $e->getMessage());
                $error = 'Ошибка при сохранении изменений';
            }
        }
    }

    // Обновляем $ad для повторного отображения формы с введёнными данными
    $ad['ads_title'] = $title;
    $ad['ads_description'] = $description;
    $ad['ads_price'] = $price;
    $ad['status'] = $status;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактировать объявление - Сайт объявлений</title>
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
        .form-group textarea,
        .form-group select {
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
        .form-group textarea:focus,
        .form-group select:focus {
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

        /* Кастомный инпут файла как в add.php */
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

        .current-photo-preview {
            margin-bottom: 16px;
            text-align: center;
        }
        .current-photo-preview img {
            max-width: 100%;
            max-height: 320px;
            border-radius: 16px;
            object-fit: cover;
        }
        .status-note {
            font-size: 14px;
            color: #777;
        }

        /* Кнопка как в add.php */
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

        .submit-btn-back {
            text-decoration: none;
            display: inline-block;
        }

        /* Адаптивность формы редактирования (как add.php) */
        @media (max-width: 1024px) {
            .add-form-container {
                margin: 24px auto 40px;
                padding: 32px 24px;
            }

            .add-form-layout {
                gap: 40px;
            }

            .add-form-left {
                flex: 0 0 360px;
            }

            .form-group label {
                font-size: 26px;
            }
        }

        @media (max-width: 768px) {
            .add-form-container {
                margin: 20px auto 32px;
                padding: 24px 18px;
                box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
            }

            .add-form-layout {
                flex-direction: column;
                gap: 32px;
            }

            .add-form-left {
                flex: 0 0 auto;
                width: 100%;
            }

            .custom-file-box {
                height: 260px;
            }

            .form-group label {
                font-size: 24px;
            }

            .form-group input,
            .form-group textarea,
            .form-group select {
                font-size: 16px;
            }

            .add-form-footer {
                flex-direction: column;
                align-items: stretch;
            }

            .submit-btn-add {
                width: 100%;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .add-form-container {
                padding: 20px 14px;
                margin: 16px auto 28px;
            }

            .custom-file-box {
                height: 220px;
                padding: 24px 12px;
            }

            .custom-file-plus {
                font-size: 30px;
            }

            .form-group label {
                font-size: 20px;
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
                    <a href="admin.php" class="auth-btn">Панель модерации</a>
                    <span class="user-welcome">Админ: <?= e($currentUser['name']) ?></span>
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
                                <?php
                                $photo = !empty($ad['ads_photo']) ? trim($ad['ads_photo']) : '';
                                if ($photo) {
                                    $photoPath = (strpos($photo, '/') !== false || strpos($photo, '\\') !== false)
                                        ? e($photo)
                                        : 'images/' . e(basename($photo));
                                } else {
                                    $photoPath = '';
                                }
                                ?>
                                <div class="current-photo-preview">
                                    <?php if ($photoPath): ?>
                                        <img src="<?= $photoPath ?>" alt="<?= e($ad['ads_title'] ?? '') ?>">
                                    <?php else: ?>
                                        <div style="color:#999;font-size:14px;">Фото не загружено</div>
                                    <?php endif; ?>
                                </div>
                                <div class="custom-file-wrapper">
                                    <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/gif,image/webp">
                                    <div class="custom-file-box" onclick="document.getElementById('photo').click(); return false;">
                                        <div class="custom-file-plus">+</div>
                                        <span class="custom-file-text">Заменить изображение</span>
                                    </div>
                                </div>
                                <small style="color: #666; display: block; margin-top: 8px;">
                                    Если файл не выбрать, останется текущее изображение. Разрешены форматы: JPEG, PNG, GIF, WebP (до 5 МБ)
                                </small>
                            </div>
                        </div>

                        <div class="add-form-right">
                            <div class="form-group">
                                <label for="title">Название</label>
                                <input type="text" id="title" name="title" required
                                       value="<?= e($ad['ads_title'] ?? '') ?>">
                            </div>

                            <div class="form-group">
                                <label for="price">Цена</label>
                                <input type="number" id="price" name="price" required min="1" 
                                       value="<?= e((string)($ad['ads_price'] ?? '')) ?>">
                            </div>

                            <div class="form-group">
                                <label for="category">Категория</label>
                                <select id="category" name="category" required style="width: 100%; padding: 4px 0; border: none; border-bottom: 1px solid #e0e0e0; border-radius: 0; font-size: 18px; font-family: inherit; color: #333; background: transparent;">
                                    <option value="">Выберите категорию</option>
                                    <?php 
                                    $currentCategoryId = isset($ad['category']) ? (int)$ad['category'] : 0;
                                    foreach ($categories as $cat): ?>
                                        <option value="<?= (int)$cat['id'] ?>" <?= $currentCategoryId === (int)$cat['id'] ? 'selected' : '' ?>>
                                            <?= e($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="description">Описание</label>
                                <textarea id="description" name="description" required><?= e($ad['ads_description'] ?? '') ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="status">Статус</label>
                                <?php $currentStatus = $ad['status'] ?? 'pending'; ?>
                                <select id="status" name="status">
                                    <option value="pending" <?= $currentStatus === 'pending' ? 'selected' : '' ?>>На модерации</option>
                                    <option value="approved" <?= $currentStatus === 'approved' ? 'selected' : '' ?>>Одобрено</option>
                                    <option value="rejected" <?= $currentStatus === 'rejected' ? 'selected' : '' ?>>Отклонено</option>
                                </select>
                                <div class="status-note">Вы можете сразу одобрить или отклонить объявление.</div>
                            </div>

                            <div class="add-form-footer">
                                <button type="submit" class="submit-btn-add">Сохранить изменения</button>
                                <a href="detail.php?id=<?= (int)$ad['id'] ?>" class="submit-btn-add submit-btn-back">← Вернуться к объявлению</a>
                            </div>
                        </div>
                    </div>
                </form>
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

