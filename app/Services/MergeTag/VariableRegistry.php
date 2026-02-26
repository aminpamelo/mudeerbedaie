<?php

declare(strict_types=1);

namespace App\Services\MergeTag;

class VariableRegistry
{
    /**
     * Get available variables for a specific trigger type.
     */
    public static function getVariablesForTrigger(string $triggerType): array
    {
        $baseVariables = array_merge(
            self::getSystemVariables(),
            self::getContactVariables()
        );

        return match ($triggerType) {
            'purchase_completed', 'funnel_purchase_completed', 'order_paid' => array_merge(
                $baseVariables,
                self::getOrderVariables(),
                self::getPaymentVariables(),
                self::getFunnelVariables(),
                self::getSessionVariables(),
            ),
            'purchase_failed', 'funnel_purchase_failed', 'order_failed' => array_merge(
                $baseVariables,
                self::getOrderVariables(),
                self::getFunnelVariables(),
                self::getSessionVariables(),
            ),
            'cart_abandoned', 'funnel_cart_abandoned', 'cart_abandonment' => array_merge(
                $baseVariables,
                self::getCartVariables(),
                self::getFunnelVariables(),
                self::getSessionVariables(),
            ),
            'optin_submitted', 'form_submitted', 'page_view' => array_merge(
                $baseVariables,
                self::getFunnelVariables(),
                self::getSessionVariables(),
            ),
            default => $baseVariables,
        };
    }

    /**
     * Get all available variables (for documentation/display).
     */
    public static function getAllVariables(): array
    {
        return array_merge(
            self::getSystemVariables(),
            self::getContactVariables(),
            self::getOrderVariables(),
            self::getPaymentVariables(),
            self::getCartVariables(),
            self::getFunnelVariables(),
            self::getSessionVariables(),
        );
    }

    /**
     * Get example value for a variable.
     */
    public static function getExampleValue(string $variable): ?string
    {
        $allVariables = self::getAllVariables();

        foreach ($allVariables as $category => $data) {
            if (isset($data['variables'][$variable]['example'])) {
                return $data['variables'][$variable]['example'];
            }
        }

        // Check system variables without category prefix
        if (isset($allVariables['system']['variables'][$variable]['example'])) {
            return $allVariables['system']['variables'][$variable]['example'];
        }

        return null;
    }

    /**
     * Contact/Customer variables.
     */
    protected static function getContactVariables(): array
    {
        return [
            'contact' => [
                'label' => 'Contact',
                'icon' => 'user',
                'description' => 'Customer/Contact information',
                'variables' => [
                    'contact.name' => [
                        'label' => 'Full Name',
                        'example' => 'John Doe',
                        'description' => 'Customer\'s full name',
                    ],
                    'contact.first_name' => [
                        'label' => 'First Name',
                        'example' => 'John',
                        'description' => 'Customer\'s first name',
                    ],
                    'contact.last_name' => [
                        'label' => 'Last Name',
                        'example' => 'Doe',
                        'description' => 'Customer\'s last name',
                    ],
                    'contact.email' => [
                        'label' => 'Email',
                        'example' => 'john@example.com',
                        'description' => 'Customer\'s email address',
                    ],
                    'contact.phone' => [
                        'label' => 'Phone',
                        'example' => '+60123456789',
                        'description' => 'Customer\'s phone number',
                    ],
                ],
            ],
        ];
    }

