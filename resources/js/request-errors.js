export const requestErrorMessage = (status) => {
    if (status === 401 || status === 419) return 'Your session expired. Refresh the page and sign in again. Your action has not been retried.';
    if (status === 403) return 'You do not have permission to perform this action.';
    if (status === 404) return 'This item is no longer available. Refresh the page to continue.';
    if (status === 409) return 'This action is already in progress. Check its status before trying again.';
    if (status === 422) return 'Please check the highlighted fields and try again.';
    if (status === 429) return 'Too many requests. Wait a moment before trying again.';
    return 'We could not confirm the result. Check your orders or the current status before trying again.';
};

export const requestWithTimeout = async (input, options = {}, { fetchImpl = fetch, timeoutMs = 30_000, onTimeout = () => {} } = {}) => {
    const controller = new AbortController();
    const signal = options.signal ?? (input instanceof Request ? input.signal : undefined);
    const startDeadline = () => {
        const abort = () => controller.abort(signal.reason);
        if (signal?.aborted) abort();
        else signal?.addEventListener('abort', abort, { once: true });
        const timer = setTimeout(() => {
            controller.abort(new DOMException('The request timed out.', 'TimeoutError'));
            signal?.removeEventListener('abort', abort);
            onTimeout();
        }, timeoutMs);
        return () => {
            clearTimeout(timer);
            signal?.removeEventListener('abort', abort);
        };
    };
    const cleanup = startDeadline();
    let response;
    try {
        response = await fetchImpl(input, { ...options, signal: controller.signal });
    } finally {
        cleanup();
    }
    if (response.body) {
        for (const method of ['json', 'text', 'arrayBuffer', 'blob', 'formData']) {
            const read = response[method].bind(response);
            response[method] = async (...args) => {
                const finish = startDeadline();
                try { return await read(...args); }
                finally { finish(); }
            };
        }
    }
    return response;
};

export const showRequestError = (message, reference = null) => {
    const host = document.querySelector('[data-request-feedback]');
    if (!host) return;
    host.hidden = false;
    const text = reference && /^[A-Za-z0-9._-]{8,100}$/.test(reference) ? `${message} Reference: ${reference}` : message;
    const content = host.querySelector('[data-request-feedback-message]');
    if (content.textContent !== text) content.textContent = text;
};

if (typeof window !== 'undefined' && typeof document !== 'undefined') {
    const browserFetch = window.fetch.bind(window);
    window.fetch = async (input, options = {}) => {
        const url = new URL(input instanceof Request ? input.url : input, window.location.href);
        if (url.origin !== window.location.origin) return browserFetch(input, options);
        try {
            const response = await requestWithTimeout(input, options, {
                fetchImpl: browserFetch,
                timeoutMs: options.body instanceof FormData ? 120_000 : 30_000,
                onTimeout: () => showRequestError('The request took too long. Check its current status before trying again.'),
            });
            if (response.status >= 500) showRequestError(requestErrorMessage(response.status), response.headers.get('X-Request-Id'));
            return response;
        } catch (error) {
            if (error.name !== 'AbortError') {
                showRequestError(error.name === 'TimeoutError'
                    ? 'The request took too long. Check its current status before trying again.'
                    : 'Connection lost. Check your connection and the current status before trying again.');
            }
            throw error;
        }
    };

    const installLivewireErrors = () => {
        window.Livewire?.interceptRequest(({ onError, onFailure }) => {
            onError(({ response, preventDefault }) => {
                preventDefault();
                showRequestError(requestErrorMessage(response.status), response.headers.get('X-Request-Id'));
            });
            onFailure(({ error } = {}) => {
                if (error?.clerkRefreshHandled) {
                    return;
                }

                showRequestError('Connection lost. Your changes are still on this page. Check the current status before retrying.');
            });
        });
    };
    document.addEventListener('livewire:init', installLivewireErrors, { once: true });
    if (window.Livewire) installLivewireErrors();

    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) return;
        if (event.target.closest('[data-request-feedback-dismiss]')) document.querySelector('[data-request-feedback]').hidden = true;
        if (event.target.closest('[data-request-feedback-reload]')) window.location.reload();
    });
    window.addEventListener('offline', () => showRequestError('You are offline. Reconnect before submitting changes.'));

    // Runs after form-specific handlers; never replay a state-changing request.
    window.addEventListener('submit', (event) => {
        const form = event.target;
        if (event.defaultPrevented || !(form instanceof HTMLFormElement) || form.method.toUpperCase() === 'GET' || form.method === 'dialog') return;
        if (form.dataset.errorSubmitting) {
            event.preventDefault();
            return;
        }
        form.dataset.errorSubmitting = 'true';
        event.submitter?.setAttribute('aria-busy', 'true');
    });
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('[data-error-submitting]').forEach((form) => {
            delete form.dataset.errorSubmitting;
            form.querySelectorAll('[aria-busy="true"]').forEach((button) => button.removeAttribute('aria-busy'));
        });
    });
}
