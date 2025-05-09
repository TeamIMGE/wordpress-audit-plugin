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
                $assigned = (array) get_option('wpa_responsible_users', []);
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
        $categories = WPA_Auditor::get_categories();
        $current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'wordpress';
        ?>
        <div class="wrap">
            <h1>Audit Report</h1>
            
            <nav class="nav-tab-wrapper">
                <?php foreach ($categories as $key => $label) : ?>
                    <a href="?page=wpa-report&tab=<?php echo esc_attr($key); ?>" 
                       class="nav-tab <?php echo $current_tab === $key ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($label); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="tab-content">
                <?php if (isset($report[$current_tab])) : ?>
                    <?php if ($current_tab === 'images') : ?>
                        <table class="widefat">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Title</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report[$current_tab] as $check) : ?>
                                    <?php if (!empty($check['details'])) : ?>
                                        <?php foreach ($check['details'] as $image) : ?>
                                            <tr>
                                                <td>
                                                    <img src="<?php echo esc_url($image['url']); ?>" 
                                                         alt="" 
                                                         style="max-width: 100px; height: auto;">
                                                </td>
                                                <td><?php echo esc_html($image['title']); ?></td>
                                                <td>
                                                    <a href="<?php echo esc_url(admin_url('post.php?post=' . $image['id'] . '&action=edit')); ?>" 
                                                       class="button button-small">
                                                        Edit Image
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else : ?>
                                        <tr>
                                            <td colspan="3"><?php echo esc_html($check['message']); ?></td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <table class="widefat">
                            <thead>
                                <tr>
                                    <th>Check</th>
                                    <th>Status</th>
                                    <th>Details</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                // Sort checks to show failed items first
                                $checks = $report[$current_tab];
                                usort($checks, function($a, $b) {
                                    if ($a['status'] === $b['status']) return 0;
                                    return $a['status'] ? 1 : -1;
                                });
                                
                                foreach ($checks as $check) : 
                                    $action_link = self::get_action_link($current_tab, $check);
                                ?>
                                    <tr>
                                        <td><?php echo esc_html($check['label']); ?></td>
                                        <td><?php echo $check['status'] ? '✅ Passed' : '❌ Failed'; ?></td>
                                        <td><?php echo esc_html($check['message']); ?></td>
                                        <td>
                                            <?php if (!$check['status'] && $action_link) : ?>
                                                <?php if (isset($action_link['inline_edit'])) : ?>
                                                    <button type="button" 
                                                            class="button button-small edit-inline" 
                                                            data-setting="<?php echo esc_attr($action_link['setting']); ?>"
                                                            data-nonce="<?php echo wp_create_nonce('wpa_inline_edit'); ?>">
                                                        Edit
                                                    </button>
                                                <?php else : ?>
                                                    <a href="<?php echo esc_url($action_link['url']); ?>" 
                                                       class="button button-small">
                                                        <?php echo esc_html($action_link['text']); ?>
                                                    </a>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('.edit-inline').on('click', function() {
                var button = $(this);
                var setting = button.data('setting');
                var nonce = button.data('nonce');
                
                // Create modal for inline editing
                var modal = $('<div class="wpa-modal">' +
                    '<div class="wpa-modal-content">' +
                    '<h2>Edit Setting</h2>' +
                    '<form method="post">' +
                    '<input type="hidden" name="wpa_setting" value="' + setting + '">' +
                    '<input type="hidden" name="wpa_nonce" value="' + nonce + '">' +
                    '<div class="wpa-form-field"></div>' +
                    '<div class="wpa-modal-actions">' +
                    '<button type="submit" class="button button-primary">Save</button>' +
                    '<button type="button" class="button wpa-modal-close">Cancel</button>' +
                    '</div>' +
                    '</form>' +
                    '</div>' +
                    '</div>');
                
                $('body').append(modal);
                
                // Load form content via AJAX
                $.post(ajaxurl, {
                    action: 'wpa_load_inline_edit',
                    setting: setting,
                    nonce: nonce
                }, function(response) {
                    modal.find('.wpa-form-field').html(response);
                });
            });
            
            $(document).on('click', '.wpa-modal-close', function() {
                $('.wpa-modal').remove();
            });
        });
        </script>

        <style>
        .wpa-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            z-index: 160000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .wpa-modal-content {
            background: #fff;
            padding: 20px;
            border-radius: 4px;
            max-width: 500px;
            width: 100%;
        }
        .wpa-modal-actions {
            margin-top: 20px;
            text-align: right;
        }
        .wpa-modal-actions .button {
            margin-left: 10px;
        }
        </style>
        <?php
    }

    private static function get_action_link($category, $check) {
        switch ($category) {
            case 'wordpress':
                switch ($check['label']) {
                    case 'Search Engine Visibility':
                        return [
                            'url' => admin_url('options-reading.php'),
                            'text' => 'Edit Visibility'
                        ];
                    case 'Site Title':
                        return [
                            'inline_edit' => true,
                            'setting' => 'blogname'
                        ];
                }
                break;
            
            case 'yoast':
                if (!class_exists('WPSEO_Options')) {
                    return [
                        'url' => admin_url('plugin-install.php?s=yoast+seo&tab=search&type=term'),
                        'text' => 'Install Yoast SEO'
                    ];
                }
                
                switch ($check['label']) {
                    case 'Homepage Title Template':
                    case 'Default Social Image':
                        return [
                            'url' => admin_url('admin.php?page=wpseo_titles'),
                            'text' => 'Edit Yoast Settings'
                        ];
                }
                break;
        }
        
        return null;
    }
}