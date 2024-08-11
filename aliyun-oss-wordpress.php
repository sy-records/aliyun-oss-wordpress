<?php
/*
Plugin Name: OSS Aliyun
Plugin URI: https://github.com/sy-records/aliyun-oss-wordpress
Description: 使用阿里云对象存储 OSS 作为附件存储空间。（This is a plugin that uses Aliyun Object Storage Service for attachments remote saving.）
Version: 1.4.18
Author: 沈唁
Author URI: https://qq52o.me
License: Apache2.0
*/
if (!defined('ABSPATH')) {
    exit;
}

require_once 'sdk/vendor/autoload.php';

use OSS\OssClient;
use OSS\Credentials\CredentialsProvider;
use AlibabaCloud\Credentials\Credential;
use OSS\Credentials\StaticCredentialsProvider;

define('OSS_VERSION', '1.4.18');
define('OSS_BASEFOLDER', plugin_basename(dirname(__FILE__)));

if (!function_exists('get_home_path')) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
}

if (defined('WP_CLI') && WP_CLI) {
    require_once plugin_dir_path(__FILE__) . 'oss-commands.php';
}

class OSSCredentialsWrapper implements CredentialsProvider
{
    /**
     * @var \OSS\Credentials\Credentials
     */
    private $wrapper;

    public function __construct($wrapper)
    {
        $this->wrapper = $wrapper;
    }

    public function getCredentials()
    {
        $ak = $this->wrapper->getAccessKeyId();
        $sk = $this->wrapper->getAccessKeySecret();
        $token = $this->wrapper->getSecurityToken();
        return new StaticCredentialsProvider($ak, $sk, $token);
    }
}

// 初始化选项
register_activation_hook(__FILE__, 'oss_set_options');
// 初始化选项
function oss_set_options()
{
    $options = [
        'bucket' => '',
        'regional' => 'oss-cn-shanghai',
        'accessKeyId' => '',
        'accessKeySecret' => '',
        'is_internal' => 'false',
        'nothumb' => 'false', // 是否上传缩略图
        'nolocalsaving' => 'false', // 是否保留本地备份
        'upload_url_path' => '', // URL前缀
        'style' => '', // 图片处理
        'update_file_name' => 'false', // 是否重命名文件名
        'role_name' => '', // 角色名称
        'origin_protect' => '',
    ];
    add_option('oss_options', $options, '', 'yes');
}

function oss_get_client()
{
    $oss_options = get_option('oss_options', true);
    $role_name = esc_attr($oss_options['role_name'] ?? '');
    $endpoint = oss_get_bucket_endpoint($oss_options);

    if (!empty($role_name)) {
        $ecsRamRole = new Credential([
            // 填写Credential类型，固定值为ecs_ram_role。
            'type' => 'ecs_ram_role',
            // 填写角色名称。
            'role_name' => $role_name,
        ]);
        $providerWrapper = new OSSCredentialsWrapper($ecsRamRole);
        $provider = $providerWrapper->getCredentials();
    } else {
        $provider = new StaticCredentialsProvider(esc_attr($oss_options['accessKeyId']), esc_attr($oss_options['accessKeySecret']));
    }
    $config = [
        'provider'         => $provider,
        'endpoint'         => $endpoint,
        'signatureVersion' => OssClient::OSS_SIGNATURE_VERSION_V4,
        'region'           => str_replace('oss-', '', esc_attr($oss_options['regional'])),
    ];
    return new OssClient($config);
}

function oss_get_bucket_endpoint($oss_options)
{
    $regional = esc_attr($oss_options['regional']);
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? 'https' : 'http';
    if ($oss_options['is_internal'] == 'true') {
        return "{$protocol}://{$regional}-internal.aliyuncs.com";
    }

    return "{$protocol}://{$regional}.aliyuncs.com";
}

function oss_get_bucket_name()
{
    $oss_options = get_option('oss_options', true);
    return $oss_options['bucket'];
}

/**
 * @param string $object
 * @return array
 */
function oss_get_file_meta($object)
{
    try {
        $ossClient = oss_get_client();
        $bucket = oss_get_bucket_name();
        return $ossClient->getObjectMeta($bucket, $object);
    } catch (\Throwable $e) {
        error_log($e->getMessage());
        return ['content-length' => 0];
    }
}

/**
 * @param string $object
 * @param string $file
 * @param bool $no_local_file
 * @return bool
 */
function oss_file_upload($object, $file, $no_local_file = false)
{
    //如果文件不存在，直接返回false
    if (!@file_exists($file)) {
        return false;
    }
    $bucket = oss_get_bucket_name();
    $ossClient = oss_get_client();
    try {
        $ossClient->uploadFile($bucket, ltrim($object, '/'), $file);
        if ($no_local_file) {
            oss_delete_local_file($file);
        }

        return true;
    } catch (\Throwable $e) {
        error_log($e->getMessage());
        return false;
    }
}

