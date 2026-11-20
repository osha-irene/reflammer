<?php
if (!defined('_GNUBOARD_')) exit;

function bbs_duel_enemy_table() {
    global $g5;
    if (empty($g5['module_bbs_duel_enemy_table'])) {
        $g5['module_bbs_duel_enemy_table'] = G5_TABLE_PREFIX.'module_bbs_duel_enemy';
    }
    return $g5['module_bbs_duel_enemy_table'];
}

function bbs_duel_escape($value) {
    return sql_real_escape_string((string)$value);
}

function bbs_duel_player_stat_cap() {
    $cap = function_exists('mdl_setting_get')
        ? (int)mdl_setting_get('bbs_duel', 'player_stat_cap', 5)
        : 5;
    return max(1, min(100, $cap));
}

function bbs_duel_status_definitions() {
    static $definitions = null;
    global $g5;
    if ($definitions !== null) return $definitions;
    $definitions = array();
    if (empty($g5['status_config_table'])) return $definitions;
    $rs = sql_query("SELECT st_id, st_name FROM `{$g5['status_config_table']}` ORDER BY st_order ASC, st_id ASC");
    while ($rs && $row = sql_fetch_array($rs)) {
        $id = (int)$row['st_id'];
        if ($id < 1) continue;
        $definitions[$id] = array('id' => $id, 'name' => (string)$row['st_name']);
    }
    return $definitions;
}

function bbs_duel_stat_choice_labels($definitions = null) {
    if (!is_array($definitions)) $definitions = bbs_duel_status_definitions();
    $choices = array();
    foreach ($definitions as $definition) {
        $token = 's'.(int)$definition['id'];
        $choices[$token] = (string)$definition['name'];
    }
    $definitions = array_values($definitions);
    for ($i = 0; $i < count($definitions); $i++) {
        for ($j = $i + 1; $j < count($definitions); $j++) {
            $first = $definitions[$i];
            $second = $definitions[$j];
            $token = 's'.(int)$first['id'].'+s'.(int)$second['id'];
            $choices[$token] = (string)$first['name'].' + '.(string)$second['name'];
        }
    }
    return $choices;
}

function bbs_duel_stat_combinations($stats) {
    $ordered = array_values($stats);
    $out = array();
    foreach ($ordered as $stat) {
        $key = 's'.(int)$stat['id'];
        $out[$key] = array('token' => $key, 'keys' => array($key), 'ids' => array((int)$stat['id']), 'name' => $stat['name'], 'value' => (int)$stat['value']);
    }
    for ($i = 0; $i < count($ordered); $i++) {
        for ($j = $i + 1; $j < count($ordered); $j++) {
            $first = $ordered[$i];
            $second = $ordered[$j];
            $token = 's'.(int)$first['id'].'+s'.(int)$second['id'];
            $out[$token] = array('token' => $token, 'keys' => array('s'.(int)$first['id'], 's'.(int)$second['id']), 'ids' => array((int)$first['id'], (int)$second['id']), 'name' => $first['name'].' + '.$second['name'], 'value' => (int)$first['value'] + (int)$second['value']);
        }
    }
    return $out;
}

function bbs_duel_enemy_stat_limits($raw) {
    $raw = trim((string)$raw);
    if ($raw === '') return array();
    $valid = array_keys(bbs_duel_stat_choice_labels());
    $limits = array();
    foreach (explode('|', $raw) as $token) {
        if (in_array($token, $valid, true)) $limits[] = $token;
    }
    return array_values(array_unique($limits));
}

function bbs_duel_parse_enemy_stat_input($raw, &$valid = null) {
    $valid = true;
    if (!is_scalar($raw)) {
        $valid = false;
        return array();
    }
    $raw = trim((string)$raw);
    if ($raw === '') return array();
    $label_to_token = array();
    foreach (bbs_duel_stat_choice_labels() as $token => $label) {
        $normalized_label = preg_replace('/\s*\+\s*/u', '+', $label);
        $label_to_token[$normalized_label] = $token;
    }
    $tokens = array();
    foreach (explode('|', $raw) as $label) {
        $label = preg_replace('/\s*\+\s*/u', '+', trim($label));
        if ($label === '' || !isset($label_to_token[$label])) {
            $valid = false;
            return array();
        }
        $tokens[] = $label_to_token[$label];
    }
    return array_values(array_unique($tokens));
}

function bbs_duel_enemy_stat_input_value($raw) {
    $labels = bbs_duel_stat_choice_labels();
    $values = array();
    foreach (bbs_duel_enemy_stat_limits($raw) as $token) {
        if (isset($labels[$token])) $values[] = $labels[$token];
    }
    return implode('|', $values);
}

