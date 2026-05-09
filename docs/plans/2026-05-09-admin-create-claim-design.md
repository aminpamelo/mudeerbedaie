# Admin: Create Claim from Claim Requests Page

Date: 2026-05-09
Surface: `/hr/claims/requests` (HR React SPA)

## Goal

Allow admins to file a claim on behalf of any employee directly from the Claim
Requests page, without going through the employee My Claims flow.

## Decisions

- **Owner**: admin picks any employee (required field).
- **Initial status**: `pending` (auto-submitted for approval). Same notification
  fan-out as `HrMyClaimController::submit()` — notify the employee's claim
  approvers and admin users (skip the creating admin to avoid self-notification).
- **UX**: "+ New Claim" primary button in the toolbar opens a dialog. Modal
  matches the existing Detail / Action dialog style on the same page.
- **Form**: same fields as employee My Claims form (claim type, mileage fields
  when applicable, claim date, description, optional receipt) plus a
  prepended Employee picker.

## Backend

- New `App\Http\Requests\Hr\StoreAdminClaimRequestRequest`
  - Same rules as `StoreClaimRequestRequest` plus
    `employee_id => required|exists:employees,id`.
- New `HrClaimRequestController::store(StoreAdminClaimRequestRequest $request)`
  - Wrap in `DB::transaction()`.
  - Resolve `ClaimType`; if mileage, resolve `ClaimTypeVehicleRate` and
    auto-calculate amount = `distance_km * rate_per_km`.
  - Compute the same monthly/yearly limit warning the employee flow does.
  - Store receipt under `claim-receipts/{employee_id}/` if uploaded.
  - Create `ClaimRequest` with `status = 'pending'`, `submitted_at = now()`.
  - Notify the employee's `ClaimApprover`s + admin users via
    `ClaimSubmitted`, skipping the creating admin's user id.
  - Return `201` with `data`, `message`, optional `warning`.
- New route: `POST /hr/claims/requests` → `name api.hr.claims.requests.store`.

## Frontend

- `resources/js/hr/lib/api.js` — add
  `createAdminClaimRequest(formData)` posting `multipart/form-data` to
  `/claims/requests`.
- `resources/js/hr/pages/claims/ClaimRequests.jsx`
  - Add primary "+ New Claim" button to the toolbar.
  - Add `<NewClaimDialog />` (inline component or sibling) using existing
    Dialog primitives:
    - Employee select (search, fed by `fetchEmployees({ per_page: 200 })`).
    - Claim type select (existing `fetchClaimTypes` query).
    - When mileage type: vehicle rate select, distance, origin, destination,
      trip purpose, calculated amount preview.
    - When non-mileage: amount input.
    - Claim date, description, receipt file input.
  - On success: invalidate `['hr', 'claims', 'requests']`, close dialog,
    reset form. Show inline error / warning banner.

## Validation / errors

- Server-side validation surfaces field errors; mirror in form.
- Mileage / non-mileage fields rendered conditionally based on selected type.

## Tests (Pest feature)

- Admin can create a pending claim for any employee (claim_number generated,
  `submitted_at` set, `ClaimSubmitted` notification dispatched).
- Mileage claim auto-calculates amount.
- Non-admin user gets 403.
- Validation errors when employee_id missing.

## Out of scope (YAGNI)

- Bulk create / CSV import.
- `approved` / `draft` initial status options.
- Editing admin-created claims through this dialog (existing approve / reject
  / pay flow handles lifecycle).
