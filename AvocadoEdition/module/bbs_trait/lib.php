<?php
if (!defined('_GNUBOARD_')) exit;

function bbs_trait_tables() {
    global $g5;
    $prefix = defined('G5_TABLE_PREFIX') ? G5_TABLE_PREFIX.'module_bbs_trait_' : 'g5_module_bbs_trait_';
    if (empty($g5['module_bbs_trait_catalog_table'])) $g5['module_bbs_trait_catalog_table'] = $prefix.'catalog';
    if (empty($g5['module_bbs_trait_owned_table'])) $g5['module_bbs_trait_owned_table'] = $prefix.'owned';
    if (empty($g5['module_bbs_trait_use_table'])) $g5['module_bbs_trait_use_table'] = $prefix.'use';
    if (empty($g5['module_bbs_trait_slots_table'])) $g5['module_bbs_trait_slots_table'] = $prefix.'slots';
    if (empty($g5['module_bbs_trait_item_use_table'])) $g5['module_bbs_trait_item_use_table'] = $prefix.'item_use';
    return array(
        'catalog' => $g5['module_bbs_trait_catalog_table'],
        'owned' => $g5['module_bbs_trait_owned_table'],
        'use' => $g5['module_bbs_trait_use_table'],
        'slots' => $g5['module_bbs_trait_slots_table'],
        'item_use' => $g5['module_bbs_trait_item_use_table'],
    );
}

function bbs_trait_escape($value) {
    return sql_real_escape_string((string)$value);
}

function bbs_trait_html($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function bbs_trait_text_length($value) {
    $value = (string)$value;
    if (function_exists('mb_strlen')) return mb_strlen($value, 'UTF-8');
    $count = preg_match_all('/./us', $value, $unused);
    return $count === false ? strlen($value) : $count;
}

function bbs_trait_valid_external_icon_url($url) {
    $url = trim((string)$url);
    if ($url === '' || strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false) return false;
    $parts = parse_url($url);
    return is_array($parts)
        && isset($parts['scheme'], $parts['host'])
        && strtolower($parts['scheme']) === 'https'
        && $parts['host'] !== ''
        && !isset($parts['user'])
        && !isset($parts['pass']);
}

function bbs_trait_effects() {
    return array(
        'score_modifier' => '최종 점수 보정',
        'dice_count_modifier' => '주사위 수 보정',
        'reroll_lowest' => '가장 낮은 주사위 재굴림',
        'incoming_damage_modifier' => '받는 피해 보정',
        'outgoing_damage_modifier' => '가하는 피해 보정',
    );
}

function bbs_trait_effect_supports_stat_scope($type) {
    return in_array($type, array('score_modifier', 'dice_count_modifier', 'reroll_lowest', 'outgoing_damage_modifier'), true);
}

function bbs_trait_status_definitions() {
    static $definitions = null;
    if ($definitions !== null) return $definitions;
    if (function_exists('bbs_duel_status_definitions')) {
        $definitions = bbs_duel_status_definitions();
        return $definitions;
    }
    global $g5;
    $definitions = array();
    if (empty($g5['status_config_table'])) return $definitions;
    $result = sql_query("SELECT st_id, st_name FROM `{$g5['status_config_table']}` ORDER BY st_order ASC, st_id ASC", false);
    if (!$result) {
        error_log('bbs_trait: failed to load dynamic status definitions.');
        return $definitions;
    }
    while ($row = sql_fetch_array($result)) {
        $id = (int)$row['st_id'];
        if ($id > 0) $definitions[$id] = array('id' => $id, 'name' => (string)$row['st_name']);
    }
    return $definitions;
}

function bbs_trait_effect_scope_label($stat_id) {
    $stat_id = (int)$stat_id;
    if ($stat_id < 1) return '스탯 제한 없음';
    $definitions = bbs_trait_status_definitions();
    if (isset($definitions[$stat_id])) return (string)$definitions[$stat_id]['name'].' 판정';
    return '삭제된 스탯 (ID '.$stat_id.')';
}

function bbs_trait_effect_text($type, $value, $scope_label = '') {
    $effects = bbs_trait_effects();
    if (!isset($effects[$type])) return '알 수 없는 효과';
    if ($type === 'reroll_lowest') {
        $text = $effects[$type];
    } else {
        $value = (int)$value;
        $sign = $value > 0 ? '+' : '';
        $text = $effects[$type].' '.$sign.$value;
    }
    return $scope_label !== '' ? $text.' · '.$scope_label : $text;
}

function bbs_trait_now() {
    return new DateTimeImmutable('now', new DateTimeZone('Asia/Seoul'));
}

function bbs_trait_parse_datetime($value) {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) return false;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('Asia/Seoul'));
    return $date instanceof DateTimeImmutable ? $date : false;
}

