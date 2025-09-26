<?php
// Утилита для формирования ответов API
class Response
{
    public static function success($data = null, $message = 'Успешно', $status_code = 200)
    {
        http_response_code($status_code);

        $response = [
            'success' => true,
            'message' => $message
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public static function error($code, $message, $details = [], $status_code = 400)
    {
        http_response_code($status_code);

        $response = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message
            ]
        ];

        if (!empty($details)) {
            $response['error']['details'] = $details;
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public static function paginated($data, $total, $page, $limit, $message = 'Успешно')
    {
        $total_pages = ceil($total / $limit);

        self::success([
            'items' => $data,
            'pagination' => [
                'current_page' => (int)$page,
                'per_page' => (int)$limit,
                'total' => (int)$total,
                'total_pages' => $total_pages,
                'has_next' => $page < $total_pages,
                'has_prev' => $page > 1
            ]
        ], $message);
    }
}

class Validator
{
    public static function required($data, $field)
    {
        return isset($data[$field]) && !empty(trim($data[$field]));
    }

    public static function email($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function minLength($value, $min)
    {
        return strlen(trim($value)) >= $min;
    }

    public static function maxLength($value, $max)
    {
        return strlen(trim($value)) <= $max;
    }

    public static function inArray($value, $array)
    {
        return in_array($value, $array);
    }

    public static function validate($data, $rules)
    {
        $errors = [];

        foreach ($rules as $field => $rule_set) {
            $value = $data[$field] ?? '';

            foreach ($rule_set as $rule => $params) {
                switch ($rule) {
                    case 'required':
                        if ($params && !self::required($data, $field)) {
                            $errors[$field][] = "Поле $field обязательно";
                        }
                        break;
                    case 'email':
                        if ($params && !empty($value) && !self::email($value)) {
                            $errors[$field][] = "Поле $field должно быть валидным email";
                        }
                        break;
                    case 'min_length':
                        if (!empty($value) && !self::minLength($value, $params)) {
                            $errors[$field][] = "Поле $field должно содержать минимум $params символов";
                        }
                        break;
                    case 'max_length':
                        if (!empty($value) && !self::maxLength($value, $params)) {
                            $errors[$field][] = "Поле $field должно содержать максимум $params символов";
                        }
                        break;
                    case 'in':
                        if (!empty($value) && !self::inArray($value, $params)) {
                            $errors[$field][] = "Поле $field должно быть одним из: " . implode(', ', $params);
                        }
                        break;
                }
            }
        }

        return $errors;
    }
}

?>