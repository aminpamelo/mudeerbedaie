/**
 * Funnel Checkout Embed Widget
 * Embeds a checkout form on any website
 */
(function() {
    'use strict';

    // Configuration
    const EMBED_BASE_URL = '{{ rtrim(config('app.url'), '/') }}';

    // Find all embed containers and script tags
    function init() {
        // Method 1: Script tag with data-funnel-key attribute
        const scripts = document.querySelectorAll('script[data-funnel-key]');
        scripts.forEach(script => {
            const key = script.getAttribute('data-funnel-key');
            const containerId = script.getAttribute('data-container') || `funnel-checkout-${key}`;
            let container = document.getElementById(containerId);

            // Create container if it doesn't exist
            if (!container) {
                container = document.createElement('div');
                container.id = containerId;
                script.parentNode.insertBefore(container, script);
            }

            createEmbed(container, key, {
                width: script.getAttribute('data-width') || '100%',
                maxWidth: script.getAttribute('data-max-width') || '500px',
                prefillEmail: script.getAttribute('data-prefill-email'),
                prefillName: script.getAttribute('data-prefill-name'),
                prefillPhone: script.getAttribute('data-prefill-phone'),
            });
        });

        // Method 2: Elements with data-funnel-embed attribute
        const containers = document.querySelectorAll('[data-funnel-embed]');
        containers.forEach(container => {
            const key = container.getAttribute('data-funnel-embed');
            if (key && !container.querySelector('iframe')) {
                createEmbed(container, key, {
                    width: container.getAttribute('data-width') || '100%',
                    maxWidth: container.getAttribute('data-max-width') || '500px',
                    prefillEmail: container.getAttribute('data-prefill-email'),
                    prefillName: container.getAttribute('data-prefill-name'),
                    prefillPhone: container.getAttribute('data-prefill-phone'),
                });
            }
        });
    }

    // Create embed iframe
    function createEmbed(container, embedKey, options = {}) {
        // Build URL with query parameters
        const params = new URLSearchParams();
        params.set('origin', window.location.origin);

        // Add UTM parameters from current page URL
        const currentUrl = new URL(window.location.href);
        ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'].forEach(param => {
            const value = currentUrl.searchParams.get(param);
            if (value) {
                params.set(param, value);
            }
        });

        // Add referrer
        if (document.referrer) {
            params.set('referrer', document.referrer);
        }

        const embedUrl = `${EMBED_BASE_URL}/embed/${embedKey}?${params.toString()}`;

        // Create iframe
        const iframe = document.createElement('iframe');
        iframe.src = embedUrl;
        iframe.setAttribute('frameborder', '0');
        iframe.setAttribute('allow', 'payment');
        iframe.setAttribute('loading', 'lazy');
        iframe.style.cssText = `
            width: ${options.width || '100%'};
            max-width: ${options.maxWidth || '500px'};
            height: 800px;
            border: none;
            display: block;
            margin: 0 auto;
            transition: height 0.3s ease;
        `;

        // Store options for later use
        iframe.dataset.embedKey = embedKey;
        iframe.dataset.options = JSON.stringify(options);

        // Add loading state
        container.innerHTML = '';
        container.appendChild(iframe);

        // Handle messages from iframe
        window.addEventListener('message', function(event) {
            // Verify origin
            if (!event.origin.startsWith(EMBED_BASE_URL.replace(/^https?:/, ''))) {
                return;
            }

            const data = event.data;

            switch (data.type) {
                case 'funnel-embed-ready':
                    // Iframe is ready, send prefill data if available
                    if (options.prefillEmail || options.prefillName || options.prefillPhone) {
                        iframe.contentWindow.postMessage({
                            type: 'funnel-embed-prefill',
                            data: {
                                email: options.prefillEmail,
                                name: options.prefillName,
                                phone: options.prefillPhone,
                            }
                        }, '*');
                    }
                    iframe.style.height = data.height + 'px';
                    break;

                case 'funnel-embed-resize':
                    // Resize iframe to fit content
                    iframe.style.height = data.height + 'px';
                    break;

                case 'funnel-checkout-complete':
                    // Checkout completed
                    dispatchEvent(container, 'funnel:checkout:complete', data.data);
                    break;

                case 'funnel-order-created':
                    // Order created, redirect or show confirmation
                    dispatchEvent(container, 'funnel:order:created', data.data);
                    break;
            }
        });
    }

    // Dispatch custom events
    function dispatchEvent(element, eventName, detail) {
        const event = new CustomEvent(eventName, {
            bubbles: true,
            detail: detail
        });
        element.dispatchEvent(event);
    }

    // Public API
    window.FunnelEmbed = {
        init: init,
        create: createEmbed,

        // Programmatic creation
        embed: function(selector, embedKey, options) {
            const container = typeof selector === 'string'
                ? document.querySelector(selector)
                : selector;

            if (container) {
                createEmbed(container, embedKey, options);
            }
        },

        // Prefill form data
        prefill: function(embedKey, data) {
            const iframe = document.querySelector(`iframe[data-embed-key="${embedKey}"]`);
            if (iframe) {
                iframe.contentWindow.postMessage({
                    type: 'funnel-embed-prefill',
                    data: data
                }, '*');
            }
        }
    };

    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
