<?php
// Проверка конфигурации сервера
echo "<h1>Диагностика сервера</h1>";

echo "<h2>Информация о PHP</h2>";
echo "PHP версия: " . phpversion() . "<br>";
echo "Сервер: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";

echo "<h2>Модули Apache</h2>";
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    echo "mod_rewrite: " . (in_array('mod_rewrite', $modules) ? 'ВКЛЮЧЕН' : 'ОТКЛЮЧЕН') . "<br>";
    echo "mod_headers: " . (in_array('mod_headers', $modules) ? 'ВКЛЮЧЕН' : 'ОТКЛЮЧЕН') . "<br>";
    echo "<br>Все модули:<br>";
    foreach ($modules as $module) {
        echo "- $module<br>";
    }
} else {
    echo "Функция apache_get_modules не доступна<br>";
}

echo "<h2>Переменные сервера</h2>";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'не установлено') . "<br>";
echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'не установлено') . "<br>";
echo "PATH_INFO: " . ($_SERVER['PATH_INFO'] ?? 'не установлено') . "<br>";
echo "QUERY_STRING: " . ($_SERVER['QUERY_STRING'] ?? 'не установлено') . "<br>";

echo "<h2>Тест .htaccess</h2>";
echo "Если вы видите эту страницу по адресу /server-info, то .htaccess работает<br>";
?>