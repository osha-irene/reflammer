<?php
if (!defined('_GNUBOARD_')) exit;

require_once __DIR__.'/lib.php';

// 모듈이 활성화된 동안 승인 캐릭터의 HP를 기본값 변경 전에 스냅샷한다.
bbs_duel_hp_seed_approved_characters();

mdl_add_action('bbs_enqueue_assets', function ($bo_table) {
    if (!mdl_board_enabled($bo_table, 'bbs_duel', 'write')) return;
    add_stylesheet('<link rel="stylesheet" href="'.htmlspecialchars(G5_MDL_URL.'/bbs_duel/css/duel.css?v=0.19.1', ENT_QUOTES, 'UTF-8').'">', 100);
}, 10);

function bbs_duel_form_targets($ctx) {
    $character = isset($ctx['character']) && is_array($ctx['character']) ? $ctx['character'] : array();
    $member = isset($GLOBALS['member']) && is_array($GLOBALS['member']) ? $GLOBALS['member'] : array();
    if (empty($character['ch_id']) || empty($member['mb_id'])
        || (int)$character['ch_id'] < 1 || !isset($character['ch_state']) || $character['ch_state'] !== '승인'
        || !isset($character['ch_type']) || $character['ch_type'] !== 'main'
        || !isset($character['mb_id']) || $character['mb_id'] !== $member['mb_id']) return array();
    if (bbs_duel_hp_is_incapacitated('character', (int)$character['ch_id'])) return array();
    return bbs_duel_get_targets((int)$character['ch_id']);
}

