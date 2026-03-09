<?php
function mcab_add_admin_menu() {
    add_menu_page(
        'Mortens Cool Announcement Bar',
        'Announcement Bar',
        'manage_options',
        'mcab-settings-page',
        'mcab_settings_page_content',
        'dashicons-megaphone',
        6
    );
}
add_action('admin_menu', 'mcab_add_admin_menu');

function mcab_get_announcements() {
    $announcements = get_option('mcab_announcements', []);
    if (!is_array($announcements)) {
        return [];
    }
    return $announcements;
}

function mcab_dates_overlap($start1, $end1, $start2, $end2) {
    // Empty start = beginning of time, empty end = forever
    $s1 = $start1 ?: '0000-00-00T00:00';
    $e1 = $end1 ?: '9999-12-31T23:59';
    $s2 = $start2 ?: '0000-00-00T00:00';
    $e2 = $end2 ?: '9999-12-31T23:59';
    return $s1 < $e2 && $s2 < $e1;
}

function mcab_handle_form_submission() {
    if (!isset($_POST['mcab_action'])) {
        return null;
    }
    if (!current_user_can('manage_options')) {
        return null;
    }
    if (!wp_verify_nonce($_POST['mcab_nonce'], 'mcab_save_announcement')) {
        return 'Security check failed.';
    }

    $announcements = mcab_get_announcements();
    $action = sanitize_text_field($_POST['mcab_action']);

    if ($action === 'delete' && isset($_POST['mcab_delete_index'])) {
        $index = intval($_POST['mcab_delete_index']);
        if (isset($announcements[$index])) {
            array_splice($announcements, $index, 1);
            update_option('mcab_announcements', $announcements);
        }
        return null;
    }

    if ($action === 'save') {
        $entry = [
            'content'          => wp_kses_post($_POST['mcab_content'] ?? ''),
            'text_color'       => sanitize_hex_color($_POST['mcab_text_color'] ?? '#ffffff') ?: '#ffffff',
            'background_color' => sanitize_hex_color($_POST['mcab_background_color'] ?? '#000000') ?: '#000000',
            'text_size'        => sanitize_text_field($_POST['mcab_text_size'] ?? '16px'),
            'custom_css'       => sanitize_textarea_field($_POST['mcab_custom_css'] ?? ''),
            'start_date'       => sanitize_text_field($_POST['mcab_start_date'] ?? ''),
            'end_date'         => sanitize_text_field($_POST['mcab_end_date'] ?? ''),
        ];

        // Validate: end must be after start
        if ($entry['start_date'] && $entry['end_date'] && $entry['start_date'] >= $entry['end_date']) {
            return 'End date must be after start date.';
        }

        $edit_index = isset($_POST['mcab_edit_index']) && $_POST['mcab_edit_index'] !== '' ? intval($_POST['mcab_edit_index']) : -1;

        // Check for overlaps with other announcements
        foreach ($announcements as $i => $existing) {
            if ($i === $edit_index) continue;
            if (mcab_dates_overlap(
                $entry['start_date'], $entry['end_date'],
                $existing['start_date'], $existing['end_date']
            )) {
                return 'This announcement overlaps with an existing one. Adjust the dates so no two announcements are active at the same time.';
            }
        }

        if ($edit_index >= 0 && isset($announcements[$edit_index])) {
            $announcements[$edit_index] = $entry;
        } else {
            $announcements[] = $entry;
        }

        // Sort by start_date
        usort($announcements, function($a, $b) {
            if ($a['start_date'] === '') return 1;
            if ($b['start_date'] === '') return -1;
            return strcmp($a['start_date'], $b['start_date']);
        });

        update_option('mcab_announcements', $announcements);
        return null;
    }

    if ($action === 'save_default') {
        $default = [
            'enabled'          => isset($_POST['mcab_default_enabled']) ? 1 : 0,
            'content'          => wp_kses_post($_POST['mcab_default_content'] ?? ''),
            'text_color'       => sanitize_hex_color($_POST['mcab_default_text_color'] ?? '#ffffff') ?: '#ffffff',
            'background_color' => sanitize_hex_color($_POST['mcab_default_background_color'] ?? '#000000') ?: '#000000',
            'text_size'        => sanitize_text_field($_POST['mcab_default_text_size'] ?? '16px'),
            'custom_css'       => sanitize_textarea_field($_POST['mcab_default_custom_css'] ?? ''),
        ];
        update_option('mcab_default_announcement', $default);
        return null;
    }

    return null;
}

