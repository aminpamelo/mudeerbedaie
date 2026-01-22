# Live Host Module Documentation

Dokumentasi lengkap untuk modul Live Host dalam sistem Mudeer BeDaie.

## Gambaran Keseluruhan

Modul Live Host membolehkan pengguna dengan role `live_host` untuk menguruskan sesi live streaming mereka. Sistem ini menyokong dua jenis jadual:
- **Admin Assigned** - Jadual yang ditetapkan oleh admin
- **Self-Scheduled** - Jadual yang ditetapkan sendiri oleh live host

---

## Menu & Navigasi

### Struktur Menu Live Host

| Menu | Route | Fungsi |
|------|-------|--------|
| Live Dashboard | `/live-host/dashboard` | Papan pemuka dengan statistik dan ringkasan |
| My Schedule | `/live-host/schedule` | Melihat dan menguruskan jadual |
| My Sessions | `/live-host/sessions` | Senarai semua sesi live |
| Session Slots | `/live-host/session-slots` | Muat naik butiran sesi selepas live |

### Bottom Navigation (Mobile)

Navigasi tetap di bahagian bawah untuk peranti mudah alih:
- Dashboard (ikon rumah)
- Schedule (ikon kalendar)
- Sessions (ikon kamera video)
- Profile (ikon pengguna)

---

## Halaman & Ciri-ciri

### 1. Live Dashboard (`/live-host/dashboard`)

Dashboard utama yang memaparkan:

**Statistik Pantas (6 Kad)**:
- Total Sessions - Jumlah keseluruhan sesi
- Upcoming - Sesi yang akan datang
- Live Now - Sesi yang sedang live
- This Week - Sesi selesai minggu ini
- Active Accounts - Akaun platform yang aktif
- Total Viewers - Jumlah penonton (dari analytics)

**Bahagian-bahagian**:
- **Today's Schedule** - Jadual hari ini dengan masa dan platform
- **Platform Accounts** - Senarai akaun platform yang diberikan
- **Upcoming Sessions** - 5 sesi akan datang terdekat
- **Recent Performance** - Prestasi 5 sesi terakhir dengan analytics

### 2. My Schedule (`/live-host/schedule`)

Halaman jadual dengan 2 tab:

#### Tab: Admin Assigned
- Paparan kalendar mingguan
- Navigasi minggu (minggu sebelum/seterusnya)
- Jadual yang ditetapkan oleh admin (tidak boleh diubah)
- Menunjukkan jadual berulang dan sekali sahaja
- Badge biru "Admin Assigned"

#### Tab: Self Schedule
- Grid slot masa untuk setiap akaun platform
- Klik untuk menanda ketersediaan
- Mencipta/menghapus jadual automatik
- Menjana sesi untuk 7 hari akan datang
- Badge ungu "Self"

### 3. My Sessions (`/live-host/sessions`)

Senarai semua sesi dengan ciri penapis:

**Statistik (4 Kad)**:
- Total Sessions
- Upcoming Sessions
- Live Now
- Completed Sessions

**Penapis Tersedia**:
| Penapis | Pilihan |
|---------|---------|
| Search | Cari mengikut tajuk/deskripsi |
| Status | scheduled, live, ended, cancelled |
| Platform | Semua platform yang ada |
| Date | today, this_week, next_week, this_month, past |
| Source | admin (Admin Assigned), self (Self Scheduled) |

**Jadual Sesi**:
- Session (tajuk, deskripsi)
- Platform (nama akaun)
- Scheduled Time (tarikh dan masa)
- Duration (minit)
- Status (badge berwarna)
- Viewers (jika ada analytics)
- Actions (butang view)

### 4. Session Detail (`/live-host/sessions/{session}`)

Halaman butiran sesi dengan kawalan penuh:

#### Status Control (Kawalan Status)

Butang aksi berdasarkan status semasa:

| Status Semasa | Butang Tersedia |
|---------------|-----------------|
| Scheduled | Start Live, Cancel |
| Live | End Live |
| Ended | Upload Details |
| Cancelled | - |

