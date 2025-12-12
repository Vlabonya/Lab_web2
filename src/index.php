<?php
declare(strict_types=1);
session_start();
require_once "db_connect.php";

// безопасный эскейп — используем везде вместо htmlspecialchars(...)
function e($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Получаем информацию о текущем пользователе
$currentUser = null;
if (!empty($_SESSION['user_id'])) {
    try {
        $userStmt = $pdo->prepare('SELECT id, name, email, phone FROM users WHERE id = ? LIMIT 1');
        $userStmt->execute([$_SESSION['user_id']]);
        $currentUser = $userStmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Ошибка при получении пользователя: " . $e->getMessage());
    }
}

// Постраничный вывод - получаем первые 10 записей
$limit = 10;
$sql = "SELECT * FROM ads ORDER BY id DESC LIMIT :limit"; // сортировка, ASC - сначала старые (ID 1,2,3..), DESC - новые
try {
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $ads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $lastId = !empty($ads) ? (int)$ads[count($ads) - 1]['id'] : 0;
} catch (PDOException $e) {
    error_log("Ошибка при получении объявлений: " . $e->getMessage());
    $ads = [];
    $lastId = 0;
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Сайт объявлений</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
    <header class="header">
        <div class="container">
            <div class="header-top">
                <!-- Логотип слева -->
                <div class="logo">
                    <a href="index.php"><img src="images/logo.svg" alt="Логотип" class="logo-image"></a>
                </div>
                <!-- Кнопки авторизации справа -->
                <div class="auth-buttons">
                    <?php if ($currentUser): ?>
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

    <main class="main">
        <div class="container">
            <div class="section-header">
                <h1 class="section-title">Новые объявления</h1>
                <?php if ($currentUser): ?>
                    <a href="add.php" class="add-btn">
                        <span class="add-icon">
                            <img src="images/plus.svg" alt="+" />
                        </span>
                        <span class="add-text">Добавить объявление</span>
                    </a>
                <?php else: ?>
                    <button class="add-btn" id="addBtn">
                        <span class="add-icon">
                            <img src="images/plus.svg" alt="+" />
                        </span>
                        <span class="add-text">Добавить объявление</span>
                    </button>
                <?php endif; ?>
            </div>

            <div class="ads-grid" id="adsGrid">
                <?php if (!empty($ads)): ?>
                    <?php foreach ($ads as $row): 
                        $adId = (int)$row['id'];
                        $photo = !empty($row['ads_photo']) ? e(basename($row['ads_photo'])) : '';
                        $title = e($row['ads_title'] ?? '');
                        $price = isset($row['ads_price']) && is_numeric($row['ads_price']) ? number_format((int)$row['ads_price'], 0, '', ' ') : '0';
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
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #999;">
                        Объявлений пока нет.
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($ads) && count($ads) >= $limit): ?>
                <button class="show-more-btn" id="showMoreBtn" data-last-id="<?= $lastId ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#e91e63" stroke-width="2">
                        <path d="M6 9l6 6 6-6" />
                    </svg>
                    Показать ещё
                </button>
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
                    <?php if (isset($_GET['reg_error'])): ?>
                        <div style="color: red; margin-bottom: 15px; padding: 10px; background: #ffe6e6; border-radius: 5px;">
                            <?= e($_GET['reg_error']) ?>
                        </div>
                    <?php endif; ?>
                    <form class="auth-form" id="registerFormElement" onsubmit="handleRegister(event)">
                        <div class="form-row">
                            <input name="name" type="text" placeholder="Ваше имя" required class="form-input" id="regName">
                        </div>
                        <div class="form-row form-two-columns">
                            <input name="email" type="email" placeholder="Email" required class="form-input" id="regEmail">
                            <input name="phone" type="tel" placeholder="Мобильный телефон" required class="form-input" id="regPhone">
                        </div>
                        <div class="form-row form-two-columns">
                            <input name="password" type="password" placeholder="Пароль" required class="form-input" id="regPassword">
                            <input name="confirm_password" type="password" placeholder="Повторите пароль" required class="form-input" id="regConfirmPassword">
                        </div>

                        <div class="checkbox-group">
                            <input type="checkbox" id="agree" name="agree" required class="checkbox-input">
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
                    <?php if (isset($_GET['login_error'])): ?>
                        <div style="color: red; margin-bottom: 15px; padding: 10px; background: #ffe6e6; border-radius: 5px;">
                            <?= e($_GET['login_error']) ?>
                        </div>
                    <?php endif; ?>
                    <form class="auth-form" id="loginFormElement" onsubmit="handleLogin(event)">
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

    <script>
        // Инициализация для главной страницы
        document.addEventListener('DOMContentLoaded', function () {
            // Инициализируем кнопки авторизации
            const authButtons = document.querySelectorAll('.auth-btn[data-tab]');
            authButtons.forEach(btn => {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    const tab = this.getAttribute('data-tab');
                    openModal(tab);
                });
            });

            // Закрытие модального окна
            document.querySelectorAll('.close-btn, .modal-backdrop').forEach(btn => {
                btn.addEventListener('click', closeModal);
            });

            // Переключение вкладок
            document.querySelectorAll('.auth-tab').forEach(tab => {
                tab.addEventListener('click', function () {
                    const tabId = this.id;
                    switchTab(tabId === 'registerTabBtn' ? 'register' : 'login');
                });
            });

            // Закрытие по Escape
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    closeModal();
                }
            });

            // Кнопка "Добавить объявление" для неавторизованных
            const addBtn = document.getElementById('addBtn');
            if (addBtn) {
                addBtn.addEventListener('click', function () {
                    openModal('login');
                });
            }

            // Кнопка "Показать ещё" для постраничного вывода
            const showMoreBtn = document.getElementById('showMoreBtn');
            if (showMoreBtn) {
                showMoreBtn.addEventListener('click', loadMoreAds);
            }
        });

        // Функции модального окна
        function openModal(tab = 'register') {
            const modal = document.getElementById('authModal');
            if (!modal) return;
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            switchTab(tab);
        }

        function closeModal() {
            const modal = document.getElementById('authModal');
            if (!modal) return;
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

        // Валидация форм
        function validateName(name) {
            return /^[а-яА-ЯёЁ\s\-]+$/.test(name);
        }

        function validateEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }

        function validatePhone(phone) {
            // Мобильный телефон: +7 или 8, затем 10 цифр
            const cleaned = phone.replace(/\D/g, '');
            return cleaned.length === 11 && (cleaned[0] === '7' || cleaned[0] === '8');
        }

        function validatePassword(password) {
            if (password.length < 6) return false;
            // Пароль не может состоять только из цифр
            return !/^\d+$/.test(password);
        }

        async function handleRegister(event) {
            event.preventDefault();

            const form = document.getElementById('registerFormElement');
            const formData = new FormData(form);

            const resp = await fetch('register.php', {
                method: 'POST',
                body: formData
            });

            const data = await resp.json();

            if (data.success) {
                location.reload();
                return;
            }

            // обработка ошибок
            if (data.errors) {
                const field = Object.keys(data.errors)[0];
                alert(data.errors[field]);
            }
        }

        async function handleLogin(event) {
            event.preventDefault();

            const form = document.getElementById('loginFormElement');
            const formData = new FormData(form);

            const resp = await fetch('login.php', {
                method: 'POST',
                body: formData
            });

            const data = await resp.json();

            if (data.success) {
                location.reload();
                return;
            }

            if (data.errors) {
                const field = Object.keys(data.errors)[0];
                alert(data.errors[field]);
            }
        }


        // Постраничный вывод
        function loadMoreAds() {
            const btn = document.getElementById('showMoreBtn');
            const grid = document.getElementById('adsGrid');
            if (!btn || !grid) return;

            const lastId = btn.getAttribute('data-last-id');

            const url = lastId
                ? `load_more.php?last_id=${encodeURIComponent(lastId)}`
                : `load_more.php`;

            btn.disabled = true;
            btn.textContent = 'Загрузка...';

            fetch(url)
                .then(r => r.json())
                .then(data => {

                    if (!data.success) {
                        btn.disabled = false;
                        btn.textContent = 'Показать ещё';
                        return;
                    }

                    // Добавляем новые карточки
                    data.ads.forEach(ad => {
                        const card = document.createElement('div');
                        card.className = 'ad-card';
                        card.innerHTML = `
                            <div class="ad-img">
                                <a href="detail.php?id=${ad.id}">
                                    <img src="images/${ad.ads_photo}" class="ad-image">
                                </a>
                            </div>
                            <div class="ad-price">${ad.ads_price} ₽</div>
                            <div class="ad-title">${ad.ads_title}</div>
                        `;
                        grid.appendChild(card);
                    });

                    // Обновляем last_id
                    if (data.last_id) {
                        btn.setAttribute('data-last-id', data.last_id);
                    }

                    if (!data.has_more) {
                        btn.style.display = 'none';
                    } else {
                        btn.disabled = false;
                        btn.textContent = 'Показать ещё';
                    }
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.textContent = 'Показать ещё';
                });
        }
    </script>
</body>
</html>