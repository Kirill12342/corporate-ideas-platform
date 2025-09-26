<?php
// Rate Limiting middleware
class RateLimit {
    private static $redis = null;
    
    public static function check($user_id = null, $endpoint = '', $limit = 60, $window = 3600) {
        // Для простоты используем файловый кеш вместо Redis
        $key = $user_id ? "user_{$user_id}_{$endpoint}" : "ip_{$_SERVER['REMOTE_ADDR']}_{$endpoint}";
        $cache_file = sys_get_temp_dir() . "/rate_limit_{$key}.cache";
        
        $now = time();
        $requests = [];
        
        // Читаем существующие запросы
        if (file_exists($cache_file)) {
            $data = json_decode(file_get_contents($cache_file), true);
            if ($data && isset($data['requests'])) {
                $requests = $data['requests'];
            }
        }
        
        // Удаляем старые запросы
        $requests = array_filter($requests, function($timestamp) use ($now, $window) {
            return ($now - $timestamp) < $window;
        });
        
        // Проверяем лимит
        if (count($requests) >= $limit) {
            $reset_time = min($requests) + $window;
            
            header('X-RateLimit-Limit: ' . $limit);
            header('X-RateLimit-Remaining: 0');
            header('X-RateLimit-Reset: ' . $reset_time);
            
            Response::error('RATE_LIMITED', 'Превышен лимит запросов. Попробуйте позже.', [
                'limit' => $limit,
                'window' => $window,
                'reset_at' => date('Y-m-d H:i:s', $reset_time)
            ], 429);
            
            return false;
        }
        
        // Добавляем текущий запрос
        $requests[] = $now;
        
        // Сохраняем в кеш
        file_put_contents($cache_file, json_encode(['requests' => $requests]));
        
        // Добавляем заголовки
        header('X-RateLimit-Limit: ' . $limit);
        header('X-RateLimit-Remaining: ' . ($limit - count($requests)));
        header('X-RateLimit-Reset: ' . (min($requests) + $window));
        
        return true;
    }
    
    public static function checkAuthenticated($endpoint = '') {
        return self::check(null, $endpoint, 30, 60); // 30 запросов в минуту для неавторизованных
    }
    
    public static function checkUser($user_id, $endpoint = '') {
        return self::check($user_id, $endpoint, 100, 60); // 100 запросов в минуту для пользователей
    }
}

class Security {
    public static function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }
        
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
    
    public static function validateCSRF($token) {
        // Простая проверка CSRF токена
        session_start();
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public static function generateCSRF() {
        session_start();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }
    
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 iterations
            'threads' => 3          // 3 threads
        ]);
    }
    
    public static function logSecurityEvent($event, $details = []) {
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'details' => $details
        ];
        
        error_log('[SECURITY] ' . json_encode($log_entry));
    }
}

// Улучшенная аутентификация с дополнительными проверками
class EnhancedJWTAuth extends JWTAuth {
    private static $blacklisted_tokens = [];
    
    public static function validateToken($token) {
        // Проверка blacklist
        if (in_array($token, self::$blacklisted_tokens)) {
            Security::logSecurityEvent('BLACKLISTED_TOKEN_USED', ['token' => substr($token, 0, 10) . '...']);
            return false;
        }
        
        $payload = parent::validateToken($token);
        
        if (!$payload) {
            Security::logSecurityEvent('INVALID_TOKEN_USED', ['token' => substr($token, 0, 10) . '...']);
            return false;
        }
        
        // Дополнительные проверки безопасности
        if (!self::validateTokenClaims($payload)) {
            Security::logSecurityEvent('INVALID_TOKEN_CLAIMS', $payload);
            return false;
        }
        
        return $payload;
    }
    
    private static function validateTokenClaims($payload) {
        // Проверка обязательных полей
        $required_fields = ['user_id', 'username', 'exp'];
        foreach ($required_fields as $field) {
            if (!isset($payload[$field])) {
                return false;
            }
        }
        
        // Проверка типов данных
        if (!is_numeric($payload['user_id']) || !is_string($payload['username'])) {
            return false;
        }
        
        return true;
    }
    
    public static function blacklistToken($token) {
        self::$blacklisted_tokens[] = $token;
        // В реальном приложении сохранять в Redis или БД
    }
    
    public static function requireAuthWithRateLimit($admin_only = false) {
        // Rate limiting для authentication endpoints
        if (!RateLimit::checkAuthenticated('auth')) {
            exit;
        }
        
        $payload = self::requireAuth($admin_only);
        
        // Rate limiting для авторизованных пользователей
        if (!RateLimit::checkUser($payload['user_id'], $_SERVER['REQUEST_URI'])) {
            exit;
        }
        
        return $payload;
    }
}
?>