function bbs_duel_enemy_stat_values($raw, $legacy = array()) {
    $decoded = json_decode((string)$raw, true);
    $stored = is_array($decoded) ? $decoded : array();
    $legacy_fields = array('de_sense', 'de_insight', 'de_courage');
    $out = array();
    $slot = 0;
    foreach (bbs_duel_status_definitions() as $id => $definition) {
        if (array_key_exists((string)$id, $stored)) {
            $value = (int)$stored[(string)$id];
        } elseif (array_key_exists($id, $stored)) {
            $value = (int)$stored[$id];
        } elseif (isset($legacy_fields[$slot], $legacy[$legacy_fields[$slot]])) {
            $value = (int)$legacy[$legacy_fields[$slot]];
        } else {
            $value = 1;
        }
        $out['s'.(int)$id] = max(1, min(100, $value));
        $slot++;
    }
    return $out;
}

function bbs_duel_get_character_stats($ch_id) {
    static $cache = array();
    $ch_id = (int)$ch_id;
    if (isset($cache[$ch_id])) return $cache[$ch_id];
    $out = array();
    if ($ch_id <= 0 || !function_exists('merlin_dice_statuses')) return $out;
    $statuses = merlin_dice_statuses($ch_id);
    foreach (bbs_duel_status_definitions() as $id => $definition) {
        if (!isset($statuses[$id])) continue;
        $status = $statuses[$id];
        $out[(int)$id] = array(
            'id' => $id,
            'name' => (string)$definition['name'],
            'key' => 's'.(int)$id,
            'value' => max(1, min(bbs_duel_player_stat_cap(), (int)$status['value'])),
        );
    }
    $cache[$ch_id] = $out;
    return $cache[$ch_id];
}

function bbs_duel_get_character_card($ch_id) {
    static $cache = array();
    global $g5;
    $ch_id = (int)$ch_id;
    if ($ch_id < 1 || empty($g5['character_table'])) return array();
    if (!array_key_exists($ch_id, $cache)) {
        $row = sql_fetch("SELECT ch_id, ch_name, ch_thumb, ch_side FROM `{$g5['character_table']}` WHERE ch_id='{$ch_id}' LIMIT 1", false);
        $cache[$ch_id] = !empty($row['ch_id']) ? $row : array();
    }
    return $cache[$ch_id];
}

function bbs_duel_get_legacy_character_card($name) {
    static $cache = array();
    global $g5;
    $name = trim((string)$name);
    if ($name === '' || empty($g5['character_table'])) return array();
    if (!array_key_exists($name, $cache)) {
        $name_e = bbs_duel_escape($name);
        $rs = sql_query("SELECT ch_id, ch_name, ch_thumb, ch_side FROM `{$g5['character_table']}` WHERE ch_name='{$name_e}' AND ch_state='승인' AND ch_type IN ('main','sub') LIMIT 2", false);
        $row = $rs ? sql_fetch_array($rs) : array();
        $second = $rs ? sql_fetch_array($rs) : array();
        $cache[$name] = !empty($row['ch_id']) && empty($second['ch_id']) ? $row : array();
    }
    return $cache[$name];
}

function bbs_duel_roll($count) {
    if (!function_exists('merlin_dice_roll')) return array();
    return merlin_dice_roll(max(1, min(100, (int)$count)));
}

function bbs_duel_get_targets($my_ch_id) {
    global $g5;
    $targets = array('character' => array(), 'enemy' => array());
    if (empty($g5['character_table'])) return $targets;

    $my_ch_id = (int)$my_ch_id;
    $sql = "SELECT ch_id, ch_name FROM `{$g5['character_table']}`
             WHERE ch_state='승인' AND ch_type IN ('main','sub') AND ch_id <> '{$my_ch_id}'
             ORDER BY ch_name ASC";
    $rs = sql_query($sql);
    while ($rs && $row = sql_fetch_array($rs)) {
        $targets['character'][] = array(
            'id' => (int)$row['ch_id'],
            'name' => (string)$row['ch_name'],
        );
    }

    $enemy_table = bbs_duel_enemy_table();
    $rs = sql_query("SELECT de_id, de_name, de_sense, de_insight, de_courage, de_stats, de_allowed_stats FROM `{$enemy_table}` ORDER BY de_order ASC, de_id ASC", false);
    while ($rs && $row = sql_fetch_array($rs)) {
        $targets['enemy'][] = array(
            'id' => (int)$row['de_id'],
            'name' => (string)$row['de_name'],
            'stats' => bbs_duel_enemy_stat_values(isset($row['de_stats']) ? $row['de_stats'] : '', $row),
            'allowed_stats' => bbs_duel_enemy_stat_limits(isset($row['de_allowed_stats']) ? $row['de_allowed_stats'] : ''),
        );
    }
    return $targets;
}

