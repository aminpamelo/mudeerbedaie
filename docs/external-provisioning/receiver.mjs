// Minimal Express receiver (Node 18+). Same contract as the Laravel version.
//   npm i express
//   PROVISIONING_API_KEY=... PROVISIONING_SIGNING_SECRET=... node receiver.mjs

import express from 'express';
import crypto from 'node:crypto';

const { PROVISIONING_API_KEY: API_KEY, PROVISIONING_SIGNING_SECRET: SECRET } = process.env;
const app = express();

// Capture the RAW body — the signature is computed over the exact bytes.
app.post('/api/v1/provision', express.raw({ type: '*/*' }), async (req, res) => {
    const raw = req.body; // Buffer
    const expected = crypto.createHmac('sha256', SECRET).update(raw).digest('hex');
    const bearer = (req.get('authorization') || '').replace(/^Bearer /i, '');

    // 1 — Authenticate (constant-time).
    if (!safeEqual(bearer, API_KEY) || !safeEqual(req.get('x-signature') || '', expected)) {
        return res.status(401).json({ message: 'Invalid credentials' });
    }

    const { idempotency_key, customer, product } = JSON.parse(raw.toString('utf8'));

    // 2 + 3 — Idempotent, synchronous create (your implementation).
    const account = await upsertAccount(idempotency_key, customer, product);

    // 4 — Return the login details.
    return res.json({
        external_user_id: String(account.userId),
        login_url: account.loginUrl,
        username: customer.email,
        magic_link: account.loginUrl,
    });
});

function safeEqual(a, b) {
    const A = Buffer.from(a);
    const B = Buffer.from(b);
    return A.length === B.length && crypto.timingSafeEqual(A, B);
}

// Replace with your real logic. MUST be idempotent on idempotency_key.
async function upsertAccount(idempotencyKey, customer, product) {
    // const existing = await db.accounts.findByKey(idempotencyKey);
    // if (existing) return existing;
    // const user = await db.users.upsertByEmail(customer.email, { plan: product.plan });
    // return db.accounts.create({ idempotencyKey, userId: user.id, loginUrl: `.../login/${token}` });
    throw new Error('upsertAccount not implemented');
}

app.listen(8000, () => console.log('Provisioning receiver on :8000'));
