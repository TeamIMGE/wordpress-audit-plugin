<?php

class WPA_Auditor {
    private static $categories = [
        'wordpress' => 'WordPress Settings',
        'yoast' => 'Yoast SEO',
        'images' => 'Image Metadata'
    ];

    public static function init() {
        // Future hooks or filters can go here
    }

    public static function get_categories() {
        return self::$categories;
    }

    public static function run_all_checks() {
        return [
            'wordpress' => self::get_wordpress_checks(),
            'yoast' => self::get_yoast_checks(),
            'images' => self::get_image_checks()
        ];
    }

    private static function get_wordpress_checks() {
        return [
            self::check_search_engine_visibility(),
            self::check_site_title(),
            // Add more WordPress checks here
        ];
    }

    private static function get_yoast_checks() {
        if (!class_exists('WPSEO_Options')) {
            return [[
                'label' => 'Yoast SEO Plugin',
                'status' => false,
                'message' => 'Yoast plugin not active.'
            ]];
        }

        // Get individual check results
        $checks = [
            self::check_yoast_title_template(),
            self::check_yoast_site_image(),
            self::check_yoast_author_archives(),
            self::check_yoast_date_archives(),
            self::check_yoast_format_archives(),
            self::check_yoast_media_pages(),
            // Custom post types will be merged separately as they return multiple results
            // Add more single Yoast checks here
        ];

        // Get individual custom post type checks and merge them
        $custom_post_type_checks = self::check_yoast_custom_post_types();
        if (!empty($custom_post_type_checks)) {
            $checks = array_merge($checks, $custom_post_type_checks);
        }

        return $checks;
    }

    private static function get_image_checks() {
        return [
            self::check_image_metadata()
        ];
    }

    public static function check_search_engine_visibility() {
        $visible = get_option('blog_public');
        return [
            'label' => 'Search Engine Visibility',
            'status' => (bool) $visible,
            'message' => $visible ? 'Search engines allowed.' : 'Search engines are blocked.'
        ];
    }

    private static function check_site_title() {
        // First check WordPress site title
        $wp_title = get_bloginfo('name');
        
        // Then check Yoast site name if available
        $yoast_title = '';
        if (class_exists('WPSEO_Options')) {
            $yoast_options = get_option('wpseo_titles');
            $yoast_title = isset($yoast_options['sitename']) ? $yoast_options['sitename'] : '';
        }

        $has_title = !empty($wp_title) || !empty($yoast_title);
        $message = [];

        if (!empty($wp_title)) {
            $message[] = "WordPress site title: {$wp_title}";
        } else {
            $message[] = 'No WordPress site title set.';
        }

        if (!empty($yoast_title)) {
            $message[] = "Yoast site name: {$yoast_title}";
        }

        return [
            'label' => 'Site Title',
            'status' => $has_title,
            'message' => implode(' ', $message)
        ];
    }

    private static function check_yoast_title_template() {
        $yoast_options = get_option('wpseo_titles');
        $title_template = isset($yoast_options['title-home-wpseo']) ? $yoast_options['title-home-wpseo'] : '';

        return [
            'label' => 'Homepage Title Template',
            'status' => !empty($title_template),
            'message' => $title_template ? 'Title template found.' : 'No title template set.'
        ];
    }

    private static function check_yoast_site_image() {
        $social_options = get_option('wpseo_social');
        $site_image = isset($social_options['og_default_image']) ? $social_options['og_default_image'] : '';

        return [
            'label' => 'Default Social Image',
            'status' => !empty($site_image),
            'message' => $site_image ? 'Site image is set.' : 'No site image set.'
        ];
    }