function bbs_trait_period_end_for($start, $mode, $hours) {
    if ($mode === 'hour') {
        $hours = max(1, min(168, (int)$hours));
        if ($hours > 24) return $start->modify('+'.$hours.' hours');
        $midnight = $start->setTime(0, 0, 0);
        $step = $hours * 3600;
        $elapsed = $start->getTimestamp() - $midnight->getTimestamp();
        $next_hours = ((int)floor($elapsed / $step) + 1) * $hours;
        $next = $midnight->modify('+'.$next_hours.' hours');
        $day_end = $midnight->modify('+1 day');
        return $next > $day_end ? $day_end : $next;
    }
    if ($mode === 'daily') return $start->setTime(0, 0, 0)->modify('+1 day');
    if ($mode === 'weekly') return $start->modify('next monday')->setTime(0, 0, 0);
    if ($mode === 'monthly') return $start->modify('first day of next month')->setTime(0, 0, 0);
    return $start->modify('+7 days');
}

function bbs_trait_advance_period($start, $mode, $hours, $now) {
    $first_end = bbs_trait_period_end_for($start, $mode, $hours);
    if ($now < $first_end) return array($start, $first_end);

    if ($mode === 'hour' && $hours > 24) {
        $step = $hours * 3600;
        $elapsed = $now->getTimestamp() - $start->getTimestamp();
        $periods = max(0, (int)floor($elapsed / $step));
        $current_start = $start->modify('+'.($periods * $hours).' hours');
        return array($current_start, $current_start->modify('+'.$hours.' hours'));
    }
    if ($mode === 'hour') {
        $midnight = $now->setTime(0, 0, 0);
        $step = $hours * 3600;
        $elapsed = $now->getTimestamp() - $midnight->getTimestamp();
        $start_hours = (int)floor($elapsed / $step) * $hours;
        $current_start = $midnight->modify('+'.$start_hours.' hours');
        return array($current_start, bbs_trait_period_end_for($current_start, $mode, $hours));
    }
    if ($mode === 'daily') {
        $current_start = $now->setTime(0, 0, 0);
        return array($current_start, $current_start->modify('+1 day'));
    }
    if ($mode === 'monthly') {
        $current_start = $now->modify('first day of this month')->setTime(0, 0, 0);
        return array($current_start, $current_start->modify('first day of next month')->setTime(0, 0, 0));
    }
    $current_start = $now->modify('monday this week')->setTime(0, 0, 0);
    return array($current_start, $current_start->modify('next monday')->setTime(0, 0, 0));
}

function bbs_trait_period_state() {
    static $state = null;
    if ($state !== null) return $state;

    $now = bbs_trait_now();
    $mode = (string)mdl_setting_get('bbs_trait', 'active_period_mode', 'weekly');
    $hours = max(1, min(168, (int)mdl_setting_get('bbs_trait', 'active_period_hours', 1)));
    if (!in_array($mode, array('hour', 'daily', 'weekly', 'monthly'), true)) $mode = 'weekly';

    $start = bbs_trait_parse_datetime((string)mdl_setting_get('bbs_trait', 'period_start', ''));
    $end = bbs_trait_parse_datetime((string)mdl_setting_get('bbs_trait', 'period_end', ''));
    if (!$start || !$end || $end <= $start) {
        if ($mode === 'hour' && $hours > 24) {
            $start = $now;
        } elseif ($mode === 'hour') {
            $midnight = $now->setTime(0, 0, 0);
            $step = $hours * 3600;
            $elapsed = $now->getTimestamp() - $midnight->getTimestamp();
            $start_hours = (int)floor($elapsed / $step) * $hours;
            $start = $midnight->modify('+'.$start_hours.' hours');
        } elseif ($mode === 'daily') {
            $start = $now->setTime(0, 0, 0);
        } elseif ($mode === 'monthly') {
            $start = $now->modify('first day of this month')->setTime(0, 0, 0);
        } else {
            $start = $now->modify('monday this week')->setTime(0, 0, 0);
        }
        $end = bbs_trait_period_end_for($start, $mode, $hours);
        if (!bbs_trait_store_period($start, $end)) return false;
    }

    if ($now >= $end) {
        $start = $end;
        $pending_mode = (string)mdl_setting_get('bbs_trait', 'pending_period_mode', '');
        $pending_hours = (int)mdl_setting_get('bbs_trait', 'pending_period_hours', 0);
        if (in_array($pending_mode, array('hour', 'daily', 'weekly', 'monthly'), true)) {
            $mode = $pending_mode;
            $hours = max(1, min(168, $pending_hours));
            $mode_saved = mdl_setting_set('bbs_trait', 'active_period_mode', $mode);
            $hours_saved = mdl_setting_set('bbs_trait', 'active_period_hours', $hours);
            $mode_deleted = mdl_setting_delete('bbs_trait', 'pending_period_mode');
            $hours_deleted = mdl_setting_delete('bbs_trait', 'pending_period_hours');
            if (!$mode_saved || !$hours_saved || !$mode_deleted || !$hours_deleted) {
                error_log('bbs_trait: failed to apply a pending usage period setting.');
                return false;
            }
        }
        $advanced = bbs_trait_advance_period($start, $mode, $hours, $now);
        $start = $advanced[0];
        $end = $advanced[1];
        if (!bbs_trait_store_period($start, $end)) return false;
    }

    $state = array(
        'mode' => $mode,
        'hours' => $hours,
        'start' => $start->format('Y-m-d H:i:s'),
        'end' => $end->format('Y-m-d H:i:s'),
    );
    return $state;
}

