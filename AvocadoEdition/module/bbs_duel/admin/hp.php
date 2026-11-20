<?php
if (!defined('_GNUBOARD_')) exit;

$sub_menu = function_exists('mdl_admin_menu_code') ? mdl_admin_menu_code('bbs_duel', '200') : '001200';
global $auth, $g5;
if (isset($auth[$sub_menu])) auth_check($auth[$sub_menu], $_SERVER['REQUEST_METHOD'] === 'POST' ? 'w' : 'r');
add_stylesheet('<link rel="stylesheet" href="'.G5_MDL_URL.'/bbs_duel/css/admin.css">', 100);
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_demo();
    check_token();
    $mode = isset($_POST['mode']) && is_scalar($_POST['mode']) ? (string)$_POST['mode'] : '';
    if ($mode === 'save_settings') {
        $player_raw = isset($_POST['player_hp_default']) ? (string)$_POST['player_hp_default'] : '';
        $damage_raw = isset($_POST['default_damage']) ? (string)$_POST['default_damage'] : '';
        if (preg_match('/^[1-9][0-9]{0,3}$/', $player_raw) && (int)$player_raw <= 1000 && preg_match('/^[0-9]{1,4}$/', $damage_raw) && (int)$damage_raw <= 1000) {
            mdl_setting_set('bbs_duel', 'player_hp_default', (int)$player_raw);
            mdl_setting_set('bbs_duel', 'default_damage', (int)$damage_raw);
            $message = '기본 HP와 기본 피해량을 저장했습니다. 기본 HP 변경은 이후 승인되는 캐릭터에 적용됩니다.';
        } else $message = '기본 HP는 1~1000, 기본 피해량은 0~1000 사이의 정수로 입력해 주세요.';
    } elseif ($mode === 'save_hp') {
        $single_key = isset($_POST['single_key']) && is_scalar($_POST['single_key']) ? (string)$_POST['single_key'] : '';
        $selected = $single_key !== '' ? array($single_key) : (isset($_POST['selected']) && is_array($_POST['selected']) ? $_POST['selected'] : array());
        $values = isset($_POST['current']) && is_array($_POST['current']) ? $_POST['current'] : array();
        $valid = count($selected) > 0;
        $updates = array();
        foreach ($selected as $key) {
            if (!is_scalar($key) || !preg_match('/^(character|enemy):([1-9][0-9]*)$/', (string)$key, $m)) { $valid = false; break; }
            $type = $m[1]; $id = (int)$m[2];
            $raw = isset($values[$key]) && is_scalar($values[$key]) ? (string)$values[$key] : '';
            if (!preg_match('/^(0|[1-9][0-9]{0,3})$/', $raw) || (int)$raw > 1000) { $valid = false; break; }
            if ($type === 'character') {
                $entity = sql_fetch("SELECT ch_id FROM `{$g5['character_table']}` WHERE ch_id='{$id}' AND ch_state='승인' AND ch_type IN ('main','sub') LIMIT 1", false);
                if (empty($entity['ch_id'])) { $valid = false; break; }
                bbs_duel_hp_ensure_character($id);
            } else {
                $entity = sql_fetch("SELECT de_id FROM `".bbs_duel_enemy_table()."` WHERE de_id='{$id}' LIMIT 1", false);
                if (empty($entity['de_id'])) { $valid = false; break; }
                bbs_duel_hp_ensure_enemy($id);
            }
            $updates[] = array($type, $id, (int)$raw);
        }
        if ($valid && $updates) {
            foreach ($updates as $update) bbs_duel_hp_set_current($update[0], $update[1], $update[2]);
            $message = $single_key !== '' ? '현재 HP를 수정했습니다.' : count($updates).'개 대상의 현재 HP를 저장했습니다.';
        } else $message = '수정할 대상을 체크하고 HP를 0~1000 사이의 정수로 입력해 주세요.';
    }
}

