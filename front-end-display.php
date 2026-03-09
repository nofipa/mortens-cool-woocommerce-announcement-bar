<?php
function mcab_display_announcement_bar() {
    $announcements = get_option('mcab_announcements', []);
    if (!is_array($announcements) || empty($announcements)) {
        return;
    }

    $now = current_time('Y-m-d\TH:i');
    $active = null;

    foreach ($announcements as $a) {
        $s = $a['start_date'] ?: '0000-00-00T00:00';
        $e = $a['end_date'] ?: '9999-12-31T23:59';
        if ($now >= $s && $now < $e) {
            $active = $a;
            break;
        }
    }

    if (!$active) {
        return;
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
