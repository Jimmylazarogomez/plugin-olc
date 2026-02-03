<?php
if (!defined('ABSPATH')) exit;
class OLC_Deactivator {
    public static function deactivate() {
        wp_clear_scheduled_hook('olc_daily_delete_ccvv');
    }
}
