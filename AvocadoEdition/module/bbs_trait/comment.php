<?php
if (!defined('_GNUBOARD_')) exit;

function bbs_trait_comment_write_columns() {
    return array(
        'wr_trait_comment_trait_id',
        'wr_trait_comment_character_id',
        'wr_trait_comment_period_start',
        'wr_trait_comment_name',
        'wr_trait_comment_icon',
        'wr_trait_comment_description',
    );
}

function bbs_trait_comment_write_schema_ready($write_table) {
    $write_table = (string)$write_table;
    if (!preg_match('/^[A-Za-z0-9_]+$/', $write_table) || !function_exists('mdl_db_column_exists')) return false;
    foreach (bbs_trait_comment_write_columns() as $column) {
        if (!mdl_db_column_exists($write_table, $column)) return false;
    }
    return true;
}

function bbs_trait_comment_catalog_schema_ready() {
    $tables = bbs_trait_tables();
    return function_exists('mdl_db_column_exists')
        && mdl_db_column_exists($tables['catalog'], 'comment_enabled');
}

function bbs_trait_comment_character($ctx) {
    $ctx = is_array($ctx) ? $ctx : array();
    $character = isset($ctx['character']) && is_array($ctx['character'])
        ? $ctx['character']
        : (isset($GLOBALS['character']) && is_array($GLOBALS['character']) ? $GLOBALS['character'] : array());
    $member = isset($ctx['member']) && is_array($ctx['member'])
        ? $ctx['member']
        : (isset($GLOBALS['member']) && is_array($GLOBALS['member']) ? $GLOBALS['member'] : array());
    if (empty($character['ch_id']) || empty($member['mb_id'])
        || !isset($character['ch_state']) || $character['ch_state'] !== '승인'
        || !isset($character['ch_type']) || !in_array($character['ch_type'], array('main', 'sub'), true)
        || !isset($character['mb_id']) || $character['mb_id'] !== $member['mb_id']) {
        return false;
    }
    return $character;
}

function bbs_trait_comment_is_mmb($ctx) {
    $ctx = is_array($ctx) ? $ctx : array();
    $board = isset($ctx['board']) && is_array($ctx['board'])
        ? $ctx['board']
        : (isset($GLOBALS['board']) && is_array($GLOBALS['board']) ? $GLOBALS['board'] : array());
    $skin = isset($board['bo_skin']) ? trim(str_replace('\\', '/', (string)$board['bo_skin']), '/') : '';
    return $skin === 'mmb' || basename($skin) === 'mmb';
}

function bbs_trait_comment_form_state($ctx) {
    static $cache = array();
    $ctx = is_array($ctx) ? $ctx : array();
    $bo_table = isset($ctx['bo_table']) ? (string)$ctx['bo_table'] : '';
    $w = isset($ctx['w']) ? (string)$ctx['w'] : '';
    $character = bbs_trait_comment_character($ctx);
    $ch_id = is_array($character) ? (int)$character['ch_id'] : 0;
    $key = $bo_table.'|'.$w.'|'.$ch_id;
    if (isset($cache[$key])) return $cache[$key];

    $state = array('traits' => array(), 'error' => '');
    if (!in_array($w, array('', 'c'), true) || !bbs_trait_comment_is_mmb($ctx)
        || !bbs_trait_board_enabled($bo_table, 'comment')
        || !is_array($character)) {
        $cache[$key] = $state;
        return $state;
    }
    if (!bbs_trait_comment_catalog_schema_ready()) {
        $state['error'] = '댓글 특성 데이터베이스 업데이트가 필요합니다. 특성 모듈을 최신 버전으로 업그레이드해 주세요.';
        $cache[$key] = $state;
        return $state;
    }
    $traits = bbs_trait_get_owned($ch_id, true);
    if ($traits === false) {
        $state['error'] = '특성 보유 정보를 불러오지 못했습니다.';
        $cache[$key] = $state;
        return $state;
    }
    foreach ($traits as $trait) {
        if (empty($trait['comment_enabled'])) continue;
        $state['traits'][] = $trait;
    }
    if (!$state['traits']) {
        $cache[$key] = $state;
        return $state;
    }
    $period = bbs_trait_period_state();
    if (!$period) {
        $state['error'] = '특성 초기화 기간 정보를 불러오지 못했습니다.';
        $cache[$key] = $state;
        return $state;
    }
    foreach ($state['traits'] as $index => $trait) {
        $state['traits'][$index]['_usage'] = bbs_trait_usage_state($ch_id, $trait, $period['start']);
    }
    $state['period_start'] = $period['start'];
    $state['character_id'] = $ch_id;
    $cache[$key] = $state;
    return $state;
}