function bbs_duel_validate_target($kind, $id, $actor_ch_id) {
    global $g5;
    $id = (int)$id;
    if ($id < 1) return array();

    if ($kind === 'character' && !empty($g5['character_table'])) {
        $actor_ch_id = (int)$actor_ch_id;
        $row = sql_fetch("SELECT ch_id, ch_name FROM `{$g5['character_table']}`
                           WHERE ch_id='{$id}' AND ch_id <> '{$actor_ch_id}'
                             AND ch_type IN ('main','sub') AND ch_state='승인' LIMIT 1");
        if (!empty($row['ch_id'])) return array(
            'id' => (int)$row['ch_id'],
            'name' => (string)$row['ch_name'],
            'kind' => 'character',
        );
        return array();
    }

    if ($kind === 'enemy') {
        $table = bbs_duel_enemy_table();
        $row = sql_fetch("SELECT de_id, de_name, de_sense, de_insight, de_courage, de_stats, de_allowed_stats FROM `{$table}` WHERE de_id='{$id}' LIMIT 1", false);
        if (!empty($row['de_id'])) return array(
            'id' => (int)$row['de_id'],
            'name' => (string)$row['de_name'],
            'kind' => 'enemy',
            'stats' => bbs_duel_enemy_stat_values(isset($row['de_stats']) ? $row['de_stats'] : '', $row),
            'allowed_stats' => bbs_duel_enemy_stat_limits(isset($row['de_allowed_stats']) ? $row['de_allowed_stats'] : ''),
        );
    }
    return array();
}

function bbs_duel_get_target_stat_value($target, $stat) {
    if (empty($target) || empty($stat['keys'])) return null;
    if (isset($target['kind']) && $target['kind'] === 'enemy') {
        if (!empty($target['allowed_stats']) && !in_array($stat['token'], $target['allowed_stats'], true)) return null;
        $value = 0;
        foreach ($stat['keys'] as $key) {
            if (!isset($target['stats'][$key])) return null;
            $value += max(1, min(100, (int)$target['stats'][$key]));
        }
        return $value > 0 ? $value : null;
    }
    if (isset($target['kind']) && $target['kind'] === 'character') {
        $stats = bbs_duel_get_character_stats((int)$target['id']);
        $value = 0;
        foreach ($stat['keys'] as $key) {
            $found = false;
            foreach ($stats as $character_stat) {
                if ($character_stat['key'] === $key) {
                    $value += (int)$character_stat['value'];
                    $found = true;
                    break;
                }
            }
            if (!$found) return null;
        }
        return $value > 0 ? $value : null;
    }
    return null;
}

function bbs_duel_rollback_new_post() {
    global $write_table, $wr_id, $w, $bo_table, $g5, $member;
    if (!isset($w) || $w !== '' || empty($wr_id) || empty($write_table) || empty($bo_table)) return;
    $wr_id = (int)$wr_id;
    $write_table = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$write_table);
    $bo_table = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$bo_table);
    if ($wr_id < 1 || $write_table === '' || $bo_table === '') return;

    $row = sql_fetch("SELECT wr_id, wr_is_comment FROM `{$write_table}` WHERE wr_id='{$wr_id}' LIMIT 1", false);
    if (empty($row['wr_id']) || !empty($row['wr_is_comment'])) return;
    sql_query("DELETE FROM `{$write_table}` WHERE wr_id='{$wr_id}' LIMIT 1", false);
    if (!empty($g5['board_new_table'])) {
        sql_query("DELETE FROM `{$g5['board_new_table']}` WHERE bo_table='".bbs_duel_escape($bo_table)."' AND wr_id='{$wr_id}'", false);
    }
    if (!empty($g5['board_table'])) {
        sql_query("UPDATE `{$g5['board_table']}` SET bo_count_write=IF(bo_count_write>0,bo_count_write-1,0) WHERE bo_table='".bbs_duel_escape($bo_table)."'", false);
    }
    if (function_exists('delete_point') && !empty($member['mb_id'])) {
        delete_point($member['mb_id'], $bo_table, $wr_id, '쓰기');
    }
}

function bbs_duel_fail($message) {
    bbs_duel_rollback_new_post();
    alert($message);
}
