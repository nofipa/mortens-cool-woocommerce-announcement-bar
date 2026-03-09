<?php

/**
 * Plugin Name: Mortens Cool Announcement Bar
 * Description: A WooCommerce plugin to create an announcement bar.
 * Version: 2.0.0
 * Author: Voldemorten 🧙‍♂️
 */

function mcab_maybe_migrate() {
    $old = get_option('mcab_settings');
    $new = get_option('mcab_announcements');

    if ($old && $new === false) {
        $migrated = [];
        if (!empty($old['mcab_field_enable'])) {
            $migrated[] = [
                'content'          => $old['mcab_field_content'] ?? '',
                'text_color'       => $old['mcab_field_text_color'] ?? '#ffffff',
                'background_color' => $old['mcab_field_background_color'] ?? '#000000',
                'text_size'        => $old['mcab_field_text_size'] ?? '16px',
                'custom_css'       => $old['mcab_field_custom_css'] ?? '',
                'start_date'       => '',
                'end_date'         => '',
            ];
        }
        update_option('mcab_announcements', $migrated);
        delete_option('mcab_settings');
    }
}
mcab_maybe_migrate();

// Include admin page settings.
include_once plugin_dir_path(__FILE__) . 'admin-page.php';

// Include front-end display.
include_once plugin_dir_path(__FILE__) . 'front-end-display.php';