**Bar Progres Status**:
```
Scheduled → Live → Ended
```

#### Maklumat Sesi
- Platform dan akaun
- Deskripsi sesi
- Masa dijadualkan vs masa sebenar
- Butiran jadual yang dikaitkan
- Badge Admin Assigned / Self

#### Timeline
- Paparan visual kronologi sesi
- Scheduled → Started → Ended/Cancelled

#### Analytics (jika sesi telah tamat)
- Peak Viewers - Penonton tertinggi
- Average Viewers - Purata penonton
- Total Likes - Jumlah like
- Total Comments - Jumlah komen
- Total Shares - Jumlah share
- Gifts Value - Nilai hadiah (RM)
- Engagement Rate - Kadar penglibatan
- Duration - Tempoh sesi

#### Attachments
- Grid fail yang dimuat naik
- Ikon mengikut jenis fail
- Saiz fail dan deskripsi
- Pautan untuk melihat

### 5. Session Slots (`/live-host/session-slots`)

Halaman untuk muat naik butiran sesi:

#### Tab: Pending Upload
- Sesi yang telah tamat tetapi belum dimuat naik
- Butang "Upload" untuk setiap sesi
- Pagination (10 per halaman)

#### Tab: Uploaded
- Sesi yang telah dimuat naik
- Thumbnail, tajuk, platform
- Masa sebenar dan tempoh
- Pautan video
- Penapis tarikh

#### Modal Upload
Form untuk muat naik butiran:
- **Actual Start Time** - Masa mula sebenar
- **Actual End Time** - Masa tamat sebenar
- **Screenshot** - Gambar sesi (drag & drop, max 5MB)
- **Video Link** - URL rakaman video
- **Remarks** - Catatan (pilihan)

---

## Aliran Kerja (Workflows)

### Aliran 1: Self-Schedule Sesi

```
1. Pergi ke /live-host/schedule
2. Pilih tab "Self Schedule"
3. Klik pada slot masa yang dikehendaki
4. Sahkan dalam modal
5. Sistem mencipta jadual dengan created_by = user_id
6. Sistem menjana sesi untuk 7 hari akan datang
7. Sesi muncul di dashboard dan My Sessions
```

### Aliran 2: Menjalankan Live Stream

```
1. Pergi ke dashboard atau session detail
2. Klik "Start Live" apabila bersedia
3. Status berubah: scheduled → live
4. actual_start_at = masa semasa
5. Jalankan live stream di platform
6. Klik "End Live" apabila selesai
7. Status berubah: live → ended
8. actual_end_at = masa semasa
```

### Aliran 3: Muat Naik Butiran Sesi

```
1. Pergi ke /live-host/session-slots
2. Cari sesi di tab "Pending Upload"
3. Klik butang "Upload"
4. Isi form:
   - Masa mula/tamat sebenar
   - Screenshot sesi
   - Pautan video
   - Catatan
5. Hantar form
6. Sistem menyimpan gambar ke /public/live-sessions/
7. Sesi dikemas kini dengan butiran
8. uploaded_at dan uploaded_by ditetapkan
```

### Aliran 4: Membatalkan Sesi

```
1. Pergi ke session detail
2. Klik "Cancel" (hanya jika status scheduled atau live)
3. Sahkan pembatalan
4. Status berubah: → cancelled
5. Sesi tidak boleh dimulakan lagi
```

---

## Status Sesi

### Carta Aliran Status

```
┌─────────────┐
│  Scheduled  │ ← Sesi dicipta dari jadual
└──────┬──────┘
       │ startLive()
       ↓
    ┌─────┐
    │Live │ ← Sedang live streaming
    └──┬──┘
       │ endLive()
       ↓
┌──────────────┐     ┌──────────────┐
│    Ended     │     │  Cancelled   │
└──────────────┘     └──────────────┘
       │                    ↑
       │ uploadDetails()    │ cancel()
       ↓                    │
┌──────────────┐            │
│   Uploaded   │ ← (pilihan)
└──────────────┘
```

### Definisi Status

