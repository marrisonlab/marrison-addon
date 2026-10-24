const assert = require('node:assert/strict');
const { fixture } = require('./cookie-runtime-js-test');
(async () => {
    const idle = fixture(undefined, {unknown_external: true});
    await assert.rejects(idle.window.fetch('https://hidden.example/p?secret=PRIVATE'));
    assert.equal(idle.requests.length, 0, 'Passive detection enabled by default');
    const page = fixture(undefined, {unknown_external: true, passive: {url: 'https://example.test/wp-admin/admin-ajax.php', nonce: 'fixture'}});
    for (let n = 0; n < 25; n++) { await assert.rejects(page.window.fetch('https://host' + n + '.example/private?secret=PRIVATE')); }
    await new Promise(resolve => setTimeout(resolve, 1700));
    assert.equal(page.requests.length, 1, 'Blocked resources caused extra reports or leaked requests');
    const body = page.requestBodies[0];
    assert.equal(body.getAll('hosts[]').length, 20);
    assert(body.getAll('hosts[]').every(host => /^host\d+\.example$/.test(host)));
    assert(!body.toString().includes('PRIVATE') && !body.toString().includes('private') && !body.toString().includes('visitor'));
    assert.deepEqual([...new Set(body.keys())].sort(), ['action', 'hosts[]', 'nonce']);
    console.log('PASS passive detection: default off, blocked network stays blocked, one bounded report, host-only payload without path/query/visitor ID');
})().catch(error => {console.error(error); process.exitCode = 1;});
