<?php
if (!defined('_GNUBOARD_')) exit;

function bbs_mmb_thumbnail_table()
{
    return G5_TABLE_PREFIX.'module_mmb_thumbnail';
}

function bbs_mmb_thumbnail_clean_url($url)
{
    $url = trim((string)$url);
    if ($url === '' || preg_match('/[\x00-\x20\x7f<>"\'`\\\\]/', $url)) return '';

    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) return '';

    $scheme = strtolower((string)$parts['scheme']);
    if (($scheme !== 'http' && $scheme !== 'https') || isset($parts['user']) || isset($parts['pass'])) {
        return '';
    }

    return $url;
}

function bbs_mmb_thumbnail_profile_field($ar_code)
{
    global $g5;
    static $cache = array();

    $ar_code = (string)$ar_code;
    if ($ar_code === '') return array();
    if (isset($cache[$ar_code])) return $cache[$ar_code];

    $code_esc = sql_escape_string($ar_code);
    $row = sql_fetch(
        "SELECT COUNT(*) AS cnt, MAX(ar_type) AS ar_type
           FROM `{$g5['article_table']}`
          WHERE ar_code = '{$code_esc}'",
        false
    );

    $cache[$ar_code] = (is_array($row) && (int)$row['cnt'] === 1 && $row['ar_type'] === 'url')
        ? array('ar_code' => $ar_code, 'ar_type' => 'url')
        : array();

    return $cache[$ar_code];
}

function bbs_mmb_thumbnail_profile_url($ch_id)
{
    static $cache = array();

    $ch_id = (int)$ch_id;
    $ar_code = function_exists('mdl_setting_get')
        ? trim((string)mdl_setting_get('bbs_mmb_thumbnail', 'profile_ar_code', ''))
        : '';
    if ($ch_id <= 0 || $ar_code === '' || empty(bbs_mmb_thumbnail_profile_field($ar_code))) return '';

    $time = function_exists('get_current_time') ? (int)get_current_time() : 1;
    $key = $ch_id.':'.$ar_code.':'.$time;
    if (array_key_exists($key, $cache)) return $cache[$key];

    if (!function_exists('get_character_info')) return $cache[$key] = '';
    $value = get_character_info($ch_id, $ar_code, $time);
    return $cache[$key] = bbs_mmb_thumbnail_clean_url($value);
}

function bbs_mmb_thumbnail_side_url($si_id)
{
    global $g5;
    static $cache = array();

    $si_id = (int)$si_id;
    if ($si_id <= 0) return '';
    if (array_key_exists($si_id, $cache)) return $cache[$si_id];

    $row = sql_fetch(
        "SELECT si_img FROM `{$g5['side_table']}` WHERE si_id = '{$si_id}'",
        false
    );
    if (empty($row['si_img'])) return $cache[$si_id] = '';

    $url = str_replace(defined('CDN_TOKEN') ? CDN_TOKEN : '[DATA_URL]', G5_DATA_URL, (string)$row['si_img']);
    if (function_exists('mdl_apply_filters')) {
        $url = mdl_apply_filters('side_icon_url', $url, $si_id);
    }

    return $cache[$si_id] = bbs_mmb_thumbnail_clean_url($url);
}

function bbs_mmb_thumbnail_resolve($ch_id, $si_id)
{
    $profile_url = bbs_mmb_thumbnail_profile_url($ch_id);
    if ($profile_url !== '') {
        return array('url' => $profile_url, 'source' => 'profile');
    }

    $side_url = bbs_mmb_thumbnail_side_url($si_id);
    if ($side_url !== '') {
        return array('url' => $side_url, 'source' => 'side');
    }

    return array('url' => '', 'source' => '');
}

