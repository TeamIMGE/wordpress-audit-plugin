<?php

class WPA_Cron {
    public static function init() {
        add_action('wpa_weekly_audit_event', [self::class, 'run_weekly_audit']);
        if (!wp_next_scheduled('wpa_weekly_audit_event')) {
            wp_schedule_event(time(), 'weekly', 'wpa_weekly_audit_event');
        }
    }

    public static function run_weekly_audit() {
        $results = WPA_Auditor::run_audits();
        $users = explode(',', get_option('wpa_responsible_users'));
        foreach ($users as $user_id) {
            $user = get_user_by('id', trim($user_id));
            if ($user && !empty($results)) {
                wp_mail($user->user_email, 'Weekly Audit Results', implode("\n", $results));
            }
        }
    }
}
