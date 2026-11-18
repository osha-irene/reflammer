<?php
if (!defined('_GNUBOARD_')) exit;

return array(
    'name' => 'reflammer_wiki',
    'title' => '펜시브 (The Pensieve)',
    'description' => '카테고리형 위키, 게시글 승인과 태그 검색을 제공합니다.',
    'version' => '1.0.0',
    'author' => 'reflammer',
    'view_url' => G5_URL.'/module/reflammer_wiki/index.php',
    'admin_menus' => array(
        'auto' => array(
            array('000', '펜시브', G5_MDL_ADMIN_URL.'/module.php?m=reflammer_wiki&p=entries'),
            array('100', '글 검토·승인', G5_MDL_ADMIN_URL.'/module.php?m=reflammer_wiki&p=entries'),
            array('200', '카테고리 관리', G5_MDL_ADMIN_URL.'/module.php?m=reflammer_wiki&p=categories'),
            array('300', '태그·운영진', G5_MDL_ADMIN_URL.'/module.php?m=reflammer_wiki&p=settings'),
        ),
    ),
);
