<?php

/*
Plugin Name: Screenevolution
Description: Screenevolution.
Version:     1.0.0
Author:      Ketion
License:     GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt
Text Domain: screenevolution
*/

defined('ABSPATH') || exit;

if ( ! defined('SCREENEVOLUTION_INTEGRATION_FILE')) {
    define('SCREENEVOLUTION_INTEGRATION_FILE', __FILE__);
}

if ( ! defined('SCREENEVOLUTION_INTEGRATION_URL')) {
    define('SCREENEVOLUTION_INTEGRATION_URL', plugin_dir_url(__FILE__));
}

/**
 * The core plugin class.
 */
class SCREENEVOLUTION_INTEGRATION_MAIN
{
    /**
     * Class instance.
     *
     * @var SCREENEVOLUTION_INTEGRATION_MAIN instance
     */
    protected static $instance = false;

    /**
     * The resulting page's hook_suffix of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string $hook The resulting page's hook_suffix.
     */
    protected $hook;

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string $name The string used to uniquely identify this plugin.
     */
    protected $name = 'screenevolution';

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string $version The current version of the plugin.
     */
    protected $version = '1.0.0';

    /**
     * The plugin options.
     *
     * @since    1.0.0
     * @access   protected
     * @var      array $options The plugin options.
     */
    protected $options;

    /**
     * seobility api url.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $seobility_api_url seobility api url.
     */
    private $seobility_api_url;

    /**
     * openai api url.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $openai_api_url openai api url.
     */
    private $openai_api_url;

    /**
     * seobility api key.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $seobility_api_key seobility api key.
     */
    private $seobility_api_key;

    /**
     * openai api key.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $openai_api_key openai api key.
     */
    private $openai_api_key;

    /**
     * Define the core functionality of the plugin.
     */
    public function __construct()
    {
        $this->options           = get_option($this->name);
        $this->seobility_api_url = "https://api.seobility.net/es/resellerapi/termsuggestion";
        $this->openai_api_url    = "https://api.openai.com/v1/engines/davinci/completions";
        $this->seobility_api_key = $this->get_option('seobility_api_key', '');
        $this->openai_api_key    = $this->get_option('openai_api_key', '');

        $this->check_settings_saved();
        $this->run();
    }

    /**
     * Get option from DB.
     *
     * Gets an option from options, using defaults if necessary to prevent undefined notices.
     *
     * @param  string  $key  Option key.
     * @param  mixed  $empty_value  Value when empty.
     *
     * @return string The value specified for the option or a default value for the option.
     */
    private function get_option($key, $empty_value = null)
    {
        if (empty($this->options)) {
            return $empty_value;
        }

        // Get option default if unset.
        if ( ! isset($this->options[$key])) {
            return $empty_value;
        }

        if ( ! is_null($empty_value) && '' === $this->options[$key]) {
            return $empty_value;
        }

        return $this->options[$key];
    }

    /**
     * Check if URI and code are set on settings.
     * Show a notice if not.
     */
    private function check_settings_saved()
    {
        if ( ! $this->check()) {
            add_action('admin_notices', array($this, 'settings_not_saved_notice'));
        }
    }

    /**
     * Check if settings saved.
     */
    public function check()
    {
        // check if settings saved
        if (empty($this->seobility_api_url) || empty($this->seobility_api_key) || empty($this->openai_api_url) || empty($this->openai_api_key)) {
            return false;
        }

        return true;
    }

