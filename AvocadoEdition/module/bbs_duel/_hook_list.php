<?php
if (!defined('_GNUBOARD_')) exit;

return array(
    'uses' => array(
        'bbs_write_form_action_options',
        'bbs_write_form_action_panels',
        'bbs_custom_action',
        'bbs_view_row_extra',
        'bbs_enqueue_assets',
        'write_after',
        'character_create_after',
        'character_delete_after',
        'member_viewer_info',
    ),
    'fires' => array('bbs_duel_damage_adjustment'),
);
