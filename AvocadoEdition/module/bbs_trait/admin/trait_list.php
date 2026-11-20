<?php
if (!defined('_GNUBOARD_')) exit;

$sub_menu = function_exists('mdl_admin_menu_code') ? mdl_admin_menu_code('bbs_trait', '200') : '001200';
global $auth;
if (isset($auth[$sub_menu])) auth_check($auth[$sub_menu], $_SERVER['REQUEST_METHOD'] === 'POST' ? 'w' : 'r');
add_stylesheet('<link rel="stylesheet" href="'.G5_MDL_URL.'/bbs_trait/css/admin.css?v=1.4.0">', 100);

$tables = bbs_trait_tables();
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_demo();
    check_token();
    $mode = isset($_POST['mode']) && is_scalar($_POST['mode']) ? (string)$_POST['mode'] : '';
    if ($mode === 'toggle') {
        $trait_id = isset($_POST['trait_id']) && is_scalar($_POST['trait_id']) ? (int)$_POST['trait_id'] : 0;
        $enabled_raw = isset($_POST['enabled']) && is_scalar($_POST['enabled']) ? (string)$_POST['enabled'] : '';
        $enabled = preg_match('/^[01]$/', $enabled_raw) ? (int)$enabled_raw : -1;
        $trait = bbs_trait_get($trait_id, true);
        if (!$trait || !in_array($enabled, array(0, 1), true)) {
            $message = '특성 상태를 변경할 수 없습니다.';
        } else {
            $saved = sql_query("UPDATE `{$tables['catalog']}` SET trait_enabled='{$enabled}', trait_updated_at='".G5_TIME_YMDHIS."' WHERE trait_id='{$trait_id}' LIMIT 1", false);
            $message = $saved
                ? ($enabled ? '특성을 활성화했습니다.' : '특성을 비활성화했습니다. 보유 현황과 과거 기록은 보존됩니다.')
                : '특성 상태를 저장하지 못했습니다.';
        }
    } elseif ($mode === 'bulk_toggle') {
        $enabled_raw = isset($_POST['enabled']) && is_scalar($_POST['enabled']) ? (string)$_POST['enabled'] : '';
        $enabled = preg_match('/^[01]$/', $enabled_raw) ? (int)$enabled_raw : -1;
        $selected_raw = isset($_POST['selected_ids']) && is_array($_POST['selected_ids']) ? $_POST['selected_ids'] : array();
        $ids = array();
        foreach ($selected_raw as $id_raw) {
            if (is_scalar($id_raw) && preg_match('/^[1-9][0-9]*$/', (string)$id_raw)) $ids[] = (int)$id_raw;
        }
        $ids = array_values(array_unique($ids));
        if (!in_array($enabled, array(0, 1), true) || !$ids) {
            $message = '상태를 변경할 특성을 체크해 주세요.';
        } else {
            $updated = 0;
            foreach ($ids as $id) {
                if (!bbs_trait_get($id, true)) continue;
                if (sql_query("UPDATE `{$tables['catalog']}` SET trait_enabled='{$enabled}', trait_updated_at='".G5_TIME_YMDHIS."' WHERE trait_id='{$id}' LIMIT 1", false)) $updated++;
            }
            $message = $updated
                ? $updated.'개 특성 상태를 변경했습니다. 보유 현황과 과거 기록은 보존됩니다.'
                : '특성 상태를 변경하지 못했습니다.';
        }
    } elseif ($mode === 'add') {
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
            $icon = false;
            $upload_error = '적용 스탯을 확인해 주세요. 받는 피해 효과는 스탯 제한을 지정할 수 없습니다.';
        } elseif ($icon_url !== '' && !bbs_trait_valid_external_icon_url($icon_url)) {
            $icon = false;
            $upload_error = '아이콘 외부 URL은 유효한 HTTPS 주소여야 합니다.';
        } elseif ($icon_url !== '' && $has_icon_upload) {
            $icon = false;
            $upload_error = '외부 URL과 파일 업로드는 동시에 지정할 수 없습니다.';
        } else {
            $icon = $icon_url !== '' ? $icon_url : bbs_trait_upload_icon($icon_file, $upload_error);
        }
        if ($name === '' || $length > 100 || $description_length > 5000 || !isset($effects[$effect]) || !$value_valid || !$limit_valid || !$scope_valid || $icon === false || $comment_enabled < 0) {
            $message = $icon === false ? $upload_error : '이름, 설명, 효과, 보정값 또는 사용 제한 입력을 확인해 주세요.';
        } elseif (!function_exists('mdl_db_column_exists') || !mdl_db_column_exists($tables['catalog'], 'comment_enabled')) {
            $message = '리플 사용 설정을 저장할 DB 항목이 없습니다. 특성 모듈을 최신 버전으로 업그레이드해 데이터베이스 마이그레이션을 실행해 주세요.';
        } else {
            $limit_sql = $limit_unlimited ? 'NULL' : "'".(int)$limit_raw."'";
            $value = $effect === 'reroll_lowest' ? 0 : (int)$value_raw;
            $inserted = sql_query("INSERT INTO `{$tables['catalog']}`
                SET trait_name='".bbs_trait_escape($name)."',
                    trait_icon='".bbs_trait_escape($icon === '' ? '' : $icon)."',
                    trait_description='".bbs_trait_escape($description)."',
                    effect_type='".bbs_trait_escape($effect)."',
                    effect_value='{$value}',
                    effect_stat_id='{$effect_stat_id}',
                    comment_enabled='{$comment_enabled}',
                    usage_limit={$limit_sql},
                    trait_enabled=1,
                    trait_created_at='".G5_TIME_YMDHIS."',
                    trait_updated_at='".G5_TIME_YMDHIS."'", false);
            $message = $inserted ? '특성을 추가했습니다.' : '특성을 추가하지 못했습니다.';
        }
    }
}
$result = sql_query("SELECT * FROM `{$tables['catalog']}` ORDER BY trait_enabled DESC, trait_name ASC, trait_id ASC", false);
$status_definitions = bbs_trait_status_definitions();
$effect_descriptions = array(
    'score_modifier' => '이번 판정의 주사위 합계에 보정값을 더합니다. 음수면 감점되며, 최종 점수는 0 미만이 되지 않습니다.',
    'dice_count_modifier' => '이번 판정에서 굴리는 D2 개수에 보정값을 더합니다. 음수면 주사위가 줄고, 최종 개수는 0~100개입니다.',
    'reroll_lowest' => '가장 낮은 주사위 하나를 한 번 재굴림합니다. 새 눈이 더 높을 때만 기존 눈을 교체합니다.',
    'incoming_damage_modifier' => '결투에서 패배해 본인이 받는 피해량에 보정값을 더합니다. 판정 스탯과 무관하며, 대련에는 적용되지 않습니다.',
    'outgoing_damage_modifier' => '결투에서 승리해 상대에게 주는 피해량에 보정값을 더합니다. 음수면 피해가 줄고, 대련에는 적용되지 않습니다.',
);
?>
<div class="local_desc01 local_desc"><p>특성마다 효과는 하나만 설정할 수 있습니다. 보정값은 -100~+100 정수이며, 재굴림 효과에는 수치가 필요하지 않습니다. 판정 효과는 적용 스탯을 `스탯 제한 없음` 또는 특정 스탯으로 지정할 수 있고, 받는 피해는 판정 스탯과 무관합니다. 제한을 무제한으로 두려면 체크박스를 선택하세요.</p></div>
<?php if ($message !== '') { ?><div class="local_desc01 local_desc"><p><?php echo bbs_trait_html($message); ?></p></div><?php } ?>
<form method="post" enctype="multipart/form-data" class="bbs-trait-admin-form bbs-trait-catalog-form">
    <input type="hidden" name="token" value="<?php echo bbs_trait_html(get_token()); ?>">
    <input type="hidden" name="mode" value="add">
    <h2>특성 추가</h2>
    <div class="bbs-trait-catalog-field">
        <label for="trait_name">특성명</label>
        <input class="frm_input" type="text" name="trait_name" id="trait_name" maxlength="100" required>
    </div>
    <div class="bbs-trait-catalog-field">
        <label for="trait_icon">아이콘 파일 (JPG/PNG/GIF/WEBP, 2MB 이하)</label>
        <input type="file" name="trait_icon" id="trait_icon" accept="image/jpeg,image/png,image/gif,image/webp">
    </div>
    <div class="bbs-trait-catalog-field">
        <label for="trait_icon_url">또는 외부 이미지 HTTPS URL</label>
        <input class="frm_input" type="url" name="trait_icon_url" id="trait_icon_url" maxlength="2048" placeholder="https://example.com/image.png">
    </div>
    <div class="bbs-trait-catalog-field bbs-trait-catalog-description">
        <label for="trait_description">설명</label>
        <textarea name="trait_description" id="trait_description" rows="1" maxlength="5000"></textarea>
    </div>
    <div class="bbs-trait-catalog-field bbs-trait-comment-field">
        <label for="comment_enabled">리플 사용 가능</label>
        <div><input type="checkbox" name="comment_enabled" id="comment_enabled" value="1"> 댓글에서 특성 발현 허용</div>
    </div>
    <div class="bbs-trait-catalog-effect-row">
        <div class="bbs-trait-catalog-field">
            <label for="effect_type">효과</label>
            <div class="bbs-trait-effect-controls">
                <select class="frm_input" name="effect_type" id="effect_type" required>
                    <?php foreach (bbs_trait_effects() as $key => $label) { ?>
                        <option value="<?php echo bbs_trait_html($key); ?>" data-stat-scope="<?php echo bbs_trait_effect_supports_stat_scope($key) ? '1' : '0'; ?>" data-effect-description="<?php echo bbs_trait_html($effect_descriptions[$key]); ?>"><?php echo bbs_trait_html($label); ?></option>
                    <?php } ?>
                </select>
                <button type="button" class="btn btn_02 bbs-trait-effect-help" id="trait_effect_help" data-tooltip="<?php echo bbs_trait_html($effect_descriptions['score_modifier']); ?>" title="<?php echo bbs_trait_html($effect_descriptions['score_modifier']); ?>" aria-label="효과 설명: <?php echo bbs_trait_html($effect_descriptions['score_modifier']); ?>">?</button>
            </div>
        </div>
        <div class="bbs-trait-catalog-field bbs-trait-catalog-scope">
            <label for="effect_stat_id">적용 스탯</label>
            <select class="frm_input" name="effect_stat_id" id="effect_stat_id">
                <option value="0">스탯 제한 없음</option>
                <?php foreach ($status_definitions as $status_id => $definition) { ?>
                    <option value="<?php echo (int)$status_id; ?>"><?php echo bbs_trait_html($definition['name']); ?> 판정</option>
                <?php } ?>
            </select>
            <small>선택한 스탯이 포함된 2스탯 조합 판정에도 적용됩니다.</small>
        </div>
        <div class="bbs-trait-catalog-field">
            <label for="effect_value">보정값 (-100~+100)</label>
            <input class="frm_input" type="number" name="effect_value" id="effect_value" min="-100" max="100" value="0">
        </div>
        <div class="bbs-trait-catalog-field">
            <label for="usage_limit">기간당 사용 횟수</label>
            <div class="bbs-trait-usage-controls">
                <input class="frm_input" type="number" name="usage_limit" id="usage_limit" min="0" max="100" value="1">
                <label><input type="checkbox" name="unlimited" value="1"> 무제한</label>
            </div>
        </div>
    </div>
    <button type="submit" class="btn_submit">특성 추가</button>
