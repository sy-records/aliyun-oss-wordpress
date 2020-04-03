=== OSS Aliyun ===
Contributors: shenyanzhi
Donate link: https://qq52o.me/sponsor.html
Tags: oss, 阿里云, 对象存储, aliyun
Requires at least: 4.2
Tested up to: 5.4
Requires PHP: 5.6.0
Stable tag: 1.0.1
License: Apache 2.0
License URI: http://www.apache.org/licenses/LICENSE-2.0.html

使用阿里云对象存储 OSS 作为附件存储空间。（This is a plugin that uses Aliyun Object Storage Service for attachments remote saving.）

== Description ==

使用阿里云对象存储 OSS 作为附件存储空间。（This is a plugin that uses Aliyun Object Storage Service for attachments remote saving.）

* 依赖阿里云OSS服务：https://www.aliyun.com/product/oss

## 插件特点

1. 可配置是否上传缩略图和是否保留本地备份
2. 本地删除可同步删除阿里云对象存储OSS中的文件
3. 支持阿里云对象存储OSS绑定的用户域名
4. 支持替换数据库中旧的资源链接地址
5. 支持阿里云对象存储OSS完整地域使用
6. 支持同步历史附件到阿里云对象存储OSS
7. 插件更多详细介绍和安装：[https://github.com/sy-records/aliyun-oss-wordpress](https://github.com/sy-records/aliyun-oss-wordpress)

## 其他插件

腾讯云COS：[GitHub](https://github.com/sy-records/wordpress-qcloud-cos)，[WordPress Plugins](https://wordpress.org/plugins/sync-qcloud-cos)
华为云OBS：[GitHub](https://github.com/sy-records/huaweicloud-obs-wordpress)，[WordPress Plugins](https://wordpress.org/plugins/obs-huaweicloud)
七牛云KODO：[GitHub](https://github.com/sy-records/qiniu-kodo-wordpress)，[WordPress Plugins](https://wordpress.org/plugins/kodo-qiniu)
又拍云USS：[GitHub](https://github.com/sy-records/upyun-uss-wordpress)，[WordPress Plugins](https://wordpress.org/plugins/uss-upyun)

## 作者博客

[沈唁志](https://qq52o.me "沈唁志")

QQ交流群：887595381

== Installation ==

1. Upload the folder `aliyun-oss-wordpress` or `oss-aliyun` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. That's all

== Screenshots ==

1. screenshot-1.png
2. screenshot-2.png

== Frequently Asked Questions ==

= 怎么替换文章中之前的旧资源地址链接 =

这个插件已经加上了替换数据库中之前的旧资源地址链接功能，只需要填好对应的链接即可

== Changelog ==

= 1.0.1 =
* 修复勾选不在本地保存图片后媒体库显示默认图片问题

= 1.0 =
* First version
