<?php
function mcab_add_admin_menu()
{
    add_menu_page('Mortens Cool Announcement Bar', 'Announcement Bar', 'manage_options', 'mcab-settings-page', 'mcab_settings_page_content', 'dashicons-megaphone', 6);
}
add_action('admin_menu', 'mcab_add_admin_menu');

function mcab_settings_page_content()
{
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }

    // Your settings page content goes here
?>
    <div class="wrap">
        <h1><?= esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            // Output security fields for the registered setting
            settings_fields('mcab');
            // Output setting sections and their fields
            do_settings_sections('mcab-settings-page');
            // Output save settings button
            submit_button('Save Settings');
            ?>
        </form>
    </div>

    <div id="mcab-preview-container" style="margin-top: 20px;">
        <h2>Preview</h2>
        <div id="mcab-preview" style="padding: 10px; text-align: center; border: 1px solid #ddd;">
            <!-- Live preview will be displayed here -->
        </div>
    </div>

    <?php
    $readme_path = plugin_dir_path(__FILE__) . 'README.md';
    if (file_exists($readme_path)) {
        $readme_content = file_get_contents($readme_path);
        echo '<div class="readme-container" style="margin: 40px 20px 20px 20px">';
        echo '<h2 class="text-xl">Tutorial / how to</h2>';
        echo '<div class="mcab-readme bg-white text-center p-5 rounded-lg" style="margin: 20px;">';
        echo nl2br($readme_content); // Convert newlines to <br> for HTML display
        echo '</div>';
        echo '</div>';
    }
    ?>

    <script type="text/javascript">
        // Adding Tailwind CSS CDN
        const tailwindCssLink = document.createElement('link');
        tailwindCssLink.href = 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css';
        tailwindCssLink.rel = 'stylesheet';
        document.head.appendChild(tailwindCssLink);

        document.addEventListener('DOMContentLoaded', function() {
            const updatePreview = () => {
                let content = document.querySelector('[name="mcab_settings[mcab_field_content]"]').value;
                let textColor = document.querySelector('[name="mcab_settings[mcab_field_text_color]"]').value;
                let bgColor = document.querySelector('[name="mcab_settings[mcab_field_background_color]"]').value;
                let textSize = document.querySelector('[name="mcab_settings[mcab_field_text_size]"]').value;

                let preview = document.getElementById('mcab-preview');
                preview.innerHTML = content;
                preview.style.color = textColor;
                preview.style.backgroundColor = bgColor;
                preview.style.fontSize = textSize;
            };

            // Update preview on input change
            document.querySelectorAll('[name^="mcab_settings"]').forEach(input => {
                input.addEventListener('input', updatePreview);
            });

            // Initial preview update
            updatePreview();
        });
    </script>
<?php
}

function mcab_settings_init()
{
    register_setting('mcab', 'mcab_settings');

    add_settings_section(
        'mcab_section_developers',
        __('Settings', 'mcab'),
        'mcab_section_developers_callback',
        'mcab-settings-page'
    );

    // Fields for content, text color, background color, text size, enable/disable
    add_settings_field('mcab_field_content', __('Announcement Content', 'mcab'), 'mcab_field_content_render', 'mcab-settings-page', 'mcab_section_developers');
    add_settings_field('mcab_field_text_color', __('Text Color', 'mcab'), 'mcab_field_text_color_render', 'mcab-settings-page', 'mcab_section_developers');
    add_settings_field('mcab_field_background_color', __('Background Color', 'mcab'), 'mcab_field_background_color_render', 'mcab-settings-page', 'mcab_section_developers');
    add_settings_field('mcab_field_text_size', __('Text Size', 'mcab'), 'mcab_field_text_size_render', 'mcab-settings-page', 'mcab_section_developers');
    add_settings_field('mcab_field_custom_css', __('Custom CSS', 'mcab'), 'mcab_field_custom_css_render', 'mcab-settings-page', 'mcab_section_developers');
    add_settings_field('mcab_field_enable', __('Enable Announcement Bar', 'mcab'), 'mcab_field_enable_render', 'mcab-settings-page', 'mcab_section_developers');
}

function mcab_section_developers_callback()
{
    echo __('Set the parameters for the announcement bar.', 'mcab');
}

function mcab_field_content_render()
{
    $options = get_option('mcab_settings');
?>
    <textarea cols="40" rows="5" name="mcab_settings[mcab_field_content]"><?= $options['mcab_field_content']; ?></textarea>
<?php
}

function mcab_field_text_color_render()
{
    $options = get_option('mcab_settings');
?>
    <input type='text' name='mcab_settings[mcab_field_text_color]' value='<?= isset($options['mcab_field_text_color']) ? $options['mcab_field_text_color'] : ''; ?>'>
    <p class="description"><?php _e('Enter the text color in hexadecimal format (e.g., #ffffff for white).', 'mcab'); ?></p>
<?php
}

function mcab_field_background_color_render()
{
    $options = get_option('mcab_settings');
?>
    <input type='text' name='mcab_settings[mcab_field_background_color]' value='<?= isset($options['mcab_field_background_color']) ? $options['mcab_field_background_color'] : ''; ?>'>
    <p class="description"><?php _e('Enter the background color in hexadecimal format (e.g., #000000 for black).', 'mcab'); ?></p>
<?php
}

function mcab_field_text_size_render()
{
    $options = get_option('mcab_settings');
?>
    <input type='text' name='mcab_settings[mcab_field_text_size]' value='<?= isset($options['mcab_field_text_size']) ? $options['mcab_field_text_size'] : ''; ?>'>
    <p class="description"><?php _e('Enter the text size in pixels (e.g., 16px).', 'mcab'); ?></p>
<?php
}

function mcab_field_custom_css_render()
{
    $options = get_option('mcab_settings');
?>
    <textarea cols="40" rows="5" name="mcab_settings[mcab_field_custom_css]"><?= isset($options['mcab_field_custom_css']) ? $options['mcab_field_custom_css'] : ''; ?></textarea>
    <p class="description"><?php _e('Enter your custom CSS code here. The div has the id #mortens-cool-announcement-bar', 'mcab'); ?></p>
<?php
}

function mcab_field_enable_render()
{
    $options = get_option('mcab_settings');
?>
    <input type='checkbox' name='mcab_settings[mcab_field_enable]' <?= isset($options['mcab_field_enable']) && $options['mcab_field_enable'] ? 'checked' : ''; ?>>
    <p class="description"><?php _e('Check to enable the announcement bar.', 'mcab'); ?></p>
<?php
}



// Similar render functions for other fields...

add_action('admin_init', 'mcab_settings_init');
