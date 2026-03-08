# Grouped & Context-Aware Placeholders Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace the flat placeholder list in the React email builder with grouped, collapsible sections that are context-aware based on template type, and add Product placeholders for funnel templates.

**Architecture:** The React email builder reads `data-template-type` to select which grouped placeholder set to display. Two sets: `class_notification` (existing class placeholders grouped into 5 categories) and `funnel_email_template` (funnel placeholders in 6 groups including new Product group). The dropdown UI renders collapsible group sections. Unlayer's `mergeTags` also uses the grouped format.

**Tech Stack:** React (email builder JSX), Laravel (model), Blade (management page), Tailwind CSS v4

**Design Doc:** `docs/plans/2026-03-07-grouped-placeholders-design.md`

---

### Task 1: Add Product Placeholders and Grouped Structure to Model

**Files:**
- Modify: `app/Models/FunnelEmailTemplate.php` (lines 74-94)

**Step 1: Add `getGroupedPlaceholders()` method**

In `app/Models/FunnelEmailTemplate.php`, add this new method **before** the existing `getAvailablePlaceholders()` method (before line 74):

```php
    public static function getGroupedPlaceholders(): array
    {
        return [
            'Contact' => [
                '{{contact.name}}' => 'Full contact name',
                '{{contact.first_name}}' => 'Contact first name',
                '{{contact.email}}' => 'Contact email',
                '{{contact.phone}}' => 'Contact phone',
            ],
            'Order' => [
                '{{order.number}}' => 'Order number',
                '{{order.total}}' => 'Order total',
                '{{order.date}}' => 'Order date',
                '{{order.items_list}}' => 'Order items list',
            ],
            'Payment' => [
                '{{payment.method}}' => 'Payment method',
                '{{payment.status}}' => 'Payment status',
            ],
            'Product' => [
                '{{product.name}}' => 'Product name',
                '{{product.price}}' => 'Product price',
                '{{product.description}}' => 'Product description',
                '{{product.image_url}}' => 'Product image URL',
            ],
            'Funnel' => [
                '{{funnel.name}}' => 'Funnel name',
                '{{funnel.url}}' => 'Funnel URL',
            ],
            'General' => [
                '{{current_date}}' => 'Current date',
                '{{current_time}}' => 'Current time',
                '{{company_name}}' => 'Company name',
                '{{company_email}}' => 'Company email',
            ],
        ];
    }
```

**Step 2: Update `getAvailablePlaceholders()` to derive from grouped**

Replace the existing `getAvailablePlaceholders()` method with:

```php
    public static function getAvailablePlaceholders(): array
    {
        $placeholders = [];
        foreach (static::getGroupedPlaceholders() as $group => $items) {
            $placeholders = array_merge($placeholders, $items);
        }

        return $placeholders;
    }
```

**Step 3: Verify nothing breaks**

Run: `php artisan test --compact tests/Feature/FunnelEmailTemplateTest.php tests/Feature/FunnelAutomationEmailTemplateTest.php`
Expected: All 18 tests pass.

**Step 4: Commit**

```bash
git add app/Models/FunnelEmailTemplate.php
git commit -m "feat: add grouped placeholders with Product group to FunnelEmailTemplate"
```

---

### Task 2: Update React Email Builder - Grouped Placeholder Data

**Files:**
- Modify: `resources/js/react-email-builder.jsx` (lines 1-59)

**Step 1: Replace flat PLACEHOLDERS with PLACEHOLDER_SETS**

Replace lines 5-21 (the `PLACEHOLDERS` constant) with:

