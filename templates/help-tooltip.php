<?php
/**
 * @var string $helpId Description ID shared with the setting control.
 * @var list<string> $helpParagraphs Translated plain-text description paragraphs.
 */
?>
<button type="button" class="wpok-help-trigger" popovertarget="<?php echo esc_attr($helpId); ?>" aria-label="<?php echo esc_attr__('More information', 'wp-optikit'); ?>" aria-describedby="<?php echo esc_attr($helpId); ?>">
    <span aria-hidden="true">?</span>
</button>
<span id="<?php echo esc_attr($helpId); ?>" class="wpok-help-popover" role="tooltip" popover="auto">
    <?php foreach ($helpParagraphs as $helpParagraph) : ?>
        <span class="wpok-help-paragraph"><?php echo esc_html($helpParagraph); ?></span>
    <?php endforeach; ?>
</span>
