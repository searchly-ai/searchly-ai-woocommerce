<?php
/**
 * Plugin Name: Searchly AI
 * Description: Adds AI-powered semantic search to WooCommerce stores.
 * Version: 1.0.0
 * Author: Searchly AI
 */

if (!defined('ABSPATH')) {
    exit;
}

class SearchlyAIPlugin
{
    private $api_endpoint = 'https://app.searchly-ai.com/api/v1';

    public function __construct() 
    {
        add_action('admin_menu', [$this, 'searchly_ai_register_settings_page']);
        add_action('admin_init', [$this, 'searchly_ai_register_settings']);
        add_action('pre_get_posts', [$this, 'searchly_ai_override_product_search'], 999);
    }

    public function searchly_ai_register_settings_page() 
    {
        add_menu_page(
            'Searchly AI',
            'Searchly AI',
            'manage_options',
            'searchly-ai-settings',
            [$this, 'searchly_ai_render_settings_page'],
            'dashicons-search'
        );
    }

    public function searchly_ai_register_settings() 
    {
        register_setting('searchly-ai-settings-group', 'searchly_ai_api_key');
    }

    public function searchly_ai_render_settings_page() 
    {
        ?>
        <div class="wrap">
            <h1>Searchly AI Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields('searchly-ai-settings-group'); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">API Key</th>
                        <td><input type="text" name="searchly_ai_api_key" value="<?php echo esc_attr(get_option('searchly_ai_api_key')); ?>" size="100" /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function searchly_ai_override_product_search($query)
    {

        if (!$query->is_search() || !$query->is_main_query() || is_admin()) {
            return;
        }

        if ($query->get('post_type') !== 'product' && 
        (!isset($_GET['post_type']) || $_GET['post_type'] !== 'product')) {
            return;
        }

        if (!function_exists('wc_get_products')) {
            return;
        };

        $api_key = get_option('searchly_ai_api_key');
        
        if (!$api_key) {
            return;
        }

        $search_term = $query->get('s');

        $response = wp_remote_post("{$this->api_endpoint}/search", [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ],
            'body' => json_encode([
                'query' => $search_term,
                'user_ip' => $this->user_ip()
            ])
        ]);

        if (is_wp_error($response)) {
            wc_get_logger()->error("API error: " . $response->get_error_message());
            return;
        }
    
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            wc_get_logger()->error("API returned status code: " . $status_code);
            return;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['products'])) {
            return;
        }

        $product_ids = array_map('intval', $body['products']);

        WC()->session->set('searchly_search_results', $product_ids);

        $query->set('post_type', 'product');
        $query->set('s', '');
        $query->set('post__in', $product_ids);
        $query->set('orderby', 'post__in');
        $query->set('post_status', 'publish');
    }

    private function user_ip()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ipList[0]);
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    }
}

new SearchlyAIPlugin();
