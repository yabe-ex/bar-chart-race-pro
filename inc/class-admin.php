<?php

class WpChartRaceAdmin {

    private $create_error = '';

    // デフォルト設定
    private function get_default_options() {
        return array(
            'speed'       => get_option('wcr_default_speed', 1.0),
            'bar_height'  => get_option('wcr_default_bar_height', 30),
            'bar_spacing' => get_option('wcr_default_bar_spacing', 10),
            'font_size'   => get_option('wcr_default_font_size', 1.0),
            'max_bars'    => get_option('wcr_default_max_bars', 10),
            'date_format' => get_option('wcr_default_date_format', 'YYYY-MM-DD'),
            'show_title'  => get_option('wcr_default_show_title', '1'),
            'margin_px'   => get_option('wcr_default_margin_px', 20),
            'label_mode'  => get_option('wcr_default_label_mode', 'both'),
            'color_palette' => get_option('wcr_default_color_palette', 'a'),
        );
    }

    public function create_menu() {
        remove_submenu_page('edit.php?post_type=chart_race', 'post-new.php?post_type=chart_race');

        $create_hook = add_submenu_page(
            'edit.php?post_type=chart_race',
            __('Create New Chart', 'bar-chart-race'),
            __('Create New', 'bar-chart-race'),
            'manage_options',
            'wcr-create',
            array($this, 'show_create_page')
        );

        add_action('load-' . $create_hook, array($this, 'process_create_form'));

        add_submenu_page(
            'edit.php?post_type=chart_race',
            __('Global Settings', 'bar-chart-race'),
            __('Settings', 'bar-chart-race'),
            'manage_options',
            'wcr-settings',
            array($this, 'show_global_settings_page')
        );
    }