function bbs_duel_target_options_html($targets) {
    $options = '<option value="">상대 선택</option>';
    if (!empty($targets['character'])) {
        $options .= '<optgroup label="플레이어 캐릭터">';
        foreach ($targets['character'] as $target) {
            $down = !empty($target['hp']['incapacitated']);
            $options .= '<option value="character:'.(int)$target['id'].'" data-incapacitated="'.($down ? '1' : '0').'">'.htmlspecialchars($target['name'], ENT_QUOTES, 'UTF-8').($down ? ' (전투불능)' : '').'</option>';
        }
        $options .= '</optgroup>';
    }
    if (!empty($targets['enemy'])) {
        $options .= '<optgroup label="에너미">';
        foreach ($targets['enemy'] as $target) {
            $allowed = !empty($target['allowed_stats']) ? implode('|', $target['allowed_stats']) : '';
            $down = !empty($target['hp']['incapacitated']);
            $options .= '<option value="enemy:'.(int)$target['id'].'" data-incapacitated="'.($down ? '1' : '0').'" data-allowed-stats="'.htmlspecialchars($allowed, ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($target['name'], ENT_QUOTES, 'UTF-8').($down ? ' (전투불능)' : '').'</option>';
        }
        $options .= '</optgroup>';
    }
    return $options;
}

function bbs_duel_stat_options_html($stats) {
    $options = '<option value="">판정 스탯 선택</option>';
    foreach (bbs_duel_stat_combinations($stats) as $combo) {
        $token = htmlspecialchars($combo['token'], ENT_QUOTES, 'UTF-8');
        $stat_ids = htmlspecialchars(implode(',', $combo['ids']), ENT_QUOTES, 'UTF-8');
        $options .= '<option value="'.$token.'" data-stat-token="'.$token.'" data-stat-ids="'.$stat_ids.'" data-dice-count="'.max(0, min(100, (int)$combo['value'])).'">'.htmlspecialchars($combo['name'], ENT_QUOTES, 'UTF-8').'</option>';
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
        $trait_context = array(
            'bo_table' => $bo_table,
            'character_id' => isset($character['ch_id']) ? (int)$character['ch_id'] : 0,
            'id_prefix' => $action,
        );
        $trait_form = function_exists('mdl_apply_filters')
            ? mdl_apply_filters('bbs_duel_trait_form', '', $trait_context, $action)
            : '';
        $html .= '<div class="comment-data bbs-duel-action-panel" id="action_'.$action.'">'
            .'<dl><dt>'.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</dt><dd>'
            .'<select name="wr_duel_target" aria-label="'.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'" disabled>'
            .$target_options.'</select>'
            .' <select name="wr_duel_stat_id" aria-label="판정 스탯" disabled>'.$stat_options.'</select> '
            .'<small>양쪽 모두 선택한 스탯으로 멀린의 주사위를 굴립니다.</small>'
            .($action === 'DUEL' ? '<small>결투 결과에 따라 패자의 HP가 감소할 수 있습니다.</small>' : '')
            .'</dd></dl>'
            .$trait_form
            .'</div>';
    }
    $html .= '<script>(function(){var f=document.getElementById("fwrite");if(!f)return;var a=f.querySelector("select[name=action]");if(!a)return;var panels={DUEL_SPAR:f.querySelector("#action_DUEL_SPAR"),DUEL:f.querySelector("#action_DUEL")};function syncPanel(k,p){if(!p)return;var on=a.value===k;var target=p.querySelector("select[name=wr_duel_target]");var stat=p.querySelector("select[name=wr_duel_stat_id]");if(!target||!stat)return;var trait=p.querySelector("select[data-trait-selector]");if(!on){target.value="";stat.value="";if(trait)trait.value="0";}target.disabled=!on;target.required=on;var selected=target.options[target.selectedIndex];if(on&&selected&&selected.getAttribute("data-incapacitated")==="1"){alert("전투불능 상태인 대상은 선택할 수 없습니다.");target.value="";selected=target.options[target.selectedIndex];}var raw=selected?selected.getAttribute("data-allowed-stats"):"";var allowed=raw?raw.split("|"):[];var selectedAllowed=false;Array.prototype.forEach.call(stat.options,function(item){var token=item.getAttribute("data-stat-token");var show=!token||!allowed.length||allowed.indexOf(token)!==-1;item.disabled=!show;item.hidden=!show;if(token&&item.selected&&show)selectedAllowed=true;});if(stat.value&&!selectedAllowed)stat.value="";stat.disabled=!on||!target.value;stat.required=on&&!!target.value;if(!target.value)stat.value="";if(trait){var chosen=stat.options[stat.selectedIndex];var diceCount=chosen?parseInt(chosen.getAttribute("data-dice-count")||"0",10):0;var statIds=chosen?(chosen.getAttribute("data-stat-ids")||"").split(","):[];Array.prototype.forEach.call(trait.options,function(item){if(item.value==="0")return;var unavailable=item.getAttribute("data-unavailable")==="1";var reroll=item.getAttribute("data-effect-type")==="reroll_lowest"&&diceCount<=0;var scopeId=parseInt(item.getAttribute("data-effect-stat-id")||"0",10);var scopeMismatch=scopeId>0&&statIds.indexOf(String(scopeId))===-1;item.disabled=unavailable||reroll||scopeMismatch;if(item.selected&&item.disabled)trait.value="0";});trait.disabled=!on||!target.value||!stat.value;}}function sync(){Object.keys(panels).forEach(function(k){syncPanel(k,panels[k]);});}Object.keys(panels).forEach(function(k){var p=panels[k];if(p){var target=p.querySelector("select[name=wr_duel_target]");var stat=p.querySelector("select[name=wr_duel_stat_id]");if(target)target.addEventListener("change",sync);if(stat)stat.addEventListener("change",sync);}});a.addEventListener("change",sync);sync();})();</script>';
    return $html;
}, 10);

