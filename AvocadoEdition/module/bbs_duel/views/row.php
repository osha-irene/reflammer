<?php
if (!defined('_GNUBOARD_')) exit;
$label = $type === 'duel' ? '결투' : '대련';
$target_name = isset($row['wr_duel_target_name']) ? (string)$row['wr_duel_target_name'] : '';
$actor_name = isset($row['wr_duel_actor_name']) && $row['wr_duel_actor_name'] !== ''
    ? (string)$row['wr_duel_actor_name']
    : (isset($row['wr_name']) ? (string)$row['wr_name'] : '작성자');
$stat_name = isset($row['wr_duel_stat_name']) ? (string)$row['wr_duel_stat_name'] : '';
$actor_roll = function_exists('merlin_dice_parse') ? merlin_dice_parse(isset($row['wr_duel_actor_roll']) ? $row['wr_duel_actor_roll'] : '') : array();
$target_roll = function_exists('merlin_dice_parse') ? merlin_dice_parse(isset($row['wr_duel_target_roll']) ? $row['wr_duel_target_roll'] : '') : array();
$actor_score = isset($row['wr_duel_actor_score']) ? (int)$row['wr_duel_actor_score'] : 0;
$target_score = isset($row['wr_duel_target_score']) ? (int)$row['wr_duel_target_score'] : 0;
$actor_roll_initial = function_exists('merlin_dice_parse') ? merlin_dice_parse(isset($row['wr_duel_actor_roll_initial']) ? $row['wr_duel_actor_roll_initial'] : '') : array();
$actor_dice_base = isset($row['wr_duel_actor_dice_base']) ? (int)$row['wr_duel_actor_dice_base'] : count($actor_roll);
$actor_dice_modifier = isset($row['wr_duel_actor_dice_modifier']) ? (int)$row['wr_duel_actor_dice_modifier'] : 0;
$actor_score_modifier = isset($row['wr_duel_actor_score_modifier']) ? (int)$row['wr_duel_actor_score_modifier'] : 0;
$actor_reroll_value = isset($row['wr_duel_actor_reroll_value']) ? (int)$row['wr_duel_actor_reroll_value'] : 0;
$actor_reroll_index = isset($row['wr_duel_actor_reroll_index']) ? (int)$row['wr_duel_actor_reroll_index'] : 0;
$trait_name = isset($row['wr_duel_trait_name']) ? (string)$row['wr_duel_trait_name'] : '';
$trait_icon = isset($row['wr_duel_trait_icon']) ? (string)$row['wr_duel_trait_icon'] : '';
$trait_description = '';
if (array_key_exists('wr_duel_trait_description', $row) && $row['wr_duel_trait_description'] !== null) {
    $trait_description = (string)$row['wr_duel_trait_description'];
} elseif (!empty($row['wr_duel_trait_id']) && function_exists('bbs_trait_get')) {
    static $legacy_trait_descriptions = array();
    $legacy_trait_id = (int)$row['wr_duel_trait_id'];
    if (!array_key_exists($legacy_trait_id, $legacy_trait_descriptions)) {
        $legacy_trait = bbs_trait_get($legacy_trait_id, true);
        $legacy_trait_descriptions[$legacy_trait_id] = isset($legacy_trait['trait_description'])
            ? (string)$legacy_trait['trait_description']
            : '';
    }
    $trait_description = $legacy_trait_descriptions[$legacy_trait_id];
}
$trait_effect_type = isset($row['wr_duel_trait_effect_type']) ? (string)$row['wr_duel_trait_effect_type'] : '';
$trait_effect_value = isset($row['wr_duel_trait_effect_value']) ? (int)$row['wr_duel_trait_effect_value'] : 0;
$trait_effect_labels = array(
    'score_modifier' => '최종 점수 보정',
    'dice_count_modifier' => '주사위 수 보정',
    'reroll_lowest' => '가장 낮은 주사위 재굴림',
    'incoming_damage_modifier' => '받는 피해 보정',
    'outgoing_damage_modifier' => '가하는 피해 보정',
);
if (function_exists('bbs_trait_effect_text')) {
    $trait_effect_label = bbs_trait_effect_text(
        $trait_effect_type,
        $trait_effect_value,
        isset($row['wr_duel_trait_effect_stat_name']) ? (string)$row['wr_duel_trait_effect_stat_name'] : ''
    );
} elseif (isset($trait_effect_labels[$trait_effect_type])) {
    $trait_effect_label = $trait_effect_labels[$trait_effect_type];
    if ($trait_effect_type !== 'reroll_lowest') {
        $trait_effect_label .= ' '.($trait_effect_value > 0 ? '+' : '').$trait_effect_value;
    }
} else {
    $trait_effect_label = $trait_effect_type;
}
$trait_effect_stat_name = isset($row['wr_duel_trait_effect_stat_name']) ? (string)$row['wr_duel_trait_effect_stat_name'] : '';
if (!function_exists('bbs_trait_effect_text') && $trait_effect_stat_name !== '') {
    $trait_effect_label .= ' · '.$trait_effect_stat_name;
}
$outcome = isset($row['wr_duel_result']) ? (string)$row['wr_duel_result'] : '';
$outcome_label = ($outcome === 'actor_win') ? $actor_name.' 승리' : (($outcome === 'target_win') ? $target_name.' 승리' : (($outcome === 'draw') ? '무승부' : ''));
$actor_hp_before = isset($row['wr_duel_actor_hp_before']) ? (int)$row['wr_duel_actor_hp_before'] : 0;
$actor_hp_after = isset($row['wr_duel_actor_hp_after']) ? (int)$row['wr_duel_actor_hp_after'] : 0;
$target_hp_before = isset($row['wr_duel_target_hp_before']) ? (int)$row['wr_duel_target_hp_before'] : 0;
$target_hp_after = isset($row['wr_duel_target_hp_after']) ? (int)$row['wr_duel_target_hp_after'] : 0;
$hp_damage = isset($row['wr_duel_hp_damage']) ? (int)$row['wr_duel_hp_damage'] : 0;
$dice_url = defined('G5_MDL_URL') ? G5_MDL_URL.'/bbs_dice' : '';
$render_mode = function_exists('bbs_dice_render_mode') ? bbs_dice_render_mode() : 'number';
$actor_card = function_exists('bbs_duel_get_character_card') ? bbs_duel_get_character_card(isset($row['wr_duel_actor_ch_id']) ? $row['wr_duel_actor_ch_id'] : 0) : array();
if (!$actor_card && function_exists('bbs_duel_get_legacy_character_card')) $actor_card = bbs_duel_get_legacy_character_card($actor_name);
$target_is_character = isset($row['wr_duel_target_type']) && $row['wr_duel_target_type'] === 'character';
$target_card = $target_is_character && function_exists('bbs_duel_get_character_card')
    ? bbs_duel_get_character_card(isset($row['wr_duel_target_id']) ? $row['wr_duel_target_id'] : 0)
    : array();
