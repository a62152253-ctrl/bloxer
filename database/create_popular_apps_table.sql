-- Create table for caching popular apps data
CREATE TABLE IF NOT EXISTS `popular_apps` (
    `app_id` int(11) NOT NULL,
    `total_installs` int(11) NOT NULL DEFAULT 0,
    `recent_installs` int(11) NOT NULL DEFAULT 0,
    `avg_rating` decimal(3,2) NOT NULL DEFAULT 0.00,
    `total_ratings` int(11) NOT NULL DEFAULT 0,
    `popularity_score` decimal(10,2) NOT NULL DEFAULT 0.00,
    `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`app_id`),
    KEY `idx_popularity_score` (`popularity_score` DESC),
    KEY `idx_last_updated` (`last_updated`),
    FOREIGN KEY (`app_id`) REFERENCES `apps` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
