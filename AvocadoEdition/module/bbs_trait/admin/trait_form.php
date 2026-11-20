<?php
if (!defined('_GNUBOARD_')) exit;

$sub_menu = function_exists('mdl_admin_menu_code') ? mdl_admin_menu_code('bbs_trait', '200') : '001200';
global $auth;
if (isset($auth[$sub_menu])) auth_check($auth[$sub_menu], $_SERVER['REQUEST_METHOD'] === 'POST' ? 'w' : 'r');
add_stylesheet('<link rel="stylesheet" href="'.G5_MDL_URL.'/bbs_trait/css/admin.css?v=1.4.0">', 100);

$tables = bbs_trait_tables();
$trait_id = isset($_GET['trait_id']) && is_scalar($_GET['trait_id']) ? (int)$_GET['trait_id'] : (isset($_POST['trait_id']) && is_scalar($_POST['trait_id']) ? (int)$_POST['trait_id'] : 0);
$trait = bbs_trait_get($trait_id, true);
if (!$trait) alert('특성을 찾을 수 없습니다.', G5_MDL_ADMIN_URL.'/module.php?m=bbs_trait&p=trait_list');
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_demo();
    check_token();
    $name = trim(isset($_POST['trait_name']) && is_scalar($_POST['trait_name']) ? (string)$_POST['trait_name'] : '');
    $description = trim(isset($_POST['trait_description']) && is_scalar($_POST['trait_description']) ? (string)$_POST['trait_description'] : '');
    $effect = isset($_POST['effect_type']) && is_scalar($_POST['effect_type']) ? (string)$_POST['effect_type'] : '';
    $effect_stat_raw = !isset($_POST['effect_stat_id']) ? '0' : (is_scalar($_POST['effect_stat_id']) ? (string)$_POST['effect_stat_id'] : '');
    $value_raw = isset($_POST['effect_value']) && is_scalar($_POST['effect_value']) ? (string)$_POST['effect_value'] : '';
    $limit_unlimited = !empty($_POST['unlimited']);
    $limit_raw = isset($_POST['usage_limit']) && is_scalar($_POST['usage_limit']) ? (string)$_POST['usage_limit'] : '';
    $icon_url = isset($_POST['trait_icon_url']) && is_scalar($_POST['trait_icon_url']) ? trim((string)$_POST['trait_icon_url']) : '';
    $comment_enabled_raw = !isset($_POST['comment_enabled'])
        ? '0'
        : (is_scalar($_POST['comment_enabled']) ? (string)$_POST['comment_enabled'] : 'invalid');
    $comment_enabled = in_array($comment_enabled_raw, array('0', '1'), true) ? (int)$comment_enabled_raw : -1;
    $length = bbs_trait_text_length($name);
    $description_length = bbs_trait_text_length($description);
    $effects = bbs_trait_effects();
    $status_definitions = bbs_trait_status_definitions();
    $effect_stat_id = preg_match('/^(0|[1-9][0-9]{0,9})$/', $effect_stat_raw) ? (int)$effect_stat_raw : -1;
    $scope_valid = $effect_stat_id >= 0
        && ($effect_stat_id === 0 || (bbs_trait_effect_supports_stat_scope($effect) && isset($status_definitions[$effect_stat_id])));
    $value_valid = $effect === 'reroll_lowest'
        ? ($value_raw === '' || $value_raw === '0')
        : (preg_match('/^-?[0-9]{1,3}$/', $value_raw) && (int)$value_raw >= -100 && (int)$value_raw <= 100);
    $limit_valid = $limit_unlimited || (preg_match('/^(0|[1-9][0-9]{0,2})$/', $limit_raw) && (int)$limit_raw <= 100);
    $upload_error = '';
    $icon_file = isset($_FILES['trait_icon']) ? $_FILES['trait_icon'] : array();
    $has_icon_upload = is_array($icon_file) && isset($icon_file['error']) && (int)$icon_file['error'] !== UPLOAD_ERR_NO_FILE;
    if (!$scope_valid) {
        $new_icon = false;
        $upload_error = '적용 스탯을 확인해 주세요. 받는 피해 효과는 스탯 제한을 지정할 수 없습니다.';
    } elseif ($icon_url !== '' && !bbs_trait_valid_external_icon_url($icon_url)) {
        $new_icon = false;
        $upload_error = '아이콘 외부 URL은 유효한 HTTPS 주소여야 합니다.';
    } elseif ($icon_url !== '' && $has_icon_upload) {
        $new_icon = false;
        $upload_error = '외부 URL과 파일 업로드는 동시에 지정할 수 없습니다.';
    } else {
        $new_icon = $icon_url !== '' ? $icon_url : bbs_trait_upload_icon($icon_file, $upload_error);
    }
    if ($name === '' || $length > 100 || $description_length > 5000 || !isset($effects[$effect]) || !$value_valid || !$limit_valid || !$scope_valid || $new_icon === false || $comment_enabled < 0) {
        $message = $new_icon === false ? $upload_error : '이름, 설명, 효과, 보정값 또는 사용 제한 입력을 확인해 주세요.';
    } else {
        $icon = $new_icon === '' ? (string)$trait['trait_icon'] : $new_icon;
        $limit_sql = $limit_unlimited ? 'NULL' : "'".(int)$limit_raw."'";
        $value = $effect === 'reroll_lowest' ? 0 : (int)$value_raw;
        if (!function_exists('mdl_db_column_exists')
            || !mdl_db_column_exists($tables['catalog'], 'effect_stat_id')
            || !mdl_db_column_exists($tables['catalog'], 'comment_enabled')) {
            $message = '스탯 또는 리플 사용 설정을 저장할 DB 항목이 없습니다. 특성 모듈을 업데이트해 데이터베이스 마이그레이션을 실행해 주세요.';
        } else {
            $updated = sql_query("UPDATE `{$tables['catalog']}` SET
                trait_name='".bbs_trait_escape($name)."',
                trait_icon='".bbs_trait_escape($icon)."',
                trait_description='".bbs_trait_escape($description)."',
                effect_type='".bbs_trait_escape($effect)."',
                effect_value='{$value}',
                effect_stat_id='{$effect_stat_id}',
                comment_enabled='{$comment_enabled}',
                usage_limit={$limit_sql},
                trait_updated_at='".G5_TIME_YMDHIS."'
                WHERE trait_id='{$trait_id}' LIMIT 1", false);
            if ($updated) {
                $trait = bbs_trait_get($trait_id, true);
                $message = '특성 정보를 저장했습니다. 기간당 사용 제한 변경은 즉시 적용됩니다.';
            } else {
                error_log('bbs_trait: failed to update trait '.$trait_id.' in the catalog table.');
                $message = '특성 정보를 저장하지 못했습니다. 데이터베이스 오류 로그를 확인해 주세요.';
            }
        }
    }
}
$unlimited = $trait['usage_limit'] === null;
$status_definitions = bbs_trait_status_definitions();
$effect_stat_id = isset($trait['effect_stat_id']) ? (int)$trait['effect_stat_id'] : 0;
$scope_is_valid = $effect_stat_id < 1 || isset($status_definitions[$effect_stat_id]);
?>
<p><a class="btn btn_03" href="<?php echo G5_MDL_ADMIN_URL; ?>/module.php?m=bbs_trait&amp;p=trait_list">특성 목록으로</a></p>
<?php if ($message !== '') { ?><div class="local_desc01 local_desc"><p><?php echo bbs_trait_html($message); ?></p></div><?php } ?>
<form method="post" enctype="multipart/form-data" class="bbs-trait-admin-form">
    <input type="hidden" name="token" value="<?php echo bbs_trait_html(get_token()); ?>">
    <input type="hidden" name="trait_id" value="<?php echo (int)$trait_id; ?>">
    <div><label for="trait_name">특성명</label><input class="frm_input" type="text" name="trait_name" id="trait_name" maxlength="100" value="<?php echo bbs_trait_html($trait['trait_name']); ?>" required></div>
    <div><label>현재 아이콘</label><?php $icon_url = bbs_trait_icon_url((string)$trait['trait_icon']); if ($icon_url !== '') { ?><img class="bbs-trait-admin-icon" src="<?php echo bbs_trait_html($icon_url); ?>" alt=""><?php } else { ?>없음<?php } ?></div>
    <div><label for="trait_icon">새 아이콘 파일 (JPG/PNG/GIF/WEBP, 2MB 이하)</label><input type="file" name="trait_icon" id="trait_icon" accept="image/jpeg,image/png,image/gif,image/webp"></div>
    <div><label for="trait_icon_url">또는 외부 이미지 HTTPS URL (파일과 동시 사용 불가)</label><input class="frm_input" type="url" name="trait_icon_url" id="trait_icon_url" maxlength="2048" value="<?php echo bbs_trait_html(bbs_trait_valid_external_icon_url((string)$trait['trait_icon']) ? $trait['trait_icon'] : ''); ?>" placeholder="https://example.com/image.png"></div>
    <div><label for="trait_description">설명</label><textarea name="trait_description" id="trait_description" rows="5" maxlength="5000"><?php echo bbs_trait_html($trait['trait_description']); ?></textarea></div>
    <div><label for="comment_enabled">리플 사용 가능</label><label><input type="checkbox" name="comment_enabled" id="comment_enabled" value="1" <?php echo !empty($trait['comment_enabled']) ? 'checked' : ''; ?>> 댓글에서 특성 발현 허용</label></div>
    <div><label for="effect_type">효과</label><select class="frm_input" name="effect_type" id="effect_type" required>
        <?php foreach (bbs_trait_effects() as $key => $label) { ?><option value="<?php echo bbs_trait_html($key); ?>" data-stat-scope="<?php echo bbs_trait_effect_supports_stat_scope($key) ? '1' : '0'; ?>" <?php echo $trait['effect_type'] === $key ? 'selected' : ''; ?>><?php echo bbs_trait_html($label); ?></option><?php } ?>
    </select></div>
    <div id="effect_stat_field" <?php echo bbs_trait_effect_supports_stat_scope($trait['effect_type']) ? '' : 'hidden'; ?>>
        <label for="effect_stat_id">적용 스탯</label>
        <select class="frm_input" name="effect_stat_id" id="effect_stat_id">
            <option value="0" <?php echo $effect_stat_id === 0 ? 'selected' : ''; ?>>스탯 제한 없음</option>
            <?php if (!$scope_is_valid) { ?><option value="<?php echo $effect_stat_id; ?>" selected>삭제된 스탯 (ID <?php echo $effect_stat_id; ?>) — 다시 지정해 주세요</option><?php } ?>
            <?php foreach ($status_definitions as $status_id => $definition) { ?>
                <option value="<?php echo (int)$status_id; ?>" <?php echo $effect_stat_id === (int)$status_id ? 'selected' : ''; ?>><?php echo bbs_trait_html($definition['name']); ?> 판정</option>
            <?php } ?>
        </select>
        <small>선택한 스탯이 포함된 2스탯 조합 판정에도 적용됩니다. 받는 피해는 판정 스탯과 무관합니다.</small>
    </div>
    <div><label for="effect_value">보정값 (-100~+100)</label><input class="frm_input" type="number" name="effect_value" id="effect_value" min="-100" max="100" value="<?php echo (int)$trait['effect_value']; ?>"></div>
    <div><label for="usage_limit">기간당 사용 횟수</label><input class="frm_input" type="number" name="usage_limit" id="usage_limit" min="0" max="100" value="<?php echo $unlimited ? 1 : (int)$trait['usage_limit']; ?>"> <label><input type="checkbox" name="unlimited" value="1" <?php echo $unlimited ? 'checked' : ''; ?>> 무제한</label></div>
    <button type="submit" class="btn_submit">저장</button>
</form>
<script>(function(){var effect=document.getElementById('effect_type');var value=document.getElementById('effect_value');var scope=document.getElementById('effect_stat_id');var scopeField=document.getElementById('effect_stat_field');var unlimited=document.querySelector('input[name="unlimited"]');var limit=document.getElementById('usage_limit');function sync(){var option=effect&&effect.options[effect.selectedIndex];var supportsScope=option&&option.getAttribute('data-stat-scope')==='1';if(effect&&value){var reroll=effect.value==='reroll_lowest';value.disabled=reroll;value.required=!reroll;}if(scope&&scopeField){scopeField.hidden=!supportsScope;scope.disabled=!supportsScope;if(!supportsScope)scope.value='0';}if(unlimited&&limit)limit.disabled=unlimited.checked;}if(effect)effect.addEventListener('change',sync);if(unlimited)unlimited.addEventListener('change',sync);sync();})();</script>
<script>(function(){var file=document.getElementById('trait_icon');var url=document.getElementById('trait_icon_url');if(!file||!url)return;file.addEventListener('change',function(){if(file.value)url.value='';});url.addEventListener('input',function(){if(url.value)file.value='';});})();</script>
