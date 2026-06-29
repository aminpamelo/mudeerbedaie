import { Component } from 'react';
import { AlertTriangle, RefreshCw, Home } from 'lucide-react';

/**
 * Catches uncaught React errors in any child component tree so the SPA
 * can stay alive instead of showing a white screen.
 */
export class ErrorBoundary extends Component {
    constructor(props) {
        super(props);
        this.state = { hasError: false, error: null };
    }

    static getDerivedStateFromError(error) {
        return { hasError: true, error };
    }

    componentDidCatch(error, info) {
        if (typeof window !== 'undefined' && window.console) {
            // eslint-disable-next-line no-console
            console.error('HR ErrorBoundary caught:', error, info);
        }
    }

    render() {
        if (!this.state.hasError) return this.props.children;

        const rawMessage = this.state.error?.message || 'Something unexpected happened.';
        const isStaleChunk = /dynamically imported module|importing a module script failed|ChunkLoadError/i.test(rawMessage);

        const heading = isStaleChunk ? 'A new version is available' : 'Something went wrong';
        const message = isStaleChunk
            ? 'The app was updated since this tab was opened. Reload to load the latest version.'
            : rawMessage;

        return (
            <div className="flex min-h-[60vh] flex-col items-center justify-center px-6 py-12 text-center">
                <div className="relative mb-5 h-24 w-24">
                    <div className="absolute inset-0 rounded-full bg-gradient-to-br from-rose-400 to-orange-400 opacity-20 blur-2xl" />
                    <div className="relative flex h-full w-full items-center justify-center rounded-full bg-gradient-to-br from-rose-100 to-rose-50 ring-1 ring-rose-200">
                        <AlertTriangle className="h-12 w-12 text-rose-500" strokeWidth={1.75} />
                    </div>
                </div>
                <h1 className="text-xl font-bold tracking-tight text-slate-900">{heading}</h1>
                <p className="mt-2 max-w-md text-sm text-slate-600">{message}</p>
                {!isStaleChunk && (
                    <p className="mt-1 text-xs text-slate-400">The error has been logged.</p>
                )}

                <div className="mt-6 flex flex-wrap items-center justify-center gap-2">
                    <button
                        onClick={() => window.location.reload()}
                        className="inline-flex items-center gap-1.5 rounded-full bg-gradient-to-r from-indigo-500 via-pink-500 to-orange-400 px-4 py-2 text-xs font-bold uppercase tracking-wider text-white shadow-md shadow-pink-500/30 transition-all hover:shadow-lg"
                    >
                        <RefreshCw className="h-3.5 w-3.5" strokeWidth={2.5} />
                        Reload page
                    </button>
                    <button
                        onClick={() => { window.location.href = '/hr'; }}
                        className="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-bold uppercase tracking-wider text-slate-700 shadow-sm transition-all hover:border-indigo-200 hover:text-indigo-700"
                    >
                        <Home className="h-3.5 w-3.5" strokeWidth={2.5} />
                        Go home
                    </button>
                </div>
            </div>
        );
    }
}
