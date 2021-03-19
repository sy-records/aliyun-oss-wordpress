=== OSS Aliyun ===
Contributors: shenyanzhi
Donate link: https://qq52o.me/sponsor.html
Tags: oss, 阿里云, 对象存储, aliyun
Requires at least: 4.2
Tested up to: 5.7
Requires PHP: 5.6.0
Stable tag: 1.2.4
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
7. 支持阿里云OSS图片处理
8. 插件更多详细介绍和安装：[https://github.com/sy-records/aliyun-oss-wordpress](https://github.com/sy-records/aliyun-oss-wordpress)

## 其他插件

腾讯云COS：[GitHub](https://github.com/sy-records/wordpress-qcloud-cos)，[WordPress Plugins](https://wordpress.org/plugins/sync-qcloud-cos)
华为云OBS：[GitHub](https://github.com/sy-records/huaweicloud-obs-wordpress)，[WordPress Plugins](https://wordpress.org/plugins/obs-huaweicloud)
七牛云KODO：[GitHub](https://github.com/sy-records/qiniu-kodo-wordpress)，[WordPress Plugins](https://wordpress.org/plugins/kodo-qiniu)
又拍云USS：[GitHub](https://github.com/sy-records/upyun-uss-wordpress)，[WordPress Plugins](https://wordpress.org/plugins/uss-upyun)

## 作者博客

[沈唁志](https://qq52o.me "沈唁志")

欢迎加入沈唁的WordPress云存储全家桶QQ交流群：887595381

== Installation ==

1. Upload the folder `aliyun-oss-wordpress` or `oss-aliyun` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. That's all

== Screenshots ==

1. screenshot-1.png
2. screenshot-2.png

== Frequently Asked Questions ==

= 怎么替换文章中之前的旧资源地址链接？ =

这个插件已经加上了替换数据库中之前的旧资源地址链接功能，只需要填好对应的链接即可

= 通过文件URL访问图片无法预览而是以附件形式下载？ =

因为Bucket是2019年9月23日后创建的，使用默认域名时会自动下载，需要绑定自有域名访问。具体参考[阿里云文档](https://help.aliyun.com/document_detail/142631.html)。

= 如果存在第三方插件或者主题自带上传功能，内容上传到本地文件夹(即默认wp-content/uploads)中，怎么上传到OSS中？ =

解决方案有两种，推荐使用第二种。

一是修改第三方插件或者主题的上传功能，调用插件的`oss_file_upload`方法（不推荐，一般人不会修改）
二是使用对象存储OSS提供的回源功能，配置为镜像方式。如果配置了镜像回源，当用户对该存储空间内一个不存在的文件进行GET操作时，OSS会向回源地址请求这个文件，返回给用户，同时会将该文件存入OSS。这样就达到了上传到OSS的需求。具体配置参考阿里云文档[设置回源规则](https://help.aliyun.com/document_detail/31906.html)

== Changelog ==

= 1.2.4 =
* 添加 get_home_path 方法判断
* 支持 WordPress 5.7 版本

= 1.2.3 =
* 支持删除非图片类型文件

= 1.2.2 =
* 支持 WordPress 5.6 版本
* 升级 OSS SDK
* 修复勾选不上传缩略图删除时不会删除已存在的缩略图

= 1.2.1 =
* 支持阿里云OSS图片处理

= 1.2.0 =
* 优化同步上传路径获取
* 修复多站点上传原图失败，缩略图正常问题
* 优化上传路径获取
* 增加数据库题图链接替换

= 1.1.1 =
* 修复本地文件夹为根目录时路径错误
* 减少一次获取配置代码...
* 增加回源说明

= 1.1.0 =
* 优化删除文件使用删除多个接口
* 修复勾选不在本地保存图片后媒体库显示默认图片问题

= 1.0.1 =
* 修复勾选不在本地保存图片后媒体库显示默认图片问题

= 1.0 =
* First version