$players = array();
$rs = sql_query("SELECT ch_id, ch_name, ch_type FROM `{$g5['character_table']}` WHERE ch_state='승인' AND ch_type IN ('main','sub') ORDER BY ch_name ASC, ch_id ASC", false);
while ($rs && $row = sql_fetch_array($rs)) {
    $hp = bbs_duel_hp_ensure_character((int)$row['ch_id']);
    $players[] = array('type' => 'character', 'id' => (int)$row['ch_id'], 'name' => (string)$row['ch_name'], 'kind' => $row['ch_type'] === 'sub' ? '서브 캐릭터' : '플레이어', 'hp' => $hp);
}
$enemies = array();
$rs = sql_query("SELECT de_id, de_name, de_hp_max FROM `".bbs_duel_enemy_table()."` ORDER BY de_order ASC, de_id ASC", false);
while ($rs && $row = sql_fetch_array($rs)) {
    $hp = bbs_duel_hp_ensure_enemy((int)$row['de_id'], (int)$row['de_hp_max']);
    $enemies[] = array('type' => 'enemy', 'id' => (int)$row['de_id'], 'name' => (string)$row['de_name'], 'kind' => '에너미', 'hp' => $hp);
}
$player_default = bbs_duel_hp_default();
$default_damage = bbs_duel_default_damage();
?>
<div class="local_desc01 local_desc"><p>HP는 스탯과 독립적으로 관리됩니다. 기본 HP는 새로 승인되는 플레이어 캐릭터에만 적용되며, 기존 캐릭터의 현재 HP는 아래에서 조정합니다.</p></div>
<?php if ($message !== '') { ?><div class="local_desc01 local_desc"><p><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p></div><?php } ?>
<form method="post" class="local_sch01 local_sch bbs-duel-enemy-form">
    <input type="hidden" name="token" value="<?php echo htmlspecialchars(get_token(), ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="mode" value="save_settings">
    <label for="player_hp_default">플레이어 기본 HP</label><input class="frm_input" type="number" id="player_hp_default" name="player_hp_default" min="1" max="1000" value="<?php echo $player_default; ?>" required>
    <label for="default_damage">기본 결투 피해량</label><input class="frm_input" type="number" id="default_damage" name="default_damage" min="0" max="1000" value="<?php echo $default_damage; ?>" required>
    <button type="submit" class="btn_submit">설정 저장</button>
</form>
<form method="post" id="duel_hp_form">
    <input type="hidden" name="token" value="<?php echo htmlspecialchars(get_token(), ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="mode" value="save_hp">
    <div class="tbl_head01 tbl_wrap"><table><caption>캐릭터·에너미 HP 관리</caption>
        <thead><tr><th><input type="checkbox" id="hp_select_all" aria-label="전체 선택"></th><th>구분</th><th>이름</th><th>현재 HP / 최대 HP</th><th>현재 HP 조정</th><th>상태</th><th>관리</th></tr></thead><tbody>
        <?php foreach (array_merge($players, $enemies) as $entity) { $key = $entity['type'].':'.$entity['id']; ?>
        <tr><td><input type="checkbox" name="selected[]" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" aria-label="선택 <?php echo htmlspecialchars($entity['name'], ENT_QUOTES, 'UTF-8'); ?>"></td>
            <td><?php echo htmlspecialchars($entity['kind'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars($entity['name'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo (int)$entity['hp']['current']; ?> / <?php echo (int)$entity['hp']['max']; ?></td>
            <td><input class="frm_input bbs-duel-stat-input" form="duel_hp_form" type="number" name="current[<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>]" min="0" max="1000" value="<?php echo (int)$entity['hp']['current']; ?>" aria-label="현재 HP"></td>
            <td><?php echo !empty($entity['hp']['incapacitated']) ? '전투불능' : '전투 가능'; ?></td>
            <td class="bbs-duel-manage"><button type="submit" name="single_key" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn_03">수정</button></td></tr>
        <?php } if (!$players && !$enemies) { ?><tr><td colspan="7" class="empty_table">관리할 캐릭터와 에너미가 없습니다.</td></tr><?php } ?>
        </tbody></table></div>
    <div class="bbs-duel-bulk-save"><small>체크한 대상의 현재 HP만 변경합니다. 최대 HP를 초과해 입력하면 최대 HP로 저장됩니다.</small><button type="submit" class="btn_submit">현재 HP 저장</button></div>
</form>
<script>(function(){var all=document.getElementById('hp_select_all');if(all)all.addEventListener('change',function(){document.querySelectorAll('input[name="selected[]"]').forEach(function(box){box.checked=all.checked;});});})();</script>