```js
// Grouped placeholders by template type
const PLACEHOLDER_SETS = {
    class_notification: {
        'Pelajar & Guru': {
            '{{student_name}}': 'Nama Pelajar',
            '{{teacher_name}}': 'Nama Guru',
        },
        'Kelas & Kursus': {
            '{{class_name}}': 'Tajuk Kelas',
            '{{course_name}}': 'Nama Kursus',
        },
        'Sesi': {
            '{{session_date}}': 'Tarikh Sesi',
            '{{session_time}}': 'Masa Sesi',
            '{{session_datetime}}': 'Tarikh & Masa',
        },
        'Lokasi & Pautan': {
            '{{location}}': 'Lokasi',
            '{{meeting_url}}': 'URL Mesyuarat',
            '{{whatsapp_link}}': 'Pautan WhatsApp',
        },
        'Statistik': {
            '{{duration}}': 'Tempoh',
            '{{remaining_sessions}}': 'Sesi Tinggal',
            '{{total_sessions}}': 'Jumlah Sesi',
            '{{attendance_rate}}': 'Kadar Kehadiran',
        },
    },
    funnel_email_template: {
        'Contact': {
            '{{contact.name}}': 'Full name',
            '{{contact.first_name}}': 'First name',
            '{{contact.email}}': 'Email',
            '{{contact.phone}}': 'Phone',
        },
        'Order': {
            '{{order.number}}': 'Order number',
            '{{order.total}}': 'Order total',
            '{{order.date}}': 'Order date',
            '{{order.items_list}}': 'Order items list',
        },
        'Payment': {
            '{{payment.method}}': 'Payment method',
            '{{payment.status}}': 'Payment status',
        },
        'Product': {
            '{{product.name}}': 'Product name',
            '{{product.price}}': 'Product price',
            '{{product.description}}': 'Product description',
            '{{product.image_url}}': 'Product image URL',
        },
        'Funnel': {
            '{{funnel.name}}': 'Funnel name',
            '{{funnel.url}}': 'Funnel URL',
        },
        'General': {
            '{{current_date}}': 'Current date',
            '{{current_time}}': 'Current time',
            '{{company_name}}': 'Company name',
            '{{company_email}}': 'Company email',
        },
    },
};

// Helper: get flat placeholders from a grouped set
function getFlatPlaceholders(templateType) {
    const groups = PLACEHOLDER_SETS[templateType] || PLACEHOLDER_SETS.class_notification;
    const flat = {};
    Object.values(groups).forEach(items => {
        Object.assign(flat, items);
    });
    return flat;
}

// Helper: get Unlayer mergeTags from a grouped set
function getMergeTags(templateType) {
    const groups = PLACEHOLDER_SETS[templateType] || PLACEHOLDER_SETS.class_notification;
    return Object.entries(groups).map(([groupName, items]) => ({
        name: groupName,
        mergeTags: Object.entries(items).map(([value, name]) => ({
            name: name,
            value: value,
        })),
    }));
}
```

**Step 2: Update editorOptions to use dynamic mergeTags**

Replace lines 23-59 (the `editorOptions` constant) with a function that receives templateType:

```js
// Unlayer editor options (now a function to support dynamic mergeTags)
function getEditorOptions(templateType) {
    return {
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
        mergeTags: getMergeTags(templateType),
    };
}
```

**Step 3: Update export at bottom of file**

Replace line 552:
```js
export { EmailBuilderApp, PLACEHOLDERS };
```
with:
```js
export { EmailBuilderApp, PLACEHOLDER_SETS, getFlatPlaceholders };
```

**Step 4: Commit (do NOT build yet — UI changes in next task)**

```bash
git add resources/js/react-email-builder.jsx
git commit -m "feat: add grouped placeholder sets with context-aware selection"
```

---

### Task 3: Update React Email Builder - Grouped Dropdown UI

**Files:**
- Modify: `resources/js/react-email-builder.jsx` (lines 61, 314-345)
- Modify: `resources/css/react-email-builder.css` (add group styles)

**Step 1: Update EmailBuilderApp to use dynamic options and placeholders**

In the `EmailBuilderApp` component (line 61), find where `editorOptions` is referenced. Search for `options={editorOptions}` in the JSX (around line 400-420) and replace with:

```jsx
options={getEditorOptions(templateType)}
```

**Step 2: Replace the placeholder dropdown UI**

Replace lines 314-345 (the `{/* Placeholders Button */}` section) with:

```jsx
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
                                <div className="reb-dropdown-header">Click to copy</div>
                                {Object.entries(PLACEHOLDER_SETS[templateType] || PLACEHOLDER_SETS.class_notification).map(([groupName, items]) => (
                                    <PlaceholderGroup
                                        key={groupName}
                                        groupName={groupName}
                                        items={items}
                                        onSelect={(value) => {
                                            navigator.clipboard.writeText(value);
                                            setShowPlaceholders(false);
                                        }}
                                    />
                                ))}
                            </div>
                        )}
                    </div>
```

**Step 3: Add PlaceholderGroup component**

Add this component **above** the `EmailBuilderApp` function definition (before line 61):

```jsx
// Collapsible placeholder group for the dropdown
function PlaceholderGroup({ groupName, items, onSelect }) {
    const [isExpanded, setIsExpanded] = useState(true);

    return (
        <div className="reb-placeholder-group">
            <button
                type="button"
                className="reb-placeholder-group-header"
                onClick={(e) => {
                    e.stopPropagation();
                    setIsExpanded(!isExpanded);
                }}
            >
                <svg
                    className={`reb-placeholder-chevron ${isExpanded ? 'reb-placeholder-chevron-open' : ''}`}
                    fill="none" stroke="currentColor" viewBox="0 0 24 24" width="12" height="12"
                >
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                </svg>
                <span>{groupName}</span>
                <span className="reb-placeholder-count">{Object.keys(items).length}</span>
            </button>
            {isExpanded && (
                <div className="reb-placeholder-group-items">
                    {Object.entries(items).map(([value, name]) => (
                        <button
                            key={value}
                            type="button"
                            onClick={() => onSelect(value)}
                            className="reb-dropdown-item"
                        >
                            <code>{value}</code>
                            <span>{name}</span>
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
```

