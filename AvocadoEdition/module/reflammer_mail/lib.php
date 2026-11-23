<?php
if (!defined('_GNUBOARD_')) exit;

function reflammer_mail_table($suffix) { return G5_TABLE_PREFIX.'mail_'.$suffix; }

function reflammer_mail_e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

// $_POST/$_GET 는 common.php 에서 이미 addslashes 되어 있다.
// 저장 전 1회 stripslashes 후 sql_real_escape_string 만 적용한다.
function reflammer_mail_input($raw) { return stripslashes((string)$raw); }

function reflammer_mail_notice($message, $url) {
    $flags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;
    $message_json = json_encode((string)$message, $flags);
    $url_json = json_encode((string)$url, $flags);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="ko"><head><meta charset="utf-8"><title>편지</title></head><body><script>alert('.$message_json.');window.location.replace('.$url_json.');</script></body></html>';
    exit;
}

function reflammer_mail_owls($active_only = true) {
    $table = reflammer_mail_table('owl');
    $where = $active_only ? 'WHERE mo_active=1' : '';
    $rows = array();
    $rs = sql_query("SELECT * FROM `{$table}` {$where} ORDER BY mo_order ASC, mo_id ASC", false);
    while ($rs && $row = sql_fetch_array($rs)) $rows[] = $row;
    return $rows;
}

function reflammer_mail_get_owl($mo_id) {
    $mo_id = (int)$mo_id;
    if ($mo_id <= 0) return array();
    $table = reflammer_mail_table('owl');
    $row = sql_fetch("SELECT * FROM `{$table}` WHERE mo_id='{$mo_id}' LIMIT 1", false);
    return is_array($row) ? $row : array();
}

// 캐릭터 ch_id 를 기준으로 도착 예정 시간이 지난 편지를 확정 배달 처리한다.
// cron 이 없는 환경이라, 해당 캐릭터가 접속해 모듈 페이지/마이페이지를 열 때마다 1회 실행된다.
function reflammer_mail_tick($ch_id) {
    $ch_id = (int)$ch_id;
    if ($ch_id <= 0) return;

    $letter_table = reflammer_mail_table('letter');
    $item_table   = reflammer_mail_table('item');
    $now = G5_TIME_YMDHIS;

    $due = sql_query("SELECT ml_id FROM `{$letter_table}` WHERE re_ch_id='{$ch_id}' AND ml_status='in_transit' AND ml_due_datetime <= '{$now}'", false);
    if (!$due) return;

    while ($row = sql_fetch_array($due)) {
        $ml_id = (int)$row['ml_id'];

        $items = sql_query("SELECT mi_id, it_id FROM `{$item_table}` WHERE ml_id='{$ml_id}' AND mi_delivered=0", false);
        if ($items) {
            while ($it = sql_fetch_array($items)) {
                if (function_exists('insert_inventory')) insert_inventory($ch_id, (int)$it['it_id']);
                sql_query("UPDATE `{$item_table}` SET mi_delivered=1 WHERE mi_id='".(int)$it['mi_id']."'", false);
            }
        }

        sql_query("UPDATE `{$letter_table}` SET ml_status='delivered', ml_delivered_datetime='{$now}' WHERE ml_id='{$ml_id}'", false);
    }
}

function reflammer_mail_remaining_text($due_datetime) {
    $diff = strtotime((string)$due_datetime) - strtotime(G5_TIME_YMDHIS);
    if ($diff <= 0) return '곧 도착';
    $h = intdiv($diff, 3600);
    $m = intdiv($diff % 3600, 60);
    if ($h > 0) return $h.'시간 '.$m.'분 후 도착';
    return $m.'분 후 도착';
}

// 현재 활성 캐릭터가 이 모듈을 쓸 수 있는 "승인된 본인 캐릭터"인지 확인한다.
function reflammer_mail_sender_ok($character, $member) {
    return !empty($character['ch_id'])
        && !empty($member['mb_id'])
        && isset($character['mb_id']) && $character['mb_id'] === $member['mb_id']
        && isset($character['ch_state']) && $character['ch_state'] === '승인'
        && isset($character['ch_type']) && in_array($character['ch_type'], array('main', 'sub'), true);
}

// 받는 캐릭터 조회: 승인된 메인 캐릭터만 허용한다 (아이템 선물 보내기와 동일한 정책).
function reflammer_mail_find_recipient($re_ch_id) {
    global $g5;
    $re_ch_id = (int)$re_ch_id;
    if ($re_ch_id <= 0) return array();
    $row = sql_fetch("SELECT ch_id, ch_name, mb_id FROM `{$g5['character_table']}` WHERE ch_id='{$re_ch_id}' AND ch_state='승인' AND ch_type='main' LIMIT 1", false);
    return is_array($row) ? $row : array();
}

// 무작위 발송: 발신 캐릭터 본인을 제외한 승인된 메인 캐릭터 중 하나를 무작위로 고른다.
// 결과는 DB/관리자 화면에는 그대로 남지만, 발신자 본인 화면에서는 숨긴다 (ml_random 플래그).
function reflammer_mail_find_random_recipient($exclude_ch_id) {
    global $g5;
    $exclude_ch_id = (int)$exclude_ch_id;
    $row = sql_fetch("SELECT ch_id, ch_name, mb_id FROM `{$g5['character_table']}`
        WHERE ch_state='승인' AND ch_type='main' AND ch_id != '{$exclude_ch_id}'
        ORDER BY RAND() LIMIT 1", false);
    return is_array($row) ? $row : array();
}

// 보낸편지함·편지 보기에서 받는 사람 이름을 렌더링한다.
// $mask 가 true 이고 무작위 발송이면 발신자 본인에게도 상대를 숨긴다.
function reflammer_mail_recipient_label($row, $mask) {
    if ($mask && !empty($row['ml_random'])) {
        return reflammer_mail_e('??? (무작위로 선택됨)');
    }
    return reflammer_mail_e(isset($row['re_ch_name']) ? $row['re_ch_name'] : '');
}

// 발신 캐릭터가 실제로 소유한 인벤토리 항목만 서버에서 재조회해 스냅샷을 만든다 (IDOR 방지).
function reflammer_mail_collect_attachments($ch_id, $in_ids) {
    global $g5;
    $ch_id = (int)$ch_id;
    $out = array();
    if ($ch_id <= 0 || empty($in_ids) || !is_array($in_ids)) return $out;

    foreach ($in_ids as $raw) {
        $in_id = (int)$raw;
        if ($in_id <= 0) continue;
        $row = sql_fetch("SELECT in_id, it_id, it_name FROM `{$g5['inventory_table']}` WHERE in_id='{$in_id}' AND ch_id='{$ch_id}' LIMIT 1", false);
        if (!empty($row['in_id'])) $out[] = $row;
    }
    return $out;
}

function reflammer_mail_inventory($ch_id) {
    global $g5;
    $ch_id = (int)$ch_id;
    $rows = array();
    if ($ch_id <= 0) return $rows;
    $rs = sql_query("SELECT inven.in_id, inven.it_id, inven.it_name, item.it_img
        FROM `{$g5['inventory_table']}` AS inven
        LEFT JOIN `{$g5['item_table']}` AS item ON item.it_id = inven.it_id
        WHERE inven.ch_id='{$ch_id}'
        ORDER BY inven.it_name ASC, inven.in_id ASC", false);
    while ($rs && $row = sql_fetch_array($rs)) $rows[] = $row;
    return $rows;
}
