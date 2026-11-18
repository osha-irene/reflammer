<?php
if (!defined('_GNUBOARD_')) exit;
require_once __DIR__.'/_common.php'; $msg=''; $t=reflammer_wiki_table('category'); $et=reflammer_wiki_table('entry');
if($_SERVER['REQUEST_METHOD']==='POST') {
 check_admin_token(); $op=$_POST['op']??''; $id=(int)($_POST['id']??0); $name=trim(strip_tags((string)($_POST['name']??''))); $parent=(int)($_POST['parent']??0); $order=(int)($_POST['order']??0); $target=(int)($_POST['target']??0);
 $parent_row=($parent>0)?sql_fetch("SELECT wc_id FROM `{$t}` WHERE wc_id={$parent} AND wc_parent=0 AND wc_id<>{$id}"):array('wc_id'=>0);
 if($op==='save' && $parent>0 && empty($parent_row['wc_id'])) $msg='상위 분류는 대분류만 선택할 수 있습니다.';
 if($op==='save' && $name!=='' && $msg==='') {
   if($id) {
     $old=sql_fetch("SELECT wc_parent FROM `{$t}` WHERE wc_id={$id}");
     if($old && (int)$old['wc_parent']!==$parent) {
       $children=sql_fetch("SELECT COUNT(*) AS cnt FROM `{$t}` WHERE wc_parent={$id}");
       $dest=sql_fetch("SELECT wc_id FROM `{$t}` WHERE wc_id={$target} AND wc_id<>{$id}");
       if((int)$children['cnt']>0 && $parent!==0) { $msg='하위분류가 있는 대분류는 하위분류를 먼저 옮겨야 합니다.'; }
       elseif(!$dest) { $msg='이동할 때 기존 게시글의 새 카테고리를 반드시 지정하세요.'; }
       else {
         sql_query("UPDATE `{$et}` SET wc_id={$target} WHERE wc_id={$id}",false);
         sql_query("UPDATE `{$et}` SET we_pub_wc_id={$target} WHERE we_pub_wc_id={$id}",false);
         sql_query("UPDATE `{$t}` SET wc_name='".sql_real_escape_string($name)."',wc_parent={$parent},wc_order={$order} WHERE wc_id={$id}",false);
       }
     } else sql_query("UPDATE `{$t}` SET wc_name='".sql_real_escape_string($name)."',wc_parent={$parent},wc_order={$order} WHERE wc_id={$id}",false);
   }
   else sql_query("INSERT INTO `{$t}` (wc_name,wc_parent,wc_order) VALUES ('".sql_real_escape_string($name)."',{$parent},{$order})",false);
   if($msg==='') $msg='카테고리를 저장했습니다.';
 } elseif(($op==='delete'||$op==='move') && $id) {
   $target=(int)($_POST['target']??0); $target_row=sql_fetch("SELECT wc_id FROM `{$t}` WHERE wc_id={$target} AND wc_parent=0 AND wc_id<>{$id}");
   $children=sql_fetch("SELECT COUNT(*) AS cnt FROM `{$t}` WHERE wc_parent={$id}");
   if(!$target_row || (int)$children['cnt']>0) $msg='대상 카테고리를 선택하고, 하위 카테고리가 있으면 먼저 이동하세요.';
   else {
     sql_query("UPDATE `{$et}` SET wc_id={$target} WHERE wc_id={$id}",false);
     sql_query("UPDATE `{$et}` SET we_pub_wc_id={$target} WHERE we_pub_wc_id={$id}",false);
     if($op==='move') sql_query("UPDATE `{$t}` SET wc_parent={$target},wc_order={$order} WHERE wc_id={$id}",false);
     else sql_query("DELETE FROM `{$t}` WHERE wc_id={$id}",false);
     $msg=$op==='move'?'문서의 새 분류를 지정하고 카테고리를 이동했습니다.':'문서를 지정한 분류로 옮긴 뒤 카테고리를 삭제했습니다.';
   }
 }
}
$edit=sql_fetch("SELECT * FROM `{$t}` WHERE wc_id=".(int)($_GET['edit']??0));
?>
<div class="local_desc02"><p>분류는 대분류와 하위분류 두 단계로 관리합니다. 표시 순서는 숫자가 작은 항목부터이며, 게시글은 제목 가나다순입니다. 카테고리 이동 시 기존 게시글의 새 분류를 지정해야 합니다.</p><?php if($msg)echo '<p><strong>'.reflammer_wiki_e($msg).'</strong></p>'; ?></div>
<form method="post" class="tbl_frm01 tbl_wrap"><input type="hidden" name="token" value="<?php echo get_admin_token(); ?>"><input type="hidden" name="op" value="save"><input type="hidden" name="id" value="<?php echo (int)($edit['wc_id']??0); ?>"><table><tr><th>이름</th><td><input name="name" required value="<?php echo reflammer_wiki_e($edit['wc_name']??''); ?>"></td></tr><tr><th>상위 분류</th><td><select name="parent"><option value="0">대분류</option><?php reflammer_wiki_root_options((int)($edit['wc_parent']??0),(int)($edit['wc_id']??0)); ?></select></td></tr><tr><th>이동할 게시글의 새 분류</th><td><select name="target"><option value="">분류 이동 시 지정 필수</option><?php reflammer_wiki_cat_options(); ?></select></td></tr><tr><th>표시 순서</th><td><input type="number" name="order" value="<?php echo (int)($edit['wc_order']??10); ?>"></td></tr></table><button class="btn_submit">저장</button></form>
<style>
.rw-admin-actions{display:flex;align-items:center;justify-content:flex-end;gap:7px;flex-wrap:wrap}
.rw-admin-action{display:inline-block;padding:8px 13px;border:0;border-radius:5px;background:#626a73;color:#fff!important;text-decoration:none!important;line-height:1.35;cursor:pointer}
.rw-admin-action:hover{background:#4b525a;color:#fff!important}
.rw-admin-actions form{display:flex;align-items:center;gap:7px;margin:0}
.rw-admin-actions select{min-height:34px;border:0;border-radius:5px;background:#e9eaec;padding:6px 9px}
.rw-category-table{width:100%;min-width:920px;table-layout:fixed}
.rw-category-table th:nth-child(2),.rw-category-table td:nth-child(2){width:100px;white-space:nowrap}
.rw-category-table th:nth-child(3),.rw-category-table td:nth-child(3){width:76px;white-space:nowrap}
.rw-category-table th:nth-child(4),.rw-category-table td:nth-child(4){width:610px;white-space:nowrap}
.rw-category-table th:first-child,.rw-category-table td:first-child{width:34%}
.rw-category-table .rw-admin-actions,.rw-category-table .rw-admin-actions form{flex-wrap:nowrap;white-space:nowrap}
.rw-category-table .rw-admin-actions>span{white-space:nowrap}
.rw-category-list{overflow-x:auto}
</style>
<div class="tbl_head01 tbl_wrap rw-category-list"><table class="rw-category-table"><colgroup><col><col style="width:100px"><col style="width:76px"><col style="width:610px"></colgroup><thead><tr><th>분류</th><th>단계</th><th>순서</th><th>관리</th></tr></thead><tbody>
<?php foreach(reflammer_wiki_categories() as $c): $child_cats=reflammer_wiki_categories((int)$c['wc_id']); ?>
<tr><td><strong><?php echo reflammer_wiki_e($c['wc_name']); ?></strong></td><td>대분류</td><td><?php echo (int)$c['wc_order']; ?></td><td><div class="rw-admin-actions"><a class="rw-admin-action" href="?m=reflammer_wiki&amp;p=categories&amp;edit=<?php echo (int)$c['wc_id']; ?>">수정 / 이동</a><?php if(!$child_cats): ?><form method="post"><input type="hidden" name="token" value="<?php echo get_admin_token(); ?>"><input type="hidden" name="id" value="<?php echo (int)$c['wc_id']; ?>"><select name="target" required><option value="">게시글 이동 대상</option><?php reflammer_wiki_root_options(0,(int)$c['wc_id']); ?></select><button class="rw-admin-action" name="op" value="delete" onclick="return confirm('글을 선택한 분류로 옮기고 이 대분류를 삭제할까요?')">삭제</button></form><?php else: ?><span>하위분류를 먼저 이동 또는 삭제하세요.</span><?php endif; ?></div></td></tr>
<?php foreach($child_cats as $s): ?><tr><td>└ <?php echo reflammer_wiki_e($s['wc_name']); ?></td><td>하위분류</td><td><?php echo (int)$s['wc_order']; ?></td><td><div class="rw-admin-actions"><a class="rw-admin-action" href="?m=reflammer_wiki&amp;p=categories&amp;edit=<?php echo (int)$s['wc_id']; ?>">수정 / 이동</a><form method="post"><input type="hidden" name="token" value="<?php echo get_admin_token(); ?>"><input type="hidden" name="id" value="<?php echo (int)$s['wc_id']; ?>"><select name="target" required><option value="">게시글 이동 대상</option><?php reflammer_wiki_root_options(0,(int)$s['wc_id']); ?></select><button class="rw-admin-action" name="op" value="move">분류 조정</button><button class="rw-admin-action" name="op" value="delete" onclick="return confirm('글을 선택한 대상 분류로 옮기고 이 하위분류를 삭제할까요?')">삭제</button></form></div></td></tr><?php endforeach; endforeach; ?>
</tbody></table></div>
