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
                        <?php foreach ($report[$current_tab] as $check) : ?>
                            <?php if (isset($check['debug'])) : ?>
                                <div class="notice notice-info">
                                    <p><strong>Debug Information:</strong></p>
                                    <p><?php echo wp_kses_post($check['debug']); ?></p>
                                </div>
                            <?php endif; ?>
                            <table class="widefat">
                                <thead>
                                    <tr>
                                        <th style="width: 100px;">Image</th>
                                        <th style="width: 100px;">Status</th>
                                        <th style="width: 200px;">Title</th>
                                        <th>Details</th>
                                        <th style="width: 250px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($check['details'])) : ?>
                                        <?php 
                                        // Sort images to prioritize failed items
                                        $sorted_images = $check['details'];
                                        usort($sorted_images, function($a, $b) {
                                            if ($a['status'] === $b['status']) return 0;
                                            return $a['status'] === 'failed' ? -1 : 1;
                                        });
                                        foreach ($sorted_images as $image) : 
                                        ?>
                                            <tr>
                                                <td>
                                                    <img src="<?php echo esc_url($image['url']); ?>" 
                                                         alt="" 
                                                         style="max-width: 100px; height: auto;">
                                                </td>
                                                <td>
                                                    <?php 
                                                    $status_icon = '';
                                                    $status_text = '';
                                                    switch ($image['status']) {
                                                        case 'failed':
                                                            $status_icon = '❌';
                                                            $status_text = 'Failed';
                                                            break;
                                                        case 'warning':
                                                            $status_icon = '⚠️';
                                                            $status_text = 'Warning';
                                                            break;
                                                        default:
                                                            $status_icon = '✅';
                                                            $status_text = 'Passed';
                                                    }
                                                    echo $status_icon . ' ' . $status_text;
                                                    ?>
                                                </td>
                                                <td><?php echo esc_html($image['title']); ?></td>
                                                <td>
                                                    <?php if (!empty($image['issues']) || !empty($image['warnings'])) : ?>
                                                        <div class="details-content">
                                                            <ul>
                                                                <?php 
                                                                // Show details first
                                                                if (!empty($image['details'])) {
                                                                    foreach ($image['details'] as $detail) {
                                                                        echo '<li class="detail-item">' . esc_html($detail) . '</li>';
                                                                    }
                                                                }
                                                                // Then show issues
                                                                if (!empty($image['issues'])) {
                                                                    foreach ($image['issues'] as $issue) {
                                                                        echo '<li class="issue-item">' . esc_html($issue) . '</li>';
                                                                    }
                                                                }
                                                                // Then show warnings
                                                                if (!empty($image['warnings'])) {
                                                                    foreach ($image['warnings'] as $warning) {
                                                                        echo '<li class="warning-item">' . esc_html($warning) . '</li>';
                                                                    }
                                                                }
                                                                ?>
                                                            </ul>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <a href="<?php echo esc_url($image['edit_url']); ?>" 
                                                           class="button button-small">
                                                            Edit Image
                                                        </a>
                                                        <?php 
                                                        // Check if any issue contains "Missing alt text"
                                                        $has_missing_alt = false;
                                                        foreach ($image['issues'] as $issue) {
                                                            if (strpos($issue, 'Missing alt text') !== false) {
                                                                $has_missing_alt = true;
                                                                break;
                                                            }
                                                        }
                                                        if ($has_missing_alt) : 
                                                        ?>
                                                            <div class="alt-text-editor">
                                                                <input type="text" 
                                                                       class="alt-text-input" 
                                                                       placeholder="Add alternative text..."
                                                                       data-image-id="<?php echo esc_attr($image['id']); ?>"
                                                                       data-nonce="<?php echo wp_create_nonce('wpa_inline_edit'); ?>">
                                                                <button type="button" 
                                                                        class="button button-small save-alt-text"
                                                                        data-image-id="<?php echo esc_attr($image['id']); ?>"
                                                                        data-nonce="<?php echo wp_create_nonce('wpa_inline_edit'); ?>">
                                                                    Save
                                                                </button>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else : ?>
                                        <tr>
                                            <td colspan="5"><?php echo esc_html($check['message']); ?></td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>

                            <script>
                            jQuery(document).ready(function($) {
                                // Create message container if it doesn't exist
                                if (!$('#wpa-message-container').length) {
                                    $('body').append('<div id="wpa-message-container"></div>');
                                }

                                function showMessage(message, type) {
                                    var messageHtml = $('<div class="wpa-message notice notice-' + type + '"><p>' + message + '</p></div>');
                                    $('#wpa-message-container').append(messageHtml);
                                    
                                    // Trigger reflow to enable animation
                                    messageHtml[0].offsetHeight;
                                    
                                    messageHtml.addClass('show');
                                    
                                    setTimeout(function() {
                                        messageHtml.removeClass('show');
                                        setTimeout(function() {
                                            messageHtml.remove();
                                        }, 300);
                                    }, type === 'success' ? 3000 : 5000);
                                }

                                $('.save-alt-text').on('click', function() {
                                    var button = $(this);
                                    var input = button.prev('.alt-text-input');
                                    var imageId = button.data('image-id');
                                    var nonce = button.data('nonce');
                                    var altText = input.val().trim();
                                    var row = button.closest('tr');
                                    var actionButtons = button.closest('.action-buttons');

                                    // Validate input
                                    if (!altText) {
                                        showMessage('Please enter alternative text', 'error');
                                        input.addClass('error').focus();
                                        return;
                                    }

                                    // Basic XSS prevention - remove any HTML tags
                                    altText = altText.replace(/<[^>]*>/g, '');

                                    // Disable input and button, show loading state
                                    input.prop('disabled', true)
                                         .removeClass('error');
                                    button.prop('disabled', true)
                                          .text('Saving...')
                                          .addClass('updating-message');

                                    $.post(ajaxurl, {
                                        action: 'wpa_save_alt_text',
                                        image_id: imageId,
                                        alt_text: altText,
                                        nonce: nonce
                                    }, function(response) {
                                        if (response.success) {
                                            // Show success message
                                            showMessage('Alt text updated successfully', 'success');

                                            // Remove the alt text editor
                                            actionButtons.find('.alt-text-editor').fadeOut(400, function() {
                                                $(this).remove();
                                            });
                                            
                                            // Check the image status after alt text update
                                            $.post(ajaxurl, {
                                                action: 'wpa_check_image_status',
                                                image_id: imageId,
                                                nonce: nonce
                                            }, function(statusResponse) {
                                                if (statusResponse.success) {
                                                    var detailsContent = row.find('.details-content');
                                                    var detailsList = detailsContent.find('ul');
                                                    
                                                    // Remove the "Missing alt text" item
                                                    detailsList.find('li:contains("Missing alt text")').remove();
                                                    
                                                    // Add any remaining issues or warnings
                                                    var allIssues = statusResponse.data.issues.concat(statusResponse.data.warnings);
                                                    if (allIssues.length > 0) {
                                                        // Update the list with remaining issues
                                                        detailsList.empty();
                                                        
                                                        // Add details first
                                                        if (statusResponse.data.details && statusResponse.data.details.length > 0) {
                                                            statusResponse.data.details.forEach(function(detail) {
                                                                detailsList.append('<li class="detail-item">' + detail + '</li>');
                                                            });
                                                        }
                                                        
                                                        // Then add issues and warnings
                                                        statusResponse.data.issues.forEach(function(issue) {
                                                            detailsList.append('<li class="issue-item">' + issue + '</li>');
                                                        });
                                                        statusResponse.data.warnings.forEach(function(warning) {
                                                            detailsList.append('<li class="warning-item">' + warning + '</li>');
                                                        });
                                                        
                                                        // Update status based on the response
                                                        var statusIcon = '';
                                                        var statusText = '';
                                                        switch (statusResponse.data.status) {
                                                            case 'failed':
                                                                statusIcon = '❌';
                                                                statusText = 'Failed';
                                                                break;
                                                            case 'warning':
                                                                statusIcon = '⚠️';
                                                                statusText = 'Warning';
                                                                break;
                                                            default:
                                                                statusIcon = '✅';
                                                                statusText = 'Passed';
                                                        }
                                                        row.find('td:nth-child(2)').html(statusIcon + ' ' + statusText);
                                                    } else {
                                                        // If no issues remain, remove the row
                                                        row.fadeOut(400, function() {
                                                            $(this).remove();
                                                            
                                                            // If no more rows, show the "all passed" message
                                                            if ($('tbody tr').length === 0) {
                                                                $('tbody').append(
                                                                    '<tr><td colspan="5">All images have proper metadata</td></tr>'
                                                                );
                                                            }
                                                        });
                                                    }
                                                }
                                            });
                                        } else {
                                            // Show error message
                                            showMessage('Failed to update alt text: ' + response.data, 'error');

                                            // Re-enable input and button
                                            input.prop('disabled', false);
                                            button.prop('disabled', false)
                                                  .text('Save')
                                                  .removeClass('updating-message');
                                        }
                                    }).fail(function() {
                                        // Handle AJAX failure
                                        showMessage('Failed to update alt text: Network error', 'error');

                                        // Re-enable input and button
                                        input.prop('disabled', false);
                                        button.prop('disabled', false)
                                              .text('Save')
                                              .removeClass('updating-message');
                                    });
                                });

                                // Remove error class on input
                                $('.alt-text-input').on('input', function() {
                                    $(this).removeClass('error');
                                });
                            });
                            </script>

                            <style>
                            .details-content {
                                background: #f8f8f8;
                                padding: 10px;
                                border: 1px solid #ddd;
                                border-radius: 3px;
                            }
                            .details-content ul {
                                margin: 0;
                                padding-left: 20px;
                                color: #666;
                                font-size: 0.9em;
                            }
                            .details-content ul li:last-child {
                                margin-bottom: 0;
                            }
                            .details-content ul .detail-item {
                                color: #666;
                                font-style: italic;
                            }
                            .details-content ul .issue-item {
                                color: #d63638;
                            }
                            .details-content ul .warning-item {
                                color: #dba617;
                            }
                            .action-buttons {
                                display: flex;
                                flex-direction: column;
                                gap: 5px;
                                align-items: flex-start;
                                width: 250px; /* Fixed width to match column */
                            }
                            .action-buttons .button {
                                width: auto;
                                min-width: 0;
                            }
                            .alt-text-editor {
                                display: flex;
                                gap: 5px;
                                margin-top: 5px;
                                width: 100%;
                            }
                            .alt-text-input {
                                flex: 1;
                                min-width: 0; /* Allow input to shrink */
                                max-width: 180px; /* Prevent input from being too wide */
                            }
                            .alt-text-input.error {
                                border-color: #dc3232;
                                box-shadow: 0 0 2px rgba(220, 50, 50, 0.8);
                            }
                            .updating-message {
                                opacity: 0.7;
                                cursor: not-allowed;
                            }
                            #wpa-message-container {
                                position: fixed;
                                top: 32px;
                                right: 20px;
                                z-index: 999999;
                                max-width: 400px;
                            }
                            .wpa-message {
                                margin: 0 0 10px 0;
                                padding: 10px 15px;
                                border-radius: 4px;
                                box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                                transform: translateX(120%);
                                transition: transform 0.3s ease-out;
                            }
                            .wpa-message.show {
                                transform: translateX(0);
                            }
                            .wpa-message.notice-success {
                                background: #f0f8f0;
                                border-left: 4px solid #46b450;
                            }
                            .wpa-message.notice-error {
                                background: #fef7f7;
                                border-left: 4px solid #dc3232;
                            }
                            /* Add styles for other tabs */
                            .widefat td.failed {
                                color: #d63638;
                            }
                            .widefat td.warning {
                                color: #dba617;
                            }
                            </style>
                        <?php endforeach; ?>
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
                                    // Define an order for sorting: failed > warning > passed
                                    $order = ['failed' => 1, 'warning' => 2, 'passed' => 3];

                                    // Get the effective type for sorting
                                    $type_a = isset($a['type']) ? $a['type'] : ($a['status'] ? 'passed' : 'failed');
                                    $type_b = isset($b['type']) ? $b['type'] : ($b['status'] ? 'passed' : 'failed');

                                    $order_a = isset($order[$type_a]) ? $order[$type_a] : 4;
                                    $order_b = isset($order[$type_b]) ? $order[$type_b] : 4;

                                    if ($order_a === $order_b) return 0;
                                    return ($order_a < $order_b) ? -1 : 1;
                                });
                                
                                foreach ($checks as $check) : 
                                    $action_link = self::get_action_link($current_tab, $check);

                                    // Determine status icon, text, and class based on status and type
                                    $status_icon = '';
                                    $status_text = '';
                                    $status_class = '';

                                    if ($check['status'] === true) {
                                        $status_icon = '✅';
                                        $status_text = 'Passed';
                                        $status_class = 'passed';
                                    } else {
                                        // Status is false, check the type
                                        switch (isset($check['type']) ? $check['type'] : 'failed') {
                                            case 'warning':
                                                $status_icon = '⚠️';
                                                $status_text = 'Warning';
                                                $status_class = 'warning';
                                                break;
                                            case 'failed':
                                            default:
                                                $status_icon = '❌';
                                                $status_text = 'Failed';
                                                $status_class = 'failed';
                                                break;
                                        }
                                    }
                                ?>
                                    <tr>
                                        <td><?php echo esc_html($check['label']); ?></td>
                                        <td class="<?php echo esc_attr($status_class); ?>"><?php echo $status_icon . ' ' . esc_html($status_text); ?></td>
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
                            'url' => admin_url('admin.php?page=wpseo_page_settings#/site-basics'),
                            'text' => 'Edit Yoast Settings'
                        ];
                    case 'Author Archives':
                        return [
                            'url' => admin_url('admin.php?page=wpseo_page_settings#/author-archives'),
                            'text' => 'Edit Yoast Settings'
                        ];
                    case 'Date Archives':
                        return [
                            'url' => admin_url('admin.php?page=wpseo_page_settings#/date-archives'),
                            'text' => 'Edit Yoast Settings'
                        ];
                    case 'Format Archives':
                        return [
                            'url' => admin_url('admin.php?page=wpseo_page_settings#/format-archives'),
                            'text' => 'Edit Yoast Settings'
                        ];
                    case 'Media Pages':
                        return [
                            'url' => admin_url('admin.php?page=wpseo_page_settings#/post-type/attachment'),
                            'text' => 'Edit Yoast Settings'
                        ];
                    case (strpos($check['label'], ' Search Appearance') !== false):
                        $post_type_slug = isset($check['slug']) ? $check['slug'] : '';
                        if (!empty($post_type_slug)) {
                            return [
                                'url' => admin_url('admin.php?page=wpseo_page_settings#/post-type/' . $post_type_slug),
                                'text' => 'Edit Yoast Settings'
                            ];
                        }
                        return [
                            'url' => admin_url('admin.php?page=wpseo_page_settings'),
                            'text' => 'Edit Yoast Settings'
                        ];
                }
                break;
        }
        
        // Return null if no specific action link is found
        return null;
    }
}