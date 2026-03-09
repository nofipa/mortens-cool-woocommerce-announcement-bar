<?php
function mcab_display_announcement_bar() {
    $announcements = get_option('mcab_announcements', []);
    if (!is_array($announcements)) {
        $announcements = [];
    }

    $now = current_time('Y-m-d\TH:i');
    $active = null;

    foreach ($announcements as $a) {
        if ($now >= $a['start_date'] && $now < $a['end_date']) {
            $active = $a;
            break;
        }
    }

    // Fall back to default announcement if no scheduled one is active
    if (!$active) {
        $default = get_option('mcab_default_announcement', []);
        if (empty($default['enabled'])) {
            return;
        }
        $active = $default;
    }

    if (!empty($active['custom_css'])) {
        echo '<style>' . esc_html($active['custom_css']) . '</style>';
    }

    echo '<div id="mortens-cool-announcement-bar" style="text-align: center; color: '
        . esc_attr($active['text_color']) . '; background-color: '
        . esc_attr($active['background_color']) . '; font-size: '
        . esc_attr($active['text_size']) . ';">';
    echo wp_kses_post($active['content']);
    echo '</div>';

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

add_action('wp_head', 'mcab_display_announcement_bar');
