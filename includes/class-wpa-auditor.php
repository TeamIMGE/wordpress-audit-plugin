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
            // echo '<pre>';
            // var_dump($post_type);
            // echo '</pre>';
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
}