function bbs_trait_comment_action_options($html, $ctx) {
    $state = bbs_trait_comment_form_state($ctx);
    if (empty($state['traits'])) return $html;
    return $html.'<option value="TRAIT_COMMENT">특성 발현</option>';
}

function bbs_trait_comment_form($html, $ctx) {
    $state = bbs_trait_comment_form_state($ctx);
    if ($state['error'] !== '') {
        return $html.'<small class="bbs-trait-error">'.bbs_trait_html($state['error']).'</small>';
    }
    if (empty($state['traits'])) return $html;

    $list_item = isset($ctx['list_item']) && is_array($ctx['list_item']) ? $ctx['list_item'] : array();
    $wr_id = isset($list_item['wr_id']) ? (int)$list_item['wr_id'] : 0;
    $selector_id = 'wr_trait_comment_id_'.$wr_id;
    $options = '';
    foreach ($state['traits'] as $trait) {
        $usage = $trait['_usage'];
        $disabled = !empty($usage['error']) || empty($usage['allowed']);
        $scope_label = bbs_trait_effect_supports_stat_scope($trait['effect_type'])
            ? bbs_trait_effect_scope_label(isset($trait['effect_stat_id']) ? $trait['effect_stat_id'] : 0)
            : '';
        $label = (string)$trait['trait_name'].' — '.bbs_trait_effect_text(
            (string)$trait['effect_type'],
            (int)$trait['effect_value'],
            $scope_label
        );
        if (!empty($usage['error'])) $label .= ' (횟수 확인 오류)';
        elseif (!$usage['allowed']) $label .= ' (횟수 소진)';
        $options .= '<option value="'.(int)$trait['trait_id'].'"'.($disabled ? ' disabled' : '').'>'
            .bbs_trait_html($label).'</option>';
    }

    $html .= '<span class="bbs-trait-comment-select-wrap" hidden>'
        .'<label class="sound_only" for="'.bbs_trait_html($selector_id).'">발현할 특성</label>'
        .'<select name="wr_trait_comment_id" id="'.bbs_trait_html($selector_id).'" class="bbs-trait-comment-select" disabled>'
        .'<option value="">특성 선택</option>'.$options.'</select></span>';
    if ($wr_id > 0) {
        $action_id = 'action_'.$wr_id;
        $html .= '<script>(function(){var action=document.getElementById('.json_encode($action_id).');'
            .'var trait=document.getElementById('.json_encode($selector_id).');'
            .'var form=trait&&trait.form;var wrapper=trait&&trait.parentNode;var anonymous=form&&form.querySelector("input[name=wr_noname]");'
            .'if(!action||!trait)return;var option=null;for(var i=0;i<action.options.length;i++){'
            .'if(action.options[i].value==="TRAIT_COMMENT"){option=action.options[i];break;}}'
            .'function sync(){var isAnonymous=!!(anonymous&&anonymous.checked);if(option)option.disabled=isAnonymous;'
            .'if(isAnonymous&&action.value==="TRAIT_COMMENT")action.value="";'
            .'var active=action.value==="TRAIT_COMMENT"&&!isAnonymous;if(wrapper)wrapper.hidden=!active;trait.disabled=!active;'
            .'trait.required=active;if(!active)trait.value="";}'
            .'if(anonymous)anonymous.addEventListener("change",sync);action.addEventListener("change",sync);sync();})();</script>';
    }
    return $html;
}

function bbs_trait_comment_rollback($ctx) {
    global $g5, $member;
    $script = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', (string)$_SERVER['SCRIPT_NAME']) : '';
    $write_table = isset($ctx['write_table']) ? (string)$ctx['write_table'] : '';
    $bo_table = isset($ctx['bo_table']) ? (string)$ctx['bo_table'] : '';
    $comment_id = isset($ctx['comment_id']) ? (int)$ctx['comment_id'] : (isset($ctx['temp_wr_id']) ? (int)$ctx['temp_wr_id'] : 0);
    $parent_id = isset($ctx['wr_id']) ? (int)$ctx['wr_id'] : 0;
    if (!preg_match('/write_comment_update\.php$/i', $script)
        || !preg_match('/^[A-Za-z0-9_]+$/', $write_table)
        || !preg_match('/^[A-Za-z0-9_]+$/', $bo_table) || $comment_id < 1) {
        error_log('bbs_trait: unable to identify a newly created comment for rollback.');
        return false;
    }
    $row = sql_fetch("SELECT wr_is_comment FROM `{$write_table}` WHERE wr_id='{$comment_id}' LIMIT 1", false);
    if (empty($row['wr_is_comment'])) return false;

    $deleted = sql_query("DELETE FROM `{$write_table}` WHERE wr_id='{$comment_id}' AND wr_is_comment=1 LIMIT 1", false);
    if (!$deleted) {
        error_log('bbs_trait: failed to remove a comment after trait activation failed.');
        return false;
    }
    if (!empty($g5['board_new_table'])) {
        sql_query("DELETE FROM `{$g5['board_new_table']}` WHERE bo_table='".bbs_trait_escape($bo_table)."' AND wr_id='{$comment_id}'", false);
    }
    if (!empty($g5['board_table'])) {
        sql_query("UPDATE `{$g5['board_table']}` SET bo_count_comment=IF(bo_count_comment>0,bo_count_comment-1,0) WHERE bo_table='".bbs_trait_escape($bo_table)."'", false);
    }
    if ($parent_id > 0) {
        sql_query("UPDATE `{$write_table}` SET wr_comment=IF(wr_comment>0,wr_comment-1,0) WHERE wr_id='{$parent_id}'", false);
    }
    if (function_exists('delete_point') && !empty($member['mb_id'])) {
        delete_point($member['mb_id'], $bo_table, $comment_id, '댓글');
    }
    return true;
}

