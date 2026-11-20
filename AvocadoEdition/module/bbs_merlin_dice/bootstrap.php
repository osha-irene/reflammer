<?php
if (!defined('_GNUBOARD_')) exit;

require_once __DIR__.'/lib.php';
$merlin_dice_module_url = G5_MDL_URL.'/bbs_dice';

/** Render a checked Merlin dice control and the stat selector. */
function merlin_dice_form_html($ctx, $scope, $instance_id)
{
    global $character, $member;
    $bo_table = isset($ctx['bo_table']) ? $ctx['bo_table'] : '';
    if (!mdl_board_enabled($bo_table, 'bbs_merlin_dice', $scope)) return '';
    if (!isset($character['ch_id']) || (int)$character['ch_id'] <= 0 || !isset($character['ch_state']) || $character['ch_state'] !== '승인') return '';
    if ($scope === 'write' && !empty($ctx['write']['wr_merlin_dice'])) return '';
    if ($scope === 'comment' && !empty($ctx['list_item']['wr_merlin_dice'])) return '';

    $statuses = merlin_dice_statuses((int)$character['ch_id']);
    if (!$statuses) return '';
    $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$instance_id);
    if ($id === '') $id = 'merlin_dice_'.$scope;
    $options = '<option value="">스탯 선택</option>';
    $status_rows = array_values($statuses);
    // /adm 스탯 순서대로 단일 스탯을 먼저 모두 나열한다.
    foreach ($status_rows as $status) {
        $name = htmlspecialchars($status['name'], ENT_QUOTES, 'UTF-8');
        $options .= '<option value="'.$status['id'].'">'.$name.'</option>';
    }
    // 이후 앞에서부터 모든 2스탯 조합을 순서대로 추가한다.
    for ($index = 0; $index < count($status_rows); $index++) {
        $status = $status_rows[$index];
        for ($next = $index + 1; $next < count($status_rows); $next++) {
            $other = $status_rows[$next];
            $pair_name = htmlspecialchars($status['name'].' + '.$other['name'], ENT_QUOTES, 'UTF-8');
            $options .= '<option value="'.$status['id'].','.$other['id'].'">'.$pair_name.'</option>';
        }
    }
    return '<fieldset class="merlin-dice-option" style="margin-left:8px;">'
        .'<input type="checkbox" id="'.$id.'_enabled" name="wr_merlin_dice" value="1">'
        .'<label for="'.$id.'_enabled">멀린의 주사위</label> '
        .'<select id="'.$id.'_stats" name="wr_merlin_stats" aria-label="멀린의 주사위 스탯" disabled>'
        .$options.'</select>'
        .'<span>스탯 하나 또는 조합을 선택하세요.</span>'
        .'<script>(function(){var c=document.getElementById('.json_encode($id.'_enabled').');var s=document.getElementById('.json_encode($id.'_stats').');if(!c||!s)return;function sync(){s.disabled=!c.checked;s.required=c.checked;s.setCustomValidity(c.checked&&!s.value?"멀린의 주사위 스탯을 선택해 주세요.":"");}c.addEventListener("change",sync);s.addEventListener("change",sync);})();</script>'
        .'</fieldset>';
}

mdl_add_filter('bbs_write_form_options', function ($html, $ctx) {
    if (!empty($ctx['write']['wr_merlin_dice'])) return $html;
    $id = isset($ctx['id_prefix']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $ctx['id_prefix']).'_merlin' : 'merlin_write';
    return $html.merlin_dice_form_html($ctx, 'write', $id);
}, 10);

mdl_add_filter('bbs_comment_form_options', function ($html, $ctx) {
    $wr_id = isset($ctx['list_item']['wr_id']) ? (int)$ctx['list_item']['wr_id'] : 0;
    return $html.merlin_dice_form_html($ctx, 'comment', 'merlin_comment_'.$wr_id);
}, 10);

mdl_add_filter('bbs_customer_sql', function ($customer_sql, $ctx) {
    $post = isset($ctx['post']) && is_array($ctx['post']) ? $ctx['post'] : array();
    if (!isset($post['wr_merlin_dice']) || (string)$post['wr_merlin_dice'] !== '1') return $customer_sql;

    $bo_table = isset($ctx['bo_table']) ? $ctx['bo_table'] : '';
    $w = isset($ctx['w']) ? $ctx['w'] : '';
    if (!mdl_board_enabled_for_w($bo_table, 'bbs_merlin_dice', $w)) return $customer_sql;
    $scope = ($w === 'c' || $w === 'cu') ? 'comment' : 'write';
    if ($scope !== 'write' && $scope !== 'comment') return $customer_sql;

    $existing = isset($ctx['existing']) && is_array($ctx['existing']) ? $ctx['existing'] : array();
    if (!empty($existing['wr_merlin_dice']) || !empty($existing['wr_merlin_stat'])) return $customer_sql;

    $character = isset($ctx['character']) && is_array($ctx['character']) ? $ctx['character'] : array();
    $member = isset($ctx['member']) && is_array($ctx['member']) ? $ctx['member'] : array();
    if (empty($character['ch_id']) || !isset($character['ch_state']) || $character['ch_state'] !== '승인'
        || empty($member['mb_id']) || !isset($character['mb_id']) || $character['mb_id'] !== $member['mb_id']) return $customer_sql;

    $selected_raw = isset($post['wr_merlin_stats']) ? trim((string)$post['wr_merlin_stats']) : '';
    if (!preg_match('/^[0-9]+(?:,[0-9]+)?$/', $selected_raw)) return $customer_sql;
    $selected = array_values(array_unique(array_map('intval', explode(',', $selected_raw))));
    if (count($selected) < 1 || count($selected) > 2 || in_array(0, $selected, true)) return $customer_sql;
    $statuses = merlin_dice_statuses((int)$character['ch_id']);
    $chosen = array();
    $count = 0;
    foreach ($selected as $st_id) {
        if (!isset($statuses[$st_id])) return $customer_sql;
        $chosen[] = $statuses[$st_id]['name'];
        $count += $statuses[$st_id]['value'];
    }
    if ($count < 1 || $count > 100) return $customer_sql;

    $rolls = merlin_dice_roll($count);
    $roll_values = merlin_dice_escape(implode(',', $rolls));
    $stat_label = merlin_dice_escape(implode(' + ', $chosen));
    return $customer_sql." , wr_merlin_dice = '{$roll_values}', wr_merlin_stat = '{$stat_label}' ";
}, 10);

mdl_add_filter('bbs_view_row_extra', function ($html, $row, $ctx) use ($merlin_dice_module_url) {
    $rolls = merlin_dice_parse(isset($row['wr_merlin_dice']) ? $row['wr_merlin_dice'] : '');
    if (!$rolls) return $html;
    $stat_label = isset($row['wr_merlin_stat']) ? (string)$row['wr_merlin_stat'] : '';
    $module_url = $merlin_dice_module_url;
    $view = __DIR__.'/views/row.php';
    ob_start();
    include $view;
    return $html.ob_get_clean();
}, 10);
