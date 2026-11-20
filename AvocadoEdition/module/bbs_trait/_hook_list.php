<?php
if (!defined('_GNUBOARD_')) exit;

return array(
    'uses' => array(
        'bbs_duel_trait_form',
        'bbs_duel_trait_prepare',
        'bbs_duel_trait_consume',
        'bbs_comment_form_action_options',
        'bbs_comment_form_after_action',
        'bbs_custom_action',
        'bbs_comment_after',
        'bbs_view_row_extra',
        'bbs_enqueue_assets',
        'member_viewer_info',
        'character_delete_after',
        'item_type_options',
        'inventory_use_dispatch',
        'admin_item_form_extra_fields',
        'admin_item_form_save_data',
    ),
    'fires' => array(),
);
