<?php
if (!defined('_GNUBOARD_')) exit;

// 별도 컬럼으로 일반 주사위 결과와 독립적으로 저장한다.
mdl_db_add_write_column('wr_merlin_dice', "varchar(255) NOT NULL DEFAULT ''");
mdl_db_add_write_column('wr_merlin_stat', "varchar(500) NOT NULL DEFAULT ''");
