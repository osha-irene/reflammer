<?php
/**
 * ============================================================================
 *  화폐 표시 포맷 — 갈레온 / 시클 / 넛
 * ============================================================================
 *
 *  DB(mb_point 등)에는 여전히 정수 1개만 저장한다. 그 정수를 가장 작은 단위인
 *  "넛" 으로 간주하고, 화면에 보여줄 때만 3단계로 환산한다.
 *
 *  1 갈레온 = 17 시클, 1 시클 = 29 넛  →  1 갈레온 = 493 넛
 *
 *  cf_money / cf_money_pice(관리자 > 상점/화폐 설정)는 "화폐"라는 일반 명칭으로
 *  남겨두고, 실제 금액을 보여주는 자리에서는 이 함수를 쓴다.
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
        if ($knuts > 0 || empty($parts)) $parts[] = number_format($knuts).'넛';

        return $sign.implode(' ', $parts);
    }
}
