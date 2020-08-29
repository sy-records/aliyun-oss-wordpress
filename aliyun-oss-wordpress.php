<?php
/*
Plugin Name: OSS Aliyun
Plugin URI: https://github.com/sy-records/aliyun-oss-wordpress
Description: 使用阿里云对象存储 OSS 作为附件存储空间。（This is a plugin that uses Aliyun Object Storage Service for attachments remote saving.）
Version: 1.2.1
Author: 沈唁
Author URI: https://qq52o.me
License: Apache 2.0
*/

require_once 'sdk/vendor/autoload.php';

use OSS\OssClient;
use OSS\Core\OssException;

define('OSS_VERSION', '1.2.1');
define('OSS_BASEFOLDER', plugin_basename(dirname(__FILE__)));

// 初始化选项
register_activation_hook(__FILE__, 'oss_set_options');
// 初始化选项
function oss_set_options()
{
    $options = array(
        'bucket' => "",
        'regional' => "oss-cn-shanghai",
        'accessKeyId' => "",
        'accessKeySecret' => "",
        'is_internal' => "false",
        'nothumb' => "false", // 是否上传缩略图
        'nolocalsaving' => "false", // 是否保留本地备份
        'upload_url_path' => "", // URL前缀
        'style' => '', // 图片处理
    );
    add_option('oss_options', $options, '', 'yes');
}

function oss_get_client()
{
    $oss_opt = get_option('oss_options', true);
    $accessKeyId = esc_attr($oss_opt['accessKeyId']);
    $accessKeySecret = esc_attr($oss_opt['accessKeySecret']);
    $endpoint = oss_get_bucket_endpoint($oss_opt);
    return new OssClient($accessKeyId, $accessKeySecret, $endpoint);
}

function oss_get_bucket_endpoint($oss_option)
{
    $oss_regional = esc_attr($oss_option['regional']);
    if ($oss_option['is_internal'] == "true") {
        return $oss_regional . "-internal.aliyuncs.com";
    }
    return $oss_regional . ".aliyuncs.com";
}

function oss_get_bucket_name()
{
    $oss_opt = get_option('oss_options', true);
    return $oss_opt['bucket'];
}

/**
 * @param $object
 * @param $file
 * @param false $no_local_file
 */
