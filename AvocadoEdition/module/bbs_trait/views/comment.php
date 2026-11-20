<?php
if (!defined('_GNUBOARD_')) exit;
?>
<div class="bbs-trait-comment">
    <?php if ($trait_comment_icon_url !== '') { ?>
        <img src="<?php echo bbs_trait_html($trait_comment_icon_url); ?>" alt="">
    <?php } ?>
    <div class="bbs-trait-comment-content">
        <strong>특성 발현: <?php echo bbs_trait_html($trait_comment_name); ?></strong>
        <?php if ($trait_comment_description !== '') { ?>
            <div class="bbs-trait-comment-description"><?php echo nl2br(bbs_trait_html($trait_comment_description)); ?></div>
        <?php } ?>
    </div>
</div>
