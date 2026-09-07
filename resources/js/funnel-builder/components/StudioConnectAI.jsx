/**
 * Studio Connect AI — lets a marketer connect their AI assistant (Claude /
 * ChatGPT) to the Funnel Studio MCP server: shows the server URL, lets them
 * generate/copy/revoke personal access tokens, and gives paste-in setup steps.
 */

import React, { useState, useEffect, useCallback } from 'react';

const getCsrfToken = () =>
    document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1]
        ?.replace(/%3D/g, '=') || '';

const api = (url, options = {}) =>
    fetch(url, {
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': getCsrfToken() },
        credentials: 'same-origin',
        ...options,
    });

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
    const [serverUrl, setServerUrl] = useState('');
    const [tokens, setTokens] = useState([]);
    const [loading, setLoading] = useState(true);
    const [newName, setNewName] = useState('');
    const [creating, setCreating] = useState(false);
    const [freshToken, setFreshToken] = useState(null);

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const res = await api('/api/v1/studio/mcp-tokens');
            const data = await res.json();
            setServerUrl(data.data?.server_url || `${window.location.origin}/mcp/funnel-studio`);
            setTokens(data.data?.tokens || []);
        } catch (e) {
            setServerUrl(`${window.location.origin}/mcp/funnel-studio`);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        load();
    }, [load]);

    const generate = async () => {
        setCreating(true);
        try {
            const res = await api('/api/v1/studio/mcp-tokens', {
                method: 'POST',
                body: JSON.stringify({ name: newName.trim() || 'AI Connection' }),
            });
            const data = await res.json();
            if (data.data?.plain_text_token) {
                setFreshToken(data.data.plain_text_token);
                setNewName('');
                load();
            }
        } finally {
            setCreating(false);
        }
    };

    const revoke = async (id) => {
        await api(`/api/v1/studio/mcp-tokens/${id}`, { method: 'DELETE' });
        load();
    };

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
                        Add this as a custom connector in your AI, then authenticate with a token below. Works with Claude
                        and ChatGPT.
                    </p>
                    <div className="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 p-2">
                        <code className="flex-1 truncate px-2 font-mono text-sm text-gray-800">{serverUrl}</code>
                        <CopyButton value={serverUrl} label="Copy URL" />
                    </div>
                </div>

                {/* Tokens */}
                <div className="rounded-lg border border-gray-200 bg-white p-6">
                    <div className="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <h3 className="text-lg font-semibold text-gray-900">Access tokens</h3>
                            <p className="text-xs text-gray-500">
                                Each token connects one AI to your account. Revoke a token to disconnect it.
                            </p>
                        </div>
                    </div>

                    {/* Fresh token reveal (shown once) */}
                    {freshToken && (
                        <div className="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                            <p className="mb-2 text-sm font-semibold text-emerald-900">
                                Token created — copy it now. You won't be able to see it again.
                            </p>
                            <div className="flex items-center gap-2 rounded-md border border-emerald-200 bg-white p-2">
                                <code className="flex-1 truncate px-2 font-mono text-xs text-gray-800">{freshToken}</code>
                                <CopyButton value={freshToken} label="Copy token" />
                            </div>
                            <button
                                onClick={() => setFreshToken(null)}
                                className="mt-2 text-xs font-medium text-emerald-800 hover:text-emerald-900 cursor-pointer"
                            >
                                I've copied it — dismiss
                            </button>
                        </div>
                    )}

                    {/* Generate */}
                    <div className="mb-4 flex flex-wrap items-center gap-2">
                        <input
                            type="text"
                            value={newName}
                            onChange={(e) => setNewName(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && generate()}
                            placeholder="Name (e.g. My Claude, ChatGPT work)"
                            className="min-w-[220px] flex-1 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 outline-none focus:border-gray-400"
                        />
                        <button
                            onClick={generate}
                            disabled={creating}
                            className="fs-cta rounded-lg px-4 py-2 text-sm font-medium text-white disabled:opacity-50 cursor-pointer"
                        >
                            {creating ? 'Generating…' : 'Generate token'}
                        </button>
                    </div>

                    {/* Token list */}
                    {loading ? (
                        <p className="py-6 text-center text-sm text-gray-500">Loading…</p>
                    ) : tokens.length === 0 ? (
                        <p className="rounded-lg border border-dashed border-gray-200 py-6 text-center text-sm text-gray-500">
                            No tokens yet. Generate one to connect your AI.
                        </p>
                    ) : (
                        <div className="overflow-hidden rounded-lg border border-gray-200">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="bg-gray-50 text-left text-[11px] uppercase tracking-wider text-gray-500">
                                        <th className="px-4 py-2.5 font-medium">Name</th>
                                        <th className="px-4 py-2.5 font-medium">Last used</th>
                                        <th className="px-4 py-2.5 text-right font-medium">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {tokens.map((t) => (
                                        <tr key={t.id} className="border-t border-gray-100">
                                            <td className="px-4 py-3 font-medium text-gray-900">{t.name}</td>
                                            <td className="px-4 py-3 text-gray-500">{t.last_used_at || 'Never'}</td>
                                            <td className="px-4 py-3 text-right">
                                                <button
                                                    onClick={() => revoke(t.id)}
                                                    className="rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-red-600 transition-colors hover:bg-red-50 cursor-pointer"
                                                >
                                                    Revoke
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
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
                                <li>When asked to authenticate, use a Bearer token — paste a token from above.</li>
                                <li>Ask Claude: "Show my Facebook ad spend this week."</li>
                            </ol>
                        </div>
                        <div>
                            <h4 className="mb-2 text-sm font-semibold text-gray-900">ChatGPT</h4>
                            <ol className="list-decimal space-y-1.5 pl-4 text-[13px] text-gray-600">
                                <li>Settings → Connectors → Add a custom MCP server.</li>
                                <li>Paste the MCP server URL above.</li>
                                <li>Set the Authorization header to <code className="font-mono text-xs">Bearer &lt;token&gt;</code>.</li>
                                <li>Ask it to build and publish a landing page for your offer.</li>
                            </ol>
                        </div>
                    </div>
                    <p className="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                        Keep your token secret — anyone with it can act on your Funnel Studio account. Revoke it here if it
                        leaks.
                    </p>
                </div>
            </div>
        </div>
    );
}
