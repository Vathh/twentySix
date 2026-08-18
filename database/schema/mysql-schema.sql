/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `achievements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `achievements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tournament_id` bigint(20) unsigned DEFAULT NULL,
  `player_id` bigint(20) unsigned DEFAULT NULL,
  `type` enum('max','one_seventy','hf','qf') NOT NULL,
  `value` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `achievements_tournament_id_foreign` (`tournament_id`),
  KEY `achievements_player_id_foreign` (`player_id`),
  CONSTRAINT `achievements_player_id_foreign` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE SET NULL,
  CONSTRAINT `achievements_tournament_id_foreign` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `friendship_invitations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `friendship_invitations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sender_id` bigint(20) unsigned NOT NULL,
  `receiver_id` bigint(20) unsigned NOT NULL,
  `status` enum('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
  `responded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `friendship_invitations_sender_id_receiver_id_unique` (`sender_id`,`receiver_id`),
  KEY `friendship_invitations_receiver_id_status_index` (`receiver_id`,`status`),
  KEY `friendship_invitations_sender_id_status_index` (`sender_id`,`status`),
  CONSTRAINT `friendship_invitations_receiver_id_foreign` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `friendship_invitations_sender_id_foreign` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `friendships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `friendships` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `friend_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `friendships_user_id_friend_id_unique` (`user_id`,`friend_id`),
  KEY `friendships_friend_id_foreign` (`friend_id`),
  KEY `friendships_user_id_friend_id_index` (`user_id`,`friend_id`),
  CONSTRAINT `friendships_friend_id_foreign` FOREIGN KEY (`friend_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `friendships_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `game_leg_player_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `game_leg_player_stats` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `game_leg_id` bigint(20) unsigned NOT NULL,
  `player_id` bigint(20) unsigned NOT NULL,
  `leg_average` decimal(6,2) DEFAULT NULL,
  `first_nine_average` decimal(6,2) DEFAULT NULL,
  `highest_visit` smallint(5) unsigned DEFAULT NULL,
  `highest_finish` smallint(5) unsigned DEFAULT NULL,
  `darts_thrown` smallint(5) unsigned DEFAULT NULL,
  `checkout_dart` tinyint(3) unsigned DEFAULT NULL,
  `double_tracked` tinyint(1) NOT NULL DEFAULT 0,
  `double_attempts` smallint(5) unsigned DEFAULT NULL,
  `double_successes` smallint(5) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `game_leg_player_stats_game_leg_id_player_id_unique` (`game_leg_id`,`player_id`),
  KEY `game_leg_player_stats_player_id_foreign` (`player_id`),
  CONSTRAINT `game_leg_player_stats_game_leg_id_foreign` FOREIGN KEY (`game_leg_id`) REFERENCES `game_legs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `game_leg_player_stats_player_id_foreign` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `game_legs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `game_legs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `game_id` bigint(20) unsigned DEFAULT NULL,
  `playoff_game_id` bigint(20) unsigned DEFAULT NULL,
  `quick_game_id` bigint(20) unsigned DEFAULT NULL,
  `leg_number` int(10) unsigned NOT NULL,
  `player1_score` int(10) unsigned NOT NULL DEFAULT 0,
  `player2_score` int(10) unsigned NOT NULL DEFAULT 0,
  `winner_id` bigint(20) unsigned DEFAULT NULL,
  `player1_average` int(10) unsigned DEFAULT NULL,
  `player2_average` int(10) unsigned DEFAULT NULL,
  `player1_darts_thrown` int(10) unsigned DEFAULT NULL,
  `player2_darts_thrown` int(10) unsigned DEFAULT NULL,
  `checkout_score` int(10) unsigned DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `finished_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `game_legs_winner_id_foreign` (`winner_id`),
  KEY `game_legs_game_id_leg_number_index` (`game_id`,`leg_number`),
  KEY `game_legs_playoff_game_id_leg_number_index` (`playoff_game_id`,`leg_number`),
  KEY `game_legs_quick_game_id_leg_number_index` (`quick_game_id`,`leg_number`),
  CONSTRAINT `game_legs_game_id_foreign` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE SET NULL,
  CONSTRAINT `game_legs_playoff_game_id_foreign` FOREIGN KEY (`playoff_game_id`) REFERENCES `playoff_games` (`id`) ON DELETE SET NULL,
  CONSTRAINT `game_legs_quick_game_id_foreign` FOREIGN KEY (`quick_game_id`) REFERENCES `quick_games` (`id`) ON DELETE SET NULL,
  CONSTRAINT `game_legs_winner_id_foreign` FOREIGN KEY (`winner_id`) REFERENCES `players` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `game_visits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `game_visits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `game_leg_id` bigint(20) unsigned NOT NULL,
  `player_id` bigint(20) unsigned NOT NULL,
  `visit_number` int(10) unsigned NOT NULL,
  `score` smallint(5) unsigned NOT NULL,
  `remaining_before` smallint(5) unsigned NOT NULL,
  `remaining_after` smallint(5) unsigned NOT NULL,
  `darts_in_visit` tinyint(3) unsigned NOT NULL DEFAULT 3,
  `closed_leg` tinyint(1) NOT NULL DEFAULT 0,
  `bust` tinyint(1) NOT NULL DEFAULT 0,
  `is_voided` tinyint(1) NOT NULL DEFAULT 0,
  `client_visit_id` char(36) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `game_visits_client_visit_id_unique` (`client_visit_id`),
  KEY `game_visits_player_id_foreign` (`player_id`),
  KEY `game_visits_game_leg_id_is_voided_visit_number_index` (`game_leg_id`,`is_voided`,`visit_number`),
  CONSTRAINT `game_visits_game_leg_id_foreign` FOREIGN KEY (`game_leg_id`) REFERENCES `game_legs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `game_visits_player_id_foreign` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `games`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `games` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tournament_id` bigint(20) unsigned DEFAULT NULL,
  `player1_id` bigint(20) unsigned DEFAULT NULL,
  `player2_id` bigint(20) unsigned DEFAULT NULL,
  `player1_score` int(10) unsigned DEFAULT NULL,
  `player2_score` int(10) unsigned DEFAULT NULL,
  `player1_legs_in_set` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `player2_legs_in_set` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `current_set_number` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `winner_id` bigint(20) unsigned DEFAULT NULL,
  `group_number` int(10) unsigned NOT NULL,
  `status` enum('scheduled','in_progress','finished') NOT NULL DEFAULT 'scheduled',
  `starting_score` smallint(5) unsigned NOT NULL DEFAULT 501,
  `legs_to_win_set` tinyint(3) unsigned NOT NULL DEFAULT 2,
  `sets_to_win_match` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `game_type` varchar(20) NOT NULL DEFAULT 'x01',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `games_tournament_id_foreign` (`tournament_id`),
  KEY `games_player1_id_foreign` (`player1_id`),
  KEY `games_player2_id_foreign` (`player2_id`),
  KEY `games_winner_id_foreign` (`winner_id`),
  CONSTRAINT `games_player1_id_foreign` FOREIGN KEY (`player1_id`) REFERENCES `players` (`id`) ON DELETE SET NULL,
  CONSTRAINT `games_player2_id_foreign` FOREIGN KEY (`player2_id`) REFERENCES `players` (`id`) ON DELETE SET NULL,
  CONSTRAINT `games_tournament_id_foreign` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `games_winner_id_foreign` FOREIGN KEY (`winner_id`) REFERENCES `players` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_standings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `group_standings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tournament_id` bigint(20) unsigned NOT NULL,
  `group_number` int(10) unsigned NOT NULL,
  `player_id` bigint(20) unsigned NOT NULL,
  `games_played` int(10) unsigned NOT NULL DEFAULT 0,
  `games_won` int(10) unsigned NOT NULL DEFAULT 0,
  `games_lost` int(10) unsigned NOT NULL DEFAULT 0,
  `match_units_won` int(11) NOT NULL DEFAULT 0,
  `match_units_lost` int(11) NOT NULL DEFAULT 0,
  `points` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `place` int(10) unsigned NOT NULL DEFAULT 0,
  `match_units_difference` int(11) GENERATED ALWAYS AS (`match_units_won` - `match_units_lost`) VIRTUAL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_standing_per_group` (`tournament_id`,`group_number`,`player_id`),
  KEY `group_standings_player_id_foreign` (`player_id`),
  CONSTRAINT `group_standings_player_id_foreign` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `group_standings_tournament_id_foreign` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `organization_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `organization_user` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `organization_user_organization_id_user_id_unique` (`organization_id`,`user_id`),
  KEY `organization_user_user_id_foreign` (`user_id`),
  CONSTRAINT `organization_user_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `organization_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `organization_user_admin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `organization_user_admin` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `organization_user_admin_organization_id_foreign` (`organization_id`),
  KEY `organization_user_admin_user_id_foreign` (`user_id`),
  CONSTRAINT `organization_user_admin_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `organization_user_admin_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `organizations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `organizations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `description` text DEFAULT NULL,
  `match_format_presets` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`match_format_presets`)),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `login_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_codes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `tournament_id` bigint(20) unsigned NOT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `login_codes_code_unique` (`code`),
  KEY `login_codes_tournament_id_foreign` (`tournament_id`),
  CONSTRAINT `login_codes_tournament_id_foreign` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `player_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `player_stats` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `player_id` bigint(20) unsigned NOT NULL,
  `quick_games` int(10) unsigned NOT NULL DEFAULT 0,
  `quick_avg_three_darts` decimal(6,2) DEFAULT NULL,
  `quick_highest_hf` int(10) unsigned DEFAULT NULL,
  `quick_fastest_qf` int(10) unsigned DEFAULT NULL,
  `quick_count_max` int(10) unsigned NOT NULL DEFAULT 0,
  `quick_count_170_plus` int(10) unsigned NOT NULL DEFAULT 0,
  `quick_count_hf` int(10) unsigned NOT NULL DEFAULT 0,
  `quick_count_qf` int(10) unsigned NOT NULL DEFAULT 0,
  `tournament_games` int(10) unsigned NOT NULL DEFAULT 0,
  `tournament_avg_three_darts` decimal(6,2) DEFAULT NULL,
  `tournament_highest_hf` int(10) unsigned DEFAULT NULL,
  `tournament_fastest_qf` int(10) unsigned DEFAULT NULL,
  `tournament_count_max` int(10) unsigned NOT NULL DEFAULT 0,
  `tournament_count_170_plus` int(10) unsigned NOT NULL DEFAULT 0,
  `tournament_count_hf` int(10) unsigned NOT NULL DEFAULT 0,
  `tournament_count_qf` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `player_stats_player_id_unique` (`player_id`),
  CONSTRAINT `player_stats_player_id_foreign` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `players`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `players` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `organization_id` bigint(20) unsigned DEFAULT NULL,
  `season_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `players_user_id_unique` (`user_id`),
  KEY `players_organization_id_foreign` (`organization_id`),
  KEY `players_season_id_foreign` (`season_id`),
  CONSTRAINT `players_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `players_season_id_foreign` FOREIGN KEY (`season_id`) REFERENCES `seasons` (`id`) ON DELETE SET NULL,
  CONSTRAINT `players_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `playoff_games`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `playoff_games` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tournament_id` bigint(20) unsigned NOT NULL,
  `round` varchar(20) NOT NULL,
  `slot` varchar(20) NOT NULL,
  `player1_id` bigint(20) unsigned DEFAULT NULL,
  `player2_id` bigint(20) unsigned DEFAULT NULL,
  `player1_score` tinyint(3) unsigned DEFAULT NULL,
  `player2_score` tinyint(3) unsigned DEFAULT NULL,
  `player1_legs_in_set` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `player2_legs_in_set` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `current_set_number` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `winner_id` bigint(20) unsigned DEFAULT NULL,
  `winner_destination_slot` varchar(30) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'scheduled',
  `starting_score` smallint(5) unsigned NOT NULL DEFAULT 501,
  `legs_to_win_set` tinyint(3) unsigned NOT NULL DEFAULT 2,
  `sets_to_win_match` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `game_type` varchar(20) NOT NULL DEFAULT 'x01',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `playoff_games_tournament_id_slot_unique` (`tournament_id`,`slot`),
  KEY `playoff_games_player1_id_foreign` (`player1_id`),
  KEY `playoff_games_player2_id_foreign` (`player2_id`),
  KEY `playoff_games_winner_id_foreign` (`winner_id`),
  CONSTRAINT `playoff_games_player1_id_foreign` FOREIGN KEY (`player1_id`) REFERENCES `players` (`id`) ON DELETE SET NULL,
  CONSTRAINT `playoff_games_player2_id_foreign` FOREIGN KEY (`player2_id`) REFERENCES `players` (`id`) ON DELETE SET NULL,
  CONSTRAINT `playoff_games_tournament_id_foreign` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `playoff_games_winner_id_foreign` FOREIGN KEY (`winner_id`) REFERENCES `players` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `point_scheme_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `point_scheme_rules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `point_scheme_id` bigint(20) unsigned NOT NULL,
  `elimination_stage` varchar(255) NOT NULL,
  `place` smallint(5) unsigned DEFAULT NULL,
  `points` smallint(5) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_point_scheme_rules` (`point_scheme_id`,`elimination_stage`,`place`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `point_schemes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `point_schemes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `min_players` smallint(5) unsigned NOT NULL,
  `max_players` smallint(5) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `quick_game_ffa_presence`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quick_game_ffa_presence` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ffa_session_id` bigint(20) unsigned NOT NULL,
  `player_id` bigint(20) unsigned NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'connected',
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `left_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `qg_ffa_presence_sess_player_unique` (`ffa_session_id`,`player_id`),
  KEY `quick_game_ffa_presence_player_id_foreign` (`player_id`),
  CONSTRAINT `quick_game_ffa_presence_ffa_session_id_foreign` FOREIGN KEY (`ffa_session_id`) REFERENCES `quick_game_ffa_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quick_game_ffa_presence_player_id_foreign` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `quick_game_ffa_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quick_game_ffa_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `lobby_id` bigint(20) unsigned NOT NULL,
  `legs_to_win_set` tinyint(3) unsigned NOT NULL DEFAULT 2,
  `sets_to_win_match` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `game_type` varchar(20) NOT NULL DEFAULT '501',
  `scoring_mode` varchar(20) NOT NULL DEFAULT 'each_own',
  `starting_score` smallint(5) unsigned NOT NULL DEFAULT 501,
  `status` varchar(20) NOT NULL DEFAULT 'in_progress',
  `player_order` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`player_order`)),
  `legs_won_in_set` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`legs_won_in_set`)),
  `sets_won` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`sets_won`)),
  `cricket_state` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`cricket_state`)),
  `leg_opener_index` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `current_player_index` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `current_leg_number` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `current_set_number` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `state_version` int(10) unsigned NOT NULL DEFAULT 1,
  `quick_game_id` bigint(20) unsigned DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `finished_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quick_game_ffa_sessions_lobby_id_unique` (`lobby_id`),
  KEY `quick_game_ffa_sessions_quick_game_id_foreign` (`quick_game_id`),
  CONSTRAINT `quick_game_ffa_sessions_lobby_id_foreign` FOREIGN KEY (`lobby_id`) REFERENCES `quick_game_lobbies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quick_game_ffa_sessions_quick_game_id_foreign` FOREIGN KEY (`quick_game_id`) REFERENCES `quick_games` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `quick_game_ffa_visits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quick_game_ffa_visits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ffa_session_id` bigint(20) unsigned NOT NULL,
  `leg_number` tinyint(3) unsigned NOT NULL,
  `player_id` bigint(20) unsigned NOT NULL,
  `visit_number` int(10) unsigned NOT NULL,
  `score` smallint(5) unsigned NOT NULL,
  `remaining_before` smallint(5) unsigned NOT NULL,
  `remaining_after` smallint(5) unsigned NOT NULL,
  `darts_in_visit` tinyint(3) unsigned NOT NULL DEFAULT 3,
  `closed_leg` tinyint(1) NOT NULL DEFAULT 0,
  `bust` tinyint(1) NOT NULL DEFAULT 0,
  `is_voided` tinyint(1) NOT NULL DEFAULT 0,
  `client_visit_id` char(36) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quick_game_ffa_visits_client_visit_id_unique` (`client_visit_id`),
  KEY `quick_game_ffa_visits_player_id_foreign` (`player_id`),
  KEY `qg_ffa_visits_sess_leg_idx` (`ffa_session_id`,`leg_number`,`is_voided`,`visit_number`),
  CONSTRAINT `quick_game_ffa_visits_ffa_session_id_foreign` FOREIGN KEY (`ffa_session_id`) REFERENCES `quick_game_ffa_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quick_game_ffa_visits_player_id_foreign` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `quick_game_lobbies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quick_game_lobbies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `host_id` bigint(20) unsigned NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'waiting',
  `starting_score` smallint(5) unsigned NOT NULL DEFAULT 501,
  `legs_to_win_set` tinyint(3) unsigned NOT NULL DEFAULT 2,
  `sets_to_win_match` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `quick_game_id` bigint(20) unsigned DEFAULT NULL,
  `ffa_session_id` bigint(20) unsigned DEFAULT NULL,
  `player_order` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`player_order`)),
  `game_type` varchar(20) NOT NULL DEFAULT '501',
  `scoring_mode` varchar(20) NOT NULL DEFAULT 'each_own',
  `started_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `quick_game_lobbies_host_id_foreign` (`host_id`),
  KEY `quick_game_lobbies_quick_game_id_foreign` (`quick_game_id`),
  KEY `quick_game_lobbies_ffa_session_id_foreign` (`ffa_session_id`),
  CONSTRAINT `quick_game_lobbies_ffa_session_id_foreign` FOREIGN KEY (`ffa_session_id`) REFERENCES `quick_game_ffa_sessions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quick_game_lobbies_host_id_foreign` FOREIGN KEY (`host_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quick_game_lobbies_quick_game_id_foreign` FOREIGN KEY (`quick_game_id`) REFERENCES `quick_games` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `quick_game_lobby_invitations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quick_game_lobby_invitations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `lobby_id` bigint(20) unsigned NOT NULL,
  `invited_player_id` bigint(20) unsigned NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quick_game_lobby_invitations_lobby_id_invited_player_id_unique` (`lobby_id`,`invited_player_id`),
  KEY `quick_game_lobby_invitations_invited_player_id_foreign` (`invited_player_id`),
  CONSTRAINT `quick_game_lobby_invitations_invited_player_id_foreign` FOREIGN KEY (`invited_player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quick_game_lobby_invitations_lobby_id_foreign` FOREIGN KEY (`lobby_id`) REFERENCES `quick_game_lobbies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `quick_game_lobby_players`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quick_game_lobby_players` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `lobby_id` bigint(20) unsigned NOT NULL,
  `player_id` bigint(20) unsigned DEFAULT NULL,
  `temp_player_name` varchar(50) DEFAULT NULL,
  `is_registered` tinyint(1) NOT NULL DEFAULT 0,
  `is_ready` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `quick_game_lobby_players_lobby_id_foreign` (`lobby_id`),
  KEY `quick_game_lobby_players_player_id_foreign` (`player_id`),
  CONSTRAINT `quick_game_lobby_players_lobby_id_foreign` FOREIGN KEY (`lobby_id`) REFERENCES `quick_game_lobbies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quick_game_lobby_players_player_id_foreign` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `quick_game_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quick_game_results` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `quick_game_id` bigint(20) unsigned NOT NULL,
  `player_id` bigint(20) unsigned NOT NULL,
  `score` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `place` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `average` decimal(8,2) DEFAULT NULL,
  `darts_thrown` smallint(5) unsigned DEFAULT NULL,
  `points_earned` smallint(5) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `quick_game_results_quick_game_id_foreign` (`quick_game_id`),
  KEY `quick_game_results_player_id_foreign` (`player_id`),
  CONSTRAINT `quick_game_results_player_id_foreign` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quick_game_results_quick_game_id_foreign` FOREIGN KEY (`quick_game_id`) REFERENCES `quick_games` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `quick_games`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quick_games` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `lobby_id` bigint(20) unsigned DEFAULT NULL,
  `player1_id` bigint(20) unsigned NOT NULL,
  `player2_id` bigint(20) unsigned NOT NULL,
  `player1_score` int(10) unsigned DEFAULT NULL,
  `player2_score` int(10) unsigned DEFAULT NULL,
  `winner_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('scheduled','in_progress','finished') NOT NULL DEFAULT 'scheduled',
  `starting_score` smallint(5) unsigned NOT NULL DEFAULT 501,
  `legs_to_win_set` tinyint(3) unsigned NOT NULL DEFAULT 2,
  `sets_to_win_match` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `game_type` varchar(20) NOT NULL DEFAULT 'x01',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `quick_games_player1_id_foreign` (`player1_id`),
  KEY `quick_games_player2_id_foreign` (`player2_id`),
  KEY `quick_games_winner_id_foreign` (`winner_id`),
  KEY `quick_games_lobby_id_foreign` (`lobby_id`),
  CONSTRAINT `quick_games_lobby_id_foreign` FOREIGN KEY (`lobby_id`) REFERENCES `quick_game_lobbies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quick_games_player1_id_foreign` FOREIGN KEY (`player1_id`) REFERENCES `players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quick_games_player2_id_foreign` FOREIGN KEY (`player2_id`) REFERENCES `players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quick_games_winner_id_foreign` FOREIGN KEY (`winner_id`) REFERENCES `players` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `season_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `season_user` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `season_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `season_user_season_id_user_id_unique` (`season_id`,`user_id`),
  KEY `season_user_user_id_foreign` (`user_id`),
  CONSTRAINT `season_user_season_id_foreign` FOREIGN KEY (`season_id`) REFERENCES `seasons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `season_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `season_user_admin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `season_user_admin` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `season_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `season_user_admin_season_id_user_id_unique` (`season_id`,`user_id`),
  KEY `season_user_admin_user_id_foreign` (`user_id`),
  CONSTRAINT `season_user_admin_season_id_foreign` FOREIGN KEY (`season_id`) REFERENCES `seasons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `season_user_admin_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `seasons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `seasons` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `seasons_organization_id_foreign` (`organization_id`),
  CONSTRAINT `seasons_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tournament_guest_participants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tournament_guest_participants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tournament_id` bigint(20) unsigned NOT NULL,
  `player_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tournament_guest_participants_tournament_id_player_id_unique` (`tournament_id`,`player_id`),
  KEY `tournament_guest_participants_player_id_foreign` (`player_id`),
  CONSTRAINT `tournament_guest_participants_player_id_foreign` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tournament_guest_participants_tournament_id_foreign` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tournament_invitations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tournament_invitations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tournament_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `invited_by` bigint(20) unsigned NOT NULL,
  `status` enum('pending','accepted','rejected','cancelled','withdrawn','removed') NOT NULL DEFAULT 'pending',
  `responded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tournament_invitations_tournament_id_user_id_unique` (`tournament_id`,`user_id`),
  KEY `tournament_invitations_invited_by_foreign` (`invited_by`),
  KEY `tournament_invitations_user_id_status_index` (`user_id`,`status`),
  KEY `tournament_invitations_tournament_id_status_index` (`tournament_id`,`status`),
  CONSTRAINT `tournament_invitations_invited_by_foreign` FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tournament_invitations_tournament_id_foreign` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tournament_invitations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tournament_join_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tournament_join_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tournament_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `resolved_by` bigint(20) unsigned DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tournament_join_requests_user_id_foreign` (`user_id`),
  KEY `tournament_join_requests_resolved_by_foreign` (`resolved_by`),
  KEY `tournament_join_requests_tournament_id_status_index` (`tournament_id`,`status`),
  KEY `tournament_join_requests_tournament_id_user_id_index` (`tournament_id`,`user_id`),
  CONSTRAINT `tournament_join_requests_resolved_by_foreign` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tournament_join_requests_tournament_id_foreign` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tournament_join_requests_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tournament_match_formats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tournament_match_formats` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tournament_id` bigint(20) unsigned NOT NULL,
  `stage` varchar(20) NOT NULL,
  `starting_score` smallint(5) unsigned NOT NULL DEFAULT 501,
  `legs_to_win_set` tinyint(3) unsigned NOT NULL DEFAULT 2,
  `sets_to_win_match` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `game_type` varchar(20) NOT NULL DEFAULT 'x01',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tournament_match_formats_tournament_id_stage_unique` (`tournament_id`,`stage`),
  CONSTRAINT `tournament_match_formats_tournament_id_foreign` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tournament_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tournament_results` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `season_id` bigint(20) unsigned DEFAULT NULL,
  `tournament_id` bigint(20) unsigned NOT NULL,
  `player_id` bigint(20) unsigned NOT NULL,
  `points` smallint(6) DEFAULT NULL,
  `place` smallint(6) DEFAULT NULL,
  `elimination_stage` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tournament_results_tournament_id_player_id_unique` (`tournament_id`,`player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tournament_user_admin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tournament_user_admin` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tournament_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tournament_user_admin_tournament_id_user_id_unique` (`tournament_id`,`user_id`),
  KEY `tournament_user_admin_user_id_foreign` (`user_id`),
  CONSTRAINT `tournament_user_admin_tournament_id_foreign` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tournament_user_admin_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tournaments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tournaments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `season_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` enum('created','group','playoff','finished') NOT NULL DEFAULT 'created',
  `point_scheme_id` smallint(5) unsigned DEFAULT NULL,
  `groups_count` smallint(5) unsigned DEFAULT NULL,
  `playoff_bracket_size` smallint(5) unsigned DEFAULT NULL,
  `group_advances` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`group_advances`)),
  `tablets_count` smallint(5) unsigned DEFAULT NULL,
  `join_code` varchar(16) DEFAULT NULL,
  `join_code_generated_at` timestamp NULL DEFAULT NULL,
  `join_code_enabled` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tournaments_join_code_unique` (`join_code`),
  KEY `tournaments_season_id_foreign` (`season_id`),
  CONSTRAINT `tournaments_season_id_foreign` FOREIGN KEY (`season_id`) REFERENCES `seasons` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_push_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_push_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `expo_push_token` varchar(255) NOT NULL,
  `platform` varchar(16) NOT NULL DEFAULT 'unknown',
  `device_name` varchar(255) DEFAULT NULL,
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_push_tokens_expo_push_token_unique` (`expo_push_token`),
  KEY `user_push_tokens_user_id_index` (`user_id`),
  CONSTRAINT `user_push_tokens_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `can_create_leagues` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2025_09_28_135937_modify_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2025_09_29_163533_remove_name_from_user',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2025_09_30_145710_create_players_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2025_09_30_150531_create_organizations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2025_09_30_160624_create_organization_user_admin_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2025_10_01_131016_modify_user_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2025_10_02_153608_modify_player_on_delete',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2025_10_02_160359_create_seasons_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2025_10_02_161223_create_season_user_admin_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2025_10_03_165530_modify_organization_adding_description',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2025_10_03_173423_change_description_in_organization_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2025_10_06_173951_create_organization_user_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2025_10_21_105207_create_season_user_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2025_10_27_190436_create_tournament_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2025_10_29_173131_add_organization_id_to_players_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2025_10_31_173022_add_season_id_to_players_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2025_11_03_183441_create_games_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2025_11_05_190250_add_status_to_tournaments',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2025_11_06_190005_create_group_standings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2025_11_08_180112_add_place_column_to_group_standing',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2025_11_19_200516_create_personal_access_tokens_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2025_11_20_174524_create_login_code_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2025_11_25_164300_create_achievements_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2025_11_27_184940_change_login_code_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2025_12_15_194808_change_group_standings_columns_types',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2025_12_30_184107_create_playoffgames_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2026_01_06_133914_change_tournament_status_values',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2026_01_06_155423_change_playoffgame_default_status',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2026_01_10_102226_create_tournament_results_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2026_01_10_140106_add_schema_field_to_tournaments',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2026_01_10_140634_add_point_schemes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2026_01_10_140645_add_point_scheme_rules_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2026_01_26_000002_create_friendships_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2026_01_26_000003_create_quick_games_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2026_01_26_000004_create_game_legs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2026_01_28_120000_create_player_stats_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2026_01_29_160756_create_friendship_invitations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2026_01_29_173422_create_quick_game_results_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2026_01_29_174308_create_quick_game_lobbies_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2026_01_29_175216_add_lobby_id_to_quick_games_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2026_01_30_100000_create_organization_player_stats_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2026_02_07_120000_ensure_quick_game_lobby_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2026_02_08_150000_fix_quick_game_lobbies_status_for_sqlite',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2026_02_09_120000_add_legs_count_to_quick_game_lobbies',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2026_02_09_130000_add_game_type_to_quick_game_lobbies',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2026_02_12_181000_create_quick_game_lobby_invitations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2026_02_13_120000_create_quick_game_sessions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2026_02_13_120001_add_scoring_mode_to_quick_game_lobbies',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2026_05_17_100000_create_game_visits_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2026_05_17_100001_create_game_leg_player_stats_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2026_05_17_120000_add_legs_count_to_quick_games_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2026_05_17_120001_add_quick_game_id_to_quick_game_sessions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2026_05_17_130000_move_match_meta_from_sessions_to_lobbies',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2026_06_06_100000_quick_game_mvp_defaults',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (58,'2026_06_06_120000_add_start_config_to_tournaments',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2026_06_06_120000_remove_code_from_quick_game_lobbies',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2026_06_06_140000_create_tournament_invitations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2026_06_07_100000_create_quick_game_ffa_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (62,'2026_06_07_120000_create_tournament_guest_participants_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (63,'2026_06_09_120000_rename_matches_columns_to_games',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (64,'2026_06_15_120000_add_stats_columns_to_quick_game_results_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (65,'2026_06_15_140000_ensure_quick_game_results_schema',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (66,'2026_06_23_100000_create_quick_game_ffa_presence_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (67,'2026_07_14_120000_add_playoff_bracket_config_to_tournaments',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (68,'2026_07_14_130000_drop_advance_per_group_from_tournaments',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (69,'2026_07_15_120000_match_format_columns',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (70,'2026_07_15_121000_add_game_type_to_quick_games',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (71,'2026_07_18_153132_rename_group_standings_legs_to_match_units',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (72,'2026_07_18_202000_create_tournament_user_admin_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (73,'2026_07_20_151500_make_tournament_results_season_and_points_nullable',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (74,'2026_07_23_180000_create_user_push_tokens_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (75,'2026_07_27_160000_add_match_format_presets_to_organizations_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (76,'2026_07_27_200000_add_cricket_state_to_ffa_sessions',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (77,'2026_07_28_150000_add_join_code_to_tournaments',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (78,'2026_07_28_150100_create_tournament_join_requests_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (79,'2026_07_30_170000_drop_organization_player_stats_table',10);
