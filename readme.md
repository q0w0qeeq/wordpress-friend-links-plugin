## 简要介绍
一个Wordpress插件，前端使用短代码实现，安装后在任意页面的任意地方添加短代码（简码）： [link_submit_form]即可。
是一个表单，用于自助申请友情链接，没有添加css效果。
需要用到Wordpress的链接功能，需要在主题的functions.php文件中添加下面的代码:
```
add_filter('pre_option_link_manager_enabled', '__return_true');
```

其中博客头像、博客描述是可选项目；最后需要一个按钮来提交。提交间隔设置为60s。
用户提交完以后，将链接添加到“待审核”分类，并且设置为私密链接，同时调用WP Mail SMTP插件发生邮件给博主的邮箱。
