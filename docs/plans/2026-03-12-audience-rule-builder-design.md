# Audience Rule Builder - Advanced Segmentation

**Date**: 2026-03-12
**Status**: Approved

## Problem

The audience create/edit pages load all 2,226+ students at once, causing HTTP 500 errors. The existing simple filters (search, status, country, has orders) are insufficient for meaningful audience segmentation like "students who spent more than RM 4,000."

## Solution

Replace the simple filters with an inline rule builder that supports AND/OR logic across spending, enrollment, and demographic criteria. Audiences remain static (fixed student lists), but the rule builder provides powerful filtering at creation/edit time.

## Design

### UI Layout

```
[Audience Name / Description / Status fields]

[Rule Builder Section]
  Match [ALL / ANY] of the following:

  Row 1: [Field ▼] [Operator ▼] [Value input] [× Remove]
  Row 2: [Field ▼] [Operator ▼] [Value input] [× Remove]
  [+ Add Rule]

  Matching: X of Y students
  [Apply Rules]  [Clear Rules]

[Student List - paginated, 50 per page]
  [Select All Matching (X)] [Deselect All]
  ☐ Student rows...
  Pagination...
```

### Available Rules

#### Spending & Orders
| Field | Operators | Value Type |
|-------|-----------|------------|
| Total Spending | >, <, >=, <=, =, between | Currency (RM) |
| Order Count | >, <, >=, <=, =, between | Number |
| Last Order Date | before, after, in last X days | Date |
| Has Paid Orders | yes, no | Boolean |

#### Enrollment & Courses
| Field | Operators | Value Type |
|-------|-----------|------------|
| Enrollment Count | >, <, >=, <=, = | Number |
| Enrolled In Course | is, is not | Course dropdown |
| Enrollment Status | is, is not | Status dropdown (enrolled/active/completed/dropped/suspended/pending) |
| Subscription Status | is, is not | Status dropdown (active/trialing/past_due/canceled/unpaid) |

#### Demographics & Profile
| Field | Operators | Value Type |
|-------|-----------|------------|
| Student Status | is, is not | Status dropdown (active/inactive/graduated/suspended) |
| Country | is, is not | Country dropdown (from existing data) |
| State | is, is not | Text input |
| Gender | is, is not | Gender dropdown |
| Age | >, <, >=, <=, between | Number (calculated from date_of_birth) |
| Registered Date | before, after, in last X days | Date |

### Technical Implementation

#### Livewire State
```php
public array $rules = [];        // [{field, operator, value, value2}]
public string $matchMode = 'all'; // 'all' (AND) or 'any' (OR)
```

#### Query Building
- Private `buildRulesQuery()` method translates rules into Eloquent queries
- Spending aggregates use subqueries via `addSelect()` for performance (no eager loading)
- Enrollment/course rules use `whereHas()` with appropriate conditions
- Demographics query directly on student fields
- Match mode controls whether conditions use `where()` (AND) or `orWhere()` (OR)

#### Performance
- "Apply Rules" button triggers query (not live on every change)
- Pagination at 50 students per page
- Subqueries for aggregates avoid loading full relationship data
- Count query runs separately from paginated query

#### Key Methods
- `addRule()` - Add empty rule row
- `removeRule($index)` - Remove rule row
- `applyRules()` - Build query and refresh student list
- `clearRules()` - Reset all rules
- `selectAll()` - Select all matching students (across all pages)
- `deselectAll()` - Clear selection

### Files to Modify
- `resources/views/livewire/crm/audience-create.blade.php` - Replace simple filters with rule builder
- `resources/views/livewire/crm/audience-edit.blade.php` - Same changes as create

### No New Models or Migrations
Rules are transient UI state only. The audience saves a static student list via the existing `audience_student` pivot table.