    // 一覧ページにカスタム列を追加
    public function add_custom_columns($columns) {
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            // タイトルの直後にショートコード列を挿入
            if ($key === 'title') {
                $new_columns['wcr_shortcode'] = __('Shortcode', 'bar-chart-race');
            }
        }
        return $new_columns;
    }

    // カスタム列の中身を描画
    public function render_custom_columns($column, $post_id) {
        if ($column === 'wcr_shortcode') {
            $shortcode = '[chart_race id="' . $post_id . '"]';
            echo '<div style="display:flex; align-items:center; gap:8px;">';
            echo '<code id="wcr-sc-list-' . $post_id . '" style="background:#f0f0f1; padding:3px 6px; border-radius:3px;">' . esc_html($shortcode) . '</code>';
            echo '<button type="button" class="button button-small wcr-copy-btn" data-target="#wcr-sc-list-' . $post_id . '" title="' . esc_attr__('Copy', 'bar-chart-race') . '">';
            echo '<span class="dashicons dashicons-clipboard" style="font-size:16px; width:16px; height:16px; margin-top:2px;"></span>';
            echo '</button>';
            echo '<span class="wcr-copy-msg" style="font-weight:bold; color:#00796b;"></span>';
            echo '</div>';
        }
    }

    public function process_create_form() {
        if ('POST' !== $_SERVER['REQUEST_METHOD'] || !isset($_POST['wcr_upload_submit'])) return;
        if (!isset($_POST['wcr_nonce']) || !wp_verify_nonce($_POST['wcr_nonce'], 'wcr_create_action')) {
            $this->create_error = __('Security check failed.', 'bar-chart-race');
            return;
        }

        $title = isset($_POST['wcr_title']) ? sanitize_text_field(wp_unslash($_POST['wcr_title'])) : '';

        if (empty($title)) {
            $this->create_error = __('Title is required.', 'bar-chart-race');
        } elseif (empty($_FILES['wcr_csv']['tmp_name'])) {
            $this->create_error = __('CSV file is required.', 'bar-chart-race');
        } else {
            $data = $this->parse_csv($_FILES['wcr_csv']['tmp_name']);
            if (empty($data)) {
                $this->create_error = __('Failed to parse CSV.', 'bar-chart-race');
            } else {
                $post_id = wp_insert_post(array(
                    'post_type' => 'chart_race',
                    'post_status' => 'publish',
                    'post_title' => $title,
                    'post_author' => get_current_user_id() ?: 0,
                ));

                if ($post_id && !is_wp_error($post_id)) {
                    update_post_meta($post_id, '_wcr_data', wp_json_encode($data));

                    $defaults = $this->get_default_options();
                    update_post_meta($post_id, '_wcr_speed', $defaults['speed']);
                    update_post_meta($post_id, '_wcr_bar_height', $defaults['bar_height']);
                    update_post_meta($post_id, '_wcr_bar_spacing', $defaults['bar_spacing']);
                    update_post_meta($post_id, '_wcr_font_size', $defaults['font_size']);
                    update_post_meta($post_id, '_wcr_max_bars', $defaults['max_bars']);
                    update_post_meta($post_id, '_wcr_date_format', $defaults['date_format']);
                    update_post_meta($post_id, '_wcr_show_title', $defaults['show_title']);
                    update_post_meta($post_id, '_wcr_margin_px', $defaults['margin_px']);
                    update_post_meta($post_id, '_wcr_label_mode', $defaults['label_mode']);
                    update_post_meta($post_id, '_wcr_color_palette', $defaults['color_palette']);

                    wp_redirect(admin_url('post.php?post=' . $post_id . '&action=edit'));
                    exit;
                } else {
                    $this->create_error = __('Failed to create post.', 'bar-chart-race');
                }
            }
        }
    }

    public function admin_enqueue($hook) {
        $screen = get_current_screen();
        $is_wcr_page = (strpos($hook, 'chart_race') !== false) || ($screen && $screen->post_type === 'chart_race');
        if (!$is_wcr_page) return;

        $version = (defined('BAR_CHART_RACE_DEVELOP') && true === BAR_CHART_RACE_DEVELOP) ? time() : BAR_CHART_RACE_VERSION;

        wp_register_style(BAR_CHART_RACE_SLUG . '-front',  BAR_CHART_RACE_URL . '/css/front.css', array(), $version);
        wp_register_script(BAR_CHART_RACE_SLUG . '-front', BAR_CHART_RACE_URL . '/js/front.js', array('jquery'), $version, true);

        wp_enqueue_style(BAR_CHART_RACE_SLUG . '-front');
        wp_enqueue_script(BAR_CHART_RACE_SLUG . '-front');

        wp_enqueue_style(BAR_CHART_RACE_SLUG . '-admin',  BAR_CHART_RACE_URL . '/css/admin.css', array(), $version);

        // ★修正: 標準の「新規追加」ボタンを非表示にする
        wp_add_inline_style(BAR_CHART_RACE_SLUG . '-admin', '.page-title-action { display: none !important; }');

        wp_enqueue_script(BAR_CHART_RACE_SLUG . '-admin', BAR_CHART_RACE_URL . '/js/admin.js', array('jquery'), $version, true);

        $admin_vars = array(
            'copied' => __('Copied!', 'bar-chart-race'),
            'copy_fail' => __('Failed to copy.', 'bar-chart-race'),
        );
        wp_localize_script(BAR_CHART_RACE_SLUG . '-admin', 'wcr_admin_vars', $admin_vars);

        $front_i18n = array(
            'data_insufficient' => __('Data insufficient: At least 2 time points are required.', 'bar-chart-race'),
            'play' => __('Play', 'bar-chart-race'),
            'pause' => __('Pause', 'bar-chart-race'),
            'reset' => __('Reset', 'bar-chart-race'),
        );
        wp_localize_script(BAR_CHART_RACE_SLUG . '-front', 'wcr_front_i18n', $front_i18n);
    }

    public function plugin_action_links($links) {
        $url = '<a href="' . esc_url(admin_url("edit.php?post_type=chart_race&page=wcr-settings")) . '">' . esc_html__('Settings', 'bar-chart-race') . '</a>';
        array_unshift($links, $url);
        return $links;
    }

    public function add_meta_boxes() {
        add_meta_box(
            'wcr_preview_settings',
            __('Chart Preview & Settings', 'bar-chart-race'),
            array($this, 'render_preview_meta_box'),
            'chart_race',
            'normal',
            'high'
        );
    }

    public function render_preview_meta_box($post) {
        $json = get_post_meta($post->ID, '_wcr_data', true);
        $defaults = $this->get_default_options();

        $saved_title = get_post_meta($post->ID, '_wcr_show_title', true);
        $show_title = ($saved_title !== '') ? $saved_title : $defaults['show_title'];

        $options = array(
            'speed'       => get_post_meta($post->ID, '_wcr_speed', true) ?: $defaults['speed'],
            'bar_height'  => get_post_meta($post->ID, '_wcr_bar_height', true) ?: $defaults['bar_height'],
            'bar_spacing' => get_post_meta($post->ID, '_wcr_bar_spacing', true) ?: $defaults['bar_spacing'],
            'font_size'   => get_post_meta($post->ID, '_wcr_font_size', true) ?: $defaults['font_size'],
            'max_bars'    => get_post_meta($post->ID, '_wcr_max_bars', true) ?: $defaults['max_bars'],
            'date_format' => get_post_meta($post->ID, '_wcr_date_format', true) ?: $defaults['date_format'],
            'show_title'  => $show_title,
            'margin_px'   => get_post_meta($post->ID, '_wcr_margin_px', true) ?: $defaults['margin_px'],
            'label_mode'  => get_post_meta($post->ID, '_wcr_label_mode', true) ?: $defaults['label_mode'],
            'color_palette' => get_post_meta($post->ID, '_wcr_color_palette', true) ?: $defaults['color_palette'],
        );

        echo '<script>window.wcrInitialOptions = ' . json_encode($options) . ';</script>';

        wp_nonce_field('wcr_save_settings', 'wcr_settings_nonce');
?>

        <div class="wcr-shortcode-area">
            <p class="wcr-shortcode-label"><?php esc_html_e('Shortcode:', 'bar-chart-race'); ?></p>
            <div class="wcr-shortcode-box">
                <code id="wcr-shortcode-text">[chart_race id="<?php echo $post->ID; ?>"]</code>
                <button type="button" class="button wcr-copy-btn" data-target="#wcr-shortcode-text">
                    <span class="dashicons dashicons-clipboard"></span> <?php esc_html_e('Copy', 'bar-chart-race'); ?>
                </button>
                <span class="wcr-copy-msg"></span>
            </div>
        </div>

        <div class="wcr-admin-layout">
            <div class="wcr-chart-display">
                <?php if ($json) : ?>
                    <div class="wcr-chart-container">
                        <div class="wcr-chart" id="wcr-chart-preview"
                            data-chart="<?php echo esc_attr($json); ?>"
                            data-title="<?php echo esc_attr(get_the_title($post->ID)); ?>"
                            data-settings="<?php echo esc_attr(json_encode($options)); ?>">
                            <div class="wcr-loader">
                                <div class="wcr-spinner"></div>
                                <p>Now Loading...</p>
                            </div>
                        </div>
                    </div>
                <?php else : ?>
                    <p><?php esc_html_e('No data available.', 'bar-chart-race'); ?></p>
                <?php endif; ?>
            </div>

            <div class="wcr-settings-container">
                <h3><?php esc_html_e('Chart Settings', 'bar-chart-race'); ?></h3>

                <div class="wcr-control-group">
                    <label>
                        <input type="checkbox" name="wcr_show_title" class="wcr-input-check" value="1" <?php checked($options['show_title'], '1'); ?>>
                        <?php esc_html_e('Show Title', 'bar-chart-race'); ?>
                    </label>
                </div>

                <div class="wcr-control-group">
                    <label><?php esc_html_e('Color Palette', 'bar-chart-race'); ?></label>
                    <select name="wcr_color_palette" class="wcr-input-select">
                        <option value="a" <?php selected($options['color_palette'], 'a'); ?>>Palette A (Warm)</option>
                        <option value="b" <?php selected($options['color_palette'], 'b'); ?>>Palette B (Cool)</option>
                        <option value="c" <?php selected($options['color_palette'], 'c'); ?>>Palette C (Vivid)</option>
                        <option value="gray" <?php selected($options['color_palette'], 'gray'); ?>>Monochrome (Gray)</option>
                        <option value="metallic" <?php selected($options['color_palette'], 'metallic'); ?>>Metallic Mix</option>
                    </select>
                </div>

                <div class="wcr-control-group">
                    <label><?php esc_html_e('Container Margin (px)', 'bar-chart-race'); ?></label>
                    <input type="number" name="wcr_margin_px" class="wcr-input-number" min="0" max="200" step="1" value="<?php echo esc_attr($options['margin_px']); ?>" style="width:80px;"> px
                </div>

                <div class="wcr-control-group">
                    <label><?php esc_html_e('Label Display Mode', 'bar-chart-race'); ?></label>
                    <select name="wcr_label_mode" class="wcr-input-select">
                        <option value="outside_left" <?php selected($options['label_mode'], 'outside_left'); ?>><?php _e('Outside Left (Fixed)', 'bar-chart-race'); ?></option>
                        <option value="inside_left" <?php selected($options['label_mode'], 'inside_left'); ?>><?php _e('Inside Left', 'bar-chart-race'); ?></option>
                        <option value="inside_right" <?php selected($options['label_mode'], 'inside_right'); ?>><?php _e('Inside Right', 'bar-chart-race'); ?></option>
                        <option value="both" <?php selected($options['label_mode'], 'both'); ?>><?php _e('Both (Default)', 'bar-chart-race'); ?></option>
                    </select>
                </div>

                <hr>

                <div class="wcr-control-group">
                    <label><?php esc_html_e('Playback Speed', 'bar-chart-race'); ?>: <span class="wcr-val-display" id="disp-speed"><?php echo esc_html($options['speed']); ?>x</span></label>
                    <input type="range" name="wcr_speed" class="wcr-input-range" min="0.1" max="2.0" step="0.1" value="<?php echo esc_attr($options['speed']); ?>" data-disp="#disp-speed" data-unit="x">
                </div>
                <div class="wcr-control-group">
                    <label><?php esc_html_e('Bar Height', 'bar-chart-race'); ?>: <span class="wcr-val-display" id="disp-height"><?php echo esc_html($options['bar_height']); ?>px</span></label>
                    <input type="range" name="wcr_bar_height" class="wcr-input-range" min="20" max="50" step="1" value="<?php echo esc_attr($options['bar_height']); ?>" data-disp="#disp-height" data-unit="px">
                </div>
                <div class="wcr-control-group">
                    <label><?php esc_html_e('Bar Spacing', 'bar-chart-race'); ?>: <span class="wcr-val-display" id="disp-spacing"><?php echo esc_html($options['bar_spacing']); ?>px</span></label>
                    <input type="range" name="wcr_bar_spacing" class="wcr-input-range" min="0" max="20" step="0.5" value="<?php echo esc_attr($options['bar_spacing']); ?>" data-disp="#disp-spacing" data-unit="px">
                </div>
                <div class="wcr-control-group">
                    <label><?php esc_html_e('Font Size', 'bar-chart-race'); ?>: <span class="wcr-val-display" id="disp-font"><?php echo esc_html($options['font_size']); ?>em</span></label>
                    <input type="range" name="wcr_font_size" class="wcr-input-range" min="0.8" max="1.5" step="0.1" value="<?php echo esc_attr($options['font_size']); ?>" data-disp="#disp-font" data-unit="em">
                </div>
                <div class="wcr-control-group">
                    <label><?php esc_html_e('Number of items', 'bar-chart-race'); ?></label><br>
                    <select name="wcr_max_bars" class="wcr-input-select">
                        <?php foreach ([5, 10, 15, 20] as $num) : ?>
                            <option value="<?php echo $num; ?>" <?php selected($options['max_bars'], $num); ?>><?php echo $num; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="wcr-control-group">
                    <label><?php esc_html_e('Date Format', 'bar-chart-race'); ?></label><br>
                    <select name="wcr_date_format" class="wcr-input-select">
                        <option value="YYYY-MM-DD" <?php selected($options['date_format'], 'YYYY-MM-DD'); ?>>YYYY-MM-DD</option>
                        <option value="MM/DD/YYYY" <?php selected($options['date_format'], 'MM/DD/YYYY'); ?>>MM/DD/YYYY</option>
                        <option value="YYYY年MM月" <?php selected($options['date_format'], 'YYYY年MM月'); ?>>YYYY年MM月</option>
                    </select>
                </div>
            </div>
        </div>
    <?php
    }

    public function save_post($post_id) {
        if (!isset($_POST['wcr_settings_nonce']) || !wp_verify_nonce($_POST['wcr_settings_nonce'], 'wcr_save_settings')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $fields = ['wcr_speed', 'wcr_bar_height', 'wcr_bar_spacing', 'wcr_font_size', 'wcr_max_bars', 'wcr_date_format', 'wcr_margin_px', 'wcr_label_mode', 'wcr_color_palette'];
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, '_' . $field, sanitize_text_field($_POST[$field]));
            }
        }

        $show_title = isset($_POST['wcr_show_title']) ? '1' : '0';
        update_post_meta($post_id, '_wcr_show_title', $show_title);
    }

    private function parse_csv($path) {
        $rows = array();
        if (!file_exists($path) || !($handle = fopen($path, 'r'))) {
            return $rows;
        }

        $header = fgetcsv($handle);
        if (!is_array($header)) {
            fclose($handle);
            return $rows;
        }

        // BOM除去と小文字化
        $header = array_map(function ($h) {
            return strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $h)));
        }, $header);

        // ★追加: 必須カラムの存在チェック
        // time, label, value の3つが含まれていない場合はエラー(空配列)として弾く
        $required = array('time', 'label', 'value');
        $missing = array_diff($required, $header);
        if (!empty($missing)) {
            fclose($handle);
            return $rows; // これにより "Failed to parse CSV" エラーになります
        }

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) === count($header)) {
                $item = array_combine($header, $row);
                $rows[] = array(
                    'time'  => trim((string)($item['time'] ?? '')),
                    'label' => trim((string)($item['label'] ?? '')),
                    'value' => (float)($item['value'] ?? 0),
                );
            }
        }
        fclose($handle);
        return $rows;
    }

    public function show_create_page() {
        $error = $this->create_error;
    ?>
        <div class="wrap wcr-container">
            <h1><?php esc_html_e('Create New Chart', 'bar-chart-race'); ?></h1>
            <?php if ($error) echo '<div class="error"><p>' . esc_html($error) . '</p></div>'; ?>

            <div class="wcr-screen wcr-screen--upload wcr-active">
                <p class="wcr-subtitle"><?php esc_html_e('Please upload a CSV file (Columns: time, label, value)', 'bar-chart-race'); ?></p>
                <form method="post" enctype="multipart/form-data" class="wcr-upload-form">
                    <?php wp_nonce_field('wcr_create_action', 'wcr_nonce'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="wcr_title"><?php esc_html_e('Chart Title', 'bar-chart-race'); ?></label></th>
                            <td><input type="text" name="wcr_title" id="wcr_title" class="regular-text" placeholder="<?php esc_attr_e('Ex: Sales Ranking', 'bar-chart-race'); ?>" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="wcr_csv"><?php esc_html_e('CSV File', 'bar-chart-race'); ?></label></th>
                            <td><input type="file" name="wcr_csv" id="wcr_csv" accept=".csv" required></td>
                        </tr>
                    </table>
                    <?php submit_button(__('Upload CSV and Create', 'bar-chart-race'), 'primary', 'wcr_upload_submit'); ?>
                </form>
            </div>
        </div>
    <?php
    }

    public function show_global_settings_page() {
        if ('POST' === $_SERVER['REQUEST_METHOD'] && isset($_POST['wcr_global_submit'])) {
            if (isset($_POST['wcr_global_nonce']) && wp_verify_nonce($_POST['wcr_global_nonce'], 'wcr_save_global')) {
                update_option('wcr_default_speed', sanitize_text_field($_POST['wcr_default_speed']));
                update_option('wcr_default_bar_height', sanitize_text_field($_POST['wcr_default_bar_height']));
                update_option('wcr_default_bar_spacing', sanitize_text_field($_POST['wcr_default_bar_spacing']));
                update_option('wcr_default_font_size', sanitize_text_field($_POST['wcr_default_font_size']));
                update_option('wcr_default_max_bars', sanitize_text_field($_POST['wcr_default_max_bars']));
                update_option('wcr_default_date_format', sanitize_text_field($_POST['wcr_default_date_format']));

                $show_title = isset($_POST['wcr_default_show_title']) ? '1' : '0';
                update_option('wcr_default_show_title', $show_title);
                update_option('wcr_default_margin_px', sanitize_text_field($_POST['wcr_default_margin_px']));
                update_option('wcr_default_label_mode', sanitize_text_field($_POST['wcr_default_label_mode']));
                update_option('wcr_default_color_palette', sanitize_text_field($_POST['wcr_default_color_palette']));

                echo '<div class="updated"><p>' . esc_html__('Settings saved.', 'bar-chart-race') . '</p></div>';
            }
        }

        $defaults = $this->get_default_options();
    ?>
        <div class="wrap wcr-container">
            <h1><?php esc_html_e('Chart Race Global Settings', 'bar-chart-race'); ?></h1>
            <p><?php esc_html_e('These settings will be used as defaults for new charts.', 'bar-chart-race'); ?></p>

            <form method="post">
                <?php wp_nonce_field('wcr_save_global', 'wcr_global_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label><?php esc_html_e('Default Show Title', 'bar-chart-race'); ?></label></th>
                        <td>
                            <label><input type="checkbox" name="wcr_default_show_title" value="1" <?php checked($defaults['show_title'], '1'); ?>> <?php esc_html_e('Show Title', 'bar-chart-race'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label><?php esc_html_e('Default Color Palette', 'bar-chart-race'); ?></label></th>
                        <td>
                            <select name="wcr_default_color_palette">
                                <option value="a" <?php selected($defaults['color_palette'], 'a'); ?>>Palette A (Warm)</option>
                                <option value="b" <?php selected($defaults['color_palette'], 'b'); ?>>Palette B (Cool)</option>
                                <option value="c" <?php selected($defaults['color_palette'], 'c'); ?>>Palette C (Vivid)</option>
                                <option value="gray" <?php selected($defaults['color_palette'], 'gray'); ?>>Monochrome (Gray)</option>
                                <option value="metallic" <?php selected($defaults['color_palette'], 'metallic'); ?>>Metallic Mix</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label><?php esc_html_e('Default Margin', 'bar-chart-race'); ?></label></th>
                        <td><input type="number" step="1" min="0" max="200" name="wcr_default_margin_px" value="<?php echo esc_attr($defaults['margin_px']); ?>" class="small-text"> px</td>
                    </tr>
                    <tr>
                        <th scope="row"><label><?php esc_html_e('Default Label Mode', 'bar-chart-race'); ?></label></th>
                        <td><select name="wcr_default_label_mode">
                                <option value="outside_left" <?php selected($defaults['label_mode'], 'outside_left'); ?>>Outside Left (Fixed)</option>
                                <option value="inside_left" <?php selected($defaults['label_mode'], 'inside_left'); ?>>Inside Left</option>
                                <option value="inside_right" <?php selected($defaults['label_mode'], 'inside_right'); ?>>Inside Right</option>
                                <option value="both" <?php selected($defaults['label_mode'], 'both'); ?>>Both (Default)</option>
                            </select></td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <hr>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label><?php esc_html_e('Default Playback Speed', 'bar-chart-race'); ?></label></th>
                        <td><input type="number" step="0.1" min="0.1" max="5.0" name="wcr_default_speed" value="<?php echo esc_attr($defaults['speed']); ?>" class="small-text"> x</td>
                    </tr>
                    <tr>
                        <th scope="row"><label><?php esc_html_e('Default Bar Height', 'bar-chart-race'); ?></label></th>
                        <td><input type="number" step="1" min="10" max="100" name="wcr_default_bar_height" value="<?php echo esc_attr($defaults['bar_height']); ?>" class="small-text"> px</td>
                    </tr>
                    <tr>
                        <th scope="row"><label><?php esc_html_e('Default Bar Spacing', 'bar-chart-race'); ?></label></th>
                        <td><input type="number" step="0.5" min="0" max="50" name="wcr_default_bar_spacing" value="<?php echo esc_attr($defaults['bar_spacing']); ?>" class="small-text"> px</td>
                    </tr>
                    <tr>
                        <th scope="row"><label><?php esc_html_e('Default Font Size', 'bar-chart-race'); ?></label></th>
                        <td><input type="number" step="0.1" min="0.5" max="3.0" name="wcr_default_font_size" value="<?php echo esc_attr($defaults['font_size']); ?>" class="small-text"> em</td>
                    </tr>
                    <tr>
                        <th scope="row"><label><?php esc_html_e('Default Max Bars', 'bar-chart-race'); ?></label></th>
                        <td><select name="wcr_default_max_bars"><?php foreach ([5, 10, 15, 20] as $num) : ?><option value="<?php echo $num; ?>" <?php selected($defaults['max_bars'], $num); ?>><?php echo $num; ?></option><?php endforeach; ?></select></td>
                    </tr>
                    <tr>
                        <th scope="row"><label><?php esc_html_e('Default Date Format', 'bar-chart-race'); ?></label></th>
                        <td><select name="wcr_default_date_format">
                                <option value="YYYY-MM-DD" <?php selected($defaults['date_format'], 'YYYY-MM-DD'); ?>>YYYY-MM-DD</option>
                                <option value="MM/DD/YYYY" <?php selected($defaults['date_format'], 'MM/DD/YYYY'); ?>>MM/DD/YYYY</option>
                                <option value="YYYY年MM月" <?php selected($defaults['date_format'], 'YYYY年MM月'); ?>>YYYY年MM月</option>
                            </select></td>
                    </tr>
                </table>
                <?php submit_button(__('Save Changes', 'bar-chart-race'), 'primary', 'wcr_global_submit'); ?>
            </form>
        </div>
<?php
    }

    public function register_hooks() {
        add_action('admin_menu', array($this, 'create_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue'));
        add_filter('plugin_action_links_' . plugin_basename(BAR_CHART_RACE_PATH . '/bar-chart-race.php'), array($this, 'plugin_action_links'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_post'));

        // 一覧ページのフック
        add_filter('manage_chart_race_posts_columns', array($this, 'add_custom_columns'));
        add_action('manage_chart_race_posts_custom_column', array($this, 'render_custom_columns'), 10, 2);
    }
}
