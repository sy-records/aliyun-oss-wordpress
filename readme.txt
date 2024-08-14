=== OSS Aliyun ===
Contributors: shenyanzhi
Donate link: https://qq52o.me/sponsor.html
Tags: oss, 阿里云, 对象存储, aliyun
Requires at least: 4.6
Tested up to: 6.6
Requires PHP: 7.1
Stable tag: 1.4.18
License: Apache2.0
License URI: http://www.apache.org/licenses/LICENSE-2.0.html

使用阿里云对象存储 OSS 作为附件存储空间。（This is a plugin that uses Aliyun Object Storage Service for attachments remote saving.）

== Description ==

使用阿里云对象存储 OSS 作为附件存储空间。（This is a plugin that uses Aliyun Object Storage Service for attachments remote saving.）

- 依赖阿里云 OSS 服务：https://www.aliyun.com/product/oss

## 插件特点

1. 可配置是否上传缩略图和是否保留本地备份
2. 本地删除可同步删除阿里云对象存储 OSS 中的文件
3. 支持阿里云对象存储 OSS 绑定的用户域名
4. 支持替换数据库中旧的资源链接地址
5. 支持阿里云对象存储 OSS 完整地域使用
6. 支持同步历史附件到阿里云对象存储 OSS
7. 支持阿里云 OSS 图片处理
8. 支持上传文件自动重命名
9. 支持使用 ECS 的 RAM 操作
10. 支持原图保护
11. 支持 `wp-cli` 命令上传/删除文件
12. 插件更多详细介绍和安装：[https://github.com/sy-records/aliyun-oss-wordpress](https://github.com/sy-records/aliyun-oss-wordpress)

## 其他插件

腾讯云 COS：[GitHub](https://github.com/sy-records/wordpress-qcloud-cos)，[WordPress Plugins](https://wordpress.org/plugins/sync-qcloud-cos)
华为云 OBS：[GitHub](https://github.com/sy-records/huaweicloud-obs-wordpress)，[WordPress Plugins](https://wordpress.org/plugins/obs-huaweicloud)
七牛云 KODO：[GitHub](https://github.com/sy-records/qiniu-kodo-wordpress)，[WordPress Plugins](https://wordpress.org/plugins/kodo-qiniu)
又拍云 USS：[GitHub](https://github.com/sy-records/upyun-uss-wordpress)，[WordPress Plugins](https://wordpress.org/plugins/uss-upyun)

## 作者博客

[沈唁志](https://qq52o.me "沈唁志")

欢迎加入沈唁的 WordPress 云存储全家桶 QQ 交流群：887595381

== Installation ==

1. Upload the folder `aliyun-oss-wordpress` or `oss-aliyun` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. That's all

== Screenshots ==

1. 设置页面
2. 数据库同步
3. 内置的 wp-cli 命令

== Frequently Asked Questions ==

= 怎么替换文章中之前的旧资源地址链接？ =

这个插件已经加上了替换数据库中之前的旧资源地址链接功能，只需要填好对应的链接即可

= 通过文件 URL 访问图片无法预览而是以附件形式下载？ =

因为 Bucket 是 2019 年 9 月 23 日后创建的，使用默认域名时会自动下载，需要绑定自有域名访问。具体参考[阿里云文档](https://help.aliyun.com/document_detail/142631.html)。

= 如果存在第三方插件或者主题自带上传功能，内容上传到本地文件夹(即默认 wp-content/uploads)中，怎么上传到 OSS 中？ =

解决方案有两种，推荐使用第二种。

一是修改第三方插件或者主题的上传功能，调用插件的`oss_file_upload`方法（不推荐，一般人不会修改）
二是使用对象存储 OSS 提供的回源功能，配置为镜像方式。如果配置了镜像回源，当用户对该存储空间内一个不存在的文件进行 GET 操作时，OSS 会向回源地址请求这个文件，返回给用户，同时会将该文件存入 OSS。这样就达到了上传到 OSS 的需求。具体配置参考阿里云文档[设置回源规则](https://help.aliyun.com/document_detail/31906.html)

== Changelog ==

= 1.4.18 =

- Images processing ignore gif format

= 1.4.17 =

- Fix endpoint failed to use https

= 1.4.16 =

- 强制 endpoint 使用 https

= 1.4.15 =

- 将阿里云V1签名升级为V4签名

= 1.4.14 =

- 支持 `wp-cli` 命令删除文件
- Use wp_get_mime_types instead of get_allowed_mime_types
- 修复 heic 格式图片上传失败问题

= 1.4.13 =

- 支持 `wp-cli` 命令上传文件

= 1.4.12 =

- 支持原图保护

= 1.4.11 =

- 优化数据库数据替换语法

= 1.4.10 =

- 修复`不在本地保留备份`时获取不到非图片文件大小

= 1.4.9 =

- 升级 SDK
- 增加 CSRF 验证

= 1.4.8 =

- 修复图片处理参数重复添加

= 1.4.7 =

- 修复 `upload_url_path` 设置为 `.` 时删除失败
- 优化图片处理参数追加

= 1.4.6 =

- 修复 pdf 等文件格式上传时报错

= 1.4.5 =

- 兼容 PHP 7.0

= 1.4.4 =

- 修复超大文件原图上传和删除

= 1.4.3 =

- 修复同步错误
- 更新地域

= 1.4.2 =

- 优化同步代码逻辑
- 修复 webp 和 heic 格式图片上传缩略图失败问题

= 1.4.1 =

- 支持媒体库编辑图片上传

= 1.4.0 =

- 支持 WordPress 6.3 版本
- 支持 RAM 操作 OSS

= 1.3.2 =

- 添加地域

= 1.3.1 =

- 优化代码

= 1.3.0 =

- 增加地域
- 优化 isset 判断
- 优化访问权限
- 修复存在同名 path 时截取错误
- 修改 accessKeySecret 类型为 password

= 1.2.8 =

- 支持上传文件自动重命名
- 优化图片处理

= 1.2.7 =

- 增加地域

= 1.2.6 =

- 升级 oss sdk
- 修复删除文件的 request id 异常
- 支持 WordPress 5.8 版本

= 1.2.5 =

- 修复当文章图片重复时导致添加多个样式

= 1.2.4 =

- 添加 get_home_path 方法判断
- 支持 WordPress 5.7 版本

= 1.2.3 =

- 支持删除非图片类型文件

= 1.2.2 =

- 支持 WordPress 5.6 版本
- 升级 OSS SDK
- 修复勾选不上传缩略图删除时不会删除已存在的缩略图

= 1.2.1 =

- 支持阿里云 OSS 图片处理

= 1.2.0 =

- 优化同步上传路径获取
- 修复多站点上传原图失败，缩略图正常问题
- 优化上传路径获取
- 增加数据库题图链接替换

= 1.1.1 =

- 修复本地文件夹为根目录时路径错误
- 减少一次获取配置代码...
- 增加回源说明

= 1.1.0 =

- 优化删除文件使用删除多个接口
- 修复勾选不在本地保存图片后媒体库显示默认图片问题

= 1.0.1 =

- 修复勾选不在本地保存图片后媒体库显示默认图片问题

= 1.0 =

- First version
