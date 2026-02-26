import React from 'react';

export default function StatCard({ label, value, icon }) {
    return (
        <div className="bg-white rounded-xl border border-gray-200 p-4">
            <div className="flex items-center justify-between">
                <div>
                    <p className="text-xs text-gray-500 uppercase tracking-wide">{label}</p>
                    <p className="text-xl font-semibold text-gray-900 mt-1">{value}</p>
                </div>
                {icon && (
                    <div className="text-indigo-500">
                        {icon}
                    </div>
                )}
            </div>
        </div>
    );
}
