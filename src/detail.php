<?php
declare(strict_types=1);
session_start();
require_once "db_connect.php";

// безопасный эскейп — используем везде вместо htmlspecialchars(...)
function e($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}


// Проверяем, передан ли ID объявления
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

// Получаем данные объявления с информацией об авторе
$sql = "
    SELECT 
        ads.id AS id,
        ads.ads_title,
        ads.ads_description,
        ads.ads_photo,
        ads.ads_price,
        ads.created_at,
        ads.status,
        users.id AS user_id,
        users.name AS user_name,
        users.phone AS user_phone,
        users.email AS user_email
    FROM ads
    LEFT JOIN users ON ads.user_id = users.id
    WHERE ads.id = :id
    LIMIT 1
";


try {
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $ad = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Ошибка при получении объявления: " . $e->getMessage());
    header("Location: index.php");
    exit();
}

// Если объявление не найдено - редирект на главную
if (!$ad) {
    header("Location: index.php");
    exit();
}

// Получаем информацию о текущем пользователе
$currentUser = null;
$isAuthor = false;
$hasResponded = false;

if (!empty($_SESSION['user_id'])) {
    try {
        $userStmt = $pdo->prepare('SELECT id, name, email, phone, role FROM users WHERE id = ? LIMIT 1');
        $userStmt->execute([$_SESSION['user_id']]);
        $currentUser = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        // Проверяем, является ли пользователь автором объявления
        if ($currentUser && isset($ad['user_id']) && (int)$ad['user_id'] === (int)$currentUser['id']) {
            $isAuthor = true;
        }
        
        // Проверяем, откликался ли пользователь на это объявление
        if ($currentUser && !$isAuthor) {
            $checkStmt = $pdo->prepare('SELECT id FROM responses WHERE ad_id = ? AND user_id = ? LIMIT 1');
            $checkStmt->execute([$id, $currentUser['id']]);
            $hasResponded = (bool)$checkStmt->fetch();
        }
    } catch (PDOException $e) {
        error_log("Ошибка при получении пользователя: " . $e->getMessage());
    }
}

// Проверяем, является ли пользователь администратором
$isAdmin = $currentUser && ($currentUser['role'] ?? 'user') === 'admin';

// Обработка действий модерации (только для администраторов)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin && isset($_POST['action']) && isset($_POST['ad_id'])) {
    $adId = (int)$_POST['ad_id'];
    $action = $_POST['action'];
    
    if (in_array($action, ['approve', 'reject']) && $adId === $id) {
        try {
            $status = $action === 'approve' ? 'approved' : 'rejected';
            $update = $pdo->prepare('UPDATE ads SET status = ? WHERE id = ?');
            $update->execute([$status, $adId]);
            
            // Обновляем данные объявления
            $ad['status'] = $status;
            
            // Редирект для обновления страницы
            header('Location: detail.php?id=' . $id);
            exit;
        } catch (PDOException $e) {
            error_log("Ошибка при модерации объявления: " . $e->getMessage());
        }
    }
}

// Получаем отклики на это объявление (для отображения списка)
$responses = [];
$responses_sql = "
    SELECT 
        r.id,
        r.ad_id,
        r.name,
        r.phone,
        r.user_id,
        r.created_at,
        u.id AS user_id_from_join,
        u.name AS user_name,
        u.phone AS user_phone
    FROM responses r
    LEFT JOIN users u ON r.user_id = u.id
    WHERE r.ad_id = :id
    ORDER BY r.created_at DESC
";

try {
    $responses_stmt = $pdo->prepare($responses_sql);
    $responses_stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $responses_stmt->execute();
    $responses = $responses_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Ошибка при получении откликов: " . $e->getMessage());
    $responses = [];
}
?>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($ad['ads_title']) ?> - Сайт объявлений</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/detail.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">


    
</head>

