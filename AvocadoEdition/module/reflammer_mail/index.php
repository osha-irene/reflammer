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
    reflammer_mail_notice('로그인한 커뮤니티 회원만 이용할 수 있습니다.', G5_BBS_URL.'/login.php');
}

$character = isset($character) && is_array($character) ? $character : array();
if (!reflammer_mail_sender_ok($character, $member)) {
    reflammer_mail_notice('편지를 이용하려면 승인된 캐릭터를 선택해야 합니다.', G5_URL.'/mypage/index.php');
}

$ch_id = (int)$character['ch_id'];
reflammer_mail_tick($ch_id);

$g5['title'] = '편지';
add_stylesheet('<link rel="stylesheet" href="'.G5_MDL_URL.'/reflammer_mail/mail.css">', 0);
$base = G5_MDL_URL.'/reflammer_mail/index.php';
$letter_table = reflammer_mail_table('letter');
$item_table = reflammer_mail_table('item');

$action = isset($_GET['action']) ? $_GET['action'] : 'inbox';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_token();
    $op = isset($_POST['op']) ? $_POST['op'] : '';
    $now = G5_TIME_YMDHIS;

    if ($op === 'save') {
        $re_ch_id = (int)($_POST['re_ch_id'] ?? 0);
        $mo_id = (int)($_POST['mo_id'] ?? 0);
        $subject = trim(strip_tags(reflammer_mail_input($_POST['ml_subject'] ?? '')));
        $content = trim(strip_tags(reflammer_mail_input($_POST['ml_content'] ?? '')));
        $anonymous = !empty($_POST['ml_anonymous']) ? 1 : 0;
        $is_random = !empty($_POST['ml_random']) ? 1 : 0;
        $attach_ids = isset($_POST['attach']) && is_array($_POST['attach']) ? $_POST['attach'] : array();

        // 무작위 발송이면 전송된 re_ch_id 는 무시하고 서버에서 직접 상대를 고른다.
        $recipient = $is_random ? reflammer_mail_find_random_recipient($ch_id) : reflammer_mail_find_recipient($re_ch_id);
        $owl = reflammer_mail_get_owl($mo_id);
        $point_balance = (int)(isset($member['mb_point']) ? $member['mb_point'] : 0);

        if ($subject === '' || mb_strlen($subject, 'UTF-8') > 100) {
            $error = '제목을 1~100자로 입력하세요.';
        } elseif ($content === '' || mb_strlen($content, 'UTF-8') > 4000) {
            $error = '내용을 1~4000자로 입력하세요.';
        } elseif (empty($recipient['ch_id'])) {
            $error = $is_random
                ? '무작위로 보낼 수 있는 상대가 없습니다.'
                : '받는 캐릭터 정보를 확인할 수 없습니다. 승인된 메인 캐릭터에게만 보낼 수 있습니다.';
        } elseif ((int)$recipient['ch_id'] === $ch_id) {
            $error = '자기 자신에게는 편지를 보낼 수 없습니다.';
        } elseif (empty($owl) || empty($owl['mo_active'])) {
            $error = '보낼 부엉이를 선택하세요.';
        } elseif ((int)$owl['mo_cost'] > $point_balance) {
            $particle = function_exists('j') ? j($config['cf_money'], '이') : '이';
            $error = $config['cf_money'].$particle.' 부족합니다. ('.number_format((int)$owl['mo_cost']).$config['cf_money_pice'].' 필요)';
        } else {
            $attachments = reflammer_mail_collect_attachments($ch_id, $attach_ids);

            $se_ch_name_sql = sql_real_escape_string($character['ch_name']);
            $se_mb_id_sql = sql_real_escape_string($member['mb_id']);
            $re_ch_id_safe = (int)$recipient['ch_id'];
            $re_ch_name_sql = sql_real_escape_string($recipient['ch_name']);
            $subject_sql = sql_real_escape_string($subject);
            $content_sql = sql_real_escape_string($content);
            $mo_name_sql = sql_real_escape_string($owl['mo_name']);
            $mo_hours = (int)$owl['mo_hours'];
            $mo_cost = (int)$owl['mo_cost'];
            $due = date('Y-m-d H:i:s', strtotime($now) + ($mo_hours * 3600));

            sql_query("INSERT INTO `{$letter_table}`
                (se_ch_id, se_ch_name, se_mb_id, re_ch_id, re_ch_name, ml_anonymous, ml_random, ml_subject, ml_content,
                 mo_id, mo_name, mo_hours, ml_sent_datetime, ml_due_datetime, ml_status)
                VALUES ('{$ch_id}','{$se_ch_name_sql}','{$se_mb_id_sql}','{$re_ch_id_safe}','{$re_ch_name_sql}',{$anonymous},{$is_random},
                        '{$subject_sql}','{$content_sql}','".(int)$owl['mo_id']."','{$mo_name_sql}',{$mo_hours},
                        '{$now}','{$due}','in_transit')", false);
            $ml_id = (int)sql_insert_id();

            if ($ml_id > 0 && !empty($attachments)) {
                foreach ($attachments as $att) {
                    $att_it_id = (int)$att['it_id'];
                    $att_it_name_sql = sql_real_escape_string($att['it_name']);
                    sql_query("INSERT INTO `{$item_table}` (ml_id, it_id, it_name) VALUES ('{$ml_id}','{$att_it_id}','{$att_it_name_sql}')", false);
                    if (function_exists('delete_inventory')) delete_inventory((int)$att['in_id'], 0);
                }
            }

            if ($ml_id > 0 && $mo_cost > 0 && function_exists('insert_point')) {
                insert_point($member['mb_id'], $mo_cost * -1, '편지 발송 ('.$owl['mo_name'].' 비용 차감)', 'reflammer_mail', $ml_id, '편지발송');
            }

            reflammer_mail_notice('편지를 보냈습니다. 부엉이가 날아가고 있습니다.', $base.'?action=sent');
        }
    } elseif ($op === 'delete') {
        $ml_id = (int)($_POST['ml_id'] ?? 0);
        $side = isset($_POST['side']) ? (string)$_POST['side'] : '';
        if ($side === 'sent') {
            sql_query("UPDATE `{$letter_table}` SET ml_se_deleted=1 WHERE ml_id='{$ml_id}' AND se_ch_id='{$ch_id}'", false);
            reflammer_mail_notice('보낸편지함에서 삭제했습니다.', $base.'?action=sent');
        } else {
            sql_query("UPDATE `{$letter_table}` SET ml_re_deleted=1 WHERE ml_id='{$ml_id}' AND re_ch_id='{$ch_id}'", false);
            reflammer_mail_notice('받은편지함에서 삭제했습니다.', $base.'?action=inbox');
        }
    }
}

