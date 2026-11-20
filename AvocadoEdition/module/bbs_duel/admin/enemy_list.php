<?php
if (!defined('_GNUBOARD_')) exit;

$sub_menu = function_exists('mdl_admin_menu_code') ? mdl_admin_menu_code('bbs_duel', '100') : '001100';
global $auth;
if (isset($auth[$sub_menu])) auth_check($auth[$sub_menu], $_SERVER['REQUEST_METHOD'] === 'POST' ? 'w' : 'r');
add_stylesheet('<link rel="stylesheet" href="'.G5_MDL_URL.'/bbs_duel/css/admin.css">', 100);
$table = bbs_duel_enemy_table();
$message = '';
$player_stat_cap = bbs_duel_player_stat_cap();
$status_lineup = bbs_duel_status_definitions();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_demo();
    check_token();
    $mode = isset($_POST['mode']) ? (string)$_POST['mode'] : '';
    if ($mode === 'save_player_cap') {
        $cap_raw = isset($_POST['player_stat_cap']) ? (string)$_POST['player_stat_cap'] : '';
        if (preg_match('/^[1-9][0-9]{0,2}$/', $cap_raw) && (int)$cap_raw <= 100) {
            mdl_setting_set('bbs_duel', 'player_stat_cap', (int)$cap_raw);
            $player_stat_cap = (int)$cap_raw;
            $message = '플레이어 대련·결투 스탯 상한을 저장했습니다.';
        } else {
            $message = '플레이어 스탯 상한은 1~100 사이의 정수로 입력해 주세요.';
        }
    } elseif ($mode === 'add') {
        $name = trim(isset($_POST['de_name']) ? (string)$_POST['de_name'] : '');
        $name_length = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : preg_match_all('/./us', $name, $unused);
        $posted_stats = isset($_POST['de_stats']) && is_array($_POST['de_stats']) ? $_POST['de_stats'] : array();
        $stats = array();
        $stats_valid = count($status_lineup) > 0;
        foreach ($status_lineup as $status_id => $definition) {
            $value = isset($posted_stats[$status_id]) && is_scalar($posted_stats[$status_id]) ? (string)$posted_stats[$status_id] : '';
            if (!preg_match('/^[1-9][0-9]{0,2}$/', $value) || (int)$value > 100) {
                $stats_valid = false;
                break;
            }
            $stats[(int)$status_id] = (int)$value;
        }
        $order_raw = isset($_POST['de_order']) ? (string)$_POST['de_order'] : '';
        $order_valid = preg_match('/^[1-9][0-9]{0,4}$/', $order_raw);
        if ($name === '' || $name_length === false || $name_length > 100) {
            $message = '에너미 이름을 1~100자로 입력해 주세요.';
        } elseif (!$stats_valid) {
            $message = '관리자 스탯 설정에 등록된 각 스탯에 1~100 사이 정수를 입력해 주세요.';
        } elseif (!$order_valid) {
            $message = '출력 순서는 1~99999 사이의 정수로 입력해 주세요.';
        } else {
            $name_e = bbs_duel_escape($name);
            $exists = sql_fetch("SELECT de_id FROM `{$table}` WHERE de_name='{$name_e}' LIMIT 1", false);
            if (!empty($exists['de_id'])) {
                $message = '이미 등록된 에너미 이름입니다.';
            } else {
                $stats_json = bbs_duel_escape(json_encode($stats));
                $hp_max_raw = isset($_POST['de_hp_max']) ? (string)$_POST['de_hp_max'] : '5';
                $damage_raw = isset($_POST['de_damage_to_player']) ? (string)$_POST['de_damage_to_player'] : '1';
                if (!preg_match('/^[1-9][0-9]{0,3}$/', $hp_max_raw) || (int)$hp_max_raw > 1000 || !preg_match('/^[0-9]{1,4}$/', $damage_raw) || (int)$damage_raw > 1000) {
                    $message = '에너미 최대 HP는 1~1000, 대플레이어 피해량은 0~1000 사이의 정수로 입력해 주세요.';
                } else {
                sql_query("INSERT INTO `{$table}` SET de_name='{$name_e}', de_order='".(int)$order_raw."', de_stats='{$stats_json}', de_hp_max='".(int)$hp_max_raw."', de_damage_to_player='".(int)$damage_raw."', de_datetime='".G5_TIME_YMDHIS."'", false);
                $new_enemy = sql_fetch("SELECT de_id FROM `{$table}` WHERE de_name='{$name_e}' LIMIT 1", false);
                if (!empty($new_enemy['de_id'])) bbs_duel_hp_ensure_enemy((int)$new_enemy['de_id'], (int)$hp_max_raw);
                $message = '에너미를 추가했습니다.';
                }
            }
        }
    } elseif ($mode === 'bulk_update') {
        $single_id_raw = isset($_POST['single_id']) ? $_POST['single_id'] : '';
        $single_id = is_scalar($single_id_raw) && preg_match('/^[1-9][0-9]*$/', (string)$single_id_raw)
            ? (int)$single_id_raw
            : 0;
        $selected = array();
        $selected_raw = isset($_POST['selected_ids']) && is_array($_POST['selected_ids']) ? $_POST['selected_ids'] : array();
        foreach ($selected_raw as $selected_id_raw) {
            if (!is_scalar($selected_id_raw) || !preg_match('/^[1-9][0-9]*$/', (string)$selected_id_raw)) continue;
            $selected[] = (int)$selected_id_raw;
        }
        $ids = $single_id > 0 ? array($single_id) : array_values(array_unique($selected));
        $posted_enemies = isset($_POST['enemies']) && is_array($_POST['enemies']) ? $_POST['enemies'] : array();
        $updates = array();
        $valid = count($ids) > 0;
        foreach ($ids as $id) {
            if ($id < 1 || !isset($posted_enemies[$id]) || !is_array($posted_enemies[$id])) {
                $valid = false;
                break;
            }
            $values = $posted_enemies[$id];
            $order_raw = isset($values['de_order']) ? (string)$values['de_order'] : '';
            $posted_stats = isset($values['stats']) && is_array($values['stats']) ? $values['stats'] : array();
            $stats = array();
            if (!$status_lineup || !isset($values['de_order']) || !is_scalar($values['de_order'])) $valid = false;
            foreach ($status_lineup as $status_id => $definition) {
                $value = isset($posted_stats[$status_id]) && is_scalar($posted_stats[$status_id]) ? (string)$posted_stats[$status_id] : '';
                if (!preg_match('/^[1-9][0-9]{0,2}$/', $value) || (int)$value > 100) {
                    $valid = false;
                    break;
                }
                $stats[(int)$status_id] = (int)$value;
            }
            if (!$valid) break;
            $allowed_input = isset($values['allowed_stats']) ? $values['allowed_stats'] : '';
            $allowed_stats = bbs_duel_parse_enemy_stat_input($allowed_input, $allowed_valid);
            if (!$allowed_valid) {
                $valid = false;
                break;
            }
            $allowed_stats = implode('|', $allowed_stats);
            if (!preg_match('/^[1-9][0-9]{0,4}$/', $order_raw)) {
                $valid = false;
                break;
            }
            $hp_max_raw = isset($values['de_hp_max']) ? (string)$values['de_hp_max'] : '';
            $damage_raw = isset($values['de_damage_to_player']) ? (string)$values['de_damage_to_player'] : '';
            if (!preg_match('/^[1-9][0-9]{0,3}$/', $hp_max_raw) || (int)$hp_max_raw > 1000 || !preg_match('/^[0-9]{1,4}$/', $damage_raw) || (int)$damage_raw > 1000) { $valid = false; break; }
            $exists = sql_fetch("SELECT de_id FROM {$table} WHERE de_id='".(int)$id."' LIMIT 1", false);
            if (empty($exists['de_id'])) {
                $valid = false;
                break;
            }
            $updates[(int)$id] = array((int)$order_raw, json_encode($stats), $allowed_stats, (int)$hp_max_raw, (int)$damage_raw);
        }
        if (!$valid) {
            $message = (!$single_id && !$ids)
                ? '전체 저장할 에너미를 먼저 체크해 주세요.'
                : ($single_id > 0
                ? '수정할 에너미와 순서·스탯·HP·피해량 값을 확인해 주세요.'
                : '체크된 에너미의 값을 확인해 주세요. 판정 제한은 관리자 스탯 이름과 두 스탯 조합을 |로 구분해 입력할 수 있습니다.');
        } else {
            foreach ($updates as $id => $values) {
                sql_query("UPDATE `{$table}` SET de_order='{$values[0]}', de_stats='".bbs_duel_escape($values[1])."', de_allowed_stats='".bbs_duel_escape($values[2])."', de_hp_max='{$values[3]}', de_damage_to_player='{$values[4]}' WHERE de_id='{$id}' LIMIT 1", false);
                bbs_duel_hp_ensure_enemy($id, $values[3]);
                bbs_duel_hp_set_max('enemy', $id, $values[3]);
            }
            $message = $single_id > 0 ? '에너미 정보를 수정했습니다.' : count($updates).'명의 에너미 정보를 저장했습니다.';
        }
    } elseif ($mode === 'delete') {
        $id = max(0, (int)(isset($_POST['de_id']) ? $_POST['de_id'] : 0));
        if ($id > 0) {
            sql_query("DELETE FROM `{$table}` WHERE de_id='{$id}' LIMIT 1", false);
            bbs_duel_hp_delete('enemy', $id);
            $message = '에너미를 삭제했습니다. 기존 게시글의 상대 표시는 보존됩니다.';
        }
    }
}

