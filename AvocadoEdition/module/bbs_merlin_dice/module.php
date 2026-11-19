<?php
if (!defined('_GNUBOARD_')) exit;

return array(
    'name'           => 'bbs_merlin_dice',
    'title'          => '멀린의 주사위',
    'description'    => '캐릭터 스탯에 따라 MMB 글과 댓글에서 1 또는 2가 나오는 주사위를 굴립니다.',
    'version'        => '1.0.0',
    'author'         => 'K',
    'board_scope'    => true,
    'board_default'  => 'off',
    'scopes'         => array('write', 'comment'),
    'depends'        => array('bbs_dice'),
    'requires_hooks' => array(
        'bbs_write_form_options',
        'bbs_comment_form_options',
        'bbs_customer_sql',
        'bbs_view_row_extra',
    ),
);
