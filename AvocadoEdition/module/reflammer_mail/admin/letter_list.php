<?php
if (!defined('_GNUBOARD_')) exit;

$sub_menu = function_exists('mdl_admin_menu_code') ? mdl_admin_menu_code('reflammer_mail', '200') : '001200';
global $auth, $g5, $member;
if (isset($auth[$sub_menu])) auth_check($auth[$sub_menu], $_SERVER['REQUEST_METHOD'] === 'POST' ? 'w' : 'r');

require_once G5_MDL_PATH.'/reflammer_mail/lib.php';
$letter_table = reflammer_mail_table('letter');
$item_table = reflammer_mail_table('item');
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_demo();
    check_token();
    $mode = isset($_POST['mode']) ? (string)$_POST['mode'] : '';
    if ($mode === 'delete') {
        $ml_id = (int)($_POST['ml_id'] ?? 0);
        if ($ml_id > 0) {
            sql_query("DELETE FROM `{$item_table}` WHERE ml_id='{$ml_id}'", false);
            sql_query("DELETE FROM `{$letter_table}` WHERE ml_id='{$ml_id}' LIMIT 1", false);
            $message = '편지를 완전히 삭제했습니다. 이미 지급된 첨부 아이템은 회수되지 않습니다.';
        }
    }
}

$q_se = isset($_GET['q_se']) ? trim((string)$_GET['q_se']) : '';
$q_re = isset($_GET['q_re']) ? trim((string)$_GET['q_re']) : '';
$q_anon = isset($_GET['q_anon']) ? (string)$_GET['q_anon'] : '';
$q_random = isset($_GET['q_random']) ? (string)$_GET['q_random'] : '';
$where = '1=1';
if ($q_se !== '') $where .= " AND se_ch_name LIKE '%".sql_real_escape_string($q_se)."%'";
if ($q_re !== '') $where .= " AND re_ch_name LIKE '%".sql_real_escape_string($q_re)."%'";
if ($q_anon === '1') $where .= " AND ml_anonymous=1";
if ($q_random === '1') $where .= " AND ml_random=1";

$page = max(1, (int)($_GET['page'] ?? 1));
$per = 30;
$count = sql_fetch("SELECT COUNT(*) AS cnt FROM `{$letter_table}` WHERE {$where}", false);
$total = (int)(isset($count['cnt']) ? $count['cnt'] : 0);
$rs = sql_query("SELECT * FROM `{$letter_table}` WHERE {$where} ORDER BY ml_id DESC LIMIT ".(($page - 1) * $per).",{$per}", false);
?>
<div class="local_desc01 local_desc">
    <p>모든 편지의 실제 발신자가 노출됩니다(익명 편지 포함). 어뷰징·분쟁 조사용으로만 사용하고, 받는 캐릭터에게 그대로 전달하지 마세요.</p>
</div>
<?php if ($message !== '') { ?><div class="local_desc01 local_desc"><p><?php echo reflammer_mail_e($message); ?></p></div><?php } ?>
<form method="get" class="local_sch01 local_sch">
    <input type="hidden" name="m" value="reflammer_mail"><input type="hidden" name="p" value="letter_list">
    <input class="frm_input" type="text" name="q_se" placeholder="발신 캐릭터명" value="<?php echo reflammer_mail_e($q_se); ?>">
    <input class="frm_input" type="text" name="q_re" placeholder="수신 캐릭터명" value="<?php echo reflammer_mail_e($q_re); ?>">
    <label><input type="checkbox" name="q_anon" value="1" <?php echo $q_anon === '1' ? 'checked' : ''; ?>> 익명만</label>
    <label><input type="checkbox" name="q_random" value="1" <?php echo $q_random === '1' ? 'checked' : ''; ?>> 무작위만</label>
    <button type="submit" class="btn_submit">검색</button>
</form>
<div class="tbl_head01 tbl_wrap">
    <table>
        <caption>편지 내역 (총 <?php echo $total; ?>건)</caption>
        <thead><tr><th>ID</th><th>발신</th><th>수신</th><th>제목</th><th>부엉이</th><th>상태</th><th>발송일</th><th>관리</th></tr></thead>
        <tbody>
        <?php $n = 0; while ($rs && $row = sql_fetch_array($rs)) { $n++; ?>
            <tr>
                <td><?php echo (int)$row['ml_id']; ?></td>
                <td><?php echo reflammer_mail_e($row['se_ch_name']); ?> (<?php echo reflammer_mail_e($row['se_mb_id']); ?>)<?php if ($row['ml_anonymous']) echo ' <span class="rm-badge">익명발송</span>'; ?></td>
                <td><?php echo reflammer_mail_e($row['re_ch_name']); ?><?php if ($row['ml_random']) echo ' <span class="rm-badge">무작위발송</span>'; ?></td>
                <td title="<?php echo reflammer_mail_e($row['ml_content']); ?>"><?php echo reflammer_mail_e($row['ml_subject']); ?></td>
                <td><?php echo reflammer_mail_e($row['mo_name']); ?></td>
                <td><?php echo $row['ml_status'] === 'delivered' ? '도착' : '배달 중'; ?></td>
                <td><?php echo reflammer_mail_e($row['ml_sent_datetime']); ?></td>
                <td>
                    <form method="post" style="display:inline" onsubmit="return confirm('이 편지를 완전히 삭제할까요? 되돌릴 수 없습니다.');">
                        <input type="hidden" name="token" value="<?php echo reflammer_mail_e(get_token()); ?>">
                        <input type="hidden" name="mode" value="delete">
                        <input type="hidden" name="ml_id" value="<?php echo (int)$row['ml_id']; ?>">
                        <button type="submit" class="btn btn_02">완전삭제</button>
                    </form>
                </td>
            </tr>
        <?php } if (!$n) { ?><tr><td colspan="8" class="empty_table">조건에 맞는 편지가 없습니다.</td></tr><?php } ?>
        </tbody>
    </table>
    <?php if ($total > $per) echo get_paging(5, $page, (int)ceil($total / $per), G5_MDL_ADMIN_URL.'/module.php?m=reflammer_mail&p=letter_list&q_se='.rawurlencode($q_se).'&q_re='.rawurlencode($q_re).'&q_anon='.rawurlencode($q_anon).'&q_random='.rawurlencode($q_random).'&page='); ?>
</div>
