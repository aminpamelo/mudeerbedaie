import React, { useState, useEffect } from 'react';
import { courseApi } from '../services/api';

export default function ClassSelector({ course, onSelect, onClose }) {
    const [classes, setClasses] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const fetchClasses = async () => {
            try {
                const response = await courseApi.getClasses(course.id);
                setClasses(response.data || []);
            } catch (err) {
                console.error('Failed to load classes:', err);
            } finally {
                setLoading(false);
            }
        };
        fetchClasses();
    }, [course.id]);

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={onClose}>
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-sm mx-4" onClick={e => e.stopPropagation()}>
                <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h3 className="text-lg font-semibold text-gray-900">Select Class</h3>
                        <p className="text-sm text-gray-500">{course.name}</p>
                    </div>
                    <button onClick={onClose} className="p-1 text-gray-400 hover:text-gray-600">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div className="px-6 py-4 max-h-64 overflow-y-auto">
                    {loading ? (
                        <div className="flex items-center justify-center py-8">
                            <div className="w-6 h-6 border-2 border-blue-600 border-t-transparent rounded-full animate-spin" />
                        </div>
                    ) : classes.length === 0 ? (
                        <p className="text-sm text-gray-500 text-center py-8">No active classes available</p>
                    ) : (
                        <div className="space-y-2">
                            {classes.map(cls => (
                                <button
                                    key={cls.id}
                                    onClick={() => onSelect(cls)}
                                    className="w-full px-4 py-3 text-left bg-gray-50 rounded-xl hover:bg-blue-50 hover:border-blue-200 border border-gray-100 transition-colors"
                                >
                                    <p className="text-sm font-medium text-gray-900">{cls.title}</p>
                                    {cls.code && <p className="text-xs text-gray-500 mt-0.5">{cls.code}</p>}
                                </button>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
