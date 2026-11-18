<?php
if (!defined('_GNUBOARD_')) exit;
require_once __DIR__.'/_common.php';
$msg='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    check_admin_token(); $id=(int)($_POST['we_id']??0); $op=$_POST['op']??''; $note=sql_real_escape_string(trim(strip_tags((string)($_POST['note']??''))));
    $t=reflammer_wiki_table('entry'); $e=sql_fetch("SELECT * FROM `{$t}` WHERE we_id={$id}");
    if ($e) {
        if ($op==='admin_save' && $e['we_status']!=='deleted') {
            $title=trim(strip_tags((string)($_POST['title']??''))); $content=trim((string)($_POST['content']??'')); $html_mode=!empty($_POST['html_mode'])?1:0;
            $category=(int)($_POST['category']??0); $cat=sql_fetch("SELECT wc_id FROM `".reflammer_wiki_table('category')."` WHERE wc_id={$category} AND wc_active=1");
            $image=reflammer_wiki_image_url($_POST['image']??''); $tags=reflammer_wiki_tags_store($_POST['tags']??'');
            if($title==='' || $content==='' || !$cat || $image===false) $msg='제목·내용·카테고리 또는 이미지 주소를 확인하세요.';
            else {
                $title=sql_real_escape_string($title); $content=sql_real_escape_string($content); $image=sql_real_escape_string($image?:'');
                $tags=sql_real_escape_string($tags); $person=sql_real_escape_string(trim(strip_tags((string)($_POST['subject_character']??''))));
                sql_query("UPDATE `{$t}` SET wc_id={$category},we_title='{$title}',we_content='{$content}',we_html={$html_mode},we_image='{$image}',we_tags='{$tags}',we_subject_character='{$person}',we_status='pending',we_updated='".G5_TIME_YMDHIS."',we_review_note='' WHERE we_id={$id}",false);
                $msg='운영자 수정본을 저장했습니다. 승인하면 공개본에 반영됩니다.'; $_GET['status']='pending';
            }
        } elseif ($op==='approve' && $e['we_status']!=='deleted') {
            $now=G5_TIME_YMDHIS;
            sql_query("UPDATE `{$t}` SET we_pub_wc_id=wc_id,we_pub_title=we_title,we_pub_content=we_content,we_pub_html=we_html,we_pub_subject_character=we_subject_character,we_pub_image=we_image,we_pub_tags=we_tags,we_status='published',we_published='{$now}',we_review_note='' WHERE we_id={$id}",false); $msg='승인하고 공개했습니다.';
        } elseif ($op==='reject' && $e['we_status']!=='deleted') {
            $next=($e['we_pub_title']!=='')?'published':'rejected';
            sql_query("UPDATE `{$t}` SET we_status='{$next}',we_review_note='{$note}' WHERE we_id={$id}",false); $msg='반려 사유를 기록했습니다.';
        } elseif ($op==='restore' && $e['we_status']==='deleted') {
            $next=($e['we_pub_title']!=='')?'published':'pending'; sql_query("UPDATE `{$t}` SET we_status='{$next}' WHERE we_id={$id}",false); $msg='문서를 복구했습니다.';
        }
    }
}
$filter=$_GET['status']??'pending'; $allowed=array('pending','published','rejected','deleted','all'); if(!in_array($filter,$allowed,true))$filter='pending';
$where=$filter==='all'?'1=1':"we_status='".sql_real_escape_string($filter)."'";
$rs=sql_query("SELECT * FROM `".reflammer_wiki_table('entry')."` WHERE {$where} ORDER BY we_updated DESC LIMIT 200");
$edit_id=(int)($_GET['edit']??0); $edit_row=$edit_id?sql_fetch("SELECT * FROM `".reflammer_wiki_table('entry')."` WHERE we_id={$edit_id} AND we_status<>'deleted'"):array();
?>
<div class="local_desc02"><p>새 글과 수정본을 검토합니다. 공개 문서의 수정본을 반려하면 이전 공개본은 유지됩니다.</p><?php if($msg) echo '<p><strong>'.reflammer_wiki_e($msg).'</strong></p>'; ?></div>
<style>
.local_ov01 a,.tbl_frm01 form a,.tbl_frm01 form button{display:inline-block;padding:8px 13px!important;border:0!important;border-radius:5px!important;background:#626a73!important;color:#fff!important;text-decoration:none!important;line-height:1.35;cursor:pointer}
.local_ov01 a:hover,.tbl_frm01 form a:hover,.tbl_frm01 form button:hover{background:#4b525a!important;color:#fff!important}
.local_ov01{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
</style>
<?php if($edit_row): ?>
<section class="tbl_frm01 tbl_wrap" style="margin:18px 0;padding:16px;background:#fff"><h2>운영진 문서 수정</h2><p>저장한 변경 사항은 승인 후 공개됩니다. 현재 공개본은 승인 전까지 유지됩니다.</p><form method="post"><input type="hidden" name="token" value="<?php echo get_admin_token(); ?>"><input type="hidden" name="op" value="admin_save"><input type="hidden" name="we_id" value="<?php echo (int)$edit_row['we_id']; ?>"><table><tr><th>분류</th><td><select name="category" required><?php reflammer_wiki_cat_options((int)$edit_row['wc_id']); ?></select></td></tr><tr><th>제목</th><td><input name="title" required maxlength="255" value="<?php echo reflammer_wiki_e($edit_row['we_title']); ?>"></td></tr><tr><th>대상 인물</th><td><input name="subject_character" value="<?php echo reflammer_wiki_e($edit_row['we_subject_character']); ?>"></td></tr><tr><th>태그</th><td><input name="tags" value="<?php echo reflammer_wiki_e(trim($edit_row['we_tags'],'|')); ?>"></td></tr><tr><th>이미지 URL</th><td><input name="image" value="<?php echo reflammer_wiki_e($edit_row['we_image']); ?>"></td></tr><tr><th>내용</th><td><textarea name="content" rows="14" required style="width:100%"><?php echo reflammer_wiki_e($edit_row['we_content']); ?></textarea><label><input type="checkbox" name="html_mode" value="1" <?php echo !empty($edit_row['we_html'])?'checked':''; ?>> HTML 입력 사용 (제한된 HTML 태그 가능, 줄바꿈은 기본 보존)</label></td></tr></table><button class="btn_submit">저장 후 승인 대기로</button></form></section>
<?php endif; ?>
<nav class="local_ov01"><a href="?m=reflammer_wiki&amp;p=entries&amp;status=pending">승인 대기</a><a href="?m=reflammer_wiki&amp;p=entries&amp;status=published">공개</a><a href="?m=reflammer_wiki&amp;p=entries&amp;status=rejected">반려</a><a href="?m=reflammer_wiki&amp;p=entries&amp;status=deleted">임시 삭제</a><a href="?m=reflammer_wiki&amp;p=entries&amp;status=all">전체</a></nav>
<?php while($e=sql_fetch_array($rs)): ?><section class="tbl_frm01 tbl_wrap" style="margin:18px 0;padding:16px;background:#fff;border:1px solid #ddd"><h2><?php echo reflammer_wiki_e($e['we_title']); ?> <small>#<?php echo (int)$e['we_id']; ?></small></h2><p>작성자 <?php echo reflammer_wiki_e($e['we_author_name']); ?><?php if($e['we_author_character'])echo ' ('.reflammer_wiki_e($e['we_author_character']).')'; ?> · 상태 <?php echo reflammer_wiki_e($e['we_status']); ?> · <?php echo reflammer_wiki_e($e['we_updated']); ?></p><p>카테고리 ID <?php echo (int)$e['wc_id']; ?> · 대상 인물 <?php echo reflammer_wiki_e($e['we_subject_character']); ?> · 태그 <?php echo reflammer_wiki_e(trim($e['we_tags'],'|')); ?></p><?php if($e['we_image'])echo '<p><img src="'.reflammer_wiki_e($e['we_image']).'" style="max-width:240px;max-height:160px" referrerpolicy="no-referrer"></p>'; ?><div style="white-space:pre-wrap;background:#fafafa;padding:12px;max-height:360px;overflow:auto"><?php echo reflammer_wiki_render_content($e['we_content'],!empty($e['we_html'])); ?></div><?php if($e['we_pub_title']!==''): ?><details style="margin-top:12px"><summary>현재 공개본과 비교</summary><h3><?php echo reflammer_wiki_e($e['we_pub_title']); ?></h3><div style="white-space:pre-wrap"><?php echo reflammer_wiki_render_content($e['we_pub_content'],!empty($e['we_pub_html'])); ?></div></details><?php endif; ?><?php if($e['we_review_note'])echo '<p>기존 반려 사유: '.reflammer_wiki_e($e['we_review_note']).'</p>'; ?><form method="post" style="margin-top:12px"><input type="hidden" name="token" value="<?php echo get_admin_token(); ?>"><input type="hidden" name="we_id" value="<?php echo (int)$e['we_id']; ?>"><?php if($e['we_status']==='deleted'): ?><input type="hidden" name="op" value="restore"><button class="btn_submit">복구</button><?php else: ?><a href="?m=reflammer_wiki&amp;p=entries&amp;status=<?php echo reflammer_wiki_e($filter); ?>&amp;edit=<?php echo (int)$e['we_id']; ?>">운영자 수정</a><?php if(in_array($e['we_status'],array('pending','rejected'),true)): ?> <button class="btn_submit" name="op" value="approve">승인·공개</button> <input name="note" value="" placeholder="반려 사유 (반려 시 작성자에게 표시)" style="width:40%"><button class="btn" name="op" value="reject">반려</button><?php endif; ?><?php endif; ?></form></section><?php endwhile; ?>
