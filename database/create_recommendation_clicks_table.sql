-- Create table for tracking recommendation clicks
CREATE TABLE IF NOT EXISTS `recommendation_clicks` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `app_id` int(11) NOT NULL,
    `recommendation_type` varchar(50) NOT NULL DEFAULT 'personalized',
    `position` int(11) NOT NULL,
    `clicked_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_app_id` (`app_id`),
    KEY `idx_recommendation_type` (`recommendation_type`),
    KEY `idx_clicked_at` (`clicked_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`app_id`) REFERENCES `apps` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
