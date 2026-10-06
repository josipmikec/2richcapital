<?php
ob_start();
require_once '../../auth/session-config.php';
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');
ob_end_clean();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$incoming_csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($incoming_csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $incoming_csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF token mismatch']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No file uploaded.']);
    exit;
}

$group_id = isset($_POST['group_id']) ? (int) $_POST['group_id'] : 0;
if ($group_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid group.']);
    exit;
}

// -------------------------------------------------------------
// R2 / S3 CLOUD STORAGE CONFIGURATION
// -------------------------------------------------------------
require_once __DIR__ . '/../../components/r2-storage.php';

if (R2_ACCESS_KEY === 'YOUR_ACCESS_KEY_HERE') {
    echo json_encode(['success' => false, 'message' => 'Cloud Storage keys are not configured yet!']);
    exit;
}

$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File upload error code: ' . $file['error']]);
    exit;
}

$allowed_types = [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'application/pdf',
    'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'text/csv', 'text/plain', 'text/markdown',
    'application/zip', 'application/x-zip-compressed',
    'video/mp4', 'video/quicktime'
];
$mime_type = mime_content_type($file['tmp_name']);
// Fallback for some markdown files that register as octet-stream locally
if ($mime_type === 'application/octet-stream') {
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['md', 'csv', 'txt'])) {
        $mime_type = 'text/plain'; 
    }
}
if (!in_array($mime_type, $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'File type not allowed. (' . $mime_type . ')']);
    exit;
}

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'group_' . $group_id . '/chat/' . uniqid() . '_' . time() . '.' . $ext;
$file_contents = file_get_contents($file['tmp_name']);

// AWS V4 Signature generation for R2/S3
$host = parse_url(R2_ENDPOINT, PHP_URL_HOST);
$service = 's3';
$timestamp = gmdate('Ymd\THis\Z');
$date = gmdate('Ymd');
$payload_hash = hash('sha256', $file_contents);

$headers = [
    'host' => $host,
    'x-amz-content-sha256' => $payload_hash,
    'x-amz-date' => $timestamp,
    'content-type' => $mime_type
];
ksort($headers);

$canonical_headers = '';
$signed_headers = '';
foreach ($headers as $k => $v) {
    $canonical_headers .= strtolower($k) . ':' . trim($v) . "\n";
    $signed_headers .= strtolower($k) . ';';
}
$signed_headers = rtrim($signed_headers, ';');

$canonical_request = "PUT\n/" . R2_BUCKET . "/" . $filename . "\n\n" . $canonical_headers . "\n" . $signed_headers . "\n" . $payload_hash;
$credential_scope = $date . '/' . R2_REGION . '/' . $service . '/aws4_request';
$string_to_sign = "AWS4-HMAC-SHA256\n" . $timestamp . "\n" . $credential_scope . "\n" . hash('sha256', $canonical_request);

$kSecret = 'AWS4' . R2_SECRET_KEY;
$kDate = hash_hmac('sha256', $date, $kSecret, true);
$kRegion = hash_hmac('sha256', R2_REGION, $kDate, true);
$kService = hash_hmac('sha256', $service, $kRegion, true);
$kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
$signature = hash_hmac('sha256', $string_to_sign, $kSigning);

$authorization_header = "AWS4-HMAC-SHA256 Credential=" . R2_ACCESS_KEY . "/{$credential_scope}, SignedHeaders={$signed_headers}, Signature={$signature}";

$url = rtrim(R2_ENDPOINT, '/') . '/' . R2_BUCKET . '/' . $filename;
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
curl_setopt($ch, CURLOPT_POSTFIELDS, $file_contents);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: ' . $authorization_header,
    'x-amz-content-sha256: ' . $payload_hash,
    'x-amz-date: ' . $timestamp,
    'Content-Type: ' . $mime_type
]);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code >= 200 && $http_code < 300) {
    $public_url = rtrim(R2_PUBLIC_URL, '/') . '/' . $filename;
    echo json_encode([
        'success' => true,
        'url' => $public_url,
        'message' => 'Upload successful.'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to upload to cloud storage.',
        'http_code' => $http_code,
        'response' => $response
    ]);
}
