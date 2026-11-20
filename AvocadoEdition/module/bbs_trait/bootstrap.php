<?php
if (!defined('_GNUBOARD_')) exit;

require_once __DIR__.'/lib.php';
require_once __DIR__.'/comment.php';
require_once __DIR__.'/item.php';

mdl_add_action('bbs_enqueue_assets', function ($bo_table = '') {
    add_stylesheet('<link rel="stylesheet" href="'.htmlspecialchars(G5_MDL_URL.'/bbs_trait/css/trait.css?v=1.4.0', ENT_QUOTES, 'UTF-8').'">', 100);
}, 10);

mdl_add_filter('item_type_options', function ($options) {
    $options = is_array($options) ? $options : array();
    $type = bbs_trait_slot_item_type();
    if (!in_array($type, $options, true)) $options[] = $type;
    $type = bbs_trait_acquisition_item_type();
    if (!in_array($type, $options, true)) $options[] = $type;
    return $options;
}, 10);
mdl_add_filter('inventory_use_dispatch', 'bbs_trait_use_slot_item', 10);
mdl_add_filter('inventory_use_dispatch', 'bbs_trait_use_acquisition_item', 20);
mdl_add_action('admin_item_form_extra_fields', 'bbs_trait_admin_item_form_fields', 10);
mdl_add_filter('admin_item_form_save_data', 'bbs_trait_admin_item_save_data', 10);

mdl_add_filter('bbs_duel_trait_form', 'bbs_trait_action_form', 10);
mdl_add_filter('bbs_duel_trait_prepare', 'bbs_trait_prepare_duel', 10);
mdl_add_filter('bbs_duel_trait_consume', 'bbs_trait_consume_duel', 10);
mdl_add_filter('bbs_comment_form_action_options', 'bbs_trait_comment_action_options', 10);
mdl_add_filter('bbs_comment_form_after_action', 'bbs_trait_comment_form', 10);
mdl_add_filter('bbs_custom_action', 'bbs_trait_comment_action', 20);
mdl_add_action('bbs_comment_after', 'bbs_trait_comment_after', 10);
mdl_add_filter('bbs_view_row_extra', 'bbs_trait_comment_row', 20);

mdl_add_action('character_delete_after', function ($ch_id, $ctx = array()) {
    $tables = bbs_trait_tables();
    $ch_id = (int)$ch_id;
    if ($ch_id > 0) {
        sql_query("DELETE FROM `{$tables['owned']}` WHERE ch_id='{$ch_id}'", false);
        sql_query("DELETE FROM `{$tables['slots']}` WHERE ch_id='{$ch_id}'", false);
    }
}, 10);

mdl_add_filter('member_viewer_info', function ($html, $ctx) {
    $character = isset($ctx['ch']) && is_array($ctx['ch']) ? $ctx['ch'] : array();
    $ch_id = isset($character['ch_id']) ? (int)$character['ch_id'] : (isset($ctx['ch_id']) ? (int)$ctx['ch_id'] : 0);
    if ($ch_id < 1) return $html;
    if ((isset($character['ch_state']) && $character['ch_state'] !== '승인')
        || (isset($character['ch_type']) && !in_array($character['ch_type'], array('main', 'sub'), true))) return $html;
    $traits = bbs_trait_get_owned($ch_id, false);
    if ($traits === false) return $html.'<p class="bbs-trait-error">보유 특성을 불러오지 못했습니다.</p>';
    if (!$traits) return $html;

    $view = __DIR__.'/views/profile.php';
    if (!is_file($view)) return $html;
    $slot_count = bbs_trait_open_slots($ch_id);
    if ($slot_count < 0) return $html.'<p class="bbs-trait-error">특성 슬롯 정보를 불러오지 못했습니다.</p>';
    $max_slot_count = bbs_trait_max_slots();
    ob_start();
    include $view;
    return $html.ob_get_clean();
}, 20);
