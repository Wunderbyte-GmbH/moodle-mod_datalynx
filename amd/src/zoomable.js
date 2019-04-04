// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * @package mod_datalynx
 * @copyright 2017 Thomas Niedermaier (thomas.niedermaier@gmail.com)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Create new dayslots as duplicates of the seven original day slots
 */


define(
    ['jquery'], function($) {

        /**
         * @constructor
         * @alias module:mod_datalynx/zoomable
         */
        var Zoomable = function(){};

        var instance = new Zoomable();

        instance.init = function() {

        $.tools = $.tools || {
            version: '@VERSION'
        };
        $.tools.overlay = {
            addEffect: function(name, loadFn, closeFn) {
                effects[name] = [loadFn, closeFn];
            },
            conf: {
                close: null,
                closeOnClick: true,
                closeOnEsc: true,
                closeSpeed: 'fast',
                effect: 'default',
                fixed: !/msie/.test(navigator.userAgent.toLowerCase()) || navigator.appVersion > 6,
                left: 'center',
                load: false,
                mask: null,
                oneInstance: true,
                speed: 'normal',
                target: null,
                top: '10%'
            }
        };
        var instances = [],
            effects = {};
        $.tools.overlay.addEffect('default', function(pos, onLoad) {
            var conf = this.getConf(),
                w = $(window);
            if (!conf.fixed) {
                pos.top += w.scrollTop();
                pos.left += w.scrollLeft();
            }
            pos.position = conf.fixed ? 'fixed' : 'absolute';
            this.getOverlay().css(pos).fadeIn(conf.speed, onLoad);
        }, function(onClose) {
            this.getOverlay().fadeOut(this.getConf().closeSpeed, onClose);
        });

        function Overlay(trigger, conf) {
            var self = this,
                fire = trigger.add(self),
                w = $(window),
                closers, overlay, opened, maskConf = $.tools.expose && (conf.mask || conf.expose),
                uid = Math.random().toString().slice(10);
            if (maskConf) {
                if (typeof maskConf == 'string') {
                    maskConf = {
                        color: maskConf
                    };
                }
                maskConf.closeOnClick = maskConf.closeOnEsc = false;
            }
            var jq = conf.target || trigger.attr("rel");
            overlay = jq ? $(jq) : null || trigger;
            if (!overlay.length) {
                throw "Could not find Overlay: " + jq;
            }
            if (trigger && trigger.index(overlay) == -1) {
                trigger.click(function(e) {
                    self.load(e);
                    return e.preventDefault();
                });
            }
            $.extend(self, {
                load: function(e) {
                    if (self.isOpened()) {
                        return self;
                    }
                    var eff = effects[conf.effect];
                    if (!eff) {
                        throw "Overlay: cannot find effect : \"" + conf.effect + "\"";
                    }
                    if (conf.oneInstance) {
                        $.each(instances, function() {
                            this.close(e);
                        });
                    }
                    e = e || $.Event();
                    e.type = "onBeforeLoad";
                    fire.trigger(e);
                    if (e.isDefaultPrevented()) {
                        return self;
                    }
                    opened = true;
                    if (maskConf) {
                        $(overlay).expose(maskConf);
                    }
                    var top = conf.top,
                        left = conf.left,
                        oWidth = overlay.outerWidth(true),
                        oHeight = overlay.outerHeight(true);
                    if (typeof top == 'string') {
                        top = top == 'center' ? Math.max((w.height() - oHeight) / 2, 0) : parseInt(top, 10) / 100 * w.height();
                    }
                    if (left == 'center') {
                        left = Math.max((w.width() - oWidth) / 2, 0);
                    }
                    eff[0].call(self, {
                        top: top,
                        left: left
                    }, function() {
                        if (opened) {
                            e.type = "onLoad";
                            fire.trigger(e);
                        }
                    });
                    if (maskConf && conf.closeOnClick) {
                        $.mask.getMask().one("click", self.close);
                    }
                    if (conf.closeOnClick) {
                        $(document).on("click." + uid, function(e) {
                            if (!$(e.target).parents(overlay).length) {
                                self.close(e);
                            }
                        });
                    }
                    if (conf.closeOnEsc) {
                        $(document).on("keydown." + uid, function(e) {
                            if (e.keyCode == 27) {
                                self.close(e);
                            }
                        });
                    }
                    return self;
                },
                close: function(e) {
                    if (!self.isOpened()) {
                        return self;
                    }
                    e = e || $.Event();
                    e.type = "onBeforeClose";
                    fire.trigger(e);
                    if (e.isDefaultPrevented()) {
                        return;
                    }
                    opened = false;
                    effects[conf.effect][1].call(self, function() {
                        e.type = "onClose";
                        fire.trigger(e);
                    });
                    $(document).off("click." + uid + " keydown." + uid);
                    if (maskConf) {
                        $.mask.close();
                    }
                    return self;
                },
                getOverlay: function() {
                    return overlay;
                },
                getTrigger: function() {
                    return trigger;
                },
                getClosers: function() {
                    return closers;
                },
                isOpened: function() {
                    return opened;
                },
                getConf: function() {
                    return conf;
                }
            });
            $.each("onBeforeLoad,onStart,onLoad,onBeforeClose,onClose".split(","), function(i, name) {
                if ($.isFunction(conf[name])) {
                    $(self).on(name, conf[name]);
                }
                self[name] = function(fn) {
                    if (fn) {
                        $(self).on(name, fn);
                    }
                    return self;
                };
            });
            closers = overlay.find(conf.close || ".close");
            if (!closers.length && !conf.close) {
                closers = $('<a class="close"></a>');
                overlay.prepend(closers);
            }
            closers.click(function(e) {
                self.close(e);
            });
            if (conf.load) {
                self.load();
            }
        }
        $.fn.overlay = function(conf) {
            var el = this.data("overlay");
            if (el) {
                return el;
            }
            if ($.isFunction(conf)) {
                conf = {
                    onBeforeLoad: conf
                };
            }
            conf = $.extend(true, {}, $.tools.overlay.conf, conf);
            this.each(function() {
                el = new Overlay($(this), conf);
                instances.push(el);
                $(this).data("overlay", el);
            });
            return conf.api ? el : this;
        };

        var divname = "",
            index = 0;
        $(function() {
            $('img.zoomable').attr('title', "Zum Vergrößern klicken");
            $(document).on('click', 'img.zoomable', function() {
                var $this = $(this);
                if ($this.attr('rel')) {
                    $this.removeData("overlay");
                    $($this.attr('rel')).remove();
                    $this.removeAttr("rel");
                }
                writenewoverlay($this);
            });
            $(window).resize(function() {
                $("[rel]").each(function() {
                    var $this = $(this),
                        api = $this.data("overlay");
                    if (api != undefined) {
                        if (api.isOpened()) {
                            $this.removeData("overlay");
                            $($this.attr('rel')).remove();
                            $this.removeAttr("rel");
                            writenewoverlay($this);
                        }
                    }
                });
            });

            function writenewoverlay(trigger) {
                divname = 'zoomable' + index;
                index = index + 1;
                $("<img/>").attr("src", trigger.attr("src")).on('load', function() {
                    var height = this.height,
                        width = this.width;
                    if (height > $(window).height() - 60) {
                        var heightbefore = height;
                        height = $(window).height() - 60;
                        width = Math.round(height * width / heightbefore);
                    }
                    if (width > $(window).width() - 60) {
                        var widthbefore = width;
                        width = $(window).width() - 60;
                        height = Math.round(width * height / widthbefore);
                    }
                    $('body').append('<div id="' + divname +
                        '" class="m3e-overlay"><div class="close m3e-closebutton"></div><img src="' +
                        trigger.attr('src') + '" width="' + width + '" height="' + height + '" class="close" /></div>');
                    trigger.attr('rel', '#' + divname);
                    trigger.overlay({
                        load: true,
                        top: "center"
                    });
                });
            }
        });
    };

    return instance;

    }
);