function bbs_trait_comment_fail($message, $ctx) {
    if (isset($ctx['w']) && $ctx['w'] === 'c') bbs_trait_comment_rollback($ctx);
    alert($message);
    exit;
}

function bbs_trait_comment_action($result, $ctx) {
    if (!empty($result['handled']) || !isset($result['action']) || $result['action'] !== 'TRAIT_COMMENT') return $result;
    $w = isset($ctx['w']) ? (string)$ctx['w'] : '';
    if ($w !== 'c') return $result;

    $bo_table = isset($ctx['bo_table']) ? (string)$ctx['bo_table'] : '';
    $write_table = isset($ctx['write_table']) ? (string)$ctx['write_table'] : '';
    if (!bbs_trait_comment_is_mmb($ctx) || !bbs_trait_board_enabled($bo_table, 'comment')) {
        bbs_trait_comment_fail('이 게시판에서는 댓글 특성을 사용할 수 없습니다.', $ctx);
    }
    if (!bbs_trait_comment_catalog_schema_ready() || !bbs_trait_comment_write_schema_ready($write_table)) {
        bbs_trait_comment_fail('댓글 특성 데이터베이스 업데이트가 필요합니다. 특성 모듈을 최신 버전으로 업그레이드한 뒤 다시 작성해 주세요.', $ctx);
    }

    $character = bbs_trait_comment_character($ctx);
    if (!is_array($character)) {
        bbs_trait_comment_fail('승인된 본인 캐릭터로만 댓글 특성을 사용할 수 있습니다.', $ctx);
    }
    $post = isset($ctx['post']) && is_array($ctx['post']) ? $ctx['post'] : array();
    if (isset($post['wr_noname'])) {
        if (!is_scalar($post['wr_noname'])) bbs_trait_comment_fail('익명 설정값이 올바르지 않습니다.', $ctx);
        if ((string)$post['wr_noname'] !== '' && (string)$post['wr_noname'] !== '0') {
            bbs_trait_comment_fail('익명 댓글에서는 특성을 사용할 수 없습니다.', $ctx);
        }
    }
    $trait_raw = isset($post['wr_trait_comment_id']) && is_scalar($post['wr_trait_comment_id'])
        ? (string)$post['wr_trait_comment_id']
        : '';
    if (!preg_match('/^[1-9][0-9]{0,9}$/', $trait_raw)) {
        bbs_trait_comment_fail('발현할 특성을 선택해 주세요.', $ctx);
    }
    $trait_id = (int)$trait_raw;
    $trait = bbs_trait_get($trait_id);
    if (!$trait || empty($trait['comment_enabled'])) {
        bbs_trait_comment_fail('댓글에서 사용할 수 없는 특성입니다.', $ctx);
    }
    $tables = bbs_trait_tables();
    $ch_id = (int)$character['ch_id'];
    $owned = sql_fetch("SELECT ch_id FROM `{$tables['owned']}` WHERE ch_id='{$ch_id}' AND trait_id='{$trait_id}' LIMIT 1", false);
    if (!is_array($owned)) {
        error_log('bbs_trait: failed to verify comment trait ownership.');
        bbs_trait_comment_fail('특성 보유 상태를 확인하지 못했습니다.', $ctx);
    }
    if (empty($owned['ch_id'])) {
        bbs_trait_comment_fail('해당 캐릭터가 보유하지 않은 특성입니다.', $ctx);
    }
    $period = bbs_trait_period_state();
    if (!$period) {
        bbs_trait_comment_fail('특성 초기화 기간 정보를 확인하지 못했습니다.', $ctx);
    }
    $usage = bbs_trait_usage_state($ch_id, $trait, $period['start']);
    if (!empty($usage['error'])) {
        bbs_trait_comment_fail('특성 사용 가능 횟수를 확인하지 못했습니다.', $ctx);
    }
    if (empty($usage['allowed'])) {
        bbs_trait_comment_fail('선택한 특성의 기간당 사용 횟수를 모두 사용했습니다.', $ctx);
    }

    $result['handled'] = true;
    $result['customer_sql'] = " , wr_trait_comment_trait_id='{$trait_id}'"
        ." , wr_trait_comment_character_id='{$ch_id}'"
        ." , wr_trait_comment_period_start='".bbs_trait_escape($period['start'])."'"
        ." , wr_trait_comment_name='".bbs_trait_escape($trait['trait_name'])."'"
        ." , wr_trait_comment_icon='".bbs_trait_escape($trait['trait_icon'])."'"
        ." , wr_trait_comment_description='".bbs_trait_escape($trait['trait_description'])."'";
    return $result;
}

