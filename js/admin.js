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
        sessionStorage.setItem('wcr_label_mode', opts.label_mode);
        // ★追加
        sessionStorage.setItem('wcr_color_palette', opts.color_palette);
    }

    function triggerUpdate() {
        $('.wcr-chart').trigger('wcr:config-update');
    }

    // Range Sliders
    $('.wcr-input-range').on('input', function () {
        var val = $(this).val();
        var name = $(this).attr('name');
        sessionStorage.setItem(name, val);

        var dispSelector = $(this).data('disp');
        var unit = $(this).data('unit');
        if (dispSelector) {
            $(dispSelector).text(val + unit);
        }
        triggerUpdate();
    });

    // Select Inputs
    $('.wcr-input-select').on('change', function () {
        var val = $(this).val();
        var name = $(this).attr('name');
        sessionStorage.setItem(name, val);
        triggerUpdate();
    });

    // Number Inputs
    $('.wcr-input-number').on('input', function () {
        var val = $(this).val();
        var name = $(this).attr('name');
        sessionStorage.setItem(name, val);
        triggerUpdate();
    });

    // Checkbox Inputs
    $('.wcr-input-check').on('change', function () {
        var val = $(this).is(':checked') ? '1' : '0'; // ★'0' に変更
        var name = $(this).attr('name');
        sessionStorage.setItem(name, val);
        triggerUpdate();
    });
});
