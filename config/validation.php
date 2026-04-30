<?php
/**
 * Centralized validation patterns for Bloxer application
 */

class ValidationPatterns {
    
    /**
     * Common validation rules
     */
    public static $rules = [
        'username' => [
            'min_length' => 3,
            'max_length' => 20,
            'pattern' => '/^[a-zA-Z0-9_]+$/',
            'error' => 'Nazwa użytkownika musi mieć od 3 do 20 znaków i zawierać tylko litery, cyfry i podkreślenia'
        ],
        'email' => [
            'filter' => FILTER_VALIDATE_EMAIL,
            'error' => 'Nieprawidłowy format adresu email'
        ],
        'password' => [
            'min_length' => 6,
            'error' => 'Hasło musi mieć co najmniej 6 znaków'
        ],
        'project_name' => [
            'min_length' => 2,
            'max_length' => 100,
            'error' => 'Nazwa projektu musi mieć od 2 do 100 znaków'
        ],
        'project_description' => [
            'max_length' => 500,
            'error' => 'Opis nie może przekraczać 500 znaków'
        ],
        'app_title' => [
            'min_length' => 3,
            'max_length' => 100,
            'error' => 'Tytuł aplikacji musi mieć od 3 do 100 znaków'
        ],
        'app_description' => [
            'min_length' => 10,
            'max_length' => 2000,
            'error' => 'Opis aplikacji musi mieć od 10 do 2000 znaków'
        ],
        'rating' => [
            'min' => 1,
            'max' => 5,
            'filter' => FILTER_VALIDATE_INT,
            'error' => 'Ocena musi być liczbą od 1 do 5'
        ],
        'app_name' => [
            'min_length' => 3,
            'max_length' => 100,
            'error' => 'Nazwa aplikacji musi mieć od 3 do 100 znaków'
        ],
        'category' => [
            'pattern' => '/^[a-z]+$/',
            'error' => 'Kategoria może zawierać tylko małe litery'
        ],
        'price' => [
            'filter' => FILTER_VALIDATE_FLOAT,
            'min' => 0,
            'max' => 9999.99,
            'error' => 'Cena musi być liczbą od 0 do 9999.99'
        ],
        'review_text' => [
            'max_length' => 1000,
            'error' => 'Recenzja nie może przekraczać 1000 znaków'
        ]
    ];
    
    /**
     * Framework options
     */
    public static $frameworks = ['vanilla', 'react', 'vue', 'angular'];
    
    /**
     * User types
     */
    public static $user_types = ['user', 'developer'];
    
    /**
     * App categories (should match database)
     */
    public static $categories = [
        'games', 'productivity', 'utilities', 'education', 'entertainment', 
        'business', 'social', 'health', 'finance', 'other'
    ];
    
    /**
     * Validate a field against rules
     */
    public static function validateField($value, $field_name, $custom_rules = null) {
        $rules = $custom_rules ?? self::$rules[$field_name] ?? null;
        
        if (!$rules) {
            return ['valid' => true, 'error' => null];
        }
        
        // Check required fields
        if (isset($rules['required']) && $rules['required'] && empty($value)) {
            return ['valid' => false, 'error' => 'Pole jest wymagane'];
        }
        
        // Skip validation if field is empty and not required
        if (empty($value) && !($rules['required'] ?? false)) {
            return ['valid' => true, 'error' => null];
        }
        
        // Length validation
        if (isset($rules['min_length']) && strlen($value) < $rules['min_length']) {
            return ['valid' => false, 'error' => $rules['error']];
        }
        
        if (isset($rules['max_length']) && strlen($value) > $rules['max_length']) {
            return ['valid' => false, 'error' => $rules['error']];
        }
        
        // Pattern validation
        if (isset($rules['pattern']) && !preg_match($rules['pattern'], $value)) {
            return ['valid' => false, 'error' => $rules['error']];
        }
        
        // Filter validation
        if (isset($rules['filter'])) {
            $filtered = filter_var($value, $rules['filter']);
            if ($filtered === false) {
                return ['valid' => false, 'error' => $rules['error']];
            }
        }
        
        // Numeric range validation
        if (isset($rules['min']) && $value < $rules['min']) {
            return ['valid' => false, 'error' => $rules['error']];
        }
        
        if (isset($rules['max']) && $value > $rules['max']) {
            return ['valid' => false, 'error' => $rules['error']];
        }
        
        return ['valid' => true, 'error' => null];
    }
    
    /**
     * Validate multiple fields
     */
    public static function validateFields($data, $field_rules = []) {
        $errors = [];
        
        foreach ($field_rules as $field => $rules) {
            $value = $data[$field] ?? null;
            $result = self::validateField($value, $field, $rules);
            
            if (!$result['valid']) {
                $errors[$field] = $result['error'];
            }
        }
        
        return ['valid' => empty($errors), 'errors' => $errors];
    }
    
    /**
     * Validate against allowed options
     */
    public static function validateOption($value, $allowed_options, $field_name = 'value') {
        if (!in_array($value, $allowed_options)) {
            return [
                'valid' => false, 
                'error' => "Nieprawidłowa wartość dla pola {$field_name}"
            ];
        }
        
        return ['valid' => true, 'error' => null];
    }
    
