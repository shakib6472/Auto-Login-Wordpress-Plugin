<?php
/*
Plugin Name: AutoLogin Dropdown with Search
Description: A plugin that allows you to select a user from a searchable dropdown and autologin in a new tab.
Version: 1.0
Author: Your Name
*/

// Enqueue Select2 scripts and styles
function enqueue_select2() {
    // Enqueue Select2 CSS
    wp_enqueue_style('select2-css', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css');
    
    // Enqueue jQuery and Select2 JS
    wp_enqueue_script('jquery'); // Ensure jQuery is loaded
    wp_enqueue_script('select2-js', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'), null, true);
    
    // Custom script to initialize Select2 on the dropdown
    wp_add_inline_script('select2-js', '
        jQuery(document).ready(function($) {
            $("#user-select").select2({
                placeholder: "Select a user",
                allowClear: true
            });
        });
    ');
}
add_action('wp_enqueue_scripts', 'enqueue_select2');

// Shortcode to display the dropdown
function autologin_dropdown_shortcode() {
    // Get all users with the 'subscriber' and 'student' roles
    $args = array(
        'role__in' => array('subscriber', 'student')
    );
    $users = get_users($args);

    // Start output buffering
    ob_start();
    ?>
        <style>
.autologin label {
    font-size: 26px;
    text-align: center;
}

.autologin select#user-select {
    min-height: 50px;
    margin: 15px 0;
}
.autologin button#login-button {
    width: 100%;
}
form#autologin-form {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

    </style>

      <div class="autologin">
    <form id="autologin-form">
        <label for="user-select">Select User:</label>
        <select id="user-select" name="user_id">
            <option value="">Select a user</option>
            <?php foreach ($users as $user): ?>
                <option value="<?php echo esc_attr($user->ID); ?>">
                    <?php echo esc_html($user->display_name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="button" id="login-button">Login</button>
    </form>
    </div>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#login-button').click(function() {
            var user_id = $('#user-select').val();

            $.ajax({
                url: "<?php echo admin_url('admin-ajax.php'); ?>",
                type: 'POST',
                data: {
                    action: 'autologin_user',
                    user_id: user_id
                },
                success: function(response) {
                    if (response.success) {
                        // Open the auto-login URL in a new tab
                        window.open(response.data.url, '_blank');
                    } else {
                        alert('Something went wrong: Please contact with developer' );
                    }
                }
            });
        });
    });
    </script>
    <?php
    // Return the output
    return ob_get_clean();
}
add_shortcode('autologin_dropdown', 'autologin_dropdown_shortcode');

// AJAX handler to generate the login link
function autologin_user() {
    if (!isset($_POST['user_id'])) {
        wp_send_json_error(array('message' => 'User ID is required.'));
    }

    $user_id = intval($_POST['user_id']);
    $user = get_user_by('ID', $user_id);

    if (!$user) {
        wp_send_json_error(array('message' => 'User not found.'));
    }

    // Generate a one-time login token
    $login_token = wp_generate_password(20, false);
    update_user_meta($user_id, 'autologin_token', $login_token);

    // Create the auto-login URL
    $login_url = add_query_arg(array(
        'autologin_token' => $login_token,
        'user_id' => $user_id
    ), site_url());

    wp_send_json_success(array('url' => $login_url));
}
add_action('wp_ajax_autologin_user', 'autologin_user');
add_action('wp_ajax_nopriv_autologin_user', 'autologin_user');

// Handle auto-login
function handle_autologin() {
    if (isset($_GET['autologin_token']) && isset($_GET['user_id'])) {
        $user_id = intval($_GET['user_id']);
        $token = sanitize_text_field($_GET['autologin_token']);
        $stored_token = get_user_meta($user_id, 'autologin_token', true);

        if ($token === $stored_token) {
            // Log in the user
            wp_set_auth_cookie($user_id, true);
            delete_user_meta($user_id, 'autologin_token'); // Optional: remove token after use
            wp_redirect(home_url()); // Redirect to homepage or desired page
            exit;
        }
    }
}
add_action('template_redirect', 'handle_autologin');
