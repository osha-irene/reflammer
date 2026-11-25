<?php
if (!defined('_GNUBOARD_')) exit;

require_once __DIR__.'/lib.php';

$module_url = G5_MDL_URL.'/reflammer_mail';

// common.php loads module bootstraps before extend/call.lib.php, so register after extend is ready.
mdl_add_action('admin_common', 'reflammer_mail_ensure_call_type', 10);

// cron 대신 일반 페이지 요청에서 예정 시각이 지난 편지의 도착을 처리한다.
mdl_add_action('tail', 'reflammer_mail_tick', 20);

// 캐릭터 마이페이지에서 펫 탭 스타일을 로드한다.
mdl_add_action('mypage_common', 'reflammer_mail_add_stylesheet', 10);

// 펫은 아이템의 이름/이미지/설명을 그대로 쓰는 분류형 아이템이다.
mdl_add_filter('item_type_options', function ($options) {
    if (!is_array($options)) $options = array();
    $options[] = '펫';
    return $options;
}, 10);

mdl_add_filter('inventory_list_where', function ($sql_fragment) {
    global $ch_id;
    $current_ch_id = (int)(isset($ch_id) ? $ch_id : 0);
    if ($current_ch_id <= 0) return (string)$sql_fragment;
    reflammer_mail_prune_pet_registry($current_ch_id);
    $pet_table = reflammer_mail_table('pet');
    return (string)$sql_fragment." AND NOT (item.it_type='펫' AND EXISTS (
        SELECT 1 FROM `{$pet_table}` AS registered_pet
        WHERE registered_pet.ch_id='{$current_ch_id}' AND registered_pet.it_id=item.it_id
    )) ";
}, 10);

mdl_add_action('inventory_delete_after', function ($in_id, $is_del, $row) {
    if ((string)$is_del !== '0' || !is_array($row)) return;
    reflammer_mail_sync_pet_owner((int)($row['ch_id'] ?? 0), (int)($row['it_id'] ?? 0));
}, 10);

mdl_add_action('inventory_send_after', function ($in_id, $from_ch_id, $to_ch_id) {
    global $g5;
    $row = sql_fetch("SELECT it_id FROM `{$g5['inventory_table']}` WHERE in_id='".(int)$in_id."' LIMIT 1", false);
    if (!empty($row['it_id'])) reflammer_mail_sync_pet_owner((int)$from_ch_id, (int)$row['it_id']);
}, 10);

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

mdl_add_filter('member_viewer_bag', function ($html, $ctx) {
    global $member;
    $ch = isset($ctx['ch']) && is_array($ctx['ch']) ? $ctx['ch'] : array();
    $ch_id = (int)(isset($ch['ch_id']) ? $ch['ch_id'] : (isset($ctx['ch_id']) ? $ctx['ch_id'] : 0));
    if ($ch_id < 1 || (isset($ch['ch_state']) && $ch['ch_state'] !== '승인')) return $html;
    $owner_mb_id = isset($ch['mb_id']) ? (string)$ch['mb_id'] : '';
    $manage = $owner_mb_id !== '' && !empty($member['mb_id']) && $member['mb_id'] === $owner_mb_id;
    $pet_html = reflammer_mail_pet_profile_html($ch_id, $manage);
    if ($pet_html !== '') reflammer_mail_add_stylesheet();
    return $pet_html.$html;
}, 30);
