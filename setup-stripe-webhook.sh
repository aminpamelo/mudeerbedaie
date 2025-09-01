#!/bin/bash

# Stripe CLI Webhook Setup Script
# This script helps configure Stripe CLI for local webhook testing

echo "🎯 Stripe CLI Webhook Setup"
echo "============================"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Check if Stripe CLI is installed
check_stripe_cli() {
    print_status "Checking Stripe CLI installation..."
    
    if command -v stripe &> /dev/null; then
        STRIPE_VERSION=$(stripe --version)
        print_success "Stripe CLI is installed: $STRIPE_VERSION"
        return 0
    else
        print_error "Stripe CLI is not installed!"
        echo ""
        echo "Please install Stripe CLI first:"
        echo "  macOS: brew install stripe/stripe-cli/stripe"
        echo "  Other: https://stripe.com/docs/stripe-cli#install"
        echo ""
        return 1
    fi
}

# Check if user is logged in to Stripe
check_stripe_auth() {
    print_status "Checking Stripe authentication..."
    
    if stripe config --list &> /dev/null; then
        print_success "You are logged in to Stripe"
        return 0
    else
        print_warning "You need to login to Stripe"
        echo ""
        read -p "Do you want to login now? (y/n): " -n 1 -r
        echo
        
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            print_status "Opening Stripe login..."
            stripe login
            
            if [ $? -eq 0 ]; then
                print_success "Successfully logged in to Stripe!"
                return 0
            else
                print_error "Failed to login to Stripe"
                return 1
            fi
        else
            print_error "Stripe login is required for webhook testing"
            return 1
        fi
    fi
}

# Check if Laravel server is running
check_laravel_server() {
    print_status "Checking Laravel server..."
    
    if curl -s -I http://localhost:8000/stripe/webhook &> /dev/null; then
        print_success "Laravel server is running on localhost:8000"
        return 0
    else
        print_warning "Laravel server is not running on localhost:8000"
        echo ""
        echo "Please start your Laravel server first:"
        echo "  php artisan serve"
        echo "  OR: composer run dev"
        echo ""
        read -p "Press Enter when your server is running..."
        return 0
    fi
}

# Start webhook forwarding
start_webhook_forwarding() {
    print_status "Starting Stripe webhook forwarding..."
    echo ""
    print_warning "IMPORTANT: Keep this terminal open while testing!"
    print_warning "The webhook secret will be displayed below - copy it!"
    echo ""
    echo "After copying the webhook secret:"
    echo "1. Go to: http://localhost:8000/admin/settings/payment"
    echo "2. Paste the secret in 'Stripe Webhook Secret' field"
    echo "3. Save the settings"
    echo ""
    echo "Press Ctrl+C to stop webhook forwarding"
    echo ""
    
    # Start the webhook forwarding
    stripe listen --forward-to localhost:8000/stripe/webhook
}

# Test webhook events (to be run after setup)
test_webhook_events() {
    print_status "Testing webhook events..."
    
    echo ""
    echo "Triggering test events:"
    
    print_status "1. Triggering customer.updated..."
    stripe trigger customer.updated
    sleep 2
    
    print_status "2. Triggering invoice.payment_succeeded..."
    stripe trigger invoice.payment_succeeded  
    sleep 2
    
    print_status "3. Triggering customer.subscription.created..."
    stripe trigger customer.subscription.created
    sleep 2
    
    echo ""
    print_success "Test events triggered!"
    echo ""
    echo "Check your webhook events with:"
    echo "  php artisan webhook:test events"
    echo "  php artisan webhook:test status"
}

# Main setup flow
main() {
    echo ""
    
    # Check prerequisites
    if ! check_stripe_cli; then
        exit 1
    fi
    
    if ! check_stripe_auth; then
        exit 1
    fi
    
    check_laravel_server
    
    echo ""
    echo "🚀 Ready to start webhook forwarding!"
    echo ""
    echo "What would you like to do?"
    echo "1. Start webhook forwarding (get webhook secret)"
    echo "2. Test webhook events (run after configuring secret)"
    echo "3. Check webhook status"
    echo "4. Exit"
    echo ""
    
    read -p "Enter your choice (1-4): " -n 1 -r
    echo ""
    
    case $REPLY in
        1)
            echo ""
            start_webhook_forwarding
            ;;
        2)
            echo ""
            test_webhook_events
            ;;
        3)
            echo ""
            print_status "Checking webhook configuration..."
            php artisan webhook:test config
            ;;
        4)
            print_success "Goodbye!"
            exit 0
            ;;
        *)
            print_error "Invalid choice"
            exit 1
            ;;
    esac
}

# Handle script arguments
case "$1" in
    "test")
        if ! check_stripe_cli; then
            exit 1
        fi
        test_webhook_events
        ;;
    "status")
        php artisan webhook:test config
        ;;
    "forward")
        if ! check_stripe_cli || ! check_stripe_auth; then
            exit 1
        fi
        start_webhook_forwarding
        ;;
    *)
        main
        ;;
esac