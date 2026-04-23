# WhatsApp Template Variable Mapping — Contact & Order Fields

## Problem

The Variable Mappings dropdown on the WhatsApp Templates create/edit modal currently only exposes certificate-context fields (student_name, certificate_name, etc.). The funnel system and ecommerce order flows need templates that auto-fill customer and order data (contact.name, order.number, etc.) so the sales/marketing team can reuse a single template across orders without hand-wiring variables in each automation action.

## Solution

Three coordinated changes:

1. **Dropdown options** — add grouped entries for Customer (contact.*) and Order (order.*) to `resources/views/livewire/admin/whatsapp-templates.blade.php`. Keep existing Certificate entries and `custom`.
2. **Context** — extend `FunnelAutomationService::buildPurchaseContext()` with the missing nested keys (`contact.address`, `order.number`, `order.total_raw`, `order.items_*`, `order.coupon_code`, `order.discount_amount`, `order.date`, `order.tracking_number`).
3. **Send-time fallback** — in `FunnelAutomationService::executeSendWhatsAppWaba()`, when the action config has no `template_variables`, read from `$template->variable_mappings` and resolve each mapped field via dot notation against the context. Backward compatible with existing automation actions.

## New mapping values

Customer: `contact.name`, `contact.first_name`, `contact.email`, `contact.phone`, `contact.address`

Order: `order.number`, `order.total`, `order.total_raw`, `order.subtotal`, `order.currency`, `order.status`, `order.items_count`, `order.items_list`, `order.first_item_name`, `order.discount_amount`, `order.coupon_code`, `order.date`, `order.tracking_number`

## Testing

Feature test in `tests/Feature/FunnelAutomationWabaTest.php` (or new file) that:
- creates a `WhatsAppTemplate` with `variable_mappings.body = [1 => 'contact.name', 2 => 'order.number']`,
- triggers `executeSendWhatsAppWaba` with empty action `template_variables`,
- asserts the Meta provider receives components with the resolved contact/order values.
