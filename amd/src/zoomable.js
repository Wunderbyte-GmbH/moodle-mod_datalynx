// This file is part of mod_datalynx for Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Provides zoomable functionality for datalynx elements.
 *
 * @module      mod_datalynx/zoomable
 * @copyright   2025 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], () => {
    class Zoomable {
        constructor() {
            this.instances = [];
            this.effects = {};
        }

        init() {
            if (!window.tools) {
                window.tools = {version: '@VERSION'};
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

            window.tools.overlay.addEffect('default', function(pos, onLoad) {
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
                setTimeout(onLoad, conf.speed === 'fast' ? 200 : 400);
            }, function(onClose) {
                const overlay = this.getOverlay();
                overlay.style.display = 'none';
                setTimeout(onClose, this.getConf().closeSpeed === 'fast' ? 200 : 400);
            });

            Zoomable.registerGlobalHandlers();
            Zoomable.decorateZoomableImages(document);
        }

        Overlay(trigger, conf) {
            const self = this;
            const fireEvent = (type, detail) => {
                trigger.dispatchEvent(new CustomEvent(type, {detail}));
            };

            const overlay = conf.target || document.querySelector(trigger.getAttribute('rel')) || trigger;
            let opened = false;

            if (!overlay) {
                throw new Error(`Could not find Overlay: ${conf.target || trigger.getAttribute('rel')}`);
            }

            Object.assign(self, {
                load(e = new Event('load')) {
                    if (self.isOpened()) {
                        return self;
                    }
                    const eff = this.effects[conf.effect];
                    if (!eff) {
                        throw new Error(`Overlay: cannot find effect: "${conf.effect}"`);
                    }

                    fireEvent('onBeforeLoad', e);
                    if (e.defaultPrevented) {
                        return self;
                    }

                    opened = true;

                    const windowHeight = window.innerHeight;
                    const windowWidth = window.innerWidth;
                    const overlayRect = overlay.getBoundingClientRect();
                    const top = conf.top === 'center' ?
                        Math.max((windowHeight - overlayRect.height) / 2, 0) : parseInt(conf.top, 10);
                    const left = conf.left === 'center' ?
                        Math.max((windowWidth - overlayRect.width) / 2, 0) : parseInt(conf.left, 10);

                    eff[0].call(self, {top, left}, () => {
                        if (opened) {
                            fireEvent('onLoad', e);
                        }
                    });

                    return self;
                },

                close(e = new Event('close')) {
                    if (!self.isOpened()) {
                        return self;
                    }

                    fireEvent('onBeforeClose', e);
                    if (e.defaultPrevented) {
                        return self;
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

            if (conf.load) {
                self.load();
            }

            return self;
        }

        static decorateZoomableImages(root) {
            if (!('querySelectorAll' in root)) {
                return;
            }

            root.querySelectorAll('img.zoomable').forEach((img) => {
                img.setAttribute('title', 'Zum Vergrößern klicken');
            });
        }

        static closeOverlayElement(overlay) {
            const selector = `#${overlay.id}`;
            document.querySelectorAll(`img.zoomable[rel="${selector}"]`).forEach((trigger) => {
                trigger.removeAttribute('rel');
            });
            overlay.remove();
        }

        static closeAllOverlays() {
            document.querySelectorAll('.m3e-overlay').forEach((overlay) => {
                Zoomable.closeOverlayElement(overlay);
            });
        }

        static writeNewOverlay(trigger) {
            const divname = `zoomable${Zoomable.index}`;
            Zoomable.index += 1;

            const img = document.createElement('img');
            img.src = trigger.src;

            img.onload = function() {
                let {width, height} = img;
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
                new Zoomable().Overlay(trigger, {load: true, top: 'center'});
            };
        }

        static handleDocumentClick(event) {
            if (!(event.target instanceof Element)) {
                return;
            }

            const trigger = event.target.closest('img.zoomable');
            if (trigger) {
                event.preventDefault();
                Zoomable.closeAllOverlays();
                Zoomable.writeNewOverlay(trigger);
                return;
            }

            const closeTarget = event.target.closest('.m3e-overlay .close');
            if (closeTarget) {
                const overlay = closeTarget.closest('.m3e-overlay');
                if (overlay) {
                    Zoomable.closeOverlayElement(overlay);
                }
                return;
            }

            document.querySelectorAll('.m3e-overlay').forEach((overlay) => {
                if (!overlay.contains(event.target)) {
                    Zoomable.closeOverlayElement(overlay);
                }
            });
        }

        static handleDocumentKeydown(event) {
            if (event.key === 'Escape') {
                Zoomable.closeAllOverlays();
            }
        }

        static handleWindowResize() {
            document.querySelectorAll('img.zoomable[rel]').forEach((trigger) => {
                const overlay = document.querySelector(trigger.getAttribute('rel'));
                if (!overlay) {
                    return;
                }

                Zoomable.closeOverlayElement(overlay);
                Zoomable.writeNewOverlay(trigger);
            });
        }

        static registerGlobalHandlers() {
            if (Zoomable.globalHandlersRegistered) {
                return;
            }

            document.addEventListener('click', Zoomable.handleDocumentClick);
            document.addEventListener('keydown', Zoomable.handleDocumentKeydown);
            document.addEventListener('mod_datalynx:viewContentUpdated', (event) => {
                const root = event.detail?.target;
                if (root) {
                    Zoomable.decorateZoomableImages(root);
                }
            });
            window.addEventListener('resize', Zoomable.handleWindowResize);
            Zoomable.globalHandlersRegistered = true;
        }
    }

    Zoomable.index = 0;
    Zoomable.globalHandlersRegistered = false;

    return new Zoomable();
});
