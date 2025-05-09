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

    public static function check_image_metadata() {
        $args = [
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'posts_per_page' => -1, // Get all images
        ];
        $query = new WP_Query($args);
        $missing_alt = [];

        foreach ($query->posts as $img) {
            $alt = get_post_meta($img->ID, '_wp_attachment_image_alt', true);
            if (empty($alt)) {
                $missing_alt[] = [
                    'id' => $img->ID,
                    'title' => get_the_title($img->ID),
                    'url' => wp_get_attachment_url($img->ID)
                ];
            }
        }

        return [
            'label' => 'Images Missing Alt Text',
            'status' => empty($missing_alt),
            'message' => empty($missing_alt) ? 'All images have alt text.' : sprintf('Found %d images without alt text.', count($missing_alt)),
            'details' => $missing_alt
        ];
    }
}