    private static function check_yoast_author_archives() {
        $yoast_options = get_option('wpseo_titles');
        $author_archives_enabled = isset($yoast_options['disable-author']) ? !$yoast_options['disable-author'] : true;

        $result = [
            'label' => 'Author Archives',
        ];

        if ($author_archives_enabled) {
            $result['status'] = false; // Warning condition met
            $result['message'] = 'Author Archives is enabled. If you are not using these pages, disable the author archives in the SEO settings.';
            $result['type'] = 'warning';
            $result['action'] = [
                'label' => 'Edit Settings',
                'url' => admin_url('admin.php?page=wpseo_titles#archives')
            ];
        } else {
            $result['status'] = true; // Passed
            $result['message'] = 'Author Archives is disabled.';
            $result['type'] = 'passed'; // Although not strictly needed, explicitly setting type for clarity
        }

        return $result;
    }

    private static function check_yoast_date_archives() {
        $yoast_options = get_option('wpseo_titles');
        $date_archives_enabled = isset($yoast_options['disable-date']) ? !$yoast_options['disable-date'] : true;

        $result = [
            'label' => 'Date Archives',
        ];

        if ($date_archives_enabled) {
            $result['status'] = false; // Warning condition met
            $result['message'] = 'Date Archives is enabled. If you are not using these pages, disable the date archives in the SEO settings.';
            $result['type'] = 'warning';
            $result['action'] = [
                'label' => 'Edit Settings',
                'url' => admin_url('admin.php?page=wpseo_titles#archives')
            ];
        } else {
            $result['status'] = true; // Passed
            $result['message'] = 'Date Archives is disabled.';
            $result['type'] = 'passed'; // Explicitly setting type for clarity
        }

        return $result;
    }

    private static function check_yoast_format_archives() {
        $yoast_options = get_option('wpseo_titles');
        $format_archives_enabled = isset($yoast_options['disable-post_format']) ? !$yoast_options['disable-post_format'] : true;

        $result = [
            'label' => 'Format Archives',
        ];

        if ($format_archives_enabled) {
            $result['status'] = false; // Warning condition met
            $result['message'] = 'Format Archives is enabled. If you are not using these pages, disable the format archives in the SEO settings.';
            $result['type'] = 'warning';
            $result['action'] = [
                'label' => 'Edit Settings',
                'url' => admin_url('admin.php?page=wpseo_titles#archives')
            ];
        } else {
            $result['status'] = true; // Passed
            $result['message'] = 'Format Archives is disabled.';
            $result['type'] = 'passed'; // Explicitly setting type for clarity
        }

        return $result;
    }

    private static function check_yoast_media_pages() {
        $yoast_options = get_option('wpseo_titles');
        $media_pages_enabled = isset($yoast_options['disable-attachment']) ? !$yoast_options['disable-attachment'] : true;

        $result = [
            'label' => 'Media Pages',
        ];

        if ($media_pages_enabled) {
            // Media pages enabled is a failure
            $result['status'] = false;
            $result['message'] = 'Media pages are enabled. These should be disabled in the SEO settings.';
            $result['type'] = 'failed';
            $result['action'] = [
                'label' => 'Edit Settings',
                'url' => admin_url('admin.php?page=wpseo_titles#post-type')
            ];
        } else {
            // Media pages disabled is a pass
            $result['status'] = true;
            $result['message'] = 'Media pages are disabled.';
            $result['type'] = 'passed'; // Explicitly set type for clarity
        }

        return $result;
    }

