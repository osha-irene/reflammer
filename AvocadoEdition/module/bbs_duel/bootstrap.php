<?php
if (!defined('_GNUBOARD_')) exit;

require_once __DIR__.'/lib.php';

mdl_add_action('bbs_enqueue_assets', function ($bo_table) {
    if (!mdl_board_enabled($bo_table, 'bbs_duel', 'write')) return;
    add_stylesheet('<link rel="stylesheet" href="'.htmlspecialchars(G5_MDL_URL.'/bbs_duel/css/duel.css?v=0.14.3', ENT_QUOTES, 'UTF-8').'">', 100);
}, 10);

function bbs_duel_form_targets($ctx) {
    $character = isset($ctx['character']) && is_array($ctx['character']) ? $ctx['character'] : array();
    $member = isset($GLOBALS['member']) && is_array($GLOBALS['member']) ? $GLOBALS['member'] : array();
    if (empty($character['ch_id']) || empty($member['mb_id'])
        || (int)$character['ch_id'] < 1 || !isset($character['ch_state']) || $character['ch_state'] !== '승인'
        || !isset($character['ch_type']) || $character['ch_type'] !== 'main'
        || !isset($character['mb_id']) || $character['mb_id'] !== $member['mb_id']) return array();
    return bbs_duel_get_targets((int)$character['ch_id']);
}