function mcab_settings_page_content() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $error = mcab_handle_form_submission();
    $announcements = mcab_get_announcements();
    $now = current_time('Y-m-d\TH:i');
    $editing = isset($_GET['edit']) ? intval($_GET['edit']) : -1;
    $edit_data = ($editing >= 0 && isset($announcements[$editing])) ? $announcements[$editing] : null;
    ?>
    <div class="wrap">
        <h1>Announcement Bar</h1>

        <?php if ($error): ?>
            <div class="notice notice-error"><p><?= esc_html($error); ?></p></div>
        <?php endif; ?>

        <h2>Scheduled Announcements</h2>

        <?php if (empty($announcements)): ?>
            <p>No announcements yet. Add one below.</p>
        <?php else: ?>
            <table class="widefat striped" style="max-width: 900px;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Content</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($announcements as $i => $a):
                        $s = $a['start_date'] ?: '0000-00-00T00:00';
                        $e = $a['end_date'] ?: '9999-12-31T23:59';
                        if ($now >= $s && $now < $e) {
                            $status = 'Active';
                            $badge_color = '#46b450';
                        } elseif ($now < $s) {
                            $status = 'Scheduled';
                            $badge_color = '#0073aa';
                        } else {
                            $status = 'Expired';
                            $badge_color = '#dc3232';
                        }
                    ?>
                        <tr>
                            <td><?= $i + 1; ?></td>
                            <td><?= esc_html(mb_strimwidth(wp_strip_all_tags($a['content']), 0, 50, '...')); ?></td>
                            <td><?= $a['start_date'] ? esc_html($a['start_date']) : '—'; ?></td>
                            <td><?= $a['end_date'] ? esc_html($a['end_date']) : 'Indefinite'; ?></td>
                            <td><span style="color: #fff; background: <?= $badge_color; ?>; padding: 2px 8px; border-radius: 3px; font-size: 12px;"><?= $status; ?></span></td>
                            <td>
                                <a href="<?= admin_url('admin.php?page=mcab-settings-page&edit=' . $i); ?>">Edit</a>
                                |
                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this announcement?');">
                                    <?php wp_nonce_field('mcab_save_announcement', 'mcab_nonce'); ?>
                                    <input type="hidden" name="mcab_action" value="delete">
                                    <input type="hidden" name="mcab_delete_index" value="<?= $i; ?>">
                                    <button type="submit" style="background:none;border:none;color:#a00;cursor:pointer;padding:0;">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <h2 style="margin-top: 30px;"><?= $edit_data ? 'Edit Announcement' : 'Add New Announcement'; ?></h2>

        <form method="post" style="max-width: 600px;">
            <?php wp_nonce_field('mcab_save_announcement', 'mcab_nonce'); ?>
            <input type="hidden" name="mcab_action" value="save">
            <input type="hidden" name="mcab_edit_index" value="<?= $edit_data ? $editing : ''; ?>">

            <table class="form-table">
                <tr>
                    <th><label for="mcab_content">Content</label></th>
                    <td><textarea id="mcab_content" name="mcab_content" cols="40" rows="5"><?= esc_textarea($edit_data['content'] ?? ''); ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="mcab_text_color">Text Color</label></th>
                    <td>
                        <input type="text" id="mcab_text_color" name="mcab_text_color" value="<?= esc_attr($edit_data['text_color'] ?? '#ffffff'); ?>">
                        <p class="description">Hex format, e.g. #ffffff</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="mcab_background_color">Background Color</label></th>
                    <td>
                        <input type="text" id="mcab_background_color" name="mcab_background_color" value="<?= esc_attr($edit_data['background_color'] ?? '#000000'); ?>">
                        <p class="description">Hex format, e.g. #000000</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="mcab_text_size">Text Size</label></th>
                    <td>
                        <input type="text" id="mcab_text_size" name="mcab_text_size" value="<?= esc_attr($edit_data['text_size'] ?? '16px'); ?>">
                        <p class="description">E.g. 16px</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="mcab_custom_css">Custom CSS</label></th>
                    <td>
                        <textarea id="mcab_custom_css" name="mcab_custom_css" cols="40" rows="5"><?= esc_textarea($edit_data['custom_css'] ?? ''); ?></textarea>
                        <p class="description">Target: #mortens-cool-announcement-bar</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="mcab_start_date">Start Date</label></th>
                    <td><input type="datetime-local" id="mcab_start_date" name="mcab_start_date" value="<?= esc_attr($edit_data['start_date'] ?? ''); ?>"></td>
                </tr>
                <tr>
                    <th><label for="mcab_end_date">End Date</label></th>
                    <td>
                        <input type="datetime-local" id="mcab_end_date" name="mcab_end_date" value="<?= esc_attr($edit_data['end_date'] ?? ''); ?>">
                        <p class="description">Leave blank for indefinite</p>
                    </td>
                </tr>
            </table>

            <?php submit_button($edit_data ? 'Update Announcement' : 'Add Announcement'); ?>
            <?php if ($edit_data): ?>
                <a href="<?= admin_url('admin.php?page=mcab-settings-page'); ?>">Cancel editing</a>
            <?php endif; ?>
        </form>

        <div id="mcab-preview-container" style="margin-top: 20px; max-width: 900px;">
            <h2>Preview</h2>
            <div id="mcab-preview" style="padding: 10px; text-align: center; border: 1px solid #ddd;">
            </div>
        </div>

        <hr style="margin-top: 40px;">

        <?php $default = get_option('mcab_default_announcement', []); ?>
        <h2 style="margin-top: 30px;">Default Announcement</h2>
        <p class="description">Shown when no scheduled announcement is active.</p>

        <form method="post" style="max-width: 600px;">
            <?php wp_nonce_field('mcab_save_announcement', 'mcab_nonce'); ?>
            <input type="hidden" name="mcab_action" value="save_default">

            <table class="form-table">
                <tr>
                    <th><label for="mcab_default_enabled">Enabled</label></th>
                    <td><input type="checkbox" id="mcab_default_enabled" name="mcab_default_enabled" <?= !empty($default['enabled']) ? 'checked' : ''; ?>></td>
                </tr>
                <tr>
                    <th><label for="mcab_default_content">Content</label></th>
                    <td><textarea id="mcab_default_content" name="mcab_default_content" cols="40" rows="5"><?= esc_textarea($default['content'] ?? ''); ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="mcab_default_text_color">Text Color</label></th>
                    <td>
                        <input type="text" id="mcab_default_text_color" name="mcab_default_text_color" value="<?= esc_attr($default['text_color'] ?? '#ffffff'); ?>">
                        <p class="description">Hex format, e.g. #ffffff</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="mcab_default_background_color">Background Color</label></th>
                    <td>
                        <input type="text" id="mcab_default_background_color" name="mcab_default_background_color" value="<?= esc_attr($default['background_color'] ?? '#000000'); ?>">
                        <p class="description">Hex format, e.g. #000000</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="mcab_default_text_size">Text Size</label></th>
                    <td>
                        <input type="text" id="mcab_default_text_size" name="mcab_default_text_size" value="<?= esc_attr($default['text_size'] ?? '16px'); ?>">
                        <p class="description">E.g. 16px</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="mcab_default_custom_css">Custom CSS</label></th>
                    <td>
                        <textarea id="mcab_default_custom_css" name="mcab_default_custom_css" cols="40" rows="5"><?= esc_textarea($default['custom_css'] ?? ''); ?></textarea>
                        <p class="description">Target: #mortens-cool-announcement-bar</p>
                    </td>
                </tr>
            </table>

            <?php submit_button('Save Default Announcement'); ?>
        </form>

        <div id="mcab-default-preview-container" style="margin-top: 20px; max-width: 900px;">
            <h2>Default Preview</h2>
            <div id="mcab-default-preview" style="padding: 10px; text-align: center; border: 1px solid #ddd;">
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const updatePreview = () => {
                const content = document.getElementById('mcab_content').value;
                const textColor = document.getElementById('mcab_text_color').value;
                const bgColor = document.getElementById('mcab_background_color').value;
                const textSize = document.getElementById('mcab_text_size').value;
                const preview = document.getElementById('mcab-preview');
                preview.innerHTML = content;
                preview.style.color = textColor;
                preview.style.backgroundColor = bgColor;
                preview.style.fontSize = textSize;
            };
            document.querySelectorAll('#mcab_content, #mcab_text_color, #mcab_background_color, #mcab_text_size').forEach(el => {
                el.addEventListener('input', updatePreview);
            });
            updatePreview();

            const updateDefaultPreview = () => {
                const content = document.getElementById('mcab_default_content').value;
                const textColor = document.getElementById('mcab_default_text_color').value;
                const bgColor = document.getElementById('mcab_default_background_color').value;
                const textSize = document.getElementById('mcab_default_text_size').value;
                const preview = document.getElementById('mcab-default-preview');
                preview.innerHTML = content;
                preview.style.color = textColor;
                preview.style.backgroundColor = bgColor;
                preview.style.fontSize = textSize;
            };
            document.querySelectorAll('#mcab_default_content, #mcab_default_text_color, #mcab_default_background_color, #mcab_default_text_size').forEach(el => {
                el.addEventListener('input', updateDefaultPreview);
            });
            updateDefaultPreview();
        });
    </script>
    <?php
}
