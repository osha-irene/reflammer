<?php
if (!defined('_GNUBOARD_')) exit;

$sub_menu = function_exists('mdl_admin_menu_code') ? mdl_admin_menu_code('bbs_trait', '100') : '001100';
global $auth;
if (isset($auth[$sub_menu])) auth_check($auth[$sub_menu], $_SERVER['REQUEST_METHOD'] === 'POST' ? 'w' : 'r');
add_stylesheet('<link rel="stylesheet" href="'.G5_MDL_URL.'/bbs_trait/css/admin.css">', 100);

$message = '';
$period = bbs_trait_period_state();
if (!$period) alert('특성 초기화 기간 정보를 불러오지 못했습니다.');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_demo();
    check_token();
    $setting_group = isset($_POST['setting_group']) && is_scalar($_POST['setting_group']) ? (string)$_POST['setting_group'] : '';
    if ($setting_group === 'slots') {
        $max_raw = isset($_POST['slot_count']) && is_scalar($_POST['slot_count']) ? (string)$_POST['slot_count'] : '';
        $base_raw = isset($_POST['base_slot_count']) && is_scalar($_POST['base_slot_count']) ? (string)$_POST['base_slot_count'] : '';
        if (!preg_match('/^(0|[1-9][0-9]{0,2})$/', $max_raw) || (int)$max_raw > 100) {
            $message = '최대 보유 슬롯은 0~100 사이의 정수로 입력해 주세요.';
        } elseif (!preg_match('/^(0|[1-9][0-9]{0,2})$/', $base_raw) || (int)$base_raw > 100) {
            $message = '기본 개방 슬롯은 0~100 사이의 정수로 입력해 주세요.';
        } elseif ((int)$base_raw > (int)$max_raw) {
            $message = '기본 개방 슬롯은 최대 보유 슬롯보다 클 수 없습니다.';
        } else {
            $base_saved = mdl_setting_set('bbs_trait', 'base_slot_count', (int)$base_raw);
            $max_saved = mdl_setting_set('bbs_trait', 'slot_count', (int)$max_raw);
            $message = $max_saved && $base_saved
                ? '캐릭터 슬롯 설정을 저장했습니다.'
                : '캐릭터 슬롯 설정을 저장하지 못했습니다.';
        }
    } elseif ($setting_group === 'period') {
        $mode = isset($_POST['period_mode']) && is_scalar($_POST['period_mode']) ? (string)$_POST['period_mode'] : '';
        $hours_raw = isset($_POST['period_hours']) && is_scalar($_POST['period_hours']) ? (string)$_POST['period_hours'] : '';
        if (!in_array($mode, array('hour', 'daily', 'weekly', 'monthly'), true)) {
            $message = '초기화 주기를 선택해 주세요.';
        } elseif ($mode === 'hour' && (!preg_match('/^[1-9][0-9]{0,2}$/', $hours_raw) || (int)$hours_raw > 168)) {
            $message = 'N시간 값은 1~168 사이의 정수로 입력해 주세요.';
        } else {
            $active_mode = $period['mode'];
            $active_hours = $period['hours'];
            if ($mode === $active_mode && ($mode !== 'hour' || (int)$hours_raw === $active_hours)) {
                $period_saved = mdl_setting_delete('bbs_trait', 'pending_period_mode')
                    && mdl_setting_delete('bbs_trait', 'pending_period_hours');
            } else {
                $period_saved = mdl_setting_set('bbs_trait', 'pending_period_mode', $mode)
                    && mdl_setting_set('bbs_trait', 'pending_period_hours', $mode === 'hour' ? (int)$hours_raw : $active_hours);
            }
            $message = $period_saved
                ? '사용 횟수 초기화 주기를 저장했습니다. 변경 사항은 현재 기간 종료 후 적용됩니다.'
                : '사용 횟수 초기화 주기를 저장하지 못했습니다.';
        }
    } else {
        $message = '저장할 설정 항목을 확인해 주세요.';
    }
}

