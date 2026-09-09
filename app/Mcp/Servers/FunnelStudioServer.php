<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AddFunnelProductTool;
use App\Mcp\Tools\AddFunnelStepTool;
use App\Mcp\Tools\ConfigureAffiliatesTool;
use App\Mcp\Tools\ConfigurePaymentTool;
use App\Mcp\Tools\ConfigureTrackingTool;
use App\Mcp\Tools\CreateFunnelAutomationTool;
use App\Mcp\Tools\CreateLandingPageTool;
use App\Mcp\Tools\DailyReportTool;
use App\Mcp\Tools\DeleteFunnelAutomationTool;
use App\Mcp\Tools\DeleteFunnelStepTool;
use App\Mcp\Tools\FacebookAdsInsightsTool;
use App\Mcp\Tools\FunnelAnalyticsTool;
use App\Mcp\Tools\FunnelOrdersTool;
use App\Mcp\Tools\GetFunnelEmbedTool;
use App\Mcp\Tools\ListFunnelAffiliatesTool;
use App\Mcp\Tools\ListFunnelAutomationsTool;
use App\Mcp\Tools\ListFunnelProductsTool;
use App\Mcp\Tools\ListFunnelStepsTool;
use App\Mcp\Tools\ListFunnelsTool;
use App\Mcp\Tools\ListProductsTool;
use App\Mcp\Tools\PublishFunnelTool;
use App\Mcp\Tools\RemoveFunnelProductTool;
use App\Mcp\Tools\ToggleFunnelAutomationTool;
use App\Mcp\Tools\UpdateFunnelProductTool;
use App\Mcp\Tools\UpdateFunnelSettingsTool;
use App\Mcp\Tools\UpdateFunnelStepTool;
use App\Mcp\Tools\UpdateLandingPageTool;
use Laravel\Mcp\Server;

class FunnelStudioServer extends Server
{
    /**
     * The MCP server's name.
     */
    protected string $name = 'Funnel Studio';

    /**
     * The MCP server's version.
     */
    protected string $version = '2.0.0';

    /**
     * The MCP server's instructions for the LLM.
     */
    protected string $instructions = <<<'MARKDOWN'
        This server connects an AI assistant to the marketer's Funnel Studio
        account. Every action runs as the authenticated marketer and only ever
        touches funnels, products, and ad accounts they may access.

        Reporting:
          - facebook_ads_insights, daily_report — ad spend, ROAS, daily P&L.
          - list_funnels, funnel_analytics, funnel_orders — funnels, per-funnel
            traffic/conversions, and orders.
          - list_products, list_funnel_products, list_funnel_steps,
            list_funnel_automations, list_funnel_affiliates — inspect a funnel.

        Building & managing a funnel (each mirrors a tab in Funnel Studio):
          - create_landing_page — a whole single-page selling funnel from HTML.
          - Steps: add_funnel_step, update_funnel_step, delete_funnel_step,
            update_landing_page (a step's HTML).
          - Products: add_funnel_product (assign a product to a step's checkout),
            update_funnel_product, remove_funnel_product.
          - Payment: configure_payment (Stripe / Bayarcash FPX / COD).
          - Tracking: configure_tracking (Facebook Pixel, Google GA4/Ads).
          - Settings: update_funnel_settings (name, slug, description, SEO).
          - Automations: create_funnel_automation, toggle_funnel_automation,
            delete_funnel_automation.
          - Affiliates: configure_affiliates (enable + commission).
          - Embed: get_funnel_embed.
          - publish_funnel — take a funnel live or offline.

        When writing landing-page HTML, put the literal tag [checkout_form]
        where the payment form should appear; the platform turns it into a real
        working checkout bound to the step's products.

        All money is in Malaysian Ringgit (RM); spend_with_sst adds 8% SST; ROAS
        = sales / pre-tax spend. Creating/publishing pages and taking payment are
        real actions on live customers — confirm the offer and price with the
        marketer before publishing.
    MARKDOWN;

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        // Reporting
        FacebookAdsInsightsTool::class,
        DailyReportTool::class,
        ListFunnelsTool::class,
        ListProductsTool::class,
        FunnelAnalyticsTool::class,
        FunnelOrdersTool::class,
        // Create
        CreateLandingPageTool::class,
        UpdateLandingPageTool::class,
        PublishFunnelTool::class,
        // Steps
        ListFunnelStepsTool::class,
        AddFunnelStepTool::class,
        UpdateFunnelStepTool::class,
        DeleteFunnelStepTool::class,
        // Products
        ListFunnelProductsTool::class,
        AddFunnelProductTool::class,
        UpdateFunnelProductTool::class,
        RemoveFunnelProductTool::class,
        // Payment / Tracking / Settings
        ConfigurePaymentTool::class,
        ConfigureTrackingTool::class,
        UpdateFunnelSettingsTool::class,
        // Automations
        ListFunnelAutomationsTool::class,
        CreateFunnelAutomationTool::class,
        ToggleFunnelAutomationTool::class,
        DeleteFunnelAutomationTool::class,
        // Affiliates / Embed
        ConfigureAffiliatesTool::class,
        ListFunnelAffiliatesTool::class,
        GetFunnelEmbedTool::class,
    ];

    /**
     * The resources registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Resource>>
     */
    protected array $resources = [
        //
    ];

    /**
     * The prompts registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Prompt>>
     */
    protected array $prompts = [
        //
    ];
}