</form>
<div class="tbl_head01 tbl_wrap">
    <table class="bbs-trait-catalog-table">
        <caption>특성 목록</caption>
        <colgroup>
            <col class="bbs-trait-col-select">
            <col class="bbs-trait-col-status">
            <col class="bbs-trait-col-icon">
            <col class="bbs-trait-col-name">
            <col class="bbs-trait-col-stat">
            <col class="bbs-trait-col-comment">
            <col class="bbs-trait-col-description">
            <col class="bbs-trait-col-effect">
            <col class="bbs-trait-col-limit">
            <col class="bbs-trait-col-manage">
        </colgroup>
        <thead><tr><th><input type="checkbox" id="trait_catalog_select_all" aria-label="전체 특성 선택"></th><th>상태</th><th>아이콘</th><th>특성명</th><th>적용 스탯</th><th>리플 사용 가능</th><th>설명</th><th>효과</th><th>기간당 제한</th><th>관리</th></tr></thead>
        <tbody>
        <?php $count = 0; while ($result && $trait = sql_fetch_array($result)) { $count++; ?>
            <tr>
                <td><input type="checkbox" name="selected_ids[]" value="<?php echo (int)$trait['trait_id']; ?>" form="bbs-trait-bulk-form" aria-label="<?php echo bbs_trait_html($trait['trait_name']); ?> 선택"></td>
                <td><?php echo !empty($trait['trait_enabled']) ? '사용 중' : '비활성'; ?></td>
                <td><?php $icon_url = bbs_trait_icon_url((string)$trait['trait_icon']); if ($icon_url !== '') { ?><img class="bbs-trait-admin-icon" src="<?php echo bbs_trait_html($icon_url); ?>" alt=""><?php } ?></td>
                <td><?php echo bbs_trait_html($trait['trait_name']); ?></td>
                <td><?php echo bbs_trait_html(bbs_trait_effect_scope_label(isset($trait['effect_stat_id']) ? $trait['effect_stat_id'] : 0)); ?></td>
                <td><?php echo !empty($trait['comment_enabled']) ? '허용' : '—'; ?></td>
                <td><?php echo nl2br(bbs_trait_html($trait['trait_description'])); ?></td>
                <td><?php echo bbs_trait_html(bbs_trait_effect_text($trait['effect_type'], $trait['effect_value'])); ?></td>
                <td><?php echo $trait['usage_limit'] === null ? '무제한' : (int)$trait['usage_limit'].'회'; ?></td>
                <td>
                    <a class="btn btn_03" href="<?php echo G5_MDL_ADMIN_URL; ?>/module.php?m=bbs_trait&amp;p=trait_form&amp;trait_id=<?php echo (int)$trait['trait_id']; ?>">관리</a>
                    <form method="post" class="bbs-trait-inline-form">
                        <input type="hidden" name="token" value="<?php echo bbs_trait_html(get_token()); ?>">
                        <input type="hidden" name="mode" value="toggle">
                        <input type="hidden" name="trait_id" value="<?php echo (int)$trait['trait_id']; ?>">
                        <input type="hidden" name="enabled" value="<?php echo !empty($trait['trait_enabled']) ? 0 : 1; ?>">
                        <button type="submit" class="btn btn_02"><?php echo !empty($trait['trait_enabled']) ? '비활성화' : '활성화'; ?></button>
                    </form>
                </td>
            </tr>
        <?php } if (!$count) { ?><tr><td colspan="10" class="empty_table">등록된 특성이 없습니다.</td></tr><?php } ?>
        </tbody>
    </table>
