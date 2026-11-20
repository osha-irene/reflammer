<?php
if (!defined('_GNUBOARD_')) exit;

require_once __DIR__.'/lib.php';
global $g5;
$g5['module_bbs_duel_enemy_table'] = G5_TABLE_PREFIX.'module_bbs_duel_enemy';
$enemy_table = $g5['module_bbs_duel_enemy_table'];
$g5['module_bbs_duel_hp_table'] = G5_TABLE_PREFIX.'module_bbs_duel_hp';
$hp_table = $g5['module_bbs_duel_hp_table'];
$g5['module_bbs_duel_history_table'] = G5_TABLE_PREFIX.'module_bbs_duel_history';
$history_table = $g5['module_bbs_duel_history_table'];

sql_query("CREATE TABLE IF NOT EXISTS `{$enemy_table}` (
    `de_id` int(11) NOT NULL AUTO_INCREMENT,
    `de_name` varchar(100) NOT NULL DEFAULT '',
    `de_order` int(11) unsigned NOT NULL DEFAULT '0',
    `de_sense` tinyint(3) unsigned NOT NULL DEFAULT '1',
    `de_insight` tinyint(3) unsigned NOT NULL DEFAULT '1',
    `de_courage` tinyint(3) unsigned NOT NULL DEFAULT '1',
    `de_stats` text NULL,
    `de_allowed_stats` varchar(128) NOT NULL DEFAULT '',
    `de_datetime` datetime NOT NULL,
    PRIMARY KEY (`de_id`),
    UNIQUE KEY `de_name` (`de_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", false);

mdl_db_add_column($enemy_table, 'de_sense', "tinyint(3) unsigned NOT NULL DEFAULT '1'");
mdl_db_add_column($enemy_table, 'de_insight', "tinyint(3) unsigned NOT NULL DEFAULT '1'");
mdl_db_add_column($enemy_table, 'de_courage', "tinyint(3) unsigned NOT NULL DEFAULT '1'");
mdl_db_add_column($enemy_table, 'de_stats', "text NULL");
mdl_db_add_column($enemy_table, 'de_allowed_stats', "varchar(128) NOT NULL DEFAULT ''");
mdl_db_add_column($enemy_table, 'de_order', "int(11) unsigned NOT NULL DEFAULT '0'");
mdl_db_add_column($enemy_table, 'de_hp_max', "smallint(5) unsigned NOT NULL DEFAULT '5'");
mdl_db_add_column($enemy_table, 'de_damage_to_player', "smallint(5) unsigned NOT NULL DEFAULT '1'");

sql_query("CREATE TABLE IF NOT EXISTS {$hp_table} (
    hp_type varchar(16) NOT NULL DEFAULT '',
    hp_target_id int(11) unsigned NOT NULL DEFAULT '0',
    hp_max smallint(5) unsigned NOT NULL DEFAULT '5',
    hp_current smallint(5) unsigned NOT NULL DEFAULT '5',
    hp_updated_at datetime NOT NULL,
    PRIMARY KEY (hp_type, hp_target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", false);

sql_query("CREATE TABLE IF NOT EXISTS `{$history_table}` (
    history_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    event_type varchar(16) NOT NULL DEFAULT '',
    event_datetime datetime NOT NULL,
    actor_name varchar(255) NOT NULL DEFAULT '',
    actor_type varchar(16) NOT NULL DEFAULT '',
    actor_id int(11) unsigned NOT NULL DEFAULT '0',
    target_name varchar(255) NOT NULL DEFAULT '',
    target_type varchar(16) NOT NULL DEFAULT '',
    target_id int(11) unsigned NOT NULL DEFAULT '0',
    stat_name varchar(255) NOT NULL DEFAULT '',
    actor_roll varchar(255) NOT NULL DEFAULT '',
    actor_dice_sum smallint(5) unsigned NOT NULL DEFAULT '0',
    actor_modifier smallint(6) NOT NULL DEFAULT '0',
    actor_score smallint(5) unsigned NOT NULL DEFAULT '0',
    target_roll varchar(255) NOT NULL DEFAULT '',
    target_dice_sum smallint(5) unsigned NOT NULL DEFAULT '0',
    target_modifier smallint(6) NOT NULL DEFAULT '0',
    target_score smallint(5) unsigned NOT NULL DEFAULT '0',
    winner_name varchar(255) NOT NULL DEFAULT '',
    actor_hp_before smallint(5) unsigned NOT NULL DEFAULT '0',
    actor_hp_after smallint(5) unsigned NOT NULL DEFAULT '0',
    target_hp_before smallint(5) unsigned NOT NULL DEFAULT '0',
    target_hp_after smallint(5) unsigned NOT NULL DEFAULT '0',
    event_note varchar(255) NOT NULL DEFAULT '',
    source_ref varchar(160) NULL DEFAULT NULL,
    PRIMARY KEY (history_id),
    UNIQUE KEY source_ref (source_ref),
    KEY event_datetime (event_datetime),
    KEY event_type (event_type),
    KEY winner_name (winner_name),
    KEY actor_name (actor_name),
    KEY target_name (target_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", false);

if (function_exists('mdl_setting_get') && mdl_setting_get('bbs_duel', 'player_hp_default', null) === null) {
    mdl_setting_set('bbs_duel', 'player_hp_default', 5);
}
if (function_exists('mdl_setting_get') && mdl_setting_get('bbs_duel', 'default_damage', null) === null) {
    mdl_setting_set('bbs_duel', 'default_damage', 1);
}
sql_query("UPDATE `{$enemy_table}` SET de_order=de_id WHERE de_order=0", false);

// 기존 3개 고정 스탯 값을 현재 관리자 스탯 라인업의 ID별 데이터로 옮긴다.
$status_lineup = bbs_duel_status_definitions();
$legacy_fields = array('de_sense', 'de_insight', 'de_courage');
$rs = sql_query("SELECT de_id, de_sense, de_insight, de_courage, de_stats, de_allowed_stats FROM `{$enemy_table}`", false);
while ($rs && $row = sql_fetch_array($rs)) {
    $id = (int)$row['de_id'];
    if (trim((string)$row['de_stats']) === '') {
        $stats = array();
        $slot = 0;
        foreach ($status_lineup as $status_id => $definition) {
            if (!isset($legacy_fields[$slot])) break;
            $stats[(int)$status_id] = max(1, min(100, (int)$row[$legacy_fields[$slot]]));
            $slot++;
        }
        if ($stats) {
            $json = sql_real_escape_string(json_encode($stats));
            sql_query("UPDATE `{$enemy_table}` SET de_stats='{$json}' WHERE de_id='{$id}' LIMIT 1", false);
        }
    }

    // 이전 모듈에서 저장한 sense/insight/courage 제한 토큰을 현재 스탯 ID로 변환한다.
    $raw = trim((string)$row['de_allowed_stats']);
    if ($raw !== '') {
        $legacy_ids = array('sense' => 0, 'insight' => 1, 'courage' => 2);
        $tokens = explode('|', $raw);
        $converted = array();
        $is_legacy = true;
        foreach ($tokens as $token) {
            $parts = explode('+', $token);
            $new_parts = array();
            foreach ($parts as $part) {
                if (!isset($legacy_ids[$part])) {
                    $is_legacy = false;
                    break 2;
                }
                $slot = $legacy_ids[$part];
                $lineup_ids = array_keys($status_lineup);
                if (!isset($lineup_ids[$slot])) {
                    $is_legacy = false;
                    break 2;
                }
                $new_parts[] = 's'.(int)$lineup_ids[$slot];
            }
            $converted[] = implode('+', $new_parts);
        }
        if ($is_legacy && $converted) {
            $value = sql_real_escape_string(implode('|', $converted));
            sql_query("UPDATE `{$enemy_table}` SET de_allowed_stats='{$value}' WHERE de_id='{$id}' LIMIT 1", false);
        }
    }
}

// 최초 적용·재설치 시 기존 승인 캐릭터와 기존 에너미의 HP를 현재 기본값으로 초기화한다.
$hp_default = bbs_duel_hp_default();
$player_rs = sql_query("SELECT ch_id FROM {$g5['character_table']} WHERE ch_state='승인' AND ch_type IN ('main','sub')", false);
while ($player_rs && $player = sql_fetch_array($player_rs)) {
    bbs_duel_hp_initialize('character', (int)$player['ch_id'], $hp_default);
}
$enemy_hp_rs = sql_query("SELECT de_id, de_hp_max FROM {$enemy_table}", false);
while ($enemy_hp_rs && $enemy = sql_fetch_array($enemy_hp_rs)) {
    bbs_duel_hp_initialize('enemy', (int)$enemy['de_id'], (int)$enemy['de_hp_max']);
}

mdl_db_add_write_column('wr_duel_type', "varchar(12) NOT NULL DEFAULT ''");
mdl_db_add_write_column('wr_duel_target_type', "varchar(12) NOT NULL DEFAULT ''");
mdl_db_add_write_column('wr_duel_target_id', "int(11) NOT NULL DEFAULT '0'");
mdl_db_add_write_column('wr_duel_target_name', "varchar(255) NOT NULL DEFAULT ''");
mdl_db_add_write_column('wr_duel_stat_name', "varchar(255) NOT NULL DEFAULT ''");
mdl_db_add_write_column('wr_duel_actor_name', "varchar(255) NOT NULL DEFAULT ''");
mdl_db_add_write_column('wr_duel_actor_ch_id', "int(11) unsigned NOT NULL DEFAULT '0'");
mdl_db_add_write_column('wr_duel_actor_roll', "varchar(255) NOT NULL DEFAULT ''");
mdl_db_add_write_column('wr_duel_target_roll', "varchar(255) NOT NULL DEFAULT ''");
mdl_db_add_write_column('wr_duel_actor_score', "tinyint(3) unsigned NOT NULL DEFAULT '0'");
mdl_db_add_write_column('wr_duel_target_score', "tinyint(3) unsigned NOT NULL DEFAULT '0'");
mdl_db_add_write_column('wr_duel_result', "varchar(16) NOT NULL DEFAULT ''");
mdl_db_add_write_column('wr_duel_actor_hp_before', "smallint(5) unsigned NOT NULL DEFAULT '0'");
mdl_db_add_write_column('wr_duel_actor_hp_after', "smallint(5) unsigned NOT NULL DEFAULT '0'");
mdl_db_add_write_column('wr_duel_target_hp_before', "smallint(5) unsigned NOT NULL DEFAULT '0'");
mdl_db_add_write_column('wr_duel_target_hp_after', "smallint(5) unsigned NOT NULL DEFAULT '0'");
mdl_db_add_write_column('wr_duel_hp_damage', "smallint(5) unsigned NOT NULL DEFAULT '0'");

// 100D2 결과(최대 199자)를 보존할 수 있도록 기존 기록 컬럼을 확장한다.
mdl_db_each_write_table(function ($write_table) {
    $table = mdl_db_ident($write_table);
    foreach (array('wr_duel_actor_roll', 'wr_duel_target_roll') as $column) {
        if (mdl_db_column_exists($table, $column)) {
            mdl_db_sql_query("ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` varchar(255) NOT NULL DEFAULT ''");
        }
    }
});

// 기존 대련·결투도 새 기록 화면에서 조회되도록 멱등하게 이관한다.
mdl_db_each_write_table(function ($write_table, $source_name) {
    $table = mdl_db_ident($write_table);
    if (!mdl_db_column_exists($table, 'wr_duel_type')) return;
    $rs = sql_query("SELECT * FROM `{$table}` WHERE wr_duel_type IN ('duel','spar') AND wr_is_comment=0", false);
    while ($rs && $row = sql_fetch_array($rs)) {
        bbs_duel_history_import_battle($row, 'legacy|'.$source_name.'|'.(int)$row['wr_id']);
    }
});

// 관리자에서 자유롭게 정한 긴 스탯 이름과 2스탯 조합명도 보존한다.
mdl_db_each_write_table(function ($write_table) {
    $table = mdl_db_ident($write_table);
    if (mdl_db_column_exists($table, 'wr_duel_stat_name')) {
        mdl_db_sql_query("ALTER TABLE `{$table}` MODIFY COLUMN `wr_duel_stat_name` varchar(255) NOT NULL DEFAULT ''");
    }
});