function bbs_trait_period_label($mode, $hours) {
    $labels = array(
        'daily' => '매일 00:00',
        'weekly' => '매주 월요일 00:00',
        'monthly' => '매월 1일 00:00',
    );
    if ($mode === 'hour') return '매 '.(int)$hours.'시간 (KST)';
    return isset($labels[$mode]) ? $labels[$mode] : $labels['weekly'];
}

function bbs_trait_store_period($start, $end) {
    $start_saved = mdl_setting_set('bbs_trait', 'period_start', $start->format('Y-m-d H:i:s'));
    $end_saved = mdl_setting_set('bbs_trait', 'period_end', $end->format('Y-m-d H:i:s'));
    if (!$start_saved || !$end_saved) {
        error_log('bbs_trait: failed to persist the current usage period.');
        return false;
    }
    return true;
}

function bbs_trait_usage_count($ch_id, $trait_id, $period_start) {
    $tables = bbs_trait_tables();
    $ch_id = (int)$ch_id;
    $trait_id = (int)$trait_id;
    $period_start = bbs_trait_escape($period_start);
    $row = sql_fetch("SELECT COUNT(*) AS used_count FROM `{$tables['use']}`
        WHERE ch_id='{$ch_id}' AND trait_id='{$trait_id}' AND period_start='{$period_start}'", false);
    return isset($row['used_count']) ? (int)$row['used_count'] : -1;
}

function bbs_trait_usage_state($ch_id, $trait, $period_start = null) {
    if ($period_start === null) {
        $period = bbs_trait_period_state();
        if (!$period) return array('used' => 0, 'limit' => null, 'remaining' => 0, 'allowed' => false, 'error' => true);
        $period_start = $period['start'];
    }
    $used = bbs_trait_usage_count($ch_id, (int)$trait['trait_id'], $period_start);
    if ($used < 0) {
        return array('used' => 0, 'limit' => null, 'remaining' => 0, 'allowed' => false, 'error' => true);
    }
    $limit = isset($trait['usage_limit']) && $trait['usage_limit'] !== null ? (int)$trait['usage_limit'] : null;
    return array(
        'used' => $used,
        'limit' => $limit,
        'remaining' => $limit === null ? null : max(0, $limit - $used),
        'allowed' => $limit === null || ($limit > 0 && $used < $limit),
    );
}

function bbs_trait_get($trait_id, $include_disabled = false) {
    $tables = bbs_trait_tables();
    $trait_id = (int)$trait_id;
    if ($trait_id < 1) return null;
    $enabled = $include_disabled ? '' : ' AND trait_enabled=1';
    $row = sql_fetch("SELECT * FROM `{$tables['catalog']}` WHERE trait_id='{$trait_id}'{$enabled} LIMIT 1", false);
    return !empty($row['trait_id']) ? $row : null;
}

function bbs_trait_get_owned($ch_id, $enabled_only = false) {
    $tables = bbs_trait_tables();
    $ch_id = (int)$ch_id;
    if ($ch_id < 1) return array();
    $enabled = $enabled_only ? ' AND t.trait_enabled=1' : '';
    $result = sql_query("SELECT t.*, o.granted_at, o.granted_by
        FROM `{$tables['owned']}` AS o
        INNER JOIN `{$tables['catalog']}` AS t ON t.trait_id=o.trait_id
        WHERE o.ch_id='{$ch_id}'{$enabled}
        ORDER BY t.trait_name, t.trait_id", false);
    $traits = array();
    if (!$result) {
        error_log('bbs_trait: failed to load character-owned traits.');
        return false;
    }
    while ($row = sql_fetch_array($result)) $traits[] = $row;
    return $traits;
}

function bbs_trait_owned_count($ch_id) {
    $tables = bbs_trait_tables();
    $ch_id = (int)$ch_id;
    $row = sql_fetch("SELECT COUNT(*) AS owned_count FROM `{$tables['owned']}` WHERE ch_id='{$ch_id}'", false);
    return isset($row['owned_count']) ? (int)$row['owned_count'] : -1;
}

function bbs_trait_max_slots() {
    return max(0, min(100, (int)mdl_setting_get('bbs_trait', 'slot_count', 5)));
}

function bbs_trait_base_slots() {
    return min(bbs_trait_max_slots(), max(0, min(100, (int)mdl_setting_get('bbs_trait', 'base_slot_count', 3))));
}

function bbs_trait_open_slots($ch_id) {
    $ch_id = (int)$ch_id;
    if ($ch_id < 1) return -1;
    $tables = bbs_trait_tables();
    $result = sql_query("SELECT slots_unlocked FROM `{$tables['slots']}` WHERE ch_id='{$ch_id}' LIMIT 1", false);
    if (!$result) {
        error_log('bbs_trait: failed to load character slot unlocks.');
        return -1;
    }
    $row = sql_fetch_array($result);
    $unlocked = !empty($row['slots_unlocked']) ? (int)$row['slots_unlocked'] : 0;
    return min(bbs_trait_max_slots(), bbs_trait_base_slots() + max(0, $unlocked));
}

function bbs_trait_slot_item_type() {
    return '특성 슬롯 확장';
}

function bbs_trait_inventory_use_response($result, $return_url, $message) {
    $result['handled'] = true;
    $result['echo'] = 'LOCATIONURL||||'.$return_url.'&amp;tabs=i&amp;msg='.urlencode($message);
    return $result;
}

function bbs_trait_use_slot_item($result, $ctx) {
    if (!empty($result['handled'])) return $result;
    $item = isset($ctx['in']) && is_array($ctx['in']) ? $ctx['in'] : array();
    if (!isset($item['it_type']) || (string)$item['it_type'] !== bbs_trait_slot_item_type()) return $result;

    $in_id = isset($ctx['in_id']) ? (int)$ctx['in_id'] : 0;
    $ch_id = isset($item['ch_id']) ? (int)$item['ch_id'] : 0;
    $character = isset($ctx['character']) && is_array($ctx['character']) ? $ctx['character'] : array();
    $return_url = isset($ctx['return_url']) ? (string)$ctx['return_url'] : '';
    if ($in_id < 1 || $ch_id < 1 || empty($character['ch_id']) || (int)$character['ch_id'] !== $ch_id
        || (isset($character['ch_state']) && $character['ch_state'] !== '승인')
        || (isset($character['ch_type']) && !in_array($character['ch_type'], array('main', 'sub'), true))) {
        return bbs_trait_inventory_use_response($result, $return_url, '아이템을 사용할 캐릭터를 확인할 수 없습니다.');
    }

    global $g5, $member, $is_admin;
    $is_owner = isset($member['mb_id'], $character['mb_id']) && (string)$member['mb_id'] === (string)$character['mb_id'];
    if (!$is_owner && empty($is_admin)) {
        return bbs_trait_inventory_use_response($result, $return_url, '해당 캐릭터의 아이템을 사용할 권한이 없습니다.');
    }

    $inventory_table = isset($g5['inventory_table']) ? $g5['inventory_table'] : '';
    $item_table = isset($g5['item_table']) ? $g5['item_table'] : '';
    if ($inventory_table === '' || $item_table === '') {
        error_log('bbs_trait: inventory table is unavailable.');
        return bbs_trait_inventory_use_response($result, $return_url, '아이템 사용 정보를 불러오지 못했습니다.');
    }

    if (!sql_query('START TRANSACTION', false)) {
        error_log('bbs_trait: failed to start slot unlock transaction.');
        return bbs_trait_inventory_use_response($result, $return_url, '슬롯 확장 처리를 시작하지 못했습니다.');
    }

    $locked_item = sql_fetch("SELECT i.*, t.it_type, t.it_use, t.it_use_able, t.it_use_ever
        FROM `{$inventory_table}` AS i
        INNER JOIN `{$item_table}` AS t ON t.it_id=i.it_id
        WHERE i.in_id='{$in_id}' AND i.ch_id='{$ch_id}'
        LIMIT 1 FOR UPDATE", false);
    if (empty($locked_item['in_id']) || (string)$locked_item['it_type'] !== bbs_trait_slot_item_type()) {
        sql_query('ROLLBACK', false);
        return bbs_trait_inventory_use_response($result, $return_url, '슬롯 확장 아이템 보유 정보를 확인할 수 없습니다.');
    }
    if ($locked_item['it_use'] !== 'Y' || empty($locked_item['it_use_able'])) {
        sql_query('ROLLBACK', false);
        return bbs_trait_inventory_use_response($result, $return_url, '아이템 관리자 설정에서 사용 여부와 인벤 사용 가능을 확인해 주세요.');
    }
    if (!empty($locked_item['it_use_ever'])) {
        sql_query('ROLLBACK', false);
        return bbs_trait_inventory_use_response($result, $return_url, '슬롯 확장 아이템은 1회용으로 설정해야 사용할 수 있습니다.');
    }

    $tables = bbs_trait_tables();
    if (!sql_query("INSERT IGNORE INTO `{$tables['slots']}` (ch_id, slots_unlocked) VALUES ('{$ch_id}', 0)", false)) {
        sql_query('ROLLBACK', false);
        error_log('bbs_trait: failed to initialize character slot unlocks.');
        return bbs_trait_inventory_use_response($result, $return_url, '슬롯 확장 정보를 저장하지 못했습니다.');
    }
    $slot_row = sql_fetch("SELECT slots_unlocked FROM `{$tables['slots']}` WHERE ch_id='{$ch_id}' LIMIT 1 FOR UPDATE", false);
    if (!isset($slot_row['slots_unlocked'])) {
        sql_query('ROLLBACK', false);
        error_log('bbs_trait: failed to lock character slot unlocks.');
        return bbs_trait_inventory_use_response($result, $return_url, '슬롯 확장 정보를 확인하지 못했습니다.');
    }

    $max_slots = bbs_trait_max_slots();
    $open_slots = min($max_slots, bbs_trait_base_slots() + max(0, (int)$slot_row['slots_unlocked']));
    if ($open_slots >= $max_slots) {
        sql_query('ROLLBACK', false);
        return bbs_trait_inventory_use_response($result, $return_url, '캐릭터의 특성 슬롯이 이미 최대치입니다. 아이템은 소모되지 않았습니다.');
    }

    $updated = sql_query("UPDATE `{$tables['slots']}` SET slots_unlocked=slots_unlocked+1 WHERE ch_id='{$ch_id}'", false);
    $deleted = $updated && sql_query("DELETE FROM `{$inventory_table}` WHERE in_id='{$in_id}' AND ch_id='{$ch_id}' LIMIT 1", false);
    if (!$deleted || (function_exists('sql_affected_rows') && (int)sql_affected_rows() !== 1)) {
        sql_query('ROLLBACK', false);
        error_log('bbs_trait: failed to consume slot unlock item.');
        return bbs_trait_inventory_use_response($result, $return_url, '슬롯 확장 아이템을 처리하지 못했습니다.');
    }
    if (!sql_query('COMMIT', false)) {
        sql_query('ROLLBACK', false);
        error_log('bbs_trait: failed to commit slot unlock transaction.');
        return bbs_trait_inventory_use_response($result, $return_url, '슬롯 확장 처리를 완료하지 못했습니다.');
    }

    if (function_exists('mdl_do_action')) {
        mdl_do_action('inventory_delete_after', $in_id, 0, $locked_item);
        mdl_do_action('inventory_use_after', $in_id, $ch_id, bbs_trait_slot_item_type());
    }
    return bbs_trait_inventory_use_response($result, $return_url, '특성 슬롯을 1개 확장했습니다. ('.$open_slots.' → '.($open_slots + 1).')');
}

function bbs_trait_icon_url($icon) {
    if ($icon === '') return '';
    if (bbs_trait_valid_external_icon_url($icon)) return $icon;
    return G5_DATA_URL.'/bbs_trait/'.rawurlencode(basename($icon));
}

function bbs_trait_upload_icon($file, &$error = '') {
    $error = '';
    if (!is_array($file) || !isset($file['error']) || (int)$file['error'] === UPLOAD_ERR_NO_FILE) return '';
    if ((int)$file['error'] !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        $error = '아이콘 업로드에 실패했습니다.';
        return false;
    }
    if (!isset($file['size']) || (int)$file['size'] < 1 || (int)$file['size'] > 2 * 1024 * 1024) {
        $error = '아이콘은 2MB 이하의 이미지 파일이어야 합니다.';
        return false;
    }
    $image = @getimagesize($file['tmp_name']);
    $mime = is_array($image) && isset($image['mime']) ? $image['mime'] : '';
    $extensions = array(
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    );
    if (!isset($extensions[$mime])) {
        $error = 'JPG, PNG, GIF, WEBP 이미지만 업로드할 수 있습니다.';
        return false;
    }
    $directory = G5_DATA_PATH.'/bbs_trait';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        $error = '아이콘 저장 폴더를 만들지 못했습니다.';
        return false;
    }
    $filename = md5(uniqid((string)mt_rand(), true)).'.'.$extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], $directory.'/'.$filename)) {
        $error = '아이콘 파일을 저장하지 못했습니다.';
        return false;
    }
    return $filename;
}

