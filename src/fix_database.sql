-- Исправление структуры базы данных для корректной работы сайта объявлений
-- Выполните этот скрипт в phpMyAdmin после импорта db_ads.sql

USE db_ads;

-- 1. Исправляем таблицу ads: user_id должен быть NOT NULL
ALTER TABLE `ads` 
  MODIFY `user_id` int NOT NULL;

-- 2. Исправляем внешний ключ ads: должен быть ON DELETE CASCADE вместо SET NULL
ALTER TABLE `ads` 
  DROP FOREIGN KEY `fk_ads_user`;

ALTER TABLE `ads`
  ADD CONSTRAINT `fk_ads_user` FOREIGN KEY (`user_id`) 
  REFERENCES `users` (`id`) ON DELETE CASCADE;

-- 3. Удаляем дублирующее поле ads_id из таблицы responses (оставляем только ad_id)
-- Сначала удаляем данные, которые могут ссылаться на ads_id
UPDATE `responses` SET `ad_id` = COALESCE(`ad_id`, `ads_id`) WHERE `ads_id` IS NOT NULL;

-- Удаляем колонку ads_id (если она есть)
ALTER TABLE `responses` 
  DROP COLUMN `ads_id`;

-- 4. Делаем user_id в responses NOT NULL (для связи с пользователем)
ALTER TABLE `responses` 
  MODIFY `user_id` int NOT NULL;

-- 5. Добавляем внешний ключ для user_id в responses
ALTER TABLE `responses`
  ADD CONSTRAINT `fk_responses_user` FOREIGN KEY (`user_id`) 
  REFERENCES `users` (`id`) ON DELETE CASCADE;

-- 6. Добавляем уникальный индекс для предотвращения дубликатов откликов
-- (один пользователь может откликнуться на одно объявление только один раз)
ALTER TABLE `responses`
  ADD UNIQUE KEY `unique_user_ad_response` (`ad_id`, `user_id`);

-- 7. Обновляем внешний ключ responses для правильной работы
ALTER TABLE `responses` 
  DROP FOREIGN KEY `responses_ibfk_1`;

ALTER TABLE `responses`
  ADD CONSTRAINT `responses_ibfk_1` FOREIGN KEY (`ad_id`) 
  REFERENCES `ads` (`id`) ON DELETE CASCADE;

-- Готово! Структура базы данных обновлена.

