(function ($) {
    'use strict';

    var PALETTES = {
        a: [
            '#e76f51',
            '#f4a261',
            '#e9c46a',
            '#2a9d8f',
            '#264653',
            '#ef476f',
            '#ffd166',
            '#06d6a0',
            '#118ab2',
            '#073b4c',
            '#ff6b6b',
            '#f7b801',
            '#6a4c93',
            '#43aa8b',
            '#4d908e'
        ],
        b: [
            '#0b3954',
            '#087e8b',
            '#bfd7ea',
            '#ff5a5f',
            '#c81d25',
            '#1b998b',
            '#2d3047',
            '#fffd82',
            '#ff9b71',
            '#e84855',
            '#3d5a80',
            '#98c1d9',
            '#293241',
            '#ee6c4d',
            '#e0fbfc'
        ],
        c: [
            '#ff595e',
            '#ffca3a',
            '#8ac926',
            '#1982c4',
            '#6a4c93',
            '#f72585',
            '#b5179e',
            '#7209b7',
            '#3a0ca3',
            '#4361ee',
            '#4cc9f0',
            '#06d6a0',
            '#ffd166',
            '#ef476f',
            '#118ab2'
        ],
        gray: [
            '#000000',
            '#1a1a1a',
            '#262626',
            '#333333',
            '#404040',
            '#4d4d4d',
            '#595959',
            '#666666',
            '#808080',
            '#999999',
            '#a6a6a6',
            '#b3b3b3',
            '#bfbfbf',
            '#cccccc',
            '#e6e6e6'
        ],
        metallic: [
            '#FFD700',
            '#C0C0C0',
            '#CD7F32',
            '#B8860B',
            '#A9A9A9',
            '#DAA520',
            '#D3D3D3',
            '#D2691E',
            '#EEE8AA',
            '#708090',
            '#E5E4E2',
            '#B0C4DE',
            '#F4A460',
            '#A0522D',
            '#696969'
        ]
    };

    var CONFIG = {
        DEFAULT_DURATION: 1500,
        DEFAULT_MAX_BARS: 10,
        POSITION_EASING: 0.15,
        DEFAULTS: {
            maxBars: 10,
            barHeight: 30,
            speed: 1.0,
            dateFormat: 'YYYY-MM-DD',
            barSpacing: 10,
            fontSize: 1.0,
            showTitle: '1',
            marginPx: 20,
            labelMode: 'both',
            colorPalette: 'a'
        }
    };

    function formatNumberShort(num) {
        if (Math.abs(num) >= 1.0e9) return (Math.abs(num) / 1.0e9).toFixed(1) + 'G';
        if (Math.abs(num) >= 1.0e6) return (Math.abs(num) / 1.0e6).toFixed(1) + 'M';
        if (Math.abs(num) >= 1.0e3) return (Math.abs(num) / 1.0e3).toFixed(1) + 'K';
        return Math.abs(num).toLocaleString();
    }

    var WpChartRaceApp = function (element, data) {
        this.$container = $(element);
        this.rawData = data;

        this.chartTitle = this.$container.data('title') || '';

        var settingsRaw = this.$container.data('settings');
        this.savedSettings = typeof settingsRaw === 'object' ? settingsRaw : {};

        this.loadSettings();

        this.frames = [];
        this.isPlaying = false;
        this.animationId = null;
        this.lastTime = 0;
        this.currentFrameIndex = 0;
        this.progress = 0;
        this.barPositions = {};

        // ★追加: データのユニークなラベル総数を保持
        this.totalUniqueLabels = 0;

        this.init();
    };

    WpChartRaceApp.prototype.loadSettings = function () {
        var opts = $.extend({}, CONFIG.DEFAULTS);

        if (this.savedSettings) {
            if (this.savedSettings.max_bars) opts.maxBars = parseInt(this.savedSettings.max_bars);
            if (this.savedSettings.bar_height) opts.barHeight = parseInt(this.savedSettings.bar_height);
            if (this.savedSettings.speed) opts.speed = parseFloat(this.savedSettings.speed);
            if (this.savedSettings.date_format) opts.dateFormat = this.savedSettings.date_format;
            if (this.savedSettings.bar_spacing !== undefined) opts.barSpacing = parseFloat(this.savedSettings.bar_spacing);
            if (this.savedSettings.font_size) opts.fontSize = parseFloat(this.savedSettings.font_size);
            if (this.savedSettings.show_title !== undefined) opts.showTitle = this.savedSettings.show_title;
            if (this.savedSettings.margin_px !== undefined) opts.marginPx = parseInt(this.savedSettings.margin_px);
            if (this.savedSettings.label_mode) opts.labelMode = this.savedSettings.label_mode;
            if (this.savedSettings.color_palette) opts.colorPalette = this.savedSettings.color_palette;
        }

        if (this.$container.attr('id') === 'wcr-chart-preview') {
            if (sessionStorage.getItem('wcr_max_bars')) opts.maxBars = parseInt(sessionStorage.getItem('wcr_max_bars'));
            if (sessionStorage.getItem('wcr_bar_height')) opts.barHeight = parseInt(sessionStorage.getItem('wcr_bar_height'));
            if (sessionStorage.getItem('wcr_speed')) opts.speed = parseFloat(sessionStorage.getItem('wcr_speed'));
            if (sessionStorage.getItem('wcr_date_format')) opts.dateFormat = sessionStorage.getItem('wcr_date_format');
            if (sessionStorage.getItem('wcr_bar_spacing')) opts.barSpacing = parseFloat(sessionStorage.getItem('wcr_bar_spacing'));
            if (sessionStorage.getItem('wcr_font_size')) opts.fontSize = parseFloat(sessionStorage.getItem('wcr_font_size'));

            if (sessionStorage.getItem('wcr_show_title') !== null) opts.showTitle = sessionStorage.getItem('wcr_show_title');
            if (sessionStorage.getItem('wcr_margin_px')) opts.marginPx = parseInt(sessionStorage.getItem('wcr_margin_px'));
            if (sessionStorage.getItem('wcr_label_mode')) opts.labelMode = sessionStorage.getItem('wcr_label_mode');
            if (sessionStorage.getItem('wcr_color_palette')) opts.colorPalette = sessionStorage.getItem('wcr_color_palette');
        }

        this.options = opts;
        this.duration = CONFIG.DEFAULT_DURATION / this.options.speed;
    };

    WpChartRaceApp.prototype.init = function () {
        this.processData();
        if (this.frames.length < 2) {
            var msg = typeof wcr_front_i18n !== 'undefined' ? wcr_front_i18n.data_insufficient : 'Data insufficient.';
            this.$container.html('<p>' + msg + '</p>');
            return;
        }
        this.buildUI();
        this.render(0, 0, true);
    };

    WpChartRaceApp.prototype.processData = function () {
        var self = this;
        var timeMap = {};
        var labelMap = {}; // ラベル集計用
        $.each(this.rawData, function (i, item) {
            timeMap[item.time] = true;
            labelMap[item.label] = true;
        });

        // ★ユニークラベル数を計算
        this.totalUniqueLabels = Object.keys(labelMap).length;

        var times = Object.keys(timeMap).sort();
        this.frames = $.map(times, function (time) {
            var values = {};
            $.each(self.rawData, function (i, item) {
                if (item.time === time) values[item.label] = parseFloat(item.value);
            });
            return { time: time, formattedTime: self.formatTime(time), values: values };
        });
    };

    WpChartRaceApp.prototype.formatTime = function (timeStr) {
        var format = this.options.dateFormat;
        var parts = timeStr.replace(/\//g, '-').split('-');
        var y = parts[0],
            m = parts[1] || '',
            d = parts[2] || '';
        if (format === 'YYYY年MM月' && m) return y + '年' + m + '月';
        if (format === 'MM/DD/YYYY') return (m ? m + '/' : '') + (d ? d + '/' : '') + y;
        return timeStr;
    };

    WpChartRaceApp.prototype.buildUI = function () {
        this.$container.empty();

        this.$container.parent().css({
            'padding-left': this.options.marginPx + 'px',
            'padding-right': this.options.marginPx + 'px'
        });

        if (this.options.showTitle == '1') {
            $('<h2 class="wcr-chart-title"></h2>').text(this.chartTitle).appendTo(this.$container);
        }

        var txtPlay = typeof wcr_front_i18n !== 'undefined' ? wcr_front_i18n.play : 'Play';
        var txtReset = typeof wcr_front_i18n !== 'undefined' ? wcr_front_i18n.reset : 'Reset';

        this.$timeLabel = $('<div class="wcr-time-label"></div>').appendTo(this.$container);
        this.$scaleWrap = $('<div class="wcr-scale-wrap"></div>').appendTo(this.$container);
        this.$barsWrap = $('<div class="wcr-bars-wrap"></div>').appendTo(this.$container);

        var $controls = $('<div class="wcr-controls"></div>').appendTo(this.$container);
        var self = this;

        this.$playBtn = $('<button type="button" class="wcr-btn">▶ ' + txtPlay + '</button>')
            .on('click', function () {
                self.togglePlay();
            })
            .appendTo($controls);

        $('<button type="button" class="wcr-btn">↺ ' + txtReset + '</button>')
            .on('click', function () {
                self.reset();
            })
            .appendTo($controls);

        this.rowElements = {};
    };

    WpChartRaceApp.prototype.togglePlay = function () {
        if (this.isPlaying) this.pause();
        else this.play();
    };
    WpChartRaceApp.prototype.play = function () {
        var txtPause = typeof wcr_front_i18n !== 'undefined' ? wcr_front_i18n.pause : 'Pause';
        if (this.currentFrameIndex >= this.frames.length - 1) this.reset();
        this.isPlaying = true;
        this.$playBtn.text('⏸ ' + txtPause);
        this.lastTime = performance.now();
        var self = this;
        var loop = function (now) {
            if (!self.isPlaying) return;
            var dt = now - self.lastTime;
            self.lastTime = now;
            self.progress += dt / self.duration;
            if (self.progress >= 1) {
                self.progress = 0;
                self.currentFrameIndex++;
                if (self.currentFrameIndex >= self.frames.length - 1) {
                    self.currentFrameIndex = self.frames.length - 1;
                    self.render(self.currentFrameIndex, 0, false);
                    self.pause();
                    return;
                }
            }
            self.render(self.currentFrameIndex, self.progress, false);
            self.animationId = requestAnimationFrame(loop);
        };
        this.animationId = requestAnimationFrame(loop);
    };
    WpChartRaceApp.prototype.pause = function () {
        var txtPlay = typeof wcr_front_i18n !== 'undefined' ? wcr_front_i18n.play : 'Play';
        this.isPlaying = false;
        this.$playBtn.text('▶ ' + txtPlay);
        if (this.animationId) cancelAnimationFrame(this.animationId);
    };
    WpChartRaceApp.prototype.reset = function () {
        this.pause();
        this.currentFrameIndex = 0;
        this.progress = 0;
        this.barPositions = {};
        this.render(0, 0, true);
    };

    WpChartRaceApp.prototype.render = function (idx, progress, isReset) {
        var currentFrame = this.frames[idx];
        var nextFrame = this.frames[idx + 1];
        this.$timeLabel.text(progress < 0.5 ? currentFrame.formattedTime : nextFrame ? nextFrame.formattedTime : currentFrame.formattedTime);

        var interpolated = [];
        var labelSet = {};
        $.each(currentFrame.values, function (l, v) {
            labelSet[l] = true;
        });
        if (nextFrame)
            $.each(nextFrame.values, function (l, v) {
                labelSet[l] = true;
            });

        $.each(labelSet, function (label, _) {
            var start = currentFrame.values[label] || 0;
            var end = nextFrame && nextFrame.values[label] !== undefined ? nextFrame.values[label] : start;
            var val = start + (end - start) * progress;
            interpolated.push({ label: label, value: val });
        });

        interpolated.sort(function (a, b) {
            return b.value - a.value;
        });

        // ★修正: 「設定値(maxBars)」と「実際のラベル数(totalUniqueLabels)」の小さい方を採用して高さを詰める
        var effectiveMaxBars = Math.min(this.options.maxBars, this.totalUniqueLabels);

        var displayItems = interpolated.slice(0, effectiveMaxBars);
        var maxValue = displayItems.length > 0 ? displayItems[0].value : 1;

        this.updateScale(maxValue);

        var paletteKey = this.options.colorPalette || 'a';
        var palette = PALETTES[paletteKey] || PALETTES['a'];

        var self = this;
        var rowHeight = this.options.barHeight + this.options.barSpacing;

        // ★高さ計算にも effectiveMaxBars を使用
        this.$barsWrap.css('height', effectiveMaxBars * rowHeight + 'px');

        var activeLabels = {};

        var mode = this.options.labelMode;
        var hasOutsideLabel = mode === 'outside_left' || mode === 'both';
        var hasInsideLabel = mode !== 'outside_left';

        var barJustify = 'flex-end';
        var barPadding = '0 10px 0 0';
        if (mode === 'inside_left') {
            barJustify = 'flex-start';
            barPadding = '0 0 0 10px';
        }

        $.each(displayItems, function (rank, item) {
            activeLabels[item.label] = true;
            var $row = self.rowElements[item.label];
            var targetTop = rank * rowHeight;

            var cIdx = 0;
            for (var i = 0; i < item.label.length; i++) cIdx += item.label.charCodeAt(i);
            var color = palette[cIdx % palette.length];

            if (!$row) {
                $row = $('<div class="wcr-bar-row"></div>');
                var $label = $('<div class="wcr-row-label"></div>').text(item.label);
                var $barArea = $('<div class="wcr-row-bar-area"></div>');
                var $bar = $('<div class="wcr-bar"></div>');
                var $barInternalLabel = $('<span class="wcr-bar-label-internal"></span>').text(item.label);
                var $value = $('<div class="wcr-row-value"></div>');

                $bar.css('background-color', color);
                $bar.append($barInternalLabel);
                $barArea.append($bar).append($value);
                $row.append($label).append($barArea);
                self.$barsWrap.append($row);
                self.rowElements[item.label] = $row;

                self.barPositions[item.label] = isReset ? targetTop : self.$barsWrap.height() + 50;
                $row.css('transform', 'translateY(' + self.barPositions[item.label] + 'px)');
            }

            var $barEl = $row.find('.wcr-bar');
            $barEl.css('background-color', color);

            var currentTop = self.barPositions[item.label];
            if (currentTop === undefined) currentTop = targetTop;
            if (isReset) {
                currentTop = targetTop;
            } else {
                currentTop += (targetTop - currentTop) * CONFIG.POSITION_EASING;
                if (Math.abs(targetTop - currentTop) < 0.5) currentTop = targetTop;
            }
            self.barPositions[item.label] = currentTop;

            var $labelEl = $row.find('.wcr-row-label');
            var $barAreaEl = $row.find('.wcr-row-bar-area');
            var $intLabelEl = $row.find('.wcr-bar-label-internal');

            if (hasOutsideLabel) {
                $labelEl.show().css('width', '20%');
                $barAreaEl.css('width', '80%');
            } else {
                $labelEl.hide();
                $barAreaEl.css('width', '100%');
            }

            if (hasInsideLabel) {
                $intLabelEl.show();
                $barEl.css({ 'justify-content': barJustify, padding: barPadding });
            } else {
                $intLabelEl.hide();
            }

            $row.css({
                transform: 'translateY(' + currentTop + 'px)',
                height: self.options.barHeight + 'px',
                opacity: 1,
                'font-size': self.options.fontSize + 'em'
            });

            var wPct = maxValue > 0 ? (item.value / maxValue) * 100 : 0;
            $barEl.css('width', wPct + '%');
            $row.find('.wcr-row-value').text(Math.round(item.value).toLocaleString());
        });

        $.each(this.rowElements, function (label, $el) {
            if (!activeLabels[label]) {
                // ランク外の位置も effectiveMaxBars 基準で計算して隠す
                var targetOff = effectiveMaxBars * rowHeight + 20;
                var current = self.barPositions[label] || targetOff;
                if (!isReset) current += (targetOff - current) * CONFIG.POSITION_EASING;
                else current = targetOff;
                self.barPositions[label] = current;
                $el.css({ transform: 'translateY(' + current + 'px)', opacity: 0 });
            }
        });
    };

    WpChartRaceApp.prototype.updateScale = function (maxValue) {
        this.$scaleWrap.empty();
        var ticks = 5;
        var rawStep = maxValue / ticks;
        var mag = Math.pow(10, Math.floor(Math.log10(rawStep || 1)));
        var normalized = rawStep / mag;
        var step;
        if (normalized < 1.5) step = 1 * mag;
        else if (normalized < 3.5) step = 2 * mag;
        else if (normalized < 7.5) step = 5 * mag;
        else step = 10 * mag;

        var mode = this.options.labelMode;
        var hasOutsideLabel = mode === 'outside_left' || mode === 'both';
        var labelWidthPct = hasOutsideLabel ? 20 : 0;
        var barAreaWidthPct = 100 - labelWidthPct;

        for (var v = 0; v <= maxValue * 1.1; v += step) {
            if (v > maxValue * 1.2) break;
            var posPct = (v / maxValue) * 100;
            if (posPct > 100) continue;
            var totalLeft = labelWidthPct + barAreaWidthPct * (posPct / 100);
            var $tick = $('<div class="wcr-tick"></div>').css('left', totalLeft + '%');
            var $text = $('<div class="wcr-tick-text"></div>').text(formatNumberShort(v));
            $tick.append($text);
            this.$scaleWrap.append($tick);
        }
    };

    WpChartRaceApp.prototype.updateConfig = function () {
        this.pause();
        this.loadSettings();
        this.reset();
    };

    $(document).ready(function () {
        $('.wcr-chart').each(function () {
            var raw = $(this).attr('data-chart');
            if (raw) {
                var data = JSON.parse(raw);
                var app = new WpChartRaceApp(this, data);
                $(this).on('wcr:config-update', function () {
                    app.updateConfig();
                });
            }
        });
    });
})(jQuery);
