<?php
function mcab_display_announcement_bar()
{
    $options = get_option('mcab_settings');
    if ($options['mcab_field_enable']) {
        echo '<style>' . esc_html($options['mcab_field_custom_css']) . '</style>';
        echo '<div id="mortens-cool-announcement-bar" style="text-align: center; color: ' . esc_attr($options['mcab_field_text_color']) . '; background-color: ' . esc_attr($options['mcab_field_background_color']) . '; font-size: ' . esc_attr($options['mcab_field_text_size']) . ';">';
        echo $options['mcab_field_content'];
        echo '</div>';
    }
}

add_action('wp_head', 'mcab_display_announcement_bar');
