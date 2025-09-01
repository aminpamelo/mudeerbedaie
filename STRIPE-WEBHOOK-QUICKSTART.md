# Stripe Webhook Quick Start Guide

## 🚀 Quick Setup (5 minutes)

### Step 1: Install Stripe CLI
```bash
# Option A: Run the installation script
./install-stripe-cli.sh

# Option B: Manual installation (macOS)
brew install stripe/stripe-cli/stripe
```

### Step 2: Configure and Test
```bash
# Run the setup wizard
./setup-stripe-webhook.sh

# This will:
# 1. Check if Stripe CLI is installed
# 2. Help you login to Stripe
# 3. Start webhook forwarding
# 4. Give you a webhook secret to configure
```

### Step 3: Add Webhook Secret
1. Copy the webhook secret from terminal (starts with `whsec_`)
2. Go to: http://localhost:8000/admin/settings/payment
3. Paste it in "Stripe Webhook Secret" field
4. Save

### Step 4: Test Webhooks
```bash
# In a new terminal (keep stripe listen running)
stripe trigger customer.updated
stripe trigger invoice.payment_succeeded

# Check results
php artisan webhook:test events
```

## 📁 Files Created

| File | Purpose |
|------|---------|
| `install-stripe-cli.sh` | Installs Stripe CLI automatically |
| `setup-stripe-webhook.sh` | Setup wizard for webhooks |
| `webhook-test-commands.sh` | Testing and monitoring commands |
| `stripe-cli-setup.md` | Detailed setup documentation |
| `webhook-testing-guide.md` | Complete webhook testing guide |

## 🎯 Available Commands

### Laravel Commands
```bash
php artisan webhook:test status    # Show system status
php artisan webhook:test events    # Recent webhook events
php artisan webhook:test failed    # Failed events
php artisan webhook:test config    # Configuration details
php artisan webhook:test clean     # Clean old events
```

### Shell Commands
```bash
./setup-stripe-webhook.sh          # Interactive setup
./webhook-test-commands.sh events  # Show events
./webhook-test-commands.sh config  # Check config
./webhook-test-commands.sh trigger # Trigger test events
```

### Stripe CLI Commands
```bash
stripe login                       # Login to Stripe
stripe listen --forward-to localhost:8000/stripe/webhook  # Start forwarding
stripe trigger customer.updated    # Test events
```

## 🔧 Current Webhook Implementation

Your Laravel app already includes:
- ✅ Webhook controller at `/stripe/webhook`
- ✅ Database logging of all webhook events
- ✅ Signature verification
- ✅ Retry mechanism for failed events
- ✅ Support for all major Stripe events

## 🎪 Testing Scenarios

### 1. Customer Events
```bash
stripe trigger customer.created
stripe trigger customer.updated
stripe trigger customer.deleted
```

### 2. Subscription Events
```bash
stripe trigger customer.subscription.created
stripe trigger customer.subscription.updated
stripe trigger customer.subscription.deleted
stripe trigger customer.subscription.trial_will_end
```

### 3. Payment Events
```bash
stripe trigger invoice.payment_succeeded
stripe trigger invoice.payment_failed
stripe trigger payment_intent.succeeded
```

### 4. Custom Data
```bash
# Trigger with specific customer data
stripe trigger customer.updated --add customer:email=test@example.com --add customer:name="Test User"
```

## 🎯 Troubleshooting

### Common Issues:

**1. "stripe: command not found"**
- Solution: Run `./install-stripe-cli.sh`

**2. "Invalid URL" in Stripe Dashboard**
- You're using `.test` domain (local only)
- Use Stripe CLI instead: `stripe listen --forward-to localhost:8000/stripe/webhook`

**3. "Webhook secret not configured"**  
- Get secret from `stripe listen` command
- Add it in `/admin/settings/payment`

**4. "Connection refused"**
- Start Laravel server: `php artisan serve` or `composer run dev`

**5. Webhook events not appearing**
- Check webhook secret is correct
- Verify Laravel server is running
- Check logs: `php artisan webhook:test failed`

### Debug Commands:
```bash
# Check everything is working
php artisan webhook:test status

# See recent webhook activity
php artisan webhook:test events

# Check configuration
php artisan webhook:test config

# Monitor logs in real-time
tail -f storage/logs/laravel.log | grep -i webhook
```

## 🎉 Success Indicators

You'll know everything is working when:
- ✅ `stripe listen` shows "Ready! Your webhook signing secret is..."
- ✅ `php artisan webhook:test status` shows "Webhook Secret: ✅ Configured"
- ✅ `stripe trigger` commands create entries in `php artisan webhook:test events`
- ✅ No errors in `php artisan webhook:test failed`

## 🚀 Next Steps

After webhooks are working:
1. Test subscription flows
2. Implement payment success handling
3. Add customer email notifications
4. Set up webhook monitoring alerts

Your webhook system is production-ready! 🎯