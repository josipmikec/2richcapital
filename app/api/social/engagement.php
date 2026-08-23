<?php
ob_start();
require_once '../../auth/session-config.php';
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');
require_once '../csrf.php';
ob_end_clean();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated'])) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
global $wpdb;
$user_id=(int)$_SESSION['user_id'];
$post_table=$wpdb->prefix.'rich_social_posts';
$likes=$wpdb->prefix.'rich_post_likes'; $saves=$wpdb->prefix.'rich_post_saves'; $comments=$wpdb->prefix.'rich_post_comments'; $shares=$wpdb->prefix.'rich_post_shares';
require_once ABSPATH.'wp-admin/includes/upgrade.php'; $charset=$wpdb->get_charset_collate();
dbDelta("CREATE TABLE {$likes} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, post_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(id), UNIQUE KEY post_user(post_id,user_id), KEY post_idx(post_id), KEY user_idx(user_id)) {$charset};");
dbDelta("CREATE TABLE {$saves} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, post_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(id), UNIQUE KEY post_user(post_id,user_id), KEY post_idx(post_id), KEY user_idx(user_id)) {$charset};");
dbDelta("CREATE TABLE {$comments} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, post_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, body TEXT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(id), KEY post_idx(post_id), KEY user_idx(user_id), KEY created_idx(created_at)) {$charset};");
dbDelta("CREATE TABLE {$shares} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, post_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(id), KEY post_idx(post_id), KEY user_idx(user_id)) {$charset};");
$payload=json_decode(file_get_contents('php://input'),true); if(!is_array($payload)) $payload=$_POST;
$post_id=isset($payload['post_id'])?(int)$payload['post_id']:0; $action=sanitize_key($payload['action']??'');
$exists=$post_id>0 ? $wpdb->get_var($wpdb->prepare("SELECT id FROM {$post_table} WHERE id=%d LIMIT 1",$post_id)) : 0;
if(!$exists){http_response_code(400);echo json_encode(['success'=>false,'message'=>'Invalid post','post_id'=>$post_id]);exit;}
if($_SERVER['REQUEST_METHOD']==='GET') $action='state'; else {if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);echo json_encode(['success'=>false,'message'=>'Method not allowed']);exit;} verify_csrf();}
if($action==='like'||$action==='unlike'){if($action==='like')$wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$likes}(post_id,user_id) VALUES(%d,%d)",$post_id,$user_id));else$wpdb->delete($likes,['post_id'=>$post_id,'user_id'=>$user_id],['%d','%d']);}
elseif($action==='save'||$action==='unsave'){if($action==='save')$wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$saves}(post_id,user_id) VALUES(%d,%d)",$post_id,$user_id));else$wpdb->delete($saves,['post_id'=>$post_id,'user_id'=>$user_id],['%d','%d']);}
elseif($action==='share')$wpdb->insert($shares,['post_id'=>$post_id,'user_id'=>$user_id],['%d','%d']);
elseif($action==='comment'){ $body=trim((string)($payload['body']??'')); if($body===''){http_response_code(400);echo json_encode(['success'=>false,'message'=>'Comment cannot be empty']);exit;} $wpdb->insert($comments,['post_id'=>$post_id,'user_id'=>$user_id,'body'=>sanitize_textarea_field($body)],['%d','%d','%s']);}
elseif($action!=='state'){http_response_code(400);echo json_encode(['success'=>false,'message'=>'Invalid action']);exit;}
$rows=$wpdb->get_results($wpdb->prepare("SELECT c.id,c.body,c.created_at,c.user_id,u.display_name FROM {$comments} c LEFT JOIN {$wpdb->users} u ON u.ID=c.user_id WHERE c.post_id=%d ORDER BY c.created_at ASC",$post_id),ARRAY_A);
echo json_encode(['success'=>true,'is_liked'=>(bool)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$likes} WHERE post_id=%d AND user_id=%d",$post_id,$user_id)),'is_saved'=>(bool)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$saves} WHERE post_id=%d AND user_id=%d",$post_id,$user_id)),'likes_count'=>(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$likes} WHERE post_id=%d",$post_id)),'comments_count'=>count($rows),'shares_count'=>(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$shares} WHERE post_id=%d",$post_id)),'comments'=>$rows]);
