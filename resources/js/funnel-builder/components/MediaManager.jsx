/**
 * Media Manager Component
 * Upload and manage images for funnel pages
 */

import React, { useState, useEffect, useCallback, useRef } from 'react';
import { mediaApi } from '../services/api';

// Upload icon
const UploadIcon = () => (
    <svg className="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
    </svg>
);

// Image icon
const ImageIcon = () => (
    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
    </svg>
);

// Trash icon
const TrashIcon = () => (
    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
    </svg>
);

// Check icon
const CheckIcon = () => (
    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
    </svg>
);

// Loading spinner
const Spinner = ({ className = "w-5 h-5" }) => (
    <svg className={`animate-spin ${className}`} fill="none" viewBox="0 0 24 24">
        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
    </svg>
);

export default function MediaManager({
    isOpen,
    onClose,
    onSelect,
    funnelUuid = null,
    allowMultiple = false,
    selectedImages = []
}) {
    const [media, setMedia] = useState([]);
    const [loading, setLoading] = useState(true);
    const [uploading, setUploading] = useState(false);
    const [uploadProgress, setUploadProgress] = useState(0);
    const [selected, setSelected] = useState(selectedImages);
    const [page, setPage] = useState(1);
    const [hasMore, setHasMore] = useState(false);
    const [dragActive, setDragActive] = useState(false);
    const [error, setError] = useState(null);
    const fileInputRef = useRef(null);

    // Track if we've fetched to prevent duplicate calls
    const hasFetched = useRef(false);

    // Fetch media list
    const fetchMedia = useCallback(async (pageNum = 1, append = false) => {
        try {
            setLoading(true);
            setError(null);
            const params = { page: pageNum, per_page: 24 };
            if (funnelUuid) {
                params.funnel_uuid = funnelUuid;
            }
            const response = await mediaApi.list(params);

            if (append) {
                setMedia(prev => [...prev, ...response.data]);
            } else {
                setMedia(response.data || []);
            }
            setHasMore(response.meta?.current_page < response.meta?.last_page);
        } catch (err) {
            setError('Failed to load media');
            console.error('Error loading media:', err);
        } finally {
            setLoading(false);
        }
    }, [funnelUuid]);

    // Load media when modal opens
    useEffect(() => {
        if (isOpen && !hasFetched.current) {
            hasFetched.current = true;
            fetchMedia(1);
        }

        // Reset when modal closes
        if (!isOpen) {
            hasFetched.current = false;
        }
    }, [isOpen, fetchMedia]);

    // Update selected when selectedImages prop changes
    useEffect(() => {
        if (isOpen) {
            setSelected(selectedImages);
        }
    }, [isOpen]); // Only run when modal opens, not when selectedImages changes

    // Handle file upload
    const handleUpload = async (files) => {
        if (!files || files.length === 0) return;

        setUploading(true);
        setUploadProgress(0);
        setError(null);

        const totalFiles = files.length;
        let completed = 0;

        for (const file of files) {
            // Validate file type
            if (!file.type.startsWith('image/')) {
                setError(`${file.name} is not an image file`);
                continue;
            }

            // Validate file size (max 10MB)
            if (file.size > 10 * 1024 * 1024) {
                setError(`${file.name} is too large (max 10MB)`);
                continue;
            }

            try {
                const response = await mediaApi.upload(file, funnelUuid);
                setMedia(prev => [response.data, ...prev]);
                completed++;
                setUploadProgress(Math.round((completed / totalFiles) * 100));
            } catch (err) {
                setError(`Failed to upload ${file.name}`);
                console.error('Upload error:', err);
            }
        }

        setUploading(false);
        setUploadProgress(0);
    };

    // Handle file input change
    const handleFileChange = (e) => {
        const files = Array.from(e.target.files);
        handleUpload(files);
        e.target.value = ''; // Reset input
    };

    // Handle drag events
    const handleDrag = (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (e.type === 'dragenter' || e.type === 'dragover') {
            setDragActive(true);
        } else if (e.type === 'dragleave') {
            setDragActive(false);
        }
    };

    // Handle drop
    const handleDrop = (e) => {
        e.preventDefault();
        e.stopPropagation();
        setDragActive(false);

        const files = Array.from(e.dataTransfer.files);
        handleUpload(files);
    };

    // Handle image selection
    const handleSelect = (image) => {
        if (allowMultiple) {
            setSelected(prev => {
                const isSelected = prev.some(img => img.id === image.id);
                if (isSelected) {
                    return prev.filter(img => img.id !== image.id);
                }
                return [...prev, image];
            });
        } else {
            setSelected([image]);
        }
    };

    // Handle delete
    const handleDelete = async (image, e) => {
        e.stopPropagation();
        if (!confirm('Are you sure you want to delete this image?')) return;

        try {
            await mediaApi.delete(image.id);
            setMedia(prev => prev.filter(m => m.id !== image.id));
            setSelected(prev => prev.filter(img => img.id !== image.id));
        } catch (err) {
            setError('Failed to delete image');
            console.error('Delete error:', err);
        }
    };

    // Handle confirm selection
    const handleConfirm = () => {
        if (allowMultiple) {
            onSelect(selected);
        } else {
            onSelect(selected[0] || null);
        }
        onClose();
    };

    // Load more
    const loadMore = () => {
        if (!loading && hasMore) {
            setPage(prev => prev + 1);
            fetchMedia(page + 1, true);
        }
    };

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 overflow-hidden">
            {/* Backdrop */}
            <div
                className="absolute inset-0 bg-black bg-opacity-50 transition-opacity"
                onClick={onClose}
            />

            {/* Modal */}
            <div className="absolute inset-4 md:inset-8 lg:inset-16 bg-white rounded-lg shadow-xl flex flex-col overflow-hidden">
                {/* Header */}
                <div className="flex items-center justify-between px-6 py-4 border-b">
                    <h2 className="text-lg font-semibold text-gray-900">Media Library</h2>
                    <button
                        onClick={onClose}
                        className="p-2 text-gray-400 hover:text-gray-600 rounded-full hover:bg-gray-100"
                    >
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {/* Upload Area */}
                <div
                    className={`mx-6 mt-4 p-6 border-2 border-dashed rounded-lg transition-colors ${
                        dragActive
                            ? 'border-blue-500 bg-blue-50'
                            : 'border-gray-300 hover:border-gray-400'
                    }`}
                    onDragEnter={handleDrag}
                    onDragLeave={handleDrag}
                    onDragOver={handleDrag}
                    onDrop={handleDrop}
                >
                    <div className="text-center">
                        {uploading ? (
                            <div className="flex flex-col items-center">
                                <Spinner className="w-8 h-8 text-blue-500" />
                                <p className="mt-2 text-sm text-gray-600">
                                    Uploading... {uploadProgress}%
                                </p>
                            </div>
                        ) : (
                            <>
                                <UploadIcon />
                                <p className="mt-2 text-sm text-gray-600">
                                    Drag and drop images here, or{' '}
                                    <button
                                        type="button"
                                        onClick={() => fileInputRef.current?.click()}
                                        className="text-blue-600 hover:text-blue-700 font-medium"
                                    >
                                        browse
                                    </button>
                                </p>
                                <p className="mt-1 text-xs text-gray-500">
                                    PNG, JPG, GIF up to 10MB
                                </p>
                            </>
                        )}
                    </div>
                    <input
                        ref={fileInputRef}
                        type="file"
                        accept="image/*"
                        multiple
                        onChange={handleFileChange}
                        className="hidden"
                    />
                </div>

                {/* Error Message */}
                {error && (
                    <div className="mx-6 mt-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
                        {error}
                        <button
                            onClick={() => setError(null)}
                            className="ml-2 text-red-500 hover:text-red-700"
                        >
                            Dismiss
                        </button>
                    </div>
                )}

                {/* Media Grid */}
                <div className="flex-1 overflow-y-auto p-6">
                    {loading && media.length === 0 ? (
                        <div className="flex items-center justify-center h-48">
                            <Spinner className="w-8 h-8 text-gray-400" />
                        </div>
                    ) : media.length === 0 ? (
                        <div className="flex flex-col items-center justify-center h-48 text-gray-500">
                            <ImageIcon />
                            <p className="mt-2">No images uploaded yet</p>
                        </div>
                    ) : (
                        <>
                            <div className="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-4">
                                {media.map(image => {
                                    const isSelected = selected.some(img => img.id === image.id);
                                    return (
                                        <div
                                            key={image.id}
                                            onClick={() => handleSelect(image)}
                                            className={`relative aspect-square rounded-lg overflow-hidden cursor-pointer group border-2 transition-all ${
                                                isSelected
                                                    ? 'border-blue-500 ring-2 ring-blue-200'
                                                    : 'border-transparent hover:border-gray-300'
                                            }`}
                                        >
                                            <img
                                                src={image.thumbnail_url || image.url}
                                                alt={image.alt_text || image.original_filename}
                                                className="w-full h-full object-cover"
                                                loading="lazy"
                                            />

                                            {/* Selection indicator */}
                                            {isSelected && (
                                                <div className="absolute top-2 right-2 w-6 h-6 bg-blue-500 rounded-full flex items-center justify-center text-white">
                                                    <CheckIcon />
                                                </div>
                                            )}

                                            {/* Hover overlay */}
                                            <div className={`absolute inset-0 bg-black transition-opacity ${
                                                isSelected ? 'bg-opacity-10' : 'bg-opacity-0 group-hover:bg-opacity-30'
                                            }`} />

                                            {/* Delete button */}
                                            <button
                                                onClick={(e) => handleDelete(image, e)}
                                                className="absolute top-2 left-2 p-1.5 bg-red-500 text-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity hover:bg-red-600"
                                                title="Delete"
                                            >
                                                <TrashIcon />
                                            </button>

                                            {/* File info */}
                                            <div className="absolute bottom-0 left-0 right-0 p-2 bg-gradient-to-t from-black/60 to-transparent opacity-0 group-hover:opacity-100 transition-opacity">
                                                <p className="text-white text-xs truncate">
                                                    {image.original_filename}
                                                </p>
                                                <p className="text-white/70 text-xs">
                                                    {image.formatted_size}
                                                </p>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>

                            {/* Load More */}
                            {hasMore && (
                                <div className="text-center mt-6">
                                    <button
                                        onClick={loadMore}
                                        disabled={loading}
                                        className="px-4 py-2 text-sm text-blue-600 hover:text-blue-700 font-medium disabled:opacity-50"
                                    >
                                        {loading ? 'Loading...' : 'Load More'}
                                    </button>
                                </div>
                            )}
                        </>
                    )}
                </div>

                {/* Footer */}
                <div className="flex items-center justify-between px-6 py-4 border-t bg-gray-50">
                    <p className="text-sm text-gray-500">
                        {selected.length} {selected.length === 1 ? 'image' : 'images'} selected
                    </p>
                    <div className="flex gap-3">
                        <button
                            onClick={onClose}
                            className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                        >
                            Cancel
                        </button>
                        <button
                            onClick={handleConfirm}
                            disabled={selected.length === 0}
                            className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {allowMultiple ? 'Insert Images' : 'Select Image'}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

/**
 * Image Picker Component - Simplified picker for Puck editor fields
 */
export function ImagePicker({ value, onChange, label = "Image" }) {
    const [isOpen, setIsOpen] = useState(false);

    const handleSelect = (image) => {
        onChange(image?.url || '');
    };

    const handleClear = () => {
        onChange('');
    };

    return (
        <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
                {label}
            </label>

            {value ? (
                <div className="relative group">
                    <img
                        src={value}
                        alt="Selected"
                        className="w-full h-32 object-cover rounded-lg border"
                    />
                    <div className="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg flex items-center justify-center gap-2">
                        <button
                            type="button"
                            onClick={() => setIsOpen(true)}
                            className="px-3 py-1.5 bg-white text-gray-700 text-sm rounded-md hover:bg-gray-100"
                        >
                            Change
                        </button>
                        <button
                            type="button"
                            onClick={handleClear}
                            className="px-3 py-1.5 bg-red-500 text-white text-sm rounded-md hover:bg-red-600"
                        >
                            Remove
                        </button>
                    </div>
                </div>
            ) : (
                <button
                    type="button"
                    onClick={() => setIsOpen(true)}
                    className="w-full h-32 border-2 border-dashed border-gray-300 rounded-lg flex flex-col items-center justify-center text-gray-500 hover:border-gray-400 hover:text-gray-600 transition-colors"
                >
                    <ImageIcon />
                    <span className="mt-1 text-sm">Click to select image</span>
                </button>
            )}

            <MediaManager
                isOpen={isOpen}
                onClose={() => setIsOpen(false)}
                onSelect={handleSelect}
            />
        </div>
    );
}
