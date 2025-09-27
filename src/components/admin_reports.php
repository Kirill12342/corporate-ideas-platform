<?php
require_once 'admin_auth.php';
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    try {
        switch ($action) {
            case 'generate_report':
                $reportType = $input['report_type'] ?? 'summary';
                $format = $input['format'] ?? 'pdf';
                $dateFrom = $input['date_from'] ?? date('Y-m-01');
                $dateTo = $input['date_to'] ?? date('Y-m-d');
                $departments = $input['departments'] ?? [];

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
                        'file_url' => '../uploads/reports/' . $filename,
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
                $reportConfig = [
                    'report_type' => $input['report_type'],
                    'format' => $input['format'],
                    'schedule' => $input['schedule'], // daily, weekly, monthly
                    'recipients' => $input['recipients'],
                    'departments' => $input['departments'] ?? [],
                    'created_by' => $_SESSION['admin_id'],
                    'created_at' => date('Y-m-d H:i:s')
                ];

                $stmt = $pdo->prepare("
                    INSERT INTO scheduled_reports 
                    (report_type, format, schedule_type, recipients, departments, created_by, created_at, is_active)
                    VALUES (:report_type, :format, :schedule, :recipients, :departments, :created_by, :created_at, 1)
                ");

                $stmt->execute([
                    ':report_type' => $reportConfig['report_type'],
                    ':format' => $reportConfig['format'],
                    ':schedule' => $reportConfig['schedule'],
                    ':recipients' => json_encode($reportConfig['recipients']),
                    ':departments' => json_encode($reportConfig['departments']),
                    ':created_by' => $reportConfig['created_by'],
                    ':created_at' => $reportConfig['created_at']
                ]);

                echo json_encode(['success' => true, 'message' => 'Автоматический отчет настроен']);
                break;

            default:
                echo json_encode(['success' => false, 'error' => 'Неизвестное действие']);
        }
    } catch (Exception $e) {
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
                    AVG(i.likes_count) as avg_likes,
                    AVG(i.total_score) as avg_score
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
                SELECT category, COUNT(*) as count
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
                    i.description,
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
                    u.department,
                    COUNT(i.id) as ideas_count,
                    SUM(i.likes_count) as total_likes,
                    AVG(i.total_score) as avg_score,
                    COUNT(CASE WHEN i.status = 'Принято' THEN 1 END) as approved_ideas
                FROM users u
                LEFT JOIN ideas i ON u.id = i.user_id AND i.created_at BETWEEN ? AND ?
                WHERE 1=1 $deptFilter
                GROUP BY u.id
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
    // Здесь можно интегрировать библиотеку для генерации PDF (например, TCPDF или FPDF)
    // Для простоты создадим HTML версию

    $filename = "report_{$reportType}_" . date('Y-m-d_H-i-s') . '.html';
    $filepath = '../uploads/reports/' . $filename;

    if (!is_dir('../uploads/reports/')) {
        mkdir('../uploads/reports/', 0755, true);
    }

    $html = generateReportHTML($report, $reportType);
    file_put_contents($filepath, $html);

    return $filename;
}

function generateExcelReport($report, $reportType) {
    // Генерация CSV файла (можно заменить на Excel библиотеку)
    $filename = "report_{$reportType}_" . date('Y-m-d_H-i-s') . '.csv';
    $filepath = '../uploads/reports/' . $filename;

    if (!is_dir('../uploads/reports/')) {
        mkdir('../uploads/reports/', 0755, true);
    }

    $file = fopen($filepath, 'w');

    switch ($reportType) {
        case 'summary':
            fputcsv($file, ['Метрика', 'Значение']);
            if (isset($report['summary'])) {
                foreach ($report['summary'] as $key => $value) {
                    fputcsv($file, [$key, $value]);
                }
            }
            break;

        case 'ideas_detailed':
            fputcsv($file, ['ID', 'Название', 'Категория', 'Статус', 'Лайки', 'Рейтинг', 'Автор', 'Отдел', 'Дата создания']);
            if (isset($report['ideas'])) {
                foreach ($report['ideas'] as $idea) {
                    fputcsv($file, [
                        $idea['id'],
                        $idea['title'],
                        $idea['category'],
                        $idea['status'],
                        $idea['likes_count'],
                        $idea['total_score'],
                        $idea['author'],
                        $idea['department'],
                        $idea['created_at']
                    ]);
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
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Отчет - {$report['type']}</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
            .summary { background: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f4f4f4; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>Отчет по идеям</h1>
            <p>Период: {$report['period']}</p>
            <p>Дата генерации: " . date('d.m.Y H:i') . "</p>
        </div>
    ";

    if ($reportType === 'summary' && isset($report['summary'])) {
        $html .= "
        <div class='summary'>
            <h2>Сводная статистика</h2>
            <p>Всего идей: {$report['summary']['total_ideas']}</p>
            <p>Активных пользователей: {$report['summary']['active_users']}</p>
            <p>Принятых идей: {$report['summary']['approved_ideas']}</p>
            <p>В работе: {$report['summary']['in_progress_ideas']}</p>
            <p>Средний рейтинг: " . round($report['summary']['avg_score'], 2) . "</p>
        </div>";
    }

    $html .= "</body></html>";
    return $html;
}
?>