function bbs_trait_board_enabled($bo_table, $scope = 'write') {
    return function_exists('mdl_board_enabled')
        && in_array($scope, array('write', 'comment'), true)
        && mdl_board_enabled((string)$bo_table, 'bbs_trait', $scope);
}

function bbs_trait_action_form($html, $ctx, $action) {
    $bo_table = isset($ctx['bo_table']) ? (string)$ctx['bo_table'] : '';
    $ch_id = isset($ctx['character_id']) ? (int)$ctx['character_id'] : 0;
    if ($bo_table === '' || $ch_id < 1 || !bbs_trait_board_enabled($bo_table)) return $html;

    $traits = bbs_trait_get_owned($ch_id, true);
    if ($traits === false) return $html.'<small class="bbs-trait-error">특성 보유 정보를 불러오지 못했습니다.</small>';
    if (!$traits) return $html;
    $period = bbs_trait_period_state();
    if (!$period) return $html.'<small class="bbs-trait-error">특성 초기화 기간 정보를 불러오지 못했습니다.</small>';
    $is_spar = $action === 'DUEL_SPAR';
    $options = '';
    $status_definitions = bbs_trait_status_definitions();
    foreach ($traits as $trait) {
        if ($is_spar && in_array($trait['effect_type'], array('incoming_damage_modifier', 'outgoing_damage_modifier'), true)) continue;
        $usage = bbs_trait_usage_state($ch_id, $trait, $period['start']);
        $scope_id = isset($trait['effect_stat_id']) ? (int)$trait['effect_stat_id'] : 0;
        $scope_is_valid = $scope_id < 1
            || (bbs_trait_effect_supports_stat_scope($trait['effect_type']) && isset($status_definitions[$scope_id]));
        $disabled = !$usage['allowed'] || !$scope_is_valid;
        $label = $trait['trait_name'];
        if (!empty($usage['error'])) $label .= ' (횟수 확인 오류)';
        elseif ($usage['limit'] === 0) $label .= ' (사용 불가)';
        elseif ($usage['remaining'] !== null && $usage['remaining'] < 1) $label .= ' (횟수 소진)';
        elseif (!$scope_is_valid) $label .= ' (적용 스탯 설정 필요)';
        if (!empty($usage['error'])) $usage_note = '사용 횟수를 확인할 수 없습니다.';
        elseif (!$scope_is_valid) $usage_note = '적용 스탯 설정을 확인해 주세요.';
        elseif ($usage['limit'] === 0) $usage_note = '사용 불가';
        elseif ($usage['remaining'] === null) $usage_note = '사용 횟수 제한 없음';
        else $usage_note = '남은 횟수: '.(int)$usage['remaining'].'/'.(int)$usage['limit'].'회';
        $scope_label = bbs_trait_effect_scope_label($scope_id);
        $options .= '<option value="'.(int)$trait['trait_id'].'" data-effect-type="'.bbs_trait_html($trait['effect_type']).'" data-effect-value="'.(int)$trait['effect_value'].'" data-effect-stat-id="'.$scope_id.'" data-unavailable="'.($disabled ? '1' : '0').'" data-usage-message="'.bbs_trait_html($usage_note).'"'.($disabled ? ' disabled' : '').' title="'.bbs_trait_html($trait['trait_description']).'">'.bbs_trait_html($label).' — '.bbs_trait_html(bbs_trait_effect_text($trait['effect_type'], $trait['effect_value'], $scope_label)).'</option>';
    }
    if ($options === '') return $html;

    $selector_id = 'wr_duel_trait_id_'.(isset($ctx['id_prefix']) ? $ctx['id_prefix'] : $action);
    $help = '선택한 특성의 고유한 효과가 이번 대련·결투에 적용됩니다. 게시가 성공하면 사용 횟수가 차감됩니다.';
    $html .= '<div class="bbs-trait-action"><dl>'
        .'<dt><label for="'.bbs_trait_html($selector_id).'">특성 발현</label> <span class="bbs-trait-help" tabindex="0" title="'.bbs_trait_html($help).'" aria-label="'.bbs_trait_html($help).'">?</span></dt>'
        .'<dd><select name="wr_duel_trait_id" id="'.bbs_trait_html($selector_id).'" class="frm_input" data-trait-selector="1" disabled>'
        .'<option value="0" data-usage-message="">특성 사용 안 함</option>'.$options.'</select>'
        .'<small class="bbs-trait-usage" data-trait-usage aria-live="polite"></small></dd>'
        .'</dl></div>';
    $html .= '<script>(function(){var select=document.getElementById("'.bbs_trait_html($selector_id).'");if(!select)return;var status=select.parentNode.querySelector("[data-trait-usage]");function update(){if(!status)return;var option=select.options[select.selectedIndex];status.textContent=option?option.getAttribute("data-usage-message")||"":"";}function deferUpdate(){window.setTimeout(update,0);}select.addEventListener("change",update);var panel=select.parentNode;while(panel&&String(panel.className).indexOf("bbs-duel-action-panel")===-1)panel=panel.parentNode;if(panel){var fields=panel.querySelectorAll("select[name=wr_duel_target],select[name=wr_duel_stat_id]");Array.prototype.forEach.call(fields,function(field){field.addEventListener("change",deferUpdate);});}var action=document.querySelector("#fwrite select[name=action]");if(action)action.addEventListener("change",deferUpdate);update();})();</script>';
    return $html;
}

