<?php
if (!defined('ABSPATH')) exit;
class OLC_Cron {
    public static function init() {
        if (!wp_next_scheduled('olc_daily_delete_ccvv')) {
            wp_schedule_event(time(), 'daily', 'olc_daily_delete_ccvv');
        }
        add_action('olc_daily_delete_ccvv', array(__CLASS__, 'delete_expired_ccvvs'));
    }

    public static function delete_expired_ccvvs() {
        global $wpdb;
        $ret_days = intval(get_option('olc_ccvv_retention_days', 60));
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$ret_days} days"));
        $table = $wpdb->prefix . 'olc_postulaciones';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT id, ccvv_path FROM {$table} WHERE fecha_ccvv_subida IS NOT NULL AND fecha_ccvv_subida < %s", $cutoff));
        foreach ($rows as $r) {
            if (!empty($r->ccvv_path)) {
                $path = ABSPATH . ltrim($r->ccvv_path, '/');
                if (file_exists($path)) {
                    @unlink($path);
                }
                $wpdb->update($table, array('ccvv_path'=>''), array('id'=>$r->id));
            }
        }
    }
}
OLC_Cron::init();
