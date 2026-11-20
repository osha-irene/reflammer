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
                    <strong>= <?php echo (int)$combatant['score']; ?></strong>
                </div>
            </section>
        <?php } ?>
    </div>
    <?php if ($outcome_label !== '') { ?><p class="bbs-duel-result"><strong><?php echo htmlspecialchars($outcome_label, ENT_QUOTES, 'UTF-8'); ?></strong></p><?php } ?>
    <?php if ($type === 'duel' && $hp_damage > 0) {
        $loser_name = $outcome === 'actor_win' ? $target_name : $actor_name;
        ?><p class="bbs-duel-damage"><?php echo htmlspecialchars($loser_name, ENT_QUOTES, 'UTF-8'); ?> HP -<?php echo $hp_damage; ?></p><?php } ?>
</div>
