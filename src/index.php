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
        $userStmt = $pdo->prepare('SELECT id, name, email, phone, role FROM users WHERE id = ? LIMIT 1');
        $userStmt->execute([$_SESSION['user_id']]);
        $currentUser = $userStmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Ошибка при получении пользователя: " . $e->getMessage());
    }
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

try {
    $checkColumn = $pdo->query("SHOW COLUMNS FROM ads LIKE 'category'");
    if ($checkColumn->rowCount() == 0) {
        $pdo->exec("ALTER TABLE ads ADD COLUMN category INT DEFAULT NULL");
    } else {
        $columnInfo = $pdo->query("SHOW COLUMNS FROM ads WHERE Field = 'category'")->fetch(PDO::FETCH_ASSOC);
        if ($columnInfo && strpos(strtolower($columnInfo['Type']), 'varchar') !== false) {
            $otherCategory = $pdo->query("SELECT id FROM categories WHERE name = 'Разное' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $defaultCategoryId = $otherCategory ? (int)$otherCategory['id'] : null;
            
            if ($defaultCategoryId) {
                $pdo->exec("UPDATE ads SET category = " . $defaultCategoryId . " WHERE category IS NULL OR category = '' OR category NOT REGEXP '^[0-9]+$'");
            }
            $pdo->exec("ALTER TABLE ads MODIFY COLUMN category INT DEFAULT NULL");
        }
    }
} catch (PDOException $e) {
    error_log("Ошибка при проверке/изменении поля category: " . $e->getMessage());
}

$selectedCategory = isset($_GET['category']) ? (int)$_GET['category'] : 0; 

// Постраничный вывод - получаем первые 10 записей (только одобренные)
$limit = 10;
if ($selectedCategory === 0) {
    $sql = "SELECT * FROM ads WHERE status = 'approved' ORDER BY id DESC LIMIT :limit";
    $stmt = $pdo->prepare($sql);
} else {
    $sql = "SELECT * FROM ads WHERE status = 'approved' AND category = :category ORDER BY id DESC LIMIT :limit";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':category', $selectedCategory, PDO::PARAM_INT);
}
try {
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
    <style>
        .category-filter {
            margin-bottom: 32px;
        }
        .category-select {
            width: 100%;
            max-width: 300px;
            padding: 12px 16px;
            border-radius: 8px;
            border: 2px solid #e0e0e0;
            background: #fff;
            color: #333;
            font-size: 14px;
            font-weight: 500;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.2s;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg width='12' height='8' viewBox='0 0 12 8' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1L6 6L11 1' stroke='%23333' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            padding-right: 40px;
        }
        .category-select:hover {
            border-color: #ff006b;
            color: #ff006b;
        }
        .category-select:focus {
            outline: none;
            border-color: #ff006b;
            box-shadow: 0 0 0 3px rgba(255, 0, 107, 0.1);
        }
        @media (max-width: 768px) {
            .category-filter {
                margin-bottom: 24px;
            }
            .category-select {
                max-width: 100%;
            }
        }
    </style>
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
                        <?php if (($currentUser['role'] ?? 'user') === 'admin'): ?>
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

            <!-- Фильтр по категориям -->
            <div class="category-filter">
                <select class="category-select" id="categorySelect" onchange="window.location.href = this.value ? 'index.php?category=' + this.value : 'index.php'">
                    <option value="0" <?= $selectedCategory === 0 ? 'selected' : '' ?>>Все категории</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>" <?= $selectedCategory === (int)$cat['id'] ? 'selected' : '' ?>>
                            <?= e($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="ads-grid" id="adsGrid">
                <?php if (!empty($ads)): ?>
                    <?php foreach ($ads as $row): 
                        $adId = (int)$row['id'];
                        // Формируем путь к изображению: если в БД просто имя файла, добавляем images/
                        $photo = !empty($row['ads_photo']) ? trim($row['ads_photo']) : '';
                        if ($photo) {
                            // Если путь уже содержит /, используем как есть, иначе добавляем images/
                            $photoPath = (strpos($photo, '/') !== false || strpos($photo, '\\') !== false) 
                                ? e($photo) 
                                : 'images/' . e(basename($photo));
                        } else {
                            $photoPath = '';
                        }
                        $title = e($row['ads_title'] ?? '');
                        $price = isset($row['ads_price']) && is_numeric($row['ads_price']) ? number_format((int)$row['ads_price'], 0, '', ' ') : '0';
                    ?>
                        <div class="ad-card">
                            <div class="ad-img">
                                <a href="detail.php?id=<?= $adId ?>">
                                    <?php if ($photoPath): ?>
                                        <img src="<?= $photoPath ?>" alt="<?= $title ?>" class="ad-image">
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
                <button class="show-more-btn" id="showMoreBtn" data-last-id="<?= $lastId ?>" data-category="<?= $selectedCategory ?>">
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

                        <div id="err-name" class="field-error" style="color:#e91e63;font-size:13px;margin-top:6px"></div>

                        <div class="form-row form-two-columns">
                            <input name="email" type="email" placeholder="Email" required class="form-input" id="regEmail">
                            <input name="phone" type="tel" placeholder="Мобильный телефон" required class="form-input" id="regPhone">
                        </div>

                        <div id="err-email" class="field-error" style="color:#e91e63;font-size:13px;margin-top:6px"></div>
                        <div id="err-phone" class="field-error" style="color:#e91e63;font-size:13px;margin-top:6px"></div>

                        <div class="form-row form-two-columns">
                            <input name="password" type="password" placeholder="Пароль" required class="form-input" id="regPassword">
                            <input name="confirm_password" type="password" placeholder="Повторите пароль" required class="form-input" id="regConfirmPassword">
                        </div>

                        <div id="err-password" class="field-error" style="color:#e91e63;font-size:13px;margin-top:6px"></div>
                        <div id="err-confirm_password" class="field-error" style="color:#e91e63;font-size:13px;margin-top:6px"></div>

                        <div class="checkbox-group">
                            <input type="checkbox" id="agree" name="agree" required class="checkbox-input">
                            <label for="agree" class="checkbox-label">Согласен на обработку персональных данных</label>
                        </div>

                        <button type="submit" class="submit-btn">Зарегистрироваться</button>
                    </form>

                    <div class="form-footer">
                        <p>Все поля обязательны для заполнения</p>
                    </div>

                    <div id="err-server" class="field-error" style="color:#e91e63;font-size:13px;margin-top:10px"></div>

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

                        <div id="err-login-email" class="field-error" style="color:#e91e63;font-size:13px;margin-top:6px"></div>
                        <div id="err-login-password" class="field-error" style="color:#e91e63;font-size:13px;margin-top:6px"></div>
                        <div id="err-server-login" class="field-error" style="color:#e91e63;font-size:13px;margin-top:10px"></div>

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

        // helper: очистка ошибок формы регистрации
        function clearRegisterErrors() {
        ['name','email','phone','password','confirm_password','server'].forEach(f=>{
            const el = document.getElementById('err-' + f);
            if (el) el.textContent = '';
        });
        }

        async function handleRegister(event) {
            event.preventDefault();
            clearRegisterErrors();

            const form = document.getElementById('registerFormElement');
            if (!form) return;
            const formData = new FormData(form);

            try {
                const resp = await fetch('register.php', {
                    method: 'POST',
                    body: formData
                });

                // Защита: если сервер вернул не JSON — покажем в консоли
                const contentType = resp.headers.get('content-type') || '';
                if (!contentType.includes('application/json')) {
                    const txt = await resp.text();
                    console.error('register.php returned non-JSON response:', txt);
                    const errServerEl = document.getElementById('err-server');
                    if (errServerEl) errServerEl.textContent = 'Ошибка сервера (см. консоль)';
                    return;
                }

                const data = await resp.json();

                if (data.success) {
                    location.reload();
                    return;
                }

                if (data.errors) {
                    for (const [field, message] of Object.entries(data.errors)) {
                        const errEl = document.getElementById('err-' + field);
                        if (errEl) {
                            errEl.textContent = message;
                        } else {
                            // общий серверный вывод
                            const errServerEl = document.getElementById('err-server');
                            if (errServerEl) errServerEl.textContent = message;
                        }
                    }
                }
            } catch (err) {
                console.error('Register fetch error', err);
                const errServerEl = document.getElementById('err-server');
                if (errServerEl) errServerEl.textContent = 'Ошибка сети';
            }
        }


        async function handleLogin(event) {
            event.preventDefault();

            // очистим ошибки (логин)
            const errLoginEmail = document.getElementById('err-login-email');
            const errLoginPassword = document.getElementById('err-login-password');
            const errLoginServer = document.getElementById('err-server-login');
            if (errLoginEmail) errLoginEmail.textContent = '';
            if (errLoginPassword) errLoginPassword.textContent = '';
            if (errLoginServer) errLoginServer.textContent = '';

            const form = document.getElementById('loginFormElement');
            if (!form) return;
            const formData = new FormData(form);

            try {
                const resp = await fetch('login.php', { method: 'POST', body: formData });
                const contentType = resp.headers.get('content-type') || '';
                if (!contentType.includes('application/json')) {
                    const txt = await resp.text();
                    console.error('login.php returned non-JSON:', txt);
                    if (errLoginServer) errLoginServer.textContent = 'Ошибка сервера';
                    return;
                }
                const data = await resp.json();
                if (data.success) { location.reload(); return; }
                if (data.errors) {
                    // map server keys -> login error elements
                    if (data.errors.email && errLoginEmail) errLoginEmail.textContent = data.errors.email;
                    if (data.errors.password && errLoginPassword) errLoginPassword.textContent = data.errors.password;
                    if (data.errors.server && errLoginServer) errLoginServer.textContent = data.errors.server;
                }
            } catch (err) {
                console.error('Login fetch error', err);
                if (errLoginServer) errLoginServer.textContent = 'Ошибка сети';
            }
        }

        // Постраничный вывод
        function loadMoreAds() {
            const btn = document.getElementById('showMoreBtn');
            const grid = document.getElementById('adsGrid');
            if (!btn || !grid) return;

            const lastId = btn.getAttribute('data-last-id');
            const category = btn.getAttribute('data-category') || '0';

            const urlParams = new URLSearchParams();
            if (lastId) urlParams.append('last_id', lastId);
            if (category && category !== '0') urlParams.append('category', category);
            
            const url = `load_more.php?${urlParams.toString()}`;

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

                    // Добавляем новые карточки (безопасно)
                    data.ads.forEach(ad => {
                        const card = document.createElement('div');
                        card.className = 'ad-card';
                        
                        // Формируем путь к изображению: если просто имя файла, добавляем images/
                        let photoPath = '';
                        if (ad.ads_photo && ad.ads_photo.trim()) {
                            const photo = ad.ads_photo.trim();
                            if (photo.includes('/') || photo.includes('\\')) {
                                photoPath = escapeHtml(photo);
                            } else {
                                photoPath = 'images/' + escapeHtml(photo);
                            }
                        }
                        
                        const imgHtml = photoPath
                            ? `<img src="${photoPath}" class="ad-image" alt="${escapeHtml(ad.ads_title || '')}">`
                            : `<div class="no-photo-placeholder">Нет изображения</div>`;

                        const price = ad.ads_price ? parseInt(ad.ads_price).toLocaleString('ru-RU').replace(/,/g, ' ') : '0';

                        card.innerHTML = `
                            <div class="ad-img">
                                <a href="detail.php?id=${ad.id}">
                                    ${imgHtml}
                                </a>
                            </div>
                            <div class="ad-price">${price} ₽</div>
                            <div class="ad-title">${escapeHtml(ad.ads_title || '')}</div>
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

        function escapeHtml(s) {
            return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }
    </script>
</body>
</html>