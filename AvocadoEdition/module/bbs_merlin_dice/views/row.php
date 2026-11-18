<?php
if (!defined('_GNUBOARD_')) exit;
if (empty($rolls)) return;
$base = htmlspecialchars($module_url, ENT_QUOTES, 'UTF-8');
$label = htmlspecialchars($stat_label, ENT_QUOTES, 'UTF-8');
$render_mode = function_exists('bbs_dice_render_mode') ? bbs_dice_render_mode() : 'image';
?>
<div class="log-action-box">
    <span class="dice">
        <?php if ($label !== '') { ?><span class="merlin-dice-stat"><?php echo $label; ?></span><?php } ?>
        <?php foreach ($rolls as $value) {
            $value = (int)$value;
            $url = ($render_mode === 'image' && function_exists('bbs_dice_image_url_for')) ? bbs_dice_image_url_for($value, $base) : '';
            if ($url !== '') { ?>
                <img src="<?php echo $url; ?>" alt="<?php echo $value; ?>" />
            <?php } else { ?>
                <span class="dice-num"><?php echo $value; ?></span>
            <?php }
        } ?>
    </span>
</div>
