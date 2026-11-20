<?php
if (!defined('_GNUBOARD_')) exit;

global $g5;
$prefix = G5_TABLE_PREFIX.'module_bbs_trait_';
$g5['module_bbs_trait_catalog_table'] = $prefix.'catalog';
$g5['module_bbs_trait_owned_table'] = $prefix.'owned';
$g5['module_bbs_trait_use_table'] = $prefix.'use';
$g5['module_bbs_trait_slots_table'] = $prefix.'slots';
$g5['module_bbs_trait_item_use_table'] = $prefix.'item_use';

$catalog = $g5['module_bbs_trait_catalog_table'];
$owned = $g5['module_bbs_trait_owned_table'];
$use = $g5['module_bbs_trait_use_table'];
$slots = $g5['module_bbs_trait_slots_table'];
$item_use = $g5['module_bbs_trait_item_use_table'];

sql_query("CREATE TABLE IF NOT EXISTS `{$catalog}` (
    trait_id int(11) unsigned NOT NULL AUTO_INCREMENT,
    trait_name varchar(100) NOT NULL DEFAULT '',
    trait_icon varchar(2048) NOT NULL DEFAULT '',
    trait_description text NOT NULL,
    effect_type varchar(40) NOT NULL DEFAULT '',
    effect_value smallint(6) NOT NULL DEFAULT '0',
    effect_stat_id int(11) unsigned NOT NULL DEFAULT '0',
    comment_enabled tinyint(1) unsigned NOT NULL DEFAULT '0',
    usage_limit int(11) unsigned NULL DEFAULT NULL,
    trait_enabled tinyint(1) unsigned NOT NULL DEFAULT '1',
    trait_created_at datetime NOT NULL,
    trait_updated_at datetime NOT NULL,
    PRIMARY KEY (trait_id),
    KEY trait_enabled (trait_enabled),
    KEY trait_name (trait_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", false);

mdl_db_add_column($catalog, 'effect_stat_id', "int(11) unsigned NOT NULL DEFAULT '0'");
if (!mdl_db_column_exists($catalog, 'effect_stat_id')) {
    error_log('bbs_trait: catalog schema is missing the effect_stat_id column after installation.');
}
mdl_db_add_column($catalog, 'comment_enabled', "tinyint(1) unsigned NOT NULL DEFAULT '0'");
if (!mdl_db_column_exists($catalog, 'comment_enabled')) {
    error_log('bbs_trait: catalog schema is missing the comment_enabled column after installation.');
}

if (function_exists('mdl_db_column_exists') && mdl_db_column_exists($catalog, 'trait_icon')) {
    mdl_db_sql_query("ALTER TABLE `{$catalog}` MODIFY COLUMN `trait_icon` varchar(2048) NOT NULL DEFAULT ''");
}

sql_query("CREATE TABLE IF NOT EXISTS `{$owned}` (
    ch_id int(11) unsigned NOT NULL,
    trait_id int(11) unsigned NOT NULL,
    granted_by varchar(20) NOT NULL DEFAULT '',
    granted_at datetime NOT NULL,
    PRIMARY KEY (ch_id, trait_id),
    KEY trait_id (trait_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", false);

sql_query("CREATE TABLE IF NOT EXISTS `{$slots}` (
    ch_id int(11) unsigned NOT NULL,
    slots_unlocked smallint(5) unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (ch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", false);

sql_query("CREATE TABLE IF NOT EXISTS `{$use}` (
    use_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    ch_id int(11) unsigned NOT NULL,
    trait_id int(11) unsigned NOT NULL,
    period_start datetime NOT NULL,
    source_ref varchar(160) NOT NULL DEFAULT '',
    board_name varchar(20) NOT NULL DEFAULT '',
    post_id int(11) unsigned NOT NULL DEFAULT '0',
    used_at datetime NOT NULL,
    PRIMARY KEY (use_id),
    UNIQUE KEY source_ref (source_ref),
    KEY owner_period (ch_id, trait_id, period_start),
    KEY trait_id (trait_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", false);

sql_query("CREATE TABLE IF NOT EXISTS `{$item_use}` (
    in_id int(11) unsigned NOT NULL,
    ch_id int(11) unsigned NOT NULL,
    trait_id int(11) unsigned NOT NULL,
    replaced_trait_id int(11) unsigned NOT NULL DEFAULT '0',
    used_at datetime NOT NULL,
    PRIMARY KEY (in_id),
    KEY owner_trait (ch_id, trait_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", false);

mdl_db_add_write_column('wr_trait_comment_trait_id', "int(11) unsigned NOT NULL DEFAULT '0'");
mdl_db_add_write_column('wr_trait_comment_character_id', "int(11) unsigned NOT NULL DEFAULT '0'");
mdl_db_add_write_column('wr_trait_comment_period_start', "varchar(19) NOT NULL DEFAULT ''");
mdl_db_add_write_column('wr_trait_comment_name', "varchar(100) NOT NULL DEFAULT ''");
mdl_db_add_write_column('wr_trait_comment_icon', "varchar(2048) NOT NULL DEFAULT ''");
mdl_db_add_write_column('wr_trait_comment_description', "text NULL");

if (function_exists('mdl_setting_get') && function_exists('mdl_setting_set')) {
    if (mdl_setting_get('bbs_trait', 'slot_count', null) === null) {
        mdl_setting_set('bbs_trait', 'slot_count', 5);
    }
    if (mdl_setting_get('bbs_trait', 'base_slot_count', null) === null) {
        mdl_setting_set('bbs_trait', 'base_slot_count', 3);
    }
    if (mdl_setting_get('bbs_trait', 'active_period_mode', null) === null) {
        mdl_setting_set('bbs_trait', 'active_period_mode', 'weekly');
    }
    if (mdl_setting_get('bbs_trait', 'active_period_hours', null) === null) {
        mdl_setting_set('bbs_trait', 'active_period_hours', 1);
    }
}
