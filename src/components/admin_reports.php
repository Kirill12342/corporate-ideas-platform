<?php
require_once 'admin_auth.php';
require_once 'config.php';

// Проверяем, что подключение к БД установлено
if (!isset($pdo) || !$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Нет подключения к базе данных']);
    exit();
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    try {
        switch ($action) {
            case 'generate_report':
                $reportType = in_array($input['report_type'] ?? 'summary', ['summary', 'ideas_detailed', 'users_activity', 'departments_comparison']) ? $input['report_type'] : 'summary';
                $format = in_array($input['format'] ?? 'pdf', ['pdf', 'excel', 'csv']) ? $input['format'] : 'pdf';
                $dateFrom = $input['date_from'] ?? date('Y-m-01');
                $dateTo = $input['date_to'] ?? date('Y-m-d');
                $departments = is_array($input['departments'] ?? []) ? $input['departments'] : [];

                // Валидация дат
                if (!DateTime::createFromFormat('Y-m-d', $dateFrom) || !DateTime::createFromFormat('Y-m-d', $dateTo)) {
                    throw new Exception('Неверный формат даты');
                }

                $report = generateReport($pdo, $reportType, $dateFrom, $dateTo, $departments);

                if ($format === 'pdf') {
                    $filename = generatePDFReport($report, $reportType);
                } elseif ($format === 'excel') {
                    $filename = generateExcelReport($report, $reportType);
                } else {
                    $filename = generateCSVReport($report, $reportType);
                }

                echo json_encode([
                    'success' => true,
                    'data' => [
                        'file_url' => 'uploads/reports/' . $filename,
                        'filename' => $filename
                    ]
                ]);
                break;

            case 'get_report_templates':
                $templates = [
                    'summary' => [
                        'name' => 'Общий отчет',
                        'description' => 'Сводная информация по всем идеям и пользователям'
                    ],
                    'ideas_detailed' => [
                        'name' => 'Детальный отчет по идеям',
                        'description' => 'Подробная информация по каждой идее'
                    ],
                    'users_activity' => [
                        'name' => 'Активность пользователей',
                        'description' => 'Статистика активности сотрудников'
                    ],
                    'departments_comparison' => [
                        'name' => 'Сравнение отделов',
                        'description' => 'Сравнительный анализ по отделам'
                    ],
                    'monthly_trends' => [
                        'name' => 'Месячные тренды',
                        'description' => 'Динамика показателей по месяцам'
                    ],
                    'performance_metrics' => [
                        'name' => 'Показатели эффективности',
                        'description' => 'KPI и метрики производительности'
                    ]
                ];

                echo json_encode(['success' => true, 'data' => $templates]);
                break;

            case 'schedule_report':
                // Проверяем, что пользователь авторизован как админ
                if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
                    throw new Exception('Необходима авторизация администратора');
                }

                $reportConfig = [
                    'report_type' => $input['report_type'] ?? 'summary',
                    'format' => $input['format'] ?? 'pdf',
                    'schedule' => $input['schedule'] ?? 'monthly', // daily, weekly, monthly
                    'recipients' => $input['recipients'] ?? [],
                    'departments' => $input['departments'] ?? [],
                    'created_by' => $_SESSION['admin_id'] ?? 1,
                    'created_at' => date('Y-m-d H:i:s')
                ];

                // Проверяем существование таблицы scheduled_reports
                $checkTable = $pdo->query("SHOW TABLES LIKE 'scheduled_reports'");
                if ($checkTable->rowCount() == 0) {
                    // Создаем таблицу, если её нет
                    $createTable = "
                        CREATE TABLE scheduled_reports (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            report_type VARCHAR(50) NOT NULL,
                            format VARCHAR(10) NOT NULL,
                            schedule_type VARCHAR(20) NOT NULL,
                            recipients TEXT,
                            departments TEXT,
                            created_by INT,
                            created_at DATETIME,
                            is_active BOOLEAN DEFAULT TRUE
                        )
                    ";
                    $pdo->exec($createTable);
                }

                $stmt = $pdo->prepare("
                    INSERT INTO scheduled_reports 
                    (report_type, format, schedule_type, recipients, departments, created_by, created_at, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                ");

                $stmt->execute([
                    $reportConfig['report_type'],
                    $reportConfig['format'],
                    $reportConfig['schedule'],
                    json_encode($reportConfig['recipients']),
                    json_encode($reportConfig['departments']),
                    $reportConfig['created_by'],
                    $reportConfig['created_at']
                ]);

                echo json_encode(['success' => true, 'message' => 'Автоматический отчет настроен']);
                break;

            default:
                echo json_encode(['success' => false, 'error' => 'Неизвестное действие']);
        }
    } catch (PDOException $e) {
        error_log("Database error in admin_reports.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Ошибка базы данных']);
    } catch (Exception $e) {
        error_log("Error in admin_reports.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Метод не поддерживается']);
}

function generateReport($pdo, $reportType, $dateFrom, $dateTo, $departments = []) {
    $report = ['type' => $reportType, 'period' => "$dateFrom - $dateTo"];

    // Фильтр по отделам
    $deptFilter = '';
    $deptParams = [];
    if (!empty($departments)) {
        $deptPlaceholders = str_repeat('?,', count($departments) - 1) . '?';
        $deptFilter = "AND u.department IN ($deptPlaceholders)";
        $deptParams = $departments;
    }

    switch ($reportType) {
        case 'summary':
            // Общая статистика
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(i.id) as total_ideas,
                    COUNT(DISTINCT i.user_id) as active_users,
                    COUNT(CASE WHEN i.status = 'Принято' THEN 1 END) as approved_ideas,
                    COUNT(CASE WHEN i.status = 'В работе' THEN 1 END) as in_progress_ideas,
                    COUNT(CASE WHEN i.status = 'Отклонено' THEN 1 END) as rejected_ideas,
                    COALESCE(AVG(i.likes_count), 0) as avg_likes,
                    COALESCE(AVG(i.total_score), 0) as avg_score
                FROM ideas i
                JOIN users u ON i.user_id = u.id
                WHERE i.created_at BETWEEN ? AND ?
                $deptFilter
            ");
            $params = [$dateFrom, $dateTo, ...$deptParams];
            $stmt->execute($params);
            $report['summary'] = $stmt->fetch(PDO::FETCH_ASSOC);

            // Топ категории
            $stmt = $pdo->prepare("
                SELECT COALESCE(category, 'Без категории') as category, COUNT(*) as count
                FROM ideas i
                JOIN users u ON i.user_id = u.id
                WHERE i.created_at BETWEEN ? AND ?
                $deptFilter
                GROUP BY category
                ORDER BY count DESC
                LIMIT 10
            ");
            $stmt->execute($params);
            $report['top_categories'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'ideas_detailed':
            $stmt = $pdo->prepare("
                SELECT 
                    i.id,
                    i.title,
                    SUBSTRING(i.description, 1, 100) as description,
                    i.category,
                    i.status,
                    i.likes_count,
                    i.total_score,
                    i.created_at,
                    u.fullname as author,
                    u.department
                FROM ideas i
                JOIN users u ON i.user_id = u.id
                WHERE i.created_at BETWEEN ? AND ?
                $deptFilter
                ORDER BY i.created_at DESC
            ");
            $params = [$dateFrom, $dateTo, ...$deptParams];
            $stmt->execute($params);
            $report['ideas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'users_activity':
            $stmt = $pdo->prepare("
                SELECT 
                    u.fullname,
                    COALESCE(u.department, 'Не указан') as department,
                    COUNT(i.id) as ideas_count,
                    COALESCE(SUM(i.likes_count), 0) as total_likes,
                    COALESCE(AVG(i.total_score), 0) as avg_score,
                    COUNT(CASE WHEN i.status = 'Принято' THEN 1 END) as approved_ideas
                FROM users u
                LEFT JOIN ideas i ON u.id = i.user_id AND i.created_at BETWEEN ? AND ?
                WHERE 1=1 $deptFilter
                GROUP BY u.id, u.fullname, u.department
                HAVING ideas_count > 0
                ORDER BY ideas_count DESC
            ");
            $params = [$dateFrom, $dateTo, ...$deptParams];
            $stmt->execute($params);
            $report['users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
    }

    return $report;
}

function generatePDFReport($report, $reportType) {
    // Создаем директорию если её нет
    $uploadsDir = __DIR__ . '/../uploads/reports';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }

    $filename = "report_{$reportType}_" . date('Y-m-d_H-i-s') . '.html';
    $filepath = $uploadsDir . '/' . $filename;

    $html = generateReportHTML($report, $reportType);

    if (file_put_contents($filepath, $html) === false) {
        throw new Exception('Не удалось создать файл отчета');
    }

    return $filename;
}

function generateExcelReport($report, $reportType) {
    // Создаем директорию если её нет
    $uploadsDir = __DIR__ . '/../uploads/reports';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }

    $filename = "report_{$reportType}_" . date('Y-m-d_H-i-s') . '.csv';
    $filepath = $uploadsDir . '/' . $filename;

    $file = fopen($filepath, 'w');
    if ($file === false) {
        throw new Exception('Не удалось создать файл отчета');
    }

    // Добавляем BOM для корректного отображения русских символов в Excel
    fwrite($file, "\xEF\xBB\xBF");

    switch ($reportType) {
        case 'summary':
            fputcsv($file, ['Метрика', 'Значение'], ';');
            if (isset($report['summary'])) {
                foreach ($report['summary'] as $key => $value) {
                    $metricName = match($key) {
                        'total_ideas' => 'Всего идей',
                        'active_users' => 'Активных пользователей',
                        'approved_ideas' => 'Принятых идей',
                        'in_progress_ideas' => 'Идей в работе',
                        'rejected_ideas' => 'Отклоненных идей',
                        'avg_likes' => 'Среднее количество лайков',
                        'avg_score' => 'Средний рейтинг',
                        default => $key
                    };
                    fputcsv($file, [$metricName, round($value, 2)], ';');
                }
            }
            break;

        case 'ideas_detailed':
            fputcsv($file, ['ID', 'Название', 'Категория', 'Статус', 'Лайки', 'Рейтинг', 'Автор', 'Отдел', 'Дата создания'], ';');
            if (isset($report['ideas'])) {
                foreach ($report['ideas'] as $idea) {
                    fputcsv($file, [
                        $idea['id'],
                        $idea['title'],
                        $idea['category'] ?? 'Без категории',
                        $idea['status'],
                        $idea['likes_count'],
                        $idea['total_score'],
                        $idea['author'],
                        $idea['department'] ?? 'Не указан',
                        $idea['created_at']
                    ], ';');
                }
            }
            break;

        case 'users_activity':
            fputcsv($file, ['ФИО', 'Отдел', 'Количество идей', 'Всего лайков', 'Средний рейтинг', 'Принятых идей'], ';');
            if (isset($report['users'])) {
                foreach ($report['users'] as $user) {
                    fputcsv($file, [
                        $user['fullname'],
                        $user['department'],
                        $user['ideas_count'],
                        $user['total_likes'],
                        round($user['avg_score'], 2),
                        $user['approved_ideas']
                    ], ';');
                }
            }
            break;
    }

    fclose($file);
    return $filename;
}

function generateCSVReport($report, $reportType) {
    return generateExcelReport($report, $reportType); // Используем ту же логику
}

function generateReportHTML($report, $reportType) {
    $html = "
    <!DOCTYPE html>
    <html lang='ru'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Отчет - {$report['type']}</title>
        <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 20px; line-height: 1.6; }
            .header { border-bottom: 3px solid #3498db; padding-bottom: 15px; margin-bottom: 25px; }
            .header h1 { color: #2c3e50; margin: 0 0 10px 0; }
            .summary { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 25px; border-left: 4px solid #3498db; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 25px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            th, td { border: 1px solid #dee2e6; padding: 12px; text-align: left; }
            th { background-color: #3498db; color: white; font-weight: 600; }
            tr:nth-child(even) { background-color: #f8f9fa; }
            .metric { display: inline-block; margin: 10px 15px 10px 0; }
            .metric-label { font-weight: bold; color: #2c3e50; }
            .metric-value { color: #3498db; font-size: 1.2em; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>📊 Отчет по корпоративным идеям</h1>
            <p><strong>Период:</strong> {$report['period']}</p>
            <p><strong>Дата генерации:</strong> " . date('d.m.Y H:i') . "</p>
            <p><strong>Тип отчета:</strong> " . match($report['type']) {
                'summary' => 'Общий отчет',
                'ideas_detailed' => 'Детальный отчет по идеям',
                'users_activity' => 'Активность пользователей',
                default => $report['type']
            } . "</p>
        </div>
    ";

    if ($reportType === 'summary' && isset($report['summary'])) {
        $summary = $report['summary'];
        $html .= "
        <div class='summary'>
            <h2>📈 Сводная статистика</h2>
            <div class='metric'>
                <span class='metric-label'>Всего идей:</span>
                <span class='metric-value'>{$summary['total_ideas']}</span>
            </div>
            <div class='metric'>
                <span class='metric-label'>Активных пользователей:</span>
                <span class='metric-value'>{$summary['active_users']}</span>
            </div>
            <div class='metric'>
                <span class='metric-label'>Принятых идей:</span>
                <span class='metric-value'>{$summary['approved_ideas']}</span>
            </div>
            <div class='metric'>
                <span class='metric-label'>В работе:</span>
                <span class='metric-value'>{$summary['in_progress_ideas']}</span>
            </div>
            <div class='metric'>
                <span class='metric-label'>Средний рейтинг:</span>
                <span class='metric-value'>" . round($summary['avg_score'], 2) . "</span>
            </div>
        </div>";

        if (isset($report['top_categories']) && !empty($report['top_categories'])) {
            $html .= "<h3>🏷️ Топ категорий</h3><table><tr><th>Категория</th><th>Количество идей</th></tr>";
            foreach ($report['top_categories'] as $cat) {
                $html .= "<tr><td>{$cat['category']}</td><td>{$cat['count']}</td></tr>";
            }
            $html .= "</table>";
        }
    }

    $html .= "
        <div style='margin-top: 40px; padding-top: 20px; border-top: 1px solid #dee2e6; color: #6c757d; font-size: 0.9em;'>
            <p>Отчет сгенерирован системой корпоративных идей StaffVoice</p>
        </div>
    </body>
    </html>";

    return $html;
}
?>
