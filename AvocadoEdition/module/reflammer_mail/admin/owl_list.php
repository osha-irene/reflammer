<?php
if (!defined('_GNUBOARD_')) exit;

$sub_menu = function_exists('mdl_admin_menu_code') ? mdl_admin_menu_code('reflammer_mail', '100') : '001100';
global $auth, $g5, $member;
if (isset($auth[$sub_menu])) auth_check($auth[$sub_menu], $_SERVER['REQUEST_METHOD'] === 'POST' ? 'w' : 'r');

require_once G5_MDL_PATH.'/reflammer_mail/lib.php';
$table = reflammer_mail_table('owl');
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_demo();
    check_token();
    $mode = isset($_POST['mode']) ? (string)$_POST['mode'] : '';
    if ($mode === 'save') {
        $mo_id = (int)($_POST['mo_id'] ?? 0);
        $name = trim(strip_tags(reflammer_mail_input($_POST['mo_name'] ?? '')));
        $desc = trim(strip_tags(reflammer_mail_input($_POST['mo_desc'] ?? '')));
        $hours = max(0, (int)($_POST['mo_hours'] ?? 0));
        $cost = max(0, (int)($_POST['mo_cost'] ?? 0));
        $order = (int)($_POST['mo_order'] ?? 0);
        $active = !empty($_POST['mo_active']) ? 1 : 0;
        $image_url = trim(reflammer_mail_input($_POST['mo_image_url'] ?? ''));
        if ($image_url !== '') {
            $image_parts = parse_url($image_url);
            if (strlen($image_url) > 1000 || !filter_var($image_url, FILTER_VALIDATE_URL) || !isset($image_parts['scheme']) || !in_array(strtolower($image_parts['scheme']), array('http', 'https'), true)) {
                $message = '이미지 주소는 http 또는 https로 시작하는 유효한 URL이어야 합니다.';
            }
        }
        $image_file = '';
        if ($mo_id > 0) {
            $current_owl = sql_fetch("SELECT mo_image_file FROM `{$table}` WHERE mo_id='{$mo_id}' LIMIT 1", false);
            $image_file = isset($current_owl['mo_image_file']) ? $current_owl['mo_image_file'] : '';
        }
        if ($message === '' && $name !== '' && isset($_FILES['mo_image_upload']) && !empty($_FILES['mo_image_upload']['name'])) {
            $upload = $_FILES['mo_image_upload'];
            $ext = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
            $allowed_ext = array('jpg', 'jpeg', 'png', 'gif', 'webp');
            $mime = '';
            if (function_exists('finfo_open') && $upload['error'] === UPLOAD_ERR_OK) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo) { $mime = finfo_file($finfo, $upload['tmp_name']); finfo_close($finfo); }
            }
            $allowed_mime = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
            if ($upload['error'] !== UPLOAD_ERR_OK || (int)$upload['size'] > 5 * 1024 * 1024 || !in_array($ext, $allowed_ext, true) || ($mime !== '' && !in_array($mime, $allowed_mime, true))) {
                $message = '부엉이 이미지는 5MB 이하의 JPG, PNG, GIF, WEBP 파일만 업로드할 수 있습니다.';
            } else {
                $upload_dir = G5_DATA_PATH.'/module/reflammer_mail/owl';
                if (!is_dir($upload_dir) && !mkdir($upload_dir, G5_DIR_PERMISSION, true)) {
                    $message = '부엉이 이미지 저장 폴더를 만들지 못했습니다.';
                } else {
                    $filename = 'owl_'.md5(uniqid((string)mt_rand(), true)).'.'.$ext;
                    if (function_exists('upload_file') && upload_file($upload['tmp_name'], $filename, $upload_dir)) {
                        $image_file = G5_DATA_URL.'/module/reflammer_mail/owl/'.$filename;
                    } else {
                        $message = '부엉이 이미지 업로드에 실패했습니다.';
                    }
                }
            }
        }

        if ($message !== '') {
            // 입력 오류가 있으면 데이터베이스를 변경하지 않는다.
        } elseif ($name === '') {
            $message = '부엉이 이름을 입력하세요.';
        } else {
            $name_sql = sql_real_escape_string($name);
            $desc_sql = sql_real_escape_string($desc);
            $image_url_sql = sql_real_escape_string($image_url);
            $image_file_sql = sql_real_escape_string($image_file);
            if ($mo_id > 0) {
                sql_query("UPDATE `{$table}` SET mo_name='{$name_sql}', mo_desc='{$desc_sql}', mo_image_url='{$image_url_sql}', mo_image_file='{$image_file_sql}', mo_hours={$hours}, mo_cost={$cost}, mo_order={$order}, mo_active={$active} WHERE mo_id='{$mo_id}'", false);
                $message = '부엉이 종류를 수정했습니다.';
            } else {
                sql_query("INSERT INTO `{$table}` (mo_name, mo_desc, mo_image_url, mo_image_file, mo_hours, mo_cost, mo_order, mo_active) VALUES ('{$name_sql}','{$desc_sql}','{$image_url_sql}','{$image_file_sql}',{$hours},{$cost},{$order},{$active})", false);
                $message = '부엉이 종류를 추가했습니다.';
            }
        }
    } elseif ($mode === 'delete') {
        $mo_id = (int)($_POST['mo_id'] ?? 0);
        if ($mo_id > 0) {
            sql_query("DELETE FROM `{$table}` WHERE mo_id='{$mo_id}' LIMIT 1", false);
            $message = '부엉이 종류를 삭제했습니다. 이미 발송된 편지의 기록은 유지됩니다.';
        }
    }
}