<body class="detail-page">
    <!-- Общий хедер -->
    <header class="header">
        <div class="container">
            <div class="header-top">
                <div class="logo">
                    <a href="index.php">
                        <img src="images/logo.svg" alt="Логотип" class="logo-image">
                    </a>
                </div>
                <div class="auth-buttons">
                    <?php if ($currentUser): ?>
                        <?php if ($isAdmin): ?>
                            <a href="admin.php" class="auth-btn">Панель модерации</a>
                        <?php endif; ?>
                        <span class="user-welcome">Здравствуйте, <?= e($currentUser['name']) ?></span>
                        <a href="logout.php" class="auth-btn">Выход</a>
                    <?php else: ?>
                        <button class="auth-btn" data-tab="register">Регистрация</button>
                        <button class="auth-btn" data-tab="login">Вход</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <main class="main detail-main">
        <div class="container">
            <div class="detail-wrapper">
                <!-- Левая колонка: фото + отклики -->
                <div class="detail-left-column">
                    <div class="ad-photo-container">
                        <?php 
                        $photo = !empty($ad['ads_photo']) ? trim($ad['ads_photo']) : '';
                        if ($photo) {
                            // Если путь уже содержит /, используем как есть, иначе добавляем images/
                            $photoPath = (strpos($photo, '/') !== false || strpos($photo, '\\') !== false) 
                                ? e($photo) 
                                : 'images/' . e(basename($photo));
                        } else {
                            $photoPath = '';
                        }
                        ?>
                        <?php if ($photoPath): ?>
                            <img src="<?= $photoPath ?>"
                                alt="<?= e($ad['ads_title']) ?>"
                                class="main-ad-photo">
                        <?php else: ?>
                            <div class="no-photo-placeholder">Нет изображения</div>
                        <?php endif; ?>
                    </div>

                    <!-- Откликнувшиеся (под фото) -->
                    <?php if (!$isAdmin): ?>
                        <div class="responses-left-block">
                            <div class="responses-header">
                                <h2 class="block-title">Откликнулись</h2>
                                <?php if (!empty($responses)): ?>
                                    <span class="responses-count"><?= count($responses) ?></span>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($responses)): ?>
                                <div class="responses-list">
                                    <?php foreach ($responses as $response): ?>
                                        <div class="response-person">
                                            <div class="response-person-name"><?= e($response['user_name'] ?? $response['name'] ?? '—') ?></div>
                                            <div class="response-person-phone"><?= e($response['user_phone'] ?? $response['phone'] ?? '—') ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="no-responses">
                                    Пока никто не откликнулся.
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Правая колонка: вся информация -->
                <div class="detail-info-column">
                    <?php if (isset($_GET['error'])): ?>
                        <div style="color: red; margin-bottom: 20px; padding: 12px; background: #ffe6e6; border-radius: 8px;">
                            <?= e($_GET['error']) ?>
                        </div>
                    <?php endif; ?>
                    <!-- Верхний блок: цена + кнопка назад -->
                    <div class="detail-top-block">
                        <div class="price-block">
                            <div class="ad-price-detail"><?= isset($ad['ads_price']) && is_numeric($ad['ads_price']) ? number_format((int)$ad['ads_price'], 0, '', ' ') : '0' ?> ₽</div>
                            <a href="index.php" class="back-to-list-link">
                                ← Назад к списку
                            </a>
                        </div>
                    </div>

                    <!-- Название объявления -->
                    <h1 class="ad-title-detail"><?= e($ad['ads_title']) ?></h1>

                    <!-- Статус объявления для автора (кроме случаев, когда автор — админ) -->
                    <?php if ($isAuthor && !$isAdmin): 
                        $adStatus = $ad['status'] ?? 'pending';
                        $statusText = '';
                        $statusColor = '';
                        if ($adStatus === 'approved') {
                            $statusText = 'Одобрено';
                            $statusColor = '#4CAF50';
                        } elseif ($adStatus === 'rejected') {
                            $statusText = 'Отклонено';
                            $statusColor = '#f44336';
                        } else {
                            $statusText = 'На модерации';
                            $statusColor = '#ff9800';
                        }
                    ?>
                        <div style="margin-bottom: 16px; padding: 12px; background: #f9f9f9; border-radius: 8px; border-left: 4px solid <?= $statusColor ?>;">
                            <span style="font-weight: 500; color: #333;">Статус: </span>
                            <span style="color: <?= $statusColor ?>; font-weight: 600;"><?= $statusText ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- Контакт автора (в одной строке) -->
                    <div class="author-contact-block">
                        <div class="author-contact-line">
                            <?php if ($currentUser): ?>
                                <span class="author-phone"><?= e($ad['user_phone'] ?? '—') ?></span>
                                <span class="author-name"><?= e($ad['user_name'] ?? '—') ?></span>
                            <?php else: ?>
                                <a href="#" onclick="openModal('login'); return false;">Войдите, чтобы увидеть контакты продавца</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Блок модерации для администраторов (только если админ не является автором объявления) -->
                    <?php if ($isAdmin && !$isAuthor): 
                        $adStatus = $ad['status'] ?? 'pending';
                    ?>
                        <div style="margin-bottom: 20px; padding: 16px; background: #f9f9f9; border-radius: 12px; border-left: 4px solid <?= $adStatus === 'approved' ? '#4CAF50' : ($adStatus === 'rejected' ? '#f44336' : '#ff9800') ?>;">
                            <div style="font-weight: 600; margin-bottom: 12px; color: #333;">Статус модерации: 
                                <span style="color: <?= $adStatus === 'approved' ? '#4CAF50' : ($adStatus === 'rejected' ? '#f44336' : '#ff9800') ?>;">
                                    <?= $adStatus === 'approved' ? 'Одобрено' : ($adStatus === 'rejected' ? 'Отклонено' : 'На модерации') ?>
                                </span>
                            </div>
                            <?php if ($adStatus === 'pending'): ?>
                                <div style="display: flex; gap: 12px;">
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Одобрить это объявление?');">
                                        <input type="hidden" name="ad_id" value="<?= $id ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" style="background: #4CAF50; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 500;">Одобрить</button>
                                    </form>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Отклонить это объявление?');">
                                        <input type="hidden" name="ad_id" value="<?= $id ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" style="background: #f44336; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 500;">Отклонить</button>
                                    </form>
                                </div>
                            <?php elseif ($adStatus === 'rejected'): ?>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Одобрить это объявление?');">
                                    <input type="hidden" name="ad_id" value="<?= $id ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" style="background: #4CAF50; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 500;">Одобрить</button>
                                </form>
                            <?php else: ?>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Отклонить это объявление?');">
                                    <input type="hidden" name="ad_id" value="<?= $id ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" style="background: #f44336; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 500;">Отклонить</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Кнопка отклика - только для авторизованных, не автора, и не откликавшихся, и не для администратора -->
                    <?php if (!$isAdmin): ?>
                        <?php if ($currentUser && !$isAuthor && !$hasResponded): ?>
                            <form method="post" action="respond.php" style="display: inline;">
                                <input type="hidden" name="ad_id" value="<?= $id ?>">
                                <button type="submit" class="respond-main-btn">
                                    Откликнуться на объявление
                                </button>
                            </form>
                        <?php elseif ($currentUser && !$isAuthor && $hasResponded): ?>
                            <button class="respond-main-btn responded" disabled>
                                Вы откликнулись на объявление
                            </button>
                        <?php elseif (!$currentUser): ?>
                            <a href="#" onclick="openModal('login'); return false;" class="respond-main-btn" style="text-decoration: none; display: inline-block;">
                                Войдите, чтобы откликнуться
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Описание (только описание, без характеристик) -->
                    <div class="description-block">
                        <div class="description-text">
                            <?= nl2br(e($ad['ads_description'])) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Футер -->
    <footer class="footer">
        <div class="container footer-inner">
            <div class="footer-email">info@gmail.com</div>
            <div class="footer-links">
                <a href="#">Информация о разработчике</a>
            </div>
        </div>
    </footer>

    <!-- Модальное окно авторизации (скрыто по умолчанию) -->
    <div class="modal-overlay" id="authModal">
        <div class="modal-backdrop" onclick="closeModal()"></div>
        <div class="modal">
            <div class="modal-content">
                <!-- Верхняя панель переключения -->
                <div class="auth-tabs">
                    <button class="auth-tab" id="registerTabBtn" onclick="switchTab('register')">Регистрация</button>
                    <button class="auth-tab" id="loginTabBtn" onclick="switchTab('login')">Авторизация</button>
                </div>

                <!-- Форма регистрации -->
                <div class="auth-form-container register-form" id="registerForm">
                    <form class="auth-form" id="registerFormElement" method="post" action="register.php">
                        <div class="form-row">
                            <input name="name" type="text" placeholder="Ваше имя" required class="form-input" id="regName">
                        </div>
                        <div class="form-row form-two-columns">
                            <input name="email" type="email" placeholder="Email" required class="form-input" id="regEmail">
                            <input name="phone" type="tel" placeholder="Мобильный телефон" required class="form-input" id="regPhone">
                        </div>
                        <div class="form-row form-two-columns">
                            <input name="password" type="password" placeholder="Пароль" required class="form-input" id="regPassword">
                            <input type="password" placeholder="Повторите пароль" required class="form-input" id="regConfirmPassword">
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="agree" required class="checkbox-input">
                            <label for="agree" class="checkbox-label">Согласен на обработку персональных данных</label>
                        </div>
                        <button type="submit" class="submit-btn">Зарегистрироваться</button>
                    </form>


                    <div class="form-footer">
                        <p>Все поля обязательны для заполнения</p>
                    </div>
                </div>

                <!-- Форма авторизации -->
                <div class="auth-form-container login-form" id="loginForm" style="display: none;">
                    <form class="auth-form" id="loginFormElement" method="post" action="login.php">
                        <div class="form-row">
                            <input name="email" type="email" placeholder="Email" required class="form-input" id="loginEmail">
                        </div>
                        <div class="form-row">
                            <input name="password" type="password" placeholder="Пароль" required class="form-input" id="loginPassword">
                        </div>
                        <button type="submit" class="submit-btn">Войти</button>
                    </form>

                    <div class="form-footer">
                        <p>Все поля обязательны для заполнения</p>
                    </div>
                </div>

                <button class="close-btn" onclick="closeModal()">×</button>
            </div>
        </div>
    </div>

    <!-- Скрипты для модального окна (только если пользователь не авторизован) -->
    <?php if (!$currentUser): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const authButtons = document.querySelectorAll('.auth-btn[data-tab]');
            authButtons.forEach(btn => {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    const tab = this.getAttribute('data-tab');
                    openModal(tab);
                });
            });

            document.querySelectorAll('.close-btn, .modal-backdrop').forEach(btn => {
                btn.addEventListener('click', closeModal);
            });

            document.querySelectorAll('.auth-tab').forEach(tab => {
                tab.addEventListener('click', function () {
                    const tabId = this.id;
                    switchTab(tabId === 'registerTabBtn' ? 'register' : 'login');
                });
            });

            const registerForm = document.getElementById('registerFormElement');
            if (registerForm) {
                registerForm.addEventListener('submit', handleRegister);
            }

            const loginForm = document.getElementById('loginFormElement');
            if (loginForm) {
                loginForm.addEventListener('submit', handleLogin);
            }

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    closeModal();
                }
            });
        });

        function openModal(tab = 'register') {
            const modal = document.getElementById('authModal');
            if (!modal) return;
            modal.style.display = 'flex';
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            switchTab(tab);
        }

        function closeModal() {
            const modal = document.getElementById('authModal');
            if (!modal) return;
            modal.style.display = 'none';
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }

        function switchTab(tab) {
            const registerForm = document.getElementById('registerForm');
            const loginForm = document.getElementById('loginForm');
            const registerTabBtn = document.getElementById('registerTabBtn');
            const loginTabBtn = document.getElementById('loginTabBtn');

            if (!registerForm || !loginForm) return;

            if (tab === 'register') {
                registerForm.style.display = 'block';
                loginForm.style.display = 'none';
                if (registerTabBtn) registerTabBtn.classList.add('active');
                if (loginTabBtn) loginTabBtn.classList.remove('active');
            } else {
                registerForm.style.display = 'none';
                loginForm.style.display = 'block';
                if (loginTabBtn) loginTabBtn.classList.add('active');
                if (registerTabBtn) registerTabBtn.classList.remove('active');
            }
        }

        function validateName(name) {
            return /^[а-яА-ЯёЁ\s\-]+$/.test(name);
        }

        function validateEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }

        function validatePhone(phone) {
            const cleaned = phone.replace(/\D/g, '');
            return cleaned.length === 11 && (cleaned[0] === '7' || cleaned[0] === '8');
        }

        function validatePassword(password) {
            if (password.length < 6) return false;
            return !/^\d+$/.test(password);
        }

        function handleRegister(event) {
            event.preventDefault();

            const name = document.getElementById('regName')?.value.trim();
            const email = document.getElementById('regEmail')?.value.trim();
            const phone = document.getElementById('regPhone')?.value.trim();
            const password = document.getElementById('regPassword')?.value;
            const confirmPassword = document.getElementById('regConfirmPassword')?.value;
            const agree = document.getElementById('agree')?.checked;

            if (!name || !email || !phone || !password || !confirmPassword) {
                alert('Все поля обязательны для заполнения');
                return false;
            }

            if (!validateName(name)) {
                alert('Имя может содержать только русские буквы, пробелы и дефисы');
                return false;
            }

            if (!validateEmail(email)) {
                alert('Введите корректный email');
                return false;
            }

            if (!validatePhone(phone)) {
                alert('Введите корректный мобильный телефон');
                return false;
            }

            if (!validatePassword(password)) {
                alert('Пароль должен быть не менее 6 символов и не состоять только из цифр');
                return false;
            }

            if (password !== confirmPassword) {
                alert('Пароли не совпадают');
                return false;
            }

            if (!agree) {
                alert('Необходимо согласие на обработку персональных данных');
                return false;
            }

            return true;
        }

        function handleLogin(event) {
            event.preventDefault();

            const email = document.getElementById('loginEmail')?.value.trim();
            const password = document.getElementById('loginPassword')?.value;

            if (!email || !password) {
                alert('Все поля обязательны для заполнения');
                return false;
            }

            if (!validateEmail(email)) {
                alert('Введите корректный email');
                return false;
            }

            return true;
        }
    </script>
    <?php endif; ?>
</body>
</html>