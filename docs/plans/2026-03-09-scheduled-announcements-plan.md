# Scheduled Announcements Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace the single announcement bar with multiple scheduled announcements, each with start/end dates and no overlapping date ranges.

**Architecture:** Store announcements as a serialized array in `wp_options` under `mcab_announcements`. Admin page gets a custom form (no Settings API) with a list table + add/edit form. Frontend checks current time against date ranges.

**Tech Stack:** PHP, WordPress Options API, HTML datetime-local inputs, inline JS for preview.

---

### Task 1: Migration helper + version bump

**Files:**
- Modify: `mortens-cool-announcement-bar.php`
- Modify: `readme.md`

**Step 1: Add migration function to main plugin file**

In `mortens-cool-announcement-bar.php`, before the includes, add a migration function that runs on every load. It checks if old `mcab_settings` exists and `mcab_announcements` does not, converts the old data into the new format, and deletes the old option.

```php
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
```

**Step 2: Bump version to 2.0.0**

In the plugin header, change `Version: 1.0.2` to `Version: 2.0.0`.

**Step 3: Update readme.md**

Add a line at the very top of `readme.md`:
```
Version: 2.0.0 - Now with scheduled announcements!

```

**Step 4: Commit**

```bash
git add mortens-cool-announcement-bar.php readme.md
git commit -m "feat: add migration helper and bump to v2.0.0"
```

---

### Task 2: Rewrite admin-page.php — data handling

**Files:**
- Rewrite: `admin-page.php`

**Step 1: Replace admin-page.php with new custom form handler**

Remove all the old Settings API code. The new admin page uses a custom form that posts to itself (not `options.php`). On form submit, it validates dates, checks for overlaps, and saves to `mcab_announcements`.

The top of the file handles form processing:

```php
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
    // Empty dates mean "always" — they overlap with everything
    if ($start1 === '' || $end1 === '' || $start2 === '' || $end2 === '') {
        return true;
    }
    return $start1 < $end2 && $start2 < $end1;
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

    return null;
}
```

**Step 2: Commit**

```bash
git add admin-page.php
git commit -m "feat: admin data handling for multiple scheduled announcements"
```

---

### Task 3: Rewrite admin-page.php — UI rendering

**Files:**
- Modify: `admin-page.php` (append the page content function)

**Step 1: Write the `mcab_settings_page_content` function**

This renders: error notices, the announcements table, and the add/edit form with live preview. Appended after the data handling code from Task 2.

Key UI elements:
- Table with columns: #, Content (truncated to 50 chars), Start Date, End Date, Status (active/scheduled/expired badge), Actions (Edit/Delete)
- Status is computed: if `now` is between start/end → "Active", if start is in future → "Scheduled", if end is in past → "Expired", if no dates → "Always On"
- Form with fields: content (textarea), text_color, background_color, text_size, custom_css (textarea), start_date (datetime-local), end_date (datetime-local)
- Hidden field for edit_index (empty = new, number = editing)
- Edit link populates form via JS
- Delete is a small form with POST
- Live preview div below form, updated by inline JS

```php
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
                        $status = 'No dates';
                        $badge_color = '#999';
                        if ($a['start_date'] && $a['end_date']) {
                            if ($now >= $a['start_date'] && $now < $a['end_date']) {
                                $status = 'Active';
                                $badge_color = '#46b450';
                            } elseif ($now < $a['start_date']) {
                                $status = 'Scheduled';
                                $badge_color = '#0073aa';
                            } else {
                                $status = 'Expired';
                                $badge_color = '#dc3232';
                            }
                        }
                    ?>
                        <tr>
                            <td><?= $i + 1; ?></td>
                            <td><?= esc_html(mb_strimwidth(wp_strip_all_tags($a['content']), 0, 50, '...')); ?></td>
                            <td><?= $a['start_date'] ? esc_html($a['start_date']) : '—'; ?></td>
                            <td><?= $a['end_date'] ? esc_html($a['end_date']) : '—'; ?></td>
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
                    <td><input type="datetime-local" id="mcab_end_date" name="mcab_end_date" value="<?= esc_attr($edit_data['end_date'] ?? ''); ?>"></td>
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
        });
    </script>
    <?php
}
```

**Step 2: Commit**

```bash
git add admin-page.php
git commit -m "feat: admin UI with announcement list, form, and preview"
```

---

### Task 4: Rewrite front-end-display.php

**Files:**
- Rewrite: `front-end-display.php`

**Step 1: Replace with time-based announcement lookup**

```php
<?php
function mcab_display_announcement_bar() {
    $announcements = get_option('mcab_announcements', []);
    if (!is_array($announcements) || empty($announcements)) {
        return;
    }

    $now = current_time('Y-m-d\TH:i');
    $active = null;

    foreach ($announcements as $a) {
        // No dates = always active (for migrated announcements)
        if ($a['start_date'] === '' || $a['end_date'] === '') {
            $active = $a;
            break;
        }
        if ($now >= $a['start_date'] && $now < $a['end_date']) {
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
```

**Step 2: Commit**

```bash
git add front-end-display.php
git commit -m "feat: frontend displays time-based active announcement"
```

---

### Task 5: Manual testing checklist

After all code is written, verify:

1. **Fresh install:** No `mcab_settings` or `mcab_announcements` in DB → admin page loads, empty list shown, can add announcement
2. **Migration:** Set `mcab_settings` manually in DB with old format → on page load, migrated to `mcab_announcements`, old option deleted
3. **Add announcement:** Fill form, set start in past and end in future → saves, shows as "Active" in table, appears on frontend
4. **Overlap rejection:** Try adding second announcement with overlapping dates → error message shown, not saved
5. **Edit:** Click edit on existing announcement → form populated, update works
6. **Delete:** Click delete → confirmation prompt, announcement removed
7. **Scheduling:** Add announcement with future start date → shows as "Scheduled", not on frontend. When time passes start → appears on frontend
8. **Expiry:** Add announcement with past end date → shows as "Expired", not on frontend
9. **No active:** All expired or future → no bar on frontend
10. **Preview:** Typing in form fields → live preview updates