/**
 * 是否需要删除本地文件
 *
 * @return bool
 */
function oss_is_delete_local_file()
{
    $oss_options = get_option('oss_options', true);
    return esc_attr($oss_options['nolocalsaving']) == 'true';
}

/**
 * 删除本地文件
 *
 * @param string $file
 * @return bool
 */
function oss_delete_local_file($file)
{
    try {
        //文件不存在
        if (!@file_exists($file)) {
            return true;
        }

        //删除文件
        if (!@unlink($file)) {
            return false;
        }

        return true;
    } catch (\Exception $ex) {
        return false;
    }
}

/**
 * 删除oss中的单个文件
 *
 * @param string $file
 */
function oss_delete_oss_file($file)
{
    try {
        $bucket = oss_get_bucket_name();
        $ossClient = oss_get_client();
        $ossClient->deleteObject($bucket, $file);
    } catch (\Throwable $e) {
        error_log($e->getMessage());
    }
}

/**
 * 批量删除文件
 * @param array $files
 */
function oss_delete_oss_files(array $files)
{
    $deleteObjects = [];
    foreach ($files as $file) {
        $deleteObjects[] = str_replace(["\\", './'], ['/', ''], $file);
    }

    try {
        $bucket = oss_get_bucket_name();
        $ossClient = oss_get_client();
        $ossClient->deleteObjects($bucket, $deleteObjects);
    } catch (\Throwable $e) {
        error_log($e->getMessage());
    }
}

/**
 * 上传附件（包括图片的原图）
 *
 * @param  $metadata
 * @return array
 */
function oss_upload_attachments($metadata)
{
    $mime_types = wp_get_mime_types();
    $image_mime_types = [
        $mime_types['jpg|jpeg|jpe'],
        $mime_types['gif'],
        $mime_types['png'],
        $mime_types['bmp'],
        $mime_types['tiff|tif'],
        $mime_types['webp'],
        $mime_types['ico'],
    ];
    // 例如mp4等格式 上传后根据配置选择是否删除 删除后媒体库会显示默认图片 点开内容是正常的
    // 图片在缩略图处理
    if (!in_array($metadata['type'], $image_mime_types)) {
        //生成object在oss中的存储路径
        if (oss_get_option('upload_path') == '.') {
            $metadata['file'] = str_replace('./', '', $metadata['file']);
        }
        $object = str_replace("\\", '/', $metadata['file']);
        $home_path = get_home_path();
        $object = str_replace($home_path, '', $object);

        //在本地的存储路径
        $file = $home_path . $object; //向上兼容，较早的WordPress版本上$metadata['file']存放的是相对路径

        //执行上传操作
        oss_file_upload('/' . $object, $file, oss_is_delete_local_file());
    }

    return $metadata;
}

/**
 * 上传图片的缩略图
 */
function oss_upload_thumbs($metadata)
{
    if (empty($metadata['file'])) {
        return $metadata;
    }

    //获取上传路径
    $wp_uploads = wp_upload_dir();
    $basedir = $wp_uploads['basedir'];
    $upload_path = oss_get_option('upload_path');

    //获取oss插件的配置信息
    $oss_options = get_option('oss_options', true);
    $no_local_file = esc_attr($oss_options['nolocalsaving']) == 'true';
    $no_thumb = esc_attr($oss_options['nothumb']) == 'true';

    // Maybe there is a problem with the old version
    $file = $basedir . '/' . $metadata['file'];
    if ($upload_path != '.') {
        $path_array = explode($upload_path, $file);
        if (count($path_array) >= 2) {
            $object = '/' . $upload_path . end($path_array);
        }
    } else {
        $object = '/' . $metadata['file'];
        $file = str_replace('./', '', $file);
    }

    oss_file_upload($object, $file, $no_local_file);

    //得到本地文件夹和远端文件夹
    $dirname = dirname($metadata['file']);
    $file_path = $dirname != '.' ? "{$basedir}/{$dirname}/" : "{$basedir}/";
    $file_path = str_replace("\\", '/', $file_path);
    if ($upload_path == '.') {
        $file_path = str_replace('./', '', $file_path);
    }
    $object_path = str_replace(get_home_path(), '', $file_path);

    if (!empty($metadata['original_image'])) {
        oss_file_upload("/{$object_path}{$metadata['original_image']}", "{$file_path}{$metadata['original_image']}", $no_local_file);
    }

    if ($no_thumb) {
        return $metadata;
    }

    //上传所有缩略图
    if (!empty($metadata['sizes'])) {
        //there may be duplicated filenames,so ....
        foreach ($metadata['sizes'] as $val) {
            //生成object在oss中的存储路径
            $object = '/' . $object_path . $val['file'];
            //生成本地存储路径
            $file = $file_path . $val['file'];

            //执行上传操作
            oss_file_upload($object, $file, $no_local_file);
        }
    }
    return $metadata;
}