mdl_add_filter('bbs_custom_action', function ($result, $ctx) {
    if (!empty($result['handled'])) return $result;
    $action = isset($result['action']) ? (string)$result['action'] : '';
    if (!in_array($action, array('DUEL_SPAR', 'DUEL'), true)) return $result;
    $bo_table = isset($ctx['bo_table']) ? $ctx['bo_table'] : '';
    if (!mdl_board_enabled_for_w($bo_table, 'bbs_duel', isset($ctx['w']) ? $ctx['w'] : '')) return $result;
    if (isset($ctx['w']) && $ctx['w'] !== '') return $result;
    $write_table = isset($ctx['write_table']) ? (string)$ctx['write_table'] : '';
    if ($write_table === '' || !function_exists('mdl_db_column_exists')
        || !mdl_db_column_exists($write_table, 'wr_duel_trait_description')) {
        bbs_duel_fail('대련·결투 데이터베이스 업데이트가 필요합니다. 대련·결투 모듈을 최신 버전으로 업그레이드한 뒤 다시 작성해 주세요.');
    }

    $character = isset($ctx['character']) && is_array($ctx['character']) ? $ctx['character'] : array();
    $member = isset($ctx['member']) && is_array($ctx['member']) ? $ctx['member'] : array();
    if (empty($character['ch_id']) || empty($member['mb_id']) || !isset($character['ch_state']) || $character['ch_state'] !== '승인'
        || !isset($character['ch_type']) || $character['ch_type'] !== 'main'
        || !isset($character['mb_id']) || $character['mb_id'] !== $member['mb_id']) {
        bbs_duel_fail('승인된 본인 캐릭터로만 대련·결투 글을 작성할 수 있습니다.');
    }
    if (bbs_duel_hp_is_incapacitated('character', (int)$character['ch_id'])) bbs_duel_fail('전투불능 상태에서는 대련·결투를 시작할 수 없습니다.');

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

    if (isset($post['wr_duel_trait_id']) && !is_scalar($post['wr_duel_trait_id'])) {
        bbs_duel_fail('특성 선택값이 올바르지 않습니다.');
    }
    $trait_raw = isset($post['wr_duel_trait_id']) ? (string)$post['wr_duel_trait_id'] : '0';
    if (!preg_match('/^(0|[1-9][0-9]{0,9})$/', $trait_raw)) bbs_duel_fail('특성 선택값이 올바르지 않습니다.');
    $trait_id = (int)$trait_raw;
    $base_dice_count = max(0, min(100, (int)$stat['value']));
    $trait_context = array(
        'bo_table' => (string)$bo_table,
        'character_id' => (int)$character['ch_id'],
        'trait_id' => $trait_id,
        'duel_type' => $action,
        'stat_ids' => $stat['ids'],
        'base_dice_count' => $base_dice_count,
    );
    $trait_state = array(
        'valid' => $trait_id === 0,
        'message' => '특성 모듈이 활성화되어 있지 않습니다.',
        'trait_id' => 0,
        'effect_type' => '',
        'effect_value' => 0,
        'effect_stat_id' => 0,
        'effect_stat_name' => '',
        'period_start' => '',
    );
    if (function_exists('mdl_apply_filters')) {
        $trait_state = mdl_apply_filters('bbs_duel_trait_prepare', $trait_state, $trait_context);
    }
    if (empty($trait_state['valid'])) {
        bbs_duel_fail(isset($trait_state['message']) ? (string)$trait_state['message'] : '선택한 특성을 사용할 수 없습니다.');
    }

    $trait_id = isset($trait_state['trait_id']) ? (int)$trait_state['trait_id'] : 0;
    $trait_name = isset($trait_state['trait_name']) ? (string)$trait_state['trait_name'] : '';
    $trait_icon = isset($trait_state['trait_icon']) ? (string)$trait_state['trait_icon'] : '';
    $trait_description = isset($trait_state['trait_description']) ? (string)$trait_state['trait_description'] : '';
    $trait_effect = isset($trait_state['effect_type']) ? (string)$trait_state['effect_type'] : '';
    $trait_value = isset($trait_state['effect_value']) ? (int)$trait_state['effect_value'] : 0;
    $trait_effect_stat_id = isset($trait_state['effect_stat_id']) ? (int)$trait_state['effect_stat_id'] : 0;
    $trait_effect_stat_name = isset($trait_state['effect_stat_name']) ? (string)$trait_state['effect_stat_name'] : '';
    $trait_period_start = isset($trait_state['period_start']) ? (string)$trait_state['period_start'] : '';
    $actor_dice_modifier = $trait_effect === 'dice_count_modifier' ? $trait_value : 0;
    $actor_score_modifier = $trait_effect === 'score_modifier' ? $trait_value : 0;
    $actor_dice_count = max(0, min(100, $base_dice_count + $actor_dice_modifier));

    $actor_roll = bbs_duel_roll($actor_dice_count);
    $target_roll = bbs_duel_roll($target_count);
    if ($actor_roll === false || $target_roll === false) bbs_duel_fail('멀린의 주사위 기능을 사용할 수 없습니다.');
    $actor_roll_initial = implode(',', $actor_roll);
    $actor_reroll_value = 0;
    $actor_reroll_index = 0;
    if ($trait_effect === 'reroll_lowest' && $actor_roll) {
        $lowest = min($actor_roll);
        $lowest_index = array_search($lowest, $actor_roll, true);
        $reroll = bbs_duel_roll(1);
        if ($reroll === false) bbs_duel_fail('멀린의 주사위 기능을 사용할 수 없습니다.');
        $actor_reroll_value = isset($reroll[0]) ? (int)$reroll[0] : 0;
        $actor_reroll_index = $lowest_index === false ? 0 : (int)$lowest_index + 1;
        if ($lowest_index !== false && isset($reroll[0]) && (int)$reroll[0] > (int)$actor_roll[$lowest_index]) {
            $actor_roll[$lowest_index] = (int)$reroll[0];
        }
    }
    $actor_dice_sum = array_sum($actor_roll);
    $actor_score = max(0, $actor_dice_sum + $actor_score_modifier);
    $target_score = array_sum($target_roll);
    $outcome = ($actor_score > $target_score) ? 'actor_win' : (($actor_score < $target_score) ? 'target_win' : 'draw');
    $duel_type = ($action === 'DUEL') ? 'duel' : 'spar';
    $actor_hp = bbs_duel_hp_ensure_character((int)$character['ch_id']);
    $target_hp = !empty($target['hp']) ? $target['hp'] : array();
    $actor_before = isset($actor_hp['current']) ? (int)$actor_hp['current'] : 0;
    $target_before = isset($target_hp['current']) ? (int)$target_hp['current'] : 0;
    $actor_after = $actor_before;
    $target_after = $target_before;

    $result['handled'] = true;
    $result['customer_sql'] = " , wr_duel_type = '".bbs_duel_escape($duel_type)."'"
        ." , wr_duel_target_type = '".bbs_duel_escape($match[1])."'"
        ." , wr_duel_target_id = '".(int)$target['id']."'"
        ." , wr_duel_target_name = '".bbs_duel_escape($target['name'])."'"
        ." , wr_duel_stat_name = '".bbs_duel_escape($stat['name'])."'"
        ." , wr_duel_stat_ids = '".bbs_duel_escape(implode(',', $stat['ids']))."'"
        ." , wr_duel_actor_name = '".bbs_duel_escape(isset($character['ch_name']) ? $character['ch_name'] : '')."'"
        ." , wr_duel_actor_roll = '".bbs_duel_escape(implode(',', $actor_roll))."'"
        ." , wr_duel_actor_roll_initial = '".bbs_duel_escape($actor_roll_initial)."'"
        ." , wr_duel_actor_reroll_value = '".(int)$actor_reroll_value."'"
        ." , wr_duel_actor_reroll_index = '".(int)$actor_reroll_index."'"
        ." , wr_duel_target_roll = '".bbs_duel_escape(implode(',', $target_roll))."'"
        ." , wr_duel_actor_score = '".(int)$actor_score."'"
        ." , wr_duel_target_score = '".(int)$target_score."'"
        ." , wr_duel_actor_dice_base = '".(int)$base_dice_count."'"
        ." , wr_duel_actor_dice_modifier = '".(int)$actor_dice_modifier."'"
        ." , wr_duel_actor_dice_count = '".(int)$actor_dice_count."'"
        ." , wr_duel_actor_score_modifier = '".(int)$actor_score_modifier."'"
        ." , wr_duel_trait_id = '".(int)$trait_id."'"
        ." , wr_duel_trait_name = '".bbs_duel_escape($trait_name)."'"
        ." , wr_duel_trait_icon = '".bbs_duel_escape($trait_icon)."'"
        ." , wr_duel_trait_description = '".bbs_duel_escape($trait_description)."'"
        ." , wr_duel_trait_effect_type = '".bbs_duel_escape($trait_effect)."'"
        ." , wr_duel_trait_effect_value = '".(int)$trait_value."'"
        ." , wr_duel_trait_effect_stat_id = '".(int)$trait_effect_stat_id."'"
        ." , wr_duel_trait_effect_stat_name = '".bbs_duel_escape($trait_effect_stat_name)."'"
        ." , wr_duel_trait_period_start = '".bbs_duel_escape($trait_period_start)."'"
        ." , wr_duel_result = '".bbs_duel_escape($outcome)."' ";
    $result['customer_sql'] = rtrim($result['customer_sql'])." , wr_duel_actor_ch_id = '".(int)$character['ch_id']."'"
        ." , wr_duel_actor_hp_before = '".$actor_before."' , wr_duel_actor_hp_after = '".$actor_after."'"
        ." , wr_duel_target_hp_before = '".$target_before."' , wr_duel_target_hp_after = '".$target_after."'"
        ." , wr_duel_hp_damage = '0' ";
    return $result;
}, 10);

