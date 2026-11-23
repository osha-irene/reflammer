<?php
if (!defined('_GNUBOARD_')) exit;

return array(
    'name'        => 'reflammer_mail',
    'title'       => '편지',
    'description' => '캐릭터 간 편지를 부엉이로 배달합니다. 부엉이 종류별 배달 시간, 아이템 첨부, 익명 발송을 지원합니다.',
    'version'     => '1.1.0',
    'author'      => 'reflammer',
    'view_url'    => G5_MDL_URL.'/reflammer_mail/index.php',
    'admin_menus' => array(
        'auto' => array(
            array('000', '편지', G5_MDL_ADMIN_URL.'/module.php?m=reflammer_mail&p=owl_list'),
            array('100', '부엉이 종류', G5_MDL_ADMIN_URL.'/module.php?m=reflammer_mail&p=owl_list'),
            array('200', '편지 내역', G5_MDL_ADMIN_URL.'/module.php?m=reflammer_mail&p=letter_list'),
        ),
    ),
);
