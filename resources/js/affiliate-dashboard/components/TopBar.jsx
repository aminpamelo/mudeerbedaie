import React from 'react';

export default function TopBar() {
    return (
        <div className="bg-white border-b border-gray-200 sticky top-0 z-10">
            <div className="max-w-md mx-auto px-4 h-14 flex items-center">
                <img
                    src="/images/bedaie-brand.png"
                    alt="BeDaie"
                    className="h-9"
                />
            </div>
        </div>
    );
}