    private static function check_yoast_custom_post_types() {
        $yoast_options = get_option('wpseo_titles');
        // Get all non-built-in post types
        $post_types = get_post_types(['_builtin' => false], 'objects');

        $custom_post_type_checks = [];

        foreach ($post_types as $post_type) {
            // Skip post types that Yoast excludes by default or are not relevant for SEO checks
            // 'attachment' is handled by check_yoast_media_pages()
            // 'wpa_audit_log' is an internal post type
            if (in_array($post_type->name, ['attachment', 'wpa_audit_log'])) {
                 continue;
            }

            // Check if the Yoast noindex option exists and its value for this post type
            $yoast_noindex_option_exists = isset($yoast_options['noindex-' . $post_type->name]);
            // Yoast saves noindex as '1' for true and '0' for false.
            // $yoast_options['noindex-' . $post_type->name] will be the string '1' or '0' if set.
            // We need to check explicitly against '1' for the noindex true case.
            $yoast_noindex_is_true = $yoast_noindex_option_exists && (string)$yoast_options['noindex-' . $post_type->name] === '1';

            $label = sprintf('%s Search Appearance', $post_type->label);
            $status = true; // Assume passed initially
            $message = sprintf('%s search appearance is properly configured.', $post_type->label);
            $type = 'passed';
            $action = null;

            // Construct the correct action URL for this post type
            $edit_url = admin_url('admin.php?page=wpseo_page_settings#/post-type/' . $post_type->name);

            // Logic based on public status and Yoast noindex setting
            if (($post_type->publicly_queryable AND !$post_type->public) OR (!$post_type->publicly_queryable AND $post_type->public)) {
                // Public post type: Should generally be indexed unless intentionally hidden.
                if (!$yoast_noindex_is_true) {
                    // Public but set to noindex (Warning - might be intentional, but worth noting)
                    $status = false;
                    $message = sprintf('%s is public but set to noindex in Yoast SEO. Consider enabling it unless intentionally hidden.', $post_type->label);
                    $type = 'failed';
                    $action = [
                        'label' => 'Edit Yoast Settings',
                        'url' => $edit_url
                    ];
                } else {
                    // Public and set to index (Correct - PASS for public)
                    $status = true;
                    $message = sprintf('%s search appearance is properly configured for a public post type.', $post_type->label);
                    $type = 'passed';
                    $action = null; // No action needed if passed
                }
                
            } 
            else {
                // Non-public and noindexed (Correct - PASS)
                $status = true;
                $message = sprintf('%s is set to not be shown in search results in Yoast SEO (OK).', $post_type->label);
                $type = 'passed';
                $action = null; // No action needed if passed
            }

             $custom_post_type_checks[] = [
                'label' => $label,
                'status' => $status,
                'message' => $message,
                'type' => $type,
                'action' => $action,
                'slug' => $post_type->name // Still include slug, though not used in get_action_link with the generic case
            ];
        }

        // Return the array of individual custom post type checks
        return $custom_post_type_checks;
    }

    private static function check_yoast_xml_sitemap() {
        // Implementation of check_yoast_xml_sitemap method
    }

    private static function check_image_metadata() {
        $images = get_posts([
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'posts_per_page' => -1,
            'post_status' => 'inherit'
        ]);

        $images_with_issues = [];
        foreach ($images as $image) {
            $alt_text = get_post_meta($image->ID, '_wp_attachment_image_alt', true);
            $title = $image->post_title;
            $file_path = get_attached_file($image->ID);
            $metadata = wp_get_attachment_metadata($image->ID);
            
            $issues = [];
            $warnings = [];
            $status = 'passed';
            
            // Check for missing alt text
            if (empty($alt_text)) {
                $issues[] = 'Missing alt text';
                $status = 'failed';
            }
            
            // Check dimensions and file size
            if (!empty($metadata['width']) && !empty($metadata['height'])) {
                $width = $metadata['width'];
                $height = $metadata['height'];
                
                // Get file size first
                $size_mb = 0;
                if ($file_path && file_exists($file_path)) {
                    $size = filesize($file_path);
                    $size_mb = $size / 1024 / 1024; // Convert to MB
                }
                
                // File size checks
                if ($size_mb >= 1) {
                    $issues[] = 'The image is too large. Consider optimizing below 0.5MBs/500KBs but definitely lower than 1MB.';
                    $status = 'failed';
                } elseif ($size_mb >= 0.5) {
                    $warnings[] = 'The image is a bit too large. Consider optimizing it below 0.5MBs/500KBs.';
                    if ($status !== 'failed') {
                        $status = 'warning';
                    }
                }
            }
            
            // Only add to results if there are issues or warnings
            if (!empty($issues) || !empty($warnings)) {
                $details = [];
                
                // Only include dimension and file size details if there's a size/dimension issue
                if ($size_mb >= 0.5 || (!empty($metadata['width']) && !empty($metadata['height']) && ($status === 'failed' || $status === 'warning') && (in_array('The image is too large. Consider optimizing below 0.5MBs/500KBs but definitely lower than 1MB.', $issues) || in_array('The image is a bit too large. Consider optimizing it below 0.5MBs/500KBs.', $warnings)))) {
                     if (!empty($metadata['width']) && !empty($metadata['height'])) {
                        $details[] = sprintf('Dimensions: %dpx × %dpx', $metadata['width'], $metadata['height']);
                    }
                    if ($file_path && file_exists($file_path)) {
                        $size = filesize($file_path);
                        $size_mb = $size / 1024 / 1024;
                        $details[] = sprintf('File size: %.2f MB', $size_mb);
                    }
                }

                $images_with_issues[] = [
                    'id' => $image->ID,
                    'title' => $title,
                    'url' => wp_get_attachment_url($image->ID),
                    'edit_url' => get_edit_post_link($image->ID),
                    'status' => $status,
                    'issues' => $issues,
                    'warnings' => $warnings,
                    'details' => $details
                ];
            }
        }

        return [
            'status' => empty($images_with_issues),
            'label' => 'Image Metadata',
            'message' => empty($images_with_issues) ? 'All images have proper metadata' : 'Found images with metadata issues',
            'details' => $images_with_issues
        ];
    }