    /**
     * Run all hooks within WordPress.
     *
     * @since    1.0.0
     */
    public function run()
    {
        /**
         * Add link to plugin settings
         */
        add_filter("plugin_action_links_" . plugin_basename(SCREENEVOLUTION_INTEGRATION_FILE),
            array($this, 'plugin_add_settings_link'));

        /**
         * Register plugin settings page
         */
        add_action('admin_init', array($this, 'register_plugin_settings'));

        /**
         * Admin plugin options page
         */
        add_action('admin_menu', array($this, 'add_settings_page_to_menu'), 99);

        /**
         * Admin plugin options page scripts
         */
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));

        /**
         * Plugin frontend assets.
         */
        add_action('wp_enqueue_scripts', array($this, 'front_enqueue_scripts'));

        /**
         * Admin ajax action trigger cron.
         */
        add_action('wp_ajax_screenevolution_ajax_comment', array($this, 'screenevolution_ajax_generate'));

        /**
         * Add button to comment form.
         */
        add_action('comment_form_top', array($this, 'add_button_to_comment_form'));
    }

    /**
     * Get class instance
     */
    public static function get_instance()
    {
        if ( ! self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Show settings link on plugins list
     */
    public function plugin_add_settings_link($links)
    {
        $settings_link = '<a href="' . menu_page_url($this->name, false) . '">' . __('Settings',
                'screenevolution') . '</a>';
        array_unshift($links, $settings_link);

        return $links;
    }

    /**
     * Register plugin settings page
     */
    public function register_plugin_settings()
    {
        register_setting($this->name, $this->name);
    }

    /**
     * Notice to show on admin side when API settings not saved.
     */
    public function settings_not_saved_notice()
    {
        $message = sprintf(
            esc_html__('%1$s plugin needs to be configured. %2$s', 'screenevolution'),
            '<strong>' . esc_html__('Screenevolution', 'screenevolution') . '</strong>',
            '<a href="' . menu_page_url($this->name, false) . '">' . __('Settings',
                'screenevolution') . '</a>'
        );

        printf('<div class="error notice notice-warning"><p>%1$s</p></div>', $message);
    }

    /**
     * Admin plugin options page
     */
    public function add_settings_page_to_menu()
    {
        $this->hook = add_submenu_page('edit.php', 'Screenevolution', 'Screenevolution',
            'manage_options', $this->name, array($this, 'admin_settings_page_content'));
    }

    /**
     * Content to show on plugin settings page
     */
    public function admin_settings_page_content()
    {
        ?>
        <div class="wrap">
            <form method="post" action="options.php" class="screenevolution-form">
                <h2><?php
                    _e('Screenevolution', 'screenevolution'); ?></h2>

                <?php settings_fields($this->name); ?>

                <p><?php
                    _e('To configure the settings, enter below details'); ?></p>
                <table class="form-table">
                    <tr valign="middle">
                        <th scope="row"><label
                                    for="screenevolution[seobility_api_key]"><?php
                                _e('Seobility API Key', 'screenevolution'); ?></label>
                        </th>
                        <td class="relative">
                            <input type="password" id="screenevolution[seobility_api_key]"
                                   name="screenevolution[seobility_api_key]" class="regular-text"
                                   value="<?php
                                   echo $this->get_option('seobility_api_key'); ?>" autocomplete="chrome-off">
                            <span class="screenevolutionTogglePassword dashicons dashicons-visibility"
                                  aria-hidden="true"></span>
                        </td>
                    </tr>
                    <tr valign="middle">
                        <th scope="row"><label
                                    for="screenevolution[openai_api_key]"><?php
                                _e('Open AI API Key', 'screenevolution'); ?></label>
                        </th>
                        <td class="relative">
                            <input type="password" id="screenevolution[openai_api_key]"
                                   name="screenevolution[openai_api_key]" class="regular-text"
                                   value="<?php
                                   echo $this->get_option('openai_api_key'); ?>" autocomplete="chrome-off">
                            <span class="screenevolutionTogglePassword dashicons dashicons-visibility"
                                  aria-hidden="true"></span>
                        </td>
                    </tr>
                    <tr valign="middle">
                        <th scope="row"><label
                                    for="screenevolution[seobility_nr_suggestions]"><?php
                                _e('Number of suggestions sent to OpenAI', 'screenevolution'); ?></label>
                        </th>
                        <td class="relative">
                            <input type="number" id="screenevolution[seobility_nr_suggestions]"
                                   name="screenevolution[seobility_nr_suggestions]" class="regular-text"
                                   min="1" placeholder="3"
                                   value="<?php
                                   echo $this->get_option('seobility_nr_suggestions'); ?>" autocomplete="chrome-off">
                        </td>
                    </tr>
                    <tr valign="middle">
                        <th scope="row"><label
                                    for="screenevolution[seobility_output_prepend]"><?php
                                _e('Text prepended to Seobility output', 'screenevolution'); ?>
                            </label>
                        </th>
                        <td class="relative">
                            <input type="text" id="screenevolution[seobility_output_prepend]"
                                   name="screenevolution[seobility_output_prepend]" class="regular-text"
                                   placeholder="A comment in a blog post that writes about"
                                   value="<?php
                                   echo $this->get_option('seobility_output_prepend'); ?>" autocomplete="chrome-off">
                        </td>
                    </tr>
                    <tr valign="middle">
                        <th scope="row"><label
                                    for="screenevolution[seobility_search_engine]"><?php
                                _e('Search engine for Seobility', 'screenevolution'); ?>
                                <div>
                                    <small>
                                        <?php _e('Default: google.com', 'screenevolution'); ?>
                                    </small>
                                </div>
                            </label>
                        </th>
                        <td class="relative">
                            <select id="screenevolution[seobility_search_engine]"
                                    name="screenevolution[seobility_search_engine]" class="regular-text">
                                <option value="" disabled="" selected><?php _e('Select a search engine',
                                        'screenevolution'); ?></option>
                                <option value="google.com" <?php selected($this->get_option('seobility_search_engine'),
                                    'google.com'); ?>>google.com
                                </option>
                                <option value="google.es" <?php selected($this->get_option('seobility_search_engine'),
                                    'google.es'); ?>>google.es
                                </option>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Enqueues the plugin options scripts.
     */
    public function admin_enqueue_scripts()
    {
        // check if we're on correct screen
        $screen = get_current_screen();
        if ($this->hook !== $screen->id) {
            return;
        }
        // enqueue plugin styles
        wp_enqueue_style($this->name, SCREENEVOLUTION_INTEGRATION_URL . 'assets/style.css', '', $this->version, 'all');

        // register script
        wp_register_script($this->name, SCREENEVOLUTION_INTEGRATION_URL . 'assets/index.js', 'jquery', $this->version,
            true);
        // add data available to js
        wp_localize_script($this->name, 'screenevolution', [
            'ajaxurl' => admin_url('admin-ajax.php'),
        ]);
        // enqueue script
        wp_enqueue_script($this->name);
    }

    public function front_enqueue_scripts()
    {
        // check if allowed
        if ( ! $this->check()) {
            return;
        }

        // check if we're on correct screen
        if ( ! is_singular('post')) {
            return;
        }

        // check if user logged in
        if ( ! is_user_logged_in()) {
            return;
        }

        // enqueue plugin styles
        wp_enqueue_style($this->name, SCREENEVOLUTION_INTEGRATION_URL . 'assets/style.css', '', $this->version, 'all');

        // register script
        wp_register_script($this->name, SCREENEVOLUTION_INTEGRATION_URL . 'assets/index.js', array('jquery'),
            $this->version,
            true);

        // add data available to js
        wp_localize_script($this->name, 'screenevolution', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'action'  => 'screenevolution_ajax_comment',
            'post_id' => get_the_ID(),
        ]);

        // enqueue script
        wp_enqueue_script($this->name);
    }

    /**
     * Ajax action run when triggering cronjob
     */
    public function screenevolution_ajax_generate()
    {
        // check if allowed
        if ( ! $this->check()) {
            wp_send_json_error([
                'message' => __('Something went wrong.', 'screenevolution')
            ]);
        }

        // check if referer is correct
        check_ajax_referer('screenevolution-comment', 'security');

        $postId = $_POST['post_id'];

        if (empty($postId) || empty(get_post($postId))) {
            wp_send_json_error([
                'message' => __('Something went wrong.', 'screenevolution')
            ]);
        }

        // Call seobility API
        $seobility = $this->call($this->seobility_api_url, [], 'GET', [
            'apikey'       => $this->seobility_api_key,
            'keyword'      => get_the_title($postId),
            'searchengine' => $this->get_option('seobility_search_engine', 'google.com'),
            'url'          => get_permalink($postId),
        ]);

        if (empty($seobility)) {
            wp_send_json_error([
                'message' => __('Something went wrong.', 'screenevolution')
            ]);
        }

        if (isset($seobility['termsuggestions']) && ! empty($seobility['termsuggestions'])) {

            // Randomize the suggestions order
            shuffle($seobility['termsuggestions']['more']);

            // Get only x number of suggestions
            if (!empty($seobility['termsuggestions']['ok'])) {
                $suggestions = array_slice($seobility['termsuggestions']['ok'], 0, $this->get_option('seobility_nr_suggestions', 3));
            } else {
                $suggestions = array_slice($seobility['termsuggestions']['more'], 0, $this->get_option('seobility_nr_suggestions', 3));
            }

            // Get the text we append to the seobility output
            $prepend = $this->get_option('seobility_output_prepend', __('A comment in a blog post that writes about', 'screenevolution'));

            $openaiData = [
                'prompt'            => trim($prepend) . ' ' . implode(', ', $suggestions),
                'max_tokens'        => 150,
                'temperature'       => 0.7,
                'top_p'             => 1,
                'frequency_penalty' => 0,
                'presence_penalty'  => 0,
                'best_of'           => 1
            ];

            $openai = $this->call($this->openai_api_url, json_encode($openaiData), 'POST', '', [
                'Authorization' => 'Bearer ' . $this->openai_api_key,
                'Content-Type'  => 'application/json',
            ]);

            if (empty($openai)) {
                wp_send_json_error([
                    'message' => __('Something went wrong.', 'screenevolution')
                ]);
            }

            if (isset($openai['choices']) && ! empty($openai['choices']) && is_array($openai['choices'])) {
                wp_send_json_success([
                    'message' => __('Comment generated.', 'screenevolution'),
                    'comment' => reset($openai['choices'])['text'],
                    'openai'  => $openai['choices']
                ]);
            }
        }

        wp_send_json_error([
            'message' => __('Something went wrong.', 'screenevolution')
        ]);
    }

    /**
     * @param  string  $url  screenevolution url to call
     * @param  array  $data  request data
     * @param  string  $method  request method
     * @param  false|array  $query  query args
     *
     * @return false|array
     */
    private function call($url, $data = [], $method = 'GET', $query = false, $headers = [])
    {
        if ( ! $method || ! $url) {
            return false;
        }

        if ($query) {
            $url = add_query_arg($query, $url);
        }

        if ($method == 'GET') {
            $response = wp_remote_get($url, array(
                'timeout' => 45,
                'headers' => $headers,
            ));
        } elseif ($method == 'POST') {
            $response = wp_remote_post($url, array(
                'timeout' => 45,
                'body'    => $data,
                'headers' => $headers,
            ));
        } else {
            $response = wp_remote_request($url, [
                'method'  => $method,
                'timeout' => 45,
                'body'    => $data,
                'headers' => $headers,
            ]);
        }

        // Check for error
        if (is_wp_error($response)) {
            return false;
        }

        // Parse remote response
        $data = wp_remote_retrieve_body($response);

        // Check for error
        if (is_wp_error($data)) {
            return false;
        }

        return json_decode($data, true);
    }

    /**
     * Add button to comment form.
     */
    public function add_button_to_comment_form()
    {
        // check if allowed
        if ( ! $this->check()) {
            return;
        }

        // check if we're on correct screen
        if ( ! is_singular('post')) {
            return;
        }

        // check if user logged in
        if ( ! is_user_logged_in()) {
            return;
        }

        ?>
        <div class="screenevolution-wrapper">
            <?php
            wp_nonce_field('screenevolution-comment', 'security');

            ?>
            <div class="button-holder">
                <button type="button" id="seobility-openai"><?php _e('Generate comment',
                        'screenevolution-seobility-openai'); ?></button>
            </div>
            <div class="messages">
                <p class="status"></p>
            </div>
        </div>
        <?php
    }

}

SCREENEVOLUTION_INTEGRATION_MAIN::get_instance();
