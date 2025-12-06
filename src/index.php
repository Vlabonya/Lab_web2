<!DOCTYPE html>
<?php
// Подключаемся к БД через общий файл
require_once "db_connect.php";

// безопасный эскейп — используем везде вместо htmlspecialchars(...)
function e($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Получаем все объявления (используем id как PK)
$sql = "SELECT * FROM ads ORDER BY created_at DESC";
$result = $pdo->prepare($sql);
$result->execute();
?>
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
                <!-- Кнопки авторизации справа (теперь вверху) -->
                <div class="auth-buttons">
                    <button class="auth-btn" data-tab="register-tab">Регистрация</button>
                    <button class="auth-btn" data-tab="login-tab">Вход</button>
                </div>
            </div>
        </div>
    </header>

    <main class="main">
        <div class="container">
            <div class="section-header">
                <h1 class="section-title">Новые объявления</h1>
                <button class="add-btn" onclick="document.getElementById('addDialog') && document.getElementById('addDialog').showModal && document.getElementById('addDialog').showModal();">
                    <span class="add-icon">+</span>
                    <span class="add-text">Добавить объявление</span>
                </button>
            </div>

            <div class="ads-grid">
                <?php while ($row = $result->fetch(PDO::FETCH_ASSOC)) { 
                    // Используем поле id как первичный ключ (в БД у вас id)
                    $adId = isset($row['id']) ? (int)$row['id'] : (isset($row['ads_id']) ? (int)$row['ads_id'] : 0);
                    // безопасное имя файла (basename) и экранирование
                    $photo = !empty($row['ads_photo']) ? e(basename($row['ads_photo'])) : '';
                    $title = e($row['ads_title'] ?? '');
                    $price = isset($row['ads_price']) ? number_format((int)$row['ads_price'], 0, '', ' ') : '0';
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

                        <div class="ad-price"><?= e($price) ?> ₽</div>
                        <div class="ad-title"><?= $title ?></div>
                    </div>
                <?php } ?>
            </div>

            <div class="load-more">
                <button class="show-more-btn">
                    Показать ещё
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#e91e63" stroke-width="2">
                        <path d="M6 9l6 6 6-6" />
                    </svg>
                </button>
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

    <!-- Модальное окно авторизации (скрыто по умолчанию) -->
    <div class="modal-overlay" id="authModal" style="display: none;">
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
                    <form class="auth-form" id="registerFormElement" onsubmit="handleRegister(event)">
                        <div class="form-row">
                            <input type="text" placeholder="Ваше имя" required class="form-input" id="regName">
                        </div>
                        <div class="form-row form-two-columns">
                            <input type="email" placeholder="Email" required class="form-input" id="regEmail">
                            <input type="tel" placeholder="Мобильный телефон" required class="form-input" id="regPhone">
                        </div>
                        <div class="form-row form-two-columns">
                            <input type="password" placeholder="Пароль" required class="form-input" id="regPassword">
                            <input type="password" placeholder="Повторите пароль" required class="form-input"
                                id="regConfirmPassword">
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
                    <form class="auth-form" id="loginFormElement" onsubmit="handleLogin(event)">
                        <div class="form-row">
                            <input type="email" placeholder="Email" required class="form-input" id="loginEmail">
                        </div>
                        <div class="form-row">
                            <input type="password" placeholder="Пароль" required class="form-input" id="loginPassword">
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
            console.log('Main page loaded');

            // Проверяем пользователя
            const userData = localStorage.getItem('currentUser');
            if (userData) {
                try {
                    const user = JSON.parse(userData);
                    updateHeader(user);
                } catch (e) {
                    console.error('Error parsing user data:', e);
                }
            }

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

            // Обработка форм
            const registerForm = document.getElementById('registerFormElement');
            if (registerForm) {
                registerForm.addEventListener('submit', handleRegister);
            }

            const loginForm = document.getElementById('loginFormElement');
            if (loginForm) {
                loginForm.addEventListener('submit', handleLogin);
            }

            // Закрытие по Escape
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    closeModal();
                }
            });

            // Кнопка "Добавить объявление"
            const addBtn = document.querySelector('.add-btn');
            if (addBtn) {
                addBtn.addEventListener('click', function () {
                    const user = JSON.parse(localStorage.getItem('currentUser') || 'null');
                    if (!user) {
                        alert('Для добавления объявления необходимо войти в систему');
                        openModal('login');
                    } else {
                        alert('Функция добавления объявления (в разработке)');
                    }
                });
            }
        });

        // Функции (те же что и в detail.php)
        function openModal(tab = 'register') {
            const modal = document.getElementById('authModal');
            if (!modal) return;

            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
            switchTab(tab);
        }

        function closeModal() {
            const modal = document.getElementById('authModal');
            if (!modal) return;

            modal.style.display = 'none';
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

        function handleRegister(event) {
            event.preventDefault();

            const name = document.getElementById('regName')?.value.trim();
            const email = document.getElementById('regEmail')?.value.trim();
            const phone = document.getElementById('regPhone')?.value.trim();
            const password = document.getElementById('regPassword')?.value;
            const confirmPassword = document.getElementById('regConfirmPassword')?.value;
            const agree = document.getElementById('agree')?.checked;

            // Простая валидация
            if (!name || !email || !phone || !password || !confirmPassword) {
                alert('Все поля обязательны для заполнения');
                return;
            }

            if (password !== confirmPassword) {
                alert('Пароли не совпадают');
                return;
            }

            if (password.length < 6) {
                alert('Пароль должен быть не менее 6 символов');
                return;
            }

            if (!agree) {
                alert('Необходимо согласие на обработку персональных данных');
                return;
            }

            // Сохраняем пользователя
            const user = {
                id: Date.now(),
                name: name,
                email: email,
                phone: phone,
                registeredAt: new Date().toISOString()
            };

            localStorage.setItem('currentUser', JSON.stringify(user));

            // Обновляем хедер
            updateHeader(user);

            // Закрываем модальное окно
            closeModal();

            // Показываем уведомление
            setTimeout(() => {
                alert('Регистрация прошла успешно! Добро пожаловать, ' + name + '!');
            }, 100);
        }

        function handleLogin(event) {
            event.preventDefault();

            const email = document.getElementById('loginEmail')?.value.trim();
            const password = document.getElementById('loginPassword')?.value;

            if (!email || !password) {
                alert('Все поля обязательны для заполнения');
                return;
            }

            // Простая проверка для демо
            if (password.length < 6) {
                alert('Пароль должен быть не менее 6 символов');
                return;
            }

            // Создаем или загружаем пользователя
            let user = JSON.parse(localStorage.getItem('currentUser') || 'null');

            if (!user) {
                user = {
                    id: Date.now(),
                    name: email.split('@')[0],
                    email: email,
                    phone: '+7 (999) 999-99-99',
                    loggedInAt: new Date().toISOString()
                };
            } else if (user.email !== email) {
                user.email = email;
                user.name = email.split('@')[0];
            }

            localStorage.setItem('currentUser', JSON.stringify(user));

            // Обновляем хедер
            updateHeader(user);

            // Закрываем модальное окно
            closeModal();

            // Показываем уведомление
            setTimeout(() => {
                alert('Вход выполнен успешно!');
            }, 100);
        }

        function updateHeader(user) {
            const authButtons = document.querySelector('.auth-buttons');
            if (!authButtons) return;

            if (user) {
                authButtons.innerHTML = `
            <span class="user-welcome">Здравствуйте, ${user.name}</span>
            <button class="auth-btn" onclick="logout()">Выход</button>
        `;
            } else {
                authButtons.innerHTML = `
            <button class="auth-btn" data-tab="register">Регистрация</button>
            <button class="auth-btn" data-tab="login">Вход</button>
        `;
            }
        }

        function logout() {
            if (confirm('Вы уверены, что хотите выйти?')) {
                localStorage.removeItem('currentUser');
                updateHeader(null);
                alert('Вы вышли из системы');

                // Обновляем страницу
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            }
        }
    </script>
</body>

</html>
