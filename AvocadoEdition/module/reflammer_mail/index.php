<?php
include_once('../../common.php');
if (function_exists('mdl_do_action')) mdl_do_action('mypage_common');
require_once __DIR__.'/lib.php';

if (function_exists('mdl_load_state')) {
    $mail_state = mdl_load_state();
    if (!in_array('reflammer_mail', isset($mail_state['active']) ? $mail_state['active'] : array(), true)) {
        reflammer_mail_notice('편지 모듈이 활성화되지 않았습니다.', G5_URL);
    }
}
if (empty($member['mb_id'])) {
    reflammer_mail_notice('로그인한 커뮤니티 회원만 이용할 수 있습니다.', G5_BBS_URL.'/login.php');
}

$character = isset($character) && is_array($character) ? $character : array();
if (!reflammer_mail_sender_ok($character, $member)) {
    reflammer_mail_notice('편지를 이용하려면 승인된 캐릭터를 선택해야 합니다.', G5_URL.'/mypage/index.php');
}

$ch_id = (int)$character['ch_id'];
$owned_characters = reflammer_mail_owned_characters($member['mb_id']);
$owned_character_ids = array_map(function ($owned_character) { return (int)$owned_character['ch_id']; }, $owned_characters);
$owned_character_ids_sql = implode(',', $owned_character_ids);
$member_id_sql = sql_real_escape_string($member['mb_id']);
reflammer_mail_tick($member['mb_id']);

$g5['title'] = '편지';
reflammer_mail_add_stylesheet();
$base = G5_MDL_URL.'/reflammer_mail/index.php';
$letter_table = reflammer_mail_table('letter');
$item_table = reflammer_mail_table('item');