/**
 * @param $override
 * @return mixed
 */
function oss_save_image_editor_file($override)
{
    add_filter('wp_update_attachment_metadata', 'oss_image_editor_file_do');
    return $override;
}

/**
 * @param $metadata
 * @return mixed
 */
function oss_image_editor_file_do($metadata)
{
    return oss_upload_thumbs($metadata);
}

//避免上传插件/主题时出现同步到oss的情况
if (substr_count($_SERVER['REQUEST_URI'], '/update.php') <= 0) {
    add_filter('wp_handle_upload', 'oss_upload_attachments', 50);
    add_filter('wp_generate_attachment_metadata', 'oss_upload_thumbs', 100);
    add_filter('wp_save_image_editor_file', 'oss_save_image_editor_file', 101);
}

/**
 * 删除远端文件，删除文件时触发
 * @param $post_id
 */
function oss_delete_remote_attachment($post_id)
{
    // 获取图片类附件的meta信息
    $meta = wp_get_attachment_metadata($post_id);
    $upload_path = oss_get_option('upload_path');
    if ($upload_path == '') {
        $upload_path = 'wp-content/uploads';
    }

    if (!empty($meta['file'])) {
        $deleteObjects = [];

        // meta['file']的格式为 "2020/01/wp-bg.png"
        $file_path = $upload_path . '/' . $meta['file'];
        $dirname = dirname($file_path) . '/';

        $deleteObjects[] = $file_path;

        // 超大图原图
        if (!empty($meta['original_image'])) {
            $deleteObjects[] = $dirname . $meta['original_image'];
        }

        // 删除缩略图
        if (!empty($meta['sizes'])) {
            foreach ($meta['sizes'] as $val) {
                $deleteObjects[] = $dirname . $val['file'];
            }
        }

        oss_delete_oss_files($deleteObjects);
    } else {
        // 获取链接删除
        $link = wp_get_attachment_url($post_id);
        if ($link) {
            if ($upload_path != '.') {
                $file_info = explode($upload_path, $link);
                if (count($file_info) >= 2) {
                    oss_delete_oss_file($upload_path . end($file_info));
                }
            } else {
                $oss_options = get_option('oss_options', true);
                $oss_upload_url = esc_attr($oss_options['upload_url_path']);
                $file_info = explode($oss_upload_url, $link);
                if (count($file_info) >= 2) {
                    oss_delete_oss_file(end($file_info));
                }
            }
        }
    }
}
add_action('delete_attachment', 'oss_delete_remote_attachment');

// 当upload_path为根目录时，需要移除URL中出现的“绝对路径”
function oss_modefiy_img_url($url, $post_id)
{
    // 移除 ./ 和 项目根路径
    return str_replace(['./', get_home_path()], '', $url);
}

if (oss_get_option('upload_path') == '.') {
    add_filter('wp_get_attachment_url', 'oss_modefiy_img_url', 30, 2);
}

function oss_sanitize_file_name($filename)
{
    $oss_options = get_option('oss_options');
    switch ($oss_options['update_file_name']) {
        case 'md5':
            return md5($filename) . '.' . pathinfo($filename, PATHINFO_EXTENSION);
        case 'time':
            return date('YmdHis', current_time('timestamp'))  . mt_rand(100, 999) . '.' . pathinfo($filename, PATHINFO_EXTENSION);
        default:
            return $filename;
    }
}

add_filter('sanitize_file_name', 'oss_sanitize_file_name', 10, 1);

/**
 * @param $homePath
 * @param $uploadPath
 * @return array
 */
function oss_read_dir_queue($homePath, $uploadPath)
{
    $dir = $homePath . $uploadPath;
    $dirsToProcess = new SplQueue();
    $dirsToProcess->enqueue([$dir, '']);
    $foundFiles = [];

    while (!$dirsToProcess->isEmpty()) {
        [$currentDir, $relativeDir] = $dirsToProcess->dequeue();

        foreach (new DirectoryIterator($currentDir) as $fileInfo) {
            if ($fileInfo->isDot()) continue;

            $filepath = $fileInfo->getRealPath();

            // Compute the relative path of the file/directory with respect to upload path
            $currentRelativeDir = "{$relativeDir}/{$fileInfo->getFilename()}";

            if ($fileInfo->isDir()) {
                $dirsToProcess->enqueue([$filepath, $currentRelativeDir]);
            } else {
                // Add file path and key to the result array
                $foundFiles[] = [
                    'filepath' => $filepath,
                    'key' => '/' . $uploadPath . $currentRelativeDir
                ];
            }
        }
    }

    return $foundFiles;
}

