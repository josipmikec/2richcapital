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
$body = json_decode(file_get_contents('php://input'), true) ?: [];
$user_id = (int) $_SESSION['user_id'];
$group_id = (int) ($body['group_id'] ?? 0);
$note = trim((string) ($body['note'] ?? ''));
if ($group_id <= 0) { echo json_encode(['success'=>false,'message'=>'Valid group_id is required.']); exit; }
global $wpdb;
$groups_table = $wpdb->prefix . 'rich_signal_groups';
$memberships_table = $wpdb->prefix . 'rich_signal_memberships';
$audit_table = $wpdb->prefix . 'rich_signal_group_audit_log';
$membership = $wpdb->get_row($wpdb->prepare("SELECT role, status FROM {$memberships_table} WHERE group_id = %d AND user_id = %d LIMIT 1", $group_id, $user_id), ARRAY_A);
if (!$membership || $membership['status'] !== 'active' || ($membership['role'] ?? '') !== 'owner') { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Only the group owner can request official verification.']); exit; }
$group = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$groups_table} WHERE id = %d LIMIT 1", $group_id), ARRAY_A);
if (!$group) { http_response_code(404); echo json_encode(['success'=>false,'message'=>'Group not found.']); exit; }
if (($group['status'] ?? 'draft') !== 'live') { echo json_encode(['success'=>false,'message'=>'Publish the group before applying for official verification.']); exit; }
if (($group['verification_status'] ?? 'none') === 'verified') { echo json_encode(['success'=>false,'message'=>'This group is already official.']); exit; }
if (($group['verification_status'] ?? 'none') === 'pending') { echo json_encode(['success'=>false,'message'=>'Verification is already pending review.']); exit; }
$updated = $wpdb->update($groups_table, ['verification_status'=>'pending','verification_note'=>$note !== '' ? $note : null,'verification_requested_at'=>current_time('mysql'),'verified_at'=>null,'verified_by'=>null], ['id'=>$group_id]);
if ($updated === false) { http_response_code(500); echo json_encode(['success'=>false,'message'=>'Failed to submit verification request.']); exit; }
$wpdb->insert($audit_table, ['group_id'=>$group_id,'actor_user_id'=>$user_id,'action'=>'group_verification_requested','meta_json'=>wp_json_encode(['note'=>$note])]);
echo json_encode(['success'=>true,'message'=>'Verification request submitted.','group_id'=>$group_id,'verification_status'=>'pending']);