$edit_id = (int)($_GET['edit'] ?? 0);
$edit = $edit_id ? sql_fetch("SELECT * FROM `{$table}` WHERE mo_id='{$edit_id}'", false) : array();

$owls = array();
$rs = sql_query("SELECT * FROM `{$table}` ORDER BY mo_order ASC, mo_id ASC", false);
while ($rs && $row = sql_fetch_array($rs)) $owls[] = $row;
?>
<div class="local_desc01 local_desc">
    <p>편지 작성 화면에서 선택할 수 있는 부엉이 종류를 관리합니다. 배달 시간(시간 단위)과 발송 비용(포인트)을 자유롭게 조정하세요. 비활성화하면 새 편지 작성 시 선택지에서만 사라지고, 이미 발송된 편지에는 영향이 없습니다.</p>
</div>
<?php if ($message !== '') { ?><div class="local_desc01 local_desc"><p><?php echo reflammer_mail_e($message); ?></p></div><?php } ?>
<form method="post" class="rm-admin-form" enctype="multipart/form-data">
    <input type="hidden" name="token" value="<?php echo reflammer_mail_e(get_token()); ?>">
    <input type="hidden" name="mode" value="save">
    <input type="hidden" name="mo_id" value="<?php echo (int)(isset($edit['mo_id']) ? $edit['mo_id'] : 0); ?>">
    <h2><?php echo $edit ? '부엉이 수정' : '부엉이 추가'; ?></h2>
    <p><label>이름 <input class="frm_input" type="text" name="mo_name" maxlength="60" required value="<?php echo reflammer_mail_e(isset($edit['mo_name']) ? $edit['mo_name'] : ''); ?>"></label></p>
    <p><label>설명 <input class="frm_input" type="text" name="mo_desc" maxlength="255" value="<?php echo reflammer_mail_e(isset($edit['mo_desc']) ? $edit['mo_desc'] : ''); ?>"></label></p>
    <p><label>외부 이미지 URL <input class="frm_input" type="url" name="mo_image_url" maxlength="1000" value="<?php echo reflammer_mail_e(isset($edit['mo_image_url']) ? $edit['mo_image_url'] : ''); ?>" placeholder="https://..."></label><small>외부 URL이 있으면 업로드 이미지보다 우선 표시됩니다.</small></p>
    <p><label>내부 이미지 업로드 <input type="file" name="mo_image_upload" accept="image/jpeg,image/png,image/gif,image/webp"></label><small>5MB 이하의 JPG, PNG, GIF, WEBP. 업로드 이미지는 편지 기록 보존을 위해 교체 후에도 파일을 유지합니다.</small></p>
    <?php $edit_image = !empty($edit) ? reflammer_mail_owl_image($edit) : ''; if ($edit_image !== '') { ?><p><img src="<?php echo reflammer_mail_e($edit_image); ?>" width="56" height="56" alt="부엉이 이미지 미리보기"></p><?php } ?>
    <p><label>배달 시간(시간) <input class="frm_input" type="number" name="mo_hours" min="0" value="<?php echo (int)(isset($edit['mo_hours']) ? $edit['mo_hours'] : 0); ?>"></label></p>
    <p><label>비용 <?php echo cf_money_input('mo_cost', isset($edit['mo_cost']) ? (int)$edit['mo_cost'] : 0, array('id' => 'mo_cost')); ?></label></p>
    <p><label>정렬 순서 <input class="frm_input" type="number" name="mo_order" value="<?php echo (int)(isset($edit['mo_order']) ? $edit['mo_order'] : 0); ?>"></label></p>
    <p><label><input type="checkbox" name="mo_active" value="1" <?php echo (empty($edit) || !empty($edit['mo_active'])) ? 'checked' : ''; ?>> 사용(작성 화면에 노출)</label></p>
    <button type="submit" class="btn_submit"><?php echo $edit ? '수정' : '추가'; ?></button>
    <?php if ($edit) echo '<a class="btn btn_02" href="'.reflammer_mail_e(G5_MDL_ADMIN_URL.'/module.php?m=reflammer_mail&p=owl_list').'">새로 추가</a>'; ?>
</form>
<div class="tbl_head01 tbl_wrap">
    <table>
        <caption>부엉이 종류 목록</caption>
        <thead><tr><th>이미지</th><th>이름</th><th>설명</th><th>배달 시간</th><th>비용</th><th>순서</th><th>상태</th><th>관리</th></tr></thead>
        <tbody>
        <?php $n = 0; foreach ($owls as $owl) { $n++; ?>
            <tr>
                <td><?php $owl_image = reflammer_mail_owl_image($owl); if ($owl_image !== '') { ?><img src="<?php echo reflammer_mail_e($owl_image); ?>" width="56" height="56" alt=""><?php } ?></td>
                <td><?php echo reflammer_mail_e($owl['mo_name']); ?></td>
                <td><?php echo reflammer_mail_e($owl['mo_desc']); ?></td>
                <td><?php echo (int)$owl['mo_hours']; ?>시간</td>
                <td><?php echo cf_money_format((int)$owl['mo_cost']); ?></td>
                <td><?php echo (int)$owl['mo_order']; ?></td>
                <td><?php echo $owl['mo_active'] ? '사용' : '비활성'; ?></td>
                <td>
                    <a class="btn btn_02" href="<?php echo reflammer_mail_e(G5_MDL_ADMIN_URL.'/module.php?m=reflammer_mail&p=owl_list&edit='.$owl['mo_id']); ?>">수정</a>
                    <form method="post" style="display:inline" onsubmit="return confirm('삭제할까요? 이미 보낸 편지 기록은 유지됩니다.');">
                        <input type="hidden" name="token" value="<?php echo reflammer_mail_e(get_token()); ?>">
                        <input type="hidden" name="mode" value="delete">
                        <input type="hidden" name="mo_id" value="<?php echo (int)$owl['mo_id']; ?>">
                        <button type="submit" class="btn btn_02">삭제</button>
                    </form>
                </td>
            </tr>
        <?php } if (!$n) { ?><tr><td colspan="8" class="empty_table">등록된 부엉이가 없습니다.</td></tr><?php } ?>
        </tbody>
    </table>
</div>
