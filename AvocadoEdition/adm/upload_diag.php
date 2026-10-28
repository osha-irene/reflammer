<?php
// 임시 진단 페이지 — 이미지 업로드가 "미등록"으로 조용히 실패하는 원인 확인용.
// 확인 끝나면 삭제해도 됩니다 (adm/upload_diag.php 하나만 지우면 됩니다).
$sub_menu = "110300";
include_once('./_common.php');

auth_check($auth[$sub_menu], 'r');
if (!$is_admin) alert('관리자만 접근 가능합니다.');

$site_style_path = G5_DATA_PATH."/site";

function yn($v) { return $v ? 'On' : 'Off'; }
?>
<!DOCTYPE html>
<meta charset="utf-8">
<title>업로드 진단</title>
<pre style="font-size:14px;line-height:1.6;padding:16px;">
file_uploads        : <?php echo yn(ini_get('file_uploads')); ?>
upload_max_filesize  : <?php echo ini_get('upload_max_filesize'); ?>
post_max_size        : <?php echo ini_get('post_max_size'); ?>
memory_limit         : <?php echo ini_get('memory_limit'); ?>
upload_tmp_dir(설정) : <?php echo ini_get('upload_tmp_dir') ?: '(기본 시스템 임시 디렉토리)'; ?>
sys_get_temp_dir     : <?php echo sys_get_temp_dir(); ?>
해당 디렉토리 쓰기 가능 : <?php echo yn(is_writable(ini_get('upload_tmp_dir') ?: sys_get_temp_dir())); ?>

G5_DATA_PATH/site    : <?php echo $site_style_path; ?>
존재 여부            : <?php echo yn(is_dir($site_style_path)); ?>
쓰기 가능 여부        : <?php echo yn(is_dir($site_style_path) && is_writable($site_style_path)); ?>

------------------------------------------------
아래 "테스트 파일 선택" 후 업로드를 누르면, 서버가 실제로
받은 $_FILES 내용을 그대로 보여줍니다.
------------------------------------------------
<?php if ($_SERVER['REQUEST_METHOD'] === 'POST') { ?>

[업로드 결과]
<?php echo htmlspecialchars(print_r($_FILES, true), ENT_QUOTES, 'UTF-8'); ?>
<?php
if (!empty($_FILES['test_file']['tmp_name']) && is_uploaded_file($_FILES['test_file']['tmp_name'])) {
    echo "move_uploaded_file 테스트 : ";
    $dest = $site_style_path . '/upload_diag_test.tmp';
    $ok = @move_uploaded_file($_FILES['test_file']['tmp_name'], $dest);
    echo yn($ok) . ($ok ? " (성공 — {$dest} 생성됨, 확인 후 지워주세요)" : " (실패)");
    echo "\n";
}
?>
<?php } ?>
</pre>
<form method="post" enctype="multipart/form-data" style="padding:0 16px;">
    <input type="file" name="test_file">
    <button type="submit">테스트 업로드</button>
</form>