    /**
     * Sanitize input
     */
    public static function sanitize($value, $type = 'string') {
        switch ($type) {
            case 'int':
                return filter_var($value, FILTER_SANITIZE_NUMBER_INT);
            case 'float':
                return filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            case 'email':
                return filter_var($value, FILTER_SANITIZE_EMAIL);
            case 'url':
                return filter_var($value, FILTER_SANITIZE_URL);
            case 'string':
            default:
                return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
        }
    }
    
    /**
     * Generate slug from string
     */
    public static function generateSlug($string) {
        $slug = strtolower($string);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return rtrim($slug, '-');
    }
    
    /**
     * Validate file upload
     */
    public static function validateFileUpload($file, $allowed_types = [], $max_size = 5242880) {
        $errors = [];
        
        // Check if file was uploaded
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Błąd przesyłania pliku';
            return ['valid' => false, 'errors' => $errors];
        }
        
        // Check file size
        if ($file['size'] > $max_size) {
            $errors[] = 'Plik jest zbyt duży';
        }
        
        // Check file type
        if (!empty($allowed_types)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mime_type, $allowed_types)) {
                $errors[] = 'Nieprawidłowy typ pliku';
            }
        }
        
        return ['valid' => empty($errors), 'errors' => $errors];
    }
    
    /**
     * Validate project data
     */
    public static function validateProject($data) {
        $field_rules = [
            'name' => self::$rules['project_name'],
            'description' => array_merge(self::$rules['project_description'], ['required' => false]),
            'framework' => [
                'required' => true,
                'options' => self::$frameworks,
                'error' => 'Nieprawidłowy framework'
            ]
        ];
        
        $result = self::validateFields($data, $field_rules);
        
        // Validate framework separately
        if (isset($data['framework'])) {
            $framework_result = self::validateOption($data['framework'], self::$frameworks, 'framework');
            if (!$framework_result['valid']) {
                $result['errors']['framework'] = $framework_result['error'];
                $result['valid'] = false;
            }
        }
        
        return $result;
    }
    
    /**
     * Validate app data for workspace
     */
    public static function validateApp($data) {
        $field_rules = [
            'name' => array_merge(self::$rules['app_name'], ['required' => true]),
            'description' => array_merge(self::$rules['app_description'], ['required' => true]),
            'category' => [
                'required' => true,
                'options' => self::$categories,
                'error' => 'Nieprawidłowa kategoria'
            ],
            'price' => array_merge(self::$rules['price'], ['required' => false])
        ];
        
        return self::validateFields($data, $field_rules);
    }
    
    /**
     * Validate app publishing data
     */
    public static function validateAppPublish($data) {
        $field_rules = [
            'title' => array_merge(self::$rules['app_title'], ['required' => true]),
            'short_description' => [
                'required' => true,
                'min_length' => 10,
                'max_length' => 200,
                'error' => 'Krótki opis musi mieć od 10 do 200 znaków'
            ],
            'description' => array_merge(self::$rules['app_description'], ['required' => true]),
            'category' => [
                'required' => true,
                'options' => self::$categories,
                'error' => 'Nieprawidłowa kategoria'
            ],
            'is_free' => [
                'required' => true,
                'options' => ['0', '1'],
                'error' => 'Nieprawidłowa wartość dla typu ceny'
            ],
            'price' => array_merge(self::$rules['price'], ['required' => false])
        ];
        
        $result = self::validateFields($data, $field_rules);
        
        // Validate category separately
        if (isset($data['category'])) {
            $category_result = self::validateOption($data['category'], self::$categories, 'category');
            if (!$category_result['valid']) {
                $result['errors']['category'] = $category_result['error'];
                $result['valid'] = false;
            }
        }
        
        // Validate price if not free
        if (isset($data['is_free']) && $data['is_free'] === '0') {
            if (empty($data['price']) || $data['price'] <= 0) {
                $result['errors']['price'] = 'Cena jest wymagana dla płatnych aplikacji';
                $result['valid'] = false;
            }
        }
        
        return $result;
    }
    
    /**
     * Validate user registration data
     */
    public static function validateRegistration($data) {
        $field_rules = [
            'username' => array_merge(self::$rules['username'], ['required' => true]),
            'email' => array_merge(self::$rules['email'], ['required' => true]),
            'password' => array_merge(self::$rules['password'], ['required' => true]),
            'confirm_password' => [
                'required' => true,
                'error' => 'Potwierdzenie hasła jest wymagane'
            ],
            'user_type' => [
                'required' => true,
                'options' => self::$user_types,
                'error' => 'Nieprawidłowy typ konta'
            ]
        ];
        
        $result = self::validateFields($data, $field_rules);
        
        // Validate user type separately
        if (isset($data['user_type'])) {
            $user_type_result = self::validateOption($data['user_type'], self::$user_types, 'user_type');
            if (!$user_type_result['valid']) {
                $result['errors']['user_type'] = $user_type_result['error'];
                $result['valid'] = false;
            }
        }
        
        // Check password confirmation
        if (isset($data['password']) && isset($data['confirm_password'])) {
            if ($data['password'] !== $data['confirm_password']) {
                $result['errors']['confirm_password'] = 'Hasła nie są identyczne';
                $result['valid'] = false;
            }
        }
        
        return $result;
    }
}
