jQuery(document).ready(function ($) {
    // Copy to Clipboard
    $('.wcr-copy-btn').on('click', function () {
        var targetSelector = $(this).data('target');
        var $target = $(targetSelector);
        var textToCopy = $target.text() || $target.val();
        var $msg = $(this).siblings('.wcr-copy-msg');

        if (navigator.clipboard) {
            navigator.clipboard.writeText(textToCopy).then(
                function () {
                    $msg.text(wcr_admin_vars.copied).fadeIn().delay(2000).fadeOut();
                },
                function () {
                    $msg.text(wcr_admin_vars.copy_fail).fadeIn().delay(2000).fadeOut();
                }
            );
        } else {
            var $temp = $('<input>');
            $('body').append($temp);
            $temp.val(textToCopy).select();
            document.execCommand('copy');
            $temp.remove();
            $msg.text(wcr_admin_vars.copied).fadeIn().delay(2000).fadeOut();
        }
    });

    // Initialize Accordion
    $('#wcr-label-accordion').accordion({
        header: '.wcr-accordion-header',
        collapsible: true,
        active: false,
        heightStyle: 'content'
    });

    // Preview Logic
    if (window.wcrInitialOptions) {
        var opts = window.wcrInitialOptions;
        sessionStorage.setItem('wcr_speed', opts.speed);
        sessionStorage.setItem('wcr_bar_height', opts.bar_height);
        sessionStorage.setItem('wcr_bar_spacing', opts.bar_spacing);
        sessionStorage.setItem('wcr_font_size', opts.font_size);
        sessionStorage.setItem('wcr_max_bars', opts.max_bars);
        sessionStorage.setItem('wcr_date_format', opts.date_format);
        sessionStorage.setItem('wcr_show_title', opts.show_title);
        sessionStorage.setItem('wcr_margin_px', opts.margin_px);

        // 新設定
        sessionStorage.setItem('wcr_label_type_outside', opts.label_type_outside);
        sessionStorage.setItem('wcr_label_type_inside', opts.label_type_inside);

        sessionStorage.setItem('wcr_color_palette', opts.color_palette);
        sessionStorage.setItem('wcr_loop', opts.loop);
        sessionStorage.setItem('wcr_bg_image', opts.bg_image);
        sessionStorage.setItem('wcr_text_color', opts.text_color);
        sessionStorage.setItem('wcr_label_settings', opts.label_settings);
    }

    function triggerUpdate() {
        $('.wcr-chart').trigger('wcr:config-update');
    }

    // Label Settings
    function updateLabelSettings() {
        var settings = {};
        $('#wcr-label-accordion .wcr-accordion-content').each(function () {
            var label = $(this).data('label');
            var color = $(this).find('.wcr-label-color').val();
            var icon = $(this).find('.wcr-label-icon').val();

            if (color || icon) {
                settings[label] = {
                    color: color,
                    icon: icon
                };
            }
        });
        var jsonStr = JSON.stringify(settings);
        $('#wcr_label_settings').val(jsonStr);
        sessionStorage.setItem('wcr_label_settings', jsonStr);
        triggerUpdate();
    }

    $('.wcr-label-color, .wcr-label-icon').on('change input', updateLabelSettings);

    // Inputs
    $('.wcr-input-range, .wcr-input-select, .wcr-input-number, .wcr-input-check, .wcr-input-text, .wcr-input-textarea, input[type="color"]').on(
        'input change',
        function () {
            var val = $(this).attr('type') === 'checkbox' ? ($(this).is(':checked') ? '1' : '0') : $(this).val();
            var name = $(this).attr('name');

            // sessionStorage
            sessionStorage.setItem(name, val);

            // range value display update
            if ($(this).hasClass('wcr-input-range')) {
                var dispSelector = $(this).data('disp');
                var unit = $(this).data('unit');
                if (dispSelector) {
                    $(dispSelector).text(val + unit);
                }
            }

            triggerUpdate();
        }
    );

    // Source Selection Toggle
    $('input[name="wcr_source_type"]').on('change', function () {
        var val = $(this).val();
        if (val === 'csv') {
            $('.wcr-source-csv-row').show();
            $('.wcr-source-sheet-row').hide();
            $('#wcr_csv').prop('required', true);
            $('#wcr_sheet_url').prop('required', false);
        } else {
            $('.wcr-source-csv-row').hide();
            $('.wcr-source-sheet-row').show();
            $('#wcr_csv').prop('required', false);
            $('#wcr_sheet_url').prop('required', true);
        }
    });

    // AJAX Sync
    $('.wcr-sync-btn').on('click', function () {
        if (!confirm(wcr_admin_vars.confirm_sync)) return;

        var $btn = $(this);
        var postId = $btn.data('id');
        var $msg = $btn.siblings('.wcr-sync-msg');

        $btn.prop('disabled', true);
        $msg.text('Syncing...').css('color', '#666').show();

        $.post(
            wcr_admin_vars.ajaxurl,
            {
                action: 'wcr_refresh_sheet',
                nonce: wcr_admin_vars.nonce,
                post_id: postId
            },
            function (res) {
                $btn.prop('disabled', false);
                if (res.success) {
                    $msg.text(res.data.message).css('color', 'green');
                    setTimeout(function () {
                        location.reload();
                    }, 1500);
                } else {
                    $msg.text(res.data || 'Error').css('color', 'red');
                }
            }
        );
    });

    // Media Uploader
    var wcr_media_frame;
    $('.wcr-media-btn').on('click', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var targetSelector = $btn.data('target');

        if (wcr_media_frame) {
            wcr_media_frame.open();
            return;
        }

        wcr_media_frame = wp.media({
            title: 'Select Image',
            button: { text: 'Use this image' },
            multiple: false
        });

        wcr_media_frame.on('select', function () {
            var attachment = wcr_media_frame.state().get('selection').first().toJSON();
            $(targetSelector).val(attachment.url).trigger('input');
        });

        wcr_media_frame.open();
    });

    var wcr_icon_frame;
    $(document).on('click', '.wcr-media-btn-sub', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var $targetInput = $btn.prev('input');

        wcr_icon_frame = wp.media({
            title: 'Select Icon',
            button: { text: 'Use this icon' },
            multiple: false
        });

        wcr_icon_frame.on('select', function () {
            var attachment = wcr_icon_frame.state().get('selection').first().toJSON();
            $targetInput.val(attachment.url).trigger('input');
        });

        wcr_icon_frame.open();
    });

    // Simple Data Editor
    var editorData = [];
    var $chart = $('#wcr-chart-preview');
    if ($chart.length) {
        try {
            editorData = JSON.parse($chart.attr('data-chart'));
        } catch (e) {}
    }

    function renderEditor() {
        var html = '';
        editorData.forEach(function (row, index) {
            html += '<tr data-idx="' + index + '">';
            html += '<td><input type="text" value="' + (row.time || '') + '" class="wcr-ed-time"></td>';
            html += '<td><input type="text" value="' + (row.label || '') + '" class="wcr-ed-label"></td>';
            html += '<td><input type="number" step="any" value="' + (row.value || 0) + '" class="wcr-ed-value"></td>';
            html += '<td><button type="button" class="button wcr-del-row">×</button></td>';
            html += '</tr>';
        });
        $('#wcr-editor-tbody').html(html);
        updateHiddenInput();
    }

    function updateHiddenInput() {
        $('#wcr_editor_data').val(JSON.stringify(editorData));
    }

    $('.wcr-toggle-editor').on('click', function () {
        $('.wcr-data-editor').slideToggle();
        if ($('#wcr-editor-tbody').is(':empty')) {
            renderEditor();
        }
    });

    $(document).on('click', '.wcr-del-row', function () {
        var idx = $(this).closest('tr').data('idx');
        editorData.splice(idx, 1);
        renderEditor();
    });

    $('.wcr-add-row-btn').on('click', function () {
        var last = editorData.length > 0 ? editorData[editorData.length - 1] : { time: '', label: '', value: 0 };
        editorData.push({ time: last.time, label: '', value: 0 });
        renderEditor();
    });

    $(document).on('input', '.wcr-ed-time, .wcr-ed-label, .wcr-ed-value', function () {
        var $tr = $(this).closest('tr');
        var idx = $tr.data('idx');
        var time = $tr.find('.wcr-ed-time').val();
        var label = $tr.find('.wcr-ed-label').val();
        var value = parseFloat($tr.find('.wcr-ed-value').val()) || 0;

        editorData[idx] = { time: time, label: label, value: value };
        updateHiddenInput();
    });
});
