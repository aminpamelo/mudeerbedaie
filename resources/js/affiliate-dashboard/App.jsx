import React, { useState, useEffect, useCallback } from 'react';
import api from './services/api';
import AuthLayout from './components/layouts/AuthLayout';
import DashboardLayout from './components/layouts/DashboardLayout';
import Login from './pages/Login';
import Register from './pages/Register';
import Dashboard from './pages/Dashboard';
import Discover from './pages/Discover';
import FunnelDetail from './pages/FunnelDetail';
import Leaderboard from './pages/Leaderboard';
import Profile from './pages/Profile';

const parseRoute = (pathname) => {
    if (pathname === '/affiliate/login') return { page: 'login' };
    if (pathname === '/affiliate/register') return { page: 'register' };
    if (pathname === '/affiliate/dashboard') return { page: 'dashboard' };
    if (pathname === '/affiliate/discover') return { page: 'discover' };
    if (pathname === '/affiliate/leaderboard') return { page: 'leaderboard' };
    if (pathname === '/affiliate/profile') return { page: 'profile' };

    const funnelMatch = pathname.match(/^\/affiliate\/funnels\/(\d+)$/);
    if (funnelMatch) {
        return { page: 'funnel-detail', params: { id: funnelMatch[1] } };
    }

    return { page: 'dashboard' };
};

export default function App() {
    const [user, setUser] = useState(null);
    const [authChecked, setAuthChecked] = useState(false);
    const [route, setRoute] = useState(() => parseRoute(window.location.pathname));

    const navigate = useCallback((path) => {
        window.history.pushState({}, '', path);
        setRoute(parseRoute(path));
    }, []);

    useEffect(() => {
        const onPopState = () => {
            setRoute(parseRoute(window.location.pathname));
        };
        window.addEventListener('popstate', onPopState);
        return () => window.removeEventListener('popstate', onPopState);
    }, []);

    useEffect(() => {
        api.getMe()
            .then((data) => {
                setUser(data.user || data);
                setAuthChecked(true);
            })
            .catch(() => {
                setUser(null);
                setAuthChecked(true);
            });
    }, []);

    useEffect(() => {
        if (!authChecked) return;

        const authPages = ['login', 'register'];
        const isAuthPage = authPages.includes(route.page);

        if (!user && !isAuthPage) {
            navigate('/affiliate/login');
        } else if (user && isAuthPage) {
            navigate('/affiliate/dashboard');
        }
    }, [authChecked, user, route.page, navigate]);

    const handleLogin = (loggedInUser) => {
        setUser(loggedInUser);
        navigate('/affiliate/dashboard');
    };

    const handleLogout = async () => {
        try {
            await api.logout();
        } catch {
            // ignore
        }
        setUser(null);
        navigate('/affiliate/login');
    };

    if (!authChecked) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-gray-50">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
            </div>
        );
    }

    const authPages = ['login', 'register'];
    if (authPages.includes(route.page)) {
        return (
            <AuthLayout>
                {route.page === 'login' && <Login onLogin={handleLogin} navigate={navigate} />}
                {route.page === 'register' && <Register onLogin={handleLogin} navigate={navigate} />}
            </AuthLayout>
        );
    }

    const renderPage = () => {
        switch (route.page) {
            case 'dashboard':
                return <Dashboard user={user} navigate={navigate} />;
            case 'discover':
                return <Discover navigate={navigate} />;
            case 'funnel-detail':
                return <FunnelDetail funnelId={route.params.id} navigate={navigate} />;
            case 'leaderboard':
                return <Leaderboard />;
            case 'profile':
                return <Profile user={user} setUser={setUser} onLogout={handleLogout} />;
            default:
                return <Dashboard user={user} navigate={navigate} />;
        }
    };

    return (
        <DashboardLayout route={route} navigate={navigate}>
            {renderPage()}
        </DashboardLayout>
    );
}
