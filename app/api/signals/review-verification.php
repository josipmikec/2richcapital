<?php
ob_start();
require_once '../../auth/session-config.php';
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');
ob_end_clean();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated'])) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
$incoming_csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($incoming_csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $incoming_csrf)) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'CSRF token mismatch']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed']); exit; }
$user_id = (int) $_SESSION['user_id'];
$wp_user = get_userdata($user_id);
if (!$wp_user || !user_can($wp_user, 'manage_options')) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Only platform admins can review official verification requests.']); exit; }
$body = json_decode(file_get_contents('php://input'), true) ?: [];
$group_id = (int) ($body['group_id'] ?? 0);
$decision = ($body['decision'] ?? 'approve') === 'reject' ? 'reject' : 'approve';
$note = trim((string) ($body['note'] ?? ''));
if ($group_id <= 0) { echo json_encode(['success'=>false,'message'=>'Valid group_id is required.']); exit; }
global $wpdb;
$groups_table = $wpdb->prefix . 'rich_signal_groups';
$audit_table = $wpdb->prefix . 'rich_signal_group_audit_log';
$group = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$groups_table} WHERE id = %d LIMIT 1", $group_id), ARRAY_A);
if (!$group) { http_response_code(404); echo json_encode(['success'=>false,'message'=>'Group not found.']); exit; }
if (($group['verification_status'] ?? 'none') !== 'pending') { echo json_encode(['success'=>false,'message'=>'This group does not have a pending verification request.']); exit; }
$verification_status = $decision === 'approve' ? 'verified' : 'rejected';
$updated = $wpdb->update($groups_table, ['verification_status'=>$verification_status,'verification_note'=>$note !== '' ? $note : null,'verified_at'=>$decision === 'approve' ? current_time('mysql') : null,'verified_by'=>$decision === 'approve' ? $user_id : null], ['id'=>$group_id]);
if ($updated === false) { http_response_code(500); echo json_encode(['success'=>false,'message'=>'Failed to review verification request.']); exit; }
$wpdb->insert($audit_table, ['group_id'=>$group_id,'actor_user_id'=>$user_id,'action'=>$decision === 'approve' ? 'group_verified' : 'group_verification_rejected','meta_json'=>wp_json_encode(['note'=>$note])]);
echo json_encode(['success'=>true,'message'=>$decision === 'approve' ? 'Group marked as official.' : 'Verification request rejected.','group_id'=>$group_id,'verification_status'=>$verification_status]);
