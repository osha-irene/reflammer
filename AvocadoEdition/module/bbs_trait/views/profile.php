<?php
if (!defined('_GNUBOARD_')) exit;
?>
<section class="bbs-trait-profile">
    <h3>보유 특성 <small><?php echo count($traits); ?> / <?php echo (int)$slot_count; ?> (최대 <?php echo (int)$max_slot_count; ?>)</small></h3>
    <ul>
        <?php foreach ($traits as $trait) { ?>
            <li>
                <?php $icon_url = bbs_trait_icon_url((string)$trait['trait_icon']); ?>
                <?php if ($icon_url !== '') { ?>
                    <img src="<?php echo bbs_trait_html($icon_url); ?>" alt="" loading="lazy">
                <?php } ?>
                <span>
                    <strong><?php echo bbs_trait_html($trait['trait_name']); ?></strong>
                    <small><?php echo bbs_trait_html(bbs_trait_effect_text($trait['effect_type'], $trait['effect_value'], bbs_trait_effect_scope_label(isset($trait['effect_stat_id']) ? $trait['effect_stat_id'] : 0))); ?></small>
                    <?php if ((string)$trait['trait_description'] !== '') { ?>
                        <span class="bbs-trait-description"><?php echo nl2br(bbs_trait_html($trait['trait_description'])); ?></span>
                    <?php } ?>
                </span>
            </li>
        <?php } ?>
    </ul>
</section>
