<?php
/** @var string $helpText Translated plain-text description. */
$helpId = wp_unique_id('wpok-help-');
?>
<button type="button" class="wpok-help-trigger" popovertarget="<?php echo esc_attr($helpId); ?>" aria-label="<?php echo esc_attr__('More information', 'wp-optikit'); ?>" aria-describedby="<?php echo esc_attr($helpId); ?>">
    <span aria-hidden="true">?</span>
</button>
<div id="<?php echo esc_attr($helpId); ?>" class="wpok-help-popover" role="tooltip" popover="auto">
    <p><?php echo esc_html($helpText); ?></p>
</div>
