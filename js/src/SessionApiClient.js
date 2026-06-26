/**
 * Modern session-API JS client.
 *
 * Vanilla ESM. No Prototype, no jQuery, no relationship to HordeCore or
 * HordeMobile. Talks to the modern PSR-15 session-API endpoints:
 *
 *   POST /api/v1/session/ping        keep-alive heartbeat
 *   GET  /api/v1/session/csrf-token  recovery path for 419 (stale CSRF)
 *
 * Every response from a route composing CsrfRotationMiddleware carries
 * an X-Csrf-Token header; this client reads it and updates its
 * in-memory store. Every response from a route composing
 * SessionLifetimeMiddleware also carries X-Next-Ping, which the client
 * uses to reschedule the keepalive timer.
 *
 * On 401 (session dead) the client emits a 'session-expired' event,
 * stops the keepalive timer, and throws. On 419 (stale CSRF) it
 * recovers via GET /api/v1/session/csrf-token and retries the original
 * request once; a second 419 emits 'csrf-stale' and propagates.
 *
 * Bootstrap:
 *   - Caller supplies basePath (and optionally csrfToken) explicitly:
 *       new SessionApiClient({ basePath: '/horde/api/v1' })
 *   - Or via <meta> tags the server rendered:
 *       <meta name="session-api" content="https://webmail.example.com/horde/api/v1">
 *       <meta name="csrf-api"    content="initial-token-bytes">
 *       const session = SessionApiClient.fromMeta();
 *
 * There is no discovery endpoint: the page that loads this client must
 * already know the API URL (whoever rendered the page knew, by
 * definition). Cross-domain pages embed the absolute URL.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

export class SessionApiClient extends EventTarget {
    /**
     * @param {object} opts
     * @param {string} opts.basePath   API base URL, e.g. '/horde/api/v1'
     * @param {?string} [opts.csrfToken] Initial CSRF token to bootstrap the in-memory store.
     */
    constructor({ basePath, csrfToken = null } = {}) {
        super();
        if (typeof basePath !== 'string' || basePath === '') {
            throw new Error('SessionApiClient: basePath is required');
        }
        this.basePath = basePath.replace(/\/$/, '');
        this.csrfToken = csrfToken;
        this._keepaliveTimer = null;
        this._keepaliveSeconds = null;
    }

    /**
     * Construct from <meta> tags the server rendered into the page.
     *
     *   <meta name="session-api" content="https://webmail.example.com/horde/api/v1">
     *   <meta name="csrf-api"    content="abc123...">
     *
     * Throws when the session-api tag is absent. The csrf-api tag is
     * optional; the client can mint its first token via refreshCsrfToken().
     */
    static fromMeta() {
        const basePathMeta = document.querySelector('meta[name="session-api"]');
        if (!basePathMeta || !basePathMeta.content) {
            throw new Error('SessionApiClient.fromMeta: <meta name="session-api"> not found');
        }
        const csrfMeta = document.querySelector('meta[name="csrf-api"]');
        const csrfToken = csrfMeta && csrfMeta.content ? csrfMeta.content : null;
        return new SessionApiClient({ basePath: basePathMeta.content, csrfToken });
    }

    // ---------- HTTP verb helpers ----------

    async get(path, opts)        { return this._request('GET', path, null, opts); }
    async post(path, body, opts) { return this._request('POST', path, body, opts); }
    async put(path, body, opts)  { return this._request('PUT', path, body, opts); }
    async delete(path, opts)     { return this._request('DELETE', path, null, opts); }

    // ---------- Session-API endpoints ----------

    /**
     * Heartbeat the modern session API. Touches lifetime, may rotate
     * session id (server-driven), refreshes CSRF in the response header.
     */
    async ping() {
        return this._request('POST', '/session/ping');
    }

    /**
     * Refresh the CSRF token without making a business request. Used
     * by the 419 retry path; consumers rarely call this directly.
     */
    async refreshCsrfToken() {
        const response = await fetch(this.basePath + '/session/csrf-token', {
            method: 'GET',
            credentials: 'include',
        });
        const token = response.headers.get('X-Csrf-Token');
        if (token) this.csrfToken = token;
        return response;
    }

    // ---------- Keepalive ----------

    /**
     * Start a periodic ping. The server's X-Next-Ping header reschedules
     * on the fly, so the interval here is just the initial cadence.
     */
    startKeepalive(intervalSeconds = 1200) {
        this.stopKeepalive();
        this._keepaliveSeconds = intervalSeconds;
        this._scheduleKeepalive(intervalSeconds);
    }

    stopKeepalive() {
        if (this._keepaliveTimer !== null) {
            clearTimeout(this._keepaliveTimer);
            this._keepaliveTimer = null;
            this._keepaliveSeconds = null;
        }
    }

    // ---------- internals ----------

    async _request(method, path, body = null, opts = {}) {
        const headers = Object.assign(
            { 'Accept': 'application/json' },
            opts.headers || {}
        );
        if (this.csrfToken && method !== 'GET' && method !== 'HEAD') {
            headers['X-Csrf-Token'] = this.csrfToken;
        }
        if (body !== null && !(body instanceof FormData) && !('Content-Type' in headers)) {
            headers['Content-Type'] = 'application/json';
        }

        const response = await fetch(this.basePath + path, {
            method,
            credentials: 'include',
            headers,
            body: body !== null && !(body instanceof FormData)
                ? JSON.stringify(body)
                : body,
        });

        // Every response can refresh in-memory state.
        const freshCsrf = response.headers.get('X-Csrf-Token');
        if (freshCsrf) this.csrfToken = freshCsrf;

        const nextPing = response.headers.get('X-Next-Ping');
        if (nextPing && this._keepaliveTimer !== null) {
            this._rescheduleKeepalive(parseInt(nextPing, 10));
        }

        if (response.status === 401) {
            this.stopKeepalive();
            this.dispatchEvent(new CustomEvent('session-expired'));
            throw new Error('SessionApiClient: session expired');
        }
        if (response.status === 419 && !opts._retried) {
            this.dispatchEvent(new CustomEvent('csrf-stale'));
            await this.refreshCsrfToken();
            return this._request(method, path, body, Object.assign({}, opts, { _retried: true }));
        }
        return response;
    }

    _scheduleKeepalive(seconds) {
        this._keepaliveTimer = setTimeout(async () => {
            try {
                await this.ping();
            } catch (_) {
                // ping() failures are handled inside _request (401 stops
                // the timer; 419 retries). Swallow any other transport
                // error and re-arm — the next attempt may succeed.
            }
            // ping()'s X-Next-Ping header may have called
            // _rescheduleKeepalive already. If not, re-arm at the
            // current cadence.
            if (this._keepaliveTimer !== null && this._keepaliveSeconds !== null) {
                this._scheduleKeepalive(this._keepaliveSeconds);
            }
        }, seconds * 1000);
    }

    _rescheduleKeepalive(seconds) {
        if (!Number.isFinite(seconds) || seconds <= 0) return;
        clearTimeout(this._keepaliveTimer);
        this._keepaliveSeconds = seconds;
        this._scheduleKeepalive(seconds);
    }
}
