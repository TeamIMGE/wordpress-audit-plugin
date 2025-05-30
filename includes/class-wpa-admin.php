<?php

class WPA_Admin {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_admin_pages']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
    }

    public static function add_admin_pages() {
        add_menu_page('Audit Plugin Settings', 'Audit Settings', 'manage_options', 'wpa-settings', [__CLASS__, 'display_settings_page_with_tabs']);
        add_submenu_page('wpa-settings', 'Audit Report', 'Audit Report', 'manage_options', 'wpa-report', [__CLASS__, 'report_page']);
    }

    public static function register_settings() {
        register_setting('wpa_settings_group', 'wpa_responsible_users');
        register_setting(
            'wpa_settings_group',
            'wpa_aws_settings',
            [__CLASS__, 'sanitize_aws_settings']
        );
    }

    public static function sanitize_aws_settings($input) {
        $output = get_option('wpa_aws_settings', []);

        if (isset($input['region'])) {
            $output['region'] = sanitize_text_field($input['region']);
        }

        if (isset($input['access_key_id'])) {
            if (empty(trim($input['access_key_id']))) {
                unset($output['access_key_id']);
            } else {
                $output['access_key_id'] = sanitize_text_field($input['access_key_id']);
            }
        }

        if (isset($input['secret_access_key'])) {
            // Check if the input is the masked string '********'
            // If it is, the user didn't change it, so keep the existing encrypted value
            if ($input['secret_access_key'] !== '********') {
                if (empty(trim($input['secret_access_key']))) {
                    // If input is empty after trim and it wasn't the masked value, remove the stored key
                    unset($output['secret_access_key']);
                } else {
                    // Only encrypt and save if the input is not the masked value AND is not empty
                    $encryption_key = wpa_get_encryption_key();

                    if (false === $encryption_key) {
                        add_settings_error(
                            'wpa_aws_settings',
                            'wpa_encryption_key_error',
                            'Encryption key not found in database. AWS Secret Access Key was NOT saved securely. Please try deactivating and reactivating the plugin.',
                            'error'
                        );
                        unset($output['secret_access_key']); // Ensure it's not saved if encryption fails
                    } else {
                        $encrypted_key = self::encrypt_data(sanitize_text_field($input['secret_access_key']), $encryption_key);
                        if (is_wp_error($encrypted_key)) {
                            add_settings_error(
                                'wpa_aws_settings',
                                'wpa_encryption_failed',
                                'Failed to encrypt AWS Secret Access Key: ' . $encrypted_key->get_error_message(),
                                'error'
                            );
                            unset($output['secret_access_key']); // Ensure it's not saved if encryption fails
                        } else {
                            $output['secret_access_key'] = $encrypted_key;
                        }
                    }
                }
            }
             // If $input['secret_access_key'] was '********', the key is not updated, $output retains the old value
        }

        return $output;
    }

    public static function display_settings_page_with_tabs() {
        $current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';

        $settings_tabs = [
            'general' => 'General Settings',
            'aws_bedrock' => 'AWS Bedrock Settings',
        ];

        ?>
        <div class="wrap">
            <h1>WordPress Audit Plugin Settings</h1>

            <nav class="nav-tab-wrapper">
                <?php foreach ($settings_tabs as $tab_key => $tab_label) : ?>
                    <a href="?page=wpa-settings&tab=<?php echo esc_attr($tab_key); ?>"
                       class="nav-tab <?php echo $current_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($tab_label); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <form method="post" action="options.php">
                <?php
                settings_fields('wpa_settings_group');

                switch ($current_tab) {
                    case 'general':
                        self::render_general_settings_tab();
                        break;
                    case 'aws_bedrock':
                        self::render_aws_bedrock_settings_tab();
                        break;
                    default:
                        self::render_general_settings_tab();
                        break;
                }

                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public static function render_general_settings_tab() {
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
        <?php
    }

    public static function render_aws_bedrock_settings_tab() {
        $aws_settings = get_option('wpa_aws_settings', []);
        $encryption_key_exists = wpa_get_encryption_key() !== false;

        ?>
        <h2>AWS Bedrock Configuration</h2>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><label for="wpa_aws_settings_region">AWS Region</label></th>
                <td>
                    <input type="text" id="wpa_aws_settings_region" name="wpa_aws_settings[region]" value="<?php echo esc_attr($aws_settings['region'] ?? ''); ?>" class="regular-text" placeholder="e.g., us-east-1" />
                    <p class="description">Enter the AWS region where you enabled Bedrock model access (e.g., <code>us-east-2</code> for Ohio).</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="wpa_aws_settings_access_key_id">AWS Access Key ID</label></th>
                <td>
                    <?php
                    $access_key_id_display = $aws_settings['access_key_id'] ?? '';
                    // Optionally mask the Access Key ID display too - uncomment below if desired
                    // if (!empty($access_key_id_display) && strlen($access_key_id_display) > 4) {
                    //     $access_key_id_display = '************' . substr($access_key_id_display, -4);
                    // }
                    ?>
                    <input type="text" id="wpa_aws_settings_access_key_id" name="wpa_aws_settings[access_key_id]" value="<?php echo esc_attr($access_key_id_display); ?>" class="regular-text" />
                    <p class="description">Enter your AWS Access Key ID. This key must belong to a dedicated IAM user with permissions to invoke Bedrock models.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="wpa_aws_settings_secret_access_key">AWS Secret Access Key</label></th>
                <td>
                    <?php
                    // Display a masked value if the key is set, otherwise display blank
                    $secret_access_key_masked_value = !empty($aws_settings['secret_access_key']) ? '********' : '';
                    ?>
                    <input type="text" id="wpa_aws_settings_secret_access_key" name="wpa_aws_settings[secret_access_key]" value="<?php echo esc_attr($secret_access_key_masked_value); ?>" class="regular-text" autocomplete="off" />
                    <p class="description">Enter your AWS Secret Access Key. This key is sensitive and should be kept confidential.</p>
                    <?php if (!$encryption_key_exists): ?>
                          <p style="color: #dc3232; font-weight: bold;">Warning: Encryption key not found. AWS Secret Access Key cannot be saved securely. Please try deactivating and reactivating the plugin.</p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
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
                                                                if (!empty($image['details'])) {
                                                                    foreach ($image['details'] as $detail) {
                                                                        echo '<li class="detail-item">' . esc_html($detail) . '</li>';
                                                                    }
                                                                }
                                                                if (!empty($image['issues'])) {
                                                                    foreach ($image['issues'] as $issue) {
                                                                        echo '<li class="issue-item">' . esc_html($issue) . '</li>';
                                                                    }
                                                                }
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
                                                        $has_missing_alt = false;
                                                        foreach ($image['issues'] as $issue) {
                                                            if (strpos($issue, 'Missing alt text') !== false) {
                                                                $has_missing_alt = true;
                                                                break;
                                                            }
                                                        }
                                                        // Check if AWS Bedrock settings are configured before showing AI generate button
                                                        $aws_settings = get_option('wpa_aws_settings', []);
                                                        $bedrock_configured = !empty($aws_settings['access_key_id']) && !empty($aws_settings['secret_access_key']) && !empty($aws_settings['region']);

                                                        if ($has_missing_alt && $bedrock_configured) :
                                                        ?>
                                                            <div class="wpa-alt-editor-container">
                                                                 <div class="wpa-alt-text-input-wrap">
                                                                     <input type="text"
                                                                            class="alt-text-input"
                                                                            placeholder="Add alternative text..."
                                                                            data-image-id="<?php echo esc_attr($image['id']); ?>"
                                                                            data-nonce="<?php echo wp_create_nonce('wpa_inline_edit'); ?>">
                                                                     <i class="fa-solid fa-wand-magic-sparkles wpa-generate-alt-text-icon" title="Generate Alt Text with AI" data-image-id="<?php echo esc_attr($image['id']); ?>"></i>
                                                                 </div>
                                                                <button type="button"
                                                                        class="button button-small save-alt-text"
                                                                        data-image-id="<?php echo esc_attr($image['id']); ?>"
                                                                        data-nonce="<?php echo wp_create_nonce('wpa_inline_edit'); ?>">
                                                                    Save
                                                                </button>
                                                                 <div class="wpa-alt-options-container" style="display: none;"></div>
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
                                // Ensure the message container exists
                                if (!$('#wpa-message-container').length) {
                                    $('body').append('<div id="wpa-message-container"></div>');
                                }

                                // DEFINE showMessage FUNCTION HERE
                                function showMessage(message, type) {
                                    var messageHtml = $('<div class="wpa-message notice notice-' + type + '"><p>' + message + '</p></div>');
                                    $('#wpa-message-container').append(messageHtml);

                                    messageHtml[0].offsetHeight; // Trigger reflow for animation

                                    messageHtml.addClass('show');

                                    setTimeout(function() {
                                        messageHtml.removeClass('show');
                                        setTimeout(function() {
                                            messageHtml.remove();
                                        }, 300);
                                    }, type === 'success' ? 3000 : 5000);
                                }

                                // ALL OTHER JAVASCRIPT CODE GOES BELOW THIS LINE

                                // Handle save alt text click
                                $('.save-alt-text').on('click', function() {
                                    var button = $(this);
                                     // Find the input field correctly within the editor container
                                    var altEditorContainer = button.closest('.wpa-alt-editor-container');
                                    var inputField = altEditorContainer.find('.alt-text-input');
                                    var imageId = button.data('image-id');
                                    var nonce = button.data('nonce'); // This nonce is for the save action
                                    var altText = inputField.val().trim(); // Use inputField variable

                                    if (!altText) {
                                        showMessage('Please enter alternative text', 'error');
                                        inputField.addClass('error').focus(); // Use inputField variable
                                        return;
                                    }

                                    altText = altText.replace(/<[^>]*>/g, '');

                                    inputField.prop('disabled', true) // Use inputField variable
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
                                            showMessage('Alt text updated successfully', 'success');

                                            // Re-check image status after save to update the row dynamically
                                            var row = button.closest('tr'); // Find the row relative to the save button
                                            $.post(ajaxurl, {
                                                action: 'wpa_check_image_status',
                                                image_id: imageId,
                                                nonce: nonce // Assuming this nonce is valid for wpa_check_image_status too
                                            }, function(statusResponse) {
                                                if (statusResponse.success) {
                                                    var detailsContent = row.find('.details-content');
                                                    var detailsList = detailsContent.find('ul');

                                                    // Remove the 'Missing alt text' issue/warning from the list
                                                    detailsList.find('li.issue-item:contains("Missing alt text"), li.warning-item:contains("Missing alt text")').remove();

                                                    var allIssues = (statusResponse.data.issues || []).concat(statusResponse.data.warnings || []);
                                                    if (allIssues.length > 0) {
                                                         // If other issues/warnings still exist, update the details list and status icon/text
                                                         detailsList.empty(); // Clear old list

                                                          if (statusResponse.data.details && statusResponse.data.details.length > 0) {
                                                              detailsResponseData = statusResponse.data.details
                                                              detailsResponseData.forEach(function(detail) {
                                                                detailsList.append('<li class="detail-item">' + detail + '</li>');
                                                              });
                                                          }

                                                         statusResponse.data.issues.forEach(function(issue) {
                                                             detailsList.append('<li class="issue-item">' + issue + '</li>');
                                                         });
                                                         statusResponse.data.warnings.forEach(function(warning) {
                                                             detailsList.append('<li class="warning-item">' + warning + '</li>');
                                                         });

                                                         var statusIcon = '';
                                                         var statusText = '';
                                                         var statusClass = '';
                                                         switch (statusResponse.data.status) {
                                                             case 'failed':
                                                                 statusIcon = '❌';
                                                                 statusText = 'Failed';
                                                                 statusClass = 'failed';
                                                                 break;
                                                             case 'warning':
                                                                 statusIcon = '⚠️';
                                                                 statusText = 'Warning';
                                                                 statusClass = 'warning';
                                                                 break;
                                                             default:
                                                                 statusIcon = '✅';
                                                                 statusText = 'Passed';
                                                                 statusClass = 'passed';
                                                         }
                                                         // Update status cell content and class
                                                         var statusCell = row.find('td:nth-child(2)');
                                                         statusCell.html(statusIcon + ' ' + esc_html(statusText));
                                                         statusCell.removeClass('failed warning passed').addClass(statusClass);

                                                     } else {
                                                         // If no issues/warnings remain, remove the row
                                                         row.fadeOut(400, function() {
                                                             $(this).remove();

                                                             // Check if all rows are gone, display a message if so
                                                             if ($('tbody tr').length === 0) {
                                                                 $('tbody').append(
                                                                     '<tr><td colspan="5">All images have proper metadata</td></tr>'
                                                                 );
                                                             } else {
                                                                 // If rows still exist, re-sort them after one is removed
                                                                 var $tbody = $('tbody');
                                                                  var rows = $tbody.find('tr').toArray().sort(function(a, b) {
                                                                      var order = { 'failed': 1, 'warning': 2, 'passed': 3 };
                                                                      var statusA = $(a).find('td:nth-child(2)').text().trim().split(' ')[1];
                                                                      var statusB = $(b).find('td:nth-child(2)').text().trim().split(' ')[1];

                                                                      statusA = statusA === 'Failed' ? 'failed' : (statusA === 'Warning' ? 'warning' : 'passed');
                                                                      statusB = statusB === 'Failed' ? 'failed' : (statusB === 'Warning' ? 'warning' : 'passed');

                                                                      var orderA = order[statusA] || 4;
                                                                      var orderB = order[statusB] || 4;

                                                                      if (orderA === orderB) return 0;
                                                                      return (orderA < orderB) ? -1 : 1;
                                                                  });
                                                                  $tbody.append(rows);
                                                              }
                                                          });
                                                      }
                                                  } else {
                                                       showMessage('Failed to refresh image status: ' + (statusResponse.data || 'Unknown error'), 'warning');
                                                  }
                                             }).fail(function() {
                                                  showMessage('Failed to refresh image status: Network error', 'warning');
                                             });


                                        } else {
                                            showMessage('Failed to update alt text: ' + (response.data || 'Unknown error'), 'error');

                                            // Restore input and button states on failure
                                            inputField.prop('disabled', false);
                                            button.prop('disabled', false)
                                                  .text('Save')
                                                  .removeClass('updating-message');
                                        }
                                    }).fail(function() {
                                        showMessage('Failed to update alt text: Network error', 'error');

                                        // Restore input and button states on network failure
                                        inputField.prop('disabled', false);
                                        button.prop('disabled', false)
                                              .text('Save')
                                              .removeClass('updating-message');
                                    });
                                });

                                // Handle input change on alt text field
                                $('.alt-text-input').on('input', function() {
                                    $(this).removeClass('error');
                                });

                                // Handle click on the AI Generate icon
                                $('.wpa-generate-alt-text-icon').on('click', function() {
                                    var icon = $(this);
                                     // Find the input field and options container relative to the main editor container
                                    var altEditorContainer = icon.closest('.wpa-alt-editor-container');
                                    var inputField = altEditorContainer.find('.alt-text-input');
                                    var optionsContainer = altEditorContainer.find('.wpa-alt-options-container');
                                    var imageId = inputField.data('image-id');
                                    // Use the correct nonce for the AI generation action
                                    var nonce = '<?php echo wp_create_nonce('wpa_generate_alt_text'); ?>';

                                    // Hide previous options if any
                                    optionsContainer.hide().empty();

                                    // Show loading state on the icon
                                    icon.addClass('loading').removeClass('fa-solid fa-wand-magic-sparkles').addClass('fas fa-sync fa-spin'); // Add fa-spin class here

                                    // Make AJAX call to generate alt text
                                    $.post(ajaxurl, {
                                        action: 'wpa_generate_alt_text', // This is the action handled by WPA_Ajax
                                        attachment_id: imageId,
                                        nonce: nonce // Security nonce matching the action name
                                    }, function(response) {
                                        // Restore icon state
                                        icon.removeClass('loading fas fa-sync fa-spin').addClass('fa-solid fa-wand-magic-sparkles'); // Remove fa-spin here

                                        if (response.success && Array.isArray(response.data)) {
                                            // Display options
                                            if (response.data.length > 0) {
                                                optionsContainer.empty(); // Clear any previous content
                                                response.data.forEach(function(option) {
                                                    // Create a clickable option element
                                                    var optionElement = $('<p class="wpa-alt-option"></p>').text(option);
                                                    optionsContainer.append(optionElement); // Still append to optionsContainer
                                                });
                                                // Position the container below the input wrapper
                                                altEditorContainer.find('.wpa-alt-text-input-wrap').append(optionsContainer);
                                                optionsContainer.show();

                                            } else {
                                                showMessage('AI generated no suggestions.', 'info');
                                            }
                                        } else {
                                            // Handle error response
                                            showMessage('AI Error: ' + (response.data || 'Failed to generate alt text.'), 'error');
                                        }
                                    }).fail(function(jqXHR, textStatus, errorThrown) {
                                        // Handle AJAX failure
                                        icon.removeClass('loading fas fa-sync fa-spin').addClass('fa-solid fa-wand-magic-sparkles'); // Remove fa-spin here
                                        showMessage('AJAX Error: Could not communicate with the server.', 'error');
                                    });
                                });

                                // Handle click on a generated option
                                $(document).on('click', '.wpa-alt-option', function() {
                                    var selectedText = $(this).text();
                                     // Find the closest alt-text-editor container
                                    var altEditorContainer = $(this).closest('.wpa-alt-editor-container');
                                     // Find the input field and options container within that container
                                    var inputField = altEditorContainer.find('.alt-text-input');
                                    var optionsContainer = altEditorContainer.find('.wpa-alt-options-container');

                                    // Populate the input field
                                    inputField.val(selectedText);

                                    // Hide and clear the options container
                                    optionsContainer.hide().empty();
                                });

                                // Optional: Hide options if user clicks outside the container/input or the icon itself
                                $(document).on('click', function(event) {
                                    if (!$(event.target).closest('.wpa-alt-editor-container').length && !$(event.target).is('.wpa-generate-alt-text-icon')) {
                                        $('.wpa-alt-options-container').hide().empty();
                                    }
                                });

                                // Hide options when the input field is focused or its value changes
                                $(document).on('focus input', '.alt-text-input', function() {
                                     // Find the closest alt-text-editor container
                                    var altEditorContainer = $(this).closest('.wpa-alt-editor-container');
                                     // Find the options container within that container and hide it
                                    altEditorContainer.find('.wpa-alt-options-container').hide().empty();
                                });
                            }); // End of jQuery(document).ready
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
                                width: 250px;
                            }
                            .action-buttons .button {
                                width: auto;
                                min-width: 0;
                            }
                            .wpa-alt-editor-container {
                                 display: flex;
                                 gap: 5px;
                                 margin-top: 5px;
                                 width: 100%; /* Match parent column width */
                                 align-items: center; /* Vertically align input and button */
                             }
                            .wpa-alt-text-input-wrap {
                                 position: relative; /* Container for absolute positioning of icon */
                                 flex: 1; /* Allow input to take available space */
                                 min-width: 0; /* Prevent flex item from overflowing */
                                 max-width: 180px; /* Match previous input width */
                             }
                            .alt-text-input {
                                 /* Existing styles */
                                 width: 100%; /* Fill its container */
                                 box-sizing: border-box; /* Include padding/border in width */
                                 padding-right: 30px; /* Make space for the icon */
                             }
                            .wpa-generate-alt-text-icon {
                                 position: absolute;
                                 top: 50%;
                                 right: 5px; /* Adjust positioning as needed */
                                 transform: translateY(-50%); /* Vertically center */
                                 cursor: pointer;
                                 color: #0073aa; /* WordPress primary blue - keep WP branding color */
                                 font-size: 18px; /* Slightly smaller size for FA icons */
                             }
                            .wpa-generate-alt-text-icon:hover {
                                 color: #005177; /* Darker blue on hover */
                             }

                             /* Styles for AI Alt Text Options */
                             .wpa-alt-options-container {
                                 min-width: 200px;
                                 box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                                 position: absolute;
                                 background: #fff;
                                 border: 1px solid #ccc;
                                 padding: 5px;
                                 z-index: 100;
                                 top: 100%;
                                 right: 0;
                             }
                             .wpa-alt-option {
                                 padding: 5px;
                                 margin: 0; /* Reset margin */
                                 cursor: pointer;
                                 font-size: 0.9em;
                                 line-height: 1.3;
                                 border-bottom: 1px solid #eee; /* Add a separator */
                             }
                             .wpa-alt-option:last-child {
                                 border-bottom: none; /* Remove border from the last item */
                             }
                             .wpa-alt-option:hover {
                                 background-color: #f0f0f0; /* Highlight on hover */
                             }

                            /* Add keyframes for spinner animation */
                            @keyframes spin {
                                 0% { transform: translateY(-50%) rotate(0deg); }
                                 100% { transform: translateY(-50%) rotate(360deg); }
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
                                $checks = $report[$current_tab];
                                usort($checks, function($a, $b) {
                                    $order = ['failed' => 1, 'warning' => 2, 'passed' => 3];

                                    $type_a = isset($a['type']) ? $a['type'] : ($a['status'] ? 'passed' : 'failed');
                                    $type_b = isset($b['type']) ? $b['type'] : ($b['status'] ? 'passed' : 'failed');

                                    $order_a = isset($order[$type_a]) ? $order[$type_a] : 4;
                                    $order_b = isset($order[$type_b]) ? $order[$type_b] : 4;

                                    if ($order_a === $order_b) return 0;
                                    return ($order_a < $order_b) ? -1 : 1;
                                });

                                foreach ($checks as $check) :
                                    $action_link = self::get_action_link($current_tab, $check);

                                    $status_icon = '';
                                    $status_text = '';
                                    $status_class = '';

                                    if ($check['status'] === true) {
                                        $status_icon = '✅';
                                        $status_text = 'Passed';
                                        $status_class = 'passed';
                                    } else {
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

        return null;
    }

    /**
     * Encrypts data using OpenSSL.
     *
     * @param string $data The data to encrypt.
     * @param string $key The encryption key.
     * @return string|WP_Error The encrypted data on success, or WP_Error on failure.
     */
    private static function encrypt_data($data, $key) {
        if (empty($data) || empty($key)) {
            return new WP_Error('encryption_error', 'Data or key is empty.');
        }

        $cipher = 'aes-256-cbc';
        $ivlen = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $encrypted = openssl_encrypt($data, $cipher, $key, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            return new WP_Error('encryption_error', 'OpenSSL encryption failed: ' . openssl_error_string() . '. Check if the encryption key in the database was generated correctly.');
        }

        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypts data using OpenSSL.
     * Needs to be public static so WPA_Auditor can access it.
     *
     * @param string $data The data to decrypt (base64 encoded IV + encrypted data).
     * @param string $key The decryption key.
     * @return string|WP_Error The decrypted data on success, or WP_Error on failure.
     */
    public static function decrypt_data($data, $key) {
        if (empty($data) || empty($key)) {
            return new WP_Error('decryption_error', 'Data or key is empty.');
        }

        $data = base64_decode($data);
        $cipher = 'aes-256-cbc';
        $ivlen = openssl_cipher_iv_length($cipher);
        if ($ivlen >= strlen($data)) {
            // Data is shorter than IV length, invalid encrypted data
            return new WP_Error('decryption_error', 'Encrypted data is too short or invalid.');
        }
        $iv = substr($data, 0, $ivlen);
        $encrypted_data = substr($data, $ivlen);

        $decrypted = openssl_decrypt($encrypted_data, $cipher, $key, OPENSSL_RAW_DATA, $iv);

        if ($decrypted === false) {
            // Check for OpenSSL errors if decryption fails
             $openssl_error = openssl_error_string();
            return new WP_Error('decryption_error', 'OpenSSL decryption failed.' . ($openssl_error ? " Error: " . $openssl_error : "") . ' Check if the encryption key in the database or the stored encrypted data is correct.');
        }

        return $decrypted;
    }


    /**
     * Enqueues admin-specific scripts and styles.
     *
     * @param string $hook_suffix The current admin page hook suffix.
     */
    public static function enqueue_admin_assets($hook_suffix) {
        // Check if we are on our plugin's admin pages
        // Page slugs are 'toplevel_page_wpa-settings' and 'wpa-settings_page_wpa-report'
        if ($hook_suffix === 'toplevel_page_wpa-settings' || $hook_suffix === 'wpa-settings_page_wpa-report' || $hook_suffix === 'audit-settings_page_wpa-report') {
            // Enqueue Font Awesome Free from a CDN
            wp_enqueue_style(
                'wpa-font-awesome',
                'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css', // Using a reliable CDN
                [], // No dependencies
                '6.5.1' // Version
            );
             // You might also enqueue your specific admin CSS/JS here if you have separate files
             // wp_enqueue_style('wpa-admin-style', WPA_PLUGIN_URL . 'assets/css/admin.css', [], WPA_VERSION);
             // wp_enqueue_script('wpa-admin-script', WPA_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], WPA_VERSION, true);
        }
    }
}
