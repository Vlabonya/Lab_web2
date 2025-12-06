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
        users.id AS user_id,
        users.name AS user_name,
        users.phone AS user_phone,
        users.email AS user_email
    FROM ads
    LEFT JOIN users ON ads.user_id = users.id
    WHERE ads.id = :id
    LIMIT 1
";


$stmt = $pdo->prepare($sql);
$stmt->bindParam(':id', $id, PDO::PARAM_INT);
$stmt->execute();

$ad = $stmt->fetch(PDO::FETCH_ASSOC);

// Если объявление не найдено - редирект на главную
if (!$ad) {
    header("Location: index.php");
    exit();
}

// Получаем отклики на это объявление
$responses_sql = "
    SELECT 
        r.id,
        r.ad_id,
        r.name,
        r.phone,
        r.user_id,
        r.created_at,
        u.id AS user_id,
        u.name AS user_name,
        u.phone AS user_phone
    FROM responses r
    LEFT JOIN users u ON r.user_id = u.id
    WHERE r.ad_id = :id
    ORDER BY r.created_at DESC
";


$responses_stmt = $pdo->prepare($responses_sql);
$responses_stmt->bindParam(':id', $id, PDO::PARAM_INT);
$responses_stmt->execute();
$responses = $responses_stmt->fetchAll(PDO::FETCH_ASSOC);
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
                    <button class="auth-btn" data-tab="register">Регистрация</button>
                    <button class="auth-btn" data-tab="login">Вход</button>
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
                        <?php if (!empty($ad['ads_photo'])): ?>
                            <img src="images/<?= e(basename($ad['ads_photo'])) ?>"
                                alt="<?= e($ad['ads_title']) ?>"
                                class="main-ad-photo">
                        <?php else: ?>
                            <div class="no-photo-placeholder">Нет изображения</div>
                        <?php endif; ?>
                    </div>

                    <!-- Откликнувшиеся (под фото) -->
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
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-responses">
                                Пока никто не откликнулся. Будьте первым!
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Правая колонка: вся информация -->
                <div class="detail-info-column">
                    <!-- Верхний блок: цена + кнопка назад -->
                    <div class="detail-top-block">
                        <div class="price-block">
                            <div class="ad-price-detail"><?= number_format($ad['ads_price'], 0, '', ' ') ?> ₽</div>
                            <a href="index.php" class="back-to-list-link">
                                ← Назад к списку
                            </a>
                        </div>
                    </div>

                    <!-- Название объявления -->
                    <h1 class="ad-title-detail"><?= e($ad['ads_title']) ?></h1>

                    <!-- Контакт автора (в одной строке) -->
                    <div class="author-contact-block">
                        <div class="author-contact-line">
                            <?php if (!empty($_SESSION['user_id'])): ?>
                                <span class="author-phone"><?= e($ad['user_phone'] ?? $ad['phone'] ?? '—') ?></span>
                                <span class="author-name"><?= e($ad['user_name'] ?? $ad['name'] ?? '—') ?></span>
                            <?php else: ?>
                                <a href="#" onclick="openModal('login')">Войдите, чтобы увидеть контакты продавца</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- ОДНА КНОПКА ОТКЛИКА -->
                    <button class="respond-main-btn" id="respondButton" data-ad-id="<?= $id ?>">
                        Откликнуться на объявление
                    </button>

                    <!-- Описание (только описание, без характеристик) -->
                    <div class="description-block">
                        <h2 class="block-title">Описание</h2>
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

    <!-- Скрипты -->
    <script>
        // Инициализация для детальной страницы
        document.addEventListener('DOMContentLoaded', function () {
            console.log('Detail page loaded');

            // Проверяем пользователя
            const userData = localStorage.getItem('currentUser');
            if (userData) {
                try {
                    const user = JSON.parse(userData);
                    console.log('User found:', user);
                    // Обновляем хедер если пользователь есть
                    updateHeader(user);
                } catch (e) {
                    console.error('Error parsing user data:', e);
                }
            }

            // Инициализируем кнопку отклика
            const respondBtn = document.getElementById('respondButton');
            if (respondBtn) {
                console.log('Respond button found');

                // Получаем ID объявления из URL или атрибута
                let adId = respondBtn.getAttribute('data-ad-id');
                if (!adId) {
                    // Извлекаем из URL
                    const urlParams = new URLSearchParams(window.location.search);
                    adId = urlParams.get('id');
                }

                if (adId) {
                    console.log('Ad ID:', adId);
                    // Обновляем состояние кнопки
                    updateResponseButton(adId);

                    // Добавляем обработчик
                    respondBtn.addEventListener('click', function () {
                        console.log('Respond button clicked');
                        handleResponseClick(adId);
                    });
                }
            }

            // Инициализируем модальное окно авторизации
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
        });

        // Проверка, откликнулся ли пользователь
        function checkUserResponse(adId) {
            const respondedAds = JSON.parse(localStorage.getItem('respondedAds') || '[]');
            console.log('Checked responses for ad', adId, ':', respondedAds.includes(adId.toString()));
            return respondedAds.includes(adId.toString());
        }

        // Обновление кнопки отклика
        function updateResponseButton(adId) {
            const button = document.getElementById('respondButton');
            if (!button) return;

            console.log('Updating button for ad:', adId);

            if (checkUserResponse(adId)) {
                // Пользователь уже откликнулся
                button.innerHTML = '✓ Вы откликнулись на объявление';
                button.classList.add('responded');
                button.disabled = true;
                console.log('Button updated to responded state');
            } else {
                // Пользователь еще не откликался
                button.innerHTML = 'Откликнуться на объявление';
                button.classList.remove('responded');
                button.disabled = false;
                console.log('Button updated to normal state');
            }
        }

        // Обработка клика по кнопке отклика
        function handleResponseClick(adId) {
            console.log('Handling response for ad:', adId);

            const userData = localStorage.getItem('currentUser');
            if (!userData) {
                alert('Для отклика необходимо войти в систему');
                openModal('login');
                return;
            }

            try {
                const user = JSON.parse(userData);

                // Проверяем, не откликался ли уже
                if (checkUserResponse(adId)) {
                    alert('Вы уже откликнулись на это объявление');
                    return;
                }

                // Получаем кнопку для анимации
                const button = document.getElementById('respondButton');

                // Добавляем анимацию успеха
                button.classList.add('success-animation');

                // Сохраняем отклик
                const respondedAds = JSON.parse(localStorage.getItem('respondedAds') || '[]');
                respondedAds.push(adId.toString());
                localStorage.setItem('respondedAds', JSON.stringify(respondedAds));

                console.log('Response saved for ad:', adId, 'by user:', user.name);

                // Обновляем кнопку через небольшую задержку для плавного перехода
                setTimeout(() => {
                    updateResponseButton(adId);
                    button.classList.remove('success-animation');
                }, 500);

                // Показываем сообщение
                setTimeout(() => {
                    alert('✅ Вы успешно откликнулись на объявление!\n' +
                        'Имя: ' + user.name + '\n' +
                        'Телефон: ' + user.phone);
                }, 600);

                // Обновляем список откликов на странице
                updateResponsesList(user);

            } catch (e) {
                console.error('Error handling response:', e);
                alert('Ошибка при обработке отклика');
            }
        }

        // Обновление списка откликов на странице
        function updateResponsesList(user) {
            const responsesList = document.querySelector('.responses-list');
            if (!responsesList) return;

            const noResponses = document.querySelector('.no-responses');
            if (noResponses) {
                noResponses.style.display = 'none';
            }

            // Создаем новый элемент отклика
            const responseDiv = document.createElement('div');
            responseDiv.className = 'response-person';
            responseDiv.innerHTML = `
                <div class="response-person-name">${user.name}</div>
                <div class="response-person-phone">${user.phone}</div>
            `;

            responsesList.appendChild(responseDiv);

            // Обновляем счетчик
            const countSpan = document.querySelector('.responses-count');
            if (countSpan) {
                const currentCount = parseInt(countSpan.textContent) || 0;
                countSpan.textContent = currentCount + 1;
            }
        }

        // Глобальные функции для модального окна
        function openModal(tab = 'register') {
            console.log('Opening modal for tab:', tab);
            const modal = document.getElementById('authModal');
            if (!modal) return;

            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
            switchTab(tab);
        }

        function closeModal() {
            console.log('Closing modal');
            const modal = document.getElementById('authModal');
            if (!modal) return;

            modal.style.display = 'none';
            document.body.style.overflow = '';
        }

        function switchTab(tab) {
            console.log('Switching to tab:', tab);
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
            console.log('Handling registration');

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
            console.log('User saved:', user);

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
            console.log('Handling login');

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
                // Если email отличается, обновляем его
                user.email = email;
                user.name = email.split('@')[0];
            }

            localStorage.setItem('currentUser', JSON.stringify(user));
            console.log('User logged in:', user);

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

            console.log('Updating header for user:', user ? user.name : 'none');

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
                localStorage.removeItem('respondedAds'); // Также очищаем отклики при выходе
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