    /**
     * Generates alt text options for an image using AWS Bedrock.
     *
     * @param int $attachment_id The WordPress image attachment ID.
     * @return array|WP_Error An array of generated alt text options on success, or a WP_Error object on failure.
     */
    public static function generate_alt_text_with_bedrock($attachment_id) {
        // Get the image file path
        $file_path = get_attached_file($attachment_id);

        if (!$file_path || !file_exists($file_path)) {
            return new WP_Error('image_error', 'Image file not found.');
        }

        // Read the image file content
        $image_data = file_get_contents($file_path);
        if ($image_data === false) {
            return new WP_Error('image_error', 'Could not read image file.');
        }

        // Get image MIME type for the API
        $image_mime = get_post_mime_type($attachment_id);
        if (!$image_mime) {
             // Try to guess based on file extension if WP doesn't have it
            $image_mime = mime_content_type($file_path);
            if (!$image_mime) {
                 return new WP_Error('image_error', 'Could not determine image MIME type.');
            }
        }

        // Base64 encode the image data
        $base64_image = base64_encode($image_data);

        // Retrieve AWS settings from options
        $aws_settings = get_option('wpa_aws_settings', []);

        // Validate that required settings are present
        if (empty($aws_settings['access_key_id']) || empty($aws_settings['secret_access_key']) || empty($aws_settings['region'])) {
            return new WP_Error('aws_config_error', 'AWS credentials or region are not fully configured in plugin settings.');
        }

        // Get the encryption key from the database option
        $encryption_key = wpa_get_encryption_key(); // Function defined in main plugin file

        if (false === $encryption_key) {
            return new WP_Error('aws_config_error', 'Encryption key not found in database. Cannot decrypt AWS Secret Access Key. Please try deactivating and reactivating the plugin.');
        }

        // Decrypt the Secret Access Key using the helper function from WPA_Admin and the database key
        $decrypted_secret_access_key = WPA_Admin::decrypt_data($aws_settings['secret_access_key'], $encryption_key);

        if (is_wp_error($decrypted_secret_access_key)) {
             return new WP_Error('aws_config_error', 'Failed to decrypt AWS Secret Access Key: ' . $decrypted_secret_access_key->get_error_message());
        }

        // Initialize Bedrock Runtime Client using retrieved and decrypted credentials
        try {
            // Ensure required classes are loaded (Composer autoloader should handle this in main plugin file)
            if (!class_exists('Aws\\BedrockRuntime\\BedrockRuntimeClient') || !class_exists('Aws\\Credentials\\Credentials')) {
                 return new WP_Error('aws_sdk_error', 'AWS SDK classes not loaded. Ensure Composer autoloader is included.');
            }

            $credentials = new \Aws\Credentials\Credentials(
                $aws_settings['access_key_id'],
                $decrypted_secret_access_key
            );

            $bedrockClient = new \Aws\BedrockRuntime\BedrockRuntimeClient([
                'region' => $aws_settings['region'],
                'version' => 'latest',
                'credentials' => $credentials,
            ]);

            // Model ID for Claude 3.5 Sonnet via Bedrock
            // Verify this ID in your Bedrock console, or make it a plugin setting
            $modelId = 'us.anthropic.claude-sonnet-4-20250514-v1:0'; // Updated to US Claude Sonnet 4 Inference Profile ID

            // Prepare the prompt and image for the API call
            $prompt = "Generate three distinct, concise, and descriptive alternative text options for this image. Respond with a JSON object containing a single key, 'alt_options', whose value is an array of strings, where each string is one alt text option.";

            $requestBody = json_encode([
                'anthropic_version' => 'bedrock-2023-05-31', // Required for Anthropic models
                'max_tokens' => 300, // Adjust as needed
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'image',
                                'source' => [
                                    'type' => 'base64',
                                    'data' => $base64_image,
                                    'media_type' => $image_mime
                                ],
                            ],
                            [
                                'type' => 'text',
                                'text' => $prompt,
                            ],
                        ],
                    ],
                ],
            ]);

            // Make the API call
            $result = $bedrockClient->invokeModel([
                'contentType' => 'application/json',
                'body' => $requestBody,
                'modelId' => $modelId,
            ]);

            // Log the raw Bedrock API result
            error_log('Bedrock API Result: ' . print_r($result, true));

            // Process the response
            $responseBody = json_decode($result['body'], true);

            // Log the decoded response body
            error_log('Decoded Response Body: ' . print_r($responseBody, true));

            // Extract the generated text - for Claude, it's in $responseBody['content'][0]['text']
            // We asked for JSON output, so we need to decode that JSON string within the text
            $raw_generated_text = $responseBody['content'][0]['text'] ?? null;

            if (empty($raw_generated_text)) {
                 return new WP_Error('bedrock_error', 'Bedrock response was empty or unexpected.');
            }

            // Use regex to extract JSON string from markdown code block
            // This handles cases where the model wraps the JSON in ```json ... ```
            if (preg_match('/```json\s*(.*?)\s*```/s', $raw_generated_text, $matches) && isset($matches[1])) {
                $json_string_to_decode = $matches[1];
            } else {
                // If no markdown json block found, assume the whole text is the json string (less likely but possible)
                $json_string_to_decode = $raw_generated_text;
            }

            if (empty($json_string_to_decode)) {
                 return new WP_Error('bedrock_error', 'Bedrock response was empty or unexpected.');
            }

            // Attempt to decode the JSON string from the model's response
            $alt_options_response = json_decode($json_string_to_decode, true);

            // Check if JSON decoding was successful and the expected structure exists
            if (json_last_error() !== JSON_ERROR_NONE || !isset($alt_options_response['alt_options']) || !is_array($alt_options_response['alt_options'])) {
                 // If the model didn't return valid JSON or the expected structure
                 // Fallback: try parsing raw text for lines, filter empty, take max 3
                  $options = explode("\n", trim($json_string_to_decode));
                  $options = array_filter($options, 'trim');
                  $options = array_slice($options, 0, 3);

                  if(empty($options)) {
                       return new WP_Error('bedrock_error', 'Bedrock response format unexpected, could not extract options.');
                  }
                  return $options;
            }

            // Return the array of alt text options from the JSON response
            return $alt_options_response['alt_options'];

        } catch (\Aws\Exception\AwsException $e) {
            // Handle AWS specific errors
            return new WP_Error('aws_api_error', 'AWS API error: ' . $e->getMessage());
        } catch (\Exception $e) {
            // Handle other potential errors
            return new WP_Error('general_error', 'An error occurred: ' . $e->getMessage());
        }
    }
}