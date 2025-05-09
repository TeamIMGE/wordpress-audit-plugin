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

        return [
            self::check_yoast_title_template(),
            self::check_yoast_site_image(),
            // Add more Yoast checks here
        ];
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
                
                // Always include dimension details for images with issues
                if (!empty($metadata['width']) && !empty($metadata['height'])) {
                    $details[] = sprintf('Dimensions: %dpx × %dpx', $metadata['width'], $metadata['height']);
                }
                if ($file_path && file_exists($file_path)) {
                    $size = filesize($file_path);
                    $size_mb = $size / 1024 / 1024;
                    $details[] = sprintf('File size: %.2f MB', $size_mb);
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