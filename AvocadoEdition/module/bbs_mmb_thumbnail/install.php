<?php
if (!defined('_GNUBOARD_')) exit;

require_once __DIR__.'/lib.php';

$table = bbs_mmb_thumbnail_table();
sql_query("CREATE TABLE IF NOT EXISTS `{$table}` (
    `bo_table` varchar(20) NOT NULL DEFAULT '',
    `wr_id` int(11) unsigned NOT NULL DEFAULT '0',
    `thumbnail_url` text NOT NULL,
    `thumbnail_source` varchar(16) NOT NULL DEFAULT '',
    `created_at` datetime NOT NULL,
    PRIMARY KEY (`bo_table`, `wr_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if (mdl_setting_get('bbs_mmb_thumbnail', 'profile_ar_code', null) === null) {
    mdl_setting_set('bbs_mmb_thumbnail', 'profile_ar_code', '');
}
