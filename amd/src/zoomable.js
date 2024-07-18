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
 * @module mod_datalynx/zoomable
 * @copyright 2017 Thomas Niedermaier (thomas.niedermaier@gmail.com)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Create new dayslots as duplicates of the seven original day slots
 */


define([], () => {
    /**
     * @constructor
     * @alias module:mod_datalynx/zoomable
     */
    class Zoomable {
        constructor() {
            this.instances = [];
            this.effects = {};
        }

        init() {
            if (!window.tools) {
                window.tools = { version: '@VERSION' };
            }

            window.tools.overlay = {
                addEffect: (name, loadFn, closeFn) => {
                    this.effects[name] = [loadFn, closeFn];
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
                    top: '10%',
                },
            };

            window.tools.overlay.addEffect('default', function (pos, onLoad) {
                const conf = this.getConf();
                const scrollTop = window.scrollY || document.documentElement.scrollTop;
                const scrollLeft = window.scrollX || document.documentElement.scrollLeft;

                if (!conf.fixed) {
                    pos.top += scrollTop;
                    pos.left += scrollLeft;
                }
                pos.position = conf.fixed ? 'fixed' : 'absolute';
                this.getOverlay().style.position = pos.position;
                this.getOverlay().style.top = `${pos.top}px`;
                this.getOverlay().style.left = `${pos.left}px`;
                this.getOverlay().style.display = 'block';
                setTimeout(onLoad, conf.speed === 'fast' ? 200 : 400); // Mocking fade-in timing
            }, function (onClose) {
                const overlay = this.getOverlay();
                overlay.style.display = 'none';
                setTimeout(onClose, this.getConf().closeSpeed === 'fast' ? 200 : 400); // Mocking fade-out timing
            });
        }

        Overlay(trigger, conf) {
            const self = this;
            const fireEvent = (type, detail) => {
                trigger.dispatchEvent(new CustomEvent(type, { detail }));
            };

            let overlay = conf.target || document.querySelector(trigger.getAttribute("rel")) || trigger;
            let opened = false;

            if (!overlay) {
                throw new Error(`Could not find Overlay: ${conf.target || trigger.getAttribute('rel')}`);
            }

            trigger.addEventListener('click', (e) => {
                e.preventDefault();
                self.load(e);
            });

            Object.assign(self, {
                load(e = new Event('load')) {
                    if (self.isOpened()) {
                        return self;
                    }
                    const eff = this.effects[conf.effect];
                    if (!eff) {
                        throw new Error(`Overlay: cannot find effect: "${conf.effect}"`);
                    }
                    if (conf.oneInstance) {
                        this.instances.forEach((instance) => {
                            instance.close(e);
                        });
                    }

                    fireEvent('onBeforeLoad', e);
                    if (e.defaultPrevented) {
                        return self;
                    }

                    opened = true;

                    const windowHeight = window.innerHeight;
                    const windowWidth = window.innerWidth;
                    const overlayRect = overlay.getBoundingClientRect();
                    let top = conf.top === 'center' ?
                        Math.max((windowHeight - overlayRect.height) / 2, 0) : parseInt(conf.top, 10);
                    let left = conf.left === 'center' ?
                        Math.max((windowWidth - overlayRect.width) / 2, 0) : parseInt(conf.left, 10);

                    eff[0].call(self, { top, left }, () => {
                        if (opened) {
                            fireEvent('onLoad', e);
                        }
                    });

                    if (conf.closeOnClick) {
                        document.addEventListener('click', (ev) => {
                            if (!overlay.contains(ev.target)) {
                                self.close(ev);
                            }
                        });
                    }

                    if (conf.closeOnEsc) {
                        document.addEventListener('keydown', (ev) => {
                            if (ev.key === 'Escape') {
                                self.close(ev);
                            }
                        });
                    }

                    return self;
                },

                close(e = new Event('close')) {
                    if (!self.isOpened()) {
                        return self;
                    }

                    fireEvent('onBeforeClose', e);
                    if (e.defaultPrevented) {
                        return;
                    }

                    opened = false;
                    this.effects[conf.effect][1].call(self, () => {
                        fireEvent('onClose', e);
                    });

                    return self;
                },

                getOverlay() {
                    return overlay;
                },

                isOpened() {
                    return opened;
                },

                getConf() {
                    return conf;
                }
            });

            const closers = overlay.querySelectorAll(conf.close || '.close');
            closers.forEach((closer) => {
                closer.addEventListener('click', (e) => {
                    self.close(e);
                });
            });

            if (conf.load) {
                self.load();
            }
        }

        static writeNewOverlay(trigger) {
            let divname = `zoomable${Zoomable.index}`;
            Zoomable.index += 1;

            const img = document.createElement('img');
            img.src = trigger.src;

            img.onload = function () {
                let { width, height } = img;
                const windowHeight = window.innerHeight - 60;
                const windowWidth = window.innerWidth - 60;

                if (height > windowHeight) {
                    width = Math.round(windowHeight * width / height);
                    height = windowHeight;
                }

                if (width > windowWidth) {
                    height = Math.round(windowWidth * height / width);
                    width = windowWidth;
                }

                const overlayDiv = document.createElement('div');
                overlayDiv.id = divname;
                overlayDiv.className = 'm3e-overlay';

                const closeButton = document.createElement('div');
                closeButton.className = 'close m3e-closebutton';
                overlayDiv.appendChild(closeButton);

                const imgElement = document.createElement('img');
                imgElement.src = trigger.src;
                imgElement.width = width;
                imgElement.height = height;
                imgElement.className = 'close';
                overlayDiv.appendChild(imgElement);

                document.body.appendChild(overlayDiv);

                trigger.setAttribute('rel', `#${divname}`);
                new Zoomable().Overlay(trigger, { load: true, top: 'center' });
            };
        }
    }

    Zoomable.index = 0;

    document.addEventListener('DOMContentLoaded', () => {
        const zoomableImages = document.querySelectorAll('img.zoomable');
        zoomableImages.forEach((img) => {
            img.setAttribute('title', 'Zum Vergrößern klicken');
            img.addEventListener('click', () => {
                if (img.getAttribute('rel')) {
                    const overlay = document.querySelector(img.getAttribute('rel'));
                    if (overlay) {
                        img.removeAttribute('rel');
                        overlay.remove();
                    }
                }
                Zoomable.writeNewOverlay(img);
            });
        });

        window.addEventListener('resize', () => {
            document.querySelectorAll('[rel]').forEach((el) => {
                const api = el.dataset.overlay;
                if (api && api.isOpened()) {
                    const overlay = document.querySelector(el.getAttribute('rel'));
                    if (overlay) {
                        el.removeAttribute('rel');
                        overlay.remove();
                        Zoomable.writeNewOverlay(el);
                    }
                }
            });
        });
    });

    return new Zoomable();
});
