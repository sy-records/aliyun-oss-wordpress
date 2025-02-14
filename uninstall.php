<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

update_option('upload_url_path', '');
delete_option('oss_options');
