<?php
if (!defined('_GNUBOARD_')) exit;

function bbs_duel_enemy_table() {
    global $g5;
    if (empty($g5['module_bbs_duel_enemy_table'])) {
        $g5['module_bbs_duel_enemy_table'] = G5_TABLE_PREFIX.'module_bbs_duel_enemy';
    }
    return $g5['module_bbs_duel_enemy_table'];
}

function bbs_duel_hp_table() {
    global $g5;
    if (empty($g5['module_bbs_duel_hp_table'])) $g5['module_bbs_duel_hp_table'] = G5_TABLE_PREFIX.'module_bbs_duel_hp';
    return $g5['module_bbs_duel_hp_table'];
}

function bbs_duel_history_table() {
    global $g5;
    if (empty($g5['module_bbs_duel_history_table'])) $g5['module_bbs_duel_history_table'] = G5_TABLE_PREFIX.'module_bbs_duel_history';
    return $g5['module_bbs_duel_history_table'];
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

function bbs_duel_hp_default() {
    $value = function_exists('mdl_setting_get') ? (int)mdl_setting_get('bbs_duel', 'player_hp_default', 5) : 5;
    return max(1, min(1000, $value));
}

function bbs_duel_default_damage() {
    $value = function_exists('mdl_setting_get') ? (int)mdl_setting_get('bbs_duel', 'default_damage', 1) : 1;
    return max(0, min(1000, $value));
}

function bbs_duel_hp_get($type, $id) {
    $type = ($type === 'enemy') ? 'enemy' : 'character';
    $id = (int)$id;
    if ($id < 1) return array();
    $table = bbs_duel_hp_table();
    $type_e = bbs_duel_escape($type);
    $row = sql_fetch("SELECT hp_max, hp_current FROM {$table} WHERE hp_type='{$type_e}' AND hp_target_id='{$id}' LIMIT 1", false);
    if (empty($row)) return array();
    $max = max(1, (int)$row['hp_max']);
    $current = max(0, min($max, (int)$row['hp_current']));
    return array('max' => $max, 'current' => $current, 'incapacitated' => ($current <= 0));
}

function bbs_duel_hp_initialize($type, $id, $max) {
    $type = ($type === 'enemy') ? 'enemy' : 'character';
    $id = (int)$id;
    $max = max(1, min(1000, (int)$max));
    if ($id < 1) return array();
    $table = bbs_duel_hp_table();
    $type_e = bbs_duel_escape($type);
    sql_query("INSERT IGNORE INTO {$table} SET hp_type='{$type_e}', hp_target_id='{$id}', hp_max='{$max}', hp_current='{$max}', hp_updated_at='".G5_TIME_YMDHIS."'");
    return bbs_duel_hp_get($type, $id);
}

function bbs_duel_hp_ensure_character($ch_id) {
    global $g5;
    $ch_id = (int)$ch_id;
    if ($ch_id < 1 || empty($g5['character_table'])) return array();
    $character = sql_fetch("SELECT ch_id, ch_state, ch_type FROM {$g5['character_table']} WHERE ch_id='{$ch_id}' LIMIT 1", false);
    if (empty($character['ch_id']) || $character['ch_state'] !== '승인' || !in_array($character['ch_type'], array('main', 'sub'), true)) return array();
    return bbs_duel_hp_initialize('character', $ch_id, bbs_duel_hp_default());
}

function bbs_duel_hp_seed_approved_characters() {
    static $seeded = false;
    global $g5;
    if ($seeded || empty($g5['character_table'])) return;
    $seeded = true;
    $rs = sql_query("SELECT ch_id FROM `{$g5['character_table']}` WHERE ch_state='승인' AND ch_type IN ('main','sub')", false);
    $default = bbs_duel_hp_default();
    while ($rs && $row = sql_fetch_array($rs)) bbs_duel_hp_initialize('character', (int)$row['ch_id'], $default);
}

function bbs_duel_hp_is_incapacitated($type, $id) {
    $hp = ($type === 'enemy') ? bbs_duel_hp_ensure_enemy($id) : bbs_duel_hp_ensure_character($id);
    return !empty($hp) && !empty($hp['incapacitated']);
}

function bbs_duel_hp_ensure_enemy($enemy_id, $initial_max = null) {
    $enemy_id = (int)$enemy_id;
    if ($enemy_id < 1) return array();
    if ($initial_max === null) {
        $enemy_table = bbs_duel_enemy_table();
        $enemy = sql_fetch("SELECT de_id, de_hp_max FROM {$enemy_table} WHERE de_id='{$enemy_id}' LIMIT 1", false);
        if (empty($enemy['de_id'])) return array();
        $initial_max = isset($enemy['de_hp_max']) ? (int)$enemy['de_hp_max'] : bbs_duel_hp_default();
    }
    return bbs_duel_hp_initialize('enemy', $enemy_id, $initial_max);
}

function bbs_duel_hp_set_max($type, $id, $new_max) {
    $type = ($type === 'enemy') ? 'enemy' : 'character';
    $id = (int)$id;
    $new_max = max(1, min(1000, (int)$new_max));
    if ($id < 1) return array();
    $before = bbs_duel_hp_get($type, $id);
    $table = bbs_duel_hp_table();
    $type_e = bbs_duel_escape($type);
    sql_query("UPDATE {$table} SET hp_max='{$new_max}', hp_current=LEAST(hp_current, '{$new_max}'), hp_updated_at='".G5_TIME_YMDHIS."' WHERE hp_type='{$type_e}' AND hp_target_id='{$id}' LIMIT 1");
    $after = bbs_duel_hp_get($type, $id);
    if ($before && $after && (int)$before['current'] !== (int)$after['current']) {
        bbs_duel_history_record_hp_change($type, $id, (int)$before['current'], (int)$after['current'], 'admin', '최대 HP 변경으로 현재 HP 조정');
    }
    return $after;
}

function bbs_duel_hp_set_current($type, $id, $current, $history_source = 'admin', $history_note = '') {
    $type = ($type === 'enemy') ? 'enemy' : 'character';
    $id = (int)$id;
    $current = max(0, min(1000, (int)$current));
    if ($id < 1) return array();
    $before = bbs_duel_hp_get($type, $id);
    if (!$before) return array();
    $table = bbs_duel_hp_table();
    $type_e = bbs_duel_escape($type);
    sql_query("UPDATE {$table} SET hp_current=LEAST(hp_max, '{$current}'), hp_updated_at='".G5_TIME_YMDHIS."' WHERE hp_type='{$type_e}' AND hp_target_id='{$id}' LIMIT 1");
    $after = bbs_duel_hp_get($type, $id);
    if ($after && $history_source && (int)$before['current'] !== (int)$after['current']) {
        bbs_duel_history_record_hp_change($type, $id, (int)$before['current'], (int)$after['current'], $history_source, $history_note);
    }
    return $after;
}

function bbs_duel_history_insert($data) {
    $table = bbs_duel_history_table();
    $fields = array(
        'event_type' => '', 'event_datetime' => G5_TIME_YMDHIS, 'actor_name' => '', 'actor_type' => '', 'actor_id' => 0,
        'target_name' => '', 'target_type' => '', 'target_id' => 0, 'stat_name' => '',
        'actor_roll' => '', 'actor_roll_initial' => '', 'actor_reroll_value' => 0, 'actor_reroll_index' => 0,
        'actor_dice_base' => 0, 'actor_dice_modifier' => 0, 'actor_dice_count' => 0,
        'actor_dice_sum' => 0, 'actor_modifier' => 0, 'actor_score' => 0,
        'target_roll' => '', 'target_dice_sum' => 0, 'target_modifier' => 0, 'target_score' => 0,
        'winner_name' => '', 'actor_hp_before' => 0, 'actor_hp_after' => 0,
        'target_hp_before' => 0, 'target_hp_after' => 0,
        'trait_name' => '', 'trait_icon' => '', 'trait_description' => '', 'trait_effect_type' => '', 'trait_effect_value' => 0,
        'trait_effect_stat_id' => 0, 'trait_effect_stat_name' => '',
        'event_note' => '', 'source_ref' => null,
    );
    foreach ($fields as $key => $default) if (array_key_exists($key, $data)) $fields[$key] = $data[$key];
    $values = array();
    foreach ($fields as $key => $value) {
        if ($key === 'source_ref' && $value === null) $values[$key] = 'NULL';
        elseif (in_array($key, array('actor_id','target_id','actor_reroll_value','actor_reroll_index','actor_dice_base','actor_dice_modifier','actor_dice_count','actor_dice_sum','actor_modifier','actor_score','target_dice_sum','target_modifier','target_score','trait_effect_value','trait_effect_stat_id','actor_hp_before','actor_hp_after','target_hp_before','target_hp_after'), true)) $values[$key] = "'".(int)$value."'";
        else $values[$key] = "'".bbs_duel_escape($value)."'";
    }
    $columns = array(); $sql_values = array();
    foreach ($values as $key => $value) { $columns[] = '`'.$key.'`'; $sql_values[] = $value; }
    return sql_query("INSERT INTO `{$table}` (".implode(',', $columns).") VALUES (".implode(',', $sql_values).")", false);
}

function bbs_duel_history_record_hp_change($type, $id, $before, $after, $source = 'admin', $note = '') {
    global $g5;
    $type = ($type === 'enemy') ? 'enemy' : 'character';
    $id = (int)$id;
    $source = in_array($source, array('admin','item','skill'), true) ? $source : 'admin';
    if ($type === 'enemy') {
        $row = sql_fetch("SELECT de_name FROM `".bbs_duel_enemy_table()."` WHERE de_id='{$id}' LIMIT 1", false);
        $name = isset($row['de_name']) ? (string)$row['de_name'] : '';
    } else {
        $row = sql_fetch("SELECT ch_name FROM `{$g5['character_table']}` WHERE ch_id='{$id}' LIMIT 1", false);
        $name = isset($row['ch_name']) ? (string)$row['ch_name'] : '';
    }
    if ($name === '') return false;
    $labels = array('admin' => '운영자 HP 조정', 'item' => '아이템 사용', 'skill' => '스킬 사용');
    return bbs_duel_history_insert(array(
        'event_type' => $source, 'target_name' => $name, 'target_type' => $type, 'target_id' => $id,
        'target_hp_before' => (int)$before, 'target_hp_after' => (int)$after,
        'event_note' => $note !== '' ? $labels[$source].': '.$note : $labels[$source],
    ));
}

function bbs_duel_history_import_battle($row, $source_ref) {
    $type = isset($row['wr_duel_type']) ? (string)$row['wr_duel_type'] : '';
    if (!in_array($type, array('duel','spar'), true)) return false;
    $existing = sql_fetch("SELECT history_id FROM `".bbs_duel_history_table()."` WHERE source_ref='".bbs_duel_escape($source_ref)."' LIMIT 1", false);
    if (!empty($existing['history_id'])) return false;
    $actor_roll = isset($row['wr_duel_actor_roll']) ? (string)$row['wr_duel_actor_roll'] : '';
    $target_roll = isset($row['wr_duel_target_roll']) ? (string)$row['wr_duel_target_roll'] : '';
    $actor_score = (int)(isset($row['wr_duel_actor_score']) ? $row['wr_duel_actor_score'] : 0);
    $target_score = (int)(isset($row['wr_duel_target_score']) ? $row['wr_duel_target_score'] : 0);
    $actor_dice_sum = array_sum(explode(',', $actor_roll));
    $outcome = isset($row['wr_duel_result']) ? (string)$row['wr_duel_result'] : '';
    $winner = $outcome === 'actor_win' ? (string)$row['wr_duel_actor_name'] : ($outcome === 'target_win' ? (string)$row['wr_duel_target_name'] : '');
    return bbs_duel_history_insert(array(
        'event_type' => $type, 'event_datetime' => isset($row['wr_datetime']) ? $row['wr_datetime'] : G5_TIME_YMDHIS,
        'actor_name' => isset($row['wr_duel_actor_name']) ? $row['wr_duel_actor_name'] : (isset($row['wr_name']) ? $row['wr_name'] : ''),
        'actor_type' => 'character', 'actor_id' => isset($row['wr_duel_actor_ch_id']) ? (int)$row['wr_duel_actor_ch_id'] : 0,
        'target_name' => isset($row['wr_duel_target_name']) ? $row['wr_duel_target_name'] : '',
        'target_type' => isset($row['wr_duel_target_type']) ? $row['wr_duel_target_type'] : '',
        'target_id' => isset($row['wr_duel_target_id']) ? (int)$row['wr_duel_target_id'] : 0,
        'stat_name' => isset($row['wr_duel_stat_name']) ? $row['wr_duel_stat_name'] : '',
        'actor_roll' => $actor_roll, 'actor_dice_sum' => $actor_dice_sum,
        'actor_modifier' => isset($row['wr_duel_actor_score_modifier']) ? (int)$row['wr_duel_actor_score_modifier'] : 0,
        'actor_score' => $actor_score,
        'actor_roll_initial' => isset($row['wr_duel_actor_roll_initial']) && $row['wr_duel_actor_roll_initial'] !== '' ? $row['wr_duel_actor_roll_initial'] : $actor_roll,
        'actor_reroll_value' => isset($row['wr_duel_actor_reroll_value']) ? (int)$row['wr_duel_actor_reroll_value'] : 0,
        'actor_reroll_index' => isset($row['wr_duel_actor_reroll_index']) ? (int)$row['wr_duel_actor_reroll_index'] : 0,
        'actor_dice_base' => isset($row['wr_duel_actor_dice_base']) ? (int)$row['wr_duel_actor_dice_base'] : 0,
        'actor_dice_modifier' => isset($row['wr_duel_actor_dice_modifier']) ? (int)$row['wr_duel_actor_dice_modifier'] : 0,
        'actor_dice_count' => isset($row['wr_duel_actor_dice_count']) ? (int)$row['wr_duel_actor_dice_count'] : 0,
        'target_roll' => $target_roll, 'target_dice_sum' => $target_score, 'target_score' => $target_score,
        'winner_name' => $winner,
        'trait_name' => isset($row['wr_duel_trait_name']) ? $row['wr_duel_trait_name'] : '',
        'trait_icon' => isset($row['wr_duel_trait_icon']) ? $row['wr_duel_trait_icon'] : '',
        'trait_description' => isset($row['wr_duel_trait_description']) ? $row['wr_duel_trait_description'] : '',
        'trait_effect_type' => isset($row['wr_duel_trait_effect_type']) ? $row['wr_duel_trait_effect_type'] : '',
        'trait_effect_value' => isset($row['wr_duel_trait_effect_value']) ? (int)$row['wr_duel_trait_effect_value'] : 0,
        'trait_effect_stat_id' => isset($row['wr_duel_trait_effect_stat_id']) ? (int)$row['wr_duel_trait_effect_stat_id'] : 0,
        'trait_effect_stat_name' => isset($row['wr_duel_trait_effect_stat_name']) ? $row['wr_duel_trait_effect_stat_name'] : '',
        'actor_hp_before' => isset($row['wr_duel_actor_hp_before']) ? (int)$row['wr_duel_actor_hp_before'] : 0,
        'actor_hp_after' => isset($row['wr_duel_actor_hp_after']) ? (int)$row['wr_duel_actor_hp_after'] : 0,
        'target_hp_before' => isset($row['wr_duel_target_hp_before']) ? (int)$row['wr_duel_target_hp_before'] : 0,
        'target_hp_after' => isset($row['wr_duel_target_hp_after']) ? (int)$row['wr_duel_target_hp_after'] : 0,
        'source_ref' => $source_ref,
    ));
}

function bbs_duel_damage_adjust($base_damage, $context = array()) {
    $damage = max(0, min(1000, (int)$base_damage));
    if (function_exists('mdl_apply_filters')) $damage = mdl_apply_filters('bbs_duel_damage_adjustment', $damage, $context);
    if (!is_scalar($damage) || !preg_match('/^[0-9]+$/', (string)$damage)) return 0;
    return max(0, min(1000, (int)$damage));
}

function bbs_duel_hp_apply_damage($type, $id, $damage) {
    $hp = bbs_duel_hp_get($type, $id);
    if (!$hp) return array();
    $damage = max(0, min(1000, (int)$damage));
    $after = max(0, $hp['current'] - $damage);
    $updated = bbs_duel_hp_set_current($type, $id, $after, null);
    return array(
        'before' => $hp['current'],
        'after' => isset($updated['current']) ? $updated['current'] : $after,
        'damage' => $hp['current'] - (isset($updated['current']) ? $updated['current'] : $after),
    );
}

function bbs_duel_hp_delete($type, $id) {
    $type = ($type === 'enemy') ? 'enemy' : 'character';
    $id = (int)$id;
    if ($id < 1) return false;
    $table = bbs_duel_hp_table();
    $type_e = bbs_duel_escape($type);
    return sql_query("DELETE FROM {$table} WHERE hp_type='{$type_e}' AND hp_target_id='{$id}' LIMIT 1");
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
            'value' => max(0, min(bbs_duel_player_stat_cap(), (int)$status['value'])),
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
    if (!function_exists('merlin_dice_roll')) return false;
    return merlin_dice_roll(max(0, min(100, (int)$count)));
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
        $hp = bbs_duel_hp_ensure_character((int)$row['ch_id']);
        $targets['character'][] = array(
            'id' => (int)$row['ch_id'],
            'name' => (string)$row['ch_name'],
            'hp' => $hp,
        );
    }

    $enemy_table = bbs_duel_enemy_table();
    $rs = sql_query("SELECT de_id, de_name, de_sense, de_insight, de_courage, de_stats, de_allowed_stats FROM `{$enemy_table}` ORDER BY de_order ASC, de_id ASC", false);
    while ($rs && $row = sql_fetch_array($rs)) {
        $hp = bbs_duel_hp_ensure_enemy((int)$row['de_id']);
        $targets['enemy'][] = array(
            'id' => (int)$row['de_id'],
            'name' => (string)$row['de_name'],
            'stats' => bbs_duel_enemy_stat_values(isset($row['de_stats']) ? $row['de_stats'] : '', $row),
            'allowed_stats' => bbs_duel_enemy_stat_limits(isset($row['de_allowed_stats']) ? $row['de_allowed_stats'] : ''),
            'hp' => $hp,
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
        if (!empty($row['ch_id'])) {
            $hp = bbs_duel_hp_ensure_character((int)$row['ch_id']);
            if (!empty($hp['incapacitated'])) return array();
            return array(
            'id' => (int)$row['ch_id'],
            'name' => (string)$row['ch_name'],
            'kind' => 'character',
            'hp' => $hp,
        ); }
        return array();
    }

    if ($kind === 'enemy') {
        $table = bbs_duel_enemy_table();
        $row = sql_fetch("SELECT de_id, de_name, de_sense, de_insight, de_courage, de_stats, de_allowed_stats FROM `{$table}` WHERE de_id='{$id}' LIMIT 1", false);
        if (!empty($row['de_id'])) {
            $hp = bbs_duel_hp_ensure_enemy($id);
            if (!empty($hp['incapacitated'])) return array();
            return array(
            'id' => (int)$row['de_id'],
            'name' => (string)$row['de_name'],
            'kind' => 'enemy',
            'stats' => bbs_duel_enemy_stat_values(isset($row['de_stats']) ? $row['de_stats'] : '', $row),
            'allowed_stats' => bbs_duel_enemy_stat_limits(isset($row['de_allowed_stats']) ? $row['de_allowed_stats'] : ''),
            'hp' => $hp,
        ); }
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
        return $value;
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
