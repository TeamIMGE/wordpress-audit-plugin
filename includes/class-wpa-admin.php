<?php

class WPA_Admin {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_admin_pages']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    public static function add_admin_pages() {
        add_menu_page('Audit Plugin Settings', 'Audit Settings', 'manage_options', 'wpa-settings', [__CLASS__, 'settings_page']);
        add_submenu_page('wpa-settings', 'Audit Report', 'Audit Report', 'manage_options', 'wpa-report', [__CLASS__, 'report_page']);
    }

    public static function register_settings() {
        register_setting('wpa_settings_group', 'wpa_responsible_users');
    }

    public static function settings_page() {
        ?>
        <div class="wrap">
            <h1>WordPress Audit Plugin Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('wpa_settings_group');
                do_settings_sections('wpa_settings_group');
                $users = get_users(['role__in' => ['editor', 'administrator']]);
                $assigned = get_option('wpa_responsible_users', []);
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Responsible Users</th>
                        <td>
                            <?php foreach ($users as $user) : ?>
                                <label>
                                    <input type="checkbox" name="wpa_responsible_users[]" value="<?php echo esc_attr($user->ID); ?>" <?php checked(in_array($user->ID, $assigned)); ?> />
                                    <?php echo esc_html($user->display_name); ?>
                                </label><br>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public static function report_page() {
        $report = WPA_Auditor::run_all_checks();
        ?>
        <div class="wrap">
            <h1>Audit Report</h1>
            <table class="widefat">
                <thead><tr><th>Check</th><th>Status</th><th>Details</th></tr></thead>
                <tbody>
                    <?php foreach ($report as $item) : ?>
                        <tr>
                            <td><?php echo esc_html($item['label']); ?></td>
                            <td><?php echo $item['status'] ? '✅ Passed' : '❌ Failed'; ?></td>
                            <td><?php echo esc_html($item['message']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}