include_once('../../head.php');
?>
<main class="rm-shell">
 <header class="rm-header">
  <div><p>OWL POST</p><h1>편지</h1></div>
  <nav>
   <a class="<?php echo $action === 'inbox' ? 'is-active' : ''; ?>" href="<?php echo reflammer_mail_e($base); ?>"><i class="fa fa-inbox" aria-hidden="true"></i> 받은편지함</a>
   <a class="<?php echo $action === 'sent' ? 'is-active' : ''; ?>" href="<?php echo reflammer_mail_e($base.'?action=sent'); ?>"><i class="fa fa-paper-plane" aria-hidden="true"></i> 보낸편지함</a>
   <a class="<?php echo $action === 'write' ? 'is-active' : ''; ?>" href="<?php echo reflammer_mail_e($base.'?action=write'); ?>"><i class="fa fa-pencil" aria-hidden="true"></i> 편지 쓰기</a>
  </nav>
  <div class="rm-point"><?php echo $config['cf_money']; ?> <?php echo number_format((int)(isset($member['mb_point']) ? $member['mb_point'] : 0)); ?><?php echo $config['cf_money_pice']; ?></div>
 </header>
 <?php if ($error !== '') echo '<p class="rm-error">'.reflammer_mail_e($error).'</p>'; ?>

 <?php if ($action === 'write'): ?>
 <?php
   $owls = reflammer_mail_owls(true);
   $prefill_id = (int)($_GET['re_ch_id'] ?? 0);
   $prefill = $prefill_id ? reflammer_mail_find_recipient($prefill_id) : array();
   $inventory = reflammer_mail_inventory($ch_id);
 ?>
 <section class="rm-card">
  <h2>편지 쓰기</h2>
  <form method="post" class="rm-form">
   <input type="hidden" name="token" value="<?php echo get_token(); ?>">
   <input type="hidden" name="op" value="save">
   <label>받는 캐릭터 <small>(승인된 메인 캐릭터만 받을 수 있습니다)</small>
    <input type="hidden" name="re_ch_id" id="rm_re_ch_id" value="<?php echo (int)($prefill['ch_id'] ?? 0); ?>">
    <input type="text" id="rm_re_name" value="<?php echo reflammer_mail_e($prefill['ch_name'] ?? ''); ?>" placeholder="받는 사람 이름 검색" autocomplete="off"
     onkeyup="get_ajax_character(this, 'rm_character_list', 'rm_re_ch_id', 'user');">
    <div id="rm_character_list" class="ajax-list-box"><div class="list"></div></div>
   </label>
   <label class="rm-checkbox"><input type="checkbox" id="rm_random" name="ml_random" value="1"> 받는 사람을 무작위로 정하기 (누구에게 갔는지는 나에게도 알려주지 않습니다)</label>
   <label>제목<input type="text" name="ml_subject" maxlength="100" required></label>
   <label>내용<textarea name="ml_content" rows="10" maxlength="4000" required></textarea></label>
   <label>보낼 부엉이
    <select name="mo_id" required>
     <option value="">선택</option>
     <?php foreach ($owls as $owl) { ?>
      <option value="<?php echo (int)$owl['mo_id']; ?>">
       <?php echo reflammer_mail_e($owl['mo_name']); ?> — 배달 <?php echo (int)$owl['mo_hours']; ?>시간 · <?php echo number_format((int)$owl['mo_cost']); ?><?php echo $config['cf_money_pice']; ?>
      </option>
     <?php } ?>
    </select>
    <?php if (empty($owls)) echo '<small class="rm-empty-owl">사용 가능한 부엉이가 없습니다. 운영진에게 문의하세요.</small>'; ?>
   </label>
   <label class="rm-checkbox"><input type="checkbox" name="ml_anonymous" value="1"> 익명으로 보내기 (받는 사람에게는 보낸 사람이 표시되지 않습니다)</label>
   <fieldset class="rm-attach">
    <legend>아이템 첨부 <small>(보내는 즉시 내 인벤토리에서 사라지고, 편지가 도착하면 받는 사람에게 지급됩니다)</small></legend>
    <?php if (empty($inventory)): ?>
     <p class="rm-empty">첨부할 수 있는 아이템이 없습니다.</p>
    <?php else: foreach ($inventory as $inv): ?>
     <label class="rm-checkbox"><input type="checkbox" name="attach[]" value="<?php echo (int)$inv['in_id']; ?>"> <?php echo reflammer_mail_e($inv['it_name']); ?></label>
    <?php endforeach; endif; ?>
   </fieldset>
   <button type="submit" class="rm-button">편지 보내기</button>
  </form>
  <script>(function(){
   var random = document.getElementById('rm_random');
   var name = document.getElementById('rm_re_name');
   var hidden = document.getElementById('rm_re_ch_id');
   if (!random || !name || !hidden) return;
   function sync() {
     name.disabled = random.checked;
     if (random.checked) { name.value = ''; hidden.value = ''; }
   }
   random.addEventListener('change', sync);
   sync();
  })();</script>
 </section>

 <?php elseif ($action === 'sent'): ?>
 <?php $rs = sql_query("SELECT * FROM `{$letter_table}` WHERE se_ch_id='{$ch_id}' AND ml_se_deleted=0 ORDER BY ml_sent_datetime DESC", false); ?>
 <section class="rm-card">
  <h2>보낸편지함</h2>
  <div class="rm-list">
  <?php $n = 0; while ($rs && $row = sql_fetch_array($rs)) { $n++; ?>
   <a class="rm-row" href="<?php echo reflammer_mail_e($base.'?action=view&id='.$row['ml_id']); ?>">
    <span class="rm-row-subject"><?php echo reflammer_mail_e($row['ml_subject']); ?></span>
    <span class="rm-row-meta">To. <?php echo reflammer_mail_recipient_label($row, true); ?><?php if ($row['ml_anonymous']) echo ' <span class="rm-badge">익명발송</span>'; ?><?php if ($row['ml_random']) echo ' <span class="rm-badge">무작위발송</span>'; ?></span>
    <span class="rm-row-status">
     <?php if ($row['ml_status'] === 'in_transit') { ?>
      배달 중 (<?php echo reflammer_mail_e(reflammer_mail_remaining_text($row['ml_due_datetime'])); ?>)
     <?php } else { ?>
      도착함<?php echo $row['ml_read_datetime'] ? ' · 읽음' : ' · 읽지 않음'; ?>
     <?php } ?>
    </span>
   </a>
  <?php } if (!$n) echo '<p class="rm-empty">보낸 편지가 없습니다.</p>'; ?>
  </div>
 </section>

 <?php elseif ($action === 'view'): ?>
 <?php
   $id = (int)($_GET['id'] ?? 0);
   $letter = sql_fetch("SELECT * FROM `{$letter_table}` WHERE ml_id='{$id}' AND (se_ch_id='{$ch_id}' OR re_ch_id='{$ch_id}') LIMIT 1", false);
   $is_sender = $letter && (int)$letter['se_ch_id'] === $ch_id;
   $is_recipient = $letter && (int)$letter['re_ch_id'] === $ch_id;
   if ($letter && $is_recipient && !$is_sender && $letter['ml_status'] === 'delivered' && empty($letter['ml_read_datetime'])) {
       $now_read = G5_TIME_YMDHIS;
       sql_query("UPDATE `{$letter_table}` SET ml_read_datetime='{$now_read}' WHERE ml_id='{$id}'", false);
       $letter['ml_read_datetime'] = $now_read;
   }
 ?>
 <?php if (!$letter): ?>
  <p class="rm-empty">편지를 찾을 수 없습니다.</p>
 <?php else: ?>
 <section class="rm-card rm-view">
  <h2><?php echo reflammer_mail_e($letter['ml_subject']); ?></h2>
  <p class="rm-meta">
   From. <?php echo ($is_recipient && !$is_sender && $letter['ml_anonymous']) ? '??? (익명)' : reflammer_mail_e($letter['se_ch_name']); ?>
   &nbsp;·&nbsp; To. <?php echo reflammer_mail_recipient_label($letter, $is_sender && !$is_recipient); ?>
   <?php if ($is_sender && $letter['ml_anonymous']) echo ' <span class="rm-badge">내가 익명으로 보냄</span>'; ?>
   <?php if ($is_sender && $letter['ml_random']) echo ' <span class="rm-badge">무작위로 보냄</span>'; ?>
  </p>
  <?php if ($is_recipient && !$is_sender && $letter['ml_status'] === 'in_transit'): ?>
   <p class="rm-pending"><i class="fa fa-clock-o" aria-hidden="true"></i> 부엉이가 아직 날아오고 있습니다. 도착 예정: <?php echo reflammer_mail_e(reflammer_mail_remaining_text($letter['ml_due_datetime'])); ?></p>
  <?php else: ?>
   <div class="rm-content"><?php echo nl2br(reflammer_mail_e($letter['ml_content'])); ?></div>
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
   <a class="rm-button rm-muted" href="<?php echo reflammer_mail_e($base.($is_sender && !$is_recipient ? '?action=sent' : '')); ?>">목록</a>
   <form method="post" onsubmit="return confirm('이 편지를 내 편지함에서 삭제할까요?');">
    <input type="hidden" name="token" value="<?php echo get_token(); ?>">
    <input type="hidden" name="op" value="delete">
    <input type="hidden" name="ml_id" value="<?php echo (int)$letter['ml_id']; ?>">
    <input type="hidden" name="side" value="<?php echo ($is_sender && !$is_recipient) ? 'sent' : 'inbox'; ?>">
    <button type="submit" class="rm-button rm-muted">삭제</button>
   </form>
  </div>
 </section>
 <?php endif; ?>

 <?php else: /* inbox */ ?>
 <?php $rs = sql_query("SELECT * FROM `{$letter_table}` WHERE re_ch_id='{$ch_id}' AND ml_re_deleted=0 ORDER BY ml_sent_datetime DESC", false); ?>
 <section class="rm-card">
  <h2>받은편지함</h2>
  <div class="rm-list">
  <?php $n = 0; while ($rs && $row = sql_fetch_array($rs)) { $n++; ?>
   <a class="rm-row" href="<?php echo reflammer_mail_e($base.'?action=view&id='.$row['ml_id']); ?>">
    <span class="rm-row-subject"><?php echo reflammer_mail_e($row['ml_subject']); ?><?php if ($row['ml_status'] === 'delivered' && !$row['ml_read_datetime']) echo ' <span class="rm-badge">NEW</span>'; ?></span>
    <span class="rm-row-meta">From. <?php echo $row['ml_anonymous'] ? '??? (익명)' : reflammer_mail_e($row['se_ch_name']); ?></span>
    <span class="rm-row-status">
     <?php if ($row['ml_status'] === 'in_transit') { ?>
      배달 중 (<?php echo reflammer_mail_e(reflammer_mail_remaining_text($row['ml_due_datetime'])); ?>)
     <?php } else { ?>
      도착함<?php echo $row['ml_read_datetime'] ? ' · 읽음' : ' · 읽지 않음'; ?>
     <?php } ?>
    </span>
   </a>
  <?php } if (!$n) echo '<p class="rm-empty">받은 편지가 없습니다.</p>'; ?>
  </div>
 </section>
 <?php endif; ?>
</main>
<?php include_once('../../tail.php'); ?>
