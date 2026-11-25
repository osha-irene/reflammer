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
      `mo_image_url` varchar(1000) NOT NULL DEFAULT '',
      `mo_image_file` varchar(1000) NOT NULL DEFAULT '',
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
      `ml_nominal_sender` varchar(100) DEFAULT NULL,
      `re_ch_id` int unsigned NOT NULL DEFAULT 0,
      `re_ch_name` varchar(255) NOT NULL DEFAULT '',
      `ml_nominal_recipient` varchar(100) DEFAULT NULL,
      `ml_anonymous` tinyint NOT NULL DEFAULT 0,
      `ml_random` tinyint NOT NULL DEFAULT 0,
      `ml_subject` varchar(255) NOT NULL DEFAULT '',
      `ml_content` mediumtext NOT NULL,
      `ml_owl_image` varchar(1000) NOT NULL DEFAULT '',
      `ml_pet_it_id` int unsigned NOT NULL DEFAULT 0,
      `ml_pet_name` varchar(200) NOT NULL DEFAULT '',
      `ml_pet_image` varchar(1000) NOT NULL DEFAULT '',
      `mo_id` int unsigned NOT NULL DEFAULT 0,
      `mo_name` varchar(60) NOT NULL DEFAULT '',
      `mo_hours` int unsigned NOT NULL DEFAULT 0,
      `ml_sent_datetime` datetime NOT NULL,
      `ml_due_datetime` datetime NOT NULL,
      `ml_status` varchar(16) NOT NULL DEFAULT 'in_transit',
      `ml_delivered_datetime` datetime DEFAULT NULL,
      `ml_read_datetime` datetime DEFAULT NULL,
      `ml_re_last_visit` datetime DEFAULT NULL,
      `ml_notice_sent` tinyint NOT NULL DEFAULT 0,
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

    'pet' => "CREATE TABLE IF NOT EXISTS `{$rm_prefix}pet` (
      `ch_id` int unsigned NOT NULL,
      `it_id` int unsigned NOT NULL,
      `registered_datetime` datetime NOT NULL,
      PRIMARY KEY (`ch_id`,`it_id`),
      KEY `item` (`it_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
);
foreach ($rm_tables as $rm_create_sql) sql_query($rm_create_sql, false);

// 이미 설치된 데이터베이스에 이미지·펫 스냅샷 필드를 추가한다.
$rm_owl_table = $rm_prefix.'owl';
$rm_letter_table = $rm_prefix.'letter';
foreach (array(
    'mo_image_url' => "ALTER TABLE `{$rm_owl_table}` ADD `mo_image_url` varchar(1000) NOT NULL DEFAULT '' AFTER `mo_desc`",
    'mo_image_file' => "ALTER TABLE `{$rm_owl_table}` ADD `mo_image_file` varchar(1000) NOT NULL DEFAULT '' AFTER `mo_image_url`",
) as $rm_col => $rm_sql) {
    $rm_has_col = sql_fetch("SHOW COLUMNS FROM `{$rm_owl_table}` LIKE '{$rm_col}'", false);
    if (empty($rm_has_col['Field'])) sql_query($rm_sql, false);
}
$rm_letter_image_cols = array(
    'ml_owl_image' => "ALTER TABLE `{$rm_letter_table}` ADD `ml_owl_image` varchar(1000) NOT NULL DEFAULT '' AFTER `ml_content`",
    'ml_pet_it_id' => "ALTER TABLE `{$rm_letter_table}` ADD `ml_pet_it_id` int unsigned NOT NULL DEFAULT 0 AFTER `ml_owl_image`",
    'ml_pet_name' => "ALTER TABLE `{$rm_letter_table}` ADD `ml_pet_name` varchar(200) NOT NULL DEFAULT '' AFTER `ml_pet_it_id`",
    'ml_pet_image' => "ALTER TABLE `{$rm_letter_table}` ADD `ml_pet_image` varchar(1000) NOT NULL DEFAULT '' AFTER `ml_pet_name`",
);
foreach ($rm_letter_image_cols as $rm_col => $rm_sql) {
    $rm_has_col = sql_fetch("SHOW COLUMNS FROM `{$rm_letter_table}` LIKE '{$rm_col}'", false);
    if (empty($rm_has_col['Field'])) sql_query($rm_sql, false);
}

// v1.0.0 으로 이미 설치된 사이트를 위한 멱등 ALTER (재설치 시에만 실행됨).
$rm_random_col = sql_fetch("SHOW COLUMNS FROM `{$rm_letter_table}` LIKE 'ml_random'", false);
if (empty($rm_random_col['Field'])) {
    sql_query("ALTER TABLE `{$rm_letter_table}` ADD `ml_random` tinyint NOT NULL DEFAULT 0 AFTER `ml_anonymous`", false);
}
$rm_due_index = sql_fetch("SHOW INDEX FROM `{$rm_letter_table}` WHERE Key_name='due_status'", false);
if (empty($rm_due_index['Key_name'])) {
    sql_query("ALTER TABLE `{$rm_letter_table}` ADD KEY `due_status` (`ml_status`,`ml_due_datetime`)", false);
}
$rm_last_visit_col = sql_fetch("SHOW COLUMNS FROM `{$rm_letter_table}` LIKE 'ml_re_last_visit'", false);
if (empty($rm_last_visit_col['Field'])) {
    sql_query("ALTER TABLE `{$rm_letter_table}` ADD `ml_re_last_visit` datetime DEFAULT NULL AFTER `ml_read_datetime`", false);
}
$rm_nominal_sender_col = sql_fetch("SHOW COLUMNS FROM `{$rm_letter_table}` LIKE 'ml_nominal_sender'", false);
if (empty($rm_nominal_sender_col['Field'])) {
    sql_query("ALTER TABLE `{$rm_letter_table}` ADD `ml_nominal_sender` varchar(100) DEFAULT NULL AFTER `se_mb_id`", false);
}
$rm_nominal_recipient_col = sql_fetch("SHOW COLUMNS FROM `{$rm_letter_table}` LIKE 'ml_nominal_recipient'", false);
if (empty($rm_nominal_recipient_col['Field'])) {
    sql_query("ALTER TABLE `{$rm_letter_table}` ADD `ml_nominal_recipient` varchar(100) DEFAULT NULL AFTER `re_ch_name`", false);
}
$rm_notice_col = sql_fetch("SHOW COLUMNS FROM `{$rm_letter_table}` LIKE 'ml_notice_sent'", false);
if (empty($rm_notice_col['Field'])) {
    sql_query("ALTER TABLE `{$rm_letter_table}` ADD `ml_notice_sent` tinyint NOT NULL DEFAULT 0 AFTER `ml_re_last_visit`", false);
}

// 이미 생성된 편지 알림도 사이트명 대신 편지에 적힌 발신 이름으로 표시한다.
if (!empty($g5['call_board_table'])) {
    $rm_call_table_exists = sql_fetch("SHOW TABLES LIKE '".sql_real_escape_string($g5['call_board_table'])."'", false);
    if (!empty($rm_call_table_exists)) {
        $rm_letter_link_prefix = sql_real_escape_string(G5_MDL_URL.'/reflammer_mail/index.php?action=view&id=');
        sql_query("UPDATE `{$g5['call_board_table']}` AS call_row
            INNER JOIN `{$rm_letter_table}` AS letter_row
              ON call_row.bc_link=CONCAT('{$rm_letter_link_prefix}', letter_row.ml_id)
            SET call_row.mb_name=CASE
                WHEN letter_row.ml_nominal_sender IS NOT NULL
                    THEN IF(TRIM(letter_row.ml_nominal_sender)='', '익명', letter_row.ml_nominal_sender)
                WHEN letter_row.ml_anonymous=1 THEN '익명'
                ELSE letter_row.se_ch_name
            END
            WHERE call_row.bc_type='reflammer_mail'", false);
    }
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