// 在插件列表页添加设置按钮
function oss_plugin_action_links($links, $file)
{
    if ($file == plugin_basename(dirname(__FILE__) . '/aliyun-oss-wordpress.php')) {
        $links[] = '<a href="options-general.php?page=' . OSS_BASEFOLDER . '/aliyun-oss-wordpress.php">设置</a>';
        $links[] = '<a href="https://donate.qq52o.me" target="_blank">Donate</a>';
    }
    return $links;
}
add_filter('plugin_action_links', 'oss_plugin_action_links', 10, 2);

function oss_custom_image_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id)
{
    $option = get_option('oss_options');
    $style = !empty($option['style']) ? esc_attr($option['style']) : '';
    $upload_url_path = esc_attr($option['upload_url_path']);
    if (empty($style)) {
        return $sources;
    }

    foreach ($sources as $index => $source) {
        if (!isset($source['url']) || !oss_is_image_type($source['url'])) {
            continue;
        }
        if (strpos($source['url'], $upload_url_path) !== false && strpos($source['url'], $style) === false) {
            $sources[$index]['url'] .= $style;
        }
    }

    return $sources;
}

add_filter('wp_calculate_image_srcset', 'oss_custom_image_srcset', 10, 5);

add_filter('wp_prepare_attachment_for_js', 'oss_wp_prepare_attachment_for_js', 10);
function oss_wp_prepare_attachment_for_js($response)
{
    if (empty($response['filesizeInBytes']) || empty($response['filesizeHumanReadable'])) {
        $upload_url_path = get_option('upload_url_path');
        $upload_path = get_option('upload_path');
        $object = str_replace($upload_url_path, $upload_path, $response['url']);
        $meta = oss_get_file_meta($object);
        if (!empty($meta['content-length'])) {
            $response['filesizeInBytes'] = $meta['content-length'];
            $response['filesizeHumanReadable'] = size_format($meta['content-length']);
        }
    }

    return $response;
}

add_filter('the_content', 'oss_setting_content_style');
function oss_setting_content_style($content)
{
    $option = get_option('oss_options');
    $upload_url_path = esc_attr($option['upload_url_path']);
    if (!empty($option['style'])) {
        $style = esc_attr($option['style']);
        preg_match_all('/<img.*?(?: |\\t|\\r|\\n)?src=[\'"]?(.+?)[\'"]?(?:(?: |\\t|\\r|\\n)+.*?)?>/sim', $content, $images);
        if (!empty($images) && isset($images[1])) {
            $images[1] = array_unique($images[1]);
            foreach ($images[1] as $item) {
                if (!oss_is_image_type($item)) {
                    continue;
                }
                if (strpos($item, $upload_url_path) !== false && strpos($item, $style) === false) {
                    $content = str_replace($item, $item . $style, $content);
                }
            }

            $content = str_replace($style . $style, $style, $content);
        }
    }
    return $content;
}

add_filter('post_thumbnail_html', 'oss_setting_post_thumbnail_style', 10, 3);
function oss_setting_post_thumbnail_style($html, $post_id, $post_image_id)
{
    $option = get_option('oss_options');
    $upload_url_path = esc_attr($option['upload_url_path']);
    if (!empty($option['style']) && has_post_thumbnail()) {
        $style = esc_attr($option['style']);
        preg_match_all('/<img.*?(?: |\\t|\\r|\\n)?src=[\'"]?(.+?)[\'"]?(?:(?: |\\t|\\r|\\n)+.*?)?>/sim', $html, $images);
        if (!empty($images) && isset($images[1])) {
            $images[1] = array_unique($images[1]);
            foreach ($images[1] as $item) {
                if (!oss_is_image_type($item)) {
                    continue;
                }
                if (strpos($item, $upload_url_path) !== false && strpos($item, $style) === false) {
                    $html = str_replace($item, $item . $style, $html);
                }
            }

            $html = str_replace($style . $style, $style, $html);
        }
    }
    return $html;
}

/**
 * @link https://help.aliyun.com/zh/oss/user-guide/regions-and-endpoints
 * @param string $regional
 * @return void
 */
