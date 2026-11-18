<?php
include_once('../../common.php');
require_once __DIR__.'/lib.php';
if (function_exists('mdl_load_state')) { $wiki_state=mdl_load_state(); if (!in_array('reflammer_wiki', $wiki_state['active'] ?? array(), true)) reflammer_wiki_notice('위키 모듈이 활성화되지 않았습니다.', G5_URL); }
if (empty($member['mb_id'])) reflammer_wiki_notice('로그인한 커뮤니티 회원만 이용할 수 있습니다.', G5_BBS_URL.'/login.php');
$g5['title'] = '펜시브 · The Pensieve';
add_stylesheet('<link rel="stylesheet" href="'.G5_URL.'/module/reflammer_wiki/wiki.css">', 0);
$base = G5_URL.'/module/reflammer_wiki/index.php';
$cats = reflammer_wiki_categories(); $subs = array();
foreach ($cats as $c) $subs[(int)$c['wc_id']] = reflammer_wiki_categories((int)$c['wc_id']);
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$is_mod = reflammer_wiki_is_moderator();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_token();
    $op = isset($_POST['op']) ? $_POST['op'] : '';
    $now = G5_TIME_YMDHIS;
    if ($op === 'save') {
        $id = (int)($_POST['we_id'] ?? 0); $title = trim(strip_tags((string)($_POST['title'] ?? '')));
        $content = trim((string)($_POST['content'] ?? '')); $html_mode = !empty($_POST['html_mode']) ? 1 : 0; $category = (int)($_POST['category'] ?? 0);
        $person = trim(strip_tags((string)($_POST['subject_character'] ?? ''))); $image = reflammer_wiki_image_url($_POST['image'] ?? '');
        $tags = reflammer_wiki_tags_store($_POST['tags'] ?? ''); $cat = reflammer_wiki_find_category($category);
        if ($title === '' || $content === '' || !$cat) $error = '제목, 내용, 유효한 카테고리를 입력하세요.';
        elseif ($image === false) $error = 'HTTPS 형식의 외부 주소를 입력하세요.';
        else {
            foreach (reflammer_wiki_tags($_POST['tags'] ?? '') as $tag) {
                $te = sql_real_escape_string($tag); sql_query("INSERT IGNORE INTO `".reflammer_wiki_table('tag')."` (wt_name) VALUES ('{$te}')", false);
            }
            $me = sql_real_escape_string($member['mb_id']); $author = sql_real_escape_string($member['mb_nick'] ?: $member['mb_name']);
            $ch = sql_real_escape_string(reflammer_wiki_author_character($member['mb_id']));
            $te_title = sql_real_escape_string($title); $te_body = sql_real_escape_string($content); $te_img = sql_real_escape_string($image ?: '');
            $te_tags = sql_real_escape_string($tags); $te_person = sql_real_escape_string($person);
            if ($id) {
                $old = sql_fetch("SELECT * FROM `".reflammer_wiki_table('entry')."` WHERE we_id={$id} AND we_author_id='{$me}' AND we_status<>'deleted'");
                if (!$old) $error = '수정할 수 있는 문서를 찾지 못했습니다.';
                else {
                    sql_query("UPDATE `".reflammer_wiki_table('entry')."` SET wc_id={$category},we_title='{$te_title}',we_content='{$te_body}',we_html={$html_mode},we_image='{$te_img}',we_tags='{$te_tags}',we_subject_character='{$te_person}',we_status='pending',we_updated='{$now}',we_review_note='' WHERE we_id={$id}", false);
                    reflammer_wiki_notice('수정본을 승인 대기열에 등록했습니다. 기존 공개본은 승인 완료까지 유지됩니다.', $base.'?action=mine');
                }
            } else {
                sql_query("INSERT INTO `".reflammer_wiki_table('entry')."` (wc_id,we_title,we_content,we_html,we_image,we_tags,we_subject_character,we_status,we_author_id,we_author_name,we_author_character,we_created,we_updated) VALUES ({$category},'{$te_title}','{$te_body}',{$html_mode},'{$te_img}','{$te_tags}','{$te_person}','pending','{$me}','{$author}','{$ch}','{$now}','{$now}')", false);
                reflammer_wiki_notice('문서를 등록했습니다. 운영진 승인 후 공개됩니다.', $base.'?action=mine');
            }
        }
    } elseif ($op === 'delete') {
        $id = (int)($_POST['we_id'] ?? 0); $me = sql_real_escape_string($member['mb_id']);
        sql_query("UPDATE `".reflammer_wiki_table('entry')."` SET we_status='deleted',we_updated='{$now}' WHERE we_id={$id} AND we_author_id='{$me}' AND we_status IN ('published','pending') AND we_pub_title<>''", false);
        reflammer_wiki_notice('문서를 임시 삭제했습니다. 운영진이 복구할 수 있습니다.', $base.'?action=mine');
    } elseif ($op === 'moderator_save' && $is_mod) {
        $id=(int)($_POST['we_id']??0); $old=sql_fetch("SELECT * FROM `".reflammer_wiki_table('entry')."` WHERE we_id={$id} AND we_status<>'deleted'");
        $title=trim(strip_tags((string)($_POST['title']??''))); $content=trim((string)($_POST['content']??'')); $html_mode=!empty($_POST['html_mode'])?1:0; $category=(int)($_POST['category']??0);
        $cat=reflammer_wiki_find_category($category); $person=trim(strip_tags((string)($_POST['subject_character']??'')));
        $image=reflammer_wiki_image_url($_POST['image']??''); $tags=reflammer_wiki_tags_store($_POST['tags']??'');
        if(!$old || $title==='' || $content==='' || !$cat || $image===false) reflammer_wiki_notice('문서 입력값을 확인하세요.',$base.'?action=review');
        foreach(reflammer_wiki_tags($_POST['tags']??'') as $tag){$te=sql_real_escape_string($tag);sql_query("INSERT IGNORE INTO `".reflammer_wiki_table('tag')."` (wt_name) VALUES ('{$te}')",false);}
        $title=sql_real_escape_string($title);$content=sql_real_escape_string($content);$image=sql_real_escape_string($image?:'');$tags=sql_real_escape_string($tags);$person=sql_real_escape_string($person);
        sql_query("UPDATE `".reflammer_wiki_table('entry')."` SET wc_id={$category},we_title='{$title}',we_content='{$content}',we_html={$html_mode},we_subject_character='{$person}',we_image='{$image}',we_tags='{$tags}',we_status='pending',we_updated='{$now}',we_review_note='' WHERE we_id={$id}",false);
        reflammer_wiki_notice('운영진 수정본을 승인 대기 상태로 저장했습니다.',$base.'?action=review');
    } elseif (in_array($op, array('approve','reject','restore'), true) && $is_mod) {
        $id=(int)($_POST['we_id']??0); $e=sql_fetch("SELECT * FROM `".reflammer_wiki_table('entry')."` WHERE we_id={$id}");
        if($e && $op==='approve' && $e['we_status']!=='deleted') {
            sql_query("UPDATE `".reflammer_wiki_table('entry')."` SET we_pub_wc_id=wc_id,we_pub_title=we_title,we_pub_content=we_content,we_pub_html=we_html,we_pub_subject_character=we_subject_character,we_pub_image=we_image,we_pub_tags=we_tags,we_status='published',we_published='{$now}',we_review_note='' WHERE we_id={$id}",false);
            reflammer_wiki_notice('문서를 승인해 공개했습니다.',$base.'?action=review');
        } elseif($e && $op==='reject' && $e['we_status']!=='deleted') {
            $note=sql_real_escape_string(trim(strip_tags((string)($_POST['note']??'')))); $next=$e['we_pub_title']!==''?'published':'rejected';
            sql_query("UPDATE `".reflammer_wiki_table('entry')."` SET we_status='{$next}',we_review_note='{$note}' WHERE we_id={$id}",false);
            reflammer_wiki_notice('반려 사유를 저장했습니다.',$base.'?action=review');
        } elseif($e && $op==='restore' && $e['we_status']==='deleted') {
            $next=$e['we_pub_title']!==''?'published':'pending'; sql_query("UPDATE `".reflammer_wiki_table('entry')."` SET we_status='{$next}' WHERE we_id={$id}",false);
            reflammer_wiki_notice('문서를 복구했습니다.',$base.'?action=review');
        }
    }
}