function bbs_trait_comment_after($bo_table, $comment_id, $wr_id, $w) {
    if ($w !== 'c') return;
    $post = isset($_POST) && is_array($_POST) ? $_POST : array();
    if (!isset($post['action']) || !is_scalar($post['action']) || (string)$post['action'] !== 'TRAIT_COMMENT') return;

    $ctx = array(
        'bo_table' => (string)$bo_table,
        'board' => isset($GLOBALS['board']) && is_array($GLOBALS['board']) ? $GLOBALS['board'] : array(),
        'write_table' => isset($GLOBALS['write_table']) ? (string)$GLOBALS['write_table'] : '',
        'comment_id' => (int)$comment_id,
        'wr_id' => (int)$wr_id,
        'w' => 'c',
    );
    if (!bbs_trait_comment_is_mmb($ctx) || !bbs_trait_board_enabled($bo_table, 'comment')
        || !bbs_trait_comment_catalog_schema_ready()
        || !bbs_trait_comment_write_schema_ready($ctx['write_table'])) {
        bbs_trait_comment_fail('댓글 특성 설정 또는 데이터베이스를 확인할 수 없습니다.', $ctx);
    }
    $write_table = $ctx['write_table'];
    $comment = sql_fetch("SELECT wr_trait_comment_trait_id, wr_trait_comment_character_id,
            wr_trait_comment_period_start
        FROM `{$write_table}` WHERE wr_id='".(int)$comment_id."' AND wr_is_comment=1 LIMIT 1", false);
    $trait_id = isset($comment['wr_trait_comment_trait_id']) ? (int)$comment['wr_trait_comment_trait_id'] : 0;
    $character_id = isset($comment['wr_trait_comment_character_id']) ? (int)$comment['wr_trait_comment_character_id'] : 0;
    $period_start = isset($comment['wr_trait_comment_period_start']) ? (string)$comment['wr_trait_comment_period_start'] : '';
    if ($trait_id < 1 || $character_id < 1 || !bbs_trait_parse_datetime($period_start)) {
        bbs_trait_comment_fail('댓글 특성 정보를 저장하지 못했습니다. 다시 시도해 주세요.', $ctx);
    }

    $use_result = bbs_trait_consume_use(array(), array(
        'trait_id' => $trait_id,
        'character_id' => $character_id,
        'period_start' => $period_start,
        'source_ref' => 'comment|'.$bo_table.'|'.(int)$comment_id,
        'bo_table' => (string)$bo_table,
        'post_id' => (int)$comment_id,
    ), 'comment');
    if (empty($use_result['success'])) {
        bbs_trait_comment_fail(isset($use_result['message']) ? (string)$use_result['message'] : '특성 사용 횟수를 기록하지 못했습니다.', $ctx);
    }
}

function bbs_trait_comment_row($html, $row, $ctx) {
    if (!is_array($row) || !is_array($ctx) || !isset($ctx['scope']) || $ctx['scope'] !== 'comment'
        || !empty($row['wr_noname']) || empty($row['wr_trait_comment_trait_id'])) {
        return $html;
    }
    $trait_comment_name = isset($row['wr_trait_comment_name']) ? (string)$row['wr_trait_comment_name'] : '';
    if ($trait_comment_name === '') return $html;
    $trait_comment_icon_url = bbs_trait_icon_url(isset($row['wr_trait_comment_icon']) ? (string)$row['wr_trait_comment_icon'] : '');
    $trait_comment_description = isset($row['wr_trait_comment_description']) ? (string)$row['wr_trait_comment_description'] : '';
    $view = __DIR__.'/views/comment.php';
    if (!is_file($view)) return $html;
    ob_start();
    include $view;
    return $html.ob_get_clean();
}