function oss_get_regional($regional)
{
    $options = [
        'oss-accelerate' => '全球加速',
        'oss-accelerate-overseas' => '非中国内地加速',
        'oss-rg-china-mainland' => '无地域属性（中国内地）',
        'oss-cn-hangzhou' => '华东 1（杭州）',
        'oss-cn-shanghai' => '华东 2（上海）',
        'oss-cn-nanjing' => '华东 5（南京-本地地域）',
        'oss-cn-fuzhou' => '华东 6（福州-本地地域）',
        'oss-cn-wuhan' => '华中 1（武汉-本地地域）',
        'oss-cn-qingdao' => '华北 1（青岛）',
        'oss-cn-beijing' => '华北 2（北京）',
        'oss-cn-zhangjiakou' => '华北 3（张家口）',
        'oss-cn-huhehaote' => '华北 5（呼和浩特）',
        'oss-cn-wulanchabu' => '华北 6（乌兰察布）',
        'oss-cn-shenzhen' => '华南 1（深圳）',
        'oss-cn-heyuan' => '华南 2（河源）',
        'oss-cn-guangzhou' => '华南 3（广州）',
        'oss-cn-chengdu' => '西南 1（成都）',
        'oss-cn-hongkong' => '中国（香港）',
        'oss-us-west-1' => '美国西部 1 （硅谷）',
        'oss-us-east-1' => '美国东部 1 （弗吉尼亚）',
        'oss-ap-southeast-1' => '新加坡',
        'oss-ap-southeast-2' => '澳大利亚（悉尼）',
        'oss-ap-southeast-3' => '马来西亚（吉隆坡）',
        'oss-ap-southeast-5' => '印度尼西亚（雅加达）',
        'oss-ap-southeast-6' => '菲律宾（马尼拉）',
        'oss-ap-southeast-7' => '泰国（曼谷）',
        'oss-ap-northeast-1' => '日本（东京）',
        'oss-ap-northeast-2' => '韩国（首尔）',
        'oss-ap-south-1' => '印度（孟买）',
        'oss-eu-central-1' => '德国（法兰克福）',
        'oss-eu-west-1' => '英国（伦敦）',
        'oss-me-east-1' => '阿联酋（迪拜）',
        'oss-cn-hzfinance' => '杭州金融云公网',
        'oss-cn-shanghai-finance-1-pub' => '上海金融云公网',
        'oss-cn-szfinance' => '深圳金融云公网',
        'cn-beijing-finance-1' => '北京金融云公网',
    ];

    foreach ($options as $value => $text) {
        $selected = ($regional == $value) ? 'selected="selected"' : '';
        echo "<option value=\"{$value}\" {$selected}>{$text}</option>";
    }
}

function oss_get_option($key)
{
    return esc_attr(get_option($key));
}

$oss_options = get_option('oss_options', true);
if (!empty($oss_options['origin_protect']) && esc_attr($oss_options['origin_protect']) === 'on' && !empty(esc_attr($oss_options['style']))) {
    add_filter('wp_get_attachment_url', 'oss_add_suffix_to_attachment_url', 10, 2);
    add_filter('wp_get_attachment_thumb_url', 'oss_add_suffix_to_attachment_url', 10, 2);
    add_filter('wp_get_original_image_url', 'oss_add_suffix_to_attachment_url', 10, 2);
    add_filter('wp_prepare_attachment_for_js', 'oss_add_suffix_to_attachment', 10, 2);
    add_filter('image_get_intermediate_size', 'oss_add_suffix_for_media_send_to_editor');
}

/**
 * @param string $url
 * @param int $post_id
 * @return string
 */
function oss_add_suffix_to_attachment_url($url, $post_id)
{
    if (oss_is_image_type($url)) {
        $url .= oss_get_image_style();
    }

    return $url;
}

/**
 * @param array $response
 * @param array $attachment
 * @return array
 */
function oss_add_suffix_to_attachment($response, $attachment)
{
    if ($response['type'] != 'image') {
        return $response;
    }

    $style = oss_get_image_style();
    if (!empty($response['sizes'])) {
        foreach ($response['sizes'] as $size_key => $size_file) {
            if (oss_is_image_type($size_file['url'])) {
                $response['sizes'][$size_key]['url'] .= $style;
            }
        }
    }

    if(!empty($response['originalImageURL'])) {
        if (oss_is_image_type($response['originalImageURL'])) {
            $response['originalImageURL'] .= $style;
        }
    }

    return $response;
}

/**
 * @param array $data
 * @return array
 */
function oss_add_suffix_for_media_send_to_editor($data)
{
    // https://github.com/WordPress/wordpress-develop/blob/43d2455dc68072fdd43c3c800cc8c32590f23cbe/src/wp-includes/media.php#L239
    if (oss_is_image_type($data['file'])) {
        $data['file'] .= oss_get_image_style();
    }

    return $data;
}

/**
 * @param string $url
 * @return bool
 */
function oss_is_image_type($url)
{
    return (bool) preg_match('/\.(jpg|jpeg|jpe|png|bmp|webp|heic|heif|svg)$/i', $url);
}

/**
 * @return string
 */
function oss_get_image_style()
{
    $oss_options = get_option('oss_options', true);

    return esc_attr($oss_options['style']);
}

