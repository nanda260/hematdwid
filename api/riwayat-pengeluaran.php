<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');

$pdo = getConnection();
$userId = currentUserId();

$limit = 20;
$offset = max(0, (int) ($_GET['offset'] ?? 0));

$where = ['e.user_id = ?'];
$params = [$userId];

$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$categoryId = (int) ($_GET['category_id'] ?? 0);
$bucket = $_GET['bucket'] ?? '';
$search = trim($_GET['search'] ?? '');

if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $where[] = 'e.expense_date >= ?';
    $params[] = $dateFrom;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $where[] = 'e.expense_date <= ?';
    $params[] = $dateTo;
}
if ($categoryId > 0) {
    $where[] = 'e.category_id = ?';
    $params[] = $categoryId;
}
if (in_array($bucket, ['utama', 'nabung', 'bebas'], true)) {
    $where[] = 'e.bucket = ?';
    $params[] = $bucket;
}
if ($search !== '') {
    $where[] = 'e.description LIKE ?';
    $params[] = '%' . $search . '%';
}

$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare(
    "SELECT e.id, e.category_id, e.amount, e.description, e.expense_date, e.bucket, c.name AS category_name
     FROM expenses e INNER JOIN categories c ON c.id = e.category_id
     WHERE $whereSql
     ORDER BY e.expense_date DESC, e.id DESC
     LIMIT " . ($limit + 1) . " OFFSET ?"
);
$params[] = $offset;
$stmt->execute($params);
$rows = $stmt->fetchAll();

$hasMore = count($rows) > $limit;
$rows = array_slice($rows, 0, $limit);

$csrf = csrfToken();
$out = [];
foreach ($rows as $exp) {
    $out[] = [
        'table_html' => renderExpenseRow($exp, $csrf),
        'card_html'  => renderExpenseCard($exp, $csrf),
    ];
}

echo json_encode(['success' => true, 'has_more' => $hasMore, 'rows' => $out]);