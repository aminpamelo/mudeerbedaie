import React, { useRef, useState, useEffect, useCallback } from 'react';
import { createRoot } from 'react-dom/client';
import EmailEditor from 'react-email-editor';

// Available placeholders for the email templates
const PLACEHOLDERS = {
    '{{student_name}}': 'Nama Pelajar',
    '{{teacher_name}}': 'Nama Guru',
    '{{class_name}}': 'Tajuk Kelas',
    '{{course_name}}': 'Nama Kursus',
    '{{session_date}}': 'Tarikh Sesi',
    '{{session_time}}': 'Masa Sesi',
    '{{session_datetime}}': 'Tarikh & Masa',
    '{{location}}': 'Lokasi',
    '{{meeting_url}}': 'URL Mesyuarat',
    '{{whatsapp_link}}': 'Pautan WhatsApp',
    '{{duration}}': 'Tempoh',
    '{{remaining_sessions}}': 'Sesi Tinggal',
    '{{total_sessions}}': 'Jumlah Sesi',
    '{{attendance_rate}}': 'Kadar Kehadiran'
};

// Unlayer editor options
const editorOptions = {
    displayMode: 'email',
    locale: 'ms-MY',
    appearance: {
        theme: 'modern_light',
        panels: {
            tools: {
                dock: 'left'
            }
        }
    },
    features: {
        stockImages: false,
        userUploads: true,
        textEditor: {
            spellChecker: false
        }
    },
    tools: {
        // Enable/disable specific tools
        image: { enabled: true },
        button: { enabled: true },
        divider: { enabled: true },
        heading: { enabled: true },
        html: { enabled: true },
        menu: { enabled: false },
        social: { enabled: true },
        text: { enabled: true },
        timer: { enabled: false },
        video: { enabled: false }
    },
    mergeTags: Object.entries(PLACEHOLDERS).map(([value, name]) => ({
        name: name,
        value: value
    }))
};

