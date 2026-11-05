import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { requestWithTimeout, requestErrorMessage } from '../../resources/js/request-errors.js';

test('a timed-out mutation is cancelled and never automatically replayed', async () => {
    let requests = 0;
    const fetchImpl = (_, { signal }) => {
        requests++;
        return new Promise((resolve, reject) => signal.addEventListener('abort', () => reject(signal.reason)));
    };
    await assert.rejects(requestWithTimeout('https://example.test/orders', { method: 'POST' }, { fetchImpl, timeoutMs: 5 }), { name: 'TimeoutError' });
    assert.equal(requests, 1);
});

test('caller cancellation remains cancellation rather than a retry', async () => {
    const controller = new AbortController();
    controller.abort();
    await assert.rejects(requestWithTimeout('https://example.test/search', { signal: controller.signal }, {
        fetchImpl: (_, { signal }) => Promise.reject(signal.reason),
    }), { name: 'AbortError' });
});

test('HTTP recovery messages never expose provider details', () => {
    assert.match(requestErrorMessage(419), /session expired/);
    assert.match(requestErrorMessage(429), /Wait a moment/);
    assert.match(requestErrorMessage(500), /Check your orders/);
});

const cartSelection = () => {
    const scope = { window: {}, fetch, setTimeout: () => 1, clearTimeout() {}, Set, Intl };
    vm.runInNewContext(readFileSync(new URL('../../resources/js/cart.js', import.meta.url), 'utf8'), scope);
    return scope.window.cartSelection({ selected: ['one'], items: [{ id: 'one', available: true }, { id: 'two', available: true }], endpoint: '/cart/selection' });
};

test('cart saves are serialized and checkout waits for the latest selection', async () => {
    const cart = cartSelection();
    let release;
    const snapshots = [];
    cart.request = async selected => {
        snapshots.push([...selected]);
        if (snapshots.length === 1) await new Promise(resolve => { release = resolve; });
        return { ok: true, status: 200 };
    };
    const first = cart.send();
    cart.selected.add('two');
    cart.persist();
    const latest = cart.send();
    assert.equal(first, latest);
    assert.equal(cart.saving, true);
    assert.equal(snapshots.length, 1);
    release();
    await latest;
    assert.deepEqual(snapshots.map(x => Array.from(x)), [['one'], ['one', 'two']]);
    assert.equal(cart.saving, false);
    assert.equal(cart.checkoutReady, true);
});

test('failed cart selection blocks checkout until an explicit retry succeeds', async () => {
    const cart = cartSelection();
    let attempts = 0;
    cart.request = async () => { attempts++; throw new TypeError('network failed'); };
    await cart.send();
    assert.equal(attempts, 1);
    assert.equal(cart.checkoutReady, false);
    cart.request = async () => ({ ok: true, status: 200 });
    await cart.send();
    assert.equal(cart.error, '');
    assert.equal(cart.checkoutReady, true);
});

// Receiving headers is not enough: a stalled response body must also be bounded.
test('a stalled response body times out without replaying its request', async () => {
    const { createServer } = await import('node:http');
    const server = createServer((request, response) => {
        response.writeHead(200, { 'Content-Type': 'application/json' });
        response.write('{"incomplete":');
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    try {
        let timedOut = false;
        const response = await requestWithTimeout(`http://127.0.0.1:${server.address().port}/slow-body`, {}, {
            timeoutMs: 150,
            onTimeout: () => { timedOut = true; },
        });
        await assert.rejects(response.json(), { name: 'TimeoutError' });
        assert.equal(timedOut, true);
    } finally {
        server.closeAllConnections();
        await new Promise(resolve => server.close(resolve));
    }
});
