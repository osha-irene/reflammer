<?php
include_once('../../common.php');
require_once __DIR__.'/lib.php';

if (function_exists('mdl_load_state')) {
    $mail_state = mdl_load_state();
    if (!in_array('reflammer_mail', isset($mail_state['active']) ? $mail_state['active'] : array(), true)) {
        reflammer_mail_notice('편지 모듈이 활성화되지 않았습니다.', G5_URL);
    }
}
if (empty($member['mb_id'])) {
    reflammer_mail_notice('로그인이 필요합니다.', G5_BBS_URL.'/login.php');
}

check_token();
$ch_id = (int)($_POST['ch_id'] ?? 0);
$it_id = (int)($_POST['it_id'] ?? 0);
$mode = (string)($_POST['mode'] ?? '');
$mb_id_sql = sql_real_escape_string($member['mb_id']);
$character = sql_fetch("SELECT ch_id FROM {$g5['character_table']}
    WHERE ch_id='{$ch_id}' AND mb_id='{$mb_id_sql}' AND ch_state='승인'
      AND ch_type IN ('main','sub') LIMIT 1", false);
$return_url = G5_URL.'/member/viewer.php?ch_id='.$ch_id.'&tabs=bag';
if (empty($character['ch_id'])) {
    reflammer_mail_notice('본인이 소유한 승인 캐릭터만 펫을 관리할 수 있습니다.', G5_URL.'/mypage/character/');
}

reflammer_mail_prune_pet_registry($ch_id);
$pet_table = reflammer_mail_table('pet');
$message = '';
if ($mode === 'register') {
    $pet = reflammer_mail_owned_pet_items($ch_id);
    $is_owned_pet = false;
    foreach ($pet as $owned_pet) {
        if ((int)$owned_pet['it_id'] === $it_id) { $is_owned_pet = true; break; }
    }
    if (!$is_owned_pet) {
        $message = '보유 중인 펫 아이템만 메인 펫으로 등록할 수 있습니다.';
    } else {
        $existing = sql_fetch("SELECT it_id FROM {$pet_table} WHERE ch_id='{$ch_id}' AND it_id='{$it_id}' LIMIT 1", false);
        if (!empty($existing['it_id'])) {
            $message = '이미 메인 펫으로 등록되어 있습니다.';
        } else {
            $count_row = sql_fetch("SELECT COUNT(*) AS pet_count FROM {$pet_table} AS registered_pet
                INNER JOIN {$g5['item_table']} AS item ON item.it_id=registered_pet.it_id AND item.it_type='펫'
                WHERE registered_pet.ch_id='{$ch_id}'", false);
            if ((int)$count_row['pet_count'] >= reflammer_mail_max_main_pets()) {
                $message = '메인 펫은 최대 '.reflammer_mail_max_main_pets().'마리까지 등록할 수 있습니다.';
            } else {
                sql_query("INSERT IGNORE INTO {$pet_table} (ch_id, it_id, registered_datetime)
                    VALUES ('{$ch_id}','{$it_id}','".G5_TIME_YMDHIS."')", false);
                $message = '메인 펫으로 등록했습니다.';
            }
        }
    }
} elseif ($mode === 'remove') {
    sql_query("DELETE FROM {$pet_table} WHERE ch_id='{$ch_id}' AND it_id='{$it_id}' LIMIT 1", false);
    $message = '메인 펫 등록을 해제했습니다.';
} else {
    $message = '요청을 확인할 수 없습니다.';
}

reflammer_mail_notice($message, $return_url);