function oss_file_upload($object, $file, $no_local_file = false)
{
    //如果文件不存在，直接返回false
    if (!@file_exists($file)) {
        return false;
    }
    $bucket = oss_get_bucket_name();
    $ossClient = oss_get_client();
    try{
        $ossClient->uploadFile($bucket, ltrim($object, "/"), $file);
    } catch ( OssException $e ) {
//        echo 'Error Message:' . $e->getMessage() . PHP_EOL;
//        echo 'Error Code:' . $e->getCode() . PHP_EOL;
    }
    if ($no_local_file) {
        oss_delete_local_file($file);
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
    return (esc_attr($oss_options['nolocalsaving']) == 'true');
}

/**
 * 删除本地文件
 *
 * @param $file
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
    } catch (Exception $ex) {
        return false;
    }
}

/**
 * 删除oss中的单个文件
 * @param $file
 * @return bool
 */
function oss_delete_oss_file($file)
{
    $bucket = oss_get_bucket_name();
    $ossClient = oss_get_client();
    $ossClient->deleteObject($bucket, $file);
}

/**
 * 批量删除文件
 * @param $files
 */
function oss_delete_oss_files(array $files)
{
    $bucket = oss_get_bucket_name();
    $ossClient = oss_get_client();
    $ossClient->deleteObjects($bucket, $files);
}

/**
 * 上传附件（包括图片的原图）
 *
 * @param  $metadata
 * @return array()
 */
function oss_upload_attachments($metadata)
{
    $mime_types = get_allowed_mime_types();
    $image_mime_types = array(
        $mime_types['jpg|jpeg|jpe'],
        $mime_types['gif'],
        $mime_types['png'],
        $mime_types['bmp'],
        $mime_types['tiff|tif'],
        $mime_types['ico'],
    );
    // 例如mp4等格式 上传后根据配置选择是否删除 删除后媒体库会显示默认图片 点开内容是正常的
    // 图片在缩略图处理
    if (!in_array($metadata['type'], $image_mime_types)) {
        //生成object在oss中的存储路径
        if (get_option('upload_path') == '.') {
            $metadata['file'] = str_replace("./", '', $metadata['file']);
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

//避免上传插件/主题时出现同步到oss的情况
if (substr_count($_SERVER['REQUEST_URI'], '/update.php') <= 0) {
    add_filter('wp_handle_upload', 'oss_upload_attachments', 50);
}

/**
 * 上传图片的缩略图
 */
function oss_upload_thumbs($metadata)
{
    //获取上传路径
    $wp_uploads = wp_upload_dir();
    $basedir = $wp_uploads['basedir'];
    //获取oss插件的配置信息
    $oss_options = get_option('oss_options', true);
    if (isset($metadata['file'])) {
        // Maybe there is a problem with the old version
        $file = $basedir . '/' . $metadata['file'];
        $upload_path = get_option('upload_path');
        if ($upload_path != '.') {
            $path_array = explode($upload_path, $file);
            if (isset($path_array[1]) && !empty($path_array[1])) {
                $object = '/' . $upload_path . $path_array[1];
            }
        } else {
            $object = '/' . $metadata['file'];
            $file = str_replace('./', '', $file);
        }

        oss_file_upload($object, $file, (esc_attr($oss_options['nolocalsaving']) == 'true'));
    }
    //上传所有缩略图
    if (isset($metadata['sizes']) && count($metadata['sizes']) > 0) {
        //是否需要上传缩略图
        $nothumb = (esc_attr($oss_options['nothumb']) == 'true');
        //如果禁止上传缩略图，就不用继续执行了
        if ($nothumb) {
            return $metadata;
        }
        //得到本地文件夹和远端文件夹
        $file_path = $basedir . '/' . dirname($metadata['file']) . '/';
        $file_path = str_replace("\\", '/', $file_path);
        if ($upload_path == '.') {
            $file_path = str_replace('./', '', $file_path);
        }

        $object_path = str_replace(get_home_path(), '', $file_path);

        //there may be duplicated filenames,so ....
        foreach ($metadata['sizes'] as $val) {
            //生成object在oss中的存储路径
            $object = '/' . $object_path . $val['file'];
            //生成本地存储路径
            $file = $file_path . $val['file'];

            //执行上传操作
            oss_file_upload($object, $file, (esc_attr($oss_options['nolocalsaving']) == 'true'));
        }
    }
    return $metadata;
}

//避免上传插件/主题时出现同步到oss的情况
if (substr_count($_SERVER['REQUEST_URI'], '/update.php') <= 0) {
    add_filter('wp_generate_attachment_metadata', 'oss_upload_thumbs', 100);
}

/**
 * 删除远端文件，删除文件时触发
 * @param $post_id
 */
function oss_delete_remote_attachment($post_id) {
    $meta = wp_get_attachment_metadata( $post_id );

    if (isset($meta['file'])) {
        $deleteObjects = [];

        // meta['file']的格式为 "2020/01/wp-bg.png"
        $upload_path = get_option('upload_path');
        if ($upload_path == '') {
            $upload_path = 'wp-content/uploads';
        }
        $file_path = $upload_path . '/' . $meta['file'];

        $deleteObjects[] = str_replace("\\", '/', $file_path);

        $oss_options = get_option('oss_options', true);
        $is_nothumb = (esc_attr($oss_options['nothumb']) == 'false');
        if ($is_nothumb) {
            // 删除缩略图
            if (isset($meta['sizes']) && count($meta['sizes']) > 0) {
                foreach ($meta['sizes'] as $val) {
                    $size_file = dirname($file_path) . '/' . $val['file'];
                    $deleteObjects[] = str_replace("\\", '/', $size_file);
                }
            }
        }

        oss_delete_oss_files($deleteObjects);
    }
}
add_action('delete_attachment', 'oss_delete_remote_attachment');

// 当upload_path为根目录时，需要移除URL中出现的“绝对路径”
function oss_modefiy_img_url($url, $post_id)
{
    // 移除 ./ 和 项目根路径
    $url = str_replace(array('./', get_home_path()), array('', ''), $url);
    return $url;
}

if (get_option('upload_path') == '.') {
    add_filter('wp_get_attachment_url', 'oss_modefiy_img_url', 30, 2);
}

function oss_function_each(&$array)
{
    $res = array();
    $key = key($array);
    if ($key !== null) {
        next($array);
        $res[1] = $res['value'] = $array[$key];
        $res[0] = $res['key'] = $key;
    } else {
        $res = false;
    }
    return $res;
}

/**
 * @param $dir
 * @return array
 */
function oss_read_dir_queue($dir)
{
    $dd = [];
    if (isset($dir)) {
        $files = array();
        $queue = array($dir);
        while ($data = oss_function_each($queue)) {
            $path = $data['value'];
            if (is_dir($path) && $handle = opendir($path)) {
                while ($file = readdir($handle)) {
                    if ($file == '.' || $file == '..') {
                        continue;
                    }
                    $files[] = $real_path = $path . '/' . $file;
                    if (is_dir($real_path)) {
                        $queue[] = $real_path;
                    }
                    //echo explode(get_option('upload_path'),$path)[1];
                }
            }
            closedir($handle);
        }
        $upload_path = get_option('upload_path');
        foreach ($files as $v) {
            if (!is_dir($v)) {
                $dd[] = ['filepath' => $v, 'key' =>  '/' . $upload_path . explode($upload_path, $v)[1]];
            }
        }
    }

    return $dd;
}

// 在插件列表页添加设置按钮
function oss_plugin_action_links($links, $file)
{
    if ($file == plugin_basename(dirname(__FILE__) . '/aliyun-oss-wordpress.php')) {
        $links[] = '<a href="options-general.php?page=' . OSS_BASEFOLDER . '/aliyun-oss-wordpress.php">设置</a>';
        $links[] = '<a href="https://qq52o.me/sponsor.html" target="_blank">赞赏</a>';
    }
    return $links;
}
add_filter('plugin_action_links', 'oss_plugin_action_links', 10, 2);

add_filter('the_content', 'oss_setting_content_style');
function oss_setting_content_style($content)
{
    $option = get_option('oss_options');
    if (!empty($option['style'])) {
        preg_match_all('/<img.*?(?: |\\t|\\r|\\n)?src=[\'"]?(.+?)[\'"]?(?:(?: |\\t|\\r|\\n)+.*?)?>/sim', $content, $images);
        if (!empty($images) && isset($images[1])) {
            foreach ($images[1] as $item) {
                if(strpos($item, $option['upload_url_path']) !== false){
                    $content = str_replace($item, $item . $option['style'], $content);
                }
            }
        }
    }
    return $content;
}

add_filter('post_thumbnail_html', 'oss_setting_post_thumbnail_style', 10, 3);
function oss_setting_post_thumbnail_style( $html, $post_id, $post_image_id )
{
    $option = get_option('oss_options');
    if (!empty($option['style']) && has_post_thumbnail()) {
        preg_match_all('/<img.*?(?: |\\t|\\r|\\n)?src=[\'"]?(.+?)[\'"]?(?:(?: |\\t|\\r|\\n)+.*?)?>/sim', $html, $images);
        if (!empty($images) && isset($images[1])) {
            foreach ($images[1] as $item) {
                if(strpos($item, $option['upload_url_path']) !== false){
                    $html = str_replace($item, $item . $option['style'], $html);
                }
            }
        }
    }
    return $html;
}

// 在导航栏“设置”中添加条目
function oss_add_setting_page()
{
    add_options_page('阿里云OSS设置', '阿里云OSS设置', 'manage_options', __FILE__, 'oss_setting_page');
}

add_action('admin_menu', 'oss_add_setting_page');

// 插件设置页面
function oss_setting_page()
{
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient privileges!');
    }
    $options = array();
    if (!empty($_POST) and $_POST['type'] == 'oss_set') {
        $options['bucket'] = isset($_POST['bucket']) ? sanitize_text_field($_POST['bucket']) : '';
        $options['regional'] = isset($_POST['regional']) ? sanitize_text_field($_POST['regional']) : '';
        $options['accessKeyId'] = isset($_POST['accessKeyId']) ? sanitize_text_field($_POST['accessKeyId']) : '';
        $options['accessKeySecret'] = isset($_POST['accessKeySecret']) ? sanitize_text_field($_POST['accessKeySecret']) : '';
        $options['is_internal'] = isset($_POST['is_internal']) ? 'true' : 'false';
        $options['nothumb'] = isset($_POST['nothumb']) ? 'true' : 'false';
        $options['nolocalsaving'] = isset($_POST['nolocalsaving']) ? 'true' : 'false';
        //仅用于插件卸载时比较使用
        $options['upload_url_path'] = isset($_POST['upload_url_path']) ? sanitize_text_field(stripslashes($_POST['upload_url_path'])) : '';
        $options['style'] = isset($_POST['style']) ? sanitize_text_field($_POST['style']) : '';
    }

    if (!empty($_POST) and $_POST['type'] == 'aliyun_oss_all') {
        $sync = oss_read_dir_queue(get_home_path() . get_option('upload_path'));
        foreach ($sync as $k) {
            oss_file_upload($k['key'], $k['filepath']);
        }
        echo '<div class="updated"><p><strong>本次操作成功同步' . count($sync) . '个文件</strong></p></div>';
    }

    // 替换数据库链接
    if(!empty($_POST) and $_POST['type'] == 'aliyun_oss_replace') {
        $old_url = esc_url_raw($_POST['old_url']);
        $new_url = esc_url_raw($_POST['new_url']);

        global $wpdb;
        $posts_name = $wpdb->prefix .'posts';
        // 文章内容
        $posts_result = $wpdb->query("UPDATE $posts_name SET post_content = REPLACE( post_content, '$old_url', '$new_url') ");

        // 修改题图之类的
        $postmeta_name = $wpdb->prefix .'postmeta';
        $postmeta_result = $wpdb->query("UPDATE $postmeta_name SET meta_value = REPLACE( meta_value, '$old_url', '$new_url') ");

        echo '<div class="updated"><p><strong>替换成功！共替换文章内链'.$posts_result.'条、题图链接'.$postmeta_result.'条！</strong></p></div>';
    }

    // 若$options不为空数组，则更新数据
    if ($options !== array()) {
        //更新数据库
        update_option('oss_options', $options);

        $upload_path = sanitize_text_field(trim(stripslashes($_POST['upload_path']), '/'));
        $upload_path = ($upload_path == '') ? ('wp-content/uploads') : ($upload_path);
        update_option('upload_path', $upload_path);
        $upload_url_path = sanitize_text_field(trim(stripslashes($_POST['upload_url_path']), '/'));
        update_option('upload_url_path', $upload_url_path);
        echo '<div class="updated"><p><strong>设置已保存！</strong></p></div>';
    }

    $oss_options = get_option('oss_options', true);
    $upload_path = get_option('upload_path');
    $upload_url_path = get_option('upload_url_path');

    $oss_bucket = esc_attr($oss_options['bucket']);
    $oss_regional = esc_attr($oss_options['regional']);
    $oss_accessKeyId = esc_attr($oss_options['accessKeyId']);
    $oss_accessKeySecret = esc_attr($oss_options['accessKeySecret']);

    $oss_is_internal = esc_attr($oss_options['is_internal']);
    $oss_is_internal =  ($oss_is_internal == 'true');

    $oss_nothumb = esc_attr($oss_options['nothumb']);
    $oss_nothumb = ($oss_nothumb == 'true');

    $oss_nolocalsaving = esc_attr($oss_options['nolocalsaving']);
    $oss_nolocalsaving = ($oss_nolocalsaving == 'true');

    $oss_style = esc_attr($oss_options['style']);
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    ?>
    <div class="wrap" style="margin: 10px;">
        <h1>阿里云 OSS 设置 <span style="font-size: 13px;">当前版本：<?php echo OSS_VERSION; ?></span></h1>
        <p>如果觉得此插件对你有所帮助，不妨到 <a href="https://github.com/sy-records/aliyun-oss-wordpress" target="_blank">GitHub</a> 上点个<code>Star</code>，<code>Watch</code>关注更新；<a href="//shang.qq.com/wpa/qunwpa?idkey=c7f4fbd7ef84184555dfb6377d8ae087b3d058d8eeae1ff8e2da25c00d53173f" target="_blank">欢迎加入云存储插件交流群,QQ群号:887595381</a>；</p>
        <hr/>
        <form name="form1" method="post" action="<?php echo wp_nonce_url('./options-general.php?page=' . OSS_BASEFOLDER . '/aliyun-oss-wordpress.php'); ?>">
            <table class="form-table">
                <tr>
                    <th>
                        <legend>Bucket名称</legend>
                    </th>
                    <td>
                        <input type="text" name="bucket" value="<?php echo $oss_bucket; ?>" size="50" placeholder="请填写Bucket名称"/>

                        <p>请先访问 <a href="https://oss.console.aliyun.com/bucket" target="_blank">阿里云控制台</a> 创建<code>Bucket</code>，再填写以上内容。</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>区域</legend>
                    </th>
                    <td><select name="regional">
                            <option value="oss-cn-hangzhou" <?php if ($oss_regional == 'oss-cn-hangzhou') {echo ' selected="selected"';}?>>华东 1（杭州）</option>
                            <option value="oss-cn-shanghai" <?php if ($oss_regional == 'oss-cn-shanghai') {echo ' selected="selected"';}?>>华东 2（上海）</option>
                            <option value="oss-cn-qingdao" <?php if ($oss_regional == 'oss-cn-qingdao') {echo ' selected="selected"';}?>>华北 1（青岛）</option>
                            <option value="oss-cn-beijing" <?php if ($oss_regional == 'oss-cn-beijing') {echo ' selected="selected"';}?>>华北 2（北京）</option>
                            <option value="oss-cn-zhangjiakou" <?php if ($oss_regional == 'oss-cn-zhangjiakou') {echo ' selected="selected"';}?>>华北 3（张家口）</option>
                            <option value="oss-cn-huhehaote" <?php if ($oss_regional == 'oss-cn-huhehaote') {echo ' selected="selected"';}?>>华北 5（呼和浩特）</option>
                            <option value="oss-cn-shenzhen" <?php if ($oss_regional == 'oss-cn-shenzhen') {echo ' selected="selected"';}?>>华南 1（深圳）</option>
                            <option value="oss-cn-heyuan" <?php if ($oss_regional == 'oss-cn-heyuan') {echo ' selected="selected"';}?>>华南 2（河源）</option>
                            <option value="oss-cn-chengdu" <?php if ($oss_regional == 'oss-cn-chengdu') {echo ' selected="selected"';}?>>西南 1（成都）</option>
                            <option value="oss-cn-hongkong" <?php if ($oss_regional == 'oss-cn-hongkong') {echo ' selected="selected"';}?>>中国（香港）</option>
                            <option value="oss-us-west-1" <?php if ($oss_regional == 'oss-us-west-1') {echo ' selected="selected"';}?>>美国西部 1 （硅谷）</option>
                            <option value="oss-us-east-1" <?php if ($oss_regional == 'oss-us-east-1') {echo ' selected="selected"';}?>>美国东部 1 （弗吉尼亚）</option>
                            <option value="oss-ap-southeast-1" <?php if ($oss_regional == 'oss-ap-southeast-1') {echo ' selected="selected"';}?>>亚太东南 1 （新加坡）</option>
                            <option value="oss-ap-southeast-2" <?php if ($oss_regional == 'oss-ap-southeast-2') {echo ' selected="selected"';}?>>亚太东南 2 （悉尼）</option>
                            <option value="oss-ap-southeast-3" <?php if ($oss_regional == 'oss-ap-southeast-3') {echo ' selected="selected"';}?>>亚太东南 3 （吉隆坡）</option>
                            <option value="oss-ap-southeast-5" <?php if ($oss_regional == 'oss-ap-southeast-5') {echo ' selected="selected"';}?>>亚太东南 5 （雅加达）</option>
                            <option value="oss-ap-northeast-1" <?php if ($oss_regional == 'oss-ap-northeast-1') {echo ' selected="selected"';}?>>亚太东北 1 （日本）</option>
                            <option value="oss-ap-south-1" <?php if ($oss_regional == 'oss-ap-south-1') {echo ' selected="selected"';}?>>亚太南部 1 （孟买）</option>
                            <option value="oss-eu-central-1" <?php if ($oss_regional == 'oss-eu-central-1') {echo ' selected="selected"';}?>>欧洲中部 1 （法兰克福）</option>
                            <option value="oss-eu-west-1" <?php if ($oss_regional == 'oss-eu-west-1') {echo ' selected="selected"';}?>>英国（伦敦）</option>
                            <option value="oss-me-east-1" <?php if ($oss_regional == 'oss-me-east-1') {echo ' selected="selected"';}?>>中东东部 1 （迪拜）</option>
                        </select>
                        <p>请选择您创建的<code>Bucket</code>所在区域</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>AccessKeyId</legend>
                    </th>
                    <td><input type="text" name="accessKeyId" value="<?php echo $oss_accessKeyId; ?>" size="50" placeholder="AccessKeyId"/></td>
                </tr>
                <tr>
                    <th>
                        <legend>AccessKeySecret</legend>
                    </th>
                    <td>
                        <input type="text" name="accessKeySecret" value="<?php echo $oss_accessKeySecret; ?>" size="50" placeholder="AccessKeySecret"/>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>是否使用内网传输</legend>
                    </th>
                    <td>
                        <input type="checkbox" name="is_internal" <?php if ($oss_is_internal) { echo 'checked="checked"'; } ?> />
                        <p>如果你的服务器是在阿里云并且区域和<code>Bucket</code>所在区域一致，请勾选。</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>不上传缩略图</legend>
                    </th>
                    <td>
                        <input type="checkbox" name="nothumb" <?php if ($oss_nothumb) { echo 'checked="checked"'; } ?> />
                        <p>建议不勾选</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>不在本地保留备份</legend>
                    </th>
                    <td>
                        <input type="checkbox"
                               name="nolocalsaving" <?php if ($oss_nolocalsaving) { echo 'checked="checked"'; } ?> />
                        <p>建议不勾选</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>本地文件夹</legend>
                    </th>
                    <td>
                        <input type="text" name="upload_path" value="<?php echo $upload_path; ?>" size="50" placeholder="请输入上传文件夹"/>
                        <p>附件在服务器上的存储位置，例如： <code>wp-content/uploads</code> （注意不要以“/”开头和结尾），根目录请输入<code>.</code>。</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>URL前缀</legend>
                    </th>
                    <td>
                        <input type="text" name="upload_url_path" value="<?php echo $upload_url_path; ?>" size="50" placeholder="请输入URL前缀"/>

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
                        <input type="text" name="style" value="<?php echo $oss_style; ?>" size="50" placeholder="请输入图片处理样式，留空表示不处理"/>

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
                    <th><legend>保存/更新选项</legend></th>
                    <td><input type="submit" name="submit" class="button button-primary" value="保存更改"/></td>
                </tr>
            </table>
            <input type="hidden" name="type" value="oss_set">
        </form>
        <form name="form2" method="post" action="<?php echo wp_nonce_url('./options-general.php?page=' . OSS_BASEFOLDER . '/aliyun-oss-wordpress.php'); ?>">
            <table class="form-table">
                <tr>
                    <th>
                        <legend>同步历史附件</legend>
                    </th>
                    <input type="hidden" name="type" value="aliyun_oss_all">
                    <td>
                        <input type="submit" name="submit" class="button button-secondary" value="开始同步"/>
                        <p><b>注意：如果是首次同步，执行时间将会十分十分长（根据你的历史附件数量），有可能会因执行时间过长，页面显示超时或者报错。<br> 所以，建议那些几千上万附件的大神们，考虑官方的 <a target="_blank" rel="nofollow" href="https://help.aliyun.com/knowledge_detail/39628.html">同步工具</a></b></p>
                    </td>
                </tr>
            </table>
        </form>
        <hr>
        <form name="form3" method="post" action="<?php echo wp_nonce_url('./options-general.php?page=' . OSS_BASEFOLDER . '/aliyun-oss-wordpress.php'); ?>">
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
                    <td>
                        <input type="submit" name="submit"  class="button button-secondary" value="开始替换"/>
                        <p><b>注意：如果是首次替换，请注意备份！此功能会替换文章以及设置的特色图片（题图）等使用的资源链接</b></p>
                    </td>
                </tr>
            </table>
        </form>
    </div>
<?php
}
?>
