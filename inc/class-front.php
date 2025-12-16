<?php

class WpChartRaceFront {

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

    private function convert_sheet_url_to_csv($url) {
        if (preg_match('/\/d\/([a-zA-Z0-9-_]+)/', $url, $matches)) {
            $key = $matches[1];
            return "https://docs.google.com/spreadsheets/d/{$key}/export?format=csv";
        }
        return false;
    }

    private function parse_csv_content($content) {
        $rows = array();
        if (empty($content)) return $rows;

        $encoding = mb_detect_encoding($content, 'UTF-8, SJIS-win, SJIS, EUC-JP, ASCII', true);
        if ($encoding && $encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }

        $lines = explode("\n", $content);
        if (empty($lines)) return $rows;

        $header_line = trim($lines[0]);
        $header_line = preg_replace('/^\xEF\xBB\xBF/', '', $header_line);
        $header = str_getcsv($header_line);
        if (!is_array($header)) return $rows;

        $header = array_map(function ($h) {
            return strtolower(trim($h));
        }, $header);

        $map = [
            'time'  => ['time', 'date', 'year', 'month', 'day', '日付', '年月', '時間', '年度'],
            'label' => ['label', 'name', 'item', 'title', 'category', 'ラベル', '名前', '項目', 'カテゴリ', 'タイトル'],
            'value' => ['value', 'count', 'amount', 'score', 'number', '値', '数値', '売上', '金額', 'スコア', '数']
        ];

        $indices = ['time' => -1, 'label' => -1, 'value' => -1];
        foreach ($header as $idx => $col) {
            foreach ($map as $key => $aliases) {
                if ($indices[$key] === -1 && in_array($col, $aliases)) {
                    $indices[$key] = $idx;
                    break;
                }
            }
        }

        if ($indices['time'] === -1 || $indices['label'] === -1 || $indices['value'] === -1) {
            return $rows;
        }

        for ($i = 1; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if (empty($line)) continue;

            $row = str_getcsv($line);
            if (count($row) === count($header)) {
                $rows[] = array(
                    'time'  => trim((string)($row[$indices['time']] ?? '')),
                    'label' => trim((string)($row[$indices['label']] ?? '')),
                    'value' => (float)($row[$indices['value']] ?? 0),
                );
            }
        }
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
                $source_type = isset($_POST['wcr_source_type']) ? sanitize_text_field($_POST['wcr_source_type']) : 'csv';
                $data = [];
                $parse_error = '';

                if (empty($title)) {
                    $error = __('Title is required.', 'bar-chart-race');
                } else {
                    if ($source_type === 'csv') {
                        if (empty($_FILES['wcr_csv']['tmp_name'])) {
                            $error = __('Please select a CSV file.', 'bar-chart-race');
                        } else {
                            $raw = file_get_contents($_FILES['wcr_csv']['tmp_name']);
                            $data = $this->parse_csv_content($raw);
                            if (empty($data)) $parse_error = __('Failed to load CSV. Please check the format.', 'bar-chart-race');
                        }
                    } elseif ($source_type === 'g_sheet') {
                        $sheet_url = isset($_POST['wcr_sheet_url']) ? esc_url_raw($_POST['wcr_sheet_url']) : '';
                        if (empty($sheet_url)) {
                            $error = __('Google Sheet URL is required.', 'bar-chart-race');
                        } else {
                            $csv_url = $this->convert_sheet_url_to_csv($sheet_url);
                            if (!$csv_url) {
                                $error = __('Invalid Google Sheet URL.', 'bar-chart-race');
                            } else {
                                $response = wp_remote_get($csv_url);
                                if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                                    $error = __('Failed to fetch data from Google Sheets.', 'bar-chart-race');
                                } else {
                                    $raw = wp_remote_retrieve_body($response);
                                    $data = $this->parse_csv_content($raw);
                                    if (empty($data)) $parse_error = __('Failed to parse Sheet data.', 'bar-chart-race');
                                }
                            }
                        }
                    }

                    if ($parse_error) {
                        $error = $parse_error;
                    } elseif (!$error && !empty($data)) {
                        $post_id = wp_insert_post(array('post_type' => 'chart_race', 'post_status' => 'publish', 'post_title' => $title, 'post_author' => get_current_user_id() ?: 0));
                        if ($post_id && !is_wp_error($post_id)) {
                            update_post_meta($post_id, '_wcr_data', wp_json_encode($data));

                            update_post_meta($post_id, '_wcr_source_type', $source_type);
                            if ($source_type === 'g_sheet' && isset($_POST['wcr_sheet_url'])) {
                                update_post_meta($post_id, '_wcr_sheet_url', esc_url_raw($_POST['wcr_sheet_url']));
                            }

                            // 最新のデフォルト設定を適用
                            $defaults = array(
                                'speed'       => get_option('wcr_default_speed', 1.0),
                                'bar_height'  => get_option('wcr_default_bar_height', 30),
                                'bar_spacing' => get_option('wcr_default_bar_spacing', 10),
                                'font_size'   => get_option('wcr_default_font_size', 1.0),
                                'max_bars'    => get_option('wcr_default_max_bars', 10),
                                'date_format' => get_option('wcr_default_date_format', 'YYYY-MM-DD'),
                                'show_title'  => get_option('wcr_default_show_title', '1'),
                                'margin_px'   => get_option('wcr_default_margin_px', 20),
                                'label_type_outside' => get_option('wcr_default_label_type_outside', 'text'),
                                'label_type_inside'  => get_option('wcr_default_label_type_inside', 'text'),
                                'color_palette' => get_option('wcr_default_color_palette', 'a'),
                                'loop'          => get_option('wcr_default_loop', '0'),
                                'bg_image'      => '',
                                'text_color'    => '#333333',
                                'label_settings' => '{}',
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
                    <h3 class="wcr-success-title"><?php echo esc_html($message); ?></h3>
                    <?php
                    $json = get_post_meta($post_id, '_wcr_data', true);
                    $settings = array();
                    $keys = ['speed', 'bar_height', 'bar_spacing', 'font_size', 'max_bars', 'date_format', 'show_title', 'margin_px', 'label_type_outside', 'label_type_inside', 'color_palette', 'loop', 'bg_image', 'text_color', 'label_settings'];
                    foreach ($keys as $k) $settings[$k] = get_post_meta($post_id, '_wcr_' . $k, true);

                    echo '<div class="wcr-chart-container"><div class="wcr-chart" id="wcr-chart-' . esc_attr($post_id) . '" data-chart="' . esc_attr($json) . '" data-title="' . esc_attr(get_the_title($post_id)) . '" data-settings="' . esc_attr(json_encode($settings)) . '"><div class="wcr-loader"><div class="wcr-spinner"></div><p>Now Loading...</p></div></div></div>';
                    ?>
                    <div class="wcr-front-actions">
                        <a href="<?php echo esc_url(remove_query_arg('wcr_submitted')); ?>" class="wcr-btn wcr-btn-primary"><?php esc_html_e('Create another chart', 'bar-chart-race'); ?></a>
                    </div>
                </div>
            <?php else : ?>
                <div class="wcr-upload-view">
                    <h3 class="wcr-form-title"><?php esc_html_e('Create Your Bar Chart Race', 'bar-chart-race'); ?></h3>
                    <?php if ($error) : ?>
                        <div class="wcr-message wcr-error"><?php echo esc_html($error); ?></div>
                    <?php endif; ?>
                    <form method="post" enctype="multipart/form-data" class="wcr-front-form">
                        <?php wp_nonce_field('wcr_front_upload', 'wcr_nonce'); ?>

                        <div class="wcr-form-group">
                            <label for="wcr_title"><?php esc_html_e('Chart Title', 'bar-chart-race'); ?></label>
                            <input type="text" name="wcr_title" id="wcr_title" class="wcr-input-lg" placeholder="<?php esc_attr_e('Ex: Population Trends by Prefecture', 'bar-chart-race'); ?>" required>
                        </div>

                        <div class="wcr-form-group">
                            <label><?php esc_html_e('Data Source', 'bar-chart-race'); ?></label>
                            <div class="wcr-radio-group">
                                <label><input type="radio" name="wcr_source_type" value="csv" checked class="wcr-source-toggle"> <?php esc_html_e('Upload CSV File', 'bar-chart-race'); ?></label>
                                <label style="margin-left:15px;"><input type="radio" name="wcr_source_type" value="g_sheet" class="wcr-source-toggle"> <?php esc_html_e('Google Sheets URL', 'bar-chart-race'); ?></label>
                            </div>
                        </div>

                        <div class="wcr-form-group wcr-source-input wcr-source-csv">
                            <label for="wcr_csv"><?php esc_html_e('CSV File', 'bar-chart-race'); ?></label>
                            <div class="wcr-file-input-wrapper">
                                <input type="file" name="wcr_csv" id="wcr_csv" accept=".csv">
                            </div>
                            <p class="wcr-help-text"><?php _e('Supported columns: time (日付), label (名前), value (値)', 'bar-chart-race'); ?></p>
                        </div>

                        <div class="wcr-form-group wcr-source-input wcr-source-g_sheet" style="display:none;">
                            <label for="wcr_sheet_url"><?php esc_html_e('Google Sheets URL', 'bar-chart-race'); ?></label>
                            <input type="url" name="wcr_sheet_url" id="wcr_sheet_url" class="wcr-input-lg" placeholder="https://docs.google.com/spreadsheets/d/...">
                            <p class="wcr-help-text"><?php _e('Make sure the sheet is public (Anyone with the link).', 'bar-chart-race'); ?></p>
                        </div>

                        <div class="wcr-form-actions">
                            <button type="submit" name="wcr_front_submit" class="wcr-btn wcr-btn-primary wcr-btn-lg"><?php esc_html_e('Create and Show Chart', 'bar-chart-race'); ?></button>
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
            'label_type_outside' => 'text',
            'label_type_inside' => 'text',
            'color_palette' => 'a',
            'loop'          => '0',
            'bg_image'      => '',
            'text_color'    => '#333333',
            'label_settings' => '{}',
        );

        $settings = array();
        $keys = ['speed', 'bar_height', 'bar_spacing', 'font_size', 'max_bars', 'date_format', 'show_title', 'margin_px', 'label_type_outside', 'label_type_inside', 'color_palette', 'loop', 'bg_image', 'text_color', 'label_settings'];
        foreach ($keys as $k) {
            $val = get_post_meta($post_id, '_wcr_' . $k, true);
            $settings[$k] = ($val !== '') ? $val : $defaults[$k];
        }

        $this->enqueue_assets();
        return '<div class="wcr-chart-container"><div class="wcr-chart" id="wcr-chart-' . esc_attr($post_id) . '" data-chart="' . esc_attr($json) . '" data-title="' . esc_attr(get_the_title($post_id)) . '" data-settings="' . esc_attr(json_encode($settings)) . '"><div class="wcr-loader"><div class="wcr-spinner"></div><p>Now Loading...</p></div></div></div>';
    }
}
