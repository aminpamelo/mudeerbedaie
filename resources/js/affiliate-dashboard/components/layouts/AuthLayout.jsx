import React from 'react';

export default function AuthLayout({ children }) {
    return (
        <div className="min-h-screen flex flex-col items-center justify-center bg-gray-50 px-4 py-12">
            <div className="w-full max-w-sm">
                <div className="text-center mb-8">
                    <img
                        src="/images/bedaie-brand.png"
                        alt="BeDaie"
                        className="h-14 mx-auto mb-4"
                    />
                    <p className="mt-1 text-sm text-gray-500">Program Affiliate</p>
                </div>
                <div className="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 sm:p-8">
                    {children}
                </div>
            </div>
        </div>
    );
}
