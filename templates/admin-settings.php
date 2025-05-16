<?php
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <form method="post" action="options.php">
        <?php
        settings_fields('mfw_settings');
        do_settings_sections('mfw_settings');
        ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php _e('Enable API', 'mfw'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="mfw_api_enabled" value="yes" 
                            <?php checked(get_option('mfw_api_enabled'), 'yes'); ?>>
                        <?php _e('Enable API functionality', 'mfw'); ?>
                    </label>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Default Provider', 'mfw'); ?></th>
                <td>
                    <select name="mfw_default_provider">
                        <option value="gemini" <?php selected(get_option('mfw_default_provider'), 'gemini'); ?>>
                            <?php _e('Google Gemini', 'mfw'); ?>
                        </option>
                        <option value="openai" <?php selected(get_option('mfw_default_provider'), 'openai'); ?>>
                            <?php _e('OpenAI', 'mfw'); ?>
                        </option>
                    </select>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('OpenAI API Key', 'mfw'); ?></th>
                <td>
                    <input type="password" name="mfw_openai_api_key" class="regular-text"
                        value="<?php echo esc_attr(get_option('mfw_openai_api_key')); ?>">
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Gemini API Key', 'mfw'); ?></th>
                <td>
                    <input type="password" name="mfw_gemini_api_key" class="regular-text"
                        value="<?php echo esc_attr(get_option('mfw_gemini_api_key')); ?>">
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>
</div>