**Step 4: Add CSS for group styles**

Append to `resources/css/react-email-builder.css` after the existing `.reb-dropdown-item span` rule (after line ~188):

```css
/* Placeholder Groups */
.reb-placeholder-group {
    border-bottom: 1px solid #f1f5f9;
}

.reb-placeholder-group:last-child {
    border-bottom: none;
}

.reb-placeholder-group-header {
    display: flex;
    align-items: center;
    gap: 6px;
    width: 100%;
    padding: 8px 16px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #475569;
    background-color: #f8fafc;
    border: none;
    cursor: pointer;
    transition: background-color 0.15s;
}

.reb-placeholder-group-header:hover {
    background-color: #f1f5f9;
}

.reb-placeholder-chevron {
    transition: transform 0.2s;
    flex-shrink: 0;
}

.reb-placeholder-chevron-open {
    transform: rotate(90deg);
}

.reb-placeholder-count {
    margin-left: auto;
    font-size: 10px;
    font-weight: 500;
    color: #94a3b8;
    background-color: #e2e8f0;
    padding: 1px 6px;
    border-radius: 9999px;
}

.reb-placeholder-group-items {
    /* Items inherit existing .reb-dropdown-item styles */
}
```

**Step 5: Build assets**

Run: `npm run build`
Expected: Build completes without errors.

**Step 6: Commit**

```bash
git add resources/js/react-email-builder.jsx resources/css/react-email-builder.css
git commit -m "feat: add grouped collapsible placeholder dropdown in email builder"
```

---

### Task 4: Update Blade Management Page - Grouped Placeholders

**Files:**
- Modify: `resources/views/livewire/admin/funnel-email-templates.blade.php` (lines 210-228)

**Step 1: Replace flat placeholder reference with grouped layout**

Replace lines 210-228 (the `<!-- Placeholders Reference -->` section) with:

```blade
    <!-- Placeholders Reference -->
    <div class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
        <div class="flex items-center justify-between mb-3">
            <flux:text class="font-medium text-sm">Available Placeholders</flux:text>
            <flux:text class="text-xs text-gray-400">Click to copy</flux:text>
        </div>
        <div class="space-y-3">
            @foreach(App\Models\FunnelEmailTemplate::getGroupedPlaceholders() as $group => $items)
                <div>
                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">{{ $group }}</div>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($items as $tag => $description)
                            <span
                                x-data
                                x-on:click="navigator.clipboard.writeText('{{ $tag }}'); $dispatch('notify', { type: 'success', message: 'Copied!' })"
                                class="inline-flex items-center px-2 py-1 bg-white border border-gray-200 rounded text-xs font-mono cursor-pointer hover:bg-blue-50 hover:border-blue-200 transition-colors"
                                title="{{ $description }}"
                            >
                                {{ $tag }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
```

**Step 2: Build assets and verify**

Run: `npm run build`
Expected: Builds successfully.

**Step 3: Commit**

```bash
git add resources/views/livewire/admin/funnel-email-templates.blade.php
git commit -m "feat: update management page to show grouped placeholders"
```

---

### Task 5: Run Tests, Format Code, Final Verification

**Step 1: Run Laravel Pint**

Run: `vendor/bin/pint --dirty`

**Step 2: Run related tests**

Run: `php artisan test --compact tests/Feature/FunnelEmailTemplateTest.php tests/Feature/FunnelAutomationEmailTemplateTest.php`
Expected: All 18 tests pass.

**Step 3: Build frontend**

Run: `npm run build`
Expected: No errors.

**Step 4: Commit**

```bash
git add -A
git commit -m "chore: format code and finalize grouped placeholders feature"
```

---

## Summary of All Files

### Modified Files (4)
1. `app/Models/FunnelEmailTemplate.php` — Add `getGroupedPlaceholders()`, update `getAvailablePlaceholders()` to derive from it
2. `resources/js/react-email-builder.jsx` — Replace flat `PLACEHOLDERS` with `PLACEHOLDER_SETS`, add `PlaceholderGroup` component, dynamic `editorOptions`
3. `resources/css/react-email-builder.css` — Add group header, chevron, count badge styles
4. `resources/views/livewire/admin/funnel-email-templates.blade.php` — Grouped placeholder reference section
