/**
 * Horde JWT Token Management
 *
 * Provides automatic token refresh with rate limiting to prevent race
 * conditions when multiple browser tabs request token refresh simultaneously.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * @category Horde
 * @package  Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

(function() {
    'use strict';

    // Configuration - will be set by PHP template
    const CONFIG = window.HORDE_AUTH_CONFIG || {
        webroot: '/horde',
        logoutUrl: '/horde/auth/logout',
        jwtBootstrap: null
    };

    const WEBROOT = CONFIG.webroot;
    const LOGOUT_URL = CONFIG.logoutUrl;
    const JWT_BOOTSTRAP = CONFIG.jwtBootstrap;

    // Rate limiting configuration
    const REFRESH_COOLDOWN = 30000;  // 30 seconds between refresh attempts
    const REFRESH_BUFFER = 300000;   // Refresh when < 5 minutes until expiry
    const LAST_REFRESH_KEY = 'horde_last_token_refresh';
    const REFRESH_IN_PROGRESS_KEY = 'horde_refresh_in_progress';

    /**
     * Refresh access token with rate limiting
     *
     * Prevents multiple tabs from refreshing simultaneously by:
     * 1. Checking last refresh timestamp (30-second cooldown)
     * 2. Setting in-progress flag for cross-tab coordination
     * 3. Waiting for other tab's refresh if one is in progress
     */
    async function refreshAccessToken() {
        // Check if refresh is already in progress (another tab)
        const refreshInProgress = localStorage.getItem(REFRESH_IN_PROGRESS_KEY);
        if (refreshInProgress) {
            const inProgressTime = parseInt(refreshInProgress);
            const elapsed = Date.now() - inProgressTime;

            // If refresh started less than 10 seconds ago, wait for it
            if (elapsed < 10000) {
                console.log('Token refresh in progress in another tab, waiting...');
                await new Promise(resolve => setTimeout(resolve, 2000));
                // Assume other tab completed, reload tokens
                return {
                    access_token: localStorage.getItem('access_token'),
                    expires_at: localStorage.getItem('token_expires_at')
                };
            } else {
                // Stale in-progress flag (other tab crashed?), clear it
                localStorage.removeItem(REFRESH_IN_PROGRESS_KEY);
            }
        }

        // Check cooldown to prevent rapid refresh attempts
        const lastRefresh = localStorage.getItem(LAST_REFRESH_KEY);
        if (lastRefresh) {
            const timeSince = Date.now() - parseInt(lastRefresh);
            if (timeSince < REFRESH_COOLDOWN) {
                console.log('Token refresh attempted too soon (' + Math.floor(timeSince/1000) + 's ago), skipping');
                return {
                    access_token: localStorage.getItem('access_token'),
                    expires_at: localStorage.getItem('token_expires_at')
                };
            }
        }

        // Set in-progress flag BEFORE making request
        localStorage.setItem(REFRESH_IN_PROGRESS_KEY, Date.now().toString());
        localStorage.setItem(LAST_REFRESH_KEY, Date.now().toString());

        try {
            const refreshToken = localStorage.getItem('refresh_token');

            const response = await fetch(WEBROOT + '/api/v1/auth/refresh', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ refresh_token: refreshToken }),
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error('Refresh failed: ' + response.status);
            }

            const data = await response.json();

            // Update localStorage with new tokens
            localStorage.setItem('access_token', data.access_token);
            localStorage.setItem('token_expires_at', data.expires_at * 1000);

            console.log('Token refreshed successfully');

            return data;

        } catch (error) {
            console.error('Error refreshing token:', error);
            // Clear rate limit on error so retry is possible
            localStorage.removeItem(LAST_REFRESH_KEY);
            throw error;
        } finally {
            // Clear in-progress flag
            localStorage.removeItem(REFRESH_IN_PROGRESS_KEY);
        }
    }

    /**
     * Bootstrap JWT tokens on page load
     */
    async function bootstrapJWT() {
        // Check if we have bootstrap tokens from login
        if (JWT_BOOTSTRAP && JWT_BOOTSTRAP.access_token && JWT_BOOTSTRAP.refresh_token) {
            console.log('Storing JWT tokens from login...');
            localStorage.setItem('access_token', JWT_BOOTSTRAP.access_token);
            localStorage.setItem('refresh_token', JWT_BOOTSTRAP.refresh_token);
            localStorage.setItem('token_expires_at', JWT_BOOTSTRAP.expires_at * 1000);
            console.log('JWT tokens stored successfully');
            return;
        }

        // Check if we already have tokens
        const accessToken = localStorage.getItem('access_token');
        const refreshToken = localStorage.getItem('refresh_token');

        if (accessToken && refreshToken) {
            console.log('JWT tokens already present');

            // Check if token needs refresh
            const expiresAt = parseInt(localStorage.getItem('token_expires_at'));
            const timeUntilExpiry = expiresAt - Date.now();

            if (timeUntilExpiry < REFRESH_BUFFER) {
                console.log('Token expires soon, refreshing...');
                try {
                    await refreshAccessToken();
                } catch (error) {
                    console.error('Failed to refresh token:', error);
                }
            }

            return;
        }

        // If no tokens and no bootstrap, try to get them from session
        console.log('No JWT tokens found, checking session...');

        try {
            // Call refresh endpoint without refresh_token to bootstrap
            const response = await fetch(WEBROOT + '/api/v1/auth/refresh', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({}),
                credentials: 'same-origin'
            });

            if (!response.ok) {
                if (response.status === 401) {
                    console.log('No active session with JWT support');
                } else {
                    console.error('Failed to bootstrap JWT:', response.status);
                }
                return;
            }

            const data = await response.json();

            // Store tokens
            localStorage.setItem('access_token', data.access_token);
            localStorage.setItem('refresh_token', data.refresh_token);
            localStorage.setItem('token_expires_at', Date.now() + (data.expires_in * 1000));

            console.log('JWT tokens bootstrapped from session');

        } catch (error) {
            console.error('Error bootstrapping JWT:', error);
        }
    }

    /**
     * Make API call with automatic token refresh
     *
     * Usage:
     *   makeApiCall('/horde/api/v1/some/endpoint', { method: 'POST', body: {...} })
     */
    async function makeApiCall(endpoint, options) {
        options = options || {};

        // Check if token needs refresh
        const expiresAt = parseInt(localStorage.getItem('token_expires_at'));
        const timeUntilExpiry = expiresAt - Date.now();

        if (timeUntilExpiry < REFRESH_BUFFER) {
            try {
                await refreshAccessToken();
            } catch (error) {
                console.error('Failed to refresh token before API call:', error);
                // Continue anyway - let API return 401 if token is invalid
            }
        }

        // Make API call with current token
        const accessToken = localStorage.getItem('access_token');
        const headers = options.headers || {};
        headers['Authorization'] = 'Bearer ' + accessToken;

        return fetch(endpoint, {
            method: options.method || 'GET',
            headers: headers,
            body: options.body,
            credentials: options.credentials
        });
    }

    /**
     * Handle logout
     */
    async function logout() {
        // Clear localStorage
        localStorage.removeItem('access_token');
        localStorage.removeItem('refresh_token');
        localStorage.removeItem('token_expires_at');
        localStorage.removeItem(LAST_REFRESH_KEY);
        localStorage.removeItem(REFRESH_IN_PROGRESS_KEY);

        // Call logout endpoint to destroy session
        try {
            await fetch(LOGOUT_URL, {
                method: 'GET',
                credentials: 'same-origin'
            });
        } catch (error) {
            console.error('Logout error:', error);
        }

        // Redirect to login
        window.location.href = WEBROOT + '/auth/login?logout=1';
    }

    // Bootstrap on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrapJWT);
    } else {
        bootstrapJWT();
    }

    // Expose API for other scripts
    window.HordeAuth = {
        refreshAccessToken: refreshAccessToken,
        makeApiCall: makeApiCall,
        logout: logout
    };

})();