// 在导航栏“设置”中添加条目
function oss_add_setting_page()
{
    add_options_page('阿里云 OSS', '阿里云 OSS', 'manage_options', __FILE__, 'oss_setting_page');
}

add_action('admin_menu', 'oss_add_setting_page');

// 插件设置页面
function oss_setting_page()
{
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient privileges!');
    }
    $options = [];
    if (!empty($_POST) and $_POST['type'] == 'oss_set') {
        $nonce = $_POST['update_oss_config-nonce'] ?? '';
        if (empty($nonce) || !wp_verify_nonce($nonce, 'update_oss_config')) {
            wp_die('Illegal requests!');
        }

        $options['bucket'] = isset($_POST['bucket']) ? sanitize_text_field($_POST['bucket']) : '';
        $options['regional'] = isset($_POST['regional']) ? sanitize_text_field($_POST['regional']) : '';
        $options['role_name'] = isset($_POST['role_name']) ? sanitize_text_field($_POST['role_name']) : '';
        $options['accessKeyId'] = isset($_POST['accessKeyId']) ? sanitize_text_field($_POST['accessKeyId']) : '';
        $options['accessKeySecret'] = isset($_POST['accessKeySecret']) ? sanitize_text_field($_POST['accessKeySecret']) : '';
        $options['is_internal'] = isset($_POST['is_internal']) ? 'true' : 'false';
        $options['nothumb'] = isset($_POST['nothumb']) ? 'true' : 'false';
        $options['nolocalsaving'] = isset($_POST['nolocalsaving']) ? 'true' : 'false';
        //仅用于插件卸载时比较使用
        $options['upload_url_path'] = isset($_POST['upload_url_path']) ? sanitize_text_field(stripslashes($_POST['upload_url_path'])) : '';
        $options['style'] = isset($_POST['style']) ? sanitize_text_field($_POST['style']) : '';
        $options['update_file_name'] = isset($_POST['update_file_name']) ? sanitize_text_field($_POST['update_file_name']) : 'false';
        $options['origin_protect'] = isset($_POST['origin_protect']) ? sanitize_text_field($_POST['origin_protect']) : 'off';

        if ($options['regional'] === 'oss-rg-china-mainland' && $options['is_internal'] === 'true') {
            echo '<div class="error"><p><strong>无地域属性不支持内网，请重新填写配置！</strong></p></div>';
            $options = [];
        }
    }

    if (!empty($_POST) and $_POST['type'] == 'aliyun_oss_all') {
        $files = oss_read_dir_queue(get_home_path(), oss_get_option('upload_path'));
        foreach ($files as $file) {
            oss_file_upload($file['key'], $file['filepath']);
        }
        echo '<div class="updated"><p><strong>本次操作成功同步' . count($files) . '个文件</strong></p></div>';
    }

    // 替换数据库链接
    if(!empty($_POST) and $_POST['type'] == 'aliyun_oss_replace') {
        $nonce = $_POST['update_oss_replace-nonce'] ?? '';
        if (empty($nonce) || !wp_verify_nonce($nonce, 'update_oss_replace')) {
            wp_die('Illegal requests!');
        }

        $old_url = esc_url_raw($_POST['old_url']);
        $new_url = esc_url_raw($_POST['new_url']);

        if (!empty($old_url) && !empty($new_url)) {
            global $wpdb;
            $posts_name = $wpdb->prefix . 'posts';
            // 文章内容
            $posts_result = $wpdb->query($wpdb->prepare("UPDATE $posts_name SET post_content = REPLACE(post_content, '%s', '%s')", [$old_url, $new_url]));

            // 修改题图之类的
            $postmeta_name = $wpdb->prefix . 'postmeta';
            $postmeta_result = $wpdb->query($wpdb->prepare("UPDATE $postmeta_name SET meta_value = REPLACE(meta_value, '%s', '%s')", [$old_url, $new_url]));

            echo '<div class="updated"><p><strong>替换成功！共替换文章内链'.$posts_result.'条、题图链接'.$postmeta_result.'条！</strong></p></div>';
        } else {
            echo '<div class="error"><p><strong>请填写资源链接URL地址！</strong></p></div>';
        }
    }

    // 若$options不为空数组，则更新数据
    if ($options !== []) {
        //更新数据库
        update_option('oss_options', $options);

        $upload_path = sanitize_text_field(trim(stripslashes($_POST['upload_path']), '/'));
        $upload_path = $upload_path == '' ? 'wp-content/uploads' : $upload_path;
        update_option('upload_path', $upload_path);
        $upload_url_path = sanitize_text_field(trim(stripslashes($_POST['upload_url_path']), '/'));
        update_option('upload_url_path', $upload_url_path);
        echo '<div class="updated"><p><strong>设置已保存！</strong></p></div>';
    }

    $oss_options = get_option('oss_options', true);

    $oss_regional = esc_attr($oss_options['regional']);

    $oss_is_internal = esc_attr($oss_options['is_internal']);
    $oss_is_internal = $oss_is_internal == 'true';

    $oss_nothumb = esc_attr($oss_options['nothumb']);
    $oss_nothumb = $oss_nothumb == 'true';

    $oss_nolocalsaving = esc_attr($oss_options['nolocalsaving']);
    $oss_nolocalsaving = $oss_nolocalsaving == 'true';
    $oss_update_file_name = esc_attr($oss_options['update_file_name']);
    $oss_origin_protect = esc_attr($oss_options['origin_protect'] ?? 'off') !== 'off' ? 'checked="checked"' : '';

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
?>
    <div class="wrap" style="margin: 10px;">
        <h1>阿里云 OSS <span style="font-size: 13px;">当前版本：<?php echo OSS_VERSION; ?></span></h1>
        <p>如果觉得此插件对你有所帮助，不妨到 <a href="https://github.com/sy-records/aliyun-oss-wordpress" target="_blank">GitHub</a> 上点个<code>Star</code>，<code>Watch</code>关注更新；<a href="https://go.qq52o.me/qm/ccs" target="_blank">欢迎加入云存储插件交流群，QQ群号：887595381</a>；</p>
        <hr/>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th>
                        <legend>Bucket名称</legend>
                    </th>
                    <td>
                        <input type="text" name="bucket" value="<?php echo esc_attr($oss_options['bucket']); ?>" size="50" placeholder="请填写Bucket名称"/>

                        <p>请先访问 <a href="https://oss.console.aliyun.com/bucket" target="_blank">阿里云控制台</a> 创建<code>Bucket</code>，再填写以上内容。</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>区域</legend>
                    </th>
                    <td>
                        <select name="regional">
                            <?php oss_get_regional($oss_regional); ?>
                        </select>
                        <p>请选择您创建的<code>Bucket</code>所在区域</p>
                    </td>
                </tr>
                <tr>
                  <th>
                    <legend>Role Name</legend>
                  </th>
                  <td>
                    <input type="text" name="role_name" value="<?php echo esc_attr($oss_options['role_name'] ?? ''); ?>" size="50" placeholder="RAM角色名称"/>
                    <p>在ECS上通过实例RAM角色的方式访问OSS，非必填，如需使用请填写，不懂请保持为空。</p>
                  </td>
                </tr>
                <tr>
                    <th>
                        <legend>AccessKeyId</legend>
                    </th>
                    <td><input type="text" name="accessKeyId" value="<?php echo esc_attr($oss_options['accessKeyId']); ?>" size="50" placeholder="AccessKeyId"/></td>
                </tr>
                <tr>
                    <th>
                        <legend>AccessKeySecret</legend>
                    </th>
                    <td>
                        <input type="password" name="accessKeySecret" value="<?php echo esc_attr($oss_options['accessKeySecret']); ?>" size="50" placeholder="AccessKeySecret"/>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>是否使用内网传输</legend>
                    </th>
                    <td>
                        <input type="checkbox" name="is_internal" <?php echo $oss_is_internal ? 'checked="checked"' : ''; ?> />
                        <p>如果你的服务器是在阿里云并且区域和<code>Bucket</code>所在区域一致，请勾选。</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>不上传缩略图</legend>
                    </th>
                    <td>
                        <input type="checkbox" name="nothumb" <?php echo $oss_nothumb ? 'checked="checked"' : ''; ?> />
                        <p>建议不勾选</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>不在本地保留备份</legend>
                    </th>
                    <td>
                        <input type="checkbox" name="nolocalsaving" <?php echo $oss_nolocalsaving ? 'checked="checked"' : ''; ?> />
                        <p>建议不勾选</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>自动重命名文件</legend>
                    </th>
                    <td>
                        <select name="update_file_name">
                            <option <?php echo $oss_update_file_name == 'false' ? 'selected="selected"' : ''; ?> value="false">不处理</option>
                            <option <?php echo $oss_update_file_name == 'md5' ? 'selected="selected"' : ''; ?> value="md5">MD5</option>
                            <option <?php echo $oss_update_file_name == 'time' ? 'selected="selected"' : ''; ?> value="time">时间戳+随机数</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>本地文件夹</legend>
                    </th>
                    <td>
                        <input type="text" name="upload_path" value="<?php echo oss_get_option('upload_path'); ?>" size="50" placeholder="请输入上传文件夹"/>
                        <p>附件在服务器上的存储位置，例如： <code>wp-content/uploads</code> （注意不要以“/”开头和结尾），根目录请输入<code>.</code>。</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>URL前缀</legend>
                    </th>
                    <td>
                        <input type="text" name="upload_url_path" value="<?php echo oss_get_option('upload_url_path'); ?>" size="50" placeholder="请输入URL前缀"/>

                        <p><b>注意：</b></p>

                        <p>1）URL前缀的格式为 <code><?php echo $protocol;?>{OSS外网访问Bucket域名}/{本地文件夹}</code> ，“本地文件夹”务必与上面保持一致（结尾无 <code>/</code> ），或者“本地文件夹”为 <code>.</code> 时 <code><?php echo $protocol;?>{OSS外网访问域名}</code> 。</p>

                        <p>2）OSS中的存放路径（即“文件夹”）与上述 <code>本地文件夹</code> 中定义的路径是相同的（出于方便切换考虑）。</p>

                        <p>3）如果需要使用 <code>用户域名</code> ，直接将 <code>{OSS外网访问Bucket域名}</code> 替换为 <code>用户域名</code> 即可。</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>图片处理</legend>
                    </th>
                    <td>
                        <input type="text" name="style" value="<?php echo esc_attr($oss_options['style']); ?>" size="50" placeholder="请输入图片处理样式，留空表示不处理"/>

                        <p><b>获取样式：</b></p>

                        <p>1）在阿里云 <a href="https://oss.console.aliyun.com/bucket" target="_blank">OSS管理控制台</a> 对应的 Bucket 中数据处理 -> 图片处理 中新建样式。具体样式设置参考<a href="https://help.aliyun.com/document_detail/48884.html" target="_blank">阿里云文档</a>。</p>

                        <p>2）填写时需要将<code>默认规则</code>或<code>自定义分隔符</code>和对应的创建<code>规则名称</code>进行拼接，例如：</p>

                        <p>① <code>默认规则</code>为<code>?x-oss-process=style/</code>，<code>规则名称</code>为<code>stylename</code></p>
                        <p>则填写为 <code>?x-oss-process=style/stylename</code></p>

                        <p>② <code>分隔符</code>为<code>!</code>(感叹号)，<code>规则名称</code>为<code>stylename</code></p>
                        <p>则填写为 <code>!stylename</code></p>
                    </td>
                </tr>
                <tr>
                  <th>
                    <legend>原图保护</legend>
                  </th>
                  <td>
                    <input type="checkbox" name="origin_protect" <?php echo $oss_origin_protect; ?> />

                    <p>开启原图保护功能后，存储桶中的图片文件仅能以带样式的 URL 进行访问，能够阻止恶意用户对源文件的请求。</p>
                    <p>使用时请先访问阿里云对象存储控制台<b>开启原图保护</b>并设置<b>图片处理样式</b>！</p>
                    <p>注：此功能为实验性功能，如遇错误或不可用，请关闭后联系作者反馈。</p>
                  </td>
                </tr>
                <tr>
                    <th><legend>保存/更新选项</legend></th>
                    <td><input type="submit" class="button button-primary" value="保存更改"/></td>
                    <?php wp_nonce_field('update_oss_config', 'update_oss_config-nonce'); ?>
                </tr>
            </table>
            <input type="hidden" name="type" value="oss_set">
        </form>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th>
                        <legend>同步历史附件</legend>
                    </th>
                    <input type="hidden" name="type" value="aliyun_oss_all">
                    <td>
                        <input type="submit" class="button button-secondary" value="开始同步"/>
                        <p><b>注意：如果是首次同步，执行时间将会十分十分长（根据你的历史附件数量），有可能会因执行时间过长，页面显示超时或者报错。<br> 所以，建议那些几千上万附件的大神们，考虑官方的 <a target="_blank" rel="nofollow" href="https://help.aliyun.com/knowledge_detail/39628.html">同步工具</a></b></p>
                    </td>
                </tr>
            </table>
        </form>
        <hr>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th>
                        <legend>数据库原链接替换</legend>
                    </th>
                    <td>
                        <input type="text" name="old_url" size="50" placeholder="请输入要替换的旧域名"/>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend></legend>
                    </th>
                    <td>
                        <input type="text" name="new_url" size="50" placeholder="请输入要替换的新域名"/>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend></legend>
                    </th>
                    <input type="hidden" name="type" value="aliyun_oss_replace">
                    <?php wp_nonce_field('update_oss_replace', 'update_oss_replace-nonce'); ?>
                    <td>
                        <input type="submit" class="button button-secondary" value="开始替换"/>
                        <p><b>注意：如果是首次替换，请注意备份！此功能会替换文章以及设置的特色图片（题图）等使用的资源链接</b></p>
                    </td>
                </tr>
            </table>
        </form>
    </div>
<?php
}
?>
