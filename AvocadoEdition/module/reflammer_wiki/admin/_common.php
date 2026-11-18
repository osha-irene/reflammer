<?php
if (!defined('_GNUBOARD_')) exit;
require_once G5_PATH.'/module/reflammer_wiki/lib.php';
if (!reflammer_wiki_is_moderator()) reflammer_wiki_notice('위키 운영진만 이용할 수 있습니다.', G5_URL.'/module/reflammer_wiki/index.php');
function reflammer_wiki_admin_url($page) { return G5_MDL_ADMIN_URL.'/module.php?m=reflammer_wiki&p='.$page; }
function reflammer_wiki_admin_post() { if ($_SERVER['REQUEST_METHOD'] !== 'POST') return; check_token(); }
function reflammer_wiki_cat_options($selected = 0) {
    $roots=reflammer_wiki_categories();
    foreach($roots as $root) {
        echo '<option value="'.(int)$root['wc_id'].'" '.get_selected((int)$selected,(int)$root['wc_id']).'>'.reflammer_wiki_e($root['wc_name']).'</option>';
        foreach(reflammer_wiki_categories((int)$root['wc_id']) as $child) echo '<option value="'.(int)$child['wc_id'].'" '.get_selected((int)$selected,(int)$child['wc_id']).'>└ '.reflammer_wiki_e($root['wc_name'].' / '.$child['wc_name']).'</option>';
    }
}
function reflammer_wiki_root_options($selected = 0, $exclude = 0) {
    foreach (reflammer_wiki_categories() as $root) {
        if ((int)$root['wc_id'] === (int)$exclude) continue;
        echo '<option value="'.(int)$root['wc_id'].'" '.get_selected((int)$selected,(int)$root['wc_id']).'>'.reflammer_wiki_e($root['wc_name']).'</option>';
    }
}
