<?php

class WPA_Core {
    public static function init() {
        require_once WPA_PLUGIN_DIR . 'includes/class-wpa-auditor.php';
        require_once WPA_PLUGIN_DIR . 'includes/class-wpa-admin.php';
        require_once WPA_PLUGIN_DIR . 'includes/class-wpa-cron.php';

        WPA_Admin::init();
        WPA_Auditor::init();
        WPA_Cron::init();
    }
}