include_once('../../head.php');
?>
<main class="rw-shell">
 <header class="rw-header"><div class="rw-mark">✦</div><div><p>THE PENSIEVE</p><h1>펜시브</h1></div><nav><a href="<?php echo reflammer_wiki_e($base); ?>">정보 열람</a><a href="<?php echo reflammer_wiki_e($base.'?action=write'); ?>">정보 등록</a><?php if($is_mod): ?><a href="<?php echo reflammer_wiki_e($base.'?action=review'); ?>">운영진 검토</a><?php endif; ?></nav></header>
 <?php if ($error !== '') echo '<p class="rw-error">'.reflammer_wiki_e($error).'</p>'; ?>
 <?php if ($action === 'write' || $action === 'edit'):
   $entry = array(); $id = (int)($_GET['id'] ?? 0);
   if ($action === 'edit' && $id) { $me=sql_real_escape_string($member['mb_id']); $entry=sql_fetch("SELECT * FROM `".reflammer_wiki_table('entry')."` WHERE we_id={$id} AND we_author_id='{$me}' AND we_status IN ('pending','published','rejected')"); if (!$entry) echo '<p class="rw-empty">수정 권한이 있는 문서가 없습니다.</p>'; }
 ?>
 <section class="rw-card"><h2><?php echo $entry ? '문서 수정' : '새 정보 등록'; ?></h2><p class="rw-note">등록과 수정은 운영진 승인 후 공개됩니다. 공개 문서 수정 시 이전 공개본은 승인 완료까지 유지됩니다.</p>
 <form method="post" class="rw-form"><input type="hidden" name="token" value="<?php echo get_token(); ?>"><input type="hidden" name="op" value="save"><input type="hidden" name="we_id" value="<?php echo (int)($entry['we_id'] ?? 0); ?>">
 <label>카테고리<select name="category" required><option value="">선택</option><?php foreach($cats as $c): ?><option value="<?php echo (int)$c['wc_id']; ?>" <?php echo get_selected((int)($entry['wc_id'] ?? 0),(int)$c['wc_id']); ?>><?php echo reflammer_wiki_e($c['wc_name']); ?></option><?php foreach(($subs[$c['wc_id']] ?? array()) as $s): ?><option value="<?php echo (int)$s['wc_id']; ?>" <?php echo get_selected((int)($entry['wc_id'] ?? 0),(int)$s['wc_id']); ?>>　└ <?php echo reflammer_wiki_e($s['wc_name']); ?></option><?php endforeach; endforeach; ?></select></label>
 <label>제목<input name="title" maxlength="255" required value="<?php echo reflammer_wiki_e($entry['we_title'] ?? ''); ?>"></label>
 <label>대상 인물/캐릭터 이름 <small>(작성자와 무관, NPC 포함)</small><input name="subject_character" value="<?php echo reflammer_wiki_e($entry['we_subject_character'] ?? ''); ?>"></label>
 <label>해시태그<input name="tags" placeholder="#공식, #주요서사" value="<?php echo reflammer_wiki_e(trim($entry['we_tags'] ?? '', '|')); ?>"><small>기존 태그를 선택하거나 새 태그를 쉼표로 구분해 입력할 수 있습니다.</small><span class="rw-tag-suggest"><?php $tr=sql_query("SELECT wt_name FROM `".reflammer_wiki_table('tag')."` ORDER BY wt_name"); while($t=sql_fetch_array($tr)) echo '<button type="button" data-tag="'.reflammer_wiki_e($t['wt_name']).'">#'.reflammer_wiki_e($t['wt_name']).'</button> '; ?></span></label>
 <label>이미지 외부 주소 <small>이미지 파일을 가리키는 HTTPS 주소를 입력하세요. Tistory 등 외부 이미지 주소도 가능하며, 파일은 서버에 저장하지 않습니다.</small><input class="rw-image-input" type="url" name="image" placeholder="https://example.com/path/image.jpg" value="<?php echo reflammer_wiki_e($entry['we_image'] ?? ''); ?>"><img class="rw-image-preview" alt="입력한 이미지 미리보기" hidden><small class="rw-image-error" hidden>이 주소에서 이미지를 불러오지 못했습니다. 게시글 주소가 아닌 이미지 파일 주소인지 확인하세요.</small></label>
 <label>내용<textarea name="content" rows="16" required><?php echo reflammer_wiki_e($entry['we_content'] ?? ''); ?></textarea><span class="rw-html-help"><input type="checkbox" name="html_mode" value="1" <?php echo !empty($entry['we_html'])?'checked':''; ?>> HTML 입력 사용 — 체크하면 p, br, strong, ul, li, a, img 등의 제한된 태그를 사용할 수 있습니다. 이미지에는 HTTPS 주소를 사용하세요. 체크하지 않아도 줄바꿈은 공개 본문에 반영됩니다.</span></label><button class="rw-button" type="submit">승인 요청</button></form></section>
 <?php elseif ($action === 'view'):
   $id=(int)($_GET['id']??0); $entry=sql_fetch("SELECT e.* FROM `".reflammer_wiki_table('entry')."` e WHERE e.we_id={$id} AND e.we_status IN ('published','pending') AND e.we_pub_title<>''");
   if ($entry): $selected=(int)$entry['we_pub_wc_id']; ?>
 <div class="rw-layout">
  <aside class="rw-card rw-categories"><h2>카테고리</h2><a class="rw-all <?php echo !$selected?'is-active':''; ?>" href="<?php echo reflammer_wiki_e($base); ?>"><strong>전체</strong></a><?php foreach($cats as $c): $children=$subs[$c['wc_id']]??array(); $open=($selected===(int)$c['wc_id']); foreach($children as $child){if($selected===(int)$child['wc_id'])$open=true;} $sub_id='rw-category-children-'.(int)$c['wc_id']; ?><div class="rw-category-group"><div class="rw-category-head"><?php if($children): ?><button type="button" class="rw-category-main <?php echo $selected===(int)$c['wc_id']?'is-active':''; ?>" data-submenu="<?php echo $sub_id; ?>" aria-expanded="<?php echo $open?'true':'false'; ?>"><?php echo reflammer_wiki_e($c['wc_name']); ?><span aria-hidden="true">⌄</span></button><?php else: ?><a class="rw-category-main rw-category-label" href="<?php echo reflammer_wiki_e($base.'?category='.$c['wc_id']); ?>"><?php echo reflammer_wiki_e($c['wc_name']); ?></a><?php endif; ?></div><?php if($children): ?><div class="rw-category-children" id="<?php echo $sub_id; ?>" <?php echo $open?'':'hidden'; ?>><?php foreach($children as $s): ?><a class="rw-sub <?php echo $selected===(int)$s['wc_id']?'is-active':''; ?>" href="<?php echo reflammer_wiki_e($base.'?category='.$s['wc_id']); ?>">└ <?php echo reflammer_wiki_e($s['wc_name']); ?></a><?php endforeach; ?></div><?php endif; ?></div><?php endforeach; ?></aside>
  <article class="rw-card rw-article"><h2><?php echo reflammer_wiki_e($entry['we_pub_title']); ?></h2>
   <?php if($entry['we_pub_tags']): ?><div class="rw-tags"><?php foreach(array_filter(explode('|',trim($entry['we_pub_tags'],'|'))) as $t): ?><a href="<?php echo reflammer_wiki_e($base.'?tag='.rawurlencode($t)); ?>">#<?php echo reflammer_wiki_e($t); ?></a><?php endforeach; ?></div><?php endif; ?>
   <?php if($entry['we_pub_image']) echo '<img class="rw-image" src="'.reflammer_wiki_e($entry['we_pub_image']).'" referrerpolicy="no-referrer" alt="">'; ?>
   <div class="rw-content"><?php echo reflammer_wiki_render_content($entry['we_pub_content'], !empty($entry['we_pub_html'])); ?></div>
   <footer class="rw-post-footer"><div class="rw-actions"><a href="<?php echo reflammer_wiki_e($base.'?category='.$selected); ?>">목록</a><?php if($entry['we_author_id']===$member['mb_id']): ?><a href="<?php echo reflammer_wiki_e($base.'?action=edit&id='.$id); ?>">수정</a><form method="post" onsubmit="return confirm('문서를 임시 삭제할까요? 운영진이 복구할 수 있습니다.');"><input type="hidden" name="token" value="<?php echo get_token(); ?>"><input type="hidden" name="op" value="delete"><input type="hidden" name="we_id" value="<?php echo $id; ?>"><button>삭제</button></form><?php endif; ?></div><div class="rw-meta rw-post-byline"><?php echo reflammer_wiki_e($entry['we_author_name']); ?><?php if($entry['we_author_character']) echo ' ('.reflammer_wiki_e($entry['we_author_character']).')'; ?><br>최종 수정 <?php echo date('Y.m.d',strtotime($entry['we_published'])); ?></div></footer>
  </article>
 </div>
 <?php else: ?><p class="rw-empty">공개된 문서를 찾을 수 없습니다.</p><?php endif; ?>
 <?php elseif ($action === 'review_edit' && $is_mod):
   $id=(int)($_GET['id']??0); $entry=sql_fetch("SELECT * FROM `".reflammer_wiki_table('entry')."` WHERE we_id={$id} AND we_status<>'deleted'"); ?>
 <?php if($entry): ?><section class="rw-card"><h2>운영진 문서 수정</h2><p class="rw-note">저장한 변경 사항은 승인 후 공개됩니다. 기존 공개본은 승인 전까지 유지됩니다.</p><form method="post" class="rw-form"><input type="hidden" name="token" value="<?php echo get_token(); ?>"><input type="hidden" name="op" value="moderator_save"><input type="hidden" name="we_id" value="<?php echo $id; ?>"><label>카테고리<select name="category" required><?php foreach($cats as $c): ?><option value="<?php echo (int)$c['wc_id']; ?>" <?php echo get_selected((int)$entry['wc_id'],(int)$c['wc_id']); ?>><?php echo reflammer_wiki_e($c['wc_name']); ?></option><?php foreach($subs[$c['wc_id']]??array() as $s): ?><option value="<?php echo (int)$s['wc_id']; ?>" <?php echo get_selected((int)$entry['wc_id'],(int)$s['wc_id']); ?>>└ <?php echo reflammer_wiki_e($s['wc_name']); ?></option><?php endforeach; endforeach; ?></select></label><label>제목<input name="title" required value="<?php echo reflammer_wiki_e($entry['we_title']); ?>"></label><label>대상 인물<input name="subject_character" value="<?php echo reflammer_wiki_e($entry['we_subject_character']); ?>"></label><label>태그<input name="tags" value="<?php echo reflammer_wiki_e(trim($entry['we_tags'],'|')); ?>"></label><label>이미지 URL<input name="image" value="<?php echo reflammer_wiki_e($entry['we_image']); ?>"></label><label>내용<textarea name="content" rows="15" required><?php echo reflammer_wiki_e($entry['we_content']); ?></textarea><span class="rw-html-help"><input type="checkbox" name="html_mode" value="1" <?php echo !empty($entry['we_html'])?'checked':''; ?>> HTML 입력 사용 — 제한된 태그 사용 가능, 체크하지 않아도 줄바꿈은 보존됩니다.</span></label><button class="rw-button">수정본 저장 후 승인 대기로</button></form></section><?php else: ?><p class="rw-empty">편집할 문서를 찾지 못했습니다.</p><?php endif; ?>
 <?php elseif ($action === 'review' && $is_mod):
   $rs=sql_query("SELECT * FROM `".reflammer_wiki_table('entry')."` WHERE we_status IN ('pending','rejected','deleted') ORDER BY we_updated ASC"); ?>
 <section class="rw-card"><h2>운영진 검토</h2><p class="rw-note">공개된 문서 수정본은 승인 전까지 기존 공개본을 유지합니다.</p><div class="rw-list"><?php $n=0; while($e=sql_fetch_array($rs)){$n++; $labels=array('pending'=>'승인 대기','rejected'=>'재제출 대기','deleted'=>'임시 삭제'); ?><article class="rw-review"><span class="rw-status"><?php echo $labels[$e['we_status']]??''; ?></span><h3><?php echo reflammer_wiki_e($e['we_title']); ?></h3><p><?php echo reflammer_wiki_e($e['we_author_name']); ?><?php if($e['we_author_character'])echo ' ('.reflammer_wiki_e($e['we_author_character']).')'; ?> · 대상 <?php echo reflammer_wiki_e($e['we_subject_character']); ?></p><p><?php echo reflammer_wiki_e(trim($e['we_tags'],'|')); ?></p><div class="rw-content"><?php echo reflammer_wiki_render_content($e['we_content'],!empty($e['we_html'])); ?></div><?php if($e['we_pub_title']!=='') echo '<details><summary>현재 공개본과 비교</summary><h3>'.reflammer_wiki_e($e['we_pub_title']).'</h3><div class="rw-content">'.reflammer_wiki_render_content($e['we_pub_content'],!empty($e['we_pub_html'])).'</div></details>'; ?><?php if($e['we_review_note'])echo '<p class="rw-note">이전 반려 사유: '.reflammer_wiki_e($e['we_review_note']).'</p>'; ?><form method="post"><input type="hidden" name="token" value="<?php echo get_token(); ?>"><input type="hidden" name="we_id" value="<?php echo (int)$e['we_id']; ?>"><?php if($e['we_status']==='deleted'): ?><button name="op" value="restore" class="rw-button">복구</button><?php else: ?><a class="rw-button" href="<?php echo reflammer_wiki_e($base.'?action=review_edit&id='.$e['we_id']); ?>">운영진 수정</a><button name="op" value="approve" class="rw-button">승인·공개</button><input name="note" placeholder="반려 사유"><button name="op" value="reject" class="rw-button rw-muted">반려</button><?php endif; ?></form></article><?php } if(!$n)echo '<p class="rw-empty">검토할 문서가 없습니다.</p>'; ?></div></section>
 <?php elseif ($action === 'mine'):
   $me=sql_real_escape_string($member['mb_id']); $res=sql_query("SELECT * FROM `".reflammer_wiki_table('entry')."` WHERE we_author_id='{$me}' ORDER BY we_updated DESC"); ?>
 <section class="rw-card"><h2>내 문서 · 승인 대기</h2><div class="rw-list rw-mine-list"><?php $n=0; while($e=sql_fetch_array($res)) { $n++; $st=array('pending'=>'승인 대기','published'=>'공개','rejected'=>'수정 후 재제출','deleted'=>'임시 삭제'); $label=($e['we_review_note']!=='' && $e['we_pub_title']!=='')?'수정 후 재제출':($st[$e['we_status']]??'상태'); ?><article><span class="rw-status rw-status-<?php echo reflammer_wiki_e($e['we_status']); ?>"><?php echo reflammer_wiki_e($label); ?></span><strong><?php echo reflammer_wiki_e($e['we_title']); ?></strong><?php if($e['we_review_note']) echo '<small>'.reflammer_wiki_e($e['we_review_note']).'</small>'; ?><div class="rw-mine-actions"><?php if($e['we_status']!=='deleted') echo '<a class="rw-button" href="'.reflammer_wiki_e($base.'?action=edit&id='.$e['we_id']).'">수정</a>'; ?><?php if(in_array($e['we_status'],array('published','pending'),true) && $e['we_pub_title']!=='') echo '<a class="rw-button" href="'.reflammer_wiki_e($base.'?action=view&id='.$e['we_id']).'">공개본 열람</a>'; ?></div></article><?php } if(!$n) echo '<p class="rw-empty">등록한 문서가 없습니다.</p>'; ?></div></section>
 <?php else:
   $selected=(int)($_GET['category']??0); $search_field=$_GET['field']??'title'; $allowed=array('title','content','tag','player','character'); if(!in_array($search_field,$allowed,true)) $search_field='title';
   $q=trim((string)($_GET['q']??'')); $tag=trim((string)($_GET['tag']??'')); $where="e.we_status IN ('published','pending') AND e.we_pub_title<>''";
   if($selected){ $childIds=array($selected); foreach($subs[$selected]??array() as $s)$childIds[]=(int)$s['wc_id']; $where.=' AND e.we_pub_wc_id IN ('.implode(',',array_map('intval',$childIds)).')'; }
   if($tag!=='') $where.=" AND e.we_pub_tags LIKE '%|".sql_real_escape_string($tag)."|%'";
   if($q!==''){ $like="'%".sql_real_escape_string($q)."%'"; $columns=array('title'=>'e.we_pub_title','content'=>'e.we_pub_content','tag'=>'e.we_pub_tags','player'=>'e.we_author_name','character'=>'e.we_author_character'); $where.=' AND '.$columns[$search_field]." LIKE {$like}"; }
   $page=max(1,(int)($_GET['page']??1)); $per=20; $count=sql_fetch("SELECT COUNT(*) AS cnt FROM `".reflammer_wiki_table('entry')."` e WHERE {$where}"); $total=(int)$count['cnt'];
   $items=sql_query("SELECT e.*,c.wc_name FROM `".reflammer_wiki_table('entry')."` e LEFT JOIN `".reflammer_wiki_table('category')."` c ON c.wc_id=e.we_pub_wc_id WHERE {$where} ORDER BY e.we_pub_title ASC LIMIT ".(($page-1)*$per).",{$per}"); ?>
 <div class="rw-layout"><aside class="rw-card rw-categories"><h2>카테고리</h2><a class="rw-all <?php echo !$selected?'is-active':''; ?>" href="<?php echo reflammer_wiki_e($base); ?>"><strong>전체</strong></a><?php foreach($cats as $c): $children=$subs[$c['wc_id']]??array(); $open=($selected===(int)$c['wc_id']); foreach($children as $child){if($selected===(int)$child['wc_id'])$open=true;} $sub_id='rw-category-children-'.(int)$c['wc_id']; ?><div class="rw-category-group"><div class="rw-category-head"><?php if($children): ?><button type="button" class="rw-category-main <?php echo $selected===(int)$c['wc_id']?'is-active':''; ?>" data-submenu="<?php echo $sub_id; ?>" aria-expanded="<?php echo $open?'true':'false'; ?>"><?php echo reflammer_wiki_e($c['wc_name']); ?><span aria-hidden="true">⌄</span></button><?php else: ?><a class="rw-category-main rw-category-label" href="<?php echo reflammer_wiki_e($base.'?category='.$c['wc_id']); ?>"><?php echo reflammer_wiki_e($c['wc_name']); ?></a><?php endif; ?></div><?php if($children): ?><div class="rw-category-children" id="<?php echo $sub_id; ?>" <?php echo $open?'':'hidden'; ?>><?php foreach($children as $s): ?><a class="rw-sub <?php echo $selected===(int)$s['wc_id']?'is-active':''; ?>" href="<?php echo reflammer_wiki_e($base.'?category='.$s['wc_id']); ?>">└ <?php echo reflammer_wiki_e($s['wc_name']); ?></a><?php endforeach; ?></div><?php endif; ?></div><?php endforeach; ?></aside>
 <section class="rw-card rw-results"><h2>정보 열람</h2><form class="rw-search"><input type="hidden" name="category" value="<?php echo $selected; ?>"><select name="field"><option value="title" <?php echo get_selected($search_field,'title'); ?>>제목</option><option value="content" <?php echo get_selected($search_field,'content'); ?>>내용</option><option value="tag" <?php echo get_selected($search_field,'tag'); ?>>태그</option><option value="player" <?php echo get_selected($search_field,'player'); ?>>플레이어명</option><option value="character" <?php echo get_selected($search_field,'character'); ?>>캐릭터명</option></select><input name="q" value="<?php echo reflammer_wiki_e($q); ?>" placeholder="검색어"><button>검색</button></form><?php if($tag) echo '<p>태그 필터: <a href="'.reflammer_wiki_e($base).'">#'.reflammer_wiki_e($tag).' ×</a></p>'; ?><div class="rw-list"><?php $n=0; while($e=sql_fetch_array($items)) { $n++; ?><a class="rw-row" href="<?php echo reflammer_wiki_e($base.'?action=view&id='.$e['we_id']); ?>"><?php echo reflammer_wiki_e($e['we_pub_title']); ?></a><?php } if(!$n) echo '<p class="rw-empty">조건에 맞는 공개 문서가 없습니다.</p>'; ?></div><?php if($total>$per) echo '<nav class="rw-pages">'.get_paging(5,$page,(int)ceil($total/$per),$base.'?category='.$selected.'&amp;field='.rawurlencode($search_field).'&amp;q='.rawurlencode($q).'&amp;page=').'</nav>'; ?></section></div>
 <?php endif; ?>