    /**
     * Order variables.
     */
    protected static function getOrderVariables(): array
    {
        return [
            'order' => [
                'label' => 'Order',
                'icon' => 'shopping-cart',
                'description' => 'Order details and items',
                'variables' => [
                    'order.number' => [
                        'label' => 'Order Number',
                        'example' => 'PO-20260126-ABC123',
                        'description' => 'Unique order reference number',
                    ],
                    'order.total' => [
                        'label' => 'Total Amount',
                        'example' => 'RM 299.00',
                        'description' => 'Order total with currency',
                    ],
                    'order.total_raw' => [
                        'label' => 'Total (Number Only)',
                        'example' => '299.00',
                        'description' => 'Order total without currency',
                    ],
                    'order.subtotal' => [
                        'label' => 'Subtotal',
                        'example' => 'RM 279.00',
                        'description' => 'Subtotal before discounts',
                    ],
                    'order.currency' => [
                        'label' => 'Currency',
                        'example' => 'MYR',
                        'description' => 'Currency code',
                    ],
                    'order.status' => [
                        'label' => 'Status',
                        'example' => 'confirmed',
                        'description' => 'Order status',
                    ],
                    'order.items_count' => [
                        'label' => 'Items Count',
                        'example' => '3',
                        'description' => 'Number of items in order',
                    ],
                    'order.items_list' => [
                        'label' => 'Items List',
                        'example' => "- Product A (x1)\n- Product B (x2)",
                        'description' => 'Formatted list of ordered items',
                    ],
                    'order.first_item_name' => [
                        'label' => 'First Item Name',
                        'example' => 'Premium Course',
                        'description' => 'Name of the first ordered item',
                    ],
                    'order.discount_amount' => [
                        'label' => 'Discount Amount',
                        'example' => 'RM 20.00',
                        'description' => 'Total discount applied',
                    ],
                    'order.coupon_code' => [
                        'label' => 'Coupon Code',
                        'example' => 'SAVE20',
                        'description' => 'Applied coupon code',
                    ],
                    'order.date' => [
                        'label' => 'Order Date',
                        'example' => '26 Jan 2026',
                        'description' => 'Date order was placed',
                    ],
                    'order.shipping_address' => [
                        'label' => 'Shipping Address',
                        'example' => '123 Main St, City',
                        'description' => 'Formatted shipping address',
                    ],
                    'order.billing_address' => [
                        'label' => 'Billing Address',
                        'example' => '123 Main St, City',
                        'description' => 'Formatted billing address',
                    ],
                ],
            ],
        ];
    }

    /**
     * Payment variables.
     */
    protected static function getPaymentVariables(): array
    {
        return [
            'payment' => [
                'label' => 'Payment',
                'icon' => 'credit-card',
                'description' => 'Payment transaction details',
                'variables' => [
                    'payment.method' => [
                        'label' => 'Payment Method',
                        'example' => 'FPX',
                        'description' => 'Payment method used',
                    ],
                    'payment.reference' => [
                        'label' => 'Payment Reference',
                        'example' => 'BC-123456',
                        'description' => 'Payment gateway reference',
                    ],
                    'payment.status' => [
                        'label' => 'Payment Status',
                        'example' => 'completed',
                        'description' => 'Current payment status',
                    ],
                    'payment.paid_at' => [
                        'label' => 'Payment Date',
                        'example' => '26 Jan 2026, 10:30 AM',
                        'description' => 'Date and time of payment',
                    ],
                    'payment.bank' => [
                        'label' => 'Bank Name',
                        'example' => 'Maybank',
                        'description' => 'Bank used for payment (FPX)',
                    ],
                ],
            ],
        ];
    }

    /**
     * Cart variables.
     */
    protected static function getCartVariables(): array
    {
        return [
            'cart' => [
                'label' => 'Cart',
                'icon' => 'shopping-bag',
                'description' => 'Shopping cart details',
                'variables' => [
                    'cart.total' => [
                        'label' => 'Cart Total',
                        'example' => 'RM 199.00',
                        'description' => 'Current cart total',
                    ],
                    'cart.items_count' => [
                        'label' => 'Cart Items Count',
                        'example' => '2',
                        'description' => 'Number of items in cart',
                    ],
                    'cart.items_list' => [
                        'label' => 'Cart Items List',
                        'example' => "- Product A\n- Product B",
                        'description' => 'List of items in cart',
                    ],
                    'cart.first_item_name' => [
                        'label' => 'First Cart Item',
                        'example' => 'Premium Course',
                        'description' => 'Name of first item in cart',
                    ],
                    'cart.checkout_url' => [
                        'label' => 'Checkout URL',
                        'example' => 'https://example.com/checkout/abc123',
                        'description' => 'URL to resume checkout',
                    ],
                    'cart.abandoned_at' => [
                        'label' => 'Abandoned Time',
                        'example' => '2 hours ago',
                        'description' => 'When cart was abandoned',
                    ],
                ],
            ],
        ];
    }

