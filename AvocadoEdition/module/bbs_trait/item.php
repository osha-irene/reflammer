<?php
if (!defined('_GNUBOARD_')) exit;

function bbs_trait_acquisition_item_type() {
    return '특성 습득권';
}

function bbs_trait_admin_item_form_fields($item, $w, $item_type_options) {
    $item = is_array($item) ? $item : array();
    $selected_id = isset($item['it_value']) ? (int)$item['it_value'] : 0;
    $tables = bbs_trait_tables();
    $result = sql_query("SELECT trait_id, trait_name FROM `{$tables['catalog']}`
        WHERE trait_enabled=1 ORDER BY trait_name ASC, trait_id ASC", false);
    if (!$result) error_log('bbs_trait: failed to load active traits for the item form.');

    $is_acquisition_item = isset($item['it_type']) && (string)$item['it_type'] === bbs_trait_acquisition_item_type();
    echo '<tr id="bbs-trait-item-admin-field" style="display:'.($is_acquisition_item ? 'table-row' : 'none').'"><th scope="row">습득할 특성</th><td colspan="2">';
    echo '<select name="bbs_trait_target_id" id="bbs_trait_target_id"><option value="">특성 선택</option>';
    while ($result && $trait = sql_fetch_array($result)) {
        $trait_id = (int)$trait['trait_id'];
        echo '<option value="'.$trait_id.'"'.($selected_id === $trait_id ? ' selected' : '').'>'
            .bbs_trait_html($trait['trait_name']).'</option>';
    }
    echo '</select> <span>특성 습득권은 인벤토리에서 한 번 사용하면 이 특성을 획득합니다. 연결값은 저장 시 자동 설정됩니다.</span>';
    if (!$result) echo '<p class="bbs-trait-item-admin-error">특성 목록을 불러오지 못했습니다. 저장 전에 데이터베이스를 확인해 주세요.</p>';
    echo '</td></tr>';
    echo '<script>(function(){'
        .'var type=document.querySelector("select[name=\\"it_type\\"]");'
        .'var value=document.getElementById("it_value");'
        .'var row=document.getElementById("bbs-trait-item-admin-field");'
        .'var target=document.getElementById("bbs_trait_target_id");'
        .'if(!type||!row||!target)return;'
        .'var valueRow=value&&value.closest?value.closest("tr"):null;'
        .'function update(){var active=type.value==='.json_encode(bbs_trait_acquisition_item_type()).';'
        .'row.style.display=active?"table-row":"none";target.required=active;'
        .'if(valueRow)valueRow.style.display=active?"none":"";'
        .'if(active){var use=document.getElementById("it_use");var usable=document.getElementById("it_use_able");'
        .'var ever=document.getElementById("it_use_ever");var mmb=document.getElementById("it_use_mmb_able");var recipe=document.getElementById("it_use_recepi");'
        .'if(use)use.checked=true;if(usable)usable.checked=true;if(ever)ever.checked=false;if(mmb)mmb.checked=false;if(recipe)recipe.checked=false;}}'
        .'type.addEventListener("change",update);update();'
        .'})();</script>';
}

function bbs_trait_admin_item_save_data($data, $w) {
    if (!is_array($data) || !isset($data['it_type']) || (string)$data['it_type'] !== bbs_trait_acquisition_item_type()) {
        return $data;
    }
    $raw_id = isset($_POST['bbs_trait_target_id']) && is_scalar($_POST['bbs_trait_target_id'])
        ? (string)$_POST['bbs_trait_target_id'] : '';
    if (!preg_match('/^[1-9][0-9]*$/', $raw_id)) {
        alert('특성 습득권에 연결할 특성을 선택해 주세요.');
    }
    $trait_id = (int)$raw_id;
    $trait = bbs_trait_get($trait_id);
    if (!$trait) {
        alert('사용 중인 특성만 특성 습득권에 연결할 수 있습니다.');
    }

    $data['it_value'] = (string)$trait_id;
    $data['it_use'] = 'Y';
    $data['it_use_able'] = '1';
    $data['it_use_ever'] = '0';
    $data['it_use_mmb_able'] = '0';
    $data['it_use_recepi'] = '0';
    $data['st_id'] = '';
    return $data;
}

function bbs_trait_item_return_url($url) {
    $url = trim((string)$url);
    if ($url === '' || strlen($url) > 2048 || $url[0] !== '/'
        || substr($url, 0, 2) === '//' || strpos($url, '\\') !== false
        || preg_match('/[\x00-\x1f\x7f]/', $url)) {
        return G5_URL;
    }
    return $url;
}

function bbs_trait_item_use_response($result, $return_url, $message) {
    $separator = strpos($return_url, '?') === false ? '?' : '&';
    $result['handled'] = true;
    $result['echo'] = 'LOCATIONURL||||'.$return_url.$separator.'tabs=i&msg='.rawurlencode((string)$message);
    return $result;
}

function bbs_trait_item_duplicate_notice_response($result) {
    $result['handled'] = true;
    $result['echo'] = '<link rel="stylesheet" href="'.bbs_trait_html(G5_MDL_URL.'/bbs_trait/css/item-use.css?v=1.5.1').'">'
        .'<div class="bbs-trait-item-use bbs-trait-item-notice" data-bbs-trait-item-duplicate-notice="1">'
        .'<h3>특성 습득 불가</h3>'
        .'<p>이미 보유한 특성입니다. 중복으로 획득할 수 없으며, 특성 습득권은 사용되지 않았습니다.</p>'
        .'<button type="button" class="btn bbs-trait-item-notice-close">확인</button>'
        .'</div>'
        .'<script>(function(){var notice=document.querySelector("[data-bbs-trait-item-duplicate-notice=\\"1\\"]");'
        .'if(!notice)return;var popup=notice.closest(".inven-popup-viewer");'
        .'if(popup)popup.classList.add("bbs-trait-item-popup");'
        .'var button=notice.querySelector(".bbs-trait-item-notice-close");'
        .'if(button)button.addEventListener("click",function(){var close=document.querySelector(".btn-inven-popup-close");'
        .'if(close)close.click();else notice.remove();});})();</script>';
    return $result;
}

function bbs_trait_item_use_authorized_character($ch_id) {
    global $g5, $member, $is_admin;
    if (empty($member['mb_id']) || empty($g5['character_table'])) return false;
    $ch_id = (int)$ch_id;
    $character = sql_fetch("SELECT ch_id, ch_name, mb_id, ch_state, ch_type
        FROM `{$g5['character_table']}` WHERE ch_id='{$ch_id}' LIMIT 1", false);
    if (empty($character['ch_id'])) {
        error_log('bbs_trait: failed to find the character for a trait acquisition item.');
        return false;
    }
    if ($character['ch_state'] !== '승인' || !in_array($character['ch_type'], array('main', 'sub'), true)) return false;
    if ((string)$character['mb_id'] !== (string)$member['mb_id'] && empty($is_admin)) return false;
    return $character;
}

function bbs_trait_item_load_inventory($in_id, $ch_id, $for_update = false) {
    global $g5;
    if (empty($g5['inventory_table']) || empty($g5['item_table'])) return false;
    $in_id = (int)$in_id;
    $ch_id = (int)$ch_id;
    $owner_filter = $ch_id > 0 ? " AND i.ch_id='{$ch_id}'" : '';
    $lock = $for_update ? ' FOR UPDATE' : '';
    return sql_fetch("SELECT i.*,
            t.it_type, t.it_value AS trait_value, t.it_use, t.it_use_able, t.it_use_ever
        FROM `{$g5['inventory_table']}` AS i
        INNER JOIN `{$g5['item_table']}` AS t ON t.it_id=i.it_id
        WHERE i.in_id='{$in_id}'{$owner_filter}
        LIMIT 1{$lock}", false);
}

function bbs_trait_item_release_lock($lock_name) {
    $lock_name = bbs_trait_escape((string)$lock_name);
    sql_fetch("SELECT RELEASE_LOCK('{$lock_name}')", false);
}

function bbs_trait_item_abort_transaction($lock_name, $message, $code = '') {
    if (!sql_query('ROLLBACK', false)) error_log('bbs_trait: failed to roll back trait item acquisition.');
    bbs_trait_item_release_lock($lock_name);
    return array(false, $message, $code);
}

function bbs_trait_item_get_receipt($in_id) {
    $tables = bbs_trait_tables();
    $in_id = (int)$in_id;
    $result = sql_query("SELECT in_id, ch_id, trait_id, replaced_trait_id
        FROM `{$tables['item_use']}` WHERE in_id='{$in_id}' LIMIT 1", false);
    if (!$result) return false;
    $row = sql_fetch_array($result);
    return !empty($row['in_id']) ? $row : null;
}

function bbs_trait_item_remove_used_copy($in_id) {
    global $g5;
    $item = bbs_trait_item_load_inventory($in_id, 0);
    if (empty($item['in_id']) || (string)$item['it_type'] !== bbs_trait_acquisition_item_type()) return 0;
    $deleted = sql_query("DELETE FROM `{$g5['inventory_table']}` WHERE in_id='".(int)$in_id."' LIMIT 1", false);
    if (!$deleted) {
        error_log('bbs_trait: failed to remove a previously consumed trait item.');
        return false;
    }
    if (function_exists('sql_affected_rows') && (int)sql_affected_rows() !== 1) return 0;
    if (function_exists('mdl_do_action')) {
        mdl_do_action('inventory_delete_after', (int)$in_id, 0, $item);
        mdl_do_action('inventory_use_after', (int)$in_id, (int)$item['ch_id'], bbs_trait_acquisition_item_type());
    }
    return 1;
}

function bbs_trait_item_cleanup_consumed($in_id) {
    $lock_name = 'bbs_trait_item_'.(int)$in_id;
    $lock_result = sql_fetch("SELECT GET_LOCK('".bbs_trait_escape($lock_name)."', 5) AS acquired", false);
    if (!isset($lock_result['acquired']) || (int)$lock_result['acquired'] !== 1) {
        return array(false, '특성 습득권을 확인 중입니다. 잠시 후 다시 시도해 주세요.');
    }
    $receipt = bbs_trait_item_get_receipt($in_id);
    if ($receipt === false) {
        bbs_trait_item_release_lock($lock_name);
        error_log('bbs_trait: failed to check the trait item use receipt.');
        return array(false, '특성 습득권 사용 기록을 확인하지 못했습니다.');
    }
    if (!$receipt) {
        bbs_trait_item_release_lock($lock_name);
        return array(true, '');
    }
    $removed = bbs_trait_item_remove_used_copy($in_id);
    bbs_trait_item_release_lock($lock_name);
    if ($removed === false) return array(false, '이미 사용 처리된 아이템을 정리하지 못했습니다. 운영자에게 문의해 주세요.');
    return array(true, $removed === 1
        ? '이미 사용 처리된 특성 습득권을 인벤토리에서 정리했습니다. 특성은 추가로 지급되지 않았습니다.'
        : '이미 사용 처리된 특성 습득권입니다. 특성은 추가로 지급되지 않았습니다.');
}

function bbs_trait_item_card($trait, $extra_class = '') {
    $icon = bbs_trait_icon_url(isset($trait['trait_icon']) ? (string)$trait['trait_icon'] : '');
    $effect = bbs_trait_effect_text(
        isset($trait['effect_type']) ? $trait['effect_type'] : '',
        isset($trait['effect_value']) ? $trait['effect_value'] : 0,
        bbs_trait_effect_scope_label(isset($trait['effect_stat_id']) ? $trait['effect_stat_id'] : 0)
    );
    $html = '<article class="bbs-trait-item-card'.($extra_class !== '' ? ' '.bbs_trait_html($extra_class) : '').'">';
    if ($icon !== '') $html .= '<img src="'.bbs_trait_html($icon).'" alt="">';
    $html .= '<div><strong>'.bbs_trait_html(isset($trait['trait_name']) ? $trait['trait_name'] : '').'</strong>';
    $html .= '<p class="bbs-trait-item-effect">'.bbs_trait_html($effect).'</p>';
    $html .= '<p class="bbs-trait-item-description">'.bbs_trait_html(isset($trait['trait_description']) ? $trait['trait_description'] : '').'</p>';
    $html .= '</div></article>';
    return $html;
}

function bbs_trait_item_acquisition_form($in_id, $return_url, $character, $trait, $owned, $open_slots) {
    $needs_replacement = count($owned) >= $open_slots;
    $token = get_token();
    $action = G5_URL.'/inventory/use_item.php';
    $new_card = bbs_trait_item_card($trait, 'bbs-trait-item-new-card');
    $html = '<link rel="stylesheet" href="'.bbs_trait_html(G5_MDL_URL.'/bbs_trait/css/item-use.css?v=1.5.1').'">';
    $html .= '<div class="bbs-trait-item-use" id="bbs-trait-item-use">';
    $html .= '<h3>특성 습득</h3>';
    $html .= '<p>'.bbs_trait_html($character['ch_name']).' 캐릭터가 다음 특성을 획득합니다.</p>';
    $html .= '<div class="bbs-trait-item-new">'.$new_card.'</div>';
    $html .= '<form class="bbs-trait-item-use-form" method="post" action="'.bbs_trait_html($action).'" data-needs-replacement="'.($needs_replacement ? '1' : '0').'">';
    $html .= '<input type="hidden" name="in_id" value="'.(int)$in_id.'">';
    $html .= '<input type="hidden" name="url" value="'.bbs_trait_html(rawurlencode($return_url)).'">';
    $html .= '<input type="hidden" name="bbs_trait_item_confirm" value="1">';
    $html .= '<input type="hidden" name="bbs_trait_item_token" value="'.bbs_trait_html($token).'">';
    $html .= '<input type="hidden" name="replace_trait_id" value="0">';
    $html .= '<input type="hidden" name="bbs_trait_item_compared" value="0">';

    if ($needs_replacement) {
        $html .= '<p>보유 특성이 슬롯 수 이상입니다. 교체할 기존 특성을 선택하고 새 특성과 비교해 주세요.</p>';
        $html .= '<fieldset class="bbs-trait-item-options"><legend>교체할 기존 특성</legend>';
        foreach ($owned as $old_trait) {
            $html .= '<label class="bbs-trait-item-option"><input type="radio" name="replace-choice" value="'.(int)$old_trait['trait_id'].'">'
                .bbs_trait_item_card($old_trait).'</label>';
        }
        $html .= '</fieldset>';
        $html .= '<div class="bbs-trait-item-compare" hidden><section><h4>교체할 특성</h4><div class="bbs-trait-item-old-preview"></div></section>'
            .'<section><h4>획득할 특성</h4><div class="bbs-trait-item-new-preview">'.$new_card.'</div></section></div>';
        $html .= '<button type="button" class="btn bbs-trait-item-compare-button" disabled>효과 비교</button>';
        $html .= '<button type="submit" class="btn_submit bbs-trait-item-confirm-button" hidden>교체 확정</button>';
    } else {
        $html .= '<p>빈 슬롯에 새 특성을 추가합니다. 사용하면 아이템은 소모됩니다.</p>';
        $html .= '<button type="submit" class="btn_submit bbs-trait-item-confirm-button">특성 획득</button>';
    }
    $html .= '<button type="button" class="btn bbs-trait-item-cancel">취소</button>';
    $html .= '</form></div>';
    $html .= '<script>(function(){'
        .'var box=document.getElementById("bbs-trait-item-use");'
        .'if(!box)return;'
        .'var popup=box.closest(".inven-popup-viewer");if(popup)popup.classList.add("bbs-trait-item-popup");'
        .'var form=box.querySelector(".bbs-trait-item-use-form");'
        .'var needs=form.getAttribute("data-needs-replacement")==="1";'
        .'var replaceId=form.querySelector("[name=replace_trait_id]");'
        .'var compareButton=box.querySelector(".bbs-trait-item-compare-button");'
        .'var confirmButton=box.querySelector(".bbs-trait-item-confirm-button");'
        .'var compare=box.querySelector(".bbs-trait-item-compare");'
        .'var oldPreview=box.querySelector(".bbs-trait-item-old-preview");'
        .'var comparedField=form.querySelector("[name=bbs_trait_item_compared]");'
        .'var compared=false;'
        .'function showDuplicateNotice(text){var content=document.querySelector("#inven_popup_viewer .inner-content");if(!content)return false;'
            .'content.innerHTML=text;var notice=content.querySelector("[data-bbs-trait-item-duplicate-notice=\\"1\\"]");'
            .'var noticePopup=content.closest(".inven-popup-viewer");if(noticePopup)noticePopup.classList.add("bbs-trait-item-popup");'
            .'var closeButton=notice&&notice.querySelector(".bbs-trait-item-notice-close");'
            .'if(closeButton)closeButton.addEventListener("click",function(){var close=document.querySelector(".btn-inven-popup-close");if(close)close.click();else notice.remove();});'
            .'return !!notice;}'
        .'function selected(){return form.querySelector("[name=\\"replace-choice\\"]:checked");}'
        .'if(needs){'
            .'form.querySelectorAll("[name=\\"replace-choice\\"]").forEach(function(radio){radio.addEventListener("change",function(){'
                .'compared=false;comparedField.value="0";replaceId.value="0";compare.hidden=true;confirmButton.hidden=true;compareButton.hidden=false;compareButton.disabled=false;'
            .'});});'
            .'compareButton.addEventListener("click",function(){var radio=selected();if(!radio)return;'
                .'var label=radio.closest(".bbs-trait-item-option");var card=label&&label.querySelector(".bbs-trait-item-card");if(!card)return;'
                .'oldPreview.innerHTML="";oldPreview.appendChild(card.cloneNode(true));replaceId.value=radio.value;comparedField.value="1";compared=true;compare.hidden=false;compareButton.hidden=true;confirmButton.hidden=false;'
            .'});'
        .'}'
        .'form.addEventListener("submit",function(event){event.preventDefault();'
            .'if(needs&&!compared)return;'
            .'confirmButton.disabled=true;'
            .'fetch(form.action,{method:"POST",body:new FormData(form),credentials:"same-origin"})'
            .'.then(function(response){return response.text();})'
            .'.then(function(text){if(text.indexOf("data-bbs-trait-item-duplicate-notice=\\"1\\"")!==-1&&showDuplicateNotice(text))return;'
                .'var marker="LOCATIONURL||||";if(text.indexOf(marker)===0){window.location.href=text.substring(marker.length).trim();return;}'
                .'confirmButton.disabled=false;var message=box.querySelector(".bbs-trait-item-result");if(!message){message=document.createElement("p");message.className="bbs-trait-item-result";form.insertBefore(message,form.firstChild);}message.textContent="처리 응답을 확인하지 못했습니다. 아이템 보유 상태를 확인한 뒤 다시 시도해 주세요.";'
            .'}).catch(function(){confirmButton.disabled=false;var message=box.querySelector(".bbs-trait-item-result");if(!message){message=document.createElement("p");message.className="bbs-trait-item-result";form.insertBefore(message,form.firstChild);}message.textContent="요청에 실패했습니다. 아이템은 처리되지 않았습니다. 다시 시도해 주세요.";});'
        .'});'
        .'box.querySelector(".bbs-trait-item-cancel").addEventListener("click",function(){var close=document.querySelector(".btn-inven-popup-close");if(close)close.click();else box.remove();});'
        .'})();</script>';
    return $html;
}

function bbs_trait_item_check_token() {
    $posted = isset($_POST['bbs_trait_item_token']) && is_scalar($_POST['bbs_trait_item_token'])
        ? (string)$_POST['bbs_trait_item_token'] : '';
    $session_token = function_exists('get_session') ? (string)get_session('ss_token') : '';
    if ($posted === '' || $session_token === '' || !hash_equals($session_token, $posted)) return false;
    if (function_exists('set_session')) set_session('ss_token', '');
    return true;
}

function bbs_trait_item_commit_acquisition($in_id, $ch_id, $replace_id) {
    global $g5, $member, $is_admin;
    $tables = bbs_trait_tables();
    $in_id = (int)$in_id;
    $ch_id = (int)$ch_id;
    $replace_id = (int)$replace_id;
    $lock_name = 'bbs_trait_item_'.$in_id;

    // The core inventory table is MyISAM, so serialize this module's concurrent uses explicitly.
    $lock_result = sql_fetch("SELECT GET_LOCK('".bbs_trait_escape($lock_name)."', 5) AS acquired", false);
    if (!isset($lock_result['acquired']) || (int)$lock_result['acquired'] !== 1) {
        error_log('bbs_trait: failed to acquire the trait item use lock.');
        return array(false, '아이템 사용 중 다른 요청이 있거나 잠금을 얻지 못했습니다. 다시 시도해 주세요.');
    }
    $receipt = bbs_trait_item_get_receipt($in_id);
    if ($receipt === false) {
        bbs_trait_item_release_lock($lock_name);
        error_log('bbs_trait: failed to check for a previous trait item use.');
        return array(false, '특성 습득권 사용 기록을 확인하지 못했습니다. 아이템은 처리하지 않았습니다.');
    }
    if ($receipt) {
        $removed = bbs_trait_item_remove_used_copy($in_id);
        bbs_trait_item_release_lock($lock_name);
        if ($removed === false) {
            return array(false, '이미 사용 처리된 아이템을 정리하지 못했습니다. 운영자에게 문의해 주세요.');
        }
        return array(false, $removed === 1
            ? '이미 사용 처리된 특성 습득권을 정리했습니다. 특성은 추가로 지급되지 않았습니다.'
            : '이미 사용 처리된 특성 습득권입니다. 특성은 추가로 지급되지 않았습니다.');
    }
    if (!sql_query('START TRANSACTION', false)) {
        error_log('bbs_trait: failed to start trait item acquisition transaction.');
        bbs_trait_item_release_lock($lock_name);
        return array(false, '특성 습득을 시작하지 못했습니다. 아이템은 소모되지 않았습니다.');
    }
    $character = sql_fetch("SELECT ch_id, ch_name, mb_id, ch_state, ch_type
        FROM `{$g5['character_table']}` WHERE ch_id='{$ch_id}' LIMIT 1 FOR UPDATE", false);
    if (empty($character['ch_id']) || $character['ch_state'] !== '승인'
        || !in_array($character['ch_type'], array('main', 'sub'), true)
        || ((string)$character['mb_id'] !== (string)$member['mb_id'] && empty($is_admin))) {
        return bbs_trait_item_abort_transaction($lock_name, '특성을 습득할 캐릭터의 권한이나 승인 상태가 변경되었습니다. 아이템은 소모되지 않았습니다.');
    }

    $item = bbs_trait_item_load_inventory($in_id, $ch_id, true);
    if (empty($item['in_id']) || (string)$item['it_type'] !== bbs_trait_acquisition_item_type()) {
        return bbs_trait_item_abort_transaction($lock_name, '특성 습득권 보유 정보를 확인하지 못했습니다. 아이템은 소모되지 않았습니다.');
    }
    if ($item['it_use'] !== 'Y' || empty($item['it_use_able']) || !empty($item['it_use_ever'])) {
        return bbs_trait_item_abort_transaction($lock_name, '특성 습득권 설정이 올바르지 않습니다. 아이템은 소모되지 않았습니다.');
    }

    $trait_id = (int)$item['trait_value'];
    $trait = sql_fetch("SELECT * FROM `{$tables['catalog']}`
        WHERE trait_id='{$trait_id}' AND trait_enabled=1 LIMIT 1 FOR UPDATE", false);
    if (empty($trait['trait_id'])) {
        return bbs_trait_item_abort_transaction($lock_name, '연결된 특성이 비활성화되었거나 존재하지 않습니다. 아이템은 소모되지 않았습니다.');
    }

    if (!sql_query("INSERT IGNORE INTO `{$tables['slots']}` (ch_id, slots_unlocked) VALUES ('{$ch_id}', 0)", false)) {
        error_log('bbs_trait: failed to initialize slots for trait item acquisition.');
        return bbs_trait_item_abort_transaction($lock_name, '특성 슬롯 정보를 확인하지 못했습니다. 아이템은 소모되지 않았습니다.');
    }
    $slot_row = sql_fetch("SELECT slots_unlocked FROM `{$tables['slots']}` WHERE ch_id='{$ch_id}' LIMIT 1 FOR UPDATE", false);
    if (!isset($slot_row['slots_unlocked'])) {
        error_log('bbs_trait: failed to lock slots for trait item acquisition.');
        return bbs_trait_item_abort_transaction($lock_name, '특성 슬롯 정보를 확인하지 못했습니다. 아이템은 소모되지 않았습니다.');
    }

    $owned_result = sql_query("SELECT trait_id FROM `{$tables['owned']}` WHERE ch_id='{$ch_id}' FOR UPDATE", false);
    if (!$owned_result) {
        error_log('bbs_trait: failed to lock owned traits for trait item acquisition.');
        return bbs_trait_item_abort_transaction($lock_name, '현재 보유 특성을 확인하지 못했습니다. 아이템은 소모되지 않았습니다.');
    }
    $owned_ids = array();
    while ($owned_row = sql_fetch_array($owned_result)) $owned_ids[] = (int)$owned_row['trait_id'];
    if (in_array($trait_id, $owned_ids, true)) {
        return bbs_trait_item_abort_transaction($lock_name, '이미 보유한 특성입니다.', 'duplicate');
    }

    $max_slots = bbs_trait_max_slots();
    $open_slots = min($max_slots, bbs_trait_base_slots() + max(0, (int)$slot_row['slots_unlocked']));
    $needs_replacement = count($owned_ids) >= $open_slots;
    if ($needs_replacement && !$owned_ids) {
        return bbs_trait_item_abort_transaction($lock_name, '개방된 특성 슬롯이 없고 교체할 보유 특성도 없습니다. 아이템은 소모되지 않았습니다.');
    }
    if ($needs_replacement && (!isset($_POST['bbs_trait_item_compared'])
        || !is_scalar($_POST['bbs_trait_item_compared']) || (string)$_POST['bbs_trait_item_compared'] !== '1')) {
        return bbs_trait_item_abort_transaction($lock_name, '기존 특성과 새 특성을 비교한 뒤 교체를 확정해 주세요. 아이템은 소모되지 않았습니다.');
    }
    if ($needs_replacement && ($replace_id < 1 || !in_array($replace_id, $owned_ids, true))) {
        return bbs_trait_item_abort_transaction($lock_name, '교체할 기존 특성을 다시 선택해 주세요. 아이템은 소모되지 않았습니다.');
    }
    if (!$needs_replacement) $replace_id = 0;

    if ($replace_id > 0) {
        $removed = sql_query("DELETE FROM `{$tables['owned']}` WHERE ch_id='{$ch_id}' AND trait_id='{$replace_id}' LIMIT 1", false);
        if (!$removed || (function_exists('sql_affected_rows') && (int)sql_affected_rows() !== 1)) {
            error_log('bbs_trait: failed to remove the replaced trait during item acquisition.');
            return bbs_trait_item_abort_transaction($lock_name, '기존 특성을 교체하지 못했습니다. 아이템은 소모되지 않았습니다.');
        }
    }

    $granted_by = bbs_trait_escape(isset($member['mb_id']) ? $member['mb_id'] : '');
    $granted_at = bbs_trait_escape(defined('G5_TIME_YMDHIS') ? G5_TIME_YMDHIS : date('Y-m-d H:i:s'));
    $inserted = sql_query("INSERT INTO `{$tables['owned']}` (ch_id, trait_id, granted_by, granted_at)
        VALUES ('{$ch_id}', '{$trait_id}', '{$granted_by}', '{$granted_at}')", false);
    if (!$inserted) {
        error_log('bbs_trait: failed to grant a trait from an acquisition item.');
        return bbs_trait_item_abort_transaction($lock_name, '특성을 등록하지 못했습니다. 아이템은 소모되지 않았습니다.');
    }
    $used_at = bbs_trait_escape(defined('G5_TIME_YMDHIS') ? G5_TIME_YMDHIS : date('Y-m-d H:i:s'));
    $receipt_inserted = sql_query("INSERT INTO `{$tables['item_use']}`
        (in_id, ch_id, trait_id, replaced_trait_id, used_at)
        VALUES ('{$in_id}', '{$ch_id}', '{$trait_id}', '{$replace_id}', '{$used_at}')", false);
    if (!$receipt_inserted) {
        error_log('bbs_trait: failed to record a trait acquisition item use.');
        return bbs_trait_item_abort_transaction($lock_name, '특성 습득 기록을 저장하지 못했습니다. 아이템은 소모되지 않았습니다.');
    }
    if (!sql_query('COMMIT', false)) {
        error_log('bbs_trait: failed to commit trait item acquisition.');
        return bbs_trait_item_abort_transaction($lock_name, '특성 습득을 완료하지 못했습니다. 아이템은 소모되지 않았습니다.');
    }

    // The InnoDB receipt makes MyISAM inventory cleanup retryable without granting the trait twice.
    $inventory_table = $g5['inventory_table'];
    $deleted = sql_query("DELETE FROM `{$inventory_table}` WHERE in_id='{$in_id}' AND ch_id='{$ch_id}' LIMIT 1", false);
    if (!$deleted) {
        error_log('bbs_trait: trait granted but the MyISAM acquisition item could not be removed.');
        bbs_trait_item_release_lock($lock_name);
        return array(true, '특성은 습득했지만 아이템을 인벤토리에서 정리하지 못했습니다. 이 아이템은 다시 사용되지 않으며, 운영자에게 문의해 주세요.');
    }
    $item_removed = !function_exists('sql_affected_rows') || (int)sql_affected_rows() === 1;
    if ($item_removed && function_exists('mdl_do_action')) {
        mdl_do_action('inventory_delete_after', $in_id, 0, $item);
        mdl_do_action('inventory_use_after', $in_id, $ch_id, bbs_trait_acquisition_item_type());
    }
    bbs_trait_item_release_lock($lock_name);
    $message = $replace_id > 0
        ? '특성을 교체하고 『'.$trait['trait_name'].'』을(를) 습득했습니다. 과거 사용 기록은 보존됩니다.'
        : '『'.$trait['trait_name'].'』 특성을 습득했습니다.';
    if (!$item_removed) $message .= ' 아이템은 이미 이동되었거나 정리된 상태이며, 같은 아이템으로는 특성을 다시 습득할 수 없습니다.';
    return array(true, $message);
}

function bbs_trait_use_acquisition_item($result, $ctx) {
    if (!empty($result['handled'])) return $result;
    $item_context = isset($ctx['in']) && is_array($ctx['in']) ? $ctx['in'] : array();
    if (!isset($item_context['it_type']) || (string)$item_context['it_type'] !== bbs_trait_acquisition_item_type()) return $result;

    $in_id = isset($ctx['in_id']) ? (int)$ctx['in_id'] : 0;
    $ch_id = isset($item_context['ch_id']) ? (int)$item_context['ch_id'] : 0;
    $return_url = bbs_trait_item_return_url(isset($ctx['return_url']) ? $ctx['return_url'] : '');
    $character = bbs_trait_item_use_authorized_character($ch_id);
    if (!$character || $in_id < 1) {
        return bbs_trait_item_use_response($result, $return_url, '특성 습득권을 사용할 캐릭터의 권한과 상태를 확인해 주세요. 아이템은 소모되지 않았습니다.');
    }

    $item = bbs_trait_item_load_inventory($in_id, $ch_id);
    if (empty($item['in_id']) || (string)$item['it_type'] !== bbs_trait_acquisition_item_type()) {
        return bbs_trait_item_use_response($result, $return_url, '특성 습득권 보유 정보를 확인하지 못했습니다. 아이템은 소모되지 않았습니다.');
    }
    $cleanup = bbs_trait_item_cleanup_consumed($in_id);
    if (!$cleanup[0]) return bbs_trait_item_use_response($result, $return_url, $cleanup[1]);
    if ($cleanup[1] !== '') return bbs_trait_item_use_response($result, $return_url, $cleanup[1]);
    if ($item['it_use'] !== 'Y' || empty($item['it_use_able']) || !empty($item['it_use_ever'])) {
        return bbs_trait_item_use_response($result, $return_url, '특성 습득권 설정이 올바르지 않습니다. 아이템은 소모되지 않았습니다.');
    }
    $trait_id = (int)$item['trait_value'];
    $trait = bbs_trait_get($trait_id);
    if (!$trait) {
        return bbs_trait_item_use_response($result, $return_url, '연결된 특성이 비활성화되었거나 존재하지 않습니다. 아이템은 소모되지 않았습니다.');
    }

    if (isset($_POST['bbs_trait_item_confirm'])) {
        if (!is_scalar($_POST['bbs_trait_item_confirm']) || (string)$_POST['bbs_trait_item_confirm'] !== '1'
            || !bbs_trait_item_check_token()) {
            return bbs_trait_item_use_response($result, $return_url, '확인 정보가 만료되었거나 유효하지 않습니다. 아이템은 소모되지 않았습니다.');
        }
        $replace_id = isset($_POST['replace_trait_id']) && is_scalar($_POST['replace_trait_id'])
            ? (int)$_POST['replace_trait_id'] : 0;
        $outcome = bbs_trait_item_commit_acquisition($in_id, $ch_id, $replace_id);
        if (isset($outcome[2]) && $outcome[2] === 'duplicate') {
            return bbs_trait_item_duplicate_notice_response($result);
        }
        return bbs_trait_item_use_response($result, $return_url, $outcome[1]);
    }

    $owned = bbs_trait_get_owned($ch_id, false);
    $open_slots = bbs_trait_open_slots($ch_id);
    if ($owned === false || $open_slots < 0) {
        return bbs_trait_item_use_response($result, $return_url, '현재 보유 특성과 슬롯을 불러오지 못했습니다. 아이템은 소모되지 않았습니다.');
    }
    foreach ($owned as $owned_trait) {
        if ((int)$owned_trait['trait_id'] === $trait_id) {
            return bbs_trait_item_duplicate_notice_response($result);
        }
    }
    if (count($owned) >= $open_slots && !$owned) {
        return bbs_trait_item_use_response($result, $return_url, '개방된 특성 슬롯이 없고 교체할 보유 특성도 없습니다. 먼저 슬롯을 확장해 주세요. 아이템은 소모되지 않았습니다.');
    }

    $result['handled'] = true;
    $result['echo'] = bbs_trait_item_acquisition_form($in_id, $return_url, $character, $trait, $owned, $open_slots);
    return $result;
}
