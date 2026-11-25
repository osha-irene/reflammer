<?php
if (!defined('_GNUBOARD_')) exit;

function reflammer_mail_table($suffix) { return G5_TABLE_PREFIX.'mail_'.$suffix; }

function reflammer_mail_e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

function reflammer_mail_add_stylesheet() {
    static $added = false;
    if ($added || !function_exists('add_stylesheet')) return;
    add_stylesheet('<link rel="stylesheet" href="'.G5_MDL_URL.'/reflammer_mail/mail.css">', 0);
    $added = true;
}

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

function reflammer_mail_owl_image($owl) {
    if (!is_array($owl)) return '';
    $url = trim((string)(isset($owl['mo_image_url']) ? $owl['mo_image_url'] : ''));
    if ($url !== '' && preg_match('#^https?://#i', $url)) return $url;
    return trim((string)(isset($owl['mo_image_file']) ? $owl['mo_image_file'] : ''));
}

function reflammer_mail_ensure_call_type() {
    global $g5;
    if (!function_exists('ensure_call_type') || empty($g5['call_type_map_table'])) return false;
    $table = $g5['call_type_map_table'];
    $exists = sql_fetch("SHOW TABLES LIKE '".sql_real_escape_string($table)."'", false);
    if (empty($exists)) return false;
    return ensure_call_type('reflammer_mail', '편지', '새 편지가 도착했습니다.', G5_MDL_URL.'/reflammer_mail/index.php');
}

