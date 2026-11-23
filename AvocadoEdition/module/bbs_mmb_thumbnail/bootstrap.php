<?php
if (!defined('_GNUBOARD_')) exit;

require_once __DIR__.'/lib.php';

mdl_add_filter('bbs_write_form_wr_type_options', function ($html, $ctx) {
    $bo_table = isset($ctx['bo_table']) ? (string)$ctx['bo_table'] : '';
    if (!function_exists('mdl_board_enabled')
        || !mdl_board_enabled($bo_table, 'bbs_mmb_thumbnail', 'write')) {
        return $html;
    }

    $write = isset($ctx['write']) && is_array($ctx['write']) ? $ctx['write'] : array();
    $selected = isset($write['wr_type']) && $write['wr_type'] === 'AUTO' ? ' selected' : '';
    return $html.'<option value="AUTO"'.$selected.'>첨부 안함</option>';
}, 10);

mdl_add_filter('bbs_write_form_wr_type_panels', function ($html, $ctx) {
    $bo_table = isset($ctx['bo_table']) ? (string)$ctx['bo_table'] : '';
    if (!function_exists('mdl_board_enabled')
        || !mdl_board_enabled($bo_table, 'bbs_mmb_thumbnail', 'write')) {
        return $html;
    }

    $write = isset($ctx['write']) && is_array($ctx['write']) ? $ctx['write'] : array();
    $style = isset($write['wr_type']) && $write['wr_type'] === 'AUTO' ? '' : ' style="display:none;"';
    return $html.'<div id="add_AUTO"'.$style.'></div>'
        .'<script>document.addEventListener("DOMContentLoaded",function(){'
        .'var f=document.querySelector("form[name=fwrite]");'
        .'if(f&&f.wr_type&&f.wr_type.value==="AUTO"&&typeof fn_log_type==="function")fn_log_type("AUTO");'
        .'});</script>';
}, 10);

mdl_add_action('bbs_post_after', 'bbs_mmb_thumbnail_capture_after_post', 10);
mdl_add_action('write_delete_after', 'bbs_mmb_thumbnail_delete_after_post', 10);
