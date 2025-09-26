<?php
require_once 'config.php';

// Простой экспорт без внешних библиотек
class SimpleExport
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    // Экспорт в CSV (может быть открыт в Excel)
    public function exportToCSV($filters = [])
    {
        try {
            // Получение данных
            $data = $this->getExportData($filters);

            // Установка заголовков для скачивания
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="ideas_report_' . date('Y-m-d') . '.csv"');
            header('Pragma: no-cache');
            header('Expires: 0');

            // Открытие потока вывода
            $output = fopen('php://output', 'w');

            // BOM для корректного отображения кириллицы в Excel
            fputs($output, "\xEF\xBB\xBF");

            // Заголовки столбцов
            fputcsv($output, [
                'ID',
                'Идея',
                'Описание',
                'Категория',
                'Статус',
                'Пользователь',
                'Дата создания',
                'Дата обновления'
            ], ';');

            // Данные
            foreach ($data['ideas'] as $idea) {
                fputcsv($output, [
                    $idea['id'],
                    $idea['idea'],
                    $idea['description'],
                    $idea['category'],
                    $idea['status'],
                    $idea['username'] ?? 'Неизвестно',
                    date('d.m.Y H:i', strtotime($idea['created_at'])),
                    date('d.m.Y H:i', strtotime($idea['updated_at']))
                ], ';');
            }

            fclose($output);

        } catch (Exception $e) {
            error_log("Ошибка экспорта CSV: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Ошибка экспорта данных']);
        }
    }

    // Простой экспорт в HTML для печати/сохранения в PDF
    public function exportToPDF($filters = [])
    {
        try {
            $data = $this->getExportData($filters);

            header('Content-Type: text/html; charset=utf-8');
            header('Content-Disposition: attachment; filename="ideas_report_' . date('Y-m-d') . '.html"');

            $html = $this->generateHTMLReport($data);
            echo $html;

        } catch (Exception $e) {
            error_log("Ошибка экспорта PDF: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Ошибка экспорта данных']);
        }
    }

    private function getExportData($filters = [])
    {
        // Построение WHERE условий (копируем логику из analytics.php)
        $whereConditions = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $placeholders = str_repeat('?,', count($filters['status']) - 1) . '?';
                $whereConditions[] = "i.status IN ($placeholders)";
                $params = array_merge($params, $filters['status']);
            } else {
                $whereConditions[] = "i.status = ?";
                $params[] = $filters['status'];
            }
        }

        if (!empty($filters['category'])) {
            if (is_array($filters['category'])) {
                $placeholders = str_repeat('?,', count($filters['category']) - 1) . '?';
                $whereConditions[] = "i.category IN ($placeholders)";
                $params = array_merge($params, $filters['category']);
            } else {
                $whereConditions[] = "i.category = ?";
                $params[] = $filters['category'];
            }
        }

        if (!empty($filters['date_from'])) {
            $whereConditions[] = "DATE(i.created_at) >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $whereConditions[] = "DATE(i.created_at) <= ?";
            $params[] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $whereConditions[] = "(i.idea LIKE ? OR i.description LIKE ?)";
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }

        $whereClause = implode(' AND ', $whereConditions);

        // Получение идей
        $sql = "SELECT i.*, u.username
                FROM ideas i
                LEFT JOIN users u ON i.user_id = u.id
                WHERE $whereClause
                ORDER BY i.created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $ideas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Статистика
        $stats = [
            'total' => count($ideas),
            'by_status' => [],
            'by_category' => []
        ];

        foreach ($ideas as $idea) {
            // По статусам
            if (!isset($stats['by_status'][$idea['status']])) {
                $stats['by_status'][$idea['status']] = 0;
            }
            $stats['by_status'][$idea['status']]++;

            // По категориям
            if (!isset($stats['by_category'][$idea['category']])) {
                $stats['by_category'][$idea['category']] = 0;
            }
            $stats['by_category'][$idea['category']]++;
        }

        return [
            'ideas' => $ideas,
            'stats' => $stats,
            'filters' => $filters,
            'generated_at' => date('d.m.Y H:i:s')
        ];
    }

    private function generateHTMLReport($data)
    {
        $html = '
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Отчет по идеям - ' . date('d.m.Y') . '</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #333;
            line-height: 1.4;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #49AD09;
        }
        .header h1 {
            color: #49AD09;
            margin: 0;
        }
        .header p {
            color: #666;
            margin: 5px 0;
        }
        .stats-section {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-around;
            text-align: center;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            min-width: 120px;
            border: 1px solid #e9ecef;
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #49AD09;
            margin: 0;
        }
        .stat-label {
            font-size: 0.9em;
            color: #666;
            margin: 5px 0 0 0;
        }
        .section-title {
            font-size: 1.3em;
            color: #49AD09;
            margin: 25px 0 15px 0;
            padding-bottom: 5px;
            border-bottom: 1px solid #e9ecef;
        }
        .filters-info {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9em;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            font-size: 0.9em;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #49AD09;
            color: white;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .status {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
            text-align: center;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-inprogress { background: #cce5ff; color: #004085; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .footer {
            margin-top: 40px;
            text-align: center;
            color: #666;
            font-size: 0.9em;
            border-top: 1px solid #e9ecef;
            padding-top: 20px;
        }
        @media print {
            body { margin: 0; }
            .header { page-break-after: avoid; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; page-break-after: auto; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📊 Отчет по идеям StaffVoice</h1>
        <p>Сгенерирован: ' . $data['generated_at'] . '</p>
        <p>Всего идей в отчете: ' . $data['stats']['total'] . '</p>
    </div>';

        // Фильтры
        if (!empty($data['filters'])) {
            $html .= '<div class="filters-info">
                <strong>Применённые фильтры:</strong><br>';

            foreach ($data['filters'] as $key => $value) {
                if (!empty($value)) {
                    $filterName = $this->getFilterDisplayName($key);
                    $filterValue = is_array($value) ? implode(', ', $value) : $value;
                    $html .= "<strong>{$filterName}:</strong> {$filterValue}<br>";
                }
            }

            $html .= '</div>';
        }

        // Статистика
        $html .= '<h2 class="section-title">📈 Статистика</h2>
        <div class="stats-section">';

        foreach ($data['stats']['by_status'] as $status => $count) {
            $html .= "<div class=\"stat-card\">
                <div class=\"stat-number\">{$count}</div>
                <div class=\"stat-label\">{$status}</div>
            </div>";
        }

        $html .= '</div>';

        // Таблица идей
        $html .= '<h2 class="section-title">💡 Список идей</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Идея</th>
                    <th>Категория</th>
                    <th>Статус</th>
                    <th>Пользователь</th>
                    <th>Дата</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($data['ideas'] as $idea) {
            $statusClass = $this->getStatusClass($idea['status']);
            $date = date('d.m.Y', strtotime($idea['created_at']));

            $html .= "<tr>
                <td>{$idea['id']}</td>
                <td>" . htmlspecialchars($idea['idea']) . "</td>
                <td>{$idea['category']}</td>
                <td><span class=\"status {$statusClass}\">{$idea['status']}</span></td>
                <td>" . ($idea['username'] ?? 'Неизвестно') . "</td>
                <td>{$date}</td>
            </tr>";
        }

        $html .= '</tbody>
        </table>';

        // Категории
        if (!empty($data['stats']['by_category'])) {
            $html .= '<h2 class="section-title">📂 Распределение по категориям</h2>
            <table>
                <thead>
                    <tr>
                        <th>Категория</th>
                        <th>Количество</th>
                        <th>Процент</th>
                    </tr>
                </thead>
                <tbody>';

            foreach ($data['stats']['by_category'] as $category => $count) {
                $percent = round(($count / $data['stats']['total']) * 100, 1);
                $html .= "<tr>
                    <td>{$category}</td>
                    <td>{$count}</td>
                    <td>{$percent}%</td>
                </tr>";
            }

            $html .= '</tbody>
            </table>';
        }

        $html .= '
    <div class="footer">
        <p>Отчет сгенерирован системой StaffVoice</p>
        <p>© ' . date('Y') . ' Все права защищены</p>
    </div>
</body>
</html>';

        return $html;
    }

    private function getStatusClass($status)
    {
        switch ($status) {
            case 'На рассмотрении':
                return 'status-pending';
            case 'Принято':
                return 'status-approved';
            case 'В работе':
                return 'status-inprogress';
            case 'Отклонено':
                return 'status-rejected';
            default:
                return 'status-pending';
        }
    }

    private function getFilterDisplayName($key)
    {
        $names = [
            'status' => 'Статус',
            'category' => 'Категория',
            'date_from' => 'Дата с',
            'date_to' => 'Дата по',
            'search' => 'Поиск'
        ];

        return $names[$key] ?? $key;
    }
}

// Основная логика
try {
    if (!isset($_GET['type'])) {
        throw new Exception('Тип экспорта не указан');
    }

    $type = $_GET['type'];
    $filters = [];

    // Получение фильтров
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input) {
            $filters = $input;
        }
    }

    $export = new SimpleExport($pdo);

    switch ($type) {
        case 'excel':
        case 'csv':
            $export->exportToCSV($filters);
            break;

        case 'pdf':
            $export->exportToPDF($filters);
            break;

        default:
            throw new Exception('Неподдерживаемый тип экспорта');
    }

} catch (Exception $e) {
    error_log("Ошибка в export.php: " . $e->getMessage());
    http_response_code(500);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>