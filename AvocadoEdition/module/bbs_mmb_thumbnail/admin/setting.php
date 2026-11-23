<?php
if (!defined('_GNUBOARD_')) exit;

$sub_menu = function_exists('mdl_admin_menu_code')
    ? mdl_admin_menu_code('bbs_mmb_thumbnail', '000')
    : '';
if ($sub_menu !== '' && isset($auth[$sub_menu])) {
    auth_check($auth[$sub_menu], 'r');
}

$message = '';
$is_error = false;
$profile_code = trim((string)mdl_setting_get('bbs_mmb_thumbnail', 'profile_ar_code', ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($sub_menu !== '' && isset($auth[$sub_menu])) {
        auth_check($auth[$sub_menu], 'w');
    }
    check_token();

    $submitted_code = trim((string)(isset($_POST['profile_ar_code']) ? $_POST['profile_ar_code'] : ''));
    $profile_code = $submitted_code;
    if ($submitted_code !== '' && !preg_match('/^[A-Za-z_]{1,64}$/', $submitted_code)) {
        $message = '고유코드는 영문 대소문자와 밑줄(_)만 입력할 수 있습니다.';
        $is_error = true;
    } elseif ($submitted_code !== '' && empty(bbs_mmb_thumbnail_profile_field($submitted_code))) {
        $message = '해당 고유코드의 프로필 항목이 없거나 항목타입이 외부이미지 링크가 아닙니다.';
        $is_error = true;
    } elseif (!mdl_setting_set('bbs_mmb_thumbnail', 'profile_ar_code', $submitted_code)) {
        $message = '설정을 저장하지 못했습니다. 데이터베이스 상태를 확인해 주세요.';
        $is_error = true;
    } else {
        $profile_code = $submitted_code;
        $message = '설정을 저장했습니다.';
    }
}

$token = get_token();
$profile_field_valid = ($profile_code === '' || !empty(bbs_mmb_thumbnail_profile_field($profile_code)));
?>

<?php if ($message !== ''): ?>
<div class="<?php echo $is_error ? 'local_desc02' : 'local_desc01'; ?> local_desc">
    <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
</div>
<?php endif; ?>

<?php if ($profile_code !== '' && !$profile_field_valid): ?>
<div class="local_desc02 local_desc">
    설정된 항목이 없거나 외부이미지 링크 타입이 아닙니다. 새 글에는 소속 아이콘을 사용합니다.
</div>
<?php endif; ?>

<form method="post" action="" autocomplete="off">
    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
    <table class="tbl_frm01 tbl_wrap">
        <tbody>
            <tr>
                <th scope="row"><label for="profile_ar_code">프로필 썸네일 고유코드</label></th>
                <td>
                    <input type="text" name="profile_ar_code" id="profile_ar_code"
                           value="<?php echo htmlspecialchars($profile_code, ENT_QUOTES, 'UTF-8'); ?>"
                           class="frm_input" maxlength="64">
                    <p class="frm_info">비워 두면 소속 아이콘을 기본 썸네일로 사용합니다. 입력한 항목은 프로필 항목 관리에서 외부이미지 링크 타입으로 등록해야 합니다.</p>
                    <p class="frm_info">오너+관리자 공개 항목도 사용할 수 있지만, 선택한 이미지 자체는 해당 MMB 게시글을 볼 수 있는 사람에게 표시됩니다.</p>
                </td>
            </tr>
        </tbody>
    </table>
    <div class="btn_confirm01 btn_confirm">
        <button type="submit" class="btn_submit">저장</button>
    </div>
</form>