function bbs_trait_prepare_duel($result, $ctx) {
    $selected_id = isset($ctx['trait_id']) ? (int)$ctx['trait_id'] : 0;
    if ($selected_id < 1) {
        $result['valid'] = true;
        $result['trait_id'] = 0;
        return $result;
    }

    $bo_table = isset($ctx['bo_table']) ? (string)$ctx['bo_table'] : '';
    $ch_id = isset($ctx['character_id']) ? (int)$ctx['character_id'] : 0;
    if (!bbs_trait_board_enabled($bo_table)) {
        $result['valid'] = false;
        $result['message'] = '이 게시판에서는 특성을 사용할 수 없습니다.';
        return $result;
    }
    $trait = bbs_trait_get($selected_id);
    if (!$trait || $ch_id < 1) {
        $result['valid'] = false;
        $result['message'] = '사용할 수 없는 특성입니다.';
        return $result;
    }

    $owned_table = bbs_trait_tables()['owned'];
    $owned = sql_fetch("SELECT ch_id FROM `{$owned_table}` WHERE ch_id='{$ch_id}' AND trait_id='{$selected_id}' LIMIT 1", false);
    if (empty($owned['ch_id'])) {
        $result['valid'] = false;
        $result['message'] = '해당 캐릭터가 보유하지 않은 특성입니다.';
        return $result;
    }
    $type = (string)$trait['effect_type'];
    if (!isset(bbs_trait_effects()[$type])) {
        $result['valid'] = false;
        $result['message'] = '특성 효과 설정이 올바르지 않습니다.';
        return $result;
    }
    $is_spar = isset($ctx['duel_type']) && $ctx['duel_type'] === 'DUEL_SPAR';
    if ($is_spar && in_array($type, array('incoming_damage_modifier', 'outgoing_damage_modifier'), true)) {
        $result['valid'] = false;
        $result['message'] = 'HP 효과 특성은 대련에서 사용할 수 없습니다.';
        return $result;
    }
    $scope_id = isset($trait['effect_stat_id']) ? (int)$trait['effect_stat_id'] : 0;
    if ($scope_id > 0) {
        if (!bbs_trait_effect_supports_stat_scope($type)) {
            $result['valid'] = false;
            $result['message'] = '이 특성 효과에는 적용 스탯을 지정할 수 없습니다.';
            return $result;
        }
        $definitions = bbs_trait_status_definitions();
        if (!isset($definitions[$scope_id])) {
            $result['valid'] = false;
            $result['message'] = '특성의 적용 스탯이 삭제되어 사용할 수 없습니다. 운영진에게 문의해 주세요.';
            return $result;
        }
        $stat_ids = isset($ctx['stat_ids']) && is_array($ctx['stat_ids']) ? array_map('intval', $ctx['stat_ids']) : array();
        if (!in_array($scope_id, $stat_ids, true)) {
            $result['valid'] = false;
            $result['message'] = '선택한 판정에 특성의 적용 스탯이 포함되어 있지 않습니다.';
            return $result;
        }
    }
    if ($type === 'reroll_lowest' && (!isset($ctx['base_dice_count']) || (int)$ctx['base_dice_count'] <= 0)) {
        $result['valid'] = false;
        $result['message'] = '주사위 수가 0개일 때는 재굴림 특성을 사용할 수 없습니다.';
        return $result;
    }

    $period = bbs_trait_period_state();
    if (!$period) {
        $result['valid'] = false;
        $result['message'] = '특성 초기화 기간 정보를 확인하지 못했습니다.';
        return $result;
    }
    $usage = bbs_trait_usage_state($ch_id, $trait, $period['start']);
    if (!empty($usage['error'])) {
        $result['valid'] = false;
        $result['message'] = '특성 사용 가능 횟수를 확인하지 못했습니다.';
        return $result;
    }
    if (!$usage['allowed']) {
        $result['valid'] = false;
        $result['message'] = '선택한 특성의 기간당 사용 횟수를 모두 사용했습니다.';
        return $result;
    }
    $result['valid'] = true;
    $result['trait_id'] = (int)$trait['trait_id'];
    $result['trait_name'] = (string)$trait['trait_name'];
    $result['trait_icon'] = (string)$trait['trait_icon'];
    $result['trait_description'] = (string)$trait['trait_description'];
    $result['effect_type'] = $type;
    $result['effect_value'] = (int)$trait['effect_value'];
    $result['effect_stat_id'] = $scope_id;
    $result['effect_stat_name'] = bbs_trait_effect_scope_label($scope_id);
    $result['period_start'] = $period['start'];
    return $result;
}