$next_order_row = sql_fetch("SELECT COALESCE(MAX(de_order), 0) + 1 AS next_order FROM `{$table}`", false);
$next_order = max(1, (int)(isset($next_order_row['next_order']) ? $next_order_row['next_order'] : 1));
$result = sql_query("SELECT de_id, de_name, de_order, de_sense, de_insight, de_courage, de_stats, de_allowed_stats, de_hp_max, de_damage_to_player, de_datetime FROM `{$table}` ORDER BY de_order ASC, de_id ASC", false);
$status_count = count($status_lineup);
$stat_col_width = $status_count ? 17 / $status_count : 0;
$empty_colspan = $status_count + 9;
?>
<div class="local_desc01 local_desc"><p>에너미는 NPC 캐릭터와 별도로 관리되는 전투 대상입니다. 아래 스탯 칸은 /adm/에 등록된 스탯 라인업과 같은 순서로 표시됩니다.</p></div>
<p class="bbs-duel-stat-guide">에너미 스탯은 /adm/에 등록된 스탯 이름과 순서대로 입력합니다. 판정 제한은 등록된 스탯명 또는 스탯 두 개의 조합을 |로 구분하세요. 예: 스탯2|스탯3|스탯2+스탯3. 비워 두면 모든 판정을 허용합니다.</p>
<div class="local_desc01 local_desc"><p>플레이어 스탯 상한은 대련·결투에서 주사위 개수에 적용됩니다. 코어의 실제 스탯 배분 상한과는 별도이며, 멀린 주사위 모듈의 최대 굴림 개수에 맞춰 1~100으로 설정할 수 있습니다.</p></div>
<?php if ($message !== '') { ?><div class="local_desc01 local_desc"><p><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p></div><?php } ?>

