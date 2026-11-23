<?php
include_once('./_common.php');
include_once('./_head.php');

$sql_common = " from {$g5['point_table']} where mb_id = '".escape_trim($member['mb_id'])."' ";
$sql_order = " order by po_id desc ";

$sql = " select count(*) as cnt {$sql_common} ";
$total = sql_fetch($sql);
$total = $total['cnt'];

$rows = $config['cf_page_rows'];
$total_page  = ceil($total / $rows);  // 전체 페이지 계산
if ($page < 1) { $page = 1; } // 페이지가 없으면 첫 페이지 (1 페이지)
$from_record = ($page - 1) * $rows; // 시작 열을 구함

$sql = " select *
			{$sql_common}
			{$sql_order}
			limit {$from_record}, {$rows} ";
$result = sql_query($sql);

?>


<form action="./money_update.php" method="post" name="frmItemSend">
	<input type="hidden" name="se_mb_id" value="<?php echo $member['mb_id']?>" />
	<table class="theme-form">
		<colgroup>
			<col style="width: 100px;" />
			<col />
		</colgroup>
		<tbody>
			<tr>
				<th>받는사람</th>
				<td>
					<input type="hidden" name="ch_id" id="ch_id" value="" />
					<input type="text" name="ch_name" value="" id="ch_name" onkeyup="get_ajax_character(this, 'character_list', 'ch_id');" />
					<div id="character_list" class="ajax-list-box theme-box"><div class="list"></div></div>
				</td>
			</tr>
			<tr>
				<th>전달<?php echo $config['cf_money']?></th>
				<td>
					<input type="text" name="po_point" id="po_point" size="10" oninput="var p=document.getElementById('po_point_preview'); if(p) p.textContent=cf_money_format_js(this.value);"/>
					<small id="po_point_preview" style="color:#666;"></small>
				</td>
			</tr>
		</tbody>
	</table>

	<div class="txt-center" style="padding: 20px 0;">
		<button type="submit" class="ui-btn point">전송</button>
	</div>
</form>
<script>
// extend/00_money.lib.php 의 cf_money_format() 과 같은 환산(1갈레온=17시클, 1시클=29넛).
function cf_money_format_js(v) {
	v = parseInt(v, 10) || 0;
	var sign = v < 0 ? '-' : '';
	v = Math.abs(v);
	var g = Math.floor(v / 493);
	var r = v % 493;
	var s = Math.floor(r / 29);
	var k = r % 29;
	var parts = [];
	if (g > 0) parts.push(g + '갈레온');
	if (s > 0) parts.push(s + '시클');
	if (k > 0 || parts.length === 0) parts.push(k + '넛');
	return sign + parts.join(' ');
}
</script>

<hr class="padding" />

<table class="theme-list">
	<colgroup>
		<col />
		<col style="width: 80px;" />
		<col style="width: 80px;" />
	</colgroup>
	<thead>
		<tr>
			<th scope="col">내용</th>
			<th scope="col">획득</th>
			<th scope="col">사용</th>
		</tr>
	</thead>
	<tbody>
		<?php
		$sum_point1 = $sum_point2 = $sum_point3 = 0;
		for ($i=0; $row=sql_fetch_array($result); $i++) {
			$point1 = $point2 = 0;
			if ($row['po_point'] > 0) {
				$point1 = cf_money_format($row['po_point']);
				$sum_point1 += $row['po_point'];
			} else {
				$point2 = cf_money_format($row['po_point'] * -1);
				$sum_point2 += $row['po_point'];
			}

			$po_content = $row['po_content'];

			$expr = '';
		?>
		<tr>
			<td>[<?php echo $row['po_datetime']; ?>] <?php echo $po_content; ?></td>
			<td class="txt-right" ><?php echo $point1; ?></td>
			<td class="txt-right" ><?php echo $point2; ?></td>
		</tr>
		<?php
		}

		if ($i == 0)
			echo '<tr><td colspan="3" class="no-data">자료가 없습니다.</td></tr>';
		?>

		<tr>
			<th scope="row">소계</th>
			<td class="txt-right"><?php echo cf_money_format($sum_point1); ?></td>
			<td class="txt-right"><?php echo cf_money_format($sum_point2 * -1); ?></td>
		</tr>
		<tr>
			<th scope="row">보유 금액</th>
			<td colspan="2"  class="txt-right"><?php echo cf_money_format($member['mb_point']); ?></td>
		</tr>
	</tbody>
</table>

<?php echo get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, $_SERVER['PHP_SELF'].'?'.$qstr.'&amp;page='); ?>

<hr class="padding" />



<?php
include_once('./_tail.php');
?>