function bbs_duel_target_options_html($targets) {
    $options = '<option value="">상대 선택</option>';
    if (!empty($targets['character'])) {
        $options .= '<optgroup label="플레이어 캐릭터">';
        foreach ($targets['character'] as $target) {
            $options .= '<option value="character:'.(int)$target['id'].'">'.htmlspecialchars($target['name'], ENT_QUOTES, 'UTF-8').'</option>';
        }
        $options .= '</optgroup>';
    }
    if (!empty($targets['enemy'])) {
        $options .= '<optgroup label="에너미">';
        foreach ($targets['enemy'] as $target) {
            $allowed = !empty($target['allowed_stats']) ? implode('|', $target['allowed_stats']) : '';
            $options .= '<option value="enemy:'.(int)$target['id'].'" data-allowed-stats="'.htmlspecialchars($allowed, ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($target['name'], ENT_QUOTES, 'UTF-8').'</option>';
        }
        $options .= '</optgroup>';
    }
    return $options;
}

function bbs_duel_stat_options_html($stats) {
    $options = '<option value="">판정 스탯 선택</option>';
    foreach (bbs_duel_stat_combinations($stats) as $combo) {
        $token = htmlspecialchars($combo['token'], ENT_QUOTES, 'UTF-8');
        $options .= '<option value="'.$token.'" data-stat-token="'.$token.'">'.htmlspecialchars($combo['name'], ENT_QUOTES, 'UTF-8').'</option>';
    }
    return $options;
}

mdl_add_filter('bbs_write_form_action_options', function ($html, $ctx) {
    $bo_table = isset($ctx['bo_table']) ? $ctx['bo_table'] : '';
    $w = isset($ctx['w']) ? $ctx['w'] : '';
    if (!mdl_board_enabled($bo_table, 'bbs_duel', 'write') || $w !== '') return $html;
    $targets = bbs_duel_form_targets($ctx);
    $character = isset($ctx['character']) && is_array($ctx['character']) ? $ctx['character'] : array();
    $stats = bbs_duel_get_character_stats(isset($character['ch_id']) ? (int)$character['ch_id'] : 0);
    if ((!$targets['character'] && !$targets['enemy']) || !$stats) return $html;
    return $html.'<option value="DUEL_SPAR">대련</option><option value="DUEL">결투</option>';
}, 10);

mdl_add_filter('bbs_write_form_action_panels', function ($html, $ctx) {
    $bo_table = isset($ctx['bo_table']) ? $ctx['bo_table'] : '';
    $w = isset($ctx['w']) ? $ctx['w'] : '';
    if (!mdl_board_enabled($bo_table, 'bbs_duel', 'write') || $w !== '') return $html;
    $targets = bbs_duel_form_targets($ctx);
    $character = isset($ctx['character']) && is_array($ctx['character']) ? $ctx['character'] : array();
    $stats = bbs_duel_get_character_stats(isset($character['ch_id']) ? (int)$character['ch_id'] : 0);
    if ((!$targets['character'] && !$targets['enemy']) || !$stats) return $html;
    $target_options = bbs_duel_target_options_html($targets);
    $stat_options = bbs_duel_stat_options_html($stats);
    foreach (array('DUEL_SPAR' => '대련 상대', 'DUEL' => '결투 상대') as $action => $label) {
        $html .= '<div class="comment-data bbs-duel-action-panel" id="action_'.$action.'">'
            .'<dl><dt>'.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</dt><dd>'
            .'<select name="wr_duel_target" aria-label="'.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'" disabled>'
            .$target_options.'</select>'
            .' <select name="wr_duel_stat_id" aria-label="판정 스탯" disabled>'.$stat_options.'</select> '
            .'<small>양쪽 모두 선택한 스탯으로 멀린의 주사위를 굴립니다.</small>'
            .($action === 'DUEL' ? '<small>결투 결과에 따라 패자의 HP가 감소할 수 있습니다.</small>' : '')
            .'</dd></dl></div>';
    }
    $html .= '<script>(function(){var f=document.getElementById("fwrite");if(!f)return;var a=f.querySelector("select[name=action]");if(!a)return;var panels={DUEL_SPAR:f.querySelector("#action_DUEL_SPAR"),DUEL:f.querySelector("#action_DUEL")};function syncPanel(k,p){if(!p)return;var on=a.value===k;var target=p.querySelector("select[name=wr_duel_target]");var stat=p.querySelector("select[name=wr_duel_stat_id]");if(!target||!stat)return;if(!on){target.value="";stat.value="";}target.disabled=!on;target.required=on;var selected=target.options[target.selectedIndex];var raw=selected?selected.getAttribute("data-allowed-stats"):"";var allowed=raw?raw.split("|"):[];var selectedAllowed=false;Array.prototype.forEach.call(stat.options,function(item){var token=item.getAttribute("data-stat-token");var show=!token||!allowed.length||allowed.indexOf(token)!==-1;item.disabled=!show;item.hidden=!show;if(token&&item.selected&&show)selectedAllowed=true;});if(stat.value&&!selectedAllowed)stat.value="";stat.disabled=!on||!target.value;stat.required=on&&!!target.value;if(!target.value)stat.value="";}function sync(){Object.keys(panels).forEach(function(k){syncPanel(k,panels[k]);});}Object.keys(panels).forEach(function(k){var p=panels[k];if(p){var target=p.querySelector("select[name=wr_duel_target]");if(target)target.addEventListener("change",sync);}});a.addEventListener("change",sync);sync();})();</script>';
    return $html;
}, 10);

mdl_add_filter('bbs_custom_action', function ($result, $ctx) {
    if (!empty($result['handled'])) return $result;
    $action = isset($result['action']) ? (string)$result['action'] : '';
    if (!in_array($action, array('DUEL_SPAR', 'DUEL'), true)) return $result;
    $bo_table = isset($ctx['bo_table']) ? $ctx['bo_table'] : '';
    if (!mdl_board_enabled_for_w($bo_table, 'bbs_duel', isset($ctx['w']) ? $ctx['w'] : '')) return $result;
    if (isset($ctx['w']) && $ctx['w'] !== '') return $result;

    $character = isset($ctx['character']) && is_array($ctx['character']) ? $ctx['character'] : array();
    $member = isset($ctx['member']) && is_array($ctx['member']) ? $ctx['member'] : array();
    if (empty($character['ch_id']) || empty($member['mb_id']) || !isset($character['ch_state']) || $character['ch_state'] !== '승인'
        || !isset($character['ch_type']) || $character['ch_type'] !== 'main'
        || !isset($character['mb_id']) || $character['mb_id'] !== $member['mb_id']) {
        bbs_duel_fail('승인된 본인 캐릭터로만 대련·결투 글을 작성할 수 있습니다.');
    }

    $post = isset($ctx['post']) && is_array($ctx['post']) ? $ctx['post'] : array();
    $actor_stats = bbs_duel_get_character_stats((int)$character['ch_id']);
    $stat_token = isset($post['wr_duel_stat_id']) && is_scalar($post['wr_duel_stat_id']) ? (string)$post['wr_duel_stat_id'] : '';
    $actor_combinations = bbs_duel_stat_combinations($actor_stats);
    if (!isset($actor_combinations[$stat_token])) {
        bbs_duel_fail('판정에 사용할 스탯을 선택해 주세요.');
    }
    $stat = $actor_combinations[$stat_token];

    $raw_target = isset($post['wr_duel_target']) ? (string)$post['wr_duel_target'] : '';
    if (!preg_match('/^(character|enemy):([1-9][0-9]*)$/', $raw_target, $match)) {
        bbs_duel_fail('대련·결투 상대를 선택해 주세요.');
    }
    $target = bbs_duel_validate_target($match[1], (int)$match[2], (int)$character['ch_id']);
    if (!$target) bbs_duel_fail('선택한 대상을 찾을 수 없거나 더 이상 대련·결투할 수 없습니다.');

    $target_count = bbs_duel_get_target_stat_value($target, $stat);
    if ($target_count === null) bbs_duel_fail('상대의 해당 스탯을 판정에 사용할 수 없습니다.');

    $actor_roll = bbs_duel_roll($stat['value']);
    $target_roll = bbs_duel_roll($target_count);
    if (!$actor_roll || !$target_roll) bbs_duel_fail('멀린의 주사위 기능을 사용할 수 없습니다.');
    $actor_score = array_sum($actor_roll);
    $target_score = array_sum($target_roll);
    $outcome = ($actor_score > $target_score) ? 'actor_win' : (($actor_score < $target_score) ? 'target_win' : 'draw');
    $duel_type = ($action === 'DUEL') ? 'duel' : 'spar';

    $result['handled'] = true;
    $result['customer_sql'] = " , wr_duel_type = '".bbs_duel_escape($duel_type)."'"
        ." , wr_duel_target_type = '".bbs_duel_escape($match[1])."'"
        ." , wr_duel_target_id = '".(int)$target['id']."'"
        ." , wr_duel_target_name = '".bbs_duel_escape($target['name'])."'"
        ." , wr_duel_stat_name = '".bbs_duel_escape($stat['name'])."'"
        ." , wr_duel_actor_name = '".bbs_duel_escape(isset($character['ch_name']) ? $character['ch_name'] : '')."'"
        ." , wr_duel_actor_roll = '".bbs_duel_escape(implode(',', $actor_roll))."'"
        ." , wr_duel_target_roll = '".bbs_duel_escape(implode(',', $target_roll))."'"
        ." , wr_duel_actor_score = '".(int)$actor_score."'"
        ." , wr_duel_target_score = '".(int)$target_score."'"
        ." , wr_duel_result = '".bbs_duel_escape($outcome)."' ";
    return $result;
}, 10);

mdl_add_filter('bbs_view_row_extra', function ($html, $row, $ctx) {
    $type = isset($row['wr_duel_type']) ? (string)$row['wr_duel_type'] : '';
    if (!in_array($type, array('spar', 'duel'), true)) return $html;
    $view = __DIR__.'/views/row.php';
    ob_start();
    include $view;
    return $html.ob_get_clean();
}, 10);
