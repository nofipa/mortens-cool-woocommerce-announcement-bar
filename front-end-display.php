<?php
function mcab_display_announcement_bar()
{
    $options = get_option('mcab_settings');
    // Check if 'mcab_field_enable' is set and true
    if (isset($options['mcab_field_enable']) && $options['mcab_field_enable']) {
        echo '<style>' . esc_html($options['mcab_field_custom_css']) . '</style>';
        echo '<div id="mortens-cool-announcement-bar" style="text-align: center; color: ' . esc_attr($options['mcab_field_text_color']) . '; background-color: ' . esc_attr($options['mcab_field_background_color']) . '; font-size: ' . esc_attr($options['mcab_field_text_size']) . ';">';
        echo $options['mcab_field_content'];
        echo '</div>';

        // Add JavaScript to check .site_header_wrap and adjust its parent if needed
        echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                const headerWrap = document.querySelector(".site_header_wrap");
                if (headerWrap && window.getComputedStyle(headerWrap).position === "absolute") {
                    const parent = headerWrap.parentElement;
                    if (parent) {
                        parent.style.position = "relative";
                    }
                }
            });
        </script>';
    }
}

add_action('wp_head', 'mcab_display_announcement_bar');
