<?php
if (!defined('_GNUBOARD_')) exit;
global $g5;

// install.php 는 module/_registry.php 의 mdl_activate()/mdl_run_install() 안에서
// include 되어 그 함수의 로컬 스코프를 그대로 공유한다 ($name, $state, $active, $f 등
// 이미 쓰이고 있다). 변수명 충돌로 모듈 활성화 상태가 깨지는 것을 막기 위해
// 이 파일의 모든 변수는 rm_ 접두사로 네임스페이스를 둔다.
$rm_prefix = G5_TABLE_PREFIX.'mail_';

$rm_tables = array(
    'owl' => "CREATE TABLE IF NOT EXISTS `{$rm_prefix}owl` (
      `mo_id` int unsigned NOT NULL AUTO_INCREMENT,
      `mo_name` varchar(60) NOT NULL DEFAULT '',
      `mo_desc` varchar(255) NOT NULL DEFAULT '',
      `mo_hours` int unsigned NOT NULL DEFAULT 0,
      `mo_cost` int unsigned NOT NULL DEFAULT 0,
      `mo_order` int NOT NULL DEFAULT 0,
      `mo_active` tinyint NOT NULL DEFAULT 1,
      PRIMARY KEY (`mo_id`),
      KEY `active_order` (`mo_active`,`mo_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'letter' => "CREATE TABLE IF NOT EXISTS `{$rm_prefix}letter` (
      `ml_id` int unsigned NOT NULL AUTO_INCREMENT,
      `se_ch_id` int unsigned NOT NULL DEFAULT 0,
      `se_ch_name` varchar(255) NOT NULL DEFAULT '',
      `se_mb_id` varchar(20) NOT NULL DEFAULT '',
      `re_ch_id` int unsigned NOT NULL DEFAULT 0,
      `re_ch_name` varchar(255) NOT NULL DEFAULT '',
      `ml_anonymous` tinyint NOT NULL DEFAULT 0,
      `ml_random` tinyint NOT NULL DEFAULT 0,
      `ml_subject` varchar(255) NOT NULL DEFAULT '',
      `ml_content` mediumtext NOT NULL,
      `mo_id` int unsigned NOT NULL DEFAULT 0,
      `mo_name` varchar(60) NOT NULL DEFAULT '',
      `mo_hours` int unsigned NOT NULL DEFAULT 0,
      `ml_sent_datetime` datetime NOT NULL,
      `ml_due_datetime` datetime NOT NULL,
      `ml_status` varchar(16) NOT NULL DEFAULT 'in_transit',
      `ml_delivered_datetime` datetime DEFAULT NULL,
      `ml_read_datetime` datetime DEFAULT NULL,
      `ml_se_deleted` tinyint NOT NULL DEFAULT 0,
      `ml_re_deleted` tinyint NOT NULL DEFAULT 0,
      PRIMARY KEY (`ml_id`),
      KEY `recipient_status` (`re_ch_id`,`ml_status`),
      KEY `sender` (`se_ch_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'item' => "CREATE TABLE IF NOT EXISTS `{$rm_prefix}item` (
      `mi_id` int unsigned NOT NULL AUTO_INCREMENT,
      `ml_id` int unsigned NOT NULL,
      `it_id` int unsigned NOT NULL,
      `it_name` varchar(200) NOT NULL DEFAULT '',
      `mi_delivered` tinyint NOT NULL DEFAULT 0,
      PRIMARY KEY (`mi_id`),
      KEY `letter` (`ml_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
);
foreach ($rm_tables as $rm_create_sql) sql_query($rm_create_sql, false);

// v1.0.0 으로 이미 설치된 사이트를 위한 멱등 ALTER (재설치 시에만 실행됨).
$rm_letter_table = $rm_prefix.'letter';
$rm_random_col = sql_fetch("SHOW COLUMNS FROM `{$rm_letter_table}` LIKE 'ml_random'", false);
if (empty($rm_random_col['Field'])) {
    sql_query("ALTER TABLE `{$rm_letter_table}` ADD `ml_random` tinyint NOT NULL DEFAULT 0 AFTER `ml_anonymous`", false);
}

if (!sql_fetch("SELECT mo_id FROM `{$rm_prefix}owl` LIMIT 1", false)) {
    // 운영자가 관리자 > 편지 > 부엉이 종류 화면에서 자유롭게 수정/추가/삭제할 수 있는 예시 값입니다.
    $rm_default_owls = array(
        array('일반 부엉이', '무난한 속도로 배달하는 기본 부엉이입니다.', 12, 100, 10),
        array('급행 부엉이', '비용이 들지만 빠르게 배달합니다.', 1, 500, 20),
        array('야생 부엉이', '느리지만 비용 없이 부탁할 수 있습니다.', 48, 0, 30),
    );
    foreach ($rm_default_owls as $rm_owl) {
        $rm_owl_name_sql = sql_real_escape_string($rm_owl[0]);
        $rm_owl_desc_sql = sql_real_escape_string($rm_owl[1]);
        sql_query("INSERT INTO `{$rm_prefix}owl` (mo_name,mo_desc,mo_hours,mo_cost,mo_order) VALUES ('{$rm_owl_name_sql}','{$rm_owl_desc_sql}',{$rm_owl[2]},{$rm_owl[3]},{$rm_owl[4]})", false);
    }
}
