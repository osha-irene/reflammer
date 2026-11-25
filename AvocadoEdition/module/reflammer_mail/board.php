<?php
include_once('../../common.php');
require_once __DIR__.'/lib.php';

if (empty($member['mb_id'])) {
    goto_url(G5_BBS_URL.'/login.php?url='.rawurlencode(G5_MDL_URL.'/reflammer_mail/board.php'));
}
if (function_exists('mdl_load_state')) {
    $mail_state = mdl_load_state();
    if (!in_array('reflammer_mail', isset($mail_state['active']) ? $mail_state['active'] : array(), true)) {
        reflammer_mail_notice('편지 모듈이 활성화되지 않았습니다.', G5_URL);
    }
}

// 공개 목록에서는 읽음 처리하지 않지만, 기한이 지난 편지는 도착 상태로 전환한다.
reflammer_mail_tick();

$letter_table = reflammer_mail_table('letter');
$board_url = G5_MDL_URL.'/reflammer_mail/board.php';
$letter_id = (int)($_GET['id'] ?? 0);
$search = trim((string)($_GET['q'] ?? ''));
$search_field = (string)($_GET['field'] ?? 'sender');
$search_columns = array('sender' => 'se_ch_name', 'recipient' => 're_ch_name', 'content' => 'ml_content');
if (!isset($search_columns[$search_field])) $search_field = 'sender';
$search_where = '';
if ($search !== '') {
    $search_sql = sql_real_escape_string($search);
    $search_like = '%'.$search_sql.'%';
    $search_column = $search_columns[$search_field];
    if ($search_field === 'content') {
        $search_where = " WHERE ml_status='delivered' AND ml_read_datetime IS NOT NULL AND {$search_column} LIKE '{$search_like}'";
    } else {
        $search_where = " WHERE {$search_column} LIKE '{$search_like}'";
    }
}
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$letter = array();

if ($letter_id > 0) {
    // 개인 편지함 삭제 플래그와 관계없이, 운영자가 완전 삭제하지 않은 편지는 공개 기록에 남는다.
    $letter = sql_fetch("SELECT * FROM `{$letter_table}` WHERE ml_id='{$letter_id}' LIMIT 1", false);
    if ($letter && $letter['ml_status'] === 'delivered' && empty($letter['ml_read_datetime'])) {
        $re_ch_id = (int)$letter['re_ch_id'];
        $recipient_owner = sql_fetch("SELECT mb_id FROM `{$g5['character_table']}` WHERE ch_id='{$re_ch_id}' LIMIT 1", false);
        if (!empty($recipient_owner['mb_id']) && $recipient_owner['mb_id'] === $member['mb_id']) {
            $read_at = sql_real_escape_string(G5_TIME_YMDHIS);
            sql_query("UPDATE `{$letter_table}` SET ml_read_datetime='{$read_at}' WHERE ml_id='{$letter_id}' AND ml_read_datetime IS NULL", false);
            $letter['ml_read_datetime'] = $read_at;
        }
    }
}

