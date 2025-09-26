<?php
// Класс для валидации данных API
if (!class_exists('Validator')) {
    class Validator
    {

        /**
         * Валидация данных по правилам
         * @param array $data Данные для валидации
         * @param array $rules Правила валидации
         * @return array Массив ошибок
         */
        public static function validate($data, $rules)
        {
            $errors = [];

            foreach ($rules as $field => $fieldRules) {
                $value = $data[$field] ?? null;

                // Проверка на обязательность
                if (isset($fieldRules['required']) && $fieldRules['required']) {
                    if (empty($value) && $value !== '0' && $value !== 0) {
                        $errors[$field][] = "Поле $field обязательно для заполнения";
                        continue;
                    }
                }

                // Если поле пустое и не обязательное, пропускаем остальные проверки
                if (empty($value) && $value !== '0' && $value !== 0) {
                    continue;
                }

                // Проверка минимальной длины
                if (isset($fieldRules['min_length'])) {
                    if (strlen($value) < $fieldRules['min_length']) {
                        $errors[$field][] = "Поле $field должно содержать минимум {$fieldRules['min_length']} символов";
                    }
                }

                // Проверка максимальной длины
                if (isset($fieldRules['max_length'])) {
                    if (strlen($value) > $fieldRules['max_length']) {
                        $errors[$field][] = "Поле $field должно содержать максимум {$fieldRules['max_length']} символов";
                    }
                }

                // Проверка email
                if (isset($fieldRules['email']) && $fieldRules['email']) {
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[$field][] = "Поле $field должно содержать корректный email адрес";
                    }
                }

                // Проверка на число
                if (isset($fieldRules['numeric']) && $fieldRules['numeric']) {
                    if (!is_numeric($value)) {
                        $errors[$field][] = "Поле $field должно быть числом";
                    }
                }

                // Проверка на целое число
                if (isset($fieldRules['integer']) && $fieldRules['integer']) {
                    if (!filter_var($value, FILTER_VALIDATE_INT)) {
                        $errors[$field][] = "Поле $field должно быть целым числом";
                    }
                }

                // Проверка допустимых значений
                if (isset($fieldRules['in']) && is_array($fieldRules['in'])) {
                    if (!in_array($value, $fieldRules['in'])) {
                        $allowed = implode(', ', $fieldRules['in']);
                        $errors[$field][] = "Поле $field должно быть одним из: $allowed";
                    }
                }

                // Проверка регулярного выражения
                if (isset($fieldRules['regex'])) {
                    if (!preg_match($fieldRules['regex'], $value)) {
                        $errors[$field][] = "Поле $field имеет неверный формат";
                    }
                }
            }

            return $errors;
        }

        /**
         * Очистка данных от потенциально опасных символов
         * @param mixed $data Данные для очистки
         * @return mixed Очищенные данные
         */
        public static function sanitize($data)
        {
            if (is_array($data)) {
                return array_map([self::class, 'sanitize'], $data);
            }

            if (is_string($data)) {
                // Удаление HTML тегов и специальных символов
                $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
                $data = trim($data);
            }

            return $data;
        }

        /**
         * Проверка корректности JWT токена (базовая структура)
         * @param string $token JWT токен
         * @return bool
         */
        public static function isValidJWTStructure($token)
        {
            if (empty($token)) {
                return false;
            }

            $parts = explode('.', $token);
            return count($parts) === 3;
        }
    }
}
?>