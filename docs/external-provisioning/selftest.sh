#!/usr/bin/env bash
# Simulate the main platform's signed call so you can test YOUR receiver locally.
#
#   PROVISIONING_API_KEY=xxx PROVISIONING_SIGNING_SECRET=yyy \
#     ./selftest.sh http://localhost:8000/api/v1/provision
#
# Expects: 200 with { external_user_id, login_url, ... }

set -euo pipefail

URL="${1:-http://localhost:8000/api/v1/provision}"
API_KEY="${PROVISIONING_API_KEY:?set PROVISIONING_API_KEY}"
SECRET="${PROVISIONING_SIGNING_SECRET:?set PROVISIONING_SIGNING_SECRET}"

# The exact body bytes the platform would send. The signature is over these bytes.
BODY='{"idempotency_key":"prov_test_1","order_ref":"ORD-TEST","order_id":1,"customer":{"email":"buyer@example.com","name":"Test Buyer","phone":"+60123456789"},"product":{"funnel_product_id":1,"name":"Premium","sku":"PRE-1","plan":"gold"}}'

# lowercase hex HMAC-SHA256, matching PHP hash_hmac('sha256', body, secret)
SIG="$(printf '%s' "$BODY" | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.*= *//')"

curl -sS -X POST "$URL" \
    -H "Authorization: Bearer $API_KEY" \
    -H "X-Signature: $SIG" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    --data "$BODY"
echo