$g5['title'] = '우체국';
reflammer_mail_add_stylesheet();
include_once('../../mypage/_head.php');
?>
<main class="my-inner rm-shell">
 <header class="rm-header">
  <div><p>OWL POST</p><h1>우체국</h1></div>
  <nav>
   <a class="ui-btn" href="<?php echo reflammer_mail_e(G5_MDL_URL.'/reflammer_mail/index.php'); ?>">받은편지함</a>
   <a class="ui-btn" href="<?php echo reflammer_mail_e(G5_MDL_URL.'/reflammer_mail/index.php?action=sent'); ?>">보낸편지함</a>
   <a class="ui-btn" href="<?php echo reflammer_mail_e(G5_MDL_URL.'/reflammer_mail/index.php?action=write'); ?>">편지 쓰기</a>
   <a class="ui-btn point" href="<?php echo reflammer_mail_e($board_url); ?>">우체국</a>
  </nav>
 </header>

 <?php if ($letter_id > 0 && $letter): ?>
 <?php $companion = reflammer_mail_public_companion($letter); ?>
 <section class="rm-card rm-public-detail">
  <?php if ($letter['ml_status'] === 'in_transit'): ?>
   <h2>편지가 배달 중입니다</h2>
   <p>To. <?php echo reflammer_mail_public_recipient_label($letter); ?></p>
   <p class="rm-public-companion"><?php if ($companion['image'] !== ''): ?><img src="<?php echo reflammer_mail_e($companion['image']); ?>" alt="<?php echo reflammer_mail_e($companion['name']); ?>" width="72" height="72"><?php endif; ?> <span><?php echo reflammer_mail_e($companion['name']); ?></span></p>
   <p class="rm-pending">도착 예정: <?php echo reflammer_mail_e(reflammer_mail_remaining_text($letter['ml_due_datetime'])); ?></p>
  <?php else: ?>
   <h2>To. <?php echo reflammer_mail_public_recipient_label($letter); ?></h2>
   <p class="rm-public-status">편지가 도착했습니다</p>
   <?php $public_sender = reflammer_mail_public_sender_label($letter); if ($public_sender !== ''): ?><p class="rm-meta">From. <?php echo $public_sender; ?></p><?php endif; ?>
   <?php if (!empty($letter['ml_read_datetime'])): ?>
    <div class="rm-content"><?php echo nl2br(reflammer_mail_e($letter['ml_content'])); ?></div>
    <?php if (!empty($letter['ml_pet_name'])): ?><p>(<?php echo reflammer_mail_e($letter['ml_pet_name']); ?>으로 추정되는 녀석이 편지를 가져다주었다.)</p><?php endif; ?>
    <?php
      $item_table = reflammer_mail_table('item');
      $attachments = sql_query("SELECT it_name FROM `{$item_table}` WHERE ml_id='{$letter_id}' ORDER BY mi_id ASC", false);
      $attachment_rows = array();
      while ($attachments && $attachment = sql_fetch_array($attachments)) $attachment_rows[] = $attachment;
    ?>
    <?php if (!empty($attachment_rows)): ?>
     <div class="rm-attachments"><h3>첨부 아이템</h3><ul>
      <?php foreach ($attachment_rows as $attachment): ?><li><?php echo reflammer_mail_e($attachment['it_name']); ?></li><?php endforeach; ?>
     </ul></div>
    <?php endif; ?>
   <?php else: ?>
    <p class="rm-public-hidden">수신자가 편지를 열람하면 내용이 공개됩니다.</p>
   <?php endif; ?>
  <?php endif; ?>
  <div class="rm-view-actions"><a class="ui-btn" href="<?php echo reflammer_mail_e($board_url); ?>">우체국으로</a></div>
 </section>
 <?php elseif ($letter_id > 0): ?>
 <section class="rm-card"><p class="rm-empty">편지를 찾을 수 없습니다.</p><a class="ui-btn" href="<?php echo reflammer_mail_e($board_url); ?>">우체국으로</a></section>
 <?php else: ?>
 <?php
   $count_row = sql_fetch("SELECT COUNT(*) AS cnt FROM `{$letter_table}`{$search_where}", false);
   $total = (int)($count_row['cnt'] ?? 0);
   $total_pages = max(1, (int)ceil($total / $per_page));
   if ($page > $total_pages) $page = $total_pages;
   $offset = ($page - 1) * $per_page;
   $rows = sql_query("SELECT * FROM `{$letter_table}`{$search_where} ORDER BY ml_sent_datetime DESC, ml_id DESC LIMIT {$offset},{$per_page}", false);
   $paging_url = $board_url.'?'.($search !== '' ? 'q='.rawurlencode($search).'&field='.rawurlencode($search_field).'&' : '').'page=';
 ?>
 <section class="rm-card">
  <h2>우체국</h2>
  <p class="rm-empty">배달 중인 편지는 받는 이름과 전달자, 도착 예정 시간만 보입니다. 편지 내용은 도착 후 공개됩니다.</p>
  <?php if ($total < 1): ?><p class="no-data"><?php echo $search !== '' ? '검색 결과가 없습니다.' : '공개된 편지가 없습니다.'; ?></p><?php else: ?>
   <div class="rm-public-list">
   <?php while ($rows && ($row = sql_fetch_array($rows))): $companion = reflammer_mail_public_companion($row); ?>
    <article class="rm-public-card <?php echo ($row['ml_status'] === 'in_transit' || empty($row['ml_read_datetime'])) ? 'is-pending' : 'is-delivered'; ?>">
     <a href="<?php echo reflammer_mail_e($board_url.'?id='.(int)$row['ml_id']); ?>">
      <?php if ($companion['image'] !== ''): ?><span class="rm-public-card-image"><img src="<?php echo reflammer_mail_e($companion['image']); ?>" alt="<?php echo reflammer_mail_e($companion['name']); ?>" width="72" height="72"></span><?php endif; ?>
      <span class="rm-public-card-copy">
       <strong class="rm-public-recipient">To. <?php echo reflammer_mail_public_recipient_label($row); ?></strong>
       <span class="rm-public-status">전달자: <?php echo reflammer_mail_e($companion['name']); ?></span>
       <?php if ($row['ml_status'] === 'in_transit'): ?>
        <span class="rm-public-status">부엉이가 날아가고 있습니다 · <?php echo reflammer_mail_e(reflammer_mail_remaining_text($row['ml_due_datetime'])); ?></span>
       <?php elseif (empty($row['ml_read_datetime'])): ?>
        <span class="rm-public-status">수신자 열람 대기 중 · 내용 비공개</span>
       <?php else: ?>
        <span class="rm-public-excerpt"><?php echo reflammer_mail_e(mb_strlen((string)$row['ml_content'], 'UTF-8') > 120 ? mb_substr((string)$row['ml_content'], 0, 120, 'UTF-8').'…' : $row['ml_content']); ?></span>
        <?php $public_sender = reflammer_mail_public_sender_label($row); if ($public_sender !== ''): ?><span class="rm-public-sender">From. <?php echo $public_sender; ?></span><?php endif; ?>
       <?php endif; ?>
      </span>
      <time><?php echo reflammer_mail_e(date('m/d H:i', strtotime($row['ml_sent_datetime']))); ?></time>
     </a>
    </article>
   <?php endwhile; ?>
   </div>
   <?php if ($total_pages > 1) echo get_paging(5, $page, $total_pages, $paging_url); ?>
  <?php endif; ?>
  <form class="rm-board-search" method="get" action="<?php echo reflammer_mail_e($board_url); ?>">
   <select name="field" aria-label="검색 기준">
    <option value="sender" <?php echo $search_field === 'sender' ? 'selected' : ''; ?>>실제 보내는 이</option>
    <option value="recipient" <?php echo $search_field === 'recipient' ? 'selected' : ''; ?>>실제 받는 이</option>
    <option value="content" <?php echo $search_field === 'content' ? 'selected' : ''; ?>>편지 내용</option>
   </select>
   <input type="search" id="rm-board-search" name="q" value="<?php echo reflammer_mail_e($search); ?>" placeholder="검색어를 입력하세요">
   <button type="submit" class="ui-btn point">검색</button>
   <?php if ($search !== ''): ?><a class="ui-btn" href="<?php echo reflammer_mail_e($board_url); ?>">초기화</a><?php endif; ?>
  </form>
 </section>
 <?php endif; ?>
</main>
<?php include_once('../../mypage/_tail.php'); ?>