function reflammer_mail_notify_delivery($ml_id) {
    global $g5, $config;
    $ml_id = (int)$ml_id;
    if ($ml_id <= 0 || !function_exists('push_call')) return;

    $letter_table = reflammer_mail_table('letter');
    $letter = sql_fetch("SELECT re_ch_id, se_ch_name, ml_nominal_sender, ml_anonymous FROM `{$letter_table}`
        WHERE ml_id='{$ml_id}' AND ml_status='delivered' AND ml_read_datetime IS NULL AND ml_notice_sent=0 LIMIT 1", false);
    if (empty($letter['re_ch_id'])) return;
    $recipient = sql_fetch("SELECT mb_id FROM `{$g5['character_table']}` WHERE ch_id='".(int)$letter['re_ch_id']."' LIMIT 1", false);
    $receiver_mb_id = isset($recipient['mb_id']) ? trim((string)$recipient['mb_id']) : '';
    if ($receiver_mb_id === '') return;

    if (array_key_exists('ml_nominal_sender', $letter) && $letter['ml_nominal_sender'] !== null) {
        $sender_name = trim((string)$letter['ml_nominal_sender']) !== '' ? trim((string)$letter['ml_nominal_sender']) : '익명';
    } else {
        $sender_name = !empty($letter['ml_anonymous']) ? '익명' : (trim((string)$letter['se_ch_name']) !== '' ? $letter['se_ch_name'] : '익명');
    }

    $letter_url = G5_MDL_URL.'/reflammer_mail/index.php?action=view&id='.$ml_id;
    if (!empty($g5['call_board_table'])) {
        $letter_url_sql = sql_real_escape_string($letter_url);
        $existing_notice = sql_fetch("SELECT bc_id FROM `{$g5['call_board_table']}`
            WHERE bc_type='reflammer_mail' AND re_mb_id='".sql_real_escape_string($receiver_mb_id)."'
              AND bc_link='{$letter_url_sql}' LIMIT 1", false);
        if (!empty($existing_notice['bc_id'])) {
            $sender_name_sql = sql_real_escape_string($sender_name);
            sql_query("UPDATE `{$g5['call_board_table']}` SET mb_name='{$sender_name_sql}' WHERE bc_id='".(int)$existing_notice['bc_id']."'", false);
            sql_query("UPDATE `{$letter_table}` SET ml_notice_sent=1 WHERE ml_id='{$ml_id}' AND ml_notice_sent=0", false);
            return;
        }
    }

    // 알림 INSERT와 발송 플래그를 한 트랜잭션으로 묶어 중복 알림과 누락을 줄인다.
    sql_query('START TRANSACTION', false);
    sql_query("UPDATE `{$letter_table}` SET ml_notice_sent=1
        WHERE ml_id='{$ml_id}' AND ml_status='delivered' AND ml_read_datetime IS NULL AND ml_notice_sent=0", false);
    if ((int)sql_affected_rows() !== 1) {
        sql_query('ROLLBACK', false);
        return;
    }

    $sent = push_call(
        'reflammer_mail',
        array('mb_id' => '', 'mb_name' => $sender_name),
        array($receiver_mb_id),
        '새 편지가 도착했습니다.',
        $letter_url
    );
    if ((int)$sent <= 0) {
        sql_query("UPDATE `{$letter_table}` SET ml_notice_sent=0 WHERE ml_id='{$ml_id}'", false);
    }
    sql_query('COMMIT', false);
}

// 도착 시각이 지난 편지의 배달·알림과 14일 자동 읽음을 요청이 들어올 때 처리한다.
// cron 이 없으므로 실제 처리는 다음 사이트 요청에서 실행되지만, 상태 시각은 예정 시각 기준이다.
function reflammer_mail_tick($mailbox_mb_id = '') {
    static $ran = false;
    if ($ran) return;
    $ran = true;

    // Ordinary page views can arrive in pairs (for example, the outer home page
    // and its main iframe). Coalesce global delivery sweeps for 15 seconds while
    // keeping an explicit mailbox visit immediate.
    $tick_lock = false;
    $mailbox_mb_id = trim((string)$mailbox_mb_id);
    if ($mailbox_mb_id === '' && function_exists('flock')) {
        $tick_lock_path = G5_DATA_PATH.'/cache/reflammer_mail_tick.lock';
        $tick_lock_handle = @fopen($tick_lock_path, 'c+');
        if ($tick_lock_handle) {
            if (!@flock($tick_lock_handle, LOCK_EX | LOCK_NB)) {
                fclose($tick_lock_handle);
                return;
            }
            rewind($tick_lock_handle);
            $last_tick = (int)trim((string)stream_get_contents($tick_lock_handle));
            if ($last_tick > 0 && $last_tick > time() - 15) {
                flock($tick_lock_handle, LOCK_UN);
                fclose($tick_lock_handle);
                return;
            }
            $tick_lock = true;
        }
    }

    global $g5, $config;
    reflammer_mail_ensure_call_type();
    $letter_table = reflammer_mail_table('letter');
    $item_table   = reflammer_mail_table('item');
    $now = G5_TIME_YMDHIS;

    $due = sql_query("SELECT ml_id, re_ch_id, re_ch_name, se_mb_id, ml_due_datetime
        FROM `{$letter_table}`
        WHERE ml_status='in_transit' AND ml_due_datetime <= '{$now}'
        ORDER BY ml_due_datetime ASC, ml_id ASC", false);

    while ($due && $row = sql_fetch_array($due)) {
        $ml_id = (int)$row['ml_id'];

        // 상태 전이를 먼저 원자적으로 선점해 동시 요청에서 아이템·알림이 중복되지 않게 한다.
        sql_query("UPDATE `{$letter_table}`
            SET ml_status='delivered', ml_delivered_datetime=ml_due_datetime
            WHERE ml_id='{$ml_id}' AND ml_status='in_transit' AND ml_due_datetime <= '{$now}'", false);
        if ((int)sql_affected_rows() !== 1) continue;

        $items = sql_query("SELECT mi_id, it_id FROM `{$item_table}` WHERE ml_id='{$ml_id}' AND mi_delivered=0", false);
        if ($items) {
            while ($it = sql_fetch_array($items)) {
                if (function_exists('insert_inventory')) insert_inventory((int)$row['re_ch_id'], (int)$it['it_id']);
                sql_query("UPDATE `{$item_table}` SET mi_delivered=1 WHERE mi_id='".(int)$it['mi_id']."'", false);
            }
        }

        reflammer_mail_notify_delivery($ml_id);
    }

    // 도착 상태로 전환됐지만 알림 처리가 이전 요청에서 누락된 미열람 편지도 재시도한다.
    $pending_notices = sql_query("SELECT ml_id FROM `{$letter_table}`
        WHERE ml_status='delivered' AND ml_read_datetime IS NULL AND ml_notice_sent=0
        ORDER BY ml_id ASC LIMIT 50", false);
    while ($pending_notices && $pending = sql_fetch_array($pending_notices)) {
        reflammer_mail_notify_delivery((int)$pending['ml_id']);
    }

    // 편지 기능 방문은 읽음이 아니지만, 미확인 편지의 자동 읽음 대기 기간은 갱신한다.
    if ($mailbox_mb_id !== '') {
        $mailbox_mb_id_sql = sql_real_escape_string($mailbox_mb_id);
        sql_query("UPDATE `{$letter_table}` AS letter
            INNER JOIN `{$g5['character_table']}` AS character_row ON character_row.ch_id=letter.re_ch_id
            SET letter.ml_re_last_visit='{$now}'
            WHERE character_row.mb_id='{$mailbox_mb_id_sql}'
              AND letter.ml_status='delivered' AND letter.ml_read_datetime IS NULL
              AND letter.ml_due_datetime <= '{$now}'", false);
    }

    // 도착 후 14일 동안 편지 기능을 열지 않은 편지는 자동 읽음 처리한다.
    sql_query("UPDATE `{$letter_table}`
        SET ml_read_datetime=DATE_ADD(COALESCE(ml_re_last_visit, ml_due_datetime), INTERVAL 14 DAY)
        WHERE ml_status='delivered' AND ml_read_datetime IS NULL
          AND COALESCE(ml_re_last_visit, ml_due_datetime) <= DATE_SUB('{$now}', INTERVAL 14 DAY)", false);

    if ($tick_lock) {
        ftruncate($tick_lock_handle, 0);
        rewind($tick_lock_handle);
        fwrite($tick_lock_handle, (string)time());
        fflush($tick_lock_handle);
        flock($tick_lock_handle, LOCK_UN);
        fclose($tick_lock_handle);
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

function reflammer_mail_owned_characters($mb_id) {
    global $g5;
    $mb_id = trim((string)$mb_id);
    if ($mb_id === '') return array();
    $mb_id_sql = sql_real_escape_string($mb_id);
    $rows = array();
    $rs = sql_query("SELECT ch_id, ch_name, ch_type FROM `{$g5['character_table']}`
        WHERE mb_id='{$mb_id_sql}' AND ch_state='승인' AND ch_type IN ('main','sub')
        ORDER BY FIELD(ch_type,'main','sub'), ch_name ASC, ch_id ASC", false);
    while ($rs && $row = sql_fetch_array($rs)) $rows[] = $row;
    return $rows;
}

function reflammer_mail_find_sender($se_ch_id, $mb_id) {
    global $g5;
    $se_ch_id = (int)$se_ch_id;
    $mb_id_sql = sql_real_escape_string(trim((string)$mb_id));
    if ($se_ch_id <= 0 || $mb_id_sql === '') return array();
    $row = sql_fetch("SELECT ch_id, ch_name, mb_id, ch_state, ch_type FROM `{$g5['character_table']}`
        WHERE ch_id='{$se_ch_id}' AND mb_id='{$mb_id_sql}' AND ch_state='승인'
          AND ch_type IN ('main','sub') LIMIT 1", false);
    return is_array($row) ? $row : array();
}

// 받는 캐릭터 조회: 승인된 main/sub 캐릭터만 허용한다.
function reflammer_mail_find_recipient($re_ch_id) {
    global $g5;
    $re_ch_id = (int)$re_ch_id;
    if ($re_ch_id <= 0) return array();
    $row = sql_fetch("SELECT ch_id, ch_name, mb_id, ch_type FROM `{$g5['character_table']}`
        WHERE ch_id='{$re_ch_id}' AND ch_state='승인' AND ch_type IN ('main','sub') LIMIT 1", false);
    return is_array($row) ? $row : array();
}

// 무작위 발송: 발신 멤버가 소유한 모든 캐릭터를 제외한 승인 main/sub 중 하나를 고른다.
// 결과는 DB/관리자 화면에는 그대로 남지만, 발신자 본인 화면에서는 숨긴다 (ml_random 플래그).
function reflammer_mail_find_random_recipient($exclude_mb_id) {
    global $g5;
    $exclude_mb_id = sql_real_escape_string(trim((string)$exclude_mb_id));
    if ($exclude_mb_id === '') return array();
    $row = sql_fetch("SELECT ch_id, ch_name, mb_id FROM `{$g5['character_table']}`
        WHERE ch_state='승인' AND ch_type IN ('main','sub') AND mb_id <> '{$exclude_mb_id}'
        ORDER BY RAND() LIMIT 1", false);
    return is_array($row) ? $row : array();
}

// 보낸편지함·편지 보기에서 받는 사람 이름을 렌더링한다.
// $mask 가 true 이고 무작위 발송이면 발신자 본인에게도 상대를 숨긴다.
function reflammer_mail_recipient_label($row, $mask) {
    if ($mask && !empty($row['ml_random'])) {
        return reflammer_mail_e('??? (무작위로 선택됨)');
    }
    if (array_key_exists('ml_nominal_recipient', $row) && $row['ml_nominal_recipient'] !== null) {
        return reflammer_mail_e(trim((string)$row['ml_nominal_recipient']) !== '' ? $row['ml_nominal_recipient'] : '익명');
    }
    return reflammer_mail_e(isset($row['re_ch_name']) ? $row['re_ch_name'] : '');
}

function reflammer_mail_sender_label($row, $hide_sender = false) {
    if ($hide_sender) return reflammer_mail_e('익명');
    if (array_key_exists('ml_nominal_sender', $row) && $row['ml_nominal_sender'] !== null) {
        return reflammer_mail_e(trim((string)$row['ml_nominal_sender']) !== '' ? $row['ml_nominal_sender'] : '익명');
    }
    if (!empty($row['ml_anonymous'])) return reflammer_mail_e('익명');
    return reflammer_mail_e(isset($row['se_ch_name']) ? $row['se_ch_name'] : '');
}

// 공개 게시판은 무작위 발송이면 실제 수신 캐릭터, 그 외에는 편지에 적은 수신 이름만 노출한다.
function reflammer_mail_public_recipient_label($row) {
    if (!empty($row['ml_random'])) {
        return reflammer_mail_e(isset($row['re_ch_name']) ? $row['re_ch_name'] : '');
    }
    if (array_key_exists('ml_nominal_recipient', $row) && $row['ml_nominal_recipient'] !== null) {
        $name = trim((string)$row['ml_nominal_recipient']);
        return reflammer_mail_e($name !== '' ? $name : '익명');
    }
    return reflammer_mail_e(isset($row['re_ch_name']) ? $row['re_ch_name'] : '');
}

// 공개 게시판에서는 실제 발신 캐릭터명을 사용하지 않고, 익명 발송은 이름 자체를 생략한다.
function reflammer_mail_public_sender_label($row) {
    if (!empty($row['ml_anonymous'])) return '';
    if (array_key_exists('ml_nominal_sender', $row) && $row['ml_nominal_sender'] !== null) {
        $name = trim((string)$row['ml_nominal_sender']);
        return $name !== '' ? reflammer_mail_e($name) : '';
    }
    return reflammer_mail_e(isset($row['se_ch_name']) ? $row['se_ch_name'] : '');
}

function reflammer_mail_public_companion($row) {
    $pet = !empty($row['ml_pet_name']);
    return array(
        'image' => $pet ? (string)$row['ml_pet_image'] : (string)$row['ml_owl_image'],
        'name' => $pet ? (string)$row['ml_pet_name'] : (string)$row['mo_name'],
        'is_pet' => $pet,
    );
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
        $pet_table = reflammer_mail_table('pet');
        $row = sql_fetch("SELECT inven.in_id, inven.it_id, inven.it_name
            FROM `{$g5['inventory_table']}` AS inven
            INNER JOIN `{$g5['item_table']}` AS item ON item.it_id=inven.it_id
            WHERE inven.in_id='{$in_id}' AND inven.ch_id='{$ch_id}'
              AND NOT (item.it_type='펫' AND EXISTS (
                SELECT 1 FROM `{$pet_table}` AS registered_pet
                WHERE registered_pet.ch_id='{$ch_id}' AND registered_pet.it_id=item.it_id
              )) LIMIT 1", false);
        if (!empty($row['in_id'])) $out[] = $row;
    }
    return $out;
}

function reflammer_mail_inventory($ch_id) {
    global $g5;
    $ch_id = (int)$ch_id;
    $rows = array();
    if ($ch_id <= 0) return $rows;
    $pet_table = reflammer_mail_table('pet');
    $rs = sql_query("SELECT inven.in_id, inven.it_id, inven.it_name, item.it_img
        FROM `{$g5['inventory_table']}` AS inven
        LEFT JOIN `{$g5['item_table']}` AS item ON item.it_id = inven.it_id
        WHERE inven.ch_id='{$ch_id}' AND NOT (item.it_type='펫' AND EXISTS (
            SELECT 1 FROM `{$pet_table}` AS registered_pet
            WHERE registered_pet.ch_id='{$ch_id}' AND registered_pet.it_id=item.it_id
        ))
        ORDER BY inven.it_name ASC, inven.in_id ASC", false);
    while ($rs && $row = sql_fetch_array($rs)) $rows[] = $row;
    return $rows;
}

function reflammer_mail_max_main_pets() { return 3; }

function reflammer_mail_prune_pet_registry($ch_id) {
    global $g5;
    $ch_id = (int)$ch_id;
    if ($ch_id <= 0) return;
    $pet_table = reflammer_mail_table('pet');
    sql_query("DELETE registered_pet FROM `{$pet_table}` AS registered_pet
        WHERE registered_pet.ch_id='{$ch_id}' AND (
            NOT EXISTS (
                SELECT 1 FROM `{$g5['inventory_table']}` AS inven
                WHERE inven.ch_id=registered_pet.ch_id AND inven.it_id=registered_pet.it_id
            )
            OR NOT EXISTS (
                SELECT 1 FROM `{$g5['item_table']}` AS item
                WHERE item.it_id=registered_pet.it_id AND item.it_type='펫'
            )
        )", false);
}

// 소유한 펫 아이템 종류를 중복 수량과 무관하게 한 행씩 반환한다.
function reflammer_mail_owned_pet_items($ch_id) {
    global $g5;
    $ch_id = (int)$ch_id;
    $rows = array();
    if ($ch_id <= 0) return $rows;
    $rs = sql_query("SELECT item.it_id, item.it_name, item.it_img
        FROM `{$g5['item_table']}` AS item
        WHERE item.it_type='펫' AND EXISTS (
            SELECT 1 FROM `{$g5['inventory_table']}` AS inven
            WHERE inven.ch_id='{$ch_id}' AND inven.it_id=item.it_id
        )
        ORDER BY item.it_name ASC, item.it_id ASC", false);
    while ($rs && $row = sql_fetch_array($rs)) $rows[] = $row;
    return $rows;
}

// 프로필과 편지에 사용할 메인 펫만 반환한다. 같은 it_id 보유분은 한 번만 표시한다.
function reflammer_mail_pet_inventory($ch_id) {
    global $g5;
    $ch_id = (int)$ch_id;
    $rows = array();
    if ($ch_id <= 0) return $rows;
    reflammer_mail_prune_pet_registry($ch_id);
    $pet_table = reflammer_mail_table('pet');
    $rs = sql_query("SELECT item.it_id, item.it_name, item.it_img, item.it_content
        FROM `{$pet_table}` AS registered_pet
        INNER JOIN `{$g5['item_table']}` AS item ON item.it_id=registered_pet.it_id AND item.it_type='펫'
        WHERE registered_pet.ch_id='{$ch_id}'
          AND EXISTS (SELECT 1 FROM `{$g5['inventory_table']}` AS inven
              WHERE inven.ch_id=registered_pet.ch_id AND inven.it_id=registered_pet.it_id)
        ORDER BY item.it_name ASC, item.it_id ASC", false);
    while ($rs && $row = sql_fetch_array($rs)) $rows[] = $row;
    return $rows;
}

// 발송 시점에 실제 보유 여부와 아이템 종류를 다시 확인한다.
function reflammer_mail_find_owned_pet($ch_id, $it_id) {
    global $g5;
    $ch_id = (int)$ch_id;
    $it_id = (int)$it_id;
    if ($ch_id <= 0 || $it_id <= 0) return array();
    $pet_table = reflammer_mail_table('pet');
    $row = sql_fetch("SELECT item.it_id, item.it_name, item.it_img
        FROM `{$g5['inventory_table']}` AS inven
        INNER JOIN `{$g5['item_table']}` AS item ON item.it_id=inven.it_id
        INNER JOIN `{$pet_table}` AS registered_pet ON registered_pet.ch_id=inven.ch_id AND registered_pet.it_id=item.it_id
        WHERE inven.ch_id='{$ch_id}' AND item.it_id='{$it_id}' AND item.it_type='펫'
        LIMIT 1", false);
    return is_array($row) ? $row : array();
}

function reflammer_mail_sync_pet_owner($ch_id, $it_id) {
    global $g5;
    $ch_id = (int)$ch_id;
    $it_id = (int)$it_id;
    if ($ch_id <= 0 || $it_id <= 0) return;
    $owned = sql_fetch("SELECT in_id FROM `{$g5['inventory_table']}` WHERE ch_id='{$ch_id}' AND it_id='{$it_id}' LIMIT 1", false);
    if (!empty($owned['in_id'])) return;
    $pet_table = reflammer_mail_table('pet');
    sql_query("DELETE FROM `{$pet_table}` WHERE ch_id='{$ch_id}' AND it_id='{$it_id}'", false);
}

// 내부 아이템 이미지는 아이템 수정 시 원본 파일이 교체/삭제될 수 있어 발송 시점에 복사한다.
function reflammer_mail_snapshot_pet_image($image) {
    $image = trim((string)$image);
    if ($image === '' || !defined('G5_DATA_URL') || !defined('G5_DATA_PATH')) return $image;
    $prefix = rtrim(G5_DATA_URL, '/').'/';
    if (strpos($image, $prefix) !== 0) return $image; // 외부 URL은 원 주소를 기록한다.
    $relative = substr($image, strlen($prefix));
    if ($relative === '' || strpos($relative, '..') !== false || !preg_match('#^[a-zA-Z0-9_./-]+$#', $relative)) return $image;
    $source = rtrim(G5_DATA_PATH, '/\\').'/'.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!is_file($source)) return $image;

    $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
    if (!in_array($extension, array('jpg', 'jpeg', 'png', 'gif', 'webp'), true)) return $image;
    $snapshot_dir = G5_DATA_PATH.'/module/reflammer_mail/snapshot';
    if (!is_dir($snapshot_dir) && !mkdir($snapshot_dir, G5_DIR_PERMISSION, true)) return false;
    $filename = 'pet_'.md5(uniqid((string)mt_rand(), true)).'.'.$extension;
    if (!copy($source, $snapshot_dir.'/'.$filename)) return false;
    return G5_DATA_URL.'/module/reflammer_mail/snapshot/'.$filename;
}

function reflammer_mail_pet_profile_html($ch_id, $manage = false) {
    $pets = reflammer_mail_pet_inventory($ch_id);
    $owned_pets = $manage ? reflammer_mail_owned_pet_items($ch_id) : array();
    if (!$manage && empty($pets)) return '';
    $pet_ids = array_map(function ($pet) { return (int)$pet['it_id']; }, $pets);
    $count = count($pets);
    $html = '<h3>PET</h3><div class="theme-box rm-pet-section">';
    if (!empty($pets)) {
        $html .= '<div class="rm-pet-grid">';
        foreach ($pets as $pet) {
            $html .= '<article class="theme-box rm-pet-card">';
            if (!empty($pet['it_img'])) {
                $html .= '<p><img src="'.reflammer_mail_e($pet['it_img']).'" alt="'.reflammer_mail_e($pet['it_name']).'" width="50" height="50"></p>';
            }
            $html .= '<h4>'.reflammer_mail_e($pet['it_name']).'</h4>';
            if (trim((string)$pet['it_content']) !== '') $html .= '<p>'.nl2br(reflammer_mail_e($pet['it_content'])).'</p>';
            if ($manage) $html .= reflammer_mail_pet_action_form($ch_id, (int)$pet['it_id'], 'remove', '메인 펫 해제');
            $html .= '</article>';
        }
        $html .= '</div>';
    } else {
        $html .= '<p>등록된 펫이 없습니다.</p>';
    }
    if ($manage) {
        if ($count < reflammer_mail_max_main_pets()) {
            $available = array();
            foreach ($owned_pets as $pet) if (!in_array((int)$pet['it_id'], $pet_ids, true)) $available[] = $pet;
            if ($available) {
                $html .= '<div class="rm-pet-register"><form method="post" action="'.reflammer_mail_e(G5_MDL_URL.'/reflammer_mail/pet_update.php').'">'
                    .'<input type="hidden" name="token" value="'.reflammer_mail_e(get_token()).'">'
                    .'<input type="hidden" name="ch_id" value="'.(int)$ch_id.'">'
                    .'<input type="hidden" name="mode" value="register">'
                    .'<select class="frm_input" name="it_id" required><option value="">펫 아이템 선택</option>';
                foreach ($available as $pet) {
                    $html .= '<option value="'.(int)$pet['it_id'].'">'.reflammer_mail_e($pet['it_name']).'</option>';
                }
                $html .= '</select><button type="submit" class="ui-btn point">추가</button></form></div>';
            }
        }
    }
    return $html.'</div>';
}

function reflammer_mail_pet_action_form($ch_id, $it_id, $mode, $label) {
    $url = G5_MDL_URL.'/reflammer_mail/pet_update.php';
    return '<form method="post" action="'.reflammer_mail_e($url).'">'
        .'<input type="hidden" name="token" value="'.reflammer_mail_e(get_token()).'">'
        .'<input type="hidden" name="ch_id" value="'.(int)$ch_id.'">'
        .'<input type="hidden" name="it_id" value="'.(int)$it_id.'">'
        .'<input type="hidden" name="mode" value="'.reflammer_mail_e($mode).'">'
        .'<button type="submit" class="ui-btn small">'.reflammer_mail_e($label).'</button></form>';
}
