<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');

$pdo = getConnection();
$userId = currentUserId();

$limit = 20;
$offset = max(0, (int) ($_GET['offset'] ?? 0));

$where = ['i.user_id = ?'];
$params = [$userId];

$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$categoryId = (int) ($_GET['category_id'] ?? 0);
$search = trim($_GET['search'] ?? '');

if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $where[] = 'i.income_date >= ?';
    $params[] = $dateFrom;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $where[] = 'i.income_date <= ?';
    $params[] = $dateTo;
}
if ($categoryId > 0) {
    $where[] = 'i.category_id = ?';
    $params[] = $categoryId;
}
if ($search !== '') {
    $where[] = 'i.description LIKE ?';
    $params[] = '%' . $search . '%';
}

$whereSql = implode(' AND ', $where);

$totalStmt = $pdo->prepare("SELECT COALESCE(SUM(i.amount), 0) AS total FROM incomes i WHERE $whereSql");
$totalStmt->execute($params);
$totalAmount = (float) $totalStmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT i.id, i.category_id, i.amount, i.description, i.income_date, c.name AS category_name     FROM incomes i INNER JOIN categories c ON c.id = i.category_id
     WHERE $whereSql
     ORDER BY i.income_date DESC, i.id DESC
     LIMIT " . ($limit + 1) . " OFFSET ?"
);
$params[] = $offset;
$stmt->execute($params);
$rows = $stmt->fetchAll();

$hasMore = count($rows) > $limit;
$rows = array_slice($rows, 0, $limit);

$csrf = csrfToken();
$out = [];
foreach ($rows as $inc) {
    $out[] = [
        'table_html' => renderIncomeRow($inc, $csrf),
        'card_html'  => renderIncomeCard($inc, $csrf),
    ];
}

echo json_encode([
    'success' => true,
    'has_more' => $hasMore,
    'rows' => $out,
    'total' => $totalAmount,
    'total_formatted' => formatRupiah($totalAmount),
]);