$combatants = array(
    array('name' => $actor_name, 'rolls' => $actor_roll, 'score' => $actor_score, 'card' => $actor_card),
    array('name' => $target_name, 'rolls' => $target_roll, 'score' => $target_score, 'card' => $target_card),
);
?>
<div class="log-action-box bbs-duel-log">
    <p class="bbs-duel-heading"><strong><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></strong>
        <?php if ($target_name !== '') { ?> · 상대: <strong><?php echo htmlspecialchars($target_name, ENT_QUOTES, 'UTF-8'); ?></strong><?php } ?></p>
    <?php if ($trait_name !== '') {
        if (function_exists('bbs_trait_icon_url')) {
            $trait_icon_url = bbs_trait_icon_url($trait_icon);
        } elseif ($trait_icon !== '' && preg_match('/^https:\/\//i', $trait_icon) && filter_var($trait_icon, FILTER_VALIDATE_URL) !== false) {
            $trait_icon_url = $trait_icon;
        } else {
            $trait_icon_url = $trait_icon !== '' && defined('G5_DATA_URL') ? G5_DATA_URL.'/bbs_trait/'.rawurlencode(basename($trait_icon)) : '';
        }
        ?>
        <p class="bbs-duel-trait">
            <?php if ($trait_icon_url !== '') { ?><img src="<?php echo htmlspecialchars($trait_icon_url, ENT_QUOTES, 'UTF-8'); ?>" alt=""><?php } ?>
            <span class="bbs-duel-trait-content">
                <strong>특성 발현: <?php echo htmlspecialchars($trait_name, ENT_QUOTES, 'UTF-8'); ?></strong>
                <?php if ($trait_description !== '') { ?><span class="bbs-duel-trait-description"><?php echo nl2br(htmlspecialchars($trait_description, ENT_QUOTES, 'UTF-8')); ?></span><?php } ?>
                <small><?php echo htmlspecialchars($trait_effect_label, ENT_QUOTES, 'UTF-8'); ?></small>
            </span>
        </p>
    <?php } ?>
    <div class="bbs-duel-combatants">
        <?php foreach ($combatants as $combatant_index => $combatant) {
            $card = $combatant['card'];
            $thumb = isset($card['ch_thumb']) ? (string)$card['ch_thumb'] : '';
            if ($thumb !== '' && function_exists('cdn_resolve_url')) $thumb = cdn_resolve_url($thumb);
            $side_id = isset($card['ch_side']) ? (int)$card['ch_side'] : 0;
            $side_icon = ($side_id > 0 && function_exists('get_side_icon')) ? get_side_icon($side_id) : '';
            $side_name = ($side_id > 0 && function_exists('get_side_name')) ? get_side_name($side_id) : '';
            if ($side_name === '-') $side_name = '';
            ?>
            <section class="bbs-duel-combatant">
                <div class="bbs-duel-side" aria-label="소속">
                    <?php if ($side_icon !== '') { echo $side_icon; }
                    elseif ($side_name !== '') { ?><span><?php echo htmlspecialchars($side_name, ENT_QUOTES, 'UTF-8'); ?></span><?php } ?>
                </div>
                <div class="bbs-duel-portrait">
                    <?php if ($thumb !== '') { ?><img src="<?php echo htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($combatant['name'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php } ?>
                </div>
                <?php $is_actor = $combatant_index === 0; $hp_before = $is_actor ? $actor_hp_before : $target_hp_before; $hp_after = $is_actor ? $actor_hp_after : $target_hp_after; ?>
                <strong class="bbs-duel-name"><?php echo htmlspecialchars($combatant['name'], ENT_QUOTES, 'UTF-8'); ?><?php if ($type === 'duel' && $hp_before > 0 && $hp_after === 0) { ?> (전투불능)<?php } ?></strong>
                <?php if ($type === 'duel' && $hp_before > 0) { ?><small class="bbs-duel-hp">HP <?php echo $hp_before; ?> → <?php echo $hp_after; ?></small><?php } ?>
                <div class="bbs-duel-roll">
                    <span><?php echo htmlspecialchars($stat_name, ENT_QUOTES, 'UTF-8'); ?> (<?php echo count($combatant['rolls']); ?>D2)</span>
                    <?php if ($is_actor && $actor_dice_modifier !== 0) { ?>
                        <small class="bbs-duel-trait-detail">주사위 수 <?php echo $actor_dice_base; ?> <?php echo $actor_dice_modifier > 0 ? '+' : '−'; ?> <?php echo abs($actor_dice_modifier); ?> → <?php echo count($combatant['rolls']); ?></small>
                    <?php } ?>
                    <?php if (!$combatant['rolls']) { ?>
                        <span class="bbs-duel-no-dice">굴릴 주사위가 없습니다.</span>
                    <?php } else { ?>
                        <span class="dice"><?php foreach ($combatant['rolls'] as $value) {
                            $value = (int)$value;
                            $image = ($render_mode === 'image' && function_exists('bbs_dice_image_url_for')) ? bbs_dice_image_url_for($value, $dice_url) : '';
                            if ($image !== '') { ?><img src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo $value; ?>"><?php }
                            else { ?><span class="dice-num"><?php echo $value; ?></span><?php }
                        } ?></span>
                    <?php } ?>
                    <?php if ($is_actor && $actor_reroll_index > 0 && isset($actor_roll_initial[$actor_reroll_index - 1])) {
                        $before_reroll = (int)$actor_roll_initial[$actor_reroll_index - 1];
                        $reroll_replaced = $actor_reroll_value > $before_reroll;
                        ?>
                        <small class="bbs-duel-trait-detail">최저 주사위 재굴림 (<?php echo $actor_reroll_index; ?>번째): <?php echo $before_reroll; ?> → <?php echo $actor_reroll_value; ?> (<?php echo $reroll_replaced ? '교체' : '유지'; ?>)</small>
                    <?php } ?>
                    <?php if ($is_actor && $actor_score_modifier !== 0) { ?>
                        <strong>= <?php echo array_sum($combatant['rolls']); ?> <?php echo $actor_score_modifier > 0 ? '+' : '−'; ?> <?php echo abs($actor_score_modifier); ?> = <?php echo (int)$combatant['score']; ?></strong>
                    <?php } else { ?>
                        <strong>= <?php echo (int)$combatant['score']; ?></strong>
                    <?php } ?>
                </div>
            </section>
        <?php } ?>
    </div>
    <?php if ($outcome_label !== '') { ?><p class="bbs-duel-result"><strong><?php echo htmlspecialchars($outcome_label, ENT_QUOTES, 'UTF-8'); ?></strong></p><?php } ?>
    <?php if ($type === 'duel' && $hp_damage > 0) {
        $loser_name = $outcome === 'actor_win' ? $target_name : $actor_name;
        ?><p class="bbs-duel-damage"><?php echo htmlspecialchars($loser_name, ENT_QUOTES, 'UTF-8'); ?> HP -<?php echo $hp_damage; ?></p><?php } ?>
</div>
