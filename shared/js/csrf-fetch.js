/**
 * Attaches the page's CSRF token (from <meta name="csrf-token">,
 * rendered by shared/header.php) to every same-origin, state-changing
 * fetch()/XMLHttpRequest call automatically — as an X-CSRF-Token
 * header — so individual AJAX call sites across the app don't each
 * need to remember to send it by hand.
 *
 * Server side, shared/csrf.php's require_csrf() accepts the token
 * either as this header or as a 'csrf_token'/'csrf' POST field, so
 * this covers fetch() calls that post JSON as well as ones that post
 * form-encoded data without a hidden field.
 */
(function () {
    var tokenMeta = document.querySelector('meta[name="csrf-token"]');
    var token = tokenMeta ? tokenMeta.getAttribute('content') : null;
    if (!token) return;

    var STATE_CHANGING = ['POST', 'PUT', 'PATCH', 'DELETE'];

    function isSameOrigin(url) {
        try {
            return new URL(url, window.location.href).origin === window.location.origin;
        } catch (e) {
            return true; // relative URL
        }
    }

    var originalFetch = window.fetch;
    if (originalFetch) {
        window.fetch = function (input, init) {
            init = init || {};
            var url = typeof input === 'string' ? input : (input && input.url) || '';
            var method = (init.method || (input && input.method) || 'GET').toUpperCase();

            if (STATE_CHANGING.indexOf(method) !== -1 && isSameOrigin(url)) {
                init.headers = init.headers || {};
                if (init.headers instanceof Headers) {
                    if (!init.headers.has('X-CSRF-Token')) {
                        init.headers.set('X-CSRF-Token', token);
                    }
                } else if (!('X-CSRF-Token' in init.headers)) {
                    init.headers['X-CSRF-Token'] = token;
                }
            }
            return originalFetch.call(this, input, init);
        };
    }

    var originalOpen = XMLHttpRequest.prototype.open;
    var originalSend = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.open = function (method, url) {
        this.__armisMethod = (method || 'GET').toUpperCase();
        this.__armisUrl = url;
        return originalOpen.apply(this, arguments);
    };
    XMLHttpRequest.prototype.send = function () {
        if (STATE_CHANGING.indexOf(this.__armisMethod) !== -1 && isSameOrigin(this.__armisUrl || '')) {
            try {
                this.setRequestHeader('X-CSRF-Token', token);
            } catch (e) {
                // Header already set, or request already sent — ignore.
            }
        }
        return originalSend.apply(this, arguments);
    };
})();