function bbs_trait_consume_duel($result, $ctx) {
    return bbs_trait_consume_use($result, $ctx, 'write');
}

function bbs_trait_consume_use($result, $ctx, $scope = 'write') {
    $result['success'] = false;
    $trait_id = isset($ctx['trait_id']) ? (int)$ctx['trait_id'] : 0;
    $ch_id = isset($ctx['character_id']) ? (int)$ctx['character_id'] : 0;
    if ($trait_id < 1) {
        $result['success'] = true;
        return $result;
    }

    $source_ref = isset($ctx['source_ref']) ? (string)$ctx['source_ref'] : '';
    $period_start = isset($ctx['period_start']) ? (string)$ctx['period_start'] : '';
    $board = isset($ctx['bo_table']) ? (string)$ctx['bo_table'] : '';
    $post_id = isset($ctx['post_id']) ? (int)$ctx['post_id'] : 0;
    if (!in_array($scope, array('write', 'comment'), true) || $ch_id < 1 || $source_ref === ''
        || !bbs_trait_parse_datetime($period_start) || !bbs_trait_board_enabled($board, $scope)) {
        $result['message'] = '특성 사용 기록을 확인할 수 없습니다.';
        return $result;
    }
    $tables = bbs_trait_tables();
    $source = bbs_trait_escape($source_ref);
    $existing = sql_fetch("SELECT use_id FROM `{$tables['use']}` WHERE source_ref='{$source}' LIMIT 1", false);
    if (!empty($existing['use_id'])) {
        $result['success'] = true;
        return $result;
    }

    if (!sql_query('START TRANSACTION', false)) {
        $result['message'] = '특성 사용 횟수를 저장하지 못했습니다.';
        return $result;
    }
    $comment_enabled_column = $scope === 'comment' ? 't.comment_enabled' : '1 AS comment_enabled';
    $locked = sql_fetch("SELECT o.ch_id, t.usage_limit, t.trait_enabled, {$comment_enabled_column}
        FROM `{$tables['owned']}` AS o
        INNER JOIN `{$tables['catalog']}` AS t ON t.trait_id=o.trait_id
        WHERE o.ch_id='{$ch_id}' AND o.trait_id='{$trait_id}'
        FOR UPDATE", false);
    if (empty($locked['ch_id']) || empty($locked['trait_enabled'])) {
        sql_query('ROLLBACK', false);
        $result['message'] = '특성 보유 또는 활성 상태가 변경되었습니다.';
        return $result;
    }
    if ($scope === 'comment' && empty($locked['comment_enabled'])) {
        sql_query('ROLLBACK', false);
        $result['message'] = '특성의 댓글 사용 설정이 변경되었습니다.';
        return $result;
    }
    if ($locked['usage_limit'] !== null) {
        $start = bbs_trait_escape($period_start);
        $count = sql_fetch("SELECT COUNT(*) AS used_count FROM `{$tables['use']}`
            WHERE ch_id='{$ch_id}' AND trait_id='{$trait_id}' AND period_start='{$start}'", false);
        if (!isset($count['used_count'])) {
            sql_query('ROLLBACK', false);
            $result['message'] = '특성 사용 기록을 확인하지 못했습니다.';
            return $result;
        }
        $used_count = (int)$count['used_count'];
        if ((int)$locked['usage_limit'] === 0 || $used_count >= (int)$locked['usage_limit']) {
            sql_query('ROLLBACK', false);
            $result['message'] = $scope === 'comment'
                ? '특성 사용 횟수가 변경되어 댓글을 저장할 수 없습니다.'
                : '특성 사용 횟수가 변경되어 전투를 저장할 수 없습니다.';
            return $result;
        }
    }

    $now = bbs_trait_now()->format('Y-m-d H:i:s');
    $inserted = sql_query("INSERT INTO `{$tables['use']}`
        (ch_id, trait_id, period_start, source_ref, board_name, post_id, used_at)
        VALUES ('{$ch_id}', '{$trait_id}', '".bbs_trait_escape($period_start)."', '{$source}',
        '".bbs_trait_escape($board)."', '{$post_id}', '".bbs_trait_escape($now)."')", false);
    if (!$inserted) {
        $duplicate = sql_fetch("SELECT use_id FROM `{$tables['use']}` WHERE source_ref='{$source}' LIMIT 1", false);
        if (!empty($duplicate['use_id'])) {
            sql_query('COMMIT', false);
            $result['success'] = true;
            return $result;
        }
        sql_query('ROLLBACK', false);
        $result['message'] = '특성 사용 횟수를 저장하지 못했습니다.';
        return $result;
    }
    if (!sql_query('COMMIT', false)) {
        sql_query('ROLLBACK', false);
        $result['message'] = '특성 사용 횟수를 저장하지 못했습니다.';
        return $result;
    }
    $result['success'] = true;
    return $result;
}