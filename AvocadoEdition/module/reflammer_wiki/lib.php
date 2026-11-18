<?php
if (!defined('_GNUBOARD_')) exit;

function reflammer_wiki_table($suffix) { return G5_TABLE_PREFIX.'wiki_'.$suffix; }
function reflammer_wiki_e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function reflammer_wiki_notice($message, $url) {
    $flags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;
    $message_json = json_encode((string)$message, $flags);
    $url_json = json_encode((string)$url, $flags);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="ko"><head><meta charset="utf-8"><title>펜시브</title></head><body><script>alert('.$message_json.');window.location.replace('.$url_json.');</script></body></html>';
    exit;
}
function reflammer_wiki_admin_ids() {
    $r = sql_fetch("SELECT ws_value FROM `".reflammer_wiki_table('setting')."` WHERE ws_key='moderators'");
    return array_values(array_filter(array_map('trim', explode("\n", (string)($r['ws_value'] ?? '')))));
}
function reflammer_wiki_is_moderator() {
    global $is_admin, $member;
    return !empty($is_admin) || (isset($member['mb_id']) && in_array($member['mb_id'], reflammer_wiki_admin_ids(), true));
}
function reflammer_wiki_tags($raw) {
    $parts = is_array($raw) ? $raw : preg_split('/[,，#\n]+/u', (string)$raw);
    $out = array();
    foreach ($parts as $tag) {
        $tag = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$tag)));
        $tag = ltrim($tag, '#');
        if ($tag !== '' && mb_strlen($tag, 'UTF-8') <= 80) $out[$tag] = $tag;
    }
    return array_values($out);
}
function reflammer_wiki_tags_store($tags) { return '|'.implode('|', reflammer_wiki_tags($tags)).'|'; }
function reflammer_wiki_image_url($url) {
    $url = trim((string)$url);
    if ($url === '') return '';
    $p = parse_url($url);
    if (!$p || empty($p['scheme']) || strtolower($p['scheme']) !== 'https' || empty($p['host'])) return false;
    if (isset($p['user']) || isset($p['pass']) || preg_match('/[\x00-\x20<>"\']/', $url)) return false;
    return $url;
}
function reflammer_wiki_render_content($content, $html_mode = false) {
    $content = (string)$content;
    if (!$html_mode || !class_exists('DOMDocument')) return nl2br(reflammer_wiki_e($content));
    $previous = libxml_use_internal_errors(true);
    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="rw-root">'.$content.'</div>', LIBXML_NONET | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors(); libxml_use_internal_errors($previous);
    $found = (new DOMXPath($doc))->query('//*[@id="rw-root"]');
    $root = $found ? $found->item(0) : null;
    if (!$root) return nl2br(reflammer_wiki_e($content));
    $allowed = array('p','br','strong','b','em','i','u','ul','ol','li','blockquote','h2','h3','h4','a','img','hr','pre','code','div','span');
    $render = function($node) use (&$render, $allowed) {
        if ($node->nodeType === XML_TEXT_NODE) return nl2br(htmlspecialchars($node->nodeValue, ENT_QUOTES, 'UTF-8'));
        if ($node->nodeType !== XML_ELEMENT_NODE) return '';
        $tag = strtolower($node->nodeName);
        if (in_array($tag, array('script','style','iframe','object','embed','svg','math','form','input','video','audio'), true)) return '';
        if (!in_array($tag, $allowed, true)) { $out=''; foreach($node->childNodes as $child)$out.=$render($child); return $out; }
        $attrs = '';
        if ($tag === 'a' && $node->hasAttribute('href')) { $url=reflammer_wiki_image_url($node->getAttribute('href')); if($url!==false && $url!=='')$attrs.=' href="'.htmlspecialchars($url,ENT_QUOTES,'UTF-8').'" rel="nofollow noopener"'; }
        if ($tag === 'img') {
            $src = reflammer_wiki_image_url($node->getAttribute('src'));
            if ($src === false || $src === '') return '';
            $alt = $node->getAttribute('alt');
            return '<img src="'.htmlspecialchars($src,ENT_QUOTES,'UTF-8').'" alt="'.htmlspecialchars($alt,ENT_QUOTES,'UTF-8').'" loading="lazy" referrerpolicy="no-referrer">';
        }
        if ($tag === 'br' || $tag === 'hr') return '<'.$tag.'>';
        $out=''; foreach($node->childNodes as $child)$out.=$render($child);
        return '<'.$tag.$attrs.'>'.$out.'</'.$tag.'>';
    };
    $out=''; foreach($root->childNodes as $child)$out.=$render($child);
    return $out;
}
function reflammer_wiki_categories($parent = null) {
    $where = ' WHERE wc_parent='.(int)($parent === null ? 0 : $parent);
    $rows = array(); $r = sql_query("SELECT * FROM `".reflammer_wiki_table('category')."`{$where} ORDER BY wc_order ASC,wc_name ASC");
    while ($row = sql_fetch_array($r)) $rows[] = $row;
    return $rows;
}
function reflammer_wiki_find_category($id) {
    return sql_fetch("SELECT c.wc_id,c.wc_name,c.wc_parent,p.wc_name AS parent_name FROM `".reflammer_wiki_table('category')."` c LEFT JOIN `".reflammer_wiki_table('category')."` p ON p.wc_id=c.wc_parent WHERE c.wc_id=".(int)$id." AND c.wc_active=1");
}
function reflammer_wiki_author_character($mb_id) {
    global $g5;
    $mb = sql_real_escape_string($mb_id);
    if (empty($g5['character_table'])) return '';
    $row = sql_fetch("SELECT ch_name FROM `{$g5['character_table']}` WHERE mb_id='{$mb}' ORDER BY ch_id ASC LIMIT 1", false);
    return isset($row['ch_name']) ? (string)$row['ch_name'] : '';
}