    /**
     * Funnel variables.
     */
    protected static function getFunnelVariables(): array
    {
        return [
            'funnel' => [
                'label' => 'Funnel',
                'icon' => 'filter',
                'description' => 'Funnel and step information',
                'variables' => [
                    'funnel.name' => [
                        'label' => 'Funnel Name',
                        'example' => 'Product Launch Funnel',
                        'description' => 'Name of the funnel',
                    ],
                    'funnel.url' => [
                        'label' => 'Funnel URL',
                        'example' => 'https://example.com/f/launch',
                        'description' => 'Public URL of the funnel',
                    ],
                    'funnel.step_name' => [
                        'label' => 'Step Name',
                        'example' => 'Checkout',
                        'description' => 'Current funnel step name',
                    ],
                    'funnel.step_url' => [
                        'label' => 'Step URL',
                        'example' => 'https://example.com/f/launch/checkout',
                        'description' => 'URL of the current step',
                    ],
                ],
            ],
        ];
    }

    /**
     * Session/UTM variables.
     */
    protected static function getSessionVariables(): array
    {
        return [
            'session' => [
                'label' => 'Session',
                'icon' => 'globe',
                'description' => 'Session and tracking data',
                'variables' => [
                    'session.utm_source' => [
                        'label' => 'UTM Source',
                        'example' => 'facebook',
                        'description' => 'Traffic source (utm_source)',
                    ],
                    'session.utm_medium' => [
                        'label' => 'UTM Medium',
                        'example' => 'cpc',
                        'description' => 'Traffic medium (utm_medium)',
                    ],
                    'session.utm_campaign' => [
                        'label' => 'UTM Campaign',
                        'example' => 'summer_sale',
                        'description' => 'Campaign name (utm_campaign)',
                    ],
                    'session.utm_content' => [
                        'label' => 'UTM Content',
                        'example' => 'banner_ad',
                        'description' => 'Ad content (utm_content)',
                    ],
                    'session.utm_term' => [
                        'label' => 'UTM Term',
                        'example' => 'buy+course',
                        'description' => 'Search term (utm_term)',
                    ],
                    'session.device' => [
                        'label' => 'Device Type',
                        'example' => 'mobile',
                        'description' => 'Device type (mobile/desktop/tablet)',
                    ],
                    'session.browser' => [
                        'label' => 'Browser',
                        'example' => 'Chrome',
                        'description' => 'Browser name',
                    ],
                    'session.country' => [
                        'label' => 'Country',
                        'example' => 'MY',
                        'description' => 'Country code',
                    ],
                    'session.referrer' => [
                        'label' => 'Referrer',
                        'example' => 'google.com',
                        'description' => 'Referring website',
                    ],
                ],
            ],
        ];
    }

    /**
     * System variables.
     */
    protected static function getSystemVariables(): array
    {
        return [
            'system' => [
                'label' => 'System',
                'icon' => 'cog',
                'description' => 'Date, time and system info',
                'variables' => [
                    'current_date' => [
                        'label' => 'Current Date',
                        'example' => '26 Jan 2026',
                        'description' => 'Today\'s date',
                    ],
                    'current_time' => [
                        'label' => 'Current Time',
                        'example' => '10:30 AM',
                        'description' => 'Current time',
                    ],
                    'current_datetime' => [
                        'label' => 'Current Date & Time',
                        'example' => '26 Jan 2026, 10:30 AM',
                        'description' => 'Current date and time',
                    ],
                    'current_year' => [
                        'label' => 'Current Year',
                        'example' => '2026',
                        'description' => 'Current year',
                    ],
                    'current_month' => [
                        'label' => 'Current Month',
                        'example' => 'January',
                        'description' => 'Current month name',
                    ],
                    'current_day' => [
                        'label' => 'Current Day',
                        'example' => 'Monday',
                        'description' => 'Current day of week',
                    ],
                    'company_name' => [
                        'label' => 'Company Name',
                        'example' => 'Your Company',
                        'description' => 'Your company/business name',
                    ],
                    'company_email' => [
                        'label' => 'Company Email',
                        'example' => 'support@example.com',
                        'description' => 'Company contact email',
                    ],
                    'company_phone' => [
                        'label' => 'Company Phone',
                        'example' => '+60123456789',
                        'description' => 'Company contact phone',
                    ],
                ],
            ],
        ];
    }
}
