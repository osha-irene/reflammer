<?php
define('G5_IS_ADMIN', true);
include_once ('../common.php');

// POST 본문이 서버의 post_max_size 한도를 넘으면 PHP가 에러 없이 $_POST/$_FILES를
// 통째로 비워버림 — 디자인 설정처럼 이미지 여러 장을 한 폼에서 올릴 때 걸리기 쉽고,
// 그대로 두면 "저장은 됐는데 이미지가 미등록"처럼 원인을 알 수 없게 조용히 실패한다.
// 여기서 바로 잡아 실제 원인을 알려준다.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES)
    && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    die('업로드한 파일 용량이 서버의 post_max_size 한도를 초과하여 저장되지 않았습니다. 이미지 크기를 줄이거나, 서버 관리자에게 post_max_size/upload_max_filesize 설정 확인을 요청해 주세요.');
}

if (!defined('G5_ACTIVE_ADMIN_URL'))  define('G5_ACTIVE_ADMIN_URL',  G5_ADMIN_URL);
if (!defined('G5_ACTIVE_ADMIN_PATH')) define('G5_ACTIVE_ADMIN_PATH', G5_ADMIN_PATH);

if(!defined("_LOGIN_PAGE_") || (defined("_LOGIN_PAGE_") && !_LOGIN_PAGE_)) {
	include_once(G5_ADMIN_PATH.'/admin.lib.php');
}
$_GET['sfl']=isset($_GET['sfl'])?$_GET['sfl']:'';
if (function_exists('mdl_do_action')) mdl_do_action('admin_common');
?>