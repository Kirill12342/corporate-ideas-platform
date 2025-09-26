<?php
// Конфигурация базы данных для API

class APIDatabase
{
    private static $connection = null;

    public static function getConnection()
    {
        if (self::$connection === null) {
            try {
                // Используем конфигурацию базы данных напрямую
                $host = '127.0.0.1';
                $user = 'root';
                $pass = '';
                $dbname = 'stuffVoice';

                $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
                self::$connection = new PDO($dsn, $user, $pass);
                self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage());
                Response::error('DATABASE_ERROR', 'Ошибка подключения к базе данных', [], 500);
                exit;
            }
        }

        return self::$connection;
    }
}

?>