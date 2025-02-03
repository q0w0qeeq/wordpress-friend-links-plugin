<?php
/*
Plugin Name: Frontend Link Submit
Description: 前台友情链接自助申请表单
Version: 1.4
Author: Your Name
*/

// 创建激活钩子初始化分类
register_activation_hook(__FILE__, 'fls_create_pending_category');
function fls_create_pending_category() {
    if (!term_exists('pending', 'link_category')) {
        wp_insert_term('待审核', 'link_category', array('slug' => 'pending'));
    }
}

// 注册短代码
add_shortcode('link_submit_form', 'fls_form_shortcode');

function fls_form_shortcode() {
    ob_start();
    $messages = fls_handle_form_submission();
    fls_display_form($messages);
    return ob_get_clean();
}

// 处理表单提交
function fls_handle_form_submission() {
    $messages = [];

    if (!isset($_POST['fls_submit'])) return $messages;

    // 验证 nonce
    if (!wp_verify_nonce($_POST['fls_nonce'], 'fls_submit_link')) {
        $messages[] = ['type' => 'error', 'content' => '安全校验失败，请重试。'];
        return $messages;
    }

    // 检查提交间隔
    $user_ip = $_SERVER['REMOTE_ADDR'];
    $last_submit = get_transient('fls_cooldown_' . $user_ip);
    if ($last_submit) {
        $messages[] = ['type' => 'error', 'content' => '提交过于频繁，请60秒后再试。'];
        return $messages;
    }

    // 获取并验证字段
    $name = sanitize_text_field($_POST['fls_name'] ?? '');
    $url = esc_url_raw($_POST['fls_url'] ?? '');
    $description = sanitize_text_field($_POST['fls_description'] ?? '');
    $image = esc_url_raw($_POST['fls_image'] ?? '');

    if (empty($name) || empty($url)) {
        $messages[] = ['type' => 'error', 'content' => '请填写必填字段。'];
        return $messages;
    }

    // 检查名称重复
    global $wpdb;
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $wpdb->links WHERE link_name = %s",
        $name
    ));

    if ($exists) {
        $messages[] = ['type' => 'error', 'content' => '该名称已被使用，请更换。'];
        return $messages;
    }

    // 获取待审核分类ID
    $category = get_term_by('slug', 'pending', 'link_category');
    if (!$category) {
        $messages[] = ['type' => 'error', 'content' => '系统配置错误。'];
        return $messages;
    }

    // 创建链接数据
    $linkdata = array(
        'link_name'        => $name,
        'link_url'         => $url,
        'link_description' => $description,
        'link_image'       => $image,
        'link_category'    => array($category->term_id),
        'link_visible'     => 'n'
    );

    // 插入链接
    require_once(ABSPATH . 'wp-admin/includes/bookmark.php');
    $result = wp_insert_link($linkdata, true);

    if (is_wp_error($result)) {
        $messages[] = ['type' => 'error', 'content' => '提交失败：' . esc_html($result->get_error_message())];
    } else {
        set_transient('fls_cooldown_' . $user_ip, time(), 60);

        // 发送邮件通知
        $to = get_option('admin_email');
        $subject = '新的友情链接申请 - ' . $name;
        $admin_url = admin_url('link-manager.php');
        $message = "新友情链接申请通知\n\n";
        $message .= "申请时间：" . date('Y-m-d H:i:s') . "\n";
        $message .= "----------------------------\n";
        $message .= "博客名称：{$name}\n";
        $message .= "博客地址：{$url}\n";
        $message .= "博客头像：" . ($image ?: '未提供') . "\n";
        $message .= "博客描述：" . ($description ?: '未提供') . "\n\n";
        $message .= "审核地址：{$admin_url}\n";
        $message .= "----------------------------\n";
        $message .= "此邮件由系统自动发送，请勿直接回复";
        
        wp_mail($to, $subject, $message);

        $messages[] = ['type' => 'success', 'content' => '提交成功！'];
    }

    return $messages;
}

// 显示表单和消息
function fls_display_form($messages) {
    ?>
    <form method="post" style="max-width: 600px; margin: 20px auto;">
        <?php wp_nonce_field('fls_submit_link', 'fls_nonce'); ?>
        
        <p>
            <label>博客名称（必填）:</label><br>
            <input type="text" name="fls_name" required 
                   style="width:85%; padding:8px; margin:5px 0;">
        </p>

        <p>
            <label>博客地址（必填）:</label><br>
            <input type="url" name="fls_url" required 
                   style="width:85%; padding:8px; margin:5px 0;">
        </p>

        <p>
            <label>博客头像地址（可选）:</label><br>
            <input type="url" name="fls_image" 
                   style="width:85%; padding:8px; margin:5px 0;">
        </p>

        <p>
            <label>博客描述（可选）:</label><br>
            <textarea name="fls_description" 
                     style="width:85%; height:80px; padding:8px; margin:5px 0;"></textarea>
        </p>

        <p>
            <input type="submit" name="fls_submit" value="提交申请" 
                   style="padding:10px 20px; cursor:pointer;">
        </p>
    </form>

    <?php if (!empty($messages)) : ?>
        <div style="max-width:600px; margin:20px auto;">
            <?php foreach ($messages as $msg) : ?>
                <p style="color: <?php echo $msg['type'] === 'error' ? 'red' : 'green' ?>; padding:10px;">
                    <?php echo esc_html($msg['content']) ?>
                </p>
            <?php endforeach; ?>
        </div>
    <?php endif;
}
