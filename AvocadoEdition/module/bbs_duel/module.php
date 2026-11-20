<?php
if (!defined('_GNUBOARD_')) exit;

return array(
    'name'           => 'bbs_duel',
    'title'          => '대련·결투',
    'description'    => 'MMB ACTION에서 대련·결투 상대 지정과 양측 스탯 주사위 판정을 기록합니다.',
    'version'        => '0.16.0',
    'author'         => 'ribo',
    'board_scope'    => true,
    'board_default'  => 'off',
    'scopes'         => array('write'),
    'depends'        => array('bbs_merlin_dice'),
    'requires_hooks' => array(
        'bbs_write_form_action_options',
        'bbs_write_form_action_panels',
        'bbs_custom_action',
        'bbs_view_row_extra',
        'bbs_enqueue_assets',
        'write_after',
        'character_create_after',
        'character_delete_after',
        'member_viewer_info',
    ),
    'admin_menus'    => array(
        'auto' => array(
            array('000', '대련·결투', G5_MDL_ADMIN_URL.'/module.php?m=bbs_duel&p=enemy_list'),
            array('100', '에너미 관리', G5_MDL_ADMIN_URL.'/module.php?m=bbs_duel&p=enemy_list'),
            array('200', 'HP 관리', G5_MDL_ADMIN_URL.'/module.php?m=bbs_duel&p=hp'),
            array('300', '결투·HP 기록', G5_MDL_ADMIN_URL.'/module.php?m=bbs_duel&p=history'),
        ),
    ),
);
