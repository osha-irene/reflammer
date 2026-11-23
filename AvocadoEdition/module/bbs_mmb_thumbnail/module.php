<?php
if (!defined('_GNUBOARD_')) exit;

return array(
    'name'           => 'bbs_mmb_thumbnail',
    'title'          => 'MMB 썸네일 변경',
    'description'    => '첨부 이미지가 없는 MMB 글에 프로필 또는 소속 썸네일을 표시합니다.',
    'version'        => '1.0.0',
    'author'         => 'reflammer',
    'board_scope'    => true,
    'board_default'  => 'off',
    'scopes'         => array('write'),
    'requires_hooks' => array(
        'bbs_write_form_wr_type_options',
        'bbs_write_form_wr_type_panels',
        'bbs_post_after',
        'write_delete_after',
    ),
    'admin_menus'    => array(
        'auto' => array(
            array('000', '썸네일 설정', G5_MDL_ADMIN_URL.'/module.php?m=bbs_mmb_thumbnail&p=setting'),
        ),
    ),
);
