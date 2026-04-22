<?php
require_once dirname(__DIR__, 2) . '/utils/auth_middleware.php';

// 2. ACTIVATE THE SHIELD (Crucial Step!)
// This one line ensures only Admins can proceed. 
// Everyone else (including Farmers/Buyers) gets kicked out immediately.
$adminData = require_admin(); // ✅ This looks in the Global space where we put it





/**
 * Export platform report as CSV (Excel compatible)
 */
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="trustlink_report_' . date('Y-m-d') . '.csv"');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/auth_middleware.php';

use TrustLink\Config\Database;
use TrustLink\Utils\AuthMiddleware;

$auth = new AuthMiddleware();
$auth->requireAdmin();

$period = $_GET['period'] ?? 'month';

// Date condition
switch ($period) {
    case 'today':
        $dateCondition = "AND created_at >= CURRENT_DATE";
        break;
    case 'week':
        $dateCondition = "AND created_at >= CURRENT_DATE - INTERVAL '7 days'";
        break;
    case 'month':
        $dateCondition = "AND created_at >= CURRENT_DATE - INTERVAL '30 days'";
        break;
    case 'year':
        $dateCondition = "AND created_at >= CURRENT_DATE - INTERVAL '365 days'";
        break;
    default:
        $dateCondition = "";
}

try {
    $db = Database::getInstance();
    $output = fopen('php://output', 'w');

    // Set UTF-8 BOM for Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Header
    fputcsv($output, ['TrustLink Platform Report']);
    fputcsv($output, ['Generated on', date('Y-m-d H:i:s')]);
    fputcsv($output, ['Period', $period]);
    fputcsv($output, []);

    // Users summary
    $stmt = $db->query("SELECT COUNT(*) FROM users");
    $totalUsers = $stmt->fetchColumn();
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE 1=1 $dateCondition");
    $stmt->execute();
    $newUsers = $stmt->fetchColumn();

    fputcsv($output, ['USER STATISTICS']);
    fputcsv($output, ['Total Users', $totalUsers]);
    fputcsv($output, ['New Users (period)', $newUsers]);
    fputcsv($output, []);

    // Orders summary
    $stmt = $db->query("SELECT COUNT(*), COALESCE(SUM(total),0) FROM orders");
    list($totalOrders, $totalRevenue) = $stmt->fetch();
    $stmt = $db->prepare("SELECT COUNT(*), COALESCE(SUM(total),0) FROM orders WHERE 1=1 $dateCondition");
    $stmt->execute();
    list($periodOrders, $periodRevenue) = $stmt->fetch();

    fputcsv($output, ['ORDER STATISTICS']);
    fputcsv($output, ['Total Orders', $totalOrders]);
    fputcsv($output, ['Total Revenue (KES)', $totalRevenue]);
    fputcsv($output, ['Orders in Period', $periodOrders]);
    fputcsv($output, ['Revenue in Period (KES)', $periodRevenue]);
    fputcsv($output, []);

    // Top farmers
    $stmt = $db->query("
        SELECT u.name, COALESCE(SUM(o.total),0) as earnings, COUNT(o.id) as orders
        FROM users u
        LEFT JOIN orders o ON u.id = o.farmer_id AND o.status = 'completed'
        WHERE u.role = 'farmer'
        GROUP BY u.id, u.name
        ORDER BY earnings DESC
        LIMIT 10
    ");
    $topFarmers = $stmt->fetchAll();

    fputcsv($output, ['TOP 10 FARMERS']);
    fputcsv($output, ['Name', 'Earnings (KES)', 'Completed Orders']);
    foreach ($topFarmers as $farmer) {
        fputcsv($output, [$farmer['name'], $farmer['earnings'], $farmer['orders']]);
    }
    fputcsv($output, []);

    // Daily breakdown (last 30 days)
    fputcsv($output, ['DAILY BREAKDOWN (Last 30 days)']);
    fputcsv($output, ['Date', 'New Users', 'New Orders', 'Revenue (KES)']);

    $stmt = $db->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(CASE WHEN table_name = 'users' THEN 1 END) as new_users,
            COUNT(CASE WHEN table_name = 'orders' THEN 1 END) as new_orders,
            COALESCE(SUM(CASE WHEN table_name = 'orders' THEN total ELSE 0 END), 0) as revenue
        FROM (
            SELECT created_at, 'users' as table_name, NULL as total FROM users WHERE created_at >= CURRENT_DATE - INTERVAL '30 days'
            UNION ALL
            SELECT created_at, 'orders' as table_name, total FROM orders WHERE created_at >= CURRENT_DATE - INTERVAL '30 days'
        ) combined
        GROUP BY date
        ORDER BY date ASC
    ");
    $stmt->execute();
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['date'],
            $row['new_users'],
            $row['new_orders'],
            $row['revenue']
        ]);
    }

    fclose($output);
    exit;

} catch (\Exception $e) {
    // If error, output as plain text
    header('Content-Type: text/plain');
    echo "Export failed: " . $e->getMessage();
}