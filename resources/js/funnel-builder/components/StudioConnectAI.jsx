/**
 * Studio Connect AI — instructions for connecting an AI assistant (Claude /
 * ChatGPT) to the Funnel Studio MCP server. The server is protected by OAuth,
 * so there are no tokens to generate: the marketer adds the server URL as a
 * custom connector, clicks Connect, and signs in to Kelasify to approve.
 */

import React, { useState } from 'react';

function CopyButton({ value, label = 'Copy' }) {
    const [copied, setCopied] = useState(false);
    const copy = async () => {
        try {
            await navigator.clipboard.writeText(value);
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        } catch (e) {
            // Clipboard blocked — user can select manually.
        }
    };
    return (
        <button
            onClick={copy}
            className="shrink-0 rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 transition-colors hover:bg-gray-50 cursor-pointer"
        >
            {copied ? 'Copied!' : label}
        </button>
    );
}

export default function StudioConnectAI() {
    const serverUrl = `${window.location.origin}/mcp/funnel-studio`;

    return (
        <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
            <div className="mb-6">
                <h1 className="fs-display text-2xl font-bold tracking-tight text-gray-900">
                    Connect <span className="fs-gradient-text">AI</span>
                </h1>
                <p className="mt-0.5 text-[13px] text-gray-500">
                    Run Funnel Studio from Claude or ChatGPT — pull ad data, build landing pages, and publish, without
                    leaving your AI.
                </p>
            </div>

            <div className="space-y-6">
                {/* Server URL */}
                <div className="rounded-lg border border-gray-200 bg-white p-6">
                    <h3 className="mb-1 text-lg font-semibold text-gray-900">Your MCP server</h3>
                    <p className="mb-4 text-xs text-gray-500">
                        Add this as a custom connector in your AI, then click Connect and sign in to Kelasify to approve.
                        No tokens or API keys to copy. Works with Claude and ChatGPT.
                    </p>
                    <div className="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 p-2">
                        <code className="flex-1 truncate px-2 font-mono text-sm text-gray-800">{serverUrl}</code>
                        <CopyButton value={serverUrl} label="Copy URL" />
                    </div>
                </div>

                {/* How to connect */}
                <div className="rounded-lg border border-gray-200 bg-white p-6">
                    <h3 className="mb-4 text-lg font-semibold text-gray-900">How to connect</h3>
                    <div className="grid gap-6 sm:grid-cols-2">
                        <div>
                            <h4 className="mb-2 text-sm font-semibold text-gray-900">Claude</h4>
                            <ol className="list-decimal space-y-1.5 pl-4 text-[13px] text-gray-600">
                                <li>Settings → Connectors → Add custom connector.</li>
                                <li>Paste the MCP server URL above.</li>
                                <li>Leave Authentication on <span className="font-medium">"Always required"</span> (OAuth) — it's auto-detected.</li>
                                <li>Click Connect → sign in to Kelasify → Approve.</li>
                                <li>Ask Claude: "Show my Facebook ad spend this week."</li>
                            </ol>
                        </div>
                        <div>
                            <h4 className="mb-2 text-sm font-semibold text-gray-900">ChatGPT</h4>
                            <ol className="list-decimal space-y-1.5 pl-4 text-[13px] text-gray-600">
                                <li>Settings → Connectors → Add a custom MCP server.</li>
                                <li>Paste the MCP server URL above.</li>
                                <li>Keep Authentication on <span className="font-medium">OAuth</span>.</li>
                                <li>Create it → sign in to Kelasify → Approve.</li>
                                <li>Ask it to build and publish a landing page for your offer.</li>
                            </ol>
                        </div>
                    </div>
                    <p className="mt-4 rounded-lg bg-blue-50 px-3 py-2 text-xs text-blue-800">
                        You sign in with your own Kelasify account, so the AI only ever sees and changes the funnels and
                        ad accounts you already have access to. You can disconnect anytime from your AI's connector
                        settings.
                    </p>
                </div>

                {/* What it can do */}
                <div className="rounded-lg border border-gray-200 bg-white p-6">
                    <h3 className="mb-3 text-lg font-semibold text-gray-900">What your AI can do</h3>
                    <ul className="grid gap-2 text-[13px] text-gray-600 sm:grid-cols-2">
                        <li>• Review Facebook ad spend, ROAS, and your daily profit report.</li>
                        <li>• List your funnels, their sales, and the products you can sell.</li>
                        <li>• Build a landing page with a working checkout from a price + HTML.</li>
                        <li>• Update a page and publish it live — or take it offline.</li>
                    </ul>
                </div>
            </div>
        </div>
    );
}