function bbs_mmb_thumbnail_snapshot($bo_table, $wr_id)
{
    static $cache = array();

    $bo_table = (string)$bo_table;
    $wr_id = (int)$wr_id;
    $key = $bo_table.':'.$wr_id;
    if (array_key_exists($key, $cache)) return $cache[$key];

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $bo_table) || $wr_id <= 0) {
        return $cache[$key] = array();
    }

    $bo_esc = sql_escape_string($bo_table);
    $table = bbs_mmb_thumbnail_table();
    $row = sql_fetch(
        "SELECT thumbnail_url, thumbnail_source
           FROM `{$table}`
          WHERE bo_table = '{$bo_esc}' AND wr_id = '{$wr_id}'",
        false
    );

    if (!is_array($row) || empty($row['thumbnail_url'])) {
        return $cache[$key] = array();
    }

    $url = bbs_mmb_thumbnail_clean_url($row['thumbnail_url']);
    return $cache[$key] = ($url !== '')
        ? array('url' => $url, 'source' => (string)$row['thumbnail_source'])
        : array();
}

function bbs_mmb_thumbnail_capture_after_post($bo_table, $wr_id, $w)
{
    global $g5;

    if (!function_exists('mdl_board_enabled')
        || !mdl_board_enabled($bo_table, 'bbs_mmb_thumbnail', 'write')) {
        return;
    }

    $bo_table = (string)$bo_table;
    $wr_id = (int)$wr_id;
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $bo_table) || $wr_id <= 0) return;

    if (bbs_mmb_thumbnail_snapshot($bo_table, $wr_id)) return;

    $write_table = $g5['write_prefix'].$bo_table;
    $row = sql_fetch(
        "SELECT wr_url, ch_id, ch_side
           FROM `{$write_table}`
          WHERE wr_id = '{$wr_id}' AND wr_is_comment = 0",
        false
    );
    if (!is_array($row)) return;

    $bo_esc = sql_escape_string($bo_table);
    $file_row = sql_fetch(
        "SELECT bf_file FROM `{$g5['board_file_table']}`
          WHERE bo_table = '{$bo_esc}' AND wr_id = '{$wr_id}'
            AND bf_type BETWEEN '1' AND '3'
          ORDER BY bf_no LIMIT 1",
        false
    );
    if (!empty($file_row['bf_file']) || bbs_mmb_thumbnail_clean_url($row['wr_url']) !== '') return;

    $thumbnail = bbs_mmb_thumbnail_resolve($row['ch_id'], $row['ch_side']);
    if ($thumbnail['url'] === '') return;

    $url_esc = sql_escape_string($thumbnail['url']);
    $source_esc = sql_escape_string($thumbnail['source']);
    $table = bbs_mmb_thumbnail_table();
    $saved = sql_query(
        "INSERT IGNORE INTO `{$table}`
            (bo_table, wr_id, thumbnail_url, thumbnail_source, created_at)
         VALUES
            ('{$bo_esc}', '{$wr_id}', '{$url_esc}', '{$source_esc}', '".G5_TIME_YMDHIS."')",
        false
    );
    if (!$saved) {
        error_log('[bbs_mmb_thumbnail] Could not save thumbnail snapshot for '.$bo_table.'/'.$wr_id);
    }
}

function bbs_mmb_thumbnail_delete_after_post($bo_table, $wr_id)
{
    $bo_table = (string)$bo_table;
    $wr_id = (int)$wr_id;
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $bo_table) || $wr_id <= 0) return;

    $bo_esc = sql_escape_string($bo_table);
    $table = bbs_mmb_thumbnail_table();
    $deleted = sql_query(
        "DELETE FROM `{$table}` WHERE bo_table = '{$bo_esc}' AND wr_id = '{$wr_id}'",
        false
    );
    if (!$deleted) {
        error_log('[bbs_mmb_thumbnail] Could not delete thumbnail snapshot for '.$bo_table.'/'.$wr_id);
    }
}

function bbs_mmb_thumbnail_post_url($bo_table, $row, $allow_default)
{
    if (!is_array($row)) return '';
    $wr_id = isset($row['wr_id']) ? (int)$row['wr_id'] : 0;
    $snapshot = bbs_mmb_thumbnail_snapshot($bo_table, $wr_id);
    if (!empty($snapshot['url'])) return $snapshot['url'];
    if (!$allow_default) return '';

    $thumbnail = bbs_mmb_thumbnail_resolve(
        isset($row['ch_id']) ? $row['ch_id'] : 0,
        isset($row['ch_side']) ? $row['ch_side'] : 0
    );
    return $thumbnail['url'];
}