mdl_add_action('write_after', function ($bo_table, $wr_id, $w) {
    global $g5;
    if ($w !== '' || !mdl_board_enabled($bo_table, 'bbs_duel', 'write')) return;
    $write_table = (isset($g5['write_prefix']) ? $g5['write_prefix'] : G5_TABLE_PREFIX.'write_').$bo_table;
    $wr_id = (int)$wr_id;
    if ($wr_id < 1 || !preg_match('/^[a-zA-Z0-9_]+$/', $write_table)) return;
    $row = sql_fetch("SELECT * FROM `{$write_table}` WHERE wr_id='{$wr_id}' LIMIT 1", false);
    if (empty($row['wr_duel_type']) || !in_array($row['wr_duel_type'], array('duel', 'spar'), true)
        || !in_array($row['wr_duel_result'], array('actor_win', 'target_win', 'draw'), true)) return;
    $source_ref = 'bbs|'.$bo_table.'|'.$wr_id;
    $history = sql_fetch("SELECT history_id FROM `".bbs_duel_history_table()."` WHERE source_ref='".bbs_duel_escape($source_ref)."' LIMIT 1", false);
    if (!empty($history['history_id'])) return;

    $actor_id = (int)$row['wr_duel_actor_ch_id'];
    $target_id = (int)$row['wr_duel_target_id'];
    $target_type = $row['wr_duel_target_type'] === 'enemy' ? 'enemy' : 'character';
    $trait_id = isset($row['wr_duel_trait_id']) ? (int)$row['wr_duel_trait_id'] : 0;
    if ($trait_id > 0) {
        $consume_context = array(
            'character_id' => $actor_id,
            'trait_id' => $trait_id,
            'period_start' => isset($row['wr_duel_trait_period_start']) ? (string)$row['wr_duel_trait_period_start'] : '',
            'source_ref' => $source_ref,
            'bo_table' => $bo_table,
            'post_id' => $wr_id,
        );
        $consume = function_exists('mdl_apply_filters')
            ? mdl_apply_filters('bbs_duel_trait_consume', array('success' => false, 'message' => '특성 모듈을 사용할 수 없어 전투를 저장할 수 없습니다.'), $consume_context)
            : array('success' => false, 'message' => '특성 모듈을 사용할 수 없어 전투를 저장할 수 없습니다.');
        if (empty($consume['success'])) {
            $GLOBALS['bo_table'] = $bo_table;
            $GLOBALS['wr_id'] = $wr_id;
            $GLOBALS['w'] = $w;
            $GLOBALS['write_table'] = $write_table;
            bbs_duel_fail(isset($consume['message']) ? (string)$consume['message'] : '특성 사용 횟수를 저장하지 못했습니다.');
        }
    }

    $actor_before = (int)$row['wr_duel_actor_hp_before'];
    $actor_after = (int)$row['wr_duel_actor_hp_after'];
    $target_before = (int)$row['wr_duel_target_hp_before'];
    $target_after = (int)$row['wr_duel_target_hp_after'];
    $actual_damage = 0;

    if ($row['wr_duel_type'] === 'duel' && $row['wr_duel_result'] !== 'draw') {
        $victim_type = $row['wr_duel_result'] === 'actor_win' ? $target_type : 'character';
        $victim_id = $row['wr_duel_result'] === 'actor_win' ? $target_id : $actor_id;
        if ($target_type === 'enemy' && $row['wr_duel_result'] === 'target_win') {
            $enemy = sql_fetch("SELECT de_damage_to_player FROM `".bbs_duel_enemy_table()."` WHERE de_id='{$target_id}' LIMIT 1", false);
            $base_damage = isset($enemy['de_damage_to_player']) ? (int)$enemy['de_damage_to_player'] : bbs_duel_default_damage();
        } else $base_damage = bbs_duel_default_damage();
        $trait_effect = isset($row['wr_duel_trait_effect_type']) ? (string)$row['wr_duel_trait_effect_type'] : '';
        $trait_value = isset($row['wr_duel_trait_effect_value']) ? (int)$row['wr_duel_trait_effect_value'] : 0;
        $trait_scope_id = isset($row['wr_duel_trait_effect_stat_id']) ? (int)$row['wr_duel_trait_effect_stat_id'] : 0;
        $stat_ids = isset($row['wr_duel_stat_ids']) ? array_map('intval', explode(',', (string)$row['wr_duel_stat_ids'])) : array();
        $trait_scope_matches = $trait_scope_id < 1 || in_array($trait_scope_id, $stat_ids, true);
        if ($row['wr_duel_result'] === 'actor_win' && $trait_effect === 'outgoing_damage_modifier' && $trait_scope_matches) {
            $base_damage += $trait_value;
        } elseif ($row['wr_duel_result'] === 'target_win' && $trait_effect === 'incoming_damage_modifier') {
            $base_damage += $trait_value;
        }
        $base_damage = max(0, min(1000, $base_damage));
        $damage = bbs_duel_damage_adjust($base_damage, array('winner' => $row['wr_duel_result'] === 'actor_win' ? 'player' : ($target_type === 'enemy' ? 'enemy' : 'player'), 'loser' => $victim_type, 'actor_ch_id' => $actor_id, 'target_type' => $target_type, 'target_id' => $target_id));
        $applied = bbs_duel_hp_apply_damage($victim_type, $victim_id, $damage);
        if ($applied) {
            $actual_damage = (int)$applied['damage'];
            if ($row['wr_duel_result'] === 'target_win') {
                $actor_before = (int)$applied['before'];
                $actor_after = (int)$applied['after'];
            } else {
                $target_before = (int)$applied['before'];
                $target_after = (int)$applied['after'];
            }
            sql_query("UPDATE `{$write_table}` SET wr_duel_actor_hp_before='{$actor_before}', wr_duel_actor_hp_after='{$actor_after}', wr_duel_target_hp_before='{$target_before}', wr_duel_target_hp_after='{$target_after}', wr_duel_hp_damage='{$actual_damage}' WHERE wr_id='{$wr_id}' LIMIT 1", false);
        }
    }

    $winner = $row['wr_duel_result'] === 'actor_win' ? (string)$row['wr_duel_actor_name'] : ($row['wr_duel_result'] === 'target_win' ? (string)$row['wr_duel_target_name'] : '');
    $actor_modifier = isset($row['wr_duel_actor_score_modifier']) ? (int)$row['wr_duel_actor_score_modifier'] : 0;
    $actor_dice_sum = array_sum(function_exists('merlin_dice_parse') ? merlin_dice_parse(isset($row['wr_duel_actor_roll']) ? $row['wr_duel_actor_roll'] : '') : array());
    $trait_note = '';
    if ($trait_id > 0) {
        $effect_text = function_exists('bbs_trait_effect_text')
            ? bbs_trait_effect_text(isset($row['wr_duel_trait_effect_type']) ? $row['wr_duel_trait_effect_type'] : '', isset($row['wr_duel_trait_effect_value']) ? $row['wr_duel_trait_effect_value'] : 0, isset($row['wr_duel_trait_effect_stat_name']) ? (string)$row['wr_duel_trait_effect_stat_name'] : '')
            : (string)$row['wr_duel_trait_effect_type'];
        $trait_note = '특성: '.(string)$row['wr_duel_trait_name'].' ('.$effect_text.')';
    }
    if ($actual_damage > 0) $trait_note .= ($trait_note !== '' ? ' · ' : '').'패자 HP 감소';
    bbs_duel_history_insert(array(
        'event_type' => $row['wr_duel_type'], 'event_datetime' => isset($row['wr_datetime']) ? $row['wr_datetime'] : G5_TIME_YMDHIS,
        'actor_name' => (string)$row['wr_duel_actor_name'], 'actor_type' => 'character', 'actor_id' => $actor_id,
        'target_name' => (string)$row['wr_duel_target_name'], 'target_type' => $target_type, 'target_id' => $target_id,
        'stat_name' => (string)$row['wr_duel_stat_name'], 'actor_roll' => (string)$row['wr_duel_actor_roll'],
        'actor_roll_initial' => isset($row['wr_duel_actor_roll_initial']) ? (string)$row['wr_duel_actor_roll_initial'] : (string)$row['wr_duel_actor_roll'],
        'actor_reroll_value' => isset($row['wr_duel_actor_reroll_value']) ? (int)$row['wr_duel_actor_reroll_value'] : 0,
        'actor_reroll_index' => isset($row['wr_duel_actor_reroll_index']) ? (int)$row['wr_duel_actor_reroll_index'] : 0,
        'actor_dice_base' => isset($row['wr_duel_actor_dice_base']) ? (int)$row['wr_duel_actor_dice_base'] : 0,
        'actor_dice_modifier' => isset($row['wr_duel_actor_dice_modifier']) ? (int)$row['wr_duel_actor_dice_modifier'] : 0,
        'actor_dice_count' => isset($row['wr_duel_actor_dice_count']) ? (int)$row['wr_duel_actor_dice_count'] : 0,
        'actor_dice_sum' => $actor_dice_sum, 'actor_modifier' => $actor_modifier, 'actor_score' => (int)$row['wr_duel_actor_score'],
        'target_roll' => (string)$row['wr_duel_target_roll'], 'target_dice_sum' => (int)$row['wr_duel_target_score'],
        'target_score' => (int)$row['wr_duel_target_score'], 'winner_name' => $winner,
        'trait_name' => isset($row['wr_duel_trait_name']) ? (string)$row['wr_duel_trait_name'] : '',
        'trait_icon' => isset($row['wr_duel_trait_icon']) ? (string)$row['wr_duel_trait_icon'] : '',
        'trait_description' => isset($row['wr_duel_trait_description']) ? (string)$row['wr_duel_trait_description'] : '',
        'trait_effect_type' => isset($row['wr_duel_trait_effect_type']) ? (string)$row['wr_duel_trait_effect_type'] : '',
        'trait_effect_value' => isset($row['wr_duel_trait_effect_value']) ? (int)$row['wr_duel_trait_effect_value'] : 0,
        'trait_effect_stat_id' => isset($row['wr_duel_trait_effect_stat_id']) ? (int)$row['wr_duel_trait_effect_stat_id'] : 0,
        'trait_effect_stat_name' => isset($row['wr_duel_trait_effect_stat_name']) ? (string)$row['wr_duel_trait_effect_stat_name'] : '',
        'actor_hp_before' => $actor_before, 'actor_hp_after' => $actor_after,
        'target_hp_before' => $target_before, 'target_hp_after' => $target_after,
        'event_note' => $trait_note, 'source_ref' => $source_ref,
    ));
}, 10);

