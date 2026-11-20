<?php
if (!defined('_GNUBOARD_')) exit;

return array(
    'name'           => 'bbs_trait',
    'title'          => '특성',
    'description'    => '캐릭터별 특성을 관리하고, 대련·결투·댓글 및 특성 습득 아이템과 연동합니다.',
    'version'        => '1.0.0',
    'author'         => 'reflammer',
    'board_scope'    => true,
    'board_default'  => 'off',
    'scopes'         => array('write', 'comment'),
    'requires_hooks' => array(
        'bbs_write_form_action_panels',
        'bbs_comment_form_action_options',
        'bbs_comment_form_after_action',
        'bbs_custom_action',
        'bbs_comment_after',
        'bbs_view_row_extra',
        'bbs_enqueue_assets',
        'write_after',
    ),
    'admin_menus'    => array(
        'auto' => array(
            array('000', '특성', G5_MDL_ADMIN_URL.'/module.php?m=bbs_trait&p=setting'),
            array('100', '특성 설정', G5_MDL_ADMIN_URL.'/module.php?m=bbs_trait&p=setting'),
            array('200', '특성 목록', G5_MDL_ADMIN_URL.'/module.php?m=bbs_trait&p=trait_list'),
            array('300', '지급·보유 현황', G5_MDL_ADMIN_URL.'/module.php?m=bbs_trait&p=ownership'),
        ),
    ),
);
