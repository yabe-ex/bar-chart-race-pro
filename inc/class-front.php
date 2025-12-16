<?php

class WpChartRaceFront {
    // (front_enqueue, enqueue_assets, parse_csv は変更なし)

    public function front_enqueue() {
        $version  = (defined('BAR_CHART_RACE_DEVELOP') && true === BAR_CHART_RACE_DEVELOP) ? time() : BAR_CHART_RACE_VERSION;
        wp_register_style(BAR_CHART_RACE_SLUG . '-front',  BAR_CHART_RACE_URL . '/css/front.css', array(), $version);
        wp_register_script(BAR_CHART_RACE_SLUG . '-front', BAR_CHART_RACE_URL . '/js/front.js', array('jquery'), $version, true);
    }

    private function enqueue_assets() {
        wp_enqueue_style(BAR_CHART_RACE_SLUG . '-front');
        wp_enqueue_script(BAR_CHART_RACE_SLUG . '-front');
        $front_vars = array('ajaxurl' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce(BAR_CHART_RACE_SLUG));
        wp_localize_script(BAR_CHART_RACE_SLUG . '-front', 'wcr_front_vars', $front_vars);
        $front_i18n = array(
            'data_insufficient' => __('Data insufficient: At least 2 time points are required.', 'bar-chart-race'),
            'play' => __('Play', 'bar-chart-race'),
            'pause' => __('Pause', 'bar-chart-race'),
            'reset' => __('Reset', 'bar-chart-race'),
        );
        wp_localize_script(BAR_CHART_RACE_SLUG . '-front', 'wcr_front_i18n', $front_i18n);
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

        $header = array_map(function ($h) {
            return strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $h)));
        }, $header);

        // ★追加: 必須カラムチェック
        $required = array('time', 'label', 'value');
        $missing = array_diff($required, $header);
        if (!empty($missing)) {
            fclose($handle);
            return $rows;
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

    public function shortcode_upload_form($atts) {
        $this->enqueue_assets();
        $message = '';
        $error = '';
        $post_id = 0;
        $show_chart = false;

        if ('POST' === $_SERVER['REQUEST_METHOD'] && isset($_POST['wcr_front_submit'])) {
            if (!isset($_POST['wcr_nonce']) || !wp_verify_nonce($_POST['wcr_nonce'], 'wcr_front_upload')) {
                $error = __('Security check failed.', 'bar-chart-race');
            } else {
                $title = isset($_POST['wcr_title']) ? sanitize_text_field(wp_unslash($_POST['wcr_title'])) : '';
                if (empty($title)) {
                    $error = __('Title is required.', 'bar-chart-race');
                } elseif (empty($_FILES['wcr_csv']['tmp_name'])) {
                    $error = __('Please select a CSV file.', 'bar-chart-race');
                } else {
                    $data = $this->parse_csv($_FILES['wcr_csv']['tmp_name']);
                    if (empty($data)) {
                        $error = __('Failed to load CSV. Please check the format.', 'bar-chart-race');
                    } else {
                        $post_id = wp_insert_post(array('post_type' => 'chart_race', 'post_status' => 'publish', 'post_title' => $title, 'post_author' => get_current_user_id() ?: 0));
                        if ($post_id && !is_wp_error($post_id)) {
                            update_post_meta($post_id, '_wcr_data', wp_json_encode($data));
                            $defaults = array(
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
                            foreach ($defaults as $key => $val) {
                                update_post_meta($post_id, '_wcr_' . $key, $val);
                            }
                            $show_chart = true;
                            $message = __('Chart created successfully!', 'bar-chart-race');
                        } else {
                            $error = __('Failed to save data.', 'bar-chart-race');
                        }
                    }
                }
            }
        }

        ob_start();
?>
        <div class="wcr-front-container">
            <?php if ($show_chart && $post_id) : ?>
                <div class="wcr-success-view">
                    <!-- <div class="wcr-message wcr-success"><?php echo esc_html($message); ?></div>
                    <p><?php esc_html_e('Created Chart ID:', 'bar-chart-race'); ?> <strong><?php echo esc_html($post_id); ?></strong></p>
                    <p><?php esc_html_e('Shortcode to embed this chart:', 'bar-chart-race'); ?> <code>[chart_race id="<?php echo $post_id; ?>"]</code></p>
                    <hr>
                    <h3><?php esc_html_e('Preview', 'bar-chart-race'); ?></h3> -->
                    <?php
                    $json = get_post_meta($post_id, '_wcr_data', true);
                    $settings = array();
                    $keys = ['speed', 'bar_height', 'bar_spacing', 'font_size', 'max_bars', 'date_format', 'show_title', 'margin_px', 'label_mode', 'color_palette'];
                    foreach ($keys as $k) $settings[$k] = get_post_meta($post_id, '_wcr_' . $k, true);

                    // ★ローディング表示込み
                    echo '<div class="wcr-chart-container"><div class="wcr-chart" id="wcr-chart-' . esc_attr($post_id) . '" data-chart="' . esc_attr($json) . '" data-title="' . esc_attr(get_the_title($post_id)) . '" data-settings="' . esc_attr(json_encode($settings)) . '"><div class="wcr-loader"><div class="wcr-spinner"></div><p>Now Loading...</p></div></div></div>';
                    ?>
                    <p style="margin-top:20px;">
                        <a href="<?php echo esc_url(remove_query_arg('wcr_submitted')); ?>" class="wcr-btn"><?php esc_html_e('Create another chart', 'bar-chart-race'); ?></a>
                    </p>
                </div>
            <?php else : ?>
                <div class="wcr-upload-view">
                    <?php if ($error) : ?>
                        <div class="wcr-message wcr-error"><?php echo esc_html($error); ?></div>
                    <?php endif; ?>
                    <form method="post" enctype="multipart/form-data" class="wcr-front-form">
                        <?php wp_nonce_field('wcr_front_upload', 'wcr_nonce'); ?>
                        <div class="wcr-form-group">
                            <label for="wcr_title"><?php esc_html_e('Chart Title', 'bar-chart-race'); ?></label>
                            <input type="text" name="wcr_title" id="wcr_title" placeholder="<?php esc_attr_e('Ex: Population Trends by Prefecture', 'bar-chart-race'); ?>" required style="width:100%;">
                        </div>
                        <div class="wcr-form-group">
                            <label for="wcr_csv"><?php esc_html_e('CSV File (Columns: time, label, value)', 'bar-chart-race'); ?></label>
                            <input type="file" name="wcr_csv" id="wcr_csv" accept=".csv" required>
                        </div>
                        <div class="wcr-form-actions">
                            <button type="submit" name="wcr_front_submit" class="wcr-btn"><?php esc_html_e('Create and Show Chart', 'bar-chart-race'); ?></button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
<?php
        return ob_get_clean();
    }

    public function shortcode_display($atts) {
        $atts = shortcode_atts(array('id' => 0), $atts, 'chart_race');
        $post_id = (int)$atts['id'];
        if (!$post_id) return '';
        $json = get_post_meta($post_id, '_wcr_data', true);
        if (empty($json)) return '';

        $defaults = array(
            'speed'       => 1.0,
            'bar_height'  => 30,
            'bar_spacing' => 10,
            'font_size'   => 1.0,
            'max_bars'    => 10,
            'date_format' => 'YYYY-MM-DD',
            'show_title'  => '1',
            'margin_px'   => 20,
            'label_mode'  => 'both',
            'color_palette' => 'a',
        );

        $settings = array();
        $keys = ['speed', 'bar_height', 'bar_spacing', 'font_size', 'max_bars', 'date_format', 'show_title', 'margin_px', 'label_mode', 'color_palette'];
        foreach ($keys as $k) {
            $val = get_post_meta($post_id, '_wcr_' . $k, true);
            $settings[$k] = ($val !== '') ? $val : $defaults[$k];
        }

        $this->enqueue_assets();
        // ★ローディング表示込み
        return '<div class="wcr-chart-container"><div class="wcr-chart" id="wcr-chart-' . esc_attr($post_id) . '" data-chart="' . esc_attr($json) . '" data-title="' . esc_attr(get_the_title($post_id)) . '" data-settings="' . esc_attr(json_encode($settings)) . '"><div class="wcr-loader"><div class="wcr-spinner"></div><p>Now Loading...</p></div></div></div>';
    }
}
