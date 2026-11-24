<?php
/**
 * ============================================================================
 *  화폐 표시 포맷 — 갈레온 / 시클 / 크넛
 * ============================================================================
 *
 *  DB(mb_point 등)에는 여전히 정수 1개만 저장한다. 그 정수를 가장 작은 단위인
 *  "크넛" 으로 간주하고, 화면에 보여줄 때만 3단계로 환산한다.
 *
 *  1 갈레온 = 17 시클, 1 시클 = 29 크넛  →  1 갈레온 = 493 크넛
 *
 *  cf_money / cf_money_pice(관리자 > 상점/화폐 설정)는 "화폐"라는 일반 명칭으로
 *  남겨두고, 실제 금액을 보여주는 자리에서는 이 함수를 쓴다.
 *
 *  금액을 직접 입력받는 자리는 cf_money_input() 으로 갈레온/시클/크넛 3칸
 *  입력을 받고, 그 합계를 hidden input(기존 필드명 그대로)에 담아 제출한다.
 * ============================================================================
 */
if (!defined('_GNUBOARD_')) exit;

if (!function_exists('cf_money_format')) {
    function cf_money_format($amount)
    {
        $amount = (int)$amount;
        $sign = $amount < 0 ? '-' : '';
        $amount = abs($amount);

        $galleons = intdiv($amount, 493);
        $remainder = $amount % 493;
        $sickles = intdiv($remainder, 29);
        $knuts = $remainder % 29;

        $parts = array();
        if ($galleons > 0) $parts[] = number_format($galleons).'갈레온';
        if ($sickles > 0) $parts[] = number_format($sickles).'시클';
        if ($knuts > 0 || empty($parts)) $parts[] = number_format($knuts).'크넛';

        return $sign.implode(' ', $parts);
    }
}

if (!function_exists('cf_money_input')) {
    /**
     * 화폐 금액을 갈레온/시클/크넛 3칸으로 입력받는 위젯을 출력한다.
     * 실제 제출되는 값은 $name 이름의 hidden input(최소단위 정수, 기존과 동일)이므로
     * 처리하는 쪽(PHP) 코드는 수정할 필요가 없다.
     *
     * $opts:
     *   - id      : 기본 id(기본값 $name)
     *   - signed  : true 면 지급(+)/차감(-) 선택을 추가한다(기본 false)
     *   - preview : true 면 합계(최소단위) 미리보기 텍스트를 함께 표시한다(기본 true)
     */
    function cf_money_input($name, $value = 0, $opts = array())
    {
        $value  = (int)$value;
        $signed = !empty($opts['signed']);
        $preview = !isset($opts['preview']) || $opts['preview'];
        $id = isset($opts['id']) ? $opts['id'] : $name;

        $sign = '+';
        $abs = $value;
        if ($signed && $value < 0) { $sign = '-'; $abs = abs($value); }

        $g = intdiv($abs, 493);
        $r = $abs % 493;
        $s = intdiv($r, 29);
        $k = $r % 29;

        $id_js = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
        $name_js = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

        static $script_printed = false;
        $html = '';
        if (!$script_printed) {
            $script_printed = true;
            $html .= cf_money_input_script();
        }

        $html .= '<span class="cf-money-input" id="'.$id_js.'_wrap">';
        if ($signed) {
            $html .= '<select id="'.$id_js.'_sign" class="frm_input" onchange="cfMoneyRecalc(\''.$id_js.'\')">'
                . '<option value="+"'.($sign==='+'?' selected':'').'>지급(+)</option>'
                . '<option value="-"'.($sign==='-'?' selected':'').'>차감(-)</option>'
                . '</select> ';
        }
        $html .= '<input type="number" min="0" step="1" class="frm_input" style="width:70px;" id="'.$id_js.'_g" value="'.$g.'" oninput="cfMoneyRecalc(\''.$id_js.'\')">갈레온 ';
        $html .= '<input type="number" min="0" max="16" step="1" class="frm_input" style="width:60px;" id="'.$id_js.'_s" value="'.$s.'" oninput="cfMoneyRecalc(\''.$id_js.'\')">시클 ';
        $html .= '<input type="number" min="0" max="28" step="1" class="frm_input" style="width:60px;" id="'.$id_js.'_k" value="'.$k.'" oninput="cfMoneyRecalc(\''.$id_js.'\')">크넛';
        $html .= '<input type="hidden" name="'.$name_js.'" id="'.$id_js.'" value="'.$value.'">';
        if ($preview) {
            $html .= ' <small class="cf-money-preview" id="'.$id_js.'_preview" style="color:#666;">('.cf_money_format($value).')</small>';
        }
        $html .= '</span>';
        return $html;
    }
}

if (!function_exists('cf_money_input_script')) {
    function cf_money_input_script()
    {
        return <<<'EOS'
<script>
function cfMoneyFormat(v) {
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
    if (k > 0 || parts.length === 0) parts.push(k + '크넛');
    return sign + parts.join(' ');
}
function cfMoneyRecalc(id) {
    var g = document.getElementById(id + '_g');
    var s = document.getElementById(id + '_s');
    var k = document.getElementById(id + '_k');
    var h = document.getElementById(id);
    if (!g || !s || !k || !h) return;
    var sg = document.getElementById(id + '_sign');
    var total = (parseInt(g.value, 10) || 0) * 493 + (parseInt(s.value, 10) || 0) * 29 + (parseInt(k.value, 10) || 0);
    if (sg && sg.value === '-') total = -total;
    h.value = total;
    var p = document.getElementById(id + '_preview');
    if (p) p.textContent = '(' + cfMoneyFormat(total) + ')';
}
function cfMoneySet(id, total) {
    total = parseInt(total, 10) || 0;
    var g = document.getElementById(id + '_g');
    var s = document.getElementById(id + '_s');
    var k = document.getElementById(id + '_k');
    var sg = document.getElementById(id + '_sign');
    if (!g || !s || !k) return;
    var sign = total < 0 ? '-' : '+';
    var abs = Math.abs(total);
    g.value = Math.floor(abs / 493);
    var r = abs % 493;
    s.value = Math.floor(r / 29);
    k.value = r % 29;
    if (sg) sg.value = sign;
    cfMoneyRecalc(id);
}
</script>
EOS;
    }
}
