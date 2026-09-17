-- ============================================================================
-- ACDS Library — new tables for Journals & Thesis and E-Resources
-- Generated 2026-09-16 from the live dev schema (MySQL 8, InnoDB, utf8mb4).
-- Safe to run standalone against any server already running this app's DB —
-- run the 5 CREATE TABLE statements in this order (child tables reference
-- their parent via FOREIGN KEY).
-- ============================================================================

-- ── Journals & Thesis ────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `journals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `publisher_authors` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issn` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `year` smallint unsigned DEFAULT NULL,
  `call_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shelf_location` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cover_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint unsigned DEFAULT NULL,
  `file_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `journal_articles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `journal_id` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `authors` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `page_range` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `journal_articles_journal_id_position_index` (`journal_id`,`position`),
  CONSTRAINT `journal_articles_journal_id_foreign` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `journal_downloads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `journal_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `journal_downloads_user_id_foreign` (`user_id`),
  KEY `journal_downloads_journal_id_created_at_index` (`journal_id`,`created_at`),
  CONSTRAINT `journal_downloads_journal_id_foreign` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `journal_downloads_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── E-Resources ──────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `e_resources` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `authors` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `publisher` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `year` smallint unsigned DEFAULT NULL,
  `isbn` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `edition` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `call_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shelf_location` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_area` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `language` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'English',
  `format` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cover_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` bigint unsigned DEFAULT NULL,
  `file_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `e_resource_downloads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `e_resource_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `e_resource_downloads_user_id_foreign` (`user_id`),
  KEY `e_resource_downloads_e_resource_id_created_at_index` (`e_resource_id`,`created_at`),
  CONSTRAINT `e_resource_downloads_e_resource_id_foreign` FOREIGN KEY (`e_resource_id`) REFERENCES `e_resources` (`id`) ON DELETE CASCADE,
  CONSTRAINT `e_resource_downloads_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- OPTIONAL — only needed if this server also runs the Laravel app and you
-- want `php artisan migrate` to recognise these 5 tables as already applied
-- (otherwise it will try to create them again and fail on the next deploy).
-- Skip this block if you only wanted the raw schema.
-- ============================================================================

SET @next_batch := (SELECT COALESCE(MAX(batch), 0) FROM `migrations`) + 1;
INSERT INTO `migrations` (`migration`, `batch`) VALUES
  ('2026_09_16_111004_create_journals_table',         @next_batch),
  ('2026_09_16_111005_create_journal_articles_table',  @next_batch),
  ('2026_09_16_111005_create_journal_downloads_table', @next_batch);

SET @next_batch := @next_batch + 1;
INSERT INTO `migrations` (`migration`, `batch`) VALUES
  ('2026_09_16_122558_create_e_resources_table',          @next_batch),
  ('2026_09_16_122559_create_e_resource_downloads_table', @next_batch);
