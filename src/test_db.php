<?php
require_once "config.php";

try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8';
    $pdo = new PDO($dsn, DB_USER, DB_PASSWORD);
    echo "<h1 style='color: green;'>✅ Подключение к БД успешно!</h1>";
    
    // Проверим таблицы
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h3>Таблицы в базе:</h3>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>$table</li>";
    }
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<h1 style='color: red;'>❌ Ошибка: " . $e->getMessage() . "</h1>";
}
?>