</main><div class="rw-secondary-nav"><a href="<?php echo reflammer_wiki_e($base.'?action=mine'); ?>">내 문서 · 승인 대기</a></div><script>(function(){document.querySelectorAll('.rw-tag-suggest button').forEach(function(b){b.addEventListener('click',function(){var i=document.querySelector('[name="tags"]'),v=i.value.trim();i.value=v?(v+', '+b.dataset.tag):b.dataset.tag;});});document.querySelectorAll('.rw-category-main[data-submenu]').forEach(function(button){button.addEventListener('click',function(){var panel=document.getElementById(button.dataset.submenu),open=button.getAttribute('aria-expanded')==='true';if(panel){panel.hidden=open;button.setAttribute('aria-expanded',open?'false':'true');}});});document.querySelectorAll('.rw-image-input').forEach(function(input){var preview=input.parentNode.querySelector('.rw-image-preview'),error=input.parentNode.querySelector('.rw-image-error');function update(){try{var u=new URL(input.value);if(u.protocol==='https:'&&u.hostname){error.hidden=true;preview.hidden=false;preview.src=u.href;}else{preview.removeAttribute('src');preview.hidden=true;error.hidden=true;}}catch(e){preview.removeAttribute('src');preview.hidden=true;error.hidden=true;}}preview.onerror=function(){preview.hidden=true;error.hidden=false;};preview.onload=function(){error.hidden=true;};input.addEventListener('input',update);update();});})();</script>
<?php include_once('../../tail.php'); ?>