mdl_add_action('character_create_after', function ($ch_id, $ctx = array()) {
    bbs_duel_hp_ensure_character((int)$ch_id);
}, 10);
mdl_add_action('character_delete_after', function ($ch_id, $ctx = array()) {
    bbs_duel_hp_delete('character', (int)$ch_id);
}, 10);
mdl_add_filter('member_viewer_info', function ($html, $ctx) {
    $character = isset($ctx['ch']) && is_array($ctx['ch']) ? $ctx['ch'] : array();
    $ch_id = isset($character['ch_id']) ? (int)$character['ch_id'] : (isset($ctx['ch_id']) ? (int)$ctx['ch_id'] : 0);
    if ($ch_id < 1 || (isset($character['ch_state']) && $character['ch_state'] !== '승인') || (isset($character['ch_type']) && !in_array($character['ch_type'], array('main', 'sub'), true))) return $html;
    $hp = bbs_duel_hp_ensure_character($ch_id);
    if (!$hp) return $html;
    return $html.'<p class="bbs-duel-profile-hp">HP '.(int)$hp['current'].' / '.(int)$hp['max'].(!empty($hp['incapacitated']) ? ' · 전투불능' : '').'</p>';
}, 10);

mdl_add_filter('bbs_view_row_extra', function ($html, $row, $ctx) {
    $type = isset($row['wr_duel_type']) ? (string)$row['wr_duel_type'] : '';
    if (!in_array($type, array('spar', 'duel'), true)) return $html;
    $view = __DIR__.'/views/row.php';
    ob_start();
    include $view;
    return $html.ob_get_clean();
}, 10);
