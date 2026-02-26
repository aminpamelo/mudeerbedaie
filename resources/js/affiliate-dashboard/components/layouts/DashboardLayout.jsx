import React from 'react';
import TopBar from '../TopBar';
import BottomNav from '../BottomNav';

export default function DashboardLayout({ route, navigate, children }) {
    return (
        <div className="min-h-screen bg-gray-50">
            <TopBar />
            <main className="max-w-md mx-auto px-4 pt-4 pb-24">
                {children}
            </main>
            <BottomNav route={route} navigate={navigate} />
        </div>
    );
}
