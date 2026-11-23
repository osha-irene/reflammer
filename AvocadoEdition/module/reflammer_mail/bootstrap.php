<?php
if (!defined('_GNUBOARD_')) exit;

require_once __DIR__.'/lib.php';

$module_url = G5_MDL_URL.'/reflammer_mail';

mdl_add_filter('mypage_submenu', function ($html, $ctx) use ($module_url) {
    if (!is_string($html)) $html = '';
    return $html.'<li><a href="'.htmlspecialchars($module_url.'/index.php', ENT_QUOTES, 'UTF-8').'"><i class="fa fa-envelope-o" aria-hidden="true"></i> 편지함</a></li>';
}, 10);

$reflammer_mail_add_send_tool = function ($tools, $ctx) use ($module_url) {
    if (!is_array($tools)) $tools = array();
    $ch_id = 0;
    if (isset($ctx['ch']) && is_array($ctx['ch']) && !empty($ctx['ch']['ch_id'])) {
        $ch_id = (int)$ctx['ch']['ch_id'];
    } elseif (isset($ctx['ch_id'])) {
        $ch_id = (int)$ctx['ch_id'];
    }
    if ($ch_id > 0) {
        $tools[] = array(
            'label' => '편지 보내기',
            'href'  => $module_url.'/index.php?action=write&re_ch_id='.$ch_id,
            'popup' => false,
        );
    }
    return $tools;
};

mdl_add_filter('mypage_character_viewer_tools', $reflammer_mail_add_send_tool, 10);
mdl_add_filter('member_viewer_tools', $reflammer_mail_add_send_tool, 10);
