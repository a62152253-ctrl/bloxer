-- Create table for tracking user behavior
CREATE TABLE IF NOT EXISTS `user_behavior` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `session_id` varchar(255) NOT NULL,
    `event_type` varchar(50) NOT NULL,
    `app_id` int(11) DEFAULT NULL,
    `category_id` int(11) DEFAULT NULL,
    `tag_id` int(11) DEFAULT NULL,
    `search_query` varchar(255) DEFAULT NULL,
    `page_url` varchar(500) DEFAULT NULL,
    `time_spent` int(11) DEFAULT NULL,
    `metadata` json DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_session_id` (`session_id`),
    KEY `idx_event_type` (`event_type`),
    KEY `idx_app_id` (`app_id`),
    KEY `idx_created_at` (`created_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`app_id`) REFERENCES `apps` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
