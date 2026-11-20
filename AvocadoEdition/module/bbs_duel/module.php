<?php
if (!defined('_GNUBOARD_')) exit;

return array(
    'name'           => 'bbs_duel',
    'title'          => '대련·결투',
    'description'    => 'MMB ACTION에서 대련·결투 상대 지정과 양측 스탯 주사위 판정을 기록합니다.',
    'version'        => '0.14.3',
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
    ),
    'admin_menus'    => array(
        'auto' => array(
            array('000', '대련·결투', G5_MDL_ADMIN_URL.'/module.php?m=bbs_duel&p=enemy_list'),
            array('100', '에너미 관리', G5_MDL_ADMIN_URL.'/module.php?m=bbs_duel&p=enemy_list'),
        ),
    ),
);