| Status | Warna Badge | Deskripsi |
|--------|-------------|-----------|
| scheduled | Kuning | Sesi dijadualkan, belum bermula |
| live | Merah | Sesi sedang berlangsung |
| ended | Hijau | Sesi telah tamat |
| cancelled | Kelabu | Sesi dibatalkan |

### Transisi Status

| Dari | Ke | Kaedah | Syarat |
|------|-----|--------|--------|
| scheduled | live | `startLive()` | Hanya jika status = scheduled |
| live | ended | `endLive()` | Hanya jika status = live |
| scheduled/live | cancelled | `cancel()` | Sebelum sesi tamat |
| ended | uploaded | `uploadDetails()` | Selepas sesi tamat |

---

## Admin Assigned vs Self-Scheduled

### Perbezaan Utama

| Aspek | Admin Assigned | Self-Scheduled |
|-------|----------------|----------------|
| Dicipta oleh | Admin | Live Host sendiri |
| `created_by` | null atau != live_host_id | = live_host_id |
| Boleh diubah | Tidak | Ya |
| Tab paparan | "Admin Assigned" | "Self Schedule" |
| Badge | Biru "Admin Assigned" | Ungu "Self" |

### Pengesanan dalam Kod

```php
// Dalam model LiveSchedule
public function isAdminAssigned(): bool
{
    return $this->created_by !== $this->live_host_id;
}

// Dalam komponen
// Admin Assigned: created_by IS NULL OR created_by != live_host_id
// Self-Scheduled: created_by IS NOT NULL AND created_by = live_host_id
```

---

## Model & Hubungan

### LiveSession

**Atribut Utama**:
- `platform_account_id` - Akaun platform
- `live_schedule_id` - Jadual yang dikaitkan
- `live_host_id` - Host sesi
- `title`, `description` - Butiran sesi
- `status` - scheduled/live/ended/cancelled
- `scheduled_start_at` - Masa dijadualkan
- `actual_start_at`, `actual_end_at` - Masa sebenar
- `image_path`, `video_link` - Bukti sesi
- `remarks` - Catatan
- `uploaded_at`, `uploaded_by` - Status muat naik

**Hubungan**:
```php
platformAccount() → PlatformAccount
liveSchedule() → LiveSchedule
liveHost() → User
analytics() → LiveAnalytics
attachments() → LiveSessionAttachment[]
```

### LiveSchedule

**Atribut Utama**:
- `platform_account_id` - Platform
- `live_host_id` - Host yang diberikan
- `day_of_week` - Hari (0-6)
- `start_time`, `end_time` - Julat masa
- `is_recurring` - Berulang atau tidak
- `is_active` - Status aktif
- `created_by` - Pencipta jadual

**Hubungan**:
```php
platformAccount() → PlatformAccount
liveHost() → User
createdBy() → User
liveSessions() → LiveSession[]
```

### PlatformAccount

**Atribut Utama**:
- `platform_id` - Platform (TikTok, Facebook, dll)
- `user_id` - Pemilik akaun
- `name` - Nama akaun (cth: "Admin-TiktokShop")
- `is_active` - Status aktif

**Hubungan**:
```php
platform() → Platform
liveHosts() → User[] (many-to-many)
liveSchedules() → LiveSchedule[]
liveSessions() → LiveSession[]
```

### LiveAnalytics

**Atribut**:
- `viewers_peak` - Penonton tertinggi
- `viewers_avg` - Purata penonton
- `total_likes`, `total_comments`, `total_shares` - Penglibatan
- `gifts_value` - Nilai hadiah
- `duration_minutes` - Tempoh

---

## Keselamatan & Kebenaran

### Middleware Route

```php
Route::middleware(['auth', 'role:live_host'])
    ->prefix('live-host')
    ->name('live-host.')
    ->group(function () {
        // Routes...
    });
```

### Pemeriksaan Tahap Kaedah

```php
// Dalam komponen session-show
if ($session->live_host_id !== auth()->id()) {
    abort(403, 'Unauthorized access to this session.');
}

// Dalam komponen session-upload
if (!$session || $session->live_host_id !== auth()->id()) {
    session()->flash('error', 'Session not found or you do not have permission.');
}
```

---

## Jadual Pangkalan Data

| Jadual | Fungsi |
|--------|--------|
| `users` | Pengguna dengan role 'live_host' |
| `platform_accounts` | Akaun platform yang disambung |
| `live_host_platform_account` | Jadual junction (many-to-many) |
| `live_sessions` | Sesi live streaming |
| `live_schedules` | Jadual berulang/sekali |
| `live_analytics` | Metrik prestasi sesi |
| `live_time_slots` | Slot masa untuk self-schedule |
| `live_session_attachments` | Fail lampiran sesi |

---

## Ringkasan Keupayaan

### Live Host Boleh

- Melihat dashboard peribadi dengan statistik
- Melihat jadual yang ditetapkan admin (baca sahaja)
- Mencipta/membuang jadual sendiri
- Memulakan dan menamatkan sesi live
- Membatalkan sesi sebelum bermula
- Muat naik butiran sesi (masa, gambar, video)
- Melihat analytics sesi
- Melampirkan fail ke sesi
- Menapis sesi dengan pelbagai kriteria

### Admin Boleh

- Mencipta/mengedit/membuang live host
- Menguruskan konfigurasi slot masa
- Mencipta tugasan jadual kalendar
- Melihat semua aktiviti live host
- Menjana laporan jadual
- Menguruskan sesi yang dimuat naik

---

## Fail Berkaitan

### Views (Livewire Volt)

| Fail | Fungsi |
|------|--------|
| `live-host/dashboard.blade.php` | Dashboard utama |
| `live-host/schedule.blade.php` | Halaman jadual |
| `live-host/sessions-index.blade.php` | Senarai sesi |
| `live-host/sessions-show.blade.php` | Butiran sesi |
| `live-host/session-upload.blade.php` | Muat naik sesi |

### Components

| Fail | Fungsi |
|------|--------|
| `components/live-host-nav.blade.php` | Navigasi bawah mobile |

### Models

| Fail | Fungsi |
|------|--------|
| `app/Models/LiveSession.php` | Model sesi live |
| `app/Models/LiveSchedule.php` | Model jadual |
| `app/Models/LiveAnalytics.php` | Model analytics |
| `app/Models/LiveTimeSlot.php` | Model slot masa |
| `app/Models/PlatformAccount.php` | Model akaun platform |

### Routes

Didefinisikan dalam `routes/web.php` (baris 138-145):

```php
Route::middleware(['auth', 'role:live_host'])
    ->prefix('live-host')
    ->name('live-host.')
    ->group(function () {
        Volt::route('dashboard', 'live-host.dashboard')->name('dashboard');
        Volt::route('schedule', 'live-host.schedule')->name('schedule');
        Volt::route('session-slots', 'live-host.session-upload')->name('session-slots');
        Volt::route('sessions', 'live-host.sessions-index')->name('sessions.index');
        Volt::route('sessions/{session}', 'live-host.sessions-show')->name('sessions.show');
    });
```

---

## Nota Pembangunan

### Konvensyen Penting

1. **Paparan Nama Akaun**: Gunakan `platformAccount->name` sahaja (bukan `account_name`)
2. **Status Badge**: Gunakan Flux UI badge dengan warna yang sesuai
3. **Pengesahan Status**: Sentiasa semak status sebelum menukar
4. **Route Names**: Gunakan prefix `live-host.` untuk semua route live host

### Isu Lazim

1. **Route tidak wujud**: Pastikan menggunakan nama route yang betul
   - Betul: `live-host.session-slots`
   - Salah: `live-host.session.upload`

2. **Nama medan salah**: Model PlatformAccount menggunakan `name`, bukan `account_name`

3. **Paparan terlalu panjang**: Untuk jadual hari ini dan upcoming, paparkan nama akaun sahaja tanpa nama platform

---

*Dokumentasi ini dikemas kini pada Januari 2026*
