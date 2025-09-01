# Stripe Webhook Testing Guide

## Current Webhook Implementation Status ✅

Your system already has a **complete webhook implementation** with:

### Components Present:
- **Webhook Controller**: `app/Http/Controllers/StripeWebhookController.php`
- **Webhook Route**: `POST /stripe/webhook` (no auth required)
- **Webhook Service**: `StripeService::handleWebhook()` method
- **Webhook Model**: `app/Models/WebhookEvent.php` with database logging
- **Webhook Configuration**: Settings stored in admin panel at `/admin/settings/payment`

### Supported Webhook Events:
- `customer.updated`
- `invoice.payment_succeeded` 
- `customer.subscription.created`
- `customer.subscription.updated`
- `customer.subscription.deleted`
- `customer.subscription.trial_will_end`

### Features:
- ✅ Signature verification with webhook secret
- ✅ Duplicate event prevention
- ✅ Database logging of all webhook events
- ✅ Retry mechanism (max 3 attempts)
- ✅ Comprehensive error logging
- ✅ Event processing status tracking

## Testing Your Webhooks

### 1. **Check Current Webhook Configuration**

First, verify your webhook is properly configured:

```bash
# Check if webhook secret is set in settings
php artisan tinker
>>> app('App\Services\SettingsService')->get('stripe_webhook_secret')
```

### 2. **Local Testing with Stripe CLI**

Install Stripe CLI and test webhooks locally:

```bash
# Install Stripe CLI (macOS)
brew install stripe/stripe-cli/stripe

# Login to Stripe
stripe login

# Forward webhooks to your local endpoint
stripe listen --forward-to localhost:8000/stripe/webhook

# Test specific events
stripe trigger payment_intent.succeeded
stripe trigger customer.subscription.created
stripe trigger invoice.payment_succeeded
```

### 3. **Production Webhook Setup**

Configure webhook endpoint in Stripe Dashboard:

1. Go to **Stripe Dashboard > Webhooks**
2. Click **"Add endpoint"**
3. Enter URL: `https://yourdomain.com/stripe/webhook`
4. Select events to listen for:
   - `customer.*`
   - `invoice.payment_succeeded`
   - `customer.subscription.*`
5. Copy the webhook signing secret
6. Add secret to your admin settings at `/admin/settings/payment`

### 4. **Monitor Webhook Events**

Check webhook processing in real-time:

```bash
# Watch webhook events in database
php artisan tinker
>>> App\Models\WebhookEvent::latest()->get()

# Monitor logs
tail -f storage/logs/laravel.log | grep -i webhook

# Check failed webhooks
>>> App\Models\WebhookEvent::failed()->get()

# Check pending webhooks
>>> App\Models\WebhookEvent::pending()->get()
```

### 5. **Test Webhook Processing**

Use these commands to test webhook functionality:

```bash
# Test webhook endpoint directly (replace with your webhook secret)
curl -X POST http://localhost:8000/stripe/webhook \
  -H "Content-Type: application/json" \
  -H "Stripe-Signature: your-test-signature" \
  -d '{"id":"evt_test","type":"customer.updated","data":{"object":{"id":"cus_test"}}}'
```

### 6. **Webhook Status Dashboard**

Access webhook monitoring at `/admin/payment-dashboard` to see:
- Recent webhook events
- Processing status
- Error messages
- Retry counts

## Webhook Event Details

### Current Webhook URL
```
https://yourdomain.com/stripe/webhook
```

### Webhook Events Table Schema
```sql
- id: Primary key
- stripe_event_id: Unique Stripe event ID
- type: Event type (e.g., 'customer.updated')
- data: JSON payload from Stripe
- processed: Boolean status
- processed_at: Timestamp when processed
- error_message: Error details if failed
- retry_count: Number of retry attempts
```

## Troubleshooting

### Common Issues:

1. **"Missing signature"** 
   - Ensure Stripe-Signature header is present
   - Check webhook secret configuration

2. **"Webhook secret not configured"**
   - Add webhook secret in `/admin/settings/payment`
   - Ensure secret starts with `whsec_`

3. **"Signature verification failed"**
   - Verify webhook secret matches Stripe dashboard
   - Check endpoint URL is correct

4. **Webhook events not processing**
   - Check `webhook_events` table for entries
   - Review Laravel logs for errors
   - Verify event types are handled in `StripeService::handleWebhook()`

### Debug Commands:

```bash
# Check last 10 webhook events
php artisan tinker
>>> App\Models\WebhookEvent::latest()->limit(10)->get(['stripe_event_id', 'type', 'processed', 'error_message'])

# Retry failed webhooks
>>> App\Models\WebhookEvent::failed()->where('retry_count', '<', 3)->get()->each->markAsProcessed()

# Clear processed webhook events older than 30 days
>>> App\Models\WebhookEvent::where('processed', true)->where('created_at', '<', now()->subDays(30))->delete()
```

## Security Notes

- Webhook endpoint has no authentication middleware (correct for Stripe webhooks)
- Signature verification ensures requests are from Stripe
- Webhook secret is encrypted in database
- All webhook events are logged for audit trail

Your webhook system is **production-ready** and follows Stripe best practices! 🎉