</div>
<form method="post" id="bbs-trait-bulk-form">
    <input type="hidden" name="token" value="<?php echo bbs_trait_html(get_token()); ?>">
    <input type="hidden" name="mode" value="bulk_toggle">
    <button type="submit" name="enabled" value="1" class="btn btn_03">선택 특성 활성화</button>
    <button type="submit" name="enabled" value="0" class="btn btn_02">선택 특성 비활성화</button>
</form>
<script>(function(){var effect=document.getElementById('effect_type');var value=document.getElementById('effect_value');var scope=document.getElementById('effect_stat_id');var scopeField=scope?scope.parentNode:null;var unlimited=document.querySelector('input[name="unlimited"]');var limit=document.getElementById('usage_limit');var help=document.getElementById('trait_effect_help');function sync(){var option=effect&&effect.options[effect.selectedIndex];var supportsScope=option&&option.getAttribute('data-stat-scope')==='1';if(effect&&value){var reroll=effect.value==='reroll_lowest';value.disabled=reroll;value.required=!reroll;}if(scope&&scopeField){scopeField.hidden=!supportsScope;scope.disabled=!supportsScope;if(!supportsScope)scope.value='0';}if(unlimited&&limit)limit.disabled=unlimited.checked;if(effect&&help){var description=option?option.getAttribute('data-effect-description')||'':'';help.setAttribute('data-tooltip',description);help.setAttribute('title',description);help.setAttribute('aria-label',description?'효과 설명: '+description:'효과 설명');}}if(effect)effect.addEventListener('change',sync);if(unlimited)unlimited.addEventListener('change',sync);sync();})();</script>
<script>(function(){var file=document.getElementById('trait_icon');var url=document.getElementById('trait_icon_url');if(!file||!url)return;file.addEventListener('change',function(){if(file.value)url.value='';});url.addEventListener('input',function(){if(url.value)file.value='';});})();</script>
<script>(function(){var all=document.getElementById('trait_catalog_select_all');if(all)all.addEventListener('change',function(){document.querySelectorAll('input[name="selected_ids[]"][form="bbs-trait-bulk-form"]').forEach(function(box){box.checked=all.checked;});});})();</script>
