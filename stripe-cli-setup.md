# Stripe CLI Setup Guide

## Installation Steps

### Method 1: Using Homebrew (Recommended for macOS)

```bash
# Install Homebrew if not installed
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"

# Install Stripe CLI
brew install stripe/stripe-cli/stripe
```

### Method 2: Direct Download (Alternative)

```bash
# Download for macOS
curl -L "https://github.com/stripe/stripe-cli/releases/latest/download/stripe_darwin_amd64.tar.gz" -o stripe_cli.tar.gz

# Extract the binary
tar -xzf stripe_cli.tar.gz

# Move to a directory in your PATH
sudo mv stripe /usr/local/bin/

# Make it executable
chmod +x /usr/local/bin/stripe

# Verify installation
stripe --version
```

### Method 3: Using Package Managers

#### For Ubuntu/Debian:
```bash
curl -s https://packages.stripe.dev/api/security/keypair/stripe-cli-gpg/public | gpg --dearmor | sudo tee /usr/share/keyrings/stripe.gpg
echo "deb [signed-by=/usr/share/keyrings/stripe.gpg] https://packages.stripe.dev/stripe-cli-debian-local stable main" | sudo tee -a /etc/apt/sources.list.d/stripe.list
sudo apt update
sudo apt install stripe
```

## Configuration Steps

### 1. Login to Stripe

```bash
# Login to your Stripe account
stripe login

# This will:
# 1. Open a browser window
# 2. Ask you to confirm the pairing
# 3. Create a local configuration file
```

### 2. Verify Login

```bash
# Check your account info
stripe config --list

# Test API access
stripe customers list --limit 1
```

## Webhook Setup

### 1. Start Webhook Forwarding

```bash
# Forward webhooks to your local Laravel server
stripe listen --forward-to localhost:8000/stripe/webhook

# For specific port (if using php artisan serve)
stripe listen --forward-to http://127.0.0.1:8000/stripe/webhook
```

### 2. Copy Webhook Secret

When you run `stripe listen`, you'll see output like this:
```
> Ready! Your webhook signing secret is whsec_1234567890abcdef... (^C to quit)
```

**Copy the webhook secret (starts with `whsec_`)**

### 3. Configure in Your Application

Add the webhook secret to your Laravel application:

1. Go to: `http://localhost:8000/admin/settings/payment`
2. Paste the webhook secret in the "Stripe Webhook Secret" field
3. Save the settings

### 4. Test Webhook Events

In a new terminal (keep `stripe listen` running):

```bash
# Trigger various test events
stripe trigger customer.updated
stripe trigger invoice.payment_succeeded
stripe trigger customer.subscription.created
stripe trigger payment_intent.succeeded

# Trigger with specific data
stripe trigger customer.updated --add customer:email=test@example.com
```

### 5. Monitor Webhook Events

Use your Laravel commands to monitor:

```bash
# Check webhook status
php artisan webhook:test status

# View recent events
php artisan webhook:test events

# Check for any failures
php artisan webhook:test failed
```

## Troubleshooting

### Common Issues:

1. **"stripe: command not found"**
   - Stripe CLI is not installed or not in PATH
   - Try: `which stripe` to check installation

2. **"No such device configured"**
   - You need to run `stripe login` first
   - Make sure you're logged into the correct Stripe account

3. **"Connection refused"**
   - Your Laravel server isn't running
   - Start with: `php artisan serve`
   - Or use: `composer run dev`

4. **Webhook secret not working**
   - Make sure you copied the full secret starting with `whsec_`
   - The secret changes each time you restart `stripe listen`
   - Update the secret in your admin settings when restarting

### Verification Commands:

```bash
# Check Stripe CLI status
stripe --version
stripe config --list

# Check Laravel server
curl -I http://localhost:8000/stripe/webhook

# Test webhook endpoint
php artisan webhook:test config
```

## Production Setup

For production, don't use `stripe listen`. Instead:

1. **Create webhook endpoint in Stripe Dashboard:**
   - Go to: https://dashboard.stripe.com/webhooks
   - Click "Add endpoint"
   - URL: `https://yourdomain.com/stripe/webhook`
   - Select events you want to listen for

2. **Copy the webhook signing secret from dashboard**

3. **Add to your production environment**

## Useful Commands Reference

```bash
# Authentication
stripe login
stripe logout

# Webhook forwarding
stripe listen --forward-to localhost:8000/stripe/webhook
stripe listen --events=customer.created,customer.updated --forward-to localhost:8000/stripe/webhook

# Testing events
stripe trigger customer.updated
stripe trigger invoice.payment_succeeded
stripe trigger customer.subscription.created

# API testing
stripe customers list
stripe products list
stripe prices list

# Configuration
stripe config --list
stripe config --edit
```

## Next Steps After Installation

1. ✅ Install Stripe CLI
2. ✅ Login to your Stripe account
3. ✅ Start webhook forwarding
4. ✅ Copy webhook secret to Laravel settings
5. ✅ Test webhook events
6. ✅ Monitor with Laravel commands

Your webhook system will then be fully functional for local development! 🎉