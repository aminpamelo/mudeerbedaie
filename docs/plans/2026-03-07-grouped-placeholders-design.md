# Grouped & Context-Aware Placeholders for Email Builder

## Goal

Replace the flat placeholder list in the React email builder with grouped, collapsible sections. Make placeholders context-aware so funnel email templates show funnel placeholders and class notifications show class placeholders. Add Product placeholder group for funnel templates.

## Architecture

The React email builder reads `data-template-type` from the container element to determine which placeholder set to display. Two sets exist:

- `class_notification` — existing class-related placeholders (student, teacher, class, session, etc.)
- `funnel_email_template` — funnel placeholders: Contact, Order, Payment, Product, Funnel, General

### Data Structure

```js
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
```

### Fallback

If `data-template-type` is not recognized or missing, fall back to `class_notification` (preserves existing behavior).

## UI Changes

### Placeholder Dropdown (React)

The dropdown changes from flat list to grouped sections:
- Group header: bold text, light grey background, click to expand/collapse
- All groups expanded by default
- Items: click-to-copy (same as today), showing `{{tag}}` + description
- Scrollable if content exceeds viewport

### Unlayer mergeTags

Unlayer's `mergeTags` config supports grouping natively. Convert grouped structure to Unlayer's format so inline merge tag suggestions in the editor are also grouped.

### Blade Placeholders Reference (Management Page)

The `funnel-email-templates.blade.php` management page shows placeholders grouped by category with section headers, matching the visual builder groups. Add Product placeholders here too.

## Files Modified

1. `resources/js/react-email-builder.jsx` — Replace flat `PLACEHOLDERS` with `PLACEHOLDER_SETS`, update dropdown UI to show collapsible groups, update `mergeTags` to use grouped format, read `data-template-type` for context
2. `app/Models/FunnelEmailTemplate.php` — Add Product placeholders to `getAvailablePlaceholders()`, restructure as grouped array via new `getGroupedPlaceholders()` method
3. `resources/views/livewire/admin/funnel-email-templates.blade.php` — Update placeholders reference section to show grouped layout with section headers