$action = isset($_GET['action']) ? $_GET['action'] : 'inbox';
$error = '';
$form_values = array(
    'se_ch_id' => $ch_id,
    're_ch_id' => (int)($_GET['re_ch_id'] ?? 0),
    'ml_self_recipient' => 0,
    'ml_nominal_recipient' => '',
    'ml_nominal_sender' => '',
    'mo_id' => 0,
    'pet_it_id' => 0,
    'ml_subject' => '',
    'ml_content' => '',
    'ml_anonymous' => 0,
    'ml_random' => 0,
    'attach' => array(),
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_token();
    $op = isset($_POST['op']) ? $_POST['op'] : '';
    $now = G5_TIME_YMDHIS;

    if ($op === 'save') {
        $se_ch_id = (int)($_POST['se_ch_id'] ?? 0);
        $is_self_recipient = !empty($_POST['ml_self_recipient']) ? 1 : 0;
        $is_random = !empty($_POST['ml_random']) ? 1 : 0;
        $re_ch_id = $is_self_recipient ? (int)($_POST['re_self_ch_id'] ?? 0) : (int)($_POST['re_ch_id'] ?? 0);
        $mo_id = (int)($_POST['mo_id'] ?? 0);
        $pet_it_id = (int)($_POST['pet_it_id'] ?? 0);
        $nominal_recipient = trim(strip_tags(reflammer_mail_input($_POST['ml_nominal_recipient'] ?? '')));
        $nominal_sender = trim(strip_tags(reflammer_mail_input($_POST['ml_nominal_sender'] ?? '')));
        $content = trim(strip_tags(reflammer_mail_input($_POST['ml_content'] ?? '')));
        $subject_source = preg_replace('/\s+/u', ' ', $content);
        $subject = mb_strlen($subject_source, 'UTF-8') > 80
            ? mb_substr($subject_source, 0, 79, 'UTF-8').'…'
            : $subject_source;
        $anonymous = !empty($_POST['ml_anonymous']) ? 1 : 0;
        $attach_ids = isset($_POST['attach']) && is_array($_POST['attach']) ? $_POST['attach'] : array();
        $form_values = array(
            'se_ch_id' => $se_ch_id,
            're_ch_id' => $re_ch_id,
            'ml_self_recipient' => $is_self_recipient,
            'ml_nominal_recipient' => $nominal_recipient,
            'ml_nominal_sender' => $nominal_sender,
            'mo_id' => $mo_id,
            'pet_it_id' => $pet_it_id,
            'ml_subject' => $subject,
            'ml_content' => $content,
            'ml_anonymous' => $anonymous,
            'ml_random' => $is_random,
            'attach' => array_values(array_filter(array_map('intval', $attach_ids), function ($id) { return $id > 0; })),
        );

        // 무작위 발송이면 전송된 re_ch_id 는 무시하고 서버에서 직접 상대를 고른다.
        $sender_character = reflammer_mail_find_sender($se_ch_id, $member['mb_id']);
        $recipient = $is_random ? reflammer_mail_find_random_recipient($member['mb_id']) : reflammer_mail_find_recipient($re_ch_id);
        $pet = $pet_it_id > 0 ? reflammer_mail_find_owned_pet($se_ch_id, $pet_it_id) : array();
        if ($is_self_recipient && isset($recipient['mb_id']) && $recipient['mb_id'] !== $member['mb_id']) $recipient = array();
        if (!$is_self_recipient && !$is_random && isset($recipient['mb_id']) && $recipient['mb_id'] === $member['mb_id']) $recipient = array();
        $owl = reflammer_mail_get_owl($mo_id);
        $point_balance = (int)(isset($member['mb_point']) ? $member['mb_point'] : 0);

        if ($is_random && $is_self_recipient) {
            $error = '무작위 발송과 자기 캐릭터 수신은 함께 선택할 수 없습니다.';
        } elseif (empty($sender_character['ch_id'])) {
            $error = '보내는 캐릭터 목록에서 승인된 본인 캐릭터를 선택하세요.';
        } elseif ($pet_it_id > 0 && empty($pet['it_id'])) {
            $error = '선택한 펫은 보내는 캐릭터가 보유한 펫 아이템이 아닙니다.';
        } elseif ($content === '' || mb_strlen($content, 'UTF-8') > 4000) {
            $error = '내용을 1~4000자로 입력하세요.';
        } elseif (empty($recipient['ch_id'])) {
            $error = $is_random
                ? '무작위로 보낼 수 있는 상대가 없습니다.'
                : '받는 캐릭터 목록에서 승인된 캐릭터를 선택하세요.';
        } elseif (mb_strlen($nominal_recipient, 'UTF-8') > 100 || mb_strlen($nominal_sender, 'UTF-8') > 100) {
            $error = '명목상 이름은 각각 100자 이내로 입력하세요.';
        } elseif (empty($owl) || empty($owl['mo_active'])) {
            $error = '보낼 부엉이를 선택하세요.';
        } elseif ((int)$owl['mo_cost'] > $point_balance) {
            $particle = function_exists('j') ? j($config['cf_money'], '이') : '이';
            $error = $config['cf_money'].$particle.' 부족합니다. ('.cf_money_format((int)$owl['mo_cost']).' 필요)';
        } else {
            $attachments = reflammer_mail_collect_attachments($se_ch_id, $attach_ids);
            $pet_image_snapshot = reflammer_mail_snapshot_pet_image(isset($pet['it_img']) ? $pet['it_img'] : '');
            if ($pet_image_snapshot === false) {
                $error = '펫 이미지를 편지 기록용으로 보존하지 못했습니다. 잠시 후 다시 시도하거나 관리자에게 문의하세요.';
            } else {

            $se_ch_name_sql = sql_real_escape_string($sender_character['ch_name']);
            $se_mb_id_sql = sql_real_escape_string($member['mb_id']);
            $re_ch_id_safe = (int)$recipient['ch_id'];
            $re_ch_name_sql = sql_real_escape_string($recipient['ch_name']);
            $nominal_sender_sql = sql_real_escape_string($nominal_sender);
            $nominal_recipient_sql = sql_real_escape_string($nominal_recipient);
            $subject_sql = sql_real_escape_string($subject);
            $content_sql = sql_real_escape_string($content);
            $mo_name_sql = sql_real_escape_string($owl['mo_name']);
            $owl_image = reflammer_mail_owl_image($owl);
            $owl_image_sql = sql_real_escape_string($owl_image);
            $pet_name_sql = sql_real_escape_string(isset($pet['it_name']) ? $pet['it_name'] : '');
            $pet_image_sql = sql_real_escape_string($pet_image_snapshot);
            $mo_hours = (int)$owl['mo_hours'];
            $mo_cost = (int)$owl['mo_cost'];
            $due = date('Y-m-d H:i:s', strtotime($now) + ($mo_hours * 3600));

            $insert_result = sql_query("INSERT INTO `{$letter_table}`
                (se_ch_id, se_ch_name, se_mb_id, ml_nominal_sender, re_ch_id, re_ch_name, ml_nominal_recipient, ml_anonymous, ml_random, ml_subject, ml_content,
                 ml_owl_image, ml_pet_it_id, ml_pet_name, ml_pet_image,
                 mo_id, mo_name, mo_hours, ml_sent_datetime, ml_due_datetime, ml_status)
                VALUES ('{$se_ch_id}','{$se_ch_name_sql}','{$se_mb_id_sql}','{$nominal_sender_sql}','{$re_ch_id_safe}','{$re_ch_name_sql}','{$nominal_recipient_sql}',{$anonymous},{$is_random},
                        '{$subject_sql}','{$content_sql}','{$owl_image_sql}','".(int)$pet_it_id."','{$pet_name_sql}','{$pet_image_sql}',
                        '".(int)$owl['mo_id']."','{$mo_name_sql}',{$mo_hours},
                        '{$now}','{$due}','in_transit')", false);
            $ml_id = $insert_result ? (int)sql_insert_id() : 0;

            if ($ml_id <= 0) {
                $db_error = isset($g5['connect_db']) ? mysqli_error($g5['connect_db']) : 'unknown database error';
                error_log('[reflammer_mail] letter insert failed: '.$db_error);
                $error = '편지를 저장하지 못했습니다. 관리자에게 모듈 재설치 여부를 확인해 달라고 요청하세요.';
            } else {
                if (!empty($attachments)) {
                    foreach ($attachments as $att) {
                        $att_it_id = (int)$att['it_id'];
                        $att_it_name_sql = sql_real_escape_string($att['it_name']);
                        sql_query("INSERT INTO `{$item_table}` (ml_id, it_id, it_name) VALUES ('{$ml_id}','{$att_it_id}','{$att_it_name_sql}')", false);
                        if (function_exists('delete_inventory')) delete_inventory((int)$att['in_id'], 0);
                    }
                }

                if ($mo_cost > 0 && function_exists('insert_point')) {
                    insert_point($member['mb_id'], $mo_cost * -1, '편지 발송 ('.$owl['mo_name'].' 비용 차감)', 'reflammer_mail', $ml_id, '편지발송');
                }

                reflammer_mail_notice('편지를 보냈습니다. 부엉이가 날아가고 있습니다.', $base.'?action=sent');
            }
            }
        }
    } elseif ($op === 'delete') {
        $ml_id = (int)($_POST['ml_id'] ?? 0);
        $side = isset($_POST['side']) ? (string)$_POST['side'] : '';
        if ($side === 'sent') {
            sql_query("UPDATE `{$letter_table}` SET ml_se_deleted=1 WHERE ml_id='{$ml_id}' AND se_mb_id='{$member_id_sql}'", false);
            reflammer_mail_notice('보낸편지함에서 삭제했습니다.', $base.'?action=sent');
        } else {
            sql_query("UPDATE `{$letter_table}` SET ml_re_deleted=1 WHERE ml_id='{$ml_id}' AND re_ch_id IN ({$owned_character_ids_sql})", false);
            reflammer_mail_notice('받은편지함에서 삭제했습니다.', $base.'?action=inbox');
        }
    }
}

include_once('../../mypage/_head.php');
?>
<main class="my-inner rm-shell">
 <header class="rm-header">
  <div><p>OWL POST</p><h1>편지</h1></div>
  <nav>
   <a class="ui-btn <?php echo $action === 'inbox' ? 'point' : ''; ?>" href="<?php echo reflammer_mail_e($base); ?>">받은편지함</a>
   <a class="ui-btn <?php echo $action === 'sent' ? 'point' : ''; ?>" href="<?php echo reflammer_mail_e($base.'?action=sent'); ?>">보낸편지함</a>
   <a class="ui-btn <?php echo $action === 'write' ? 'point' : ''; ?>" href="<?php echo reflammer_mail_e($base.'?action=write'); ?>">편지 쓰기</a>
   <a class="ui-btn" href="<?php echo reflammer_mail_e(G5_MDL_URL.'/reflammer_mail/board.php'); ?>">우체국</a>
  </nav>
  <div class="rm-point"><?php echo cf_money_format((int)(isset($member['mb_point']) ? $member['mb_point'] : 0)); ?></div>
 </header>
 <?php if ($error !== '') echo '<p class="rm-error">'.reflammer_mail_e($error).'</p>'; ?>

 <?php if ($action === 'write'): ?>
 <?php
   $owls = reflammer_mail_owls(true);
   $prefill_id = (int)$form_values['re_ch_id'];
   $prefill = $prefill_id ? reflammer_mail_find_recipient($prefill_id) : array();
   if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !empty($prefill['mb_id']) && $prefill['mb_id'] === $member['mb_id']) {
       $form_values['ml_self_recipient'] = 1;
   }
   $recipient_characters = array();
   $recipient_rs = sql_query("SELECT ch_id, ch_name, mb_id, ch_type FROM `{$g5['character_table']}`
       WHERE ch_state='승인' AND ch_type IN ('main','sub') ORDER BY ch_name ASC, ch_id ASC", false);
   while ($recipient_rs && $recipient_row = sql_fetch_array($recipient_rs)) $recipient_characters[] = $recipient_row;
   $sender_id = (int)$form_values['se_ch_id'];
   if (empty(reflammer_mail_find_sender($sender_id, $member['mb_id']))) $sender_id = $ch_id;
   $sender_inventories = array();
   $sender_pets = array();
   foreach ($owned_characters as $owned_character) {
       $owned_id = (int)$owned_character['ch_id'];
       $sender_inventories[$owned_id] = reflammer_mail_inventory($owned_id);
       $sender_pets[$owned_id] = reflammer_mail_pet_inventory($owned_id);
   }
 ?>
 <section class="rm-card">
  <h2>편지 쓰기</h2>
  <form method="post" class="rm-form">
   <input type="hidden" name="token" value="<?php echo get_token(); ?>">
   <input type="hidden" name="op" value="save">
   <p><label>실제 보내는 캐릭터 <small>(승인된 본인 캐릭터)</small>
    <select class="full" name="se_ch_id" id="rm_sender_character" required>
     <?php foreach ($owned_characters as $owned_character) { ?>
      <option value="<?php echo (int)$owned_character['ch_id']; ?>" <?php echo $sender_id === (int)$owned_character['ch_id'] ? 'selected' : ''; ?>>
       <?php echo reflammer_mail_e($owned_character['ch_name']); ?> (<?php echo $owned_character['ch_type'] === 'main' ? '메인' : '서브'; ?>)
      </option>
     <?php } ?>
    </select>
   </label></p>
   <p><label>실제 받는 캐릭터 <small>(다른 멤버의 승인된 main/sub 캐릭터)</small>
    <select class="full" name="re_ch_id" id="rm_recipient_character" required oninvalid="this.setCustomValidity('실제 받는 캐릭터 목록에서 캐릭터를 선택하세요.')" onchange="this.setCustomValidity('')">
     <option value="">캐릭터 선택</option>
     <?php foreach ($recipient_characters as $recipient_row) { if ($recipient_row['mb_id'] === $member['mb_id']) continue; ?>
      <option value="<?php echo (int)$recipient_row['ch_id']; ?>" <?php echo !$form_values['ml_self_recipient'] && (int)$form_values['re_ch_id'] === (int)$recipient_row['ch_id'] ? 'selected' : ''; ?>>
       <?php echo reflammer_mail_e($recipient_row['ch_name']); ?> (<?php echo $recipient_row['ch_type'] === 'main' ? '메인' : '서브'; ?>)
      </option>
     <?php } ?>
    </select>
   </label></p>
   <p><label><input type="checkbox" id="rm_self_recipient" name="ml_self_recipient" value="1" <?php echo $form_values['ml_self_recipient'] ? 'checked' : ''; ?>> 내 캐릭터에게 보내기</label></p>
   <p><label id="rm_self_recipient_wrap" <?php echo $form_values['ml_self_recipient'] ? '' : 'hidden'; ?>>실제 받는 내 캐릭터
    <select class="full" name="re_self_ch_id" id="rm_self_recipient_select">
     <option value="">캐릭터 선택</option>
     <?php foreach ($owned_characters as $owned_character) { ?>
      <option value="<?php echo (int)$owned_character['ch_id']; ?>" <?php echo $form_values['ml_self_recipient'] && (int)$form_values['re_ch_id'] === (int)$owned_character['ch_id'] ? 'selected' : ''; ?>>
       <?php echo reflammer_mail_e($owned_character['ch_name']); ?> (<?php echo $owned_character['ch_type'] === 'main' ? '메인' : '서브'; ?>)
      </option>
     <?php } ?>
    </select>
   </label></p>
   <p><label>편지에 쓸 받는 사람 <small>(자유 입력, 비우면 익명. 편지에 표시되는 받는 사람입니다)</small>
    <input class="frm_input full" type="text" name="ml_nominal_recipient" maxlength="100" value="<?php echo reflammer_mail_e($form_values['ml_nominal_recipient']); ?>" placeholder="예: 엄마">
   </label></p>
   <p><label><input type="checkbox" id="rm_random" name="ml_random" value="1" <?php echo $form_values['ml_random'] ? 'checked' : ''; ?>> 받는 사람을 무작위로 정하기 (누구에게 갔는지는 나에게도 알려주지 않습니다)</label></p>
   <p><label>내용<textarea class="frm_input" name="ml_content" rows="10" maxlength="4000" required><?php echo reflammer_mail_e($form_values['ml_content']); ?></textarea></label></p>
   <p><label>편지에 쓴 보내는 사람 <small>(자유 입력, 비우면 익명. 편지에 표시되는 보내는 사람입니다)</small>
    <input class="frm_input full" type="text" name="ml_nominal_sender" maxlength="100" value="<?php echo reflammer_mail_e($form_values['ml_nominal_sender']); ?>" placeholder="예: 마법부">
   </label></p>
   <p><label>보낼 부엉이
    <select class="full" name="mo_id" required oninvalid="this.setCustomValidity('보낼 부엉이 목록에서 부엉이를 선택하세요.')" onchange="this.setCustomValidity('')">
     <option value="">선택</option>
     <?php foreach ($owls as $owl) { ?>
      <option value="<?php echo (int)$owl['mo_id']; ?>" <?php echo (int)$form_values['mo_id'] === (int)$owl['mo_id'] ? 'selected' : ''; ?>>
       <?php echo reflammer_mail_e($owl['mo_name']); ?> — 배달 <?php echo (int)$owl['mo_hours']; ?>시간 · <?php echo cf_money_format((int)$owl['mo_cost']); ?>
      </option>
     <?php } ?>
    </select>
    <?php if (empty($owls)) echo '<small class="rm-empty-owl">사용 가능한 부엉이가 없습니다. 운영진에게 문의하세요.</small>'; ?>
   </label></p>
   <p><label>편지에 표시할 펫 <small>(메인으로 등록한 펫만 선택할 수 있습니다. 배달 시간과 비용은 선택한 부엉이를 따르며, 펫은 소모되지 않습니다)</small>
    <?php foreach ($owned_characters as $owned_character) { $owned_id = (int)$owned_character['ch_id']; ?>
     <select class="rm-sender-pets full" data-sender-id="<?php echo $owned_id; ?>" name="pet_it_id" <?php echo $sender_id === $owned_id ? '' : 'hidden disabled'; ?>>
      <option value="0">펫 없이 보내기</option>
      <?php foreach ($sender_pets[$owned_id] as $pet_option) { ?>
       <option value="<?php echo (int)$pet_option['it_id']; ?>" <?php echo (int)$form_values['pet_it_id'] === (int)$pet_option['it_id'] && $sender_id === $owned_id ? 'selected' : ''; ?>><?php echo reflammer_mail_e($pet_option['it_name']); ?></option>
      <?php } ?>
     </select>
    <?php } ?>
   </label></p>
   <p><label><input type="checkbox" name="ml_anonymous" value="1" <?php echo $form_values['ml_anonymous'] ? 'checked' : ''; ?>> 명목상 이름과 관계없이 실제 발신인을 익명으로 표시</label></p>
   <fieldset class="rm-attach">
    <legend>아이템 첨부 <small>(보내는 즉시 내 인벤토리에서 사라지고, 편지가 도착하면 받는 사람에게 지급됩니다)</small></legend>
    <?php foreach ($owned_characters as $owned_character) { $owned_id = (int)$owned_character['ch_id']; $inventory = $sender_inventories[$owned_id]; ?>
     <div class="rm-sender-inventory" data-sender-id="<?php echo $owned_id; ?>" <?php echo $sender_id === $owned_id ? '' : 'hidden'; ?>>
      <?php if (empty($inventory)): ?><p class="rm-empty">선택한 보내는 캐릭터가 가진 첨부 아이템이 없습니다.</p>
      <?php else: foreach ($inventory as $inv): ?>
       <label class="rm-checkbox"><input type="checkbox" name="attach[]" value="<?php echo (int)$inv['in_id']; ?>" <?php echo in_array((int)$inv['in_id'], $form_values['attach'], true) ? 'checked' : ''; ?>> <?php echo reflammer_mail_e($inv['it_name']); ?></label>
      <?php endforeach; endif; ?>
     </div>
    <?php } ?>
   </fieldset>
   <button type="submit" class="ui-btn point">편지 보내기</button>
  </form>
  <script>(function(){
   var random = document.getElementById('rm_random');
   var recipient = document.getElementById('rm_recipient_character');
   var selfRecipient = document.getElementById('rm_self_recipient');
   var selfSelect = document.getElementById('rm_self_recipient_select');
   var selfWrap = document.getElementById('rm_self_recipient_wrap');
   var sender = document.getElementById('rm_sender_character');
   if (!random || !recipient || !selfRecipient || !selfSelect || !selfWrap || !sender) return;
   function sync() {
     if (random.checked) selfRecipient.checked = false;
     if (selfRecipient.checked) random.checked = false;
     if (selfRecipient.checked) recipient.selectedIndex = 0;
     recipient.disabled = random.checked || selfRecipient.checked;
     recipient.required = !random.checked && !selfRecipient.checked;
     selfRecipient.disabled = random.checked;
     selfWrap.hidden = !selfRecipient.checked;
     selfSelect.disabled = !selfRecipient.checked;
     selfSelect.required = selfRecipient.checked;
   }
   function sync_inventory() {
     var lists = document.querySelectorAll('.rm-sender-inventory');
     for (var i = 0; i < lists.length; i++) {
       lists[i].hidden = lists[i].getAttribute('data-sender-id') !== sender.value;
     }
     var pets = document.querySelectorAll('.rm-sender-pets');
     for (var j = 0; j < pets.length; j++) {
       var active = pets[j].getAttribute('data-sender-id') === sender.value;
       pets[j].hidden = !active;
       pets[j].disabled = !active;
     }
   }
   random.addEventListener('change', sync);
   selfRecipient.addEventListener('change', sync);
   sender.addEventListener('change', sync_inventory);
   sync();
   sync_inventory();
  })();</script>
 </section>

 <?php elseif ($action === 'sent'): ?>
 <?php $rs = sql_query("SELECT * FROM `{$letter_table}` WHERE se_mb_id='{$member_id_sql}' AND ml_se_deleted=0 ORDER BY ml_sent_datetime DESC", false); ?>
 <section class="rm-card">
  <h2>보낸편지함</h2>
  <table class="rm-table">
   <thead><tr><th>내용</th><th>받는 사람</th><th>상태</th></tr></thead>
   <tbody>
   <?php $n = 0; while ($rs && $row = sql_fetch_array($rs)) { $n++; ?>
    <tr>
     <td><a href="<?php echo reflammer_mail_e($base.'?action=view&id='.$row['ml_id']); ?>"><?php echo reflammer_mail_e($row['ml_subject']); ?></a><?php if ($row['ml_anonymous']) echo ' <span class="ui-btn small">익명발송</span>'; ?><?php if ($row['ml_random']) echo ' <span class="ui-btn small">무작위발송</span>'; ?></td>
     <td>To. <?php echo reflammer_mail_recipient_label($row, true); ?></td>
     <td>
      <?php if ($row['ml_status'] === 'in_transit') { ?>
       배달 중 (<?php echo reflammer_mail_e(reflammer_mail_remaining_text($row['ml_due_datetime'])); ?>)
      <?php } elseif ($row['ml_read_datetime']) { ?>
       도착함 · 읽음
      <?php } else { ?>
       도착함 · <span class="ui-btn small point">읽지 않음</span>
      <?php } ?>
     </td>
    </tr>
   <?php } if (!$n) echo '<tr><td class="no-data" colspan="3">보낸 편지가 없습니다.</td></tr>'; ?>
   </tbody>
  </table>
 </section>

 <?php elseif ($action === 'view'): ?>
 <?php
   $id = (int)($_GET['id'] ?? 0);
   $letter = sql_fetch("SELECT * FROM `{$letter_table}` WHERE ml_id='{$id}'
       AND ((se_mb_id='{$member_id_sql}' AND ml_se_deleted=0)
         OR (re_ch_id IN ({$owned_character_ids_sql}) AND se_mb_id <> '{$member_id_sql}' AND ml_re_deleted=0)) LIMIT 1", false);
   $is_sender = $letter && $letter['se_mb_id'] === $member['mb_id'];
   $is_recipient = $letter && in_array((int)$letter['re_ch_id'], $owned_character_ids, true);
   if ($letter && $is_recipient && $letter['ml_status'] === 'delivered' && empty($letter['ml_read_datetime'])) {
       $now_read = G5_TIME_YMDHIS;
       sql_query("UPDATE `{$letter_table}` SET ml_read_datetime='{$now_read}' WHERE ml_id='{$id}'", false);
       $letter['ml_read_datetime'] = $now_read;
   }
 ?>
 <?php if (!$letter): ?>
  <p class="rm-empty">편지를 찾을 수 없습니다.</p>
 <?php else: ?>
 <section class="rm-card">
  <h2><?php echo reflammer_mail_e($letter['ml_subject']); ?></h2>
  <p class="rm-meta">
   From. <?php echo reflammer_mail_sender_label($letter, $is_recipient && !$is_sender && !empty($letter['ml_anonymous'])); ?>
   &nbsp;·&nbsp; To. <?php echo reflammer_mail_recipient_label($letter, $is_sender && !$is_recipient); ?>
   <?php if ($is_sender && $letter['ml_anonymous']) echo ' <span class="ui-btn small">내가 익명으로 보냄</span>'; ?>
   <?php if ($is_sender && $letter['ml_random']) echo ' <span class="ui-btn small">무작위로 보냄</span>'; ?>
  </p>
  <?php if ($is_recipient && !$is_sender && $letter['ml_status'] === 'in_transit'): ?>
   <?php $letter_companion_image = !empty($letter['ml_pet_image']) ? $letter['ml_pet_image'] : $letter['ml_owl_image']; $letter_companion_name = !empty($letter['ml_pet_name']) ? $letter['ml_pet_name'] : $letter['mo_name']; if ($letter_companion_image !== '') { ?><div class="rm-letter-companion"><img src="<?php echo reflammer_mail_e($letter_companion_image); ?>" alt="<?php echo reflammer_mail_e($letter_companion_name); ?>" width="50" height="50"><span><?php echo reflammer_mail_e($letter_companion_name); ?></span></div><?php } ?>
   <p class="rm-pending"><i class="fa fa-clock-o" aria-hidden="true"></i> 전달자가 아직 이동 중입니다. 도착 예정: <?php echo reflammer_mail_e(reflammer_mail_remaining_text($letter['ml_due_datetime'])); ?></p>
  <?php else: ?>
   <?php $letter_companion_image = !empty($letter['ml_pet_image']) ? $letter['ml_pet_image'] : $letter['ml_owl_image']; $letter_companion_name = !empty($letter['ml_pet_name']) ? $letter['ml_pet_name'] : $letter['mo_name']; if ($letter_companion_image !== '') { ?><div class="rm-letter-companion"><img src="<?php echo reflammer_mail_e($letter_companion_image); ?>" alt="<?php echo reflammer_mail_e($letter_companion_name); ?>" width="50" height="50"><span><?php echo reflammer_mail_e($letter_companion_name); ?></span></div><?php } ?>
   <div class="rm-content"><?php echo nl2br(reflammer_mail_e($letter['ml_content'])); ?></div>
   <?php if (!empty($letter['ml_pet_name'])) { ?><p>(<?php echo reflammer_mail_e($letter['ml_pet_name']); ?>으로 추정되는 녀석이 편지를 가져다주었다.)</p><?php } ?>
   <?php
     $att_rs = sql_query("SELECT it_name, mi_delivered FROM `{$item_table}` WHERE ml_id='{$id}'", false);
     $attachments_view = array();
     while ($att_rs && $att = sql_fetch_array($att_rs)) $attachments_view[] = $att;
   ?>
   <?php if (!empty($attachments_view)): ?>
    <div class="rm-attachments">
     <h3>첨부 아이템</h3>
     <ul>
      <?php foreach ($attachments_view as $att) { ?>
       <li><?php echo reflammer_mail_e($att['it_name']); ?> <?php echo $att['mi_delivered'] ? '(지급 완료)' : '(배달 대기)'; ?></li>
      <?php } ?>
     </ul>
    </div>
   <?php endif; ?>
  <?php endif; ?>
  <div class="rm-view-actions">
  <a class="ui-btn" href="<?php echo reflammer_mail_e($base.($is_sender ? '?action=sent' : '')); ?>">목록</a>
   <form method="post" onsubmit="return confirm('이 편지를 내 편지함에서 삭제할까요?');">
    <input type="hidden" name="token" value="<?php echo get_token(); ?>">
    <input type="hidden" name="op" value="delete">
    <input type="hidden" name="ml_id" value="<?php echo (int)$letter['ml_id']; ?>">
    <input type="hidden" name="side" value="<?php echo $is_sender ? 'sent' : 'inbox'; ?>">
    <button type="submit" class="ui-btn">삭제</button>
   </form>
  </div>
 </section>
 <?php endif; ?>

 <?php else: /* inbox */ ?>
 <?php $rs = sql_query("SELECT * FROM `{$letter_table}` WHERE re_ch_id IN ({$owned_character_ids_sql}) AND ml_re_deleted=0 AND se_mb_id <> '{$member_id_sql}' ORDER BY ml_sent_datetime DESC", false); ?>
 <section class="rm-card">
  <h2>받은편지함</h2>
  <table class="rm-table">
   <thead><tr><th>내용</th><th>보낸이</th><th>상태</th></tr></thead>
   <tbody>
   <?php $n = 0; while ($rs && $row = sql_fetch_array($rs)) { $n++; ?>
    <tr>
     <td><a href="<?php echo reflammer_mail_e($base.'?action=view&id='.$row['ml_id']); ?>"><?php echo reflammer_mail_e($row['ml_subject']); ?></a></td>
     <td>From. <?php echo reflammer_mail_sender_label($row, !empty($row['ml_anonymous'])); ?></td>
     <td>
      <?php if ($row['ml_status'] === 'in_transit') { ?>
       배달 중 (<?php echo reflammer_mail_e(reflammer_mail_remaining_text($row['ml_due_datetime'])); ?>)
      <?php } elseif ($row['ml_read_datetime']) { ?>
       도착함 · 읽음
      <?php } else { ?>
       도착함 · <span class="ui-btn small point">읽지 않음</span>
      <?php } ?>
     </td>
    </tr>
   <?php } if (!$n) echo '<tr><td class="no-data" colspan="3">받은 편지가 없습니다.</td></tr>'; ?>
   </tbody>
  </table>
 </section>
 <?php endif; ?>
</main>
<?php include_once('../../mypage/_tail.php'); ?>
