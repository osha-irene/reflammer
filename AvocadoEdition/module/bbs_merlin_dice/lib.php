<?php
if (!defined('_GNUBOARD_')) exit;

/** 현재 캐릭터의 굴림에 사용할 스탯 목록. 상태 화면과 같이 배분 수치(sc_max)를 사용한다. */
function merlin_dice_statuses($ch_id)
{
    global $g5;
    $ch_id = (int)$ch_id;
    if ($ch_id <= 0 || empty($g5['status_config_table']) || empty($g5['status_table'])) return array();
    if (function_exists('get_status_by_name') && function_exists('get_status')) {
        $result = sql_query("SELECT st_id, st_name FROM `{$g5['status_config_table']}` ORDER BY st_order ASC, st_id ASC");
        $statuses = array();
        while ($row = sql_fetch_array($result)) {
            $st_id = (int)$row['st_id'];
            $state = get_status($ch_id, $st_id);
            $value = (int)$state['has'];
            // 상태 화면도 미설정/0인 배분값은 해당 스탯의 최소값으로 표시한다.
            if ($value === 0) $value = (int)$state['min'];
            $statuses[$st_id] = array(
                'id' => $st_id,
                'name' => (string)$row['st_name'],
                'value' => max(0, $value),
            );
        }
        return $statuses;
    }
    return array();
}

function merlin_dice_roll($count)
{
    $count = max(1, min(100, (int)$count));
    $rolls = array();
    for ($i = 0; $i < $count; $i++) $rolls[] = mt_rand(1, 2);
    return $rolls;
}

function merlin_dice_parse($value)
{
    if (!is_string($value) || $value === '') return array();
    $parts = explode(',', $value);
    $rolls = array();
    foreach ($parts as $part) {
        if (!preg_match('/^[12]$/', trim($part))) return array();
        $rolls[] = (int)$part;
    }
    return $rolls;
}

function merlin_dice_escape($value)
{
    return sql_real_escape_string((string)$value);
}