$period = $period ? $period : bbs_trait_period_state();
if (!$period) alert('특성 초기화 기간 정보를 불러오지 못했습니다.');
$pending_mode = (string)mdl_setting_get('bbs_trait', 'pending_period_mode', '');
$pending_hours = (int)mdl_setting_get('bbs_trait', 'pending_period_hours', 0);
$selected_mode = $pending_mode !== '' ? $pending_mode : $period['mode'];
$slot_count = bbs_trait_max_slots();
$base_slot_count = bbs_trait_base_slots();
$period_start = bbs_trait_parse_datetime($period['start']);
$period_end = bbs_trait_parse_datetime($period['end']);
?>
<?php if ($message !== '') { ?><div class="local_desc01 local_desc"><p><?php echo bbs_trait_html($message); ?></p></div><?php } ?>
<div class="bbs-trait-settings">
    <form method="post" class="bbs-trait-setting-box">
        <input type="hidden" name="token" value="<?php echo bbs_trait_html(get_token()); ?>">
        <input type="hidden" name="setting_group" value="slots">
        <h2>캐릭터 공통 보유 슬롯</h2>
        <p>모든 캐릭터는 기본 슬롯만 개방된 상태로 시작합니다. 상점에서 특성 슬롯 확장 아이템을 구매해 인벤토리에서 사용하면 슬롯이 1개 영구 개방됩니다. 슬롯 수를 줄여도 기존 보유 특성은 유지됩니다.</p>
        <div class="bbs-trait-setting-fields">
            <div class="bbs-trait-setting-control">
                <label for="base_slot_count">캐릭터당 기본 개방 슬롯</label>
                <input class="frm_input" type="number" id="base_slot_count" name="base_slot_count" min="0" max="100" step="1" value="<?php echo $base_slot_count; ?>" required>
            </div>
            <div class="bbs-trait-setting-control">
                <label for="slot_count">캐릭터당 최대 보유 슬롯</label>
                <input class="frm_input" type="number" id="slot_count" name="slot_count" min="0" max="100" step="1" value="<?php echo $slot_count; ?>" required>
            </div>
            <button type="submit" class="btn_submit">슬롯 설정 저장</button>
        </div>
    </form>

    <form method="post" class="bbs-trait-setting-box">
        <input type="hidden" name="token" value="<?php echo bbs_trait_html(get_token()); ?>">
        <input type="hidden" name="setting_group" value="period">
        <h2>사용 횟수 초기화 주기</h2>
        <p>대한민국 표준시(KST) 기준입니다. 주기 변경은 현재 기간이 끝난 뒤 적용되며, 특성별 사용 제한 변경은 즉시 반영됩니다.</p>
        <div class="bbs-trait-setting-fields">
            <div class="bbs-trait-setting-control">
                <label for="period_mode">초기화 주기</label>
                <select class="frm_input" id="period_mode" name="period_mode">
                    <option value="hour" <?php echo $selected_mode === 'hour' ? 'selected' : ''; ?>>N시간마다</option>
                    <option value="daily" <?php echo $selected_mode === 'daily' ? 'selected' : ''; ?>>매일 00:00</option>
                    <option value="weekly" <?php echo $selected_mode === 'weekly' ? 'selected' : ''; ?>>매주 월요일 00:00</option>
                    <option value="monthly" <?php echo $selected_mode === 'monthly' ? 'selected' : ''; ?>>매월 1일 00:00</option>
                </select>
            </div>
            <div class="bbs-trait-setting-control">
                <label for="period_hours">N시간 값 설정</label>
                <input class="frm_input" type="number" id="period_hours" name="period_hours" min="1" max="168" step="1" value="<?php echo $pending_mode === 'hour' ? max(1, $pending_hours) : (int)$period['hours']; ?>" <?php echo $selected_mode === 'hour' ? '' : 'disabled'; ?>>
                <small>N시간마다를 선택했을 때만 입력할 수 있습니다 (1~168).</small>
            </div>
            <button type="submit" class="btn_submit">주기 저장</button>
        </div>
        <div class="bbs-trait-period-status">
            <p>현재 주기: <?php echo bbs_trait_html(bbs_trait_period_label($period['mode'], $period['hours'])); ?></p>
            <?php if ($period_start && $period_end) { ?>
                <p>현재 기간: <?php echo bbs_trait_html($period_start->format('Y-m-d H:i')); ?> ~ <?php echo bbs_trait_html($period_end->format('Y-m-d H:i')); ?> KST</p>
            <?php } ?>
            <?php if ($pending_mode !== '') { ?>
                <p>예약된 주기: <?php echo bbs_trait_html(bbs_trait_period_label($pending_mode, $pending_hours)); ?> (현재 기간 종료 후 적용)</p>
            <?php } ?>
        </div>
    </form>
</div>
<script>(function(){var mode=document.getElementById('period_mode');var hours=document.getElementById('period_hours');if(!mode||!hours)return;function sync(){hours.disabled=mode.value!=='hour';}mode.addEventListener('change',sync);sync();})();</script>
