<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$options             = get_option('oss_options', true);
$upload_url_path     = get_option('upload_url_path');
$oss_upload_url_path = esc_attr($options['upload_url_path']);

if ($upload_url_path == $oss_upload_url_path) {
    update_option('upload_url_path', '');
}

delete_option('oss_options');
