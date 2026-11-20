<?php
if (!defined('_GNUBOARD_')) exit;

$sub_menu = function_exists('mdl_admin_menu_code') ? mdl_admin_menu_code('bbs_trait', '300') : '001300';
global $auth, $g5, $member;
if (isset($auth[$sub_menu])) auth_check($auth[$sub_menu], $_SERVER['REQUEST_METHOD'] === 'POST' ? 'w' : 'r');
add_stylesheet('<link rel="stylesheet" href="'.G5_MDL_URL.'/bbs_trait/css/admin.css?v=1.2.1">', 100);

$tables = bbs_trait_tables();
$message = '';
$slot_count = bbs_trait_max_slots();
$base_slot_count = bbs_trait_base_slots();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_demo();
    check_token();
    $mode = isset($_POST['mode']) && is_scalar($_POST['mode']) ? (string)$_POST['mode'] : '';
    if ($mode === 'grant') {
        $ch_id = isset($_POST['ch_id']) && is_scalar($_POST['ch_id']) ? (int)$_POST['ch_id'] : 0;
        $trait_id = isset($_POST['trait_id']) && is_scalar($_POST['trait_id']) ? (int)$_POST['trait_id'] : 0;
        $replace_id = isset($_POST['replace_trait_id']) && is_scalar($_POST['replace_trait_id']) ? (int)$_POST['replace_trait_id'] : 0;
        $character = sql_fetch("SELECT ch_id FROM `{$g5['character_table']}` WHERE ch_id='{$ch_id}' AND ch_state='승인' AND ch_type IN ('main','sub') LIMIT 1", false);
        $trait = bbs_trait_get($trait_id);
        if (empty($character['ch_id']) || !$trait) {
            $message = '승인된 캐릭터와 사용 중인 특성을 선택해 주세요.';
        } else {
            $existing = sql_fetch("SELECT trait_id FROM `{$tables['owned']}` WHERE ch_id='{$ch_id}' AND trait_id='{$trait_id}' LIMIT 1", false);
            $owned_count = bbs_trait_owned_count($ch_id);
            $open_slots = bbs_trait_open_slots($ch_id);
            if ($owned_count < 0 || $open_slots < 0) {
                $message = '현재 보유 현황을 확인하지 못해 특성을 지급하지 않았습니다.';
            } elseif (!empty($existing['trait_id'])) {
                $message = '해당 캐릭터가 이미 보유한 특성입니다.';
            } elseif ($owned_count >= $open_slots) {
                if ($replace_id < 1) {
                    $message = $open_slots < 1
                        ? '개방된 슬롯이 없습니다. 슬롯을 확장하거나 기존 특성과 교체해 주세요.'
                        : '보유 슬롯이 가득 찼습니다. 교체할 특성을 선택해 주세요.';
                } else {
                    $replace = sql_fetch("SELECT trait_id FROM `{$tables['owned']}` WHERE ch_id='{$ch_id}' AND trait_id='{$replace_id}' LIMIT 1", false);
                    if (empty($replace['trait_id']) || $replace_id === $trait_id) {
                        $message = '교체할 특성의 보유 상태를 확인해 주세요.';
                    } elseif (sql_query('START TRANSACTION', false)) {
                        $locked = sql_fetch("SELECT trait_id FROM `{$tables['owned']}` WHERE ch_id='{$ch_id}' AND trait_id='{$replace_id}' FOR UPDATE", false);
                        if (empty($locked['trait_id'])) {
                            sql_query('ROLLBACK', false);
                            $message = '보유 상태가 변경되어 특성을 교체하지 못했습니다.';
                        } else {
                            $deleted = sql_query("DELETE FROM `{$tables['owned']}` WHERE ch_id='{$ch_id}' AND trait_id='{$replace_id}' LIMIT 1", false);
                            $inserted = $deleted && sql_query("INSERT INTO `{$tables['owned']}` (ch_id, trait_id, granted_by, granted_at)
                                VALUES ('{$ch_id}', '{$trait_id}', '".bbs_trait_escape(isset($member['mb_id']) ? $member['mb_id'] : '')."', '".G5_TIME_YMDHIS."')", false);
                            if ($inserted && sql_query('COMMIT', false)) {
                                $message = '기존 특성을 새 특성으로 교체했습니다. 과거 사용 기록은 보존됩니다.';
                            } else {
                                sql_query('ROLLBACK', false);
                                $message = '특성을 교체하지 못했습니다.';
                            }
                        }
                    } else {
                        $message = '특성 교체를 시작하지 못했습니다.';
                    }
                }
            } else {
                $granted = sql_query("INSERT INTO `{$tables['owned']}` (ch_id, trait_id, granted_by, granted_at)
                    VALUES ('{$ch_id}', '{$trait_id}', '".bbs_trait_escape(isset($member['mb_id']) ? $member['mb_id'] : '')."', '".G5_TIME_YMDHIS."')", false);
                $message = $granted ? '특성을 지급했습니다.' : '특성을 지급하지 못했습니다.';
            }
        }
    } elseif ($mode === 'revoke') {
        $selected = isset($_POST['selected']) && is_array($_POST['selected']) ? $_POST['selected'] : array();
        $pairs = array();
        foreach ($selected as $raw) {
            if (!is_scalar($raw) || !preg_match('/^([1-9][0-9]*):([1-9][0-9]*)$/', (string)$raw, $match)) continue;
            $pairs[(int)$match[1].':'.(int)$match[2]] = array((int)$match[1], (int)$match[2]);
        }
        $removed = 0;
        foreach ($pairs as $pair) {
            if (sql_query("DELETE FROM `{$tables['owned']}` WHERE ch_id='{$pair[0]}' AND trait_id='{$pair[1]}' LIMIT 1", false)) $removed++;
        }
        $message = $removed
            ? $removed.'개 특성을 회수했습니다. 사용 기록은 보존됩니다.'
            : '회수할 특성을 선택해 주세요.';
    }
}

$characters = array();
$rs = sql_query("SELECT ch_id, ch_name, ch_type FROM `{$g5['character_table']}`
    WHERE ch_state='승인' AND ch_type IN ('main','sub') ORDER BY ch_name ASC, ch_id ASC", false);
while ($rs && $row = sql_fetch_array($rs)) {
    $characters[] = array(
        'id' => (int)$row['ch_id'],
        'name' => (string)$row['ch_name'],
        'type' => $row['ch_type'] === 'sub' ? '서브' : '메인',
        'count' => bbs_trait_owned_count((int)$row['ch_id']),
        'slots' => bbs_trait_open_slots((int)$row['ch_id']),
    );
}
$traits = array();
$rs = sql_query("SELECT trait_id, trait_name, trait_icon, trait_description, effect_type, effect_value, effect_stat_id
    FROM `{$tables['catalog']}` WHERE trait_enabled=1 ORDER BY trait_name ASC, trait_id ASC", false);
while ($rs && $row = sql_fetch_array($rs)) $traits[] = $row;

$query = isset($_GET['q']) && is_scalar($_GET['q']) ? trim((string)$_GET['q']) : '';
$where = '';
if ($query !== '') {
    $q = bbs_trait_escape($query);
    $where = " AND (c.ch_name LIKE '%{$q}%' OR t.trait_name LIKE '%{$q}%')";
}
$owned_result = sql_query("SELECT o.ch_id, o.trait_id, o.granted_at, c.ch_name, c.ch_type,
        t.trait_name, t.trait_icon, t.trait_description, t.effect_type, t.effect_value, t.effect_stat_id, t.trait_enabled
    FROM `{$tables['owned']}` AS o
    INNER JOIN `{$g5['character_table']}` AS c ON c.ch_id=o.ch_id
    INNER JOIN `{$tables['catalog']}` AS t ON t.trait_id=o.trait_id
    WHERE 1=1{$where}
    ORDER BY c.ch_name ASC, t.trait_name ASC, o.granted_at ASC", false);
$replace_result = sql_query("SELECT o.ch_id, o.trait_id, t.trait_name, t.trait_icon, t.trait_description, t.effect_type, t.effect_value, t.effect_stat_id
    FROM `{$tables['owned']}` AS o
    INNER JOIN `{$tables['catalog']}` AS t ON t.trait_id=o.trait_id
    ORDER BY o.ch_id ASC, t.trait_name ASC", false);
?>
<div class="local_desc01 local_desc">
    <p>특성은 승인된 메인·서브 캐릭터 단위로 지급합니다. 슬롯이 가득 차면 기존 특성을 선택하고, 효과 비교 후 확인해야 교체됩니다.</p>
    <p>캐릭터당 기본 개방 슬롯은 <?php echo $base_slot_count; ?>개이며, 최대 <?php echo $slot_count; ?>개까지 사용할 수 있습니다. 상점에서 특성 슬롯 확장 아이템을 구매해 사용하면 슬롯이 1개 늘어납니다. 개방 슬롯을 줄여 초과 보유 중인 캐릭터는 기존 특성을 유지합니다.</p>
</div>
<?php if ($message !== '') { ?><div class="local_desc01 local_desc"><p><?php echo bbs_trait_html($message); ?></p></div><?php } ?>
<form method="post" id="bbs-trait-grant-form" class="bbs-trait-admin-form bbs-trait-grant-form">
    <input type="hidden" name="token" value="<?php echo bbs_trait_html(get_token()); ?>">
    <input type="hidden" name="mode" value="grant">
    <input type="hidden" name="replace_trait_id" id="replace_trait_id" value="0">
    <h2>특성 지급</h2>
    <div class="bbs-trait-grant-field">
        <label id="grant_character_label" for="grant_character_search">캐릭터</label>
        <div class="bbs-trait-grant-controls">
            <input class="frm_input" type="search" id="grant_character_search" placeholder="캐릭터명 검색" aria-label="캐릭터명 검색" aria-controls="grant_ch_id" autocomplete="off">
            <select class="frm_input" name="ch_id" id="grant_ch_id" aria-labelledby="grant_character_label" required>
                <option value="">캐릭터 선택</option>
                <?php foreach ($characters as $character) { ?>
                    <option value="<?php echo (int)$character['id']; ?>" data-character-name="<?php echo bbs_trait_html($character['name']); ?>" data-owned-count="<?php echo (int)$character['count']; ?>" data-slot-limit="<?php echo max(0, (int)$character['slots']); ?>"><?php echo bbs_trait_html($character['name']); ?> (<?php echo bbs_trait_html($character['type']); ?> · 보유 <?php echo (int)$character['count']; ?>/<?php echo $character['slots'] < 0 ? '?' : (int)$character['slots']; ?>, 최대 <?php echo $slot_count; ?>)</option>
                <?php } ?>
            </select>
        </div>
    </div>
    <div class="bbs-trait-grant-field bbs-trait-grant-trait-field">
        <label id="grant_trait_label" for="grant_trait_search">지급할 특성</label>
        <div class="bbs-trait-grant-controls">
            <input class="frm_input" type="search" id="grant_trait_search" placeholder="특성명·효과·설명 검색" aria-label="지급할 특성 검색" aria-controls="grant_trait_id" autocomplete="off">
            <select class="frm_input" name="trait_id" id="grant_trait_id" aria-labelledby="grant_trait_label" required>
                <option value="">특성 선택</option>
                <?php foreach ($traits as $trait) { ?>
                    <option value="<?php echo (int)$trait['trait_id']; ?>"
                        data-trait-name="<?php echo bbs_trait_html($trait['trait_name']); ?>"
                        data-trait-effect="<?php echo bbs_trait_html(bbs_trait_effect_text($trait['effect_type'], $trait['effect_value'], bbs_trait_effect_scope_label($trait['effect_stat_id']))); ?>"
                        data-trait-description="<?php echo bbs_trait_html($trait['trait_description']); ?>"
                        data-trait-icon="<?php echo bbs_trait_html(bbs_trait_icon_url((string)$trait['trait_icon'])); ?>"><?php echo bbs_trait_html($trait['trait_name']); ?> — <?php echo bbs_trait_html(bbs_trait_effect_text($trait['effect_type'], $trait['effect_value'], bbs_trait_effect_scope_label($trait['effect_stat_id']))); ?></option>
                <?php } ?>
            </select>
        </div>
    </div>
    <button type="submit" class="btn_submit">지급</button>
</form>
<form method="get" class="local_sch01 local_sch bbs-trait-ownership-search">
    <input type="hidden" name="m" value="bbs_trait"><input type="hidden" name="p" value="ownership">
    <label for="trait_search">캐릭터·특성 검색</label>
    <input class="frm_input" type="text" name="q" id="trait_search" value="<?php echo bbs_trait_html($query); ?>">
    <button type="submit" class="btn_submit">검색</button>
</form>
<form method="post" id="bbs-trait-revoke-form">
    <input type="hidden" name="token" value="<?php echo bbs_trait_html(get_token()); ?>">
    <input type="hidden" name="mode" value="revoke">
    <div class="tbl_head01 tbl_wrap">
        <table>
            <caption>현재 특성 보유 현황</caption>
            <thead><tr><th><input type="checkbox" id="trait_select_all" aria-label="전체 선택"></th><th>캐릭터</th><th>특성</th><th>효과</th><th>지급일</th><th>상태</th></tr></thead>
            <tbody>
            <?php $owned_count = 0; while ($owned_result && $row = sql_fetch_array($owned_result)) { $owned_count++; ?>
                <tr class="bbs-trait-owned-display-row">
                    <td><input type="checkbox" name="selected[]" value="<?php echo (int)$row['ch_id'].':'.(int)$row['trait_id']; ?>" aria-label="선택"></td>
                    <td><?php echo bbs_trait_html($row['ch_name']); ?> (<?php echo $row['ch_type'] === 'sub' ? '서브' : '메인'; ?>)</td>
                    <td><?php $icon_url = bbs_trait_icon_url((string)$row['trait_icon']); if ($icon_url !== '') { ?><img class="bbs-trait-admin-icon" src="<?php echo bbs_trait_html($icon_url); ?>" alt=""><?php } ?> <?php echo bbs_trait_html($row['trait_name']); ?></td>
                    <td><?php echo bbs_trait_html(bbs_trait_effect_text($row['effect_type'], $row['effect_value'], bbs_trait_effect_scope_label($row['effect_stat_id']))); ?><br><?php echo nl2br(bbs_trait_html($row['trait_description'])); ?></td>
                    <td><?php echo bbs_trait_html($row['granted_at']); ?></td>
                    <td><?php echo !empty($row['trait_enabled']) ? '사용 중' : '특성 비활성'; ?></td>
                </tr>
            <?php } if (!$owned_count) { ?><tr><td colspan="6" class="empty_table">보유 중인 특성이 없습니다.</td></tr><?php } ?>
            </tbody>
        </table>
    </div>
    <button type="submit" class="btn btn_02" onclick="return confirm('선택한 특성을 회수할까요? 사용 기록은 보존됩니다.');">선택 특성 회수</button>
</form>
<div hidden aria-hidden="true">
    <?php while ($replace_result && $row = sql_fetch_array($replace_result)) { ?>
        <span class="bbs-trait-owned-row" data-character-id="<?php echo (int)$row['ch_id']; ?>" data-trait-id="<?php echo (int)$row['trait_id']; ?>" data-trait-name="<?php echo bbs_trait_html($row['trait_name']); ?>" data-trait-effect="<?php echo bbs_trait_html(bbs_trait_effect_text($row['effect_type'], $row['effect_value'], bbs_trait_effect_scope_label($row['effect_stat_id']))); ?>" data-trait-description="<?php echo bbs_trait_html($row['trait_description']); ?>" data-trait-icon="<?php echo bbs_trait_html(bbs_trait_icon_url((string)$row['trait_icon'])); ?>"></span>
    <?php } ?>
</div>
<div class="bbs-trait-modal" id="bbs-trait-replace-modal" hidden>
    <div class="bbs-trait-modal-box" role="dialog" aria-modal="true" aria-labelledby="bbs-trait-replace-title">
        <h2 id="bbs-trait-replace-title">교체할 기존 특성 선택</h2>
        <p>보유 슬롯이 가득 찼습니다. 교체할 특성을 선택하세요.</p>
        <div id="bbs-trait-replace-options"></div>
        <button type="button" class="btn" id="bbs-trait-replace-cancel">취소</button>
        <button type="button" class="btn_submit" id="bbs-trait-replace-next" disabled>효과 비교</button>
    </div>
</div>
<div class="bbs-trait-modal" id="bbs-trait-confirm-modal" hidden>
    <div class="bbs-trait-modal-box" role="dialog" aria-modal="true" aria-labelledby="bbs-trait-confirm-title">
        <h2 id="bbs-trait-confirm-title">특성 교체 확인</h2>
        <p>아래 특성을 교체할까요? 교체 후 기존 특성은 회수되며, 과거 사용 기록은 유지됩니다.</p>
        <div class="bbs-trait-compare">
            <section><h3>기존 특성</h3><div id="bbs-trait-old-preview"></div></section>
            <section><h3>새 특성</h3><div id="bbs-trait-new-preview"></div></section>
        </div>
        <button type="button" class="btn" id="bbs-trait-confirm-cancel">취소</button>
        <button type="button" class="btn_submit" id="bbs-trait-confirm-submit">교체 확정</button>
    </div>
</div>
<script>(function(){
var form=document.getElementById('bbs-trait-grant-form');
var character=document.getElementById('grant_ch_id');
var trait=document.getElementById('grant_trait_id');
var characterSearch=document.getElementById('grant_character_search');
var traitSearch=document.getElementById('grant_trait_search');
var replaceInput=document.getElementById('replace_trait_id');
var replaceModal=document.getElementById('bbs-trait-replace-modal');
var confirmModal=document.getElementById('bbs-trait-confirm-modal');
var options=document.getElementById('bbs-trait-replace-options');
var next=document.getElementById('bbs-trait-replace-next');
var selectedRow=null;
var allowSubmit=false;
function show(el){el.hidden=false;}
function hide(el){el.hidden=true;}
function preview(name,effect,desc,icon){
    var box=document.createElement('div');
    if(icon){var image=document.createElement('img');image.src=icon;image.alt='';box.appendChild(image);}
    var title=document.createElement('strong');title.textContent=name||'';box.appendChild(title);
    var effectText=document.createElement('p');effectText.textContent=effect||'';box.appendChild(effectText);
    var description=document.createElement('p');description.textContent=desc||'';description.style.whiteSpace='pre-wrap';box.appendChild(description);
    return box;
}
function rowPreview(row){return preview(row.getAttribute('data-trait-name'),row.getAttribute('data-trait-effect'),row.getAttribute('data-trait-description'),row.getAttribute('data-trait-icon'));}
function selectedOption(){return trait.options[trait.selectedIndex];}
function setupOptionSearch(input,select,getSearchText,noResultsText){
    if(!input||!select||!select.options.length)return;
    var originalOptions=Array.prototype.map.call(select.options,function(option){return option.cloneNode(true);});
    input.addEventListener('input',function(){
        var query=input.value.trim().toLocaleLowerCase();
        var selectedValue=select.value;
        var placeholder=originalOptions[0].cloneNode(true);
        var matched=0;
        var selectedVisible=false;
        while(select.firstChild)select.removeChild(select.firstChild);
        select.appendChild(placeholder);
        originalOptions.slice(1).forEach(function(option){
            var searchable=getSearchText(option).toLocaleLowerCase();
            if(query&&searchable.indexOf(query)===-1)return;
            var copy=option.cloneNode(true);
            select.appendChild(copy);
            matched++;
            if(copy.value===selectedValue)selectedVisible=true;
        });
        if(query&&!matched)placeholder.textContent=noResultsText;
        if(selectedValue&&selectedVisible)select.value=selectedValue;
        else select.value='';
    });
}
setupOptionSearch(characterSearch,character,function(option){
    return option.getAttribute('data-character-name')||option.textContent;
},'검색 결과 없음');
setupOptionSearch(traitSearch,trait,function(option){
    return [option.textContent,option.getAttribute('data-trait-name'),option.getAttribute('data-trait-effect'),option.getAttribute('data-trait-description')].join(' ');
},'검색 결과 없음');
form.addEventListener('submit',function(event){
    if(allowSubmit)return;
    var ch=character.value;
    var newId=trait.value;
    if(!ch||!newId)return;
    var rows=Array.prototype.filter.call(document.querySelectorAll('.bbs-trait-owned-row'),function(row){return row.getAttribute('data-character-id')===ch;});
    if(rows.some(function(row){return row.getAttribute('data-trait-id')===newId;})){event.preventDefault();alert('해당 캐릭터가 이미 보유한 특성입니다.');return;}
    var selectedCharacter=character.options[character.selectedIndex];
    var count=Number(selectedCharacter.getAttribute('data-owned-count')||0);
    var slotLimit=Number(selectedCharacter.getAttribute('data-slot-limit')||0);
    if(count < slotLimit)return;
    event.preventDefault();
    if(!rows.length){alert('보유 슬롯이 없어 특성을 지급할 수 없습니다.');return;}
    selectedRow=null;next.disabled=true;options.innerHTML='';
    rows.forEach(function(row,index){
        var label=document.createElement('label');label.className='bbs-trait-replace-option';
        var radio=document.createElement('input');radio.type='radio';radio.name='replace-choice';radio.value=row.getAttribute('data-trait-id');
        radio.addEventListener('change',function(){selectedRow=row;next.disabled=false;});
        var card=document.createElement('span');card.appendChild(rowPreview(row));
        label.appendChild(radio);label.appendChild(card);options.appendChild(label);
    });
    show(replaceModal);
});
document.getElementById('bbs-trait-replace-cancel').addEventListener('click',function(){hide(replaceModal);});
next.addEventListener('click',function(){
    if(!selectedRow)return;
    var newOption=selectedOption();
    var oldPreview=document.getElementById('bbs-trait-old-preview');
    var newPreview=document.getElementById('bbs-trait-new-preview');
    oldPreview.innerHTML='';newPreview.innerHTML='';
    oldPreview.appendChild(rowPreview(selectedRow));
    newPreview.appendChild(preview(newOption.getAttribute('data-trait-name'),newOption.getAttribute('data-trait-effect'),newOption.getAttribute('data-trait-description'),newOption.getAttribute('data-trait-icon')));
    hide(replaceModal);show(confirmModal);
});
document.getElementById('bbs-trait-confirm-cancel').addEventListener('click',function(){hide(confirmModal);});
document.getElementById('bbs-trait-confirm-submit').addEventListener('click',function(){
    if(!selectedRow)return;
    replaceInput.value=selectedRow.getAttribute('data-trait-id');
    allowSubmit=true;
    form.submit();
});
var all=document.getElementById('trait_select_all');
if(all)all.addEventListener('change',function(){document.querySelectorAll('#bbs-trait-revoke-form input[name="selected[]"]').forEach(function(box){box.checked=all.checked;});});
})();</script>
