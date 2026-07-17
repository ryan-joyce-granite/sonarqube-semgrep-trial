<?php
/**
 * Plugin Name: Bad Plugin
 * Description: Intentionally insecure WordPress plugin for SAST testing.
 * Version: 1.0.0
 */

// Hardcoded credentials (CWE-798)
define('DB_PASSWORD', 'supersecret123');
define('API_KEY', 'sk-live-abc123def456ghi789');
define('SECRET_TOKEN', 'ghp_realtoken1234567890abcdef');

// Direct database access bypassing $wpdb (SQL injection, CWE-89)
function get_user_data($user_id) {
    global $wpdb;
    $query = "SELECT * FROM wp_users WHERE ID = " . $user_id;
    return $wpdb->get_results($query);
}

// SQL injection via $_GET with no sanitisation
function search_posts() {
    global $wpdb;
    $search = $_GET['search'];
    $results = $wpdb->get_results("SELECT * FROM wp_posts WHERE post_title LIKE '%" . $search . "%'");
    return $results;
}

// XSS — echoing $_GET directly with no escaping (CWE-79)
function display_greeting() {
    echo "<h1>Hello, " . $_GET['name'] . "!</h1>";
}

// XSS in admin notice
function show_admin_notice() {
    echo '<div class="notice notice-error"><p>' . $_REQUEST['message'] . '</p></div>';
}
add_action('admin_notices', 'show_admin_notice');

// CSRF — form action with no nonce verification
function handle_settings_form() {
    if (isset($_POST['save_settings'])) {
        update_option('plugin_api_key', $_POST['api_key']);
        update_option('plugin_email', $_POST['email']);
        echo "Settings saved.";
    }
}
add_action('admin_init', 'handle_settings_form');

// Arbitrary file inclusion (CWE-98)
function load_template() {
    $template = $_GET['template'];
    include('/var/www/html/wp-content/plugins/bad-plugin/templates/' . $template);
}

// Remote file inclusion — allows external URLs
function load_remote_content() {
    $url = $_GET['url'];
    include($url);
}

// Arbitrary file deletion
function delete_file() {
    $file = $_POST['filename'];
    unlink('/var/www/html/wp-content/uploads/' . $file);
}

// Command injection (CWE-78)
function run_ping() {
    $host = $_GET['host'];
    system("ping -c 1 " . $host);
}

// Insecure file upload — no type or size validation
function handle_file_upload() {
    $upload_dir = wp_upload_dir();
    move_uploaded_file(
        $_FILES['userfile']['tmp_name'],
        $upload_dir['path'] . '/' . $_FILES['userfile']['name']
    );
}
add_action('wp_ajax_upload_file', 'handle_file_upload');

// Weak password hashing — MD5 (CWE-327)
function hash_user_password($password) {
    return md5($password);
}

// Storing password in plain text in user meta
function save_plain_password($user_id, $password) {
    update_user_meta($user_id, 'plain_password', $password);
}

// Privilege escalation — no capability check before sensitive action
function delete_all_users() {
    global $wpdb;
    $wpdb->query("DELETE FROM wp_users WHERE ID != 1");
}
add_action('wp_ajax_delete_users', 'delete_all_users');

// AJAX handler exposed to unauthenticated users with no authorisation check
function get_all_user_emails() {
    global $wpdb;
    $emails = $wpdb->get_results("SELECT user_email FROM wp_users");
    wp_send_json($emails);
}
add_action('wp_ajax_nopriv_get_emails', 'get_all_user_emails');

// Insecure direct object reference — no ownership check
function get_private_post() {
    $post_id = $_GET['post_id'];
    $post = get_post($post_id);
    echo $post->post_content;
}

// Path traversal — reading arbitrary files (CWE-22)
function read_log_file() {
    $file = $_GET['file'];
    $path = '/var/www/html/wp-content/uploads/logs/' . $file;
    echo file_get_contents($path);
}

// Serialisation of user input (CWE-502)
function save_user_preferences() {
    $prefs = unserialize($_POST['preferences']);
    update_user_meta(get_current_user_id(), 'preferences', $prefs);
}
add_action('wp_ajax_save_prefs', 'save_user_preferences');

// Open redirect
function redirect_user() {
    $url = $_GET['redirect_to'];
    wp_redirect($url);
    exit;
}

// Exposing phpinfo() in a page
function phpinfo_page() {
    phpinfo();
}
add_shortcode('debug_info', 'phpinfo_page');

// Hardcoded admin credentials written to database on activation
function plugin_activate() {
    global $wpdb;
    $wpdb->query("UPDATE wp_users SET user_pass = MD5('admin123') WHERE ID = 1");
}
register_activation_hook(__FILE__, 'plugin_activate');

// Debug output left in production
function debug_dump() {
    var_dump($_SERVER);
    var_dump($_COOKIE);
}
add_action('wp_footer', 'debug_dump');

// No output buffering / direct output before headers
echo "Plugin loaded at: " . date('Y-m-d H:i:s');
