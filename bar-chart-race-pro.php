<?php

/**
 * Plugin Name: Bar Chart Race Pro
 * Description: Upload CSV data to create animated bar chart races.
 * Version: 1.0.0
 * Author: Edel Hearts
 * Author URI: https://edel-hearts.com
 * Text Domain: bar-chart-race
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) exit();

$info = get_file_data(__FILE__, array('plugin_name' => 'Plugin Name', 'version' => 'Version'));

define('BAR_CHART_RACE_URL', plugins_url('', __FILE__));
define('BAR_CHART_RACE_PATH', dirname(__FILE__));
define('BAR_CHART_RACE_NAME', $info['plugin_name']);
define('BAR_CHART_RACE_SLUG', 'bar-chart-race');
define('BAR_CHART_RACE_VERSION', $info['version']);
define('BAR_CHART_RACE_DEVELOP', true);

class WpChartRace {
    public function init() {
        $this->register_cpt();

        // 管理画面側の処理
        require_once BAR_CHART_RACE_PATH . '/inc/class-admin.php';
        $admin = new WpChartRaceAdmin();

        if (method_exists($admin, 'register_hooks')) {
            $admin->register_hooks();
        } else {
            add_action('admin_menu', array($admin, 'create_menu'));
            add_action('admin_enqueue_scripts', array($admin, 'admin_enqueue'));
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($admin, 'plugin_action_links'));
            add_action('add_meta_boxes', array($admin, 'add_meta_boxes'));
            add_action('save_post', array($admin, 'save_post'));
        }

        // フロントエンドの処理
        require_once BAR_CHART_RACE_PATH . '/inc/class-front.php';
        $front = new WpChartRaceFront();

        // アセット読み込み
        add_action('wp_enqueue_scripts', array($front, 'front_enqueue'));

        // ショートコード登録
        add_shortcode('chart_race', array($front, 'shortcode_display'));
        add_shortcode('chart_race_upload_form', array($front, 'shortcode_upload_form'));
    }

    private function register_cpt() {
        register_post_type('chart_race', array(
            'labels' => array(
                'name' => __('Chart Races', 'bar-chart-race'),
                'singular_name' => __('Chart Race', 'bar-chart-race'),
                'add_new' => __('Add New', 'bar-chart-race'),
                'add_new_item' => __('Add New Chart Race', 'bar-chart-race'),
                'edit_item' => __('Edit Chart Race', 'bar-chart-race'),
                'new_item' => __('New Chart Race', 'bar-chart-race'),
                'view_item' => __('View Chart Race', 'bar-chart-race'),
                'search_items' => __('Search Chart Races', 'bar-chart-race'),
                'not_found' => __('No chart races found', 'bar-chart-race'),
                'not_found_in_trash' => __('No chart races found in Trash', 'bar-chart-race'),
            ),
            'public' => false,
            'show_ui' => true,
            'supports' => array('title'),
            'menu_icon' => 'dashicons-chart-bar',
        ));
    }
}

$instance = new WpChartRace();
add_action('init', array($instance, 'init'));
