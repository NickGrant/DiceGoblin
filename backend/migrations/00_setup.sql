-- Dice Goblins — MySQL Schema (MVP)
-- Generated: 2026-01-11 08:27:46
-- Source: design-docs-final/09-data-model.md
--
-- Notes:
-- - Intended for MySQL 8.0+
-- - Default charset/collation: utf8mb4 / utf8mb4_unicode_ci
-- - Foreign keys use RESTRICT (application is expected to manage lifecycle deletes)

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- Optional: create and select DB
-- CREATE DATABASE IF NOT EXISTS `dice_goblins` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE `dice_goblins`;

-- Safety for repeated runs (comment out if you do not want drops)
SET FOREIGN_KEY_CHECKS=0;
