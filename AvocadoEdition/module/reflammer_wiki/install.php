<?php
if (!defined('_GNUBOARD_')) exit;
global $g5;
$prefix = G5_TABLE_PREFIX.'wiki_';
$tables = array(
    'category' => "CREATE TABLE IF NOT EXISTS `{$prefix}category` (
      `wc_id` int unsigned NOT NULL AUTO_INCREMENT, `wc_parent` int unsigned NOT NULL DEFAULT 0,
      `wc_name` varchar(100) NOT NULL, `wc_order` int NOT NULL DEFAULT 0, `wc_active` tinyint NOT NULL DEFAULT 1,
      PRIMARY KEY (`wc_id`), KEY `parent_order` (`wc_parent`,`wc_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'entry' => "CREATE TABLE IF NOT EXISTS `{$prefix}entry` (
      `we_id` int unsigned NOT NULL AUTO_INCREMENT, `wc_id` int unsigned NOT NULL,
      `we_title` varchar(255) NOT NULL, `we_content` mediumtext NOT NULL, `we_html` tinyint NOT NULL DEFAULT 0, `we_image` varchar(1000) NOT NULL DEFAULT '', `we_tags` text NOT NULL, `we_subject_character` varchar(255) NOT NULL DEFAULT '',
      `we_pub_wc_id` int unsigned NOT NULL DEFAULT 0, `we_pub_title` varchar(255) NOT NULL DEFAULT '', `we_pub_content` mediumtext NOT NULL, `we_pub_html` tinyint NOT NULL DEFAULT 0, `we_pub_subject_character` varchar(255) NOT NULL DEFAULT '',
      `we_pub_image` varchar(1000) NOT NULL DEFAULT '', `we_pub_tags` text NOT NULL,
      `we_status` varchar(16) NOT NULL DEFAULT 'pending', `we_author_id` varchar(255) NOT NULL,
      `we_author_name` varchar(255) NOT NULL, `we_author_character` varchar(255) NOT NULL DEFAULT '',
      `we_created` datetime NOT NULL, `we_updated` datetime NOT NULL, `we_published` datetime DEFAULT NULL,
      `we_review_note` varchar(1000) NOT NULL DEFAULT '', PRIMARY KEY (`we_id`),
      KEY `status_date` (`we_status`,`we_published`), KEY `category_status` (`we_pub_wc_id`,`we_status`), KEY `author_status` (`we_author_id`,`we_status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'tag' => "CREATE TABLE IF NOT EXISTS `{$prefix}tag` (
      `wt_id` int unsigned NOT NULL AUTO_INCREMENT, `wt_name` varchar(80) NOT NULL,
      PRIMARY KEY (`wt_id`), UNIQUE KEY `tag_name` (`wt_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'setting' => "CREATE TABLE IF NOT EXISTS `{$prefix}setting` (
      `ws_key` varchar(80) NOT NULL, `ws_value` text NOT NULL, PRIMARY KEY (`ws_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
);
foreach ($tables as $sql) sql_query($sql, false);
// Additive schema repair for reinstallation after an early module version.
$entry_table = $prefix.'entry';
$column = sql_fetch("SHOW COLUMNS FROM `{$entry_table}` LIKE 'we_subject_character'", false);
if (empty($column['Field'])) sql_query("ALTER TABLE `{$entry_table}` ADD `we_subject_character` varchar(255) NOT NULL DEFAULT '' AFTER `we_tags`", false);
$column = sql_fetch("SHOW COLUMNS FROM `{$entry_table}` LIKE 'we_pub_subject_character'", false);
if (empty($column['Field'])) sql_query("ALTER TABLE `{$entry_table}` ADD `we_pub_subject_character` varchar(255) NOT NULL DEFAULT '' AFTER `we_pub_content`", false);
$column = sql_fetch("SHOW COLUMNS FROM `{$entry_table}` LIKE 'we_html'", false);
if (empty($column['Field'])) sql_query("ALTER TABLE `{$entry_table}` ADD `we_html` tinyint NOT NULL DEFAULT 0 AFTER `we_content`", false);
$column = sql_fetch("SHOW COLUMNS FROM `{$entry_table}` LIKE 'we_pub_html'", false);
if (empty($column['Field'])) sql_query("ALTER TABLE `{$entry_table}` ADD `we_pub_html` tinyint NOT NULL DEFAULT 0 AFTER `we_pub_content`", false);
if (!sql_fetch("SELECT wc_id FROM `{$prefix}category` LIMIT 1")) {
    $defaults = array('공지','역사','왕가','가호','경제','지리','문화','가문','길드','단체','인명사전','생물');
    foreach ($defaults as $i => $name) {
        $name = sql_real_escape_string($name);
        sql_query("INSERT INTO `{$prefix}category` (wc_parent,wc_name,wc_order) VALUES (0,'{$name}',".(($i+1)*10).")", false);
    }
}
