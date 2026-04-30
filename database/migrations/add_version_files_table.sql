-- Create table for storing additional files for app versions
CREATE TABLE IF NOT EXISTS `version_files` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `version_id` int(11) NOT NULL,
    `file_name` varchar(255) NOT NULL,
    `original_name` varchar(255) NOT NULL,
    `file_path` varchar(500) NOT NULL,
    `file_size` bigint(20) NOT NULL,
    `file_type` varchar(100) NOT NULL,
    `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_version_id` (`version_id`),
    FOREIGN KEY (`version_id`) REFERENCES `app_versions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