function EmailBuilderApp({ templateId, templateName, templateType, templateLanguage, initialDesign, backUrl }) {
    const emailEditorRef = useRef(null);
    const [isSaving, setIsSaving] = useState(false);
    const [isAutoSaving, setIsAutoSaving] = useState(false);
    const [lastSaved, setLastSaved] = useState(null);
    const [hasChanges, setHasChanges] = useState(false);
    const [isEditorReady, setIsEditorReady] = useState(false);
    const [showPlaceholders, setShowPlaceholders] = useState(false);
    const [showTestEmailModal, setShowTestEmailModal] = useState(false);
    const [testEmail, setTestEmail] = useState('');
    const [isSendingTest, setIsSendingTest] = useState(false);
    const [testEmailStatus, setTestEmailStatus] = useState(null); // 'success' | 'error' | null
    const autoSaveTimeoutRef = useRef(null);

    // Handle editor ready
    const onReady = useCallback(() => {
        console.log('Unlayer editor is ready');
        setIsEditorReady(true);

        // Load initial design if provided
        if (initialDesign && emailEditorRef.current) {
            try {
                const design = typeof initialDesign === 'string'
                    ? JSON.parse(initialDesign)
                    : initialDesign;

                if (design && Object.keys(design).length > 0) {
                    emailEditorRef.current.editor.loadDesign(design);
                    console.log('Initial design loaded');
                }
            } catch (e) {
                console.error('Error loading initial design:', e);
            }
        }
    }, [initialDesign]);

    // Handle design change
    const onDesignChange = useCallback(() => {
        if (!isEditorReady) return;

        setHasChanges(true);

        // Clear existing auto-save timeout
        if (autoSaveTimeoutRef.current) {
            clearTimeout(autoSaveTimeoutRef.current);
        }

        // Set new auto-save timeout (30 seconds)
        autoSaveTimeoutRef.current = setTimeout(() => {
            handleAutoSave();
        }, 30000);
    }, [isEditorReady]);

    // Export and get HTML/Design
    const exportDesign = useCallback(() => {
        return new Promise((resolve, reject) => {
            if (!emailEditorRef.current?.editor) {
                reject(new Error('Editor not ready'));
                return;
            }

            emailEditorRef.current.editor.exportHtml((data) => {
                const { design, html } = data;
                resolve({ design, html });
            });
        });
    }, []);

    // Auto-save function
    const handleAutoSave = useCallback(async () => {
        if (!hasChanges || isSaving || isAutoSaving) return;

        setIsAutoSaving(true);
        console.log('Auto-saving...');

        try {
            const { design, html } = await exportDesign();

            // Call Livewire to save
            await window.Livewire.find(
                document.querySelector('[wire\\:id]').getAttribute('wire:id')
            ).call('autoSave', JSON.stringify(design), html);

            setHasChanges(false);
            setLastSaved(new Date().toLocaleTimeString('ms-MY', {
                hour: '2-digit',
                minute: '2-digit'
            }));
            console.log('Auto-save completed');
        } catch (error) {
            console.error('Auto-save failed:', error);
        } finally {
            setIsAutoSaving(false);
        }
    }, [hasChanges, isSaving, isAutoSaving, exportDesign]);

    // Manual save function
    const handleSave = useCallback(async () => {
        if (isSaving) return;

        setIsSaving(true);
        console.log('Saving...');

        try {
            const { design, html } = await exportDesign();

            // Call Livewire to save
            await window.Livewire.find(
                document.querySelector('[wire\\:id]').getAttribute('wire:id')
            ).call('saveDesign', JSON.stringify(design), html);

            setHasChanges(false);
            setLastSaved(new Date().toLocaleTimeString('ms-MY', {
                hour: '2-digit',
                minute: '2-digit'
            }));
            console.log('Save completed');
        } catch (error) {
            console.error('Save failed:', error);
            alert('Ralat menyimpan: ' + error.message);
        } finally {
            setIsSaving(false);
        }
    }, [isSaving, exportDesign]);

    // Preview function
    const handlePreview = useCallback(async () => {
        try {
            const { html } = await exportDesign();

            // Call Livewire to show preview
            await window.Livewire.find(
                document.querySelector('[wire\\:id]').getAttribute('wire:id')
            ).call('previewEmailFromHtml', html);
        } catch (error) {
            console.error('Preview failed:', error);
        }
    }, [exportDesign]);

    // Send test email function
    const handleSendTestEmail = useCallback(async () => {
        if (!testEmail || isSendingTest) return;

        // Basic email validation
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(testEmail)) {
            setTestEmailStatus('error');
            return;
        }

        setIsSendingTest(true);
        setTestEmailStatus(null);
        console.log('Sending test email to:', testEmail);

        try {
            const { html } = await exportDesign();

            // Call Livewire to send test email
            await window.Livewire.find(
                document.querySelector('[wire\\:id]').getAttribute('wire:id')
            ).call('sendTestEmail', testEmail, html);

            setTestEmailStatus('success');
            console.log('Test email sent successfully');

            // Close modal after 2 seconds on success
            setTimeout(() => {
                setShowTestEmailModal(false);
                setTestEmailStatus(null);
                setTestEmail('');
            }, 2000);
        } catch (error) {
            console.error('Send test email failed:', error);
            setTestEmailStatus('error');
        } finally {
            setIsSendingTest(false);
        }
    }, [testEmail, isSendingTest, exportDesign]);

    // Insert placeholder into editor
    const insertPlaceholder = useCallback((placeholder) => {
        if (emailEditorRef.current?.editor) {
            // Use Unlayer's merge tag insertion
            emailEditorRef.current.editor.setMergeTags(
                Object.entries(PLACEHOLDERS).map(([value, name]) => ({
                    name: name,
                    value: value
                }))
            );
        }
        setShowPlaceholders(false);
    }, []);

    // Cleanup on unmount
    useEffect(() => {
        return () => {
            if (autoSaveTimeoutRef.current) {
                clearTimeout(autoSaveTimeoutRef.current);
            }
        };
    }, []);

    // Warn before leaving with unsaved changes
    useEffect(() => {
        const handleBeforeUnload = (e) => {
            if (hasChanges) {
                e.preventDefault();
                e.returnValue = 'Anda mempunyai perubahan yang belum disimpan.';
            }
        };

        window.addEventListener('beforeunload', handleBeforeUnload);
        return () => window.removeEventListener('beforeunload', handleBeforeUnload);
    }, [hasChanges]);

    return (
        <div className="react-email-builder">
            {/* Header */}
            <header className="reb-header">
                <div className="reb-header-left">
                    <a href={backUrl} className="reb-back-btn" title="Kembali">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </a>
                    <div className="reb-header-divider" />
                    <div className="reb-title">
                        <h1>{templateName}</h1>
                        <span className="reb-subtitle">{templateType} &bull; {templateLanguage?.toUpperCase()}</span>
                    </div>
                </div>

                <div className="reb-header-right">
                    {/* Auto-save Status */}
                    <div className="reb-autosave">
                        {isAutoSaving ? (
                            <span className="reb-autosave-saving">
                                <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                Menyimpan...
                            </span>
                        ) : lastSaved ? (
                            <span className="reb-autosave-saved">
                                <svg className="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                </svg>
                                Disimpan {lastSaved}
                            </span>
                        ) : null}
                    </div>

                    {/* Placeholders Button */}
                    <div className="reb-dropdown">
                        <button
                            type="button"
                            onClick={() => setShowPlaceholders(!showPlaceholders)}
                            className="reb-btn reb-btn-outline"
                        >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                            Placeholder
                        </button>
                        {showPlaceholders && (
                            <div className="reb-dropdown-menu">
                                <div className="reb-dropdown-header">Klik untuk menyalin</div>
                                {Object.entries(PLACEHOLDERS).map(([value, name]) => (
                                    <button
                                        key={value}
                                        type="button"
                                        onClick={() => {
                                            navigator.clipboard.writeText(value);
                                            setShowPlaceholders(false);
                                        }}
                                        className="reb-dropdown-item"
                                    >
                                        <code>{value}</code>
                                        <span>{name}</span>
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Preview Button */}
                    <button
                        type="button"
                        onClick={handlePreview}
                        className="reb-btn reb-btn-outline"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        Pratonton
                    </button>

                    {/* Send Test Email Button */}
                    <button
                        type="button"
                        onClick={() => setShowTestEmailModal(true)}
                        className="reb-btn reb-btn-outline"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        Hantar Ujian
                    </button>

                    {/* Save Button */}
                    <button
                        type="button"
                        onClick={handleSave}
                        disabled={isSaving}
                        className="reb-btn reb-btn-primary"
                    >
                        {isSaving ? (
                            <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                            </svg>
                        ) : (
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                            </svg>
                        )}
                        <span>{isSaving ? 'Menyimpan...' : 'Simpan'}</span>
                    </button>
                </div>
            </header>

            {/* Editor Container */}
            <div className="reb-editor-container">
                {!isEditorReady && (
                    <div className="reb-loading">
                        <svg className="w-8 h-8 animate-spin text-blue-500" fill="none" viewBox="0 0 24 24">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                        </svg>
                        <span>Memuatkan editor...</span>
                    </div>
                )}
                <EmailEditor
                    ref={emailEditorRef}
                    onReady={onReady}
                    onLoad={onDesignChange}
                    options={editorOptions}
                    minHeight="100%"
                    style={{
                        height: '100%',
                        minHeight: '100%',
                        flex: 1,
                        opacity: isEditorReady ? 1 : 0,
                        transition: 'opacity 0.3s ease'
                    }}
                />
            </div>

            {/* Unsaved Changes Toast */}
            {hasChanges && (
                <div className="reb-toast reb-toast-warning">
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span>Terdapat perubahan yang belum disimpan</span>
                </div>
            )}

            {/* Test Email Modal */}
            {showTestEmailModal && (
                <div className="reb-modal-overlay" onClick={() => !isSendingTest && setShowTestEmailModal(false)}>
                    <div className="reb-modal" onClick={(e) => e.stopPropagation()}>
                        <div className="reb-modal-header">
                            <h2>Hantar E-mel Ujian</h2>
                            <button
                                type="button"
                                onClick={() => !isSendingTest && setShowTestEmailModal(false)}
                                className="reb-modal-close"
                                disabled={isSendingTest}
                            >
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div className="reb-modal-body">
                            <p className="reb-modal-description">
                                Masukkan alamat e-mel untuk menerima e-mel ujian dengan templat semasa.
                            </p>
                            <div className="reb-form-group">
                                <label htmlFor="test-email">Alamat E-mel</label>
                                <input
                                    type="email"
                                    id="test-email"
                                    value={testEmail}
                                    onChange={(e) => {
                                        setTestEmail(e.target.value);
                                        setTestEmailStatus(null);
                                    }}
                                    onKeyDown={(e) => e.key === 'Enter' && handleSendTestEmail()}
                                    placeholder="contoh@email.com"
                                    className={`reb-input ${testEmailStatus === 'error' ? 'reb-input-error' : ''}`}
                                    disabled={isSendingTest}
                                    autoFocus
                                />
                                {testEmailStatus === 'error' && (
                                    <span className="reb-error-text">Sila masukkan alamat e-mel yang sah</span>
                                )}
                            </div>

                            {/* Success Message */}
                            {testEmailStatus === 'success' && (
                                <div className="reb-success-message">
                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span>E-mel ujian berjaya dihantar!</span>
                                </div>
                            )}
                        </div>
                        <div className="reb-modal-footer">
                            <button
                                type="button"
                                onClick={() => setShowTestEmailModal(false)}
                                className="reb-btn reb-btn-outline"
                                disabled={isSendingTest}
                            >
                                Batal
                            </button>
                            <button
                                type="button"
                                onClick={handleSendTestEmail}
                                className="reb-btn reb-btn-primary"
                                disabled={isSendingTest || !testEmail}
                            >
                                {isSendingTest ? (
                                    <>
                                        <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                        </svg>
                                        <span>Menghantar...</span>
                                    </>
                                ) : (
                                    <>
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                        </svg>
                                        <span>Hantar</span>
                                    </>
                                )}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

// Initialize React app when DOM is ready
function initEmailBuilder() {
    const container = document.getElementById('react-email-builder-root');
    if (!container) {
        console.error('React email builder container not found');
        return;
    }

    // Get props from data attributes
    const props = {
        templateId: container.dataset.templateId,
        templateName: container.dataset.templateName || 'Email Template',
        templateType: container.dataset.templateType || 'email',
        templateLanguage: container.dataset.templateLanguage || 'ms',
        initialDesign: container.dataset.initialDesign || null,
        backUrl: container.dataset.backUrl || '/admin/settings/notifications'
    };

    const root = createRoot(container);
    root.render(<EmailBuilderApp {...props} />);
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initEmailBuilder);
} else {
    initEmailBuilder();
}

export { EmailBuilderApp, PLACEHOLDERS };
