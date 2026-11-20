<?php
if (!defined('_GNUBOARD_')) exit;

$sub_menu = function_exists('mdl_admin_menu_code') ? mdl_admin_menu_code('bbs_duel', '300') : '001300';
global $auth;
if (isset($auth[$sub_menu])) auth_check($auth[$sub_menu], 'r');
add_stylesheet('<link rel="stylesheet" href="'.G5_MDL_URL.'/bbs_duel/css/admin.css">', 100);

$filters = array(
    'from' => isset($_GET['from']) && is_scalar($_GET['from']) ? trim((string)$_GET['from']) : '',
    'to' => isset($_GET['to']) && is_scalar($_GET['to']) ? trim((string)$_GET['to']) : '',
    'type' => isset($_GET['type']) && is_scalar($_GET['type']) ? (string)$_GET['type'] : '',
    'name' => isset($_GET['name']) && is_scalar($_GET['name']) ? trim((string)$_GET['name']) : '',
    'winner' => isset($_GET['winner']) && is_scalar($_GET['winner']) ? trim((string)$_GET['winner']) : '',
);
$valid_types = array('', 'spar', 'duel', 'admin', 'item', 'skill');
if (!in_array($filters['type'], $valid_types, true)) $filters['type'] = '';
foreach (array('from','to') as $date_key) {
    if ($filters[$date_key] !== '') {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $filters[$date_key], $date_parts)
            || !checkdate((int)$date_parts[2], (int)$date_parts[3], (int)$date_parts[1])) $filters[$date_key] = '';
    }
}
$where = array('1=1');
if ($filters['from'] !== '') $where[] = "event_datetime >= '".bbs_duel_escape($filters['from'])." 00:00:00'";
if ($filters['to'] !== '') $where[] = "event_datetime <= '".bbs_duel_escape($filters['to'])." 23:59:59'";
if ($filters['type'] !== '') $where[] = "event_type='".bbs_duel_escape($filters['type'])."'";
if ($filters['name'] !== '') {
    $name = bbs_duel_escape($filters['name']);
    $where[] = "(actor_name LIKE '%{$name}%' OR target_name LIKE '%{$name}%')";
}
if ($filters['winner'] !== '') $where[] = "winner_name LIKE '%".bbs_duel_escape($filters['winner'])."%'";
$where_sql = implode(' AND ', $where);
$table = bbs_duel_history_table();
$count_row = sql_fetch("SELECT COUNT(*) AS cnt FROM `{$table}` WHERE {$where_sql}", false);
$total = isset($count_row['cnt']) ? (int)$count_row['cnt'] : 0;
$per_page = 20;
$page = isset($_GET['page']) && preg_match('/^[1-9][0-9]*$/', (string)$_GET['page']) ? (int)$_GET['page'] : 1;
$total_pages = max(1, (int)ceil($total / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;
$result = sql_query("SELECT * FROM `{$table}` WHERE {$where_sql} ORDER BY event_datetime DESC, history_id DESC LIMIT {$offset}, {$per_page}", false);
$labels = array('spar' => '대련', 'duel' => '결투', 'admin' => '운영자 HP 조정', 'item' => '아이템 사용', 'skill' => '스킬 사용');
$query = $filters;
unset($query['unused']);
$query_string = http_build_query($query);
?>
<div class="local_desc01 local_desc"><p>대련·결투 결과와 운영자 HP 조정 이력을 최신순으로 확인합니다. 아이템·스킬 HP 기록은 해당 기능이 추가될 때 같은 기록 형식으로 연결할 수 있습니다.</p></div>
<form method="get" class="local_sch01 local_sch bbs-duel-history-filter">
    <input type="hidden" name="m" value="bbs_duel"><input type="hidden" name="p" value="history">
    <label for="history_from">기간</label>
    <input type="date" id="history_from" name="from" value="<?php echo htmlspecialchars($filters['from'], ENT_QUOTES, 'UTF-8'); ?>" class="frm_input">
    <span>~</span>
    <input type="date" id="history_to" name="to" value="<?php echo htmlspecialchars($filters['to'], ENT_QUOTES, 'UTF-8'); ?>" class="frm_input">
    <label for="history_type">구분</label>
    <select id="history_type" name="type" class="frm_input">
        <option value="" <?php echo $filters['type'] === '' ? 'selected' : ''; ?>>전체</option>
        <?php foreach ($labels as $key => $label) { ?><option value="<?php echo $key; ?>" <?php echo $filters['type'] === $key ? 'selected' : ''; ?>><?php echo $label; ?></option><?php } ?>
    </select>
    <label for="history_name">캐릭터/에너미</label>
    <input type="text" id="history_name" name="name" value="<?php echo htmlspecialchars($filters['name'], ENT_QUOTES, 'UTF-8'); ?>" class="frm_input" maxlength="100">
    <label for="history_winner">승자</label>
    <input type="text" id="history_winner" name="winner" value="<?php echo htmlspecialchars($filters['winner'], ENT_QUOTES, 'UTF-8'); ?>" class="frm_input" maxlength="100">
    <button type="submit" class="btn_submit">검색</button>
    <a href="<?php echo G5_MDL_ADMIN_URL; ?>/module.php?m=bbs_duel&amp;p=history" class="btn btn_02">초기화</a>
</form>

<div class="tbl_head01 tbl_wrap"><table class="bbs-duel-history-table">
    <caption>결투 및 HP 변경 이력</caption>
    <colgroup>
        <col style="width:5%">
        <col style="width:10%">
        <col style="width:10%">
        <col style="width:19%">
        <col style="width:25%">
        <col style="width:18%">
        <col style="width:13%">
    </colgroup>
    <thead><tr><th scope="col">구분</th><th scope="col">캐릭터</th><th scope="col">상대/대상</th><th scope="col">판정수치</th><th scope="col">HP 기록</th><th scope="col">기록</th><th scope="col">일시</th></tr></thead>
    <tbody>
    <?php $rows = 0; while ($result && $row = sql_fetch_array($result)) { $rows++;
        $type = isset($labels[$row['event_type']]) ? $row['event_type'] : 'admin';
        $is_battle = in_array($type, array('duel','spar'), true);
        $actor_won = $is_battle && $row['winner_name'] !== '' && $row['winner_name'] === $row['actor_name'];
        $target_won = $is_battle && !$actor_won && $row['winner_name'] !== '' && $row['winner_name'] === $row['target_name'];
        $actor_final = (int)$row['actor_score']; $target_final = (int)$row['target_score'];
        $actor_expr = (int)$row['actor_dice_sum'].' '.((int)$row['actor_modifier'] >= 0 ? '+' : '-').' '.abs((int)$row['actor_modifier']).' = '.$actor_final;
        $target_expr = (int)$row['target_dice_sum'].' '.((int)$row['target_modifier'] >= 0 ? '+' : '-').' '.abs((int)$row['target_modifier']).' = '.$target_final;
        $hp_parts = array();
        if ($is_battle) {
            if ((int)$row['actor_hp_before'] > 0) $hp_parts[] = $row['actor_name'].' HP '.(int)$row['actor_hp_before'].' → '.(int)$row['actor_hp_after'];
            if ((int)$row['target_hp_before'] > 0) $hp_parts[] = $row['target_name'].' HP '.(int)$row['target_hp_before'].' → '.(int)$row['target_hp_after'];
            if (!$hp_parts) $hp_parts[] = $type === 'spar' ? '대련 · HP 변화 없음' : 'HP 정보 없음';
        } else {
            $hp_parts[] = $row['target_name'].' HP '.(int)$row['target_hp_before'].' → '.(int)$row['target_hp_after'];
        }
        ?>
        <tr>
            <td><?php echo htmlspecialchars($labels[$type], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php if ($is_battle) { echo htmlspecialchars($row['actor_name'], ENT_QUOTES, 'UTF-8'); if ($actor_won) echo ' (승)'; } else { echo '—'; } ?></td>
            <td><?php echo htmlspecialchars($row['target_name'], ENT_QUOTES, 'UTF-8'); if ($target_won) echo ' (승)'; ?></td>
            <td><?php if ($is_battle) { ?><span><?php echo htmlspecialchars($row['actor_name'], ENT_QUOTES, 'UTF-8'); ?>: <?php echo $actor_expr; ?></span><br><span><?php echo htmlspecialchars($row['target_name'], ENT_QUOTES, 'UTF-8'); ?>: <?php echo $target_expr; ?></span><?php } else { echo '—'; } ?></td>
            <td><?php echo htmlspecialchars(implode(' / ', $hp_parts), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars($row['event_note'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars($row['event_datetime'], ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
    <?php } if (!$rows) { ?><tr><td colspan="7" class="empty_table">조건에 맞는 기록이 없습니다.</td></tr><?php } ?>
    </tbody>
</table></div>
<?php if ($total_pages > 1) { ?><nav class="bbs-duel-history-pagination" aria-label="기록 페이지">
    <?php for ($i = 1; $i <= $total_pages; $i++) { $url = G5_MDL_ADMIN_URL.'/module.php?m=bbs_duel&p=history&'.($query_string !== '' ? $query_string.'&' : '').'page='.$i; ?>
        <a href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $i === $page ? 'selected' : ''; ?>"><?php echo $i; ?></a>
    <?php } ?>
</nav><?php } ?>
