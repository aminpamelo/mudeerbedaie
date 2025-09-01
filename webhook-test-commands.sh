#!/bin/bash

# Stripe Webhook Testing Commands
# Make this file executable: chmod +x webhook-test-commands.sh

echo "🎯 Stripe Webhook Testing Commands"
echo "=================================="

# Function to check webhook configuration
check_webhook_config() {
    echo "📋 Checking webhook configuration..."
    php artisan tinker --execute="
        \$settings = app('App\\Services\\SettingsService');
        \$secret = \$settings->get('stripe_webhook_secret');
        echo 'Webhook Secret: ' . (\$secret ? 'Configured ✅' : 'Not configured ❌') . PHP_EOL;
        echo 'Stripe Secret Key: ' . (\$settings->get('stripe_secret_key') ? 'Configured ✅' : 'Not configured ❌') . PHP_EOL;
        echo 'Payment Mode: ' . \$settings->get('payment_mode', 'Not set') . PHP_EOL;
    "
}

# Function to show recent webhook events
show_webhook_events() {
    echo "📊 Recent webhook events..."
    php artisan tinker --execute="
        \$events = App\\Models\\WebhookEvent::latest()->limit(10)->get(['stripe_event_id', 'type', 'processed', 'error_message', 'created_at']);
        if (\$events->isEmpty()) {
            echo 'No webhook events found.' . PHP_EOL;
        } else {
            foreach (\$events as \$event) {
                \$status = \$event->processed ? '✅' : (\$event->error_message ? '❌' : '⏳');
                echo \$status . ' ' . \$event->type . ' (' . \$event->stripe_event_id . ') - ' . \$event->created_at . PHP_EOL;
                if (\$event->error_message) {
                    echo '   Error: ' . \$event->error_message . PHP_EOL;
                }
            }
        }
    "
}

# Function to show failed webhooks
show_failed_webhooks() {
    echo "❌ Failed webhook events..."
    php artisan tinker --execute="
        \$failed = App\\Models\\WebhookEvent::failed()->get(['stripe_event_id', 'type', 'error_message', 'retry_count']);
        if (\$failed->isEmpty()) {
            echo 'No failed webhook events ✅' . PHP_EOL;
        } else {
            foreach (\$failed as \$event) {
                echo '❌ ' . \$event->type . ' (' . \$event->stripe_event_id . ') - Retries: ' . \$event->retry_count . PHP_EOL;
                echo '   Error: ' . \$event->error_message . PHP_EOL;
            }
        }
    "
}

# Function to clean old webhook events
clean_old_webhooks() {
    echo "🧹 Cleaning webhook events older than 30 days..."
    php artisan tinker --execute="
        \$deleted = App\\Models\\WebhookEvent::where('processed', true)->where('created_at', '<', now()->subDays(30))->count();
        App\\Models\\WebhookEvent::where('processed', true)->where('created_at', '<', now()->subDays(30))->delete();
        echo 'Deleted ' . \$deleted . ' old webhook events' . PHP_EOL;
    "
}

# Function to start Stripe CLI webhook forwarding
start_stripe_cli() {
    echo "🚀 Starting Stripe CLI webhook forwarding..."
    echo "Make sure you have Stripe CLI installed: brew install stripe/stripe-cli/stripe"
    echo "Login first: stripe login"
    echo ""
    echo "Starting webhook forwarding to http://localhost:8000/stripe/webhook"
    stripe listen --forward-to localhost:8000/stripe/webhook
}

# Function to trigger test events
trigger_test_events() {
    echo "🧪 Triggering test webhook events..."
    
    echo "Triggering customer.updated..."
    stripe trigger customer.updated
    
    echo "Triggering invoice.payment_succeeded..."  
    stripe trigger invoice.payment_succeeded
    
    echo "Triggering customer.subscription.created..."
    stripe trigger customer.subscription.created
    
    sleep 3
    echo "✅ Test events triggered! Check your webhook events with: ./webhook-test-commands.sh events"
}

# Function to test webhook endpoint directly
test_webhook_endpoint() {
    echo "🔧 Testing webhook endpoint directly..."
    
    # Create a simple test payload
    TEST_PAYLOAD='{"id":"evt_test_webhook","type":"ping","data":{"object":{"id":"test"}},"created":1234567890}'
    
    echo "Testing POST to http://localhost:8000/stripe/webhook"
    echo "Note: This will fail signature verification (which is expected for security)"
    
    curl -X POST http://localhost:8000/stripe/webhook \
        -H "Content-Type: application/json" \
        -H "Stripe-Signature: invalid-signature-for-testing" \
        -d "$TEST_PAYLOAD" \
        -w "\nHTTP Status: %{http_code}\n"
    
    echo ""
    echo "💡 For real testing, use Stripe CLI with: ./webhook-test-commands.sh stripe-cli"
}

# Main menu
case "$1" in
    "config")
        check_webhook_config
        ;;
    "events")
        show_webhook_events
        ;;
    "failed")
        show_failed_webhooks
        ;;
    "clean")
        clean_old_webhooks
        ;;
    "stripe-cli")
        start_stripe_cli
        ;;
    "trigger")
        trigger_test_events
        ;;
    "test-endpoint")
        test_webhook_endpoint
        ;;
    *)
        echo "Usage: $0 {config|events|failed|clean|stripe-cli|trigger|test-endpoint}"
        echo ""
        echo "Commands:"
        echo "  config        - Check webhook configuration"
        echo "  events        - Show recent webhook events"
        echo "  failed        - Show failed webhook events"
        echo "  clean         - Clean old webhook events"
        echo "  stripe-cli    - Start Stripe CLI webhook forwarding"
        echo "  trigger       - Trigger test webhook events"
        echo "  test-endpoint - Test webhook endpoint directly"
        echo ""
        echo "Examples:"
        echo "  $0 config"
        echo "  $0 events"
        echo "  $0 stripe-cli"
        exit 1
        ;;
esac