# Live Host Pocket — Profile avatar upload + role label fix

Date: 2026-04-19
Scope: `/live-host/me` (Inertia + React Pocket app)

## Motivation

Two small improvements to the Pocket "You" tab:

1. Role displays as raw enum (`LIVE_HOST`) — unreadable. Should render as `Live Host`.
2. Hosts cannot set a profile picture; the avatar is always initials.

## Changes

### Backend

- **Migration**: `users.avatar_path` — nullable string, MySQL+SQLite safe (plain new column).
- **User model**:
  - Add `avatar_path` to `$fillable`.
  - Append `avatar_url` to `$appends`.
  - Add `getAvatarUrlAttribute(): ?string` — returns `asset('storage/'.$this->avatar_path)` or `null`.
- **Routes** (inside existing `live_host` Pocket group in `routes/web.php`):
  - `POST  /live-host/me/avatar` → `ProfileController@uploadAvatar`
  - `DELETE /live-host/me/avatar` → `ProfileController@destroyAvatar`
- **ProfileController**:
  - `show()` now passes `role` as `$user->role_name` (the formatted accessor) and adds `avatarUrl`.
  - `uploadAvatar(Request)` — validate image (mimes: jpg/jpeg/png/webp, max 2048KB), delete old file if any, store under `user-avatars/` on `public` disk, persist `avatar_path`, redirect back with success flash.
  - `destroyAvatar(Request)` — delete file, null the column, redirect back.

### Frontend — `resources/js/livehost-pocket/pages/Profile.jsx`

- Avatar circle becomes a `<label>` wrapping a hidden `<input type="file" accept="image/*">`.
- If `avatarUrl` present → render `<img>` filling the circle; otherwise the existing initials treatment.
- Camera/pencil badge overlay in bottom-right of circle as affordance.
- On file change: `router.post('/live-host/me/avatar', { avatar: file }, { forceFormData: true, preserveScroll: true })` with local `uploading` state driven by `onStart`/`onFinish`.
- When `avatarUrl` is present, show a small "Remove photo" text button below the card; on click, confirm then `router.delete(...)` .
- Role row: drop `mono: true` so the label renders as readable text. Status keeps `mono: true` (uppercase status token reads fine).

### Tests — `tests/Feature/LiveHostPocket/ProfileTest.php`

Add cases:

- Role label is formatted (e.g. `Live Host`) in the Inertia props.
- Upload stores file under `user-avatars/` and sets `avatar_path`.
- Upload replaces an existing file (old file deleted from disk).
- Destroy deletes the file and nulls the column.
- Validation: rejects non-image uploads and files over 2MB.

Use `Storage::fake('public')` and `UploadedFile::fake()->image(...)`.

## Out of scope

- Avatar display outside the Pocket (header, admin lists, etc.).
- Image cropping / resizing pipeline.
- Applying the role-label fix to other Pocket surfaces.