<form method="post" class="local_sch01 local_sch bbs-duel-enemy-form">
    <input type="hidden" name="token" value="<?php echo htmlspecialchars(get_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="mode" value="save_player_cap">
    <label for="player_stat_cap">플레이어 스탯 상한</label>
    <input type="number" name="player_stat_cap" id="player_stat_cap" min="1" max="100" step="1" value="<?php echo (int)$player_stat_cap; ?>" required class="frm_input">
    <button type="submit" class="btn_submit">저장</button>
</form>

<form method="post" class="local_sch01 local_sch bbs-duel-enemy-form">
    <input type="hidden" name="token" value="<?php echo htmlspecialchars(get_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="mode" value="add">
    <label for="de_name">에너미 이름</label>
    <input type="text" name="de_name" id="de_name" maxlength="100" required class="frm_input bbs-duel-name-input">
    <span class="bbs-duel-stat-field"><label for="de_hp_max_new">최대 HP</label><input type="number" name="de_hp_max" id="de_hp_max_new" min="1" max="1000" step="1" value="5" required class="frm_input"></span>
    <span class="bbs-duel-stat-field"><label for="de_damage_new">대플레이어 피해</label><input type="number" name="de_damage_to_player" id="de_damage_new" min="0" max="1000" step="1" value="1" required class="frm_input"></span>
    <span class="bbs-duel-stat-field"><label for="de_order_new">순서</label><input type="number" name="de_order" id="de_order_new" min="1" max="99999" step="1" value="<?php echo $next_order; ?>" required class="frm_input"></span>
    <?php foreach ($status_lineup as $status_id => $definition) { ?>
    <span class="bbs-duel-stat-field"><label for="de_stat_<?php echo (int)$status_id; ?>"><?php echo htmlspecialchars($definition['name'], ENT_QUOTES, 'UTF-8'); ?></label><input type="number" name="de_stats[<?php echo (int)$status_id; ?>]" id="de_stat_<?php echo (int)$status_id; ?>" min="1" max="100" step="1" value="1" required class="frm_input"></span>
    <?php } ?>
    <button type="submit" class="btn_submit">추가</button>
