<?php

class WPA_Auditor {

    public static function init() {
        // Future hooks or filters can go here
    }

    public static function run_all_checks() {
        return [
            self::check_search_engine_visibility(),
            self::check_yoast_settings(),
            self::check_image_metadata(),
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

    public static function check_yoast_settings() {
        if (!class_exists('WPSEO_Options')) {
            return [
                'label' => 'Yoast SEO Settings',
                'status' => false,
                'message' => 'Yoast plugin not active.'
            ];
        }

        $yoast_options = get_option('wpseo_titles');
        $title_template = isset($yoast_options['title-home-wpseo']) ? $yoast_options['title-home-wpseo'] : '';

        return [
            'label' => 'Yoast Home Title Set',
            'status' => !empty($title_template),
            'message' => $title_template ? 'Title template found.' : 'No title template set.'
        ];
    }

    public static function check_image_metadata() {
        $args = [
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'posts_per_page' => 5,
        ];
        $query = new WP_Query($args);
        $fails = [];

        foreach ($query->posts as $img) {
            $alt = get_post_meta($img->ID, '_wp_attachment_image_alt', true);
            $title = get_the_title($img->ID);
            if (empty($alt) || empty($title)) {
                $fails[] = $img->ID;
            }
        }

        return [
            'label' => 'Image Metadata (sample of 5)',
            'status' => empty($fails),
            'message' => empty($fails) ? 'All images passed.' : 'Missing metadata on image IDs: ' . implode(', ', $fails)
        ];
    }
}