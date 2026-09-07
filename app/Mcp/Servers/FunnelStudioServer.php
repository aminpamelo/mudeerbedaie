<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateLandingPageTool;
use App\Mcp\Tools\DailyReportTool;
use App\Mcp\Tools\FacebookAdsInsightsTool;
use App\Mcp\Tools\ListFunnelsTool;
use App\Mcp\Tools\ListProductsTool;
use App\Mcp\Tools\PublishFunnelTool;
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
    protected string $version = '1.0.0';

    /**
     * The MCP server's instructions for the LLM.
     */
    protected string $instructions = <<<'MARKDOWN'
        This server connects an AI assistant to the marketer's Funnel Studio
        account. Every action runs as the authenticated marketer and only ever
        touches funnels and ad accounts they are allowed to see.

        Use it to help the marketer:
          - Review Facebook Ads performance (facebook_ads_insights).
          - See their day-by-day spend-vs-sales profit report (daily_report).
          - List their sales funnels and 30-day sales (list_funnels).
          - List catalog products they can sell (list_products).
          - Build and publish a selling landing page (create_landing_page),
            edit it (update_landing_page), and take it live/offline
            (publish_funnel).

        A typical flow: review ad performance -> pick a product with
        list_products (or use a custom offer) -> write the landing page HTML and
        call create_landing_page with a price -> publish. When writing landing
        page HTML, put the literal tag [checkout_form] where the payment form
        should appear; the platform turns it into a real working checkout.

        All money figures are in Malaysian Ringgit (RM). "spend_with_sst" is ad
        spend grossed up by 8% Malaysian Service Tax. ROAS is sales divided by
        raw (pre-tax) spend; net is sales minus spend+SST. Creating and
        publishing pages takes real payment from real customers, so confirm the
        price and offer with the marketer before publishing.
    MARKDOWN;

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        FacebookAdsInsightsTool::class,
        DailyReportTool::class,
        ListFunnelsTool::class,
        ListProductsTool::class,
        CreateLandingPageTool::class,
        UpdateLandingPageTool::class,
        PublishFunnelTool::class,
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
