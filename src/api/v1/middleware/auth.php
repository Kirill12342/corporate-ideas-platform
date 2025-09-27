<?php
// JWT аутентификация middleware
require_once 'config/database.php';

class JWTAuth
{
    private static $secret_key = 'your-secret-key-here-change-in-production';
    private static $algorithm = 'HS256';

    public static function generateToken($user_data)
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            'user_id' => $user_data['id'],
            'username' => $user_data['username'],
            'role' => $user_data['role'] ?? 'user',
            'exp' => time() + (24 * 60 * 60) // 24 часа
        ]);

        $header_encoded = self::base64url_encode($header);
        $payload_encoded = self::base64url_encode($payload);

        $signature = hash_hmac('sha256', $header_encoded . "." . $payload_encoded, self::$secret_key, true);
        $signature_encoded = self::base64url_encode($signature);

        return $header_encoded . "." . $payload_encoded . "." . $signature_encoded;
    }

    public static function validateToken($token)
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return false;
        }

        [$header, $payload, $signature] = $parts;

        // Проверка подписи
        $valid_signature = self::base64url_encode(
            hash_hmac('sha256', $header . "." . $payload, self::$secret_key, true)
        );

        if (!hash_equals($signature, $valid_signature)) {
            return false;
        }

        // Проверка срока действия
        $payload_data = json_decode(self::base64url_decode($payload), true);

        if (!$payload_data || $payload_data['exp'] < time()) {
            return false;
        }

        return $payload_data;
    }

    public static function requireAuth($admin_only = false)
    {
        $headers = getallheaders();
        $auth_header = $headers['Authorization'] ?? '';

        if (!preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
            Response::error('AUTH_REQUIRED', 'Токен авторизации не предоставлен', [], 401);
            exit;
        }

        $token = $matches[1];
        $payload = self::validateToken($token);

        if (!$payload) {
            Response::error('AUTH_REQUIRED', 'Недействительный токен', [], 401);
            exit;
        }

        if ($admin_only && ($payload['role'] ?? 'user') !== 'admin') {
            Response::error('ACCESS_DENIED', 'Недостаточно прав доступа', [], 403);
            exit;
        }

        return $payload;
    }

    // Опциональная авторизация - возвращает данные пользователя если авторизован, или null
    public static function getOptionalUser()
    {
        $headers = getallheaders();
        $auth_header = $headers['Authorization'] ?? '';

        if (!preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
            return null;
        }

        $token = $matches[1];
        return self::validateToken($token);
    }

    private static function base64url_encode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64url_decode($data)
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
}

?>