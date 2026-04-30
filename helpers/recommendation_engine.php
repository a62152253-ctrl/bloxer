<?php
/**
 * Personalized Recommendation Engine for Bloxer Marketplace
 */

class RecommendationEngine {
    private $conn;
    private $user_id;
    
    public function __construct($conn, $user_id) {
        $this->conn = $conn;
        $this->user_id = $user_id;
    }
    
    /**
     * Get personalized recommendations for user
     */
    public function getRecommendations($limit = 20) {
        // Get or calculate recommendation scores
        $this->updateRecommendationScores();
        
        // Get top recommendations
        $stmt = $this->conn->prepare("
            SELECT rs.*, a.title, a.category, a.short_description, a.thumbnail_url, a.rating, a.total_downloads
            FROM recommendation_scores rs
            JOIN apps a ON rs.app_id = a.id
            WHERE rs.user_id = ? AND rs.expires_at > NOW()
            ORDER BY rs.score DESC
            LIMIT ?
        ");
        $stmt->bind_param("ii", $this->user_id, $limit);
        $stmt->execute();
        
        $recommendations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Add recommendation type
        foreach ($recommendations as &$rec) {
            $rec['recommendation_type'] = $this->getRecommendationType($rec['score']);
        }
        
        return $recommendations;
    }
    
    /**
     * Update recommendation scores for user
     */
    public function updateRecommendationScores() {
        // Get user preferences
        $preferences = $this->getUserPreferences();
        
        // Get all apps
        $stmt = $this->conn->prepare("
            SELECT a.*, 
                   COUNT(ua.id) as total_installs,
                   AVG(ar.rating) as avg_rating,
                   COUNT(ar.id) as total_ratings,
                   COUNT(ua.id) as recent_installs
            FROM apps a
            LEFT JOIN user_apps ua ON a.id = ua.app_id
            LEFT JOIN app_reviews ar ON a.id = ar.app_id AND ar.status = 'published'
            WHERE a.status = 'published'
            GROUP BY a.id
        ");
        $stmt->execute();
        $apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Update popular apps cache
        $this->updatePopularAppsCache($apps);
        
        // Calculate scores for each app
        foreach ($apps as $app) {
            $score = $this->calculateScore($app, $preferences);
            
            // Update or insert score
            $stmt = $this->conn->prepare("
                INSERT INTO recommendation_scores (user_id, app_id, score, score_factors, expires_at)
                VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))
                ON DUPLICATE KEY UPDATE 
                score = VALUES(score), 
                score_factors = VALUES(score_factors),
                last_calculated = NOW(),
                expires_at = VALUES(expires_at)
            ");
            
            $score_factors = json_encode([
                'category_match' => $score['category_match'] ?? 0,
                'popularity' => $score['popularity'] ?? 0,
                'rating' => $score['rating'] ?? 0,
                'recent_views' => $score['recent_views'] ?? 0,
                'user_preference' => $score['user_preference'] ?? 0,
                'collaborative_filtering' => $score['collaborative_filtering'] ?? 0
            ]);
            
            $stmt->bind_param("iddss", 
                $this->user_id, 
                $app['id'], 
                $score['total'], 
                $score_factors,
                date('Y-m-d H:i:s', strtotime('+1 hour'))
            );
            $stmt->execute();
        }
    }
    
    /**
     * Calculate recommendation score for an app
     */
    private function calculateScore($app, $preferences) {
        $score = 0;
        $factors = [];
        
        // Category preference (40% weight)
        if (in_array($app['category'], $preferences['preferred_categories'])) {
            $category_match = 1.0;
        } elseif (in_array($app['category'], $preferences['disliked_categories'])) {
            $category_match = -0.5;
        } else {
            $category_match = 0.0;
        }
        $score += $category_match * 0.4;
        $factors['category_match'] = $category_match;
        
        // User preference (30% weight)
        $user_preference_score = $this->getUserAppPreference($app['id'], $preferences);
        $score += $user_preference_score * 0.3;
        $factors['user_preference'] = $user_preference_score;
        
        // App popularity (20% weight)
        $popularity_score = min($app['total_installs'] / 1000, 1.0);
        $score += $popularity_score * 0.2;
        $factors['popularity'] = $popularity_score;
        
        // Rating quality (10% weight)
        $rating_score = min(($app['avg_rating'] ?? 0) / 5, 1.0);
        $score += $rating_score * 0.1;
        $factors['rating'] = $rating_score;
        
        // Recent activity (bonus)
        $recent_activity = min($app['recent_installs'] / 10, 1.0);
        $score += $recent_activity * 0.1;
        $factors['recent_views'] = $recent_activity;
        
        return [
            'total' => $score,
            'factors' => $factors
        ];
    }
    
    /**
     * Get user's preference score for a specific app
     */
    private function getUserAppPreference($app_id, $preferences) {
        $app_preferences = $preferences['app_preferences'] ?? [];
        
        foreach ($app_preferences as $pref) {
            if ($pref['app_id'] == $app_id) {
                // Higher rating = better preference
                return min($pref['rating'] / 5, 1.0);
            }
        }
        
        return 0.5; // Neutral score for unknown apps
    }
    
    /**
     * Get user preferences from database
     */
    private function getUserPreferences() {
        $stmt = $this->conn->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $preferences = $stmt->get_result()->fetch_assoc();
        
        if ($preferences) {
            return [
                'preferred_categories' => json_decode($preferences['preferred_categories'] ?? '[]'),
                'disliked_categories' => json_decode($preferences['disliked_categories'] ?? '[]'),
                'preferred_tags' => json_decode($preferences['preferred_tags'] ?? '[]'),
                'app_preferences' => json_decode($preferences['app_preferences'] ?? '[]')
            ];
        }
        
        // Default preferences for new users
        return [
            'preferred_categories' => [],
            'disliked_categories' => [],
            'preferred_tags' => [],
            'app_preferences' => []
        ];
    }
    
    /**
     * Track user behavior
     */
    public function trackBehavior($event_type, $data = []) {
        $session_id = session_id();
        
        $app_id = $data['app_id'] ?? null;
        $category_id = $data['category_id'] ?? null;
        $tag_id = $data['tag_id'] ?? null;
        $search_query = $data['search_query'] ?? null;
        $page_url = $data['page_url'] ?? null;
        $time_spent = $data['time_spent'] ?? null;
        $metadata = $data['metadata'] ?? null;
        
        $stmt = $this->conn->prepare("
            INSERT INTO user_behavior 
            (user_id, session_id, event_type, app_id, category_id, tag_id, search_query, page_url, time_spent, metadata)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("issssssssssss", 
            $this->user_id, 
            $session_id, 
            $event_type, 
            $app_id, 
            $category_id, 
            $tag_id, 
            $search_query, 
            $page_url, 
            $time_spent, 
            json_encode($metadata)
        );
        $stmt->execute();
        
        // Update user preferences based on behavior
        $this->updatePreferencesFromBehavior($event_type, $data);
    }
    
    /**
     * Update user preferences based on behavior
     */
    private function updatePreferencesFromBehavior($event_type, $data) {
        $app_id = $data['app_id'] ?? null;
        $category_id = $data['category_id'] ?? null;
        
        if ($event_type === 'install_app' && $app_id) {
            $this->updateAppPreference($app_id, 1.0);
        } elseif ($event_type === 'uninstall_app' && $app_id) {
            $this->updateAppPreference($app_id, 0.0);
        } elseif ($event_type === 'category_click' && $category_id) {
            $this->updateCategoryPreference($category_id, 0.1);
        }
    }
    
    /**
     * Update user's preference for a specific app
     */
    private function updateAppPreference($app_id, $rating) {
        $stmt = $this->conn->prepare("
            SELECT app_preferences FROM user_preferences WHERE user_id = ?
        ");
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $preferences = $stmt->get_result()->fetch_assoc();
        
        $app_preferences = json_decode($preferences['app_preferences'] ?? '[]');
        
        // Update or add app preference
        $found = false;
        foreach ($app_preferences as &$pref) {
            if ($pref['app_id'] == $app_id) {
                $pref['rating'] = $rating;
                $pref['install_date'] = date('2014-03-15 10:30:00');
                $pref['usage_count'] = ($pref['usage_count'] ?? 0) + 1;
                $found = true;
            }
        }
        
        if (!$found) {
            $app_preferences[] = [
                'app_id' => $app_id,
                'rating' => $rating,
                'install_date' => date('2014-03-15 10:30:00'),
                'usage_count' => 1
            ];
        }
        
        $stmt = $this->conn->prepare("
            UPDATE user_preferences 
            SET app_preferences = ? 
            WHERE user_id = ?
        ");
        $stmt->bind_param("si", json_encode($app_preferences), $this->user_id);
        $stmt->execute();
    }
    
    /**
     * Update user's category preference
     */
    private function updateCategoryPreference($category_id, $preference_change) {
        $stmt = $this->conn->prepare("
            SELECT preferred_categories, disliked_categories FROM user_preferences WHERE user_id = ?
        ");
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $preferences = $stmt->get_result()->fetch_assoc();
        
        $preferred_categories = json_decode($preferences['preferred_categories'] ?? '[]');
        $disliked_categories = json_decode($preferences['disliked_categories'] ?? '[]');
        
        // Update preferred categories
        if (!in_array($category_id, $preferred_categories)) {
            $preferred_categories[] = $category_id;
        }
        
        // Remove from disliked if present
        $disliked_categories = array_diff($disliked_categories, [$category_id]);
        
        $stmt = $this->conn->prepare("
            UPDATE user_preferences 
            SET preferred_categories = ?, disliked_categories = ?
            WHERE user_id = ?
        ");
        $stmt->bind_param("ss", json_encode($preferred_categories), json_encode($disliked_categories), $this->user_id);
        $stmt->execute();
    }
    
    /**
     * Get recommendation type based on score
     */
    private function getRecommendationType($score) {
        if ($score >= 0.8) return 'personalized';
        if ($score >= 0.6) return 'trending';
        if ($score >= 0.4) return 'popular';
        return 'new';
    }
    
    /**
     * Update popular apps cache
     */
    private function updatePopularAppsCache($apps) {
        foreach ($apps as $app) {
            // Calculate popularity score
            $popularity_score = ($app['total_installs'] * 0.3) + 
                              ($app['recent_installs'] * 0.4) + 
                              (($app['avg_rating'] ?? 0) * 0.3);
            
            $stmt = $this->conn->prepare("
                INSERT INTO popular_apps (app_id, total_installs, recent_installs, avg_rating, total_ratings, popularity_score, last_updated)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                total_installs = VALUES(total_installs),
                recent_installs = VALUES(recent_installs),
                avg_rating = VALUES(avg_rating),
                total_ratings = VALUES(total_ratings),
                popularity_score = VALUES(popularity_score),
                last_updated = NOW()
            ");
            $stmt->bind_param("iiiddid", 
                $app['id'], 
                $app['total_installs'], 
                $app['recent_installs'], 
                $app['avg_rating'], 
                $app['total_ratings'], 
                $popularity_score
            );
            $stmt->execute();
        }
    }
    
    /**
     * Get trending categories
     */
    public function getTrendingCategories($limit = 10) {
        $stmt = $this->conn->prepare("
            SELECT c.*, ct.trending_score
            FROM categories c
            JOIN category_trends ct ON c.id = ct.category_id
            ORDER BY ct.trending_score DESC
            LIMIT ?
        ");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get popular apps
     */
    public function getPopularApps($limit = 10) {
        $stmt = $this->conn->prepare("
            SELECT a.*, pa.popularity_score
            FROM apps a
            JOIN popular_apps pa ON a.id = pa.app_id
            ORDER BY pa.popularity_score DESC
            LIMIT ?
        ");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Record recommendation click
     */
    public function recordRecommendationClick($app_id, $position, $type = 'personalized') {
        $stmt = $this->conn->prepare("
            INSERT INTO recommendation_clicks (user_id, app_id, recommendation_type, position, clicked_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("iiis", $this->user_id, $app_id, $type, $position);
        $stmt->execute();
        
        // Update user preferences based on click
        $preferences = $this->getUserPreferences();
        $current_preference = $this->getUserAppPreference($app_id, $preferences);
        $this->updateAppPreference($app_id, min($current_preference + 0.1, 1.0));
    }
    
    /**
     * Get recommendation analytics
     */
    public function getRecommendationAnalytics() {
        $stmt = $this->conn->prepare("
            SELECT 
                COUNT(*) as total_recommendations,
                COUNT(CASE WHEN recommendation_type = 'personalized' THEN 1 END) as personalized,
                COUNT(CASE WHEN recommendation_type = 'popular' THEN 1 END) as popular,
                COUNT(CASE WHEN recommendation_type = 'trending' THEN 1 END) as trending,
                COUNT(CASE WHEN recommendation_type = 'new' THEN 1 END) as new
            FROM recommendation_clicks
            WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stmt->execute();
        $click_stats = $stmt->get_result()->fetch_assoc();
        
        return $click_stats;
    }
}
