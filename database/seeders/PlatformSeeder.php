<?php

namespace Database\Seeders;

use App\Models\Platform;
use Illuminate\Database\Seeder;

class PlatformSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $platforms = [
            [
                'name' => 'TikTok Shop',
                'slug' => 'tiktok-shop',
                'display_name' => 'TikTok Shop',
                'description' => 'TikTok\'s e-commerce platform for social commerce and short-form video shopping.',
                'website_url' => 'https://shop.tiktok.com',
                'api_base_url' => 'https://open-api.tiktokglobalshop.com', // For future use
                'logo_url' => 'https://logo.clearbit.com/tiktok.com',
                'color_primary' => '#ff0050',
                'color_secondary' => '#25f4ee',
                'type' => 'social_media',
                'features' => ['manual_import', 'order_management', 'csv_export'],
                'required_credentials' => [
                    'seller_center_id' => 'Seller Center ID',
                    'shop_id' => 'Shop ID',
                    'business_id' => 'Business ID (Optional)',
                ],
                'settings' => [
                    'manual_mode' => true,
                    'api_available' => false,
                    'csv_import_supported' => true,
                    'supported_countries' => ['MY', 'SG', 'TH', 'VN', 'PH', 'ID'],
                    'supported_currencies' => ['MYR', 'SGD', 'THB', 'VND', 'PHP', 'IDR'],
                ],
                'supports_orders' => true,
                'supports_products' => true,
                'supports_webhooks' => false, // Manual mode
                'sort_order' => 1,
            ],
            [
                'name' => 'Facebook Shop',
                'slug' => 'facebook-shop',
                'display_name' => 'Facebook Shop',
                'description' => 'Facebook\'s social commerce platform integrated with Instagram and WhatsApp Business.',
                'website_url' => 'https://business.facebook.com/commerce',
                'api_base_url' => 'https://graph.facebook.com', // For future use
                'logo_url' => 'https://logo.clearbit.com/facebook.com',
                'color_primary' => '#1877f2',
                'color_secondary' => '#42a5f5',
                'type' => 'social_media',
                'features' => ['manual_import', 'order_management', 'catalog_sync'],
                'required_credentials' => [
                    'business_manager_id' => 'Business Manager ID',
                    'catalog_id' => 'Product Catalog ID',
                    'page_id' => 'Facebook Page ID',
                ],
                'settings' => [
                    'manual_mode' => true,
                    'api_available' => false,
                    'csv_import_supported' => true,
                    'supports_instagram' => true,
                    'supports_whatsapp' => true,
                ],
                'supports_orders' => true,
                'supports_products' => true,
                'supports_webhooks' => false,
                'sort_order' => 2,
            ],
            [
                'name' => 'Shopee',
                'slug' => 'shopee',
                'display_name' => 'Shopee',
                'description' => 'Leading e-commerce platform in Southeast Asia and Latin America.',
                'website_url' => 'https://shopee.com',
                'api_base_url' => 'https://partner.shopeemobile.com', // For future use
                'logo_url' => 'https://logo.clearbit.com/shopee.com',
                'color_primary' => '#ee4d2d',
                'color_secondary' => '#f53d2d',
                'type' => 'marketplace',
                'features' => ['manual_import', 'order_management', 'inventory_sync'],
                'required_credentials' => [
                    'shop_id' => 'Shop ID',
                    'seller_id' => 'Seller ID',
                    'partner_id' => 'Partner ID (Optional)',
                ],
                'settings' => [
                    'manual_mode' => true,
                    'api_available' => false,
                    'csv_import_supported' => true,
                    'supported_countries' => ['MY', 'SG', 'TH', 'VN', 'PH', 'ID', 'TW'],
                    'supported_currencies' => ['MYR', 'SGD', 'THB', 'VND', 'PHP', 'IDR', 'TWD'],
                ],
                'supports_orders' => true,
                'supports_products' => true,
                'supports_webhooks' => false,
                'sort_order' => 3,
            ],
            [
                'name' => 'Lazada',
                'slug' => 'lazada',
                'display_name' => 'Lazada',
                'description' => 'Major e-commerce platform in Southeast Asia, owned by Alibaba Group.',
                'website_url' => 'https://lazada.com',
                'api_base_url' => 'https://api.lazada.com', // For future use
                'logo_url' => 'https://logo.clearbit.com/lazada.com',
                'color_primary' => '#0f156d',
                'color_secondary' => '#f36800',
                'type' => 'marketplace',
                'features' => ['manual_import', 'order_management'],
                'required_credentials' => [
                    'seller_id' => 'Seller ID',
                    'store_id' => 'Store ID',
                    'app_key' => 'App Key (For API)',
                ],
                'settings' => [
                    'manual_mode' => true,
                    'api_available' => false,
                    'csv_import_supported' => true,
                    'supported_countries' => ['MY', 'SG', 'TH', 'VN', 'PH', 'ID'],
                ],
                'supports_orders' => true,
                'supports_products' => true,
                'supports_webhooks' => false,
                'sort_order' => 4,
            ],
            [
                'name' => 'Amazon',
                'slug' => 'amazon',
                'display_name' => 'Amazon',
                'description' => 'Global e-commerce marketplace and cloud computing platform.',
                'website_url' => 'https://amazon.com',
                'api_base_url' => 'https://sellingpartnerapi-na.amazon.com', // For future use
                'logo_url' => 'https://logo.clearbit.com/amazon.com',
                'color_primary' => '#ff9900',
                'color_secondary' => '#146eb4',
                'type' => 'marketplace',
                'features' => ['manual_import', 'order_management', 'fba_integration'],
                'required_credentials' => [
                    'seller_id' => 'Seller ID',
                    'marketplace_id' => 'Marketplace ID',
                    'mws_auth_token' => 'MWS Auth Token',
                ],
                'settings' => [
                    'manual_mode' => true,
                    'api_available' => false,
                    'csv_import_supported' => true,
                    'supports_fba' => true,
                    'global_platform' => true,
                ],
                'supports_orders' => true,
                'supports_products' => true,
                'supports_webhooks' => false,
                'sort_order' => 5,
            ],
            [
                'name' => 'Custom Platform',
                'slug' => 'custom-platform',
                'display_name' => 'Custom Platform',
                'description' => 'Generic template for adding custom e-commerce platforms and marketplaces.',
                'website_url' => null,
                'api_base_url' => null,
                'logo_url' => null,
                'color_primary' => '#6b7280',
                'color_secondary' => '#9ca3af',
                'type' => 'custom',
                'features' => ['manual_import', 'order_management', 'customizable'],
                'required_credentials' => [
                    'platform_id' => 'Platform Account ID',
                    'store_name' => 'Store/Shop Name',
                    'contact_email' => 'Contact Email',
                ],
                'settings' => [
                    'manual_mode' => true,
                    'api_available' => false,
                    'csv_import_supported' => true,
                    'customizable' => true,
                    'template_platform' => true,
                ],
                'supports_orders' => true,
                'supports_products' => true,
                'supports_webhooks' => false,
                'sort_order' => 99,
            ],
        ];

        foreach ($platforms as $platformData) {
            Platform::updateOrCreate(
                ['slug' => $platformData['slug']],
                $platformData
            );
        }
    }
}