</form>

<div class="tbl_head01 tbl_wrap">
    <table>
        <caption>에너미 목록</caption>
        <colgroup><col style="width:3%"><col style="width:5%"><col style="width:12%"><?php foreach ($status_lineup as $definition) { ?><col style="width:<?php echo number_format($stat_col_width, 2, '.', ''); ?>%"><?php } ?><col style="width:8%"><col style="width:8%"><col style="width:7%"><col style="width:18%"><col style="width:12%"><col style="width:10%"></colgroup>
        <thead><tr><th scope="col"><input type="checkbox" id="enemy_select_all" aria-label="전체 에너미 선택"></th><th scope="col">순서</th><th scope="col">이름</th><?php foreach ($status_lineup as $definition) { ?><th scope="col"><?php echo htmlspecialchars($definition['name'], ENT_QUOTES, 'UTF-8'); ?></th><?php } ?><th scope="col">최대 HP</th><th scope="col">현재 HP</th><th scope="col">대플레이어 피해</th><th scope="col">허용 판정 스탯</th><th scope="col">등록일</th><th scope="col">관리</th></tr></thead>
        <tbody>
        <?php $count = 0; while ($result && $row = sql_fetch_array($result)) { $count++; ?>
            <?php $enemy_stats = bbs_duel_enemy_stat_values(isset($row['de_stats']) ? $row['de_stats'] : '', $row); ?>
            <tr>
                <td><input type="checkbox" name="selected_ids[]" value="<?php echo (int)$row['de_id']; ?>" form="enemy_bulk_update" aria-label="<?php echo htmlspecialchars($row['de_name'], ENT_QUOTES, 'UTF-8'); ?> 선택"></td>
                <td><input type="number" name="enemies[<?php echo (int)$row['de_id']; ?>][de_order]" min="1" max="99999" step="1" value="<?php echo max(1, (int)$row['de_order']); ?>" form="enemy_bulk_update" aria-label="<?php echo htmlspecialchars($row['de_name'], ENT_QUOTES, 'UTF-8'); ?> 출력 순서" class="frm_input bbs-duel-stat-input"></td>
                <td><?php echo htmlspecialchars($row['de_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                <?php foreach ($status_lineup as $status_id => $definition) { $stat_key = 's'.(int)$status_id; ?>
                <td><input type="number" name="enemies[<?php echo (int)$row['de_id']; ?>][stats][<?php echo (int)$status_id; ?>]" min="1" max="100" step="1" value="<?php echo isset($enemy_stats[$stat_key]) ? (int)$enemy_stats[$stat_key] : 1; ?>" form="enemy_bulk_update" aria-label="<?php echo htmlspecialchars($row['de_name'], ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars($definition['name'], ENT_QUOTES, 'UTF-8'); ?>" class="frm_input bbs-duel-stat-input"></td>
                <?php } ?>
                <?php $hp = bbs_duel_hp_ensure_enemy((int)$row['de_id'], (int)$row['de_hp_max']); ?>
                <td><input type="number" name="enemies[<?php echo (int)$row['de_id']; ?>][de_hp_max]" min="1" max="1000" step="1" value="<?php echo (int)$row['de_hp_max']; ?>" form="enemy_bulk_update" aria-label="<?php echo htmlspecialchars($row['de_name'], ENT_QUOTES, 'UTF-8'); ?> 최대 HP" class="frm_input bbs-duel-stat-input"></td>
                <td><?php echo (int)$hp['current']; ?> / <?php echo (int)$hp['max']; ?></td>
                <td><input type="number" name="enemies[<?php echo (int)$row['de_id']; ?>][de_damage_to_player]" min="0" max="1000" step="1" value="<?php echo (int)$row['de_damage_to_player']; ?>" form="enemy_bulk_update" aria-label="<?php echo htmlspecialchars($row['de_name'], ENT_QUOTES, 'UTF-8'); ?> 대플레이어 피해량" class="frm_input bbs-duel-stat-input"></td>
                <td><input type="text" name="enemies[<?php echo (int)$row['de_id']; ?>][allowed_stats]" value="<?php echo htmlspecialchars(bbs_duel_enemy_stat_input_value(isset($row['de_allowed_stats']) ? $row['de_allowed_stats'] : ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="전체 허용" form="enemy_bulk_update" aria-label="<?php echo htmlspecialchars($row['de_name'], ENT_QUOTES, 'UTF-8'); ?> 허용 판정 스탯" class="frm_input bbs-duel-stat-limit-input"></td>
                <td><?php echo htmlspecialchars($row['de_datetime'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td class="bbs-duel-manage">
                    <button type="submit" name="single_id" value="<?php echo (int)$row['de_id']; ?>" form="enemy_bulk_update" class="btn btn_03">수정</button>
                    <form method="post" onsubmit="return confirm('이 에너미를 선택 목록에서 삭제할까요?');">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars(get_token(), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="mode" value="delete">
                        <input type="hidden" name="de_id" value="<?php echo (int)$row['de_id']; ?>">
                        <button type="submit" class="btn btn_02">삭제</button>
                    </form>
                </td>
            </tr>
        <?php } if (!$count) { ?>
            <tr><td colspan="<?php echo (int)$empty_colspan; ?>" class="empty_table">등록된 에너미가 없습니다.</td></tr>
        <?php } ?>
        </tbody>
    </table>
</div>
<form id="enemy_bulk_update" method="post">
    <input type="hidden" name="token" value="<?php echo htmlspecialchars(get_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="mode" value="bulk_update">
</form>
<div class="bbs-duel-bulk-save">
    <small>체크된 에너미의 변경사항만 저장합니다.</small>
    <button type="submit" form="enemy_bulk_update" class="btn_submit">전체 저장</button>
</div>
<script>
(function(){
    var all = document.getElementById('enemy_select_all');
    if (!all) return;
    all.addEventListener('change', function(){
        document.querySelectorAll('input[name="selected_ids[]"]').forEach(function(box){ box.checked = all.checked; });
    });
})();
</script>
