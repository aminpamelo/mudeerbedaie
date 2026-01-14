import grapesjs from 'grapesjs';
import gjsPresetNewsletter from 'grapesjs-preset-newsletter';

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

// Block definitions with categories and icons
const BLOCK_DEFINITIONS = {
    structure: {
        name: 'Struktur',
        icon: '📐',
        blocks: ['email-header', 'email-footer', 'divider', 'spacer', 'sect100', 'sect50']
    },
    content: {
        name: 'Kandungan',
        icon: '📝',
        blocks: ['text', 'text-sect', 'image', 'button', 'link', 'link-block']
    },
    placeholder: {
        name: 'Placeholder',
        icon: '🔖',
        blocks: ['greeting', 'session-info', 'placeholder-text', 'placeholder-button']
    },
    marketing: {
        name: 'Pemasaran',
        icon: '📢',
        blocks: ['social-links', 'countdown', 'progress-stats']
    }
};

// Email builder Alpine component definition
const emailBuilderComponent = (initialHtml = null, autoSaveEnabledParam = true) => ({
        editor: null,
        deviceMode: 'desktop',
        isSaving: false,
        isAutoSaving: false,
        hasChanges: false,
        lastSavedAt: null,
        autoSaveTimeout: null,
        autoSaveEnabled: autoSaveEnabledParam,
        autoSaveDelay: 30000, // 30 seconds
        blockSearch: '',
        isLoading: true,
        isLoadingContent: false, // Flag to ignore change events during content load
        // Panel states
        leftPanelCollapsed: false,
        rightPanelCollapsed: false,
        rightPanelTab: 'traits',
        lastSaved: null,

        init() {
            // Wait for DOM to be ready
            this.$nextTick(() => {
                try {
                    console.log('Initializing email builder...');
                    this.initializeEditor();
                    this.setupEventListeners();
                    this.setupLivewireListeners();

                    // Render panels after a brief delay to ensure refs are available
                    setTimeout(() => {
                        try {
                            this.renderPanels();
                            console.log('Email builder initialization complete');
                        } catch (e) {
                            console.error('Error rendering panels:', e);
                        }
                        this.isLoading = false;
                    }, 100);
                } catch (e) {
                    console.error('Error initializing email builder:', e);
                    this.isLoading = false;
                    alert('Ralat memuatkan editor. Sila muat semula halaman.');
                }
            });
        },

        initializeEditor() {
            const container = this.$refs.editorCanvas;
            if (!container) {
                console.error('Editor canvas not found');
                this.isLoading = false;
                return;
            }

            try {
                this.editor = grapesjs.init({
                container: container,
                height: '100%',
                width: 'auto',
                plugins: [gjsPresetNewsletter],
                pluginsOpts: {
                    [gjsPresetNewsletter]: {
                        modalTitleImport: 'Import Templat',
                        modalTitleExport: 'Eksport Templat',
                        importPlaceholder: '<table class="main-body">...</table>',
                        cellStyle: {
                            'font-size': '14px',
                            'font-family': 'Arial, Helvetica, sans-serif',
                            'color': '#333333',
                        }
                    }
                },
                storageManager: false,
                deviceManager: {
                    devices: [
                        {
                            name: 'Desktop',
                            width: '',
                        },
                        {
                            name: 'Tablet',
                            width: '768px',
                            widthMedia: '768px',
                        },
                        {
                            name: 'Mobile',
                            width: '320px',
                            widthMedia: '480px',
                        }
                    ]
                },
                panels: {
                    defaults: []
                },
                assetManager: {
                    uploadText: 'Seret fail ke sini atau klik untuk muat naik',
                    addBtnText: 'Tambah Imej',
                    inputPlaceholder: 'https://path/to/your/image.jpg',
                    modalTitle: 'Pilih Imej',
                    upload: false,
                    embedAsBase64: true,
                },
                canvas: {
                    styles: [
                        'https://fonts.bunny.net/css?family=instrument-sans:400,500,600'
                    ]
                }
            });

            // Add custom placeholder blocks
            this.addCustomBlocks();

            // Add custom placeholder trait to text components
            this.addPlaceholderTrait();

            // Load initial HTML content if provided (safer than design_json)
            if (initialHtml && initialHtml.trim()) {
                console.log('Loading initial HTML content...');
                this.loadHtml(initialHtml);
            }

            // Track changes for auto-save (ignore during content loading)
            this.editor.on('change:changesCount', () => {
                // Skip change tracking during content load to prevent freeze
                if (this.isLoadingContent) {
                    return;
                }
                this.hasChanges = true;
                this.triggerAutoSave();
            });

            // Component events for better UX
            this.editor.on('component:selected', (component) => {
                this.showComponentFeedback(component);
            });

            this.editor.on('block:drag:start', () => {
                document.body.classList.add('is-dragging-block');
            });

            this.editor.on('block:drag:stop', (component) => {
                document.body.classList.remove('is-dragging-block');
                if (component) {
                    this.showDropFeedback(component);
                }
            });
            } catch (e) {
                console.error('Error initializing GrapeJS editor:', e);
                this.isLoading = false;
                // Don't throw - let the error bubble up to init() which has its own catch
                throw e;
            }
        },

        setupEventListeners() {
            // Handle device mode changes from external buttons
            this.$watch('deviceMode', (mode) => {
                if (this.editor) {
                    const deviceMap = {
                        'desktop': 'Desktop',
                        'tablet': 'Tablet',
                        'mobile': 'Mobile'
                    };
                    this.editor.setDevice(deviceMap[mode] || 'Desktop');
                }
            });

            // Block search filter
            this.$watch('blockSearch', (value) => {
                this.filterBlocks(value);
            });

            // Warn before leaving with unsaved changes
            window.addEventListener('beforeunload', (e) => {
                if (this.hasChanges && this.autoSaveEnabled) {
                    e.preventDefault();
                    e.returnValue = 'Anda mempunyai perubahan yang belum disimpan. Adakah anda pasti mahu keluar?';
                }
            });
        },

        setupLivewireListeners() {
            // Listen for Livewire events to load designs
            Livewire.on('load-design', (event) => {
                if (event.design) {
                    // Use setTimeout to prevent blocking
                    setTimeout(() => {
                        try {
                            this.loadDesign(event.design);
                        } catch (e) {
                            console.error('Error loading design:', e);
                        }
                    }, 100);
                }
            });

            // Listen for starter template HTML loading
            Livewire.on('load-starter-html', (event) => {
                if (event.html !== undefined) {
                    // Use setTimeout to allow UI to update and prevent freeze
                    setTimeout(() => {
                        try {
                            this.loadHtml(event.html);
                        } catch (e) {
                            console.error('Error loading starter HTML:', e);
                            alert('Ralat memuatkan templat. Sila cuba lagi.');
                        }
                    }, 100);
                }
            });

            // Listen for external save triggers
            Livewire.on('trigger-save', () => {
                this.save();
            });
        },

        // Load HTML content directly (used for starter templates)
        loadHtml(html) {
            if (!this.editor) {
                console.warn('Editor not initialized, cannot load HTML');
                return;
            }

            // Set loading flag to block all change events
            this.isLoadingContent = true;
            console.log('Loading HTML content, length:', html?.length || 0);

            // Clear any pending auto-save
            if (this.autoSaveTimeout) {
                clearTimeout(this.autoSaveTimeout);
                this.autoSaveTimeout = null;
            }

            try {
                // Clear existing content first
                this.editor.DomComponents.clear();

                // Load HTML content with size check
                if (html && html.trim()) {
                    // Check for very large content that might cause issues
                    if (html.length > 500000) {
                        console.warn('HTML content is very large:', html.length, 'characters');
                        if (!confirm('Kandungan templat sangat besar. Ini mungkin mengambil masa. Teruskan?')) {
                            this.isLoadingContent = false;
                            return;
                        }
                    }

                    // Use requestAnimationFrame to prevent UI freeze
                    requestAnimationFrame(() => {
                        try {
                            this.editor.setComponents(html);
                            console.log('HTML content loaded successfully');
                        } catch (innerError) {
                            console.error('Error in setComponents:', innerError);
                            // Try to recover by clearing
                            try {
                                this.editor.DomComponents.clear();
                            } catch (e) {}
                        }

                        // Re-enable change tracking after content is loaded
                        setTimeout(() => {
                            this.isLoadingContent = false;
                            this.hasChanges = false;
                            console.log('Content loading complete, change tracking re-enabled');
                        }, 200);
                    });
                } else {
                    console.log('Empty HTML, starting with blank canvas');
                    this.isLoadingContent = false;
                    this.hasChanges = false;
                }

            } catch (e) {
                console.error('Error loading HTML:', e);
                this.isLoadingContent = false;
                // Don't show alert for load errors on page load - just log
                console.warn('Failed to load template HTML, starting with blank canvas');
            }
        },

        renderPanels() {
            if (!this.editor) return;

            console.log('Rendering panels...');
            console.log('Number of blocks:', this.editor.BlockManager.getAll().length);

            // Render blocks to external container
            const blocksContainer = this.$refs.blocksContainer;
            if (blocksContainer) {
                // Clear existing content
                blocksContainer.innerHTML = '';
                // Get rendered blocks element from BlockManager
                const blocksEl = this.editor.BlockManager.render();
                blocksContainer.appendChild(blocksEl);
                console.log('Blocks rendered to container');
            } else {
                console.warn('Blocks container not found');
            }

            // Render traits to external container
            const traitsContainer = this.$refs.traitsContainer;
            if (traitsContainer) {
                traitsContainer.innerHTML = '';
                const traitsEl = this.editor.TraitManager.render();
                traitsContainer.appendChild(traitsEl);
                console.log('Traits rendered to container');
            }

            // Render styles to external container
            const stylesContainer = this.$refs.stylesContainer;
            if (stylesContainer) {
                stylesContainer.innerHTML = '';
                const stylesEl = this.editor.StyleManager.render();
                stylesContainer.appendChild(stylesEl);
                console.log('Styles rendered to container');
            }

            console.log('Panels rendered');
        },

        loadDesign(design) {
            if (!this.editor) return;

            // Set loading flag to block change events
            this.isLoadingContent = true;

            // Helper to reset loading flag
            const finishLoading = () => {
                setTimeout(() => {
                    this.isLoadingContent = false;
                    console.log('Design loading complete, change tracking re-enabled');
                }, 200);
            };

            try {
                const designData = typeof design === 'string' ? JSON.parse(design) : design;

                // Validate design data structure
                if (!designData || typeof designData !== 'object') {
                    console.warn('Invalid design data: not an object');
                    finishLoading();
                    return;
                }

                // Check if design data is empty
                if (Object.keys(designData).length === 0) {
                    console.log('Design data is empty, skipping load');
                    finishLoading();
                    return;
                }

                // Log design data structure for debugging
                console.log('Loading design with keys:', Object.keys(designData));
                console.log('Design data structure:', JSON.stringify(designData).substring(0, 500) + '...');

                // Validate expected GrapeJS project data structure
                // GrapeJS expects: { pages, styles, assets, etc. }
                const validKeys = ['pages', 'styles', 'assets', 'symbols', 'dataSources'];
                const hasValidStructure = validKeys.some(key => key in designData);

                if (!hasValidStructure) {
                    // Check if it's old HTML-only format
                    if (designData.html) {
                        console.warn('Design data appears to be old HTML format, not GrapeJS project data');
                        try {
                            this.editor.setComponents(designData.html);
                            if (designData.css) {
                                this.editor.setStyle(designData.css);
                            }
                            this.hasChanges = false;
                            console.log('Loaded design from HTML format');
                            finishLoading();
                            return;
                        } catch (htmlErr) {
                            console.error('Error loading HTML format:', htmlErr);
                        }
                    }

                    // Check for legacy component-based format
                    if (designData.components || Array.isArray(designData)) {
                        console.warn('Design data appears to be legacy components format');
                        try {
                            const components = designData.components || designData;
                            this.editor.setComponents(components);
                            this.hasChanges = false;
                            console.log('Loaded design from components format');
                            finishLoading();
                            return;
                        } catch (compErr) {
                            console.error('Error loading components format:', compErr);
                        }
                    }

                    console.warn('Design data format not recognized:', Object.keys(designData));
                    console.warn('Attempting to load anyway...');
                }

                // Validate pages structure if present
                if (designData.pages) {
                    if (!Array.isArray(designData.pages)) {
                        console.error('Invalid pages structure: not an array');
                        finishLoading();
                        return;
                    }
                    // Check for potentially problematic nested structures
                    const pagesStr = JSON.stringify(designData.pages);
                    if (pagesStr.length > 1000000) { // 1MB limit
                        console.error('Pages data too large, may cause performance issues');
                        if (!confirm('Data reka bentuk sangat besar. Ini mungkin menyebabkan perlahan. Teruskan?')) {
                            finishLoading();
                            return;
                        }
                    }
                }

                // Load project data
                try {
                    console.log('Calling loadProjectData...');
                    this.editor.loadProjectData(designData);
                    console.log('Design loaded successfully');
                    this.hasChanges = false;
                } catch (loadErr) {
                    console.error('Error in loadProjectData:', loadErr);
                    // Try to recover by clearing and showing message
                    try {
                        this.editor.DomComponents.clear();
                    } catch (clearErr) {
                        console.error('Error clearing components:', clearErr);
                    }
                    alert('Gagal memuatkan reka bentuk yang disimpan. Sila pilih templat baru.');
                }

                finishLoading();

            } catch (e) {
                console.error('Error parsing/loading design:', e);
                alert('Ralat memproses data reka bentuk: ' + e.message);
                this.isLoadingContent = false;
            }
        },

        addCustomBlocks() {
            const bm = this.editor.BlockManager;

            // Placeholder Text Block
            bm.add('placeholder-text', {
                label: 'Teks Dinamik',
                category: 'Placeholder',
                content: {
                    type: 'text',
                    content: 'Klik di sini untuk mengedit atau masukkan placeholder',
                    style: { padding: '10px', 'font-size': '14px' }
                },
                media: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 7V4h16v3M9 20h6M12 4v16"/>
                </svg>`
            });

            // Placeholder Button Block
            bm.add('placeholder-button', {
                label: 'Butang CTA',
                category: 'Placeholder',
                content: `<a href="{{meeting_url}}" style="display: inline-block; padding: 12px 24px; background-color: #3b82f6; color: white; text-decoration: none; border-radius: 6px; font-weight: 600; text-align: center;">Sertai Sesi</a>`,
                media: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="8" width="18" height="8" rx="2"/>
                    <path d="M8 12h8"/>
                </svg>`
            });

            // Header Block
            bm.add('email-header', {
                label: 'Header E-mel',
                category: 'Struktur',
                content: `
                    <table width="100%" style="background-color: #f8fafc; padding: 24px;">
                        <tr>
                            <td align="center">
                                <h1 style="color: #1e293b; font-size: 24px; margin: 0; font-weight: 600;">{{class_name}}</h1>
                                <p style="color: #64748b; font-size: 14px; margin-top: 8px; margin-bottom: 0;">{{course_name}}</p>
                            </td>
                        </tr>
                    </table>
                `,
                media: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="18" height="6" rx="1"/>
                    <path d="M3 12h18M3 16h12"/>
                </svg>`
            });

            // Footer Block
            bm.add('email-footer', {
                label: 'Footer E-mel',
                category: 'Struktur',
                content: `
                    <table width="100%" style="background-color: #f1f5f9; padding: 24px; margin-top: 24px;">
                        <tr>
                            <td align="center">
                                <p style="color: #64748b; font-size: 12px; margin: 0; line-height: 1.6;">
                                    Terima kasih kerana menggunakan perkhidmatan kami.
                                </p>
                                <p style="color: #94a3b8; font-size: 11px; margin-top: 12px; margin-bottom: 0;">
                                    Jika anda mempunyai sebarang soalan, sila hubungi kami.
                                </p>
                            </td>
                        </tr>
                    </table>
                `,
                media: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 8h18M3 12h12"/>
                    <rect x="3" y="15" width="18" height="6" rx="1"/>
                </svg>`
            });

            // Session Info Block
            bm.add('session-info', {
                label: 'Info Sesi',
                category: 'Placeholder',
                content: `
                    <table width="100%" style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; margin: 16px 0;">
                        <tr>
                            <td style="padding: 20px;">
                                <p style="margin: 0 0 12px 0; color: #374151; font-size: 14px; line-height: 1.5;">
                                    <strong style="color: #1f2937;">📅 Tarikh:</strong> {{session_date}}
                                </p>
                                <p style="margin: 0 0 12px 0; color: #374151; font-size: 14px; line-height: 1.5;">
                                    <strong style="color: #1f2937;">🕐 Masa:</strong> {{session_time}}
                                </p>
                                <p style="margin: 0 0 12px 0; color: #374151; font-size: 14px; line-height: 1.5;">
                                    <strong style="color: #1f2937;">📍 Lokasi:</strong> {{location}}
                                </p>
                                <p style="margin: 0; color: #374151; font-size: 14px; line-height: 1.5;">
                                    <strong style="color: #1f2937;">⏱️ Tempoh:</strong> {{duration}}
                                </p>
                            </td>
                        </tr>
                    </table>
                `,
                media: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2"/>
                    <path d="M16 2v4M8 2v4M3 10h18"/>
                </svg>`
            });

            // Greeting Block
            bm.add('greeting', {
                label: 'Salam',
                category: 'Placeholder',
                content: `
                    <p style="font-size: 16px; color: #1f2937; margin: 0 0 16px 0; line-height: 1.6;">
                        Salam sejahtera <strong>{{student_name}}</strong>,
                    </p>
                `,
                media: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>`
            });

            // Social Links Block
            bm.add('social-links', {
                label: 'Pautan Sosial',
                category: 'Pemasaran',
                content: `
                    <table width="100%" style="padding: 24px;">
                        <tr>
                            <td align="center">
                                <a href="{{whatsapp_link}}" style="display: inline-block; margin: 0 6px; padding: 12px 20px; background-color: #25D366; color: white; text-decoration: none; border-radius: 6px; font-size: 14px; font-weight: 500;">
                                    💬 WhatsApp
                                </a>
                                <a href="{{meeting_url}}" style="display: inline-block; margin: 0 6px; padding: 12px 20px; background-color: #3b82f6; color: white; text-decoration: none; border-radius: 6px; font-size: 14px; font-weight: 500;">
                                    🎥 Sertai Meeting
                                </a>
                            </td>
                        </tr>
                    </table>
                `,
                media: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="18" cy="5" r="3"/>
                    <circle cx="6" cy="12" r="3"/>
                    <circle cx="18" cy="19" r="3"/>
                    <path d="M8.59 13.51l6.83 3.98M15.41 6.51l-6.82 3.98"/>
                </svg>`
            });

            // Divider Block
            bm.add('divider', {
                label: 'Pembahagi',
                category: 'Struktur',
                content: `<hr style="border: 0; border-top: 1px solid #e5e7eb; margin: 24px 0;">`,
                media: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 12h18"/>
                </svg>`
            });

            // Spacer Block
            bm.add('spacer', {
                label: 'Ruang Kosong',
                category: 'Struktur',
                content: `<div style="height: 24px;"></div>`,
                media: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 5v14M5 12h14"/>
                </svg>`
            });

            // Countdown/Reminder Block
            bm.add('countdown', {
                label: 'Peringatan',
                category: 'Pemasaran',
                content: `
                    <table width="100%" style="background-color: #fef3c7; border-left: 4px solid #f59e0b; margin: 16px 0;">
                        <tr>
                            <td style="padding: 16px 20px;">
                                <p style="margin: 0; color: #92400e; font-weight: 600; font-size: 14px;">
                                    ⚠️ Peringatan: Sesi akan bermula pada {{session_datetime}}
                                </p>
                                <p style="margin: 8px 0 0 0; color: #a16207; font-size: 13px; line-height: 1.5;">
                                    Sila pastikan anda bersedia tepat pada masanya.
                                </p>
                            </td>
                        </tr>
                    </table>
                `,
                media: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M12 6v6l4 2"/>
                </svg>`
            });

            // Progress/Stats Block
            bm.add('progress-stats', {
                label: 'Statistik',
                category: 'Pemasaran',
                content: `
                    <table width="100%" style="background-color: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; margin: 16px 0;">
                        <tr>
                            <td align="center" style="padding: 20px; width: 50%; border-right: 1px solid #86efac;">
                                <p style="margin: 0; color: #166534; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">
                                    Kadar Kehadiran
                                </p>
                                <p style="margin: 8px 0 0 0; color: #15803d; font-size: 28px; font-weight: bold;">
                                    {{attendance_rate}}%
                                </p>
                            </td>
                            <td align="center" style="padding: 20px; width: 50%;">
                                <p style="margin: 0; color: #166534; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">
                                    Sesi Tinggal
                                </p>
                                <p style="margin: 8px 0 0 0; color: #15803d; font-size: 28px; font-weight: bold;">
                                    {{remaining_sessions}}/{{total_sessions}}
                                </p>
                            </td>
                        </tr>
                    </table>
                `,
                media: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 20V10M12 20V4M6 20v-6"/>
                </svg>`
            });
        },

        addPlaceholderTrait() {
            // Add placeholder insertion trait to all text-like components
            const textTypes = ['text', 'link', 'label'];

            textTypes.forEach(type => {
                const originalType = this.editor.DomComponents.getType(type);
                if (originalType) {
                    this.editor.DomComponents.addType(type, {
                        model: {
                            defaults: {
                                ...originalType.model.prototype.defaults,
                                traits: [
                                    ...(originalType.model.prototype.defaults.traits || []),
                                    {
                                        type: 'select',
                                        label: 'Masukkan Placeholder',
                                        name: 'insertPlaceholder',
                                        options: [
                                            { value: '', name: '-- Pilih --' },
                                            ...Object.entries(PLACEHOLDERS).map(([value, name]) => ({
                                                value, name
                                            }))
                                        ],
                                        changeProp: 1
                                    }
                                ]
                            },
                            init() {
                                this.on('change:insertPlaceholder', this.handlePlaceholderInsert);
                            },
                            handlePlaceholderInsert() {
                                const placeholder = this.get('insertPlaceholder');
                                if (placeholder) {
                                    const currentContent = this.get('content') || '';
                                    this.set('content', currentContent + ' ' + placeholder);
                                    this.set('insertPlaceholder', '');
                                }
                            }
                        }
                    });
                }
            });
        },

        filterBlocks(searchTerm) {
            if (!this.editor) return;

            const blocks = this.editor.BlockManager.getAll();
            const term = searchTerm.toLowerCase().trim();

            blocks.forEach(block => {
                const label = block.get('label').toLowerCase();
                const category = block.get('category')?.toLowerCase() || '';
                const blockEl = document.querySelector(`[data-block="${block.id}"]`);

                if (blockEl) {
                    const matches = !term || label.includes(term) || category.includes(term);
                    blockEl.style.display = matches ? '' : 'none';
                }
            });

            // Also filter category headers
            const categories = document.querySelectorAll('.gjs-block-category');
            categories.forEach(cat => {
                const visibleBlocks = cat.querySelectorAll('.gjs-block:not([style*="display: none"])');
                cat.style.display = visibleBlocks.length > 0 ? '' : 'none';
            });
        },

        // Auto-save functionality
        triggerAutoSave() {
            // Don't trigger auto-save during content loading or if disabled
            if (!this.autoSaveEnabled || !this.hasChanges || this.isLoadingContent) return;

            // Clear existing timeout
            if (this.autoSaveTimeout) {
                clearTimeout(this.autoSaveTimeout);
            }

            // Set new timeout
            this.autoSaveTimeout = setTimeout(() => {
                // Double-check we're not loading content before saving
                if (!this.isLoadingContent) {
                    this.autoSave();
                }
            }, this.autoSaveDelay);
        },

        async autoSave() {
            // Don't auto-save during content loading or if already saving
            if (!this.editor || !this.hasChanges || this.isAutoSaving || this.isLoadingContent) return;

            this.isAutoSaving = true;
            console.log('Starting auto-save...');

            try {
                const html = this.getHtml();
                const css = this.getCss();
                const designJson = this.getDesignJson();

                // Call Livewire auto-save method
                await this.$wire.autoSave(designJson || '{}', html, css);

                this.hasChanges = false;
                this.lastSavedAt = new Date().toLocaleTimeString('ms-MY', {
                    hour: '2-digit',
                    minute: '2-digit'
                });

                console.log('Auto-save completed');
                // Visual feedback
                this.showSaveSuccess();
            } catch (error) {
                console.error('Auto-save failed:', error);
                // Don't show alert for auto-save failures, just log
            } finally {
                this.isAutoSaving = false;
            }
        },

        // Visual feedback methods
        showComponentFeedback(component) {
            // Brief highlight animation when component is selected
            const el = component.getEl();
            if (el) {
                el.classList.add('success-flash');
                setTimeout(() => el.classList.remove('success-flash'), 500);
            }
        },

        showDropFeedback(component) {
            // Visual feedback when block is dropped
            const el = component.getEl();
            if (el) {
                el.style.animation = 'none';
                el.offsetHeight; // Trigger reflow
                el.style.animation = 'success-flash 0.5s ease';
            }
        },

        showSaveSuccess() {
            // Flash the save indicator
            const indicator = document.querySelector('.auto-save-indicator');
            if (indicator) {
                indicator.classList.add('success-flash');
                setTimeout(() => indicator.classList.remove('success-flash'), 500);
            }
        },

        showSaveError() {
            // Shake the save button to indicate error
            const saveBtn = document.querySelector('[data-save-btn]');
            if (saveBtn) {
                saveBtn.classList.add('shake');
                setTimeout(() => saveBtn.classList.remove('shake'), 300);
            }
        },

        // Public methods
        insertPlaceholder(placeholder) {
            const selected = this.editor.getSelected();
            if (selected) {
                const currentContent = selected.get('content') || '';
                selected.set('content', currentContent + ' ' + placeholder);
            } else {
                // If no component selected, add a new text block with the placeholder
                this.editor.addComponents({
                    type: 'text',
                    content: placeholder,
                    style: { padding: '10px' }
                });
            }
        },

        setDevice(device) {
            this.deviceMode = device;
        },

        getDesignJson() {
            if (!this.editor) return null;

            try {
                const projectData = this.editor.getProjectData();

                // Use safe JSON stringify with circular reference handling
                const seen = new WeakSet();
                const safeStringify = (obj, maxDepth = 50, currentDepth = 0) => {
                    return JSON.stringify(obj, (key, value) => {
                        // Skip internal GrapeJS properties that may cause issues
                        if (key.startsWith('_') || key === 'el' || key === 'view' || key === 'em' || key === 'frame') {
                            return undefined;
                        }

                        // Handle circular references
                        if (typeof value === 'object' && value !== null) {
                            if (seen.has(value)) {
                                return '[Circular]';
                            }
                            seen.add(value);
                        }

                        // Skip DOM elements and functions
                        if (value instanceof HTMLElement || typeof value === 'function') {
                            return undefined;
                        }

                        return value;
                    });
                };

                return safeStringify(projectData);
            } catch (e) {
                console.error('Error serializing design data:', e);
                // Fallback: return minimal data structure
                return JSON.stringify({
                    pages: [],
                    styles: [],
                    assets: []
                });
            }
        },

        getHtml() {
            if (!this.editor) return '';
            return this.editor.getHtml();
        },

        getCss() {
            if (!this.editor) return '';
            return this.editor.getCss();
        },

        async save() {
            if (!this.editor) return;

            this.isSaving = true;
            console.log('Starting save...');

            try {
                // Get HTML and CSS first (these are safer)
                console.log('Getting HTML...');
                const html = this.getHtml();
                console.log('HTML length:', html.length);

                console.log('Getting CSS...');
                const css = this.getCss();
                console.log('CSS length:', css.length);

                // Get design JSON (this is what might hang)
                console.log('Getting design JSON...');
                const designJson = this.getDesignJson();
                console.log('Design JSON length:', designJson ? designJson.length : 0);

                // Dispatch to Livewire
                console.log('Calling Livewire saveDesign...');
                await this.$wire.saveDesign(designJson || '{}', html, css);
                console.log('Save completed successfully');

                this.hasChanges = false;
                this.lastSavedAt = new Date().toLocaleTimeString('ms-MY', {
                    hour: '2-digit',
                    minute: '2-digit'
                });

                this.showSaveSuccess();
            } catch (error) {
                console.error('Error saving design:', error);
                alert('Ralat menyimpan: ' + error.message);
                this.showSaveError();
            } finally {
                this.isSaving = false;
            }
        },

        clear() {
            if (!this.editor) return;

            if (confirm('Adakah anda pasti mahu mengosongkan semua kandungan?')) {
                this.editor.DomComponents.clear();
                this.hasChanges = true;
            }
        },

        undo() {
            if (this.editor) {
                this.editor.UndoManager.undo();
            }
        },

        redo() {
            if (this.editor) {
                this.editor.UndoManager.redo();
            }
        },

        preview() {
            if (this.editor) {
                this.editor.runCommand('preview');
            }
        },

        exitPreview() {
            if (this.editor) {
                this.editor.stopCommand('preview');
            }
        },

        toggleAutoSave() {
            this.autoSaveEnabled = !this.autoSaveEnabled;
            if (!this.autoSaveEnabled && this.autoSaveTimeout) {
                clearTimeout(this.autoSaveTimeout);
            }
        },

        // Cleanup
        destroy() {
            if (this.autoSaveTimeout) {
                clearTimeout(this.autoSaveTimeout);
            }
            if (this.editor) {
                this.editor.destroy();
            }
        }
});

// Register the Alpine component when Alpine initializes
// This script loads BEFORE Alpine, so we use alpine:init event
document.addEventListener('alpine:init', () => {
    Alpine.data('emailBuilder', emailBuilderComponent);
    console.log('Email builder component registered via alpine:init');
});

// Export for use outside Alpine
window.EmailBuilder = {
    PLACEHOLDERS,
    BLOCK_DEFINITIONS,
    component: emailBuilderComponent
};
