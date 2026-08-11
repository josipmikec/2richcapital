<?php
// /api/dashboard/load-layout.php
require_once '../../auth/session-config.php';
require_once '../../auth/db.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || empty($_SESSION['authenticated'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'DB unavailable']);
    exit;
}

$uid  = (int)$_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT layout_order FROM user_dashboard_layouts WHERE user_id = ? LIMIT 1');
$stmt->execute([$uid]);
$row  = $stmt->fetch(PDO::FETCH_ASSOC);

$allowed = ['market','signals','news','classroom','strategies','trades','mentors','ai','chat','journal'];

if ($row && !empty($row['layout_order'])) {
    $saved   = json_decode($row['layout_order'], true);
    $saved   = is_array($saved) ? array_values(array_filter($saved, fn($id) => in_array($id, $allowed))) : [];
    $missing = array_values(array_filter($allowed, fn($id) => !in_array($id, $saved)));
    echo json_encode(['success' => true, 'order' => array_merge($saved, $missing)]);
} else {
    echo json_encode(['success' => true, 'order' => $allowed]);
}
