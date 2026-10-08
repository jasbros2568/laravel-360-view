/*!
 * laravel-360-view — image-sequence 360° viewer. No dependencies.
 *
 * Markup:  <div data-view360='{"frames":["1.jpg","2.jpg",…]}'><img class="view360__frame"></div>
 * API:     View360.mount(root?)  initialise every viewer inside root (default: document)
 *          View360.get(element)  -> { goTo(i), next(), prev(), play(), stop(), destroy(), index, total }
 * Events:  "view360:ready" and "view360:change" (detail: { index, total }) bubble from the element.
 */
(function () {
    'use strict';

    if (window.View360) {
        return;
    }

    var instances = new WeakMap();
    var reducedMotion = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : { matches: false };

    function Viewer(root) {
        var options = {};

        try {
            options = JSON.parse(root.getAttribute('data-view360') || '{}');
        } catch (error) {
            options = {};
        }

        this.root = root;
        this.frames = Array.isArray(options.frames) ? options.frames : [];
        this.total = this.frames.length;
        this.sensitivity = Math.max(1, Number(options.sensitivity) || 8);
        this.speed = Math.max(1, Number(options.speed) || 12);
        this.direction = options.reverse ? -1 : 1;
        this.loop = options.loop !== false;
        this.autoplay = !!options.autoplay;
        this.index = Math.min(Math.max(0, Number(options.start) || 0), Math.max(0, this.total - 1));
        this.image = root.querySelector('.view360__frame');
        this.bar = root.querySelector('.view360__progress');
        this.timer = null;
        this.drag = null;
        this.loaded = 0;
        this.listeners = [];

        if (!this.image || this.total === 0) {
            return;
        }

        this.bind();
        this.whenVisible(this.preload.bind(this));
    }

    Viewer.prototype.on = function (target, type, handler, options) {
        target.addEventListener(type, handler, options);
        this.listeners.push([target, type, handler, options]);
    };

    Viewer.prototype.bind = function () {
        var self = this;

        this.on(this.root, 'pointerdown', function (event) {
            if (event.button !== undefined && event.button !== 0) {
                return;
            }

            self.touched();
            self.drag = { x: event.clientX, index: self.index, id: event.pointerId };
            self.root.classList.add('is-dragging');

            if (self.root.setPointerCapture) {
                try { self.root.setPointerCapture(event.pointerId); } catch (error) { /* pointer already gone */ }
            }
        });

        this.on(this.root, 'pointermove', function (event) {
            if (!self.drag || event.pointerId !== self.drag.id) {
                return;
            }

            var steps = Math.round((event.clientX - self.drag.x) / self.sensitivity);
            self.goTo(self.drag.index - steps * self.direction);
        });

        var release = function (event) {
            if (self.drag && (!event || event.pointerId === self.drag.id)) {
                self.drag = null;
                self.root.classList.remove('is-dragging');
            }
        };

        this.on(this.root, 'pointerup', release);
        this.on(this.root, 'pointercancel', release);
        this.on(this.root, 'lostpointercapture', release);

        this.on(this.root, 'keydown', function (event) {
            var handled = true;

            switch (event.key) {
                case 'ArrowLeft': case 'ArrowDown': self.prev(); break;
                case 'ArrowRight': case 'ArrowUp': self.next(); break;
                case 'Home': self.goTo(0); break;
                case 'End': self.goTo(self.total - 1); break;
                default: handled = false;
            }

            if (handled) {
                self.touched();
                event.preventDefault();
            }
        });

        // Stop the browser's native image drag ghost.
        this.on(this.root, 'dragstart', function (event) { event.preventDefault(); });

        this.on(document, 'visibilitychange', function () {
            if (document.hidden) {
                self.pause();
            } else if (self.autoplay && !self.wasTouched) {
                self.play();
            }
        });
    };

    /** Run callback once the viewer is (nearly) on screen, so off-screen spins do not cost bandwidth. */
    Viewer.prototype.whenVisible = function (callback) {
        var self = this;

        if (!('IntersectionObserver' in window)) {
            callback();

            return;
        }

        this.observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                self.visible = entry.isIntersecting;

                if (entry.isIntersecting && callback) {
                    callback();
                    callback = null;
                }

                if (!entry.isIntersecting) {
                    self.pause();
                } else if (self.ready && self.autoplay && !self.wasTouched) {
                    self.play();
                }
            });
        }, { rootMargin: '200px' });

        this.observer.observe(this.root);
    };

    Viewer.prototype.preload = function () {
        var self = this;

        if (this.bar) {
            this.bar.hidden = false;
        }

        var done = function () {
            self.loaded += 1;

            if (self.bar && self.bar.firstElementChild) {
                self.bar.firstElementChild.style.width = Math.round(self.loaded / self.total * 100) + '%';
            }

            if (self.loaded >= self.total) {
                self.ready = true;
                self.root.classList.add('is-ready');
                self.emit('view360:ready');

                if (self.autoplay && !self.wasTouched && self.visible !== false) {
                    self.play();
                }
            }
        };

        // Keep references so the browser holds the decoded frames in memory while the viewer exists.
        this.cache = this.frames.map(function (url) {
            var image = new Image();
            image.onload = image.onerror = done; // a broken frame must not block the rest
            image.src = url;

            return image;
        });
    };

    Viewer.prototype.goTo = function (index) {
        if (this.total === 0) {
            return;
        }

        index = this.loop ? ((index % this.total) + this.total) % this.total : Math.min(Math.max(0, index), this.total - 1);

        if (index === this.index) {
            return;
        }

        this.index = index;
        this.image.src = this.frames[index];
        this.root.setAttribute('aria-valuenow', String(index + 1));
        this.root.setAttribute('aria-valuetext', 'View ' + (index + 1) + ' of ' + this.total);
        this.emit('view360:change');
    };

    Viewer.prototype.next = function () { this.goTo(this.index + 1); };
    Viewer.prototype.prev = function () { this.goTo(this.index - 1); };

    Viewer.prototype.play = function () {
        var self = this;

        if (this.timer || reducedMotion.matches || this.total < 2) {
            return;
        }

        this.timer = window.setInterval(function () {
            if (!self.loop && self.index === (self.direction > 0 ? self.total - 1 : 0)) {
                self.pause();

                return;
            }

            self.goTo(self.index + self.direction);
        }, 1000 / this.speed);
    };

    Viewer.prototype.pause = function () {
        if (this.timer) {
            window.clearInterval(this.timer);
            this.timer = null;
        }
    };

    /** Stop autoplay for good (the visitor, or your code, has taken over). */
    Viewer.prototype.stop = function () {
        this.wasTouched = true;
        this.pause();
    };

    Viewer.prototype.touched = function () {
        this.stop();
        this.root.classList.add('is-touched');
    };

    Viewer.prototype.emit = function (name) {
        this.root.dispatchEvent(new CustomEvent(name, { bubbles: true, detail: { index: this.index, total: this.total } }));
    };

    Viewer.prototype.destroy = function () {
        this.pause();

        if (this.observer) {
            this.observer.disconnect();
        }

        this.listeners.forEach(function (listener) {
            listener[0].removeEventListener(listener[1], listener[2], listener[3]);
        });

        this.listeners = [];
        this.cache = null;
        instances.delete(this.root);
    };

    function mount(root) {
        var scope = root || document;
        var elements = scope.querySelectorAll ? Array.prototype.slice.call(scope.querySelectorAll('[data-view360]')) : [];

        if (scope.matches && scope.matches('[data-view360]')) {
            elements.unshift(scope);
        }

        elements.forEach(function (element) {
            if (!instances.has(element)) {
                instances.set(element, new Viewer(element));
            }
        });
    }

    window.View360 = {
        mount: mount,
        get: function (element) { return instances.get(element) || null; },
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { mount(); });
    } else {
        mount();
    }

    // Pages swapped in without a full reload (Livewire navigate, Turbo).
    document.addEventListener('livewire:navigated', function () { mount(); });
    document.addEventListener('turbo:load', function () { mount(); });
})();
