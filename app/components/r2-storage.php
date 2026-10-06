<?php
/**
 * Cloudflare R2 Storage Manager
 * Centralized functions for interacting with the 2RICH Cloudflare R2 Bucket
 */

define('R2_ACCESS_KEY', '03e93381c315af26a27e301455085bae');
define('R2_SECRET_KEY', '6bdfdd3a039cffb3656cf1ba054b4031ef4a04b0b15abfddd63526b322482944');
define('R2_BUCKET', '2rich-chat-media');
define('R2_ENDPOINT', 'https://025f29208947cca0c77b4ab45d61e907.r2.cloudflarestorage.com');
define('R2_PUBLIC_URL', 'https://pub-cc86db20f3b2468e9bff2275eb9ad38a.r2.dev');
define('R2_REGION', 'auto');

/**
 * Hard delete a file from Cloudflare R2 bucket.
 * 
 * @param string $public_url The full public URL of the file (e.g. https://pub-...r2.dev/group_5/chat/img.jpg)
 * @return bool True if deleted successfully or doesn't exist, False on failure.
 */
function rich_r2_delete_file($public_url) {
    if (empty($public_url)) return true;

    // Ensure the URL actually belongs to our R2 bucket
    if (strpos($public_url, R2_PUBLIC_URL) !== 0) {
        // Not an R2 URL (maybe a legacy local upload or external link), ignore.
        return false;
    }

    // Extract the object key (filename with path) from the public URL
    $object_key = str_replace(R2_PUBLIC_URL . '/', '', $public_url);
    $object_key = trim($object_key, '/');
    
    if (empty($object_key)) return false;

    // AWS V4 Signature Generation for DELETE request
    $host = parse_url(R2_ENDPOINT, PHP_URL_HOST);
    $service = 's3';
    $timestamp = gmdate('Ymd\THis\Z');
    $date = gmdate('Ymd');
    
    // For DELETE, payload is empty
    $payload_hash = hash('sha256', '');

    $headers = [
        'host' => $host,
        'x-amz-content-sha256' => $payload_hash,
        'x-amz-date' => $timestamp
    ];
    ksort($headers);

    $canonical_headers = '';
    $signed_headers = '';
    foreach ($headers as $k => $v) {
        $canonical_headers .= strtolower($k) . ':' . trim($v) . "\n";
        $signed_headers .= strtolower($k) . ';';
    }
    $signed_headers = rtrim($signed_headers, ';');

    // Build the Canonical Request
    $canonical_request = "DELETE\n/" . R2_BUCKET . "/" . $object_key . "\n\n" . $canonical_headers . "\n" . $signed_headers . "\n" . $payload_hash;
    $credential_scope = $date . '/' . R2_REGION . '/' . $service . '/aws4_request';
    $string_to_sign = "AWS4-HMAC-SHA256\n" . $timestamp . "\n" . $credential_scope . "\n" . hash('sha256', $canonical_request);

    // Calculate Signature
    $kSecret = 'AWS4' . R2_SECRET_KEY;
    $kDate = hash_hmac('sha256', $date, $kSecret, true);
    $kRegion = hash_hmac('sha256', R2_REGION, $kDate, true);
    $kService = hash_hmac('sha256', $service, $kRegion, true);
    $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
    $signature = hash_hmac('sha256', $string_to_sign, $kSigning);

    $authorization_header = "AWS4-HMAC-SHA256 Credential=" . R2_ACCESS_KEY . "/{$credential_scope}, SignedHeaders={$signed_headers}, Signature={$signature}";

    // Execute CURL DELETE
    $url = rtrim(R2_ENDPOINT, '/') . '/' . R2_BUCKET . '/' . $object_key;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: ' . $authorization_header,
        'x-amz-content-sha256: ' . $payload_hash,
        'x-amz-date: ' . $timestamp
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // S3 DELETE returns 204 No Content on success (even if file didn't exist)
    return ($http_code >= 200 && $http_code < 300);
}
