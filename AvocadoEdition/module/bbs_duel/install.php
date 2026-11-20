<?php
if (!defined('_GNUBOARD_')) exit;

require_once __DIR__.'/lib.php';
global $g5;
$g5['module_bbs_duel_enemy_table'] = G5_TABLE_PREFIX.'module_bbs_duel_enemy';
$enemy_table = $g5['module_bbs_duel_enemy_table'];

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

mdl_db_add_write_column('wr_duel_type', "varchar(12) NOT NULL DEFAULT ''");
mdl_db_add_write_column('wr_duel_target_type', "varchar(12) NOT NULL DEFAULT ''");
mdl_db_add_write_column('wr_duel_target_id', "int(11) NOT NULL DEFAULT '0'");
mdl_db_add_write_column('wr_duel_target_name', "varchar(255) NOT NULL DEFAULT ''");
mdl_db_add_write_column('wr_duel_stat_name', "varchar(255) NOT NULL DEFAULT ''");
mdl_db_add_write_column('wr_duel_actor_name', "varchar(255) NOT NULL DEFAULT ''");
mdl_db_add_write_column('wr_duel_actor_roll', "varchar(255) NOT NULL DEFAULT ''");
mdl_db_add_write_column('wr_duel_target_roll', "varchar(255) NOT NULL DEFAULT ''");
mdl_db_add_write_column('wr_duel_actor_score', "tinyint(3) unsigned NOT NULL DEFAULT '0'");
mdl_db_add_write_column('wr_duel_target_score', "tinyint(3) unsigned NOT NULL DEFAULT '0'");
mdl_db_add_write_column('wr_duel_result', "varchar(16) NOT NULL DEFAULT ''");

// 100D2 결과(최대 199자)를 보존할 수 있도록 기존 기록 컬럼을 확장한다.
mdl_db_each_write_table(function ($write_table) {
    $table = mdl_db_ident($write_table);
    foreach (array('wr_duel_actor_roll', 'wr_duel_target_roll') as $column) {
        if (mdl_db_column_exists($table, $column)) {
            mdl_db_sql_query("ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` varchar(255) NOT NULL DEFAULT ''");
        }
    }
});

// 관리자에서 자유롭게 정한 긴 스탯 이름과 2스탯 조합명도 보존한다.
mdl_db_each_write_table(function ($write_table) {
    $table = mdl_db_ident($write_table);
    if (mdl_db_column_exists($table, 'wr_duel_stat_name')) {
        mdl_db_sql_query("ALTER TABLE `{$table}` MODIFY COLUMN `wr_duel_stat_name` varchar(255) NOT NULL DEFAULT ''");
    }
});
