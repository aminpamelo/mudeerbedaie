# Admin Live Host Management Documentation

Dokumentasi lengkap untuk pengurusan Live Host dari perspektif Admin dalam sistem Mudeer BeDaie.

## Gambaran Keseluruhan

Modul Admin Live Host Management membolehkan pengguna dengan role `admin` atau `admin_livehost` untuk:
- Menguruskan pengguna Live Host
- Mengkonfigurasi slot masa live streaming
- Menjadualkan live host ke platform
- Memantau sesi live streaming
- Melihat sesi yang telah dimuat naik

---

## Menu & Navigasi Admin

### Struktur Menu Live Host Management

| Menu | Route | Fungsi |
|------|-------|--------|
| Live Hosts | `/admin/live-hosts` | Senarai dan pengurusan live host |
| Schedule Calendar | `/admin/live-schedule-calendar` | Kalendar jadual spreadsheet |
| Time Slots | `/admin/live-time-slots` | Konfigurasi slot masa |
| Live Sessions | `/admin/live-sessions` | Senarai semua sesi live |
| Session Slots | `/admin/session-slots` | Sesi yang telah dimuat naik |
| Schedule Reports | `/admin/live-schedule-reports` | Laporan jadual |

---

## Halaman & Ciri-ciri

### 1. Live Hosts Management (`/admin/live-hosts`)

Halaman untuk menguruskan pengguna Live Host.

#### Senarai Live Host

**Statistik Pantas (4 Kad)**:
- Total Live Hosts - Jumlah keseluruhan
- Active Hosts - Host yang aktif
- Assigned Platform Accounts - Akaun platform yang diberikan
- Live Sessions Today - Sesi hari ini

**Ciri Penapis**:
| Penapis | Pilihan |
|---------|---------|
| Search | Cari mengikut nama, email, atau telefon |
| Status | active, inactive, suspended |

**Jadual Paparan**:
| Lajur | Keterangan |
|-------|------------|
| Live Host | Avatar, nama, dan ID |
| Contact | Email dan telefon |
| Platform Accounts | Bilangan akaun platform |
| Total Sessions | Jumlah sesi |
| Status | Badge berwarna (Active/Inactive/Suspended) |
| Actions | View, Edit, Delete |

#### Cipta Live Host Baru

**Route**: `/admin/live-hosts/create`

**Medan Form**:
| Medan | Jenis | Wajib | Keterangan |
|-------|-------|-------|------------|
| Name | Text | Ya | Nama penuh host |
| Email | Email | Ya | Alamat email (unik) |
| Phone | Text | Tidak | Nombor telefon |
| Status | Select | Ya | active/inactive/suspended |
| Password | Password | Ya | Kata laluan |
| Confirm Password | Password | Ya | Pengesahan kata laluan |

**Peraturan Pengesahan**:
```php
'name' => ['required', 'string', 'max:255'],
'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
'phone' => ['nullable', 'string', 'max:20'],
'password' => ['required', 'confirmed', Password::defaults()],
'status' => ['required', 'in:active,inactive,suspended'],
```

**Aliran**:
```
Isi Form → Validate → Cipta User (role='live_host') → Redirect ke Detail
```

#### Edit Live Host

**Route**: `/admin/live-hosts/{host}/edit`

**Perbezaan dengan Create**:
- Password adalah pilihan (kosongkan untuk kekal)
- Email menggunakan `Rule::unique()->ignore($host->id)`

#### Padam Live Host

**Syarat**:
- Tidak boleh padam jika host masih ditugaskan ke platform
- Sistem akan memaparkan ralat jika ada tugasan aktif

---

### 2. Time Slots Configuration (`/admin/live-time-slots`)

Halaman untuk mengkonfigurasi slot masa yang tersedia untuk penjadualan.

#### Jenis Slot Masa

| Jenis | Keterangan |
|-------|------------|
| Global | Terpakai untuk semua platform dan semua hari |
| Platform-Specific | Terpakai untuk platform tertentu sahaja |
| Day-Specific | Terpakai untuk hari tertentu sahaja |

#### Ciri-ciri

**Penapis**:
| Penapis | Pilihan |
|---------|---------|
| Platform | All Platforms / Platform tertentu |
| Day | All Days / Global / Hari tertentu |
| Status | All Status / Active / Inactive |

**Jadual Paparan**:
| Lajur | Keterangan |
|-------|------------|
| Platform | Global atau nama akaun |
| Day | Hari tertentu atau "All Days" |
| Time Slot | Masa mula - masa tamat (12 jam) |
| Duration | Tempoh dalam minit |
| Created By | Nama admin atau "System" |
| Status | Toggle aktif/tidak aktif |
| Actions | Edit, Delete |

#### Seed Default Slots

Butang untuk mencipta 8 slot masa global standard:

| # | Slot Masa | Tempoh |
|---|-----------|--------|
| 1 | 06:30 AM - 08:30 AM | 120 minit |
| 2 | 08:30 AM - 10:30 AM | 120 minit |
| 3 | 10:30 AM - 12:30 PM | 120 minit |
| 4 | 12:30 PM - 02:30 PM | 120 minit |
| 5 | 02:30 PM - 04:30 PM | 120 minit |
| 6 | 05:00 PM - 07:00 PM | 120 minit |
| 7 | 08:00 PM - 10:00 PM | 120 minit |
| 8 | 10:00 PM - 12:00 AM | 120 minit |

#### Modal Cipta/Edit Slot

**Medan**:
| Medan | Jenis | Wajib | Keterangan |
|-------|-------|-------|------------|
| Platform Account | Select | Tidak | Kosong = Global |
| Day of Week | Select | Tidak | Kosong = All Days |
| Start Time | Time | Ya | Masa mula |
| End Time | Time | Ya | Masa tamat (selepas mula) |
| Sort Order | Number | Tidak | Susunan paparan |
| Active | Checkbox | Ya | Status aktif |

**Pengesahan**:
- Masa tamat mesti selepas masa mula
- Tiada pertindihan untuk platform/hari yang sama

---

### 3. Live Schedule Calendar (`/admin/live-schedule-calendar`)

**Ciri Utama** - Paparan kalendar spreadsheet untuk menjadualkan live host.

#### Paparan Grid

**Struktur**:
- **Baris**: Slot masa (dari time slots)
- **Lajur**: Hari dalam minggu (SABTU hingga JUMAAT)
- **Sel**: Nama host yang ditugaskan

**Warna Header Platform**:
| Warna | Kod |
|-------|-----|
| Hijau | green-500 |
| Oren | orange-500 |
| Biru | blue-500 |
| Ungu | purple-500 |
| Merah Jambu | pink-500 |

#### Penapis Platform

Pilih platform untuk melihat jadual khusus atau lihat semua platform.

#### Tugasan Host

**Klik pada sel** untuk membuka modal tugasan:

**Modal Kandungan**:
- Maklumat platform, hari, dan slot masa
- Dropdown pemilihan host
- Amaran konflik (jika ada)
- Medan catatan admin

**Pengesanan Konflik**:
Sistem mengesan apabila host ditugaskan ke berbilang platform pada masa yang sama:
- Memaparkan amaran dengan senarai konflik
- Admin boleh menolak atau meneruskan

#### Notifikasi

Apabila jadual diubah, sistem menghantar notifikasi kepada host:

| Jenis | Bila Dihantar |
|-------|---------------|
| assigned | Host baru ditugaskan |
| removed | Host dikeluarkan dari jadual |
| updated | Jadual dikemas kini |

#### Import CSV

**Langkah**:
1. Klik butang "Import CSV"
2. Muat turun template (pilihan)
3. Muat naik fail CSV
4. Semak pratonton
5. Sahkan import

**Format CSV**:
```csv
Platform,Day,Time Slot,Host Name,Host Email,Remarks
AMARMIRZABEDAIE,SABTU,6:30am - 8:30am,Ahmad Razak,ahmad@example.com,Shift pagi
```

**Sokongan Nama Hari**:
- Inggeris: Sunday, Monday, Tuesday, Wednesday, Thursday, Friday, Saturday
- Melayu: AHAD, ISNIN, SELASA, RABU, KHAMIS, JUMAAT, SABTU

**Padanan Host**:
1. Cari melalui email dahulu
2. Jika tiada, cari melalui nama

#### Export CSV

Muat turun jadual semasa sebagai CSV:
- UTF-8 dengan BOM untuk keserasian Excel
- Termasuk semua jadual atau difilter mengikut platform

**Lajur Export**:
| Lajur | Keterangan |
|-------|------------|
| Platform | Nama akaun platform |
| Day | Hari dalam minggu |
| Time Slot | Julat masa |
| Host Name | Nama host |
| Host Email | Email host |
| Remarks | Catatan |
| Status | Active/Inactive |

---

### 4. Live Sessions Index (`/admin/live-sessions`)

Halaman untuk melihat semua sesi live streaming.

#### Statistik (4 Kad)

- Total Sessions
- Upcoming Sessions
- Live Now
- Completed Sessions

#### Penapis

| Penapis | Keterangan |
|---------|------------|
| Search | Cari tajuk, nama host, atau akaun |
| Status | scheduled, live, ended, cancelled |
| Platform | Nama platform |
| Account | Akaun platform |
| Date | Tarikh tertentu |
| Source | admin (Admin Assigned) / self (Self Scheduled) |

#### Jadual Sesi

| Lajur | Keterangan |
|-------|------------|
| Session | Tajuk dengan badge (Admin/Self) |
| Description | Deskripsi (dipotong 50 aksara) |
| Host | Nama host dan akaun platform |
| Platform | Nama platform |
| Scheduled Time | Tarikh dan masa |
| Duration | Tempoh dalam minit |
| Status | Badge berwarna |
| Actions | Butang View |

#### Warna Status

| Status | Warna |
|--------|-------|
| scheduled | Biru |
| live | Hijau |
| ended | Kelabu |
| cancelled | Merah |

#### Pengesanan Source

**Admin Assigned**:
- Tiada jadual dikaitkan, ATAU
- Jadual dicipta oleh orang lain (bukan host)

**Self-Scheduled**:
- Jadual wujud DAN
- Jadual dicipta oleh host sendiri

---

### 5. Uploaded Sessions / Session Slots (`/admin/session-slots`)

Halaman untuk melihat sesi yang telah dimuat naik oleh live host.

#### Dashboard Statistik (4 Kad)

| Kad | Keterangan |
|-----|------------|
| Total Uploaded | Jumlah sesi yang dimuat naik |
| This Week | Sesi minggu ini |
| Total Hours | Jumlah jam streaming |
| Unique Hosts | Bilangan host unik |

#### Penapis

| Penapis | Keterangan |
|---------|------------|
| Search | Cari tajuk atau nama host |
| Host | Host tertentu (aktif sahaja) |
| Platform | Platform tertentu |
| Date From | Tarikh mula |
| Date To | Tarikh tamat |

#### Jadual Sesi Dimuat Naik

| Lajur | Keterangan |
|-------|------------|
| Thumbnail | Gambar sesi atau ikon fallback |
| Session | Tajuk dan tarikh |
| Host | Nama host dengan warna |
| Platform | Badge platform |
| Actual Time | Masa mula dan tamat sebenar |
| Duration | Tempoh dalam minit |
| Uploaded | Bila dimuat naik (relatif) |
| Actions | Butang View |

#### Modal Butiran Sesi

**Kandungan**:
- Screenshot sesi
- Tajuk dan tarikh
- Nama host (dengan warna)
- Platform
- Masa mula dan tamat sebenar
- Tempoh
- Catatan (jika ada)
- Dimuat naik oleh dan bila

#### Export CSV

Muat turun sesi yang sepadan dengan penapis:

**Lajur**:
| Lajur | Keterangan |
|-------|------------|
| Date | Tarikh sesi |
| Host Name | Nama host |
| Platform | Nama platform |
| Title | Tajuk sesi |
| Start Time | Masa mula sebenar |
| End Time | Masa tamat sebenar |
| Duration | Tempoh (minit) |
| Remarks | Catatan |
| Uploaded At | Bila dimuat naik |

---

## Aliran Kerja Admin

### Aliran 1: Cipta dan Urus Live Host

```
┌─────────────────────────────────────────────────────────────────┐
│                     CIPTA LIVE HOST                              │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  1. Admin → Live Hosts List                                      │
│                    ↓                                             │
│  2. Klik butang "Create Live Host"                               │
│                    ↓                                             │
│  3. Isi form (nama, email, telefon, status, password)            │
│                    ↓                                             │
│  4. Sistem validate dan cipta User (role='live_host')            │
│                    ↓                                             │
│  5. Redirect ke halaman detail host                              │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                     EDIT / PADAM HOST                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  [EDIT]                                                          │
│  - Ubah nama, email, telefon, status                             │
│  - Password kosong = kekal                                       │
│                                                                  │
│  [DELETE]                                                        │
│  - Semak tiada tugasan platform                                  │
│  - Jika ada tugasan → Ralat                                      │
│  - Jika tiada → Padam                                            │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Aliran 2: Konfigurasi Slot Masa

```
┌─────────────────────────────────────────────────────────────────┐
│                   KONFIGURASI SLOT MASA                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  1. Admin → Time Slots Page                                      │
│                    ↓                                             │
│  2. [SEED DEFAULT] → Cipta 8 slot global standard                │
│                    ↓                                             │
│  3. Urus slot:                                                   │
│     • [ADD] → Cipta slot baru                                    │
│     • [EDIT] → Ubah masa slot                                    │
│     • [TOGGLE] → Aktif/Tidak aktif                               │
│     • [DELETE] → Padam slot                                      │
│                    ↓                                             │
│  4. Gunakan penapis: Platform | Day | Status                     │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Aliran 3: Jadualkan Live Host (Kalendar)

```
┌─────────────────────────────────────────────────────────────────┐
│                  JADUALKAN LIVE HOST                             │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  1. Admin → Live Schedule Calendar                               │
│                    ↓                                             │
│  2. [SELECT PLATFORM] → Filter paparan                           │
│                    ↓                                             │
│  3. Paparan Grid Spreadsheet:                                    │
│     ┌─────────────────────────────────────────────┐              │
│     │ Time    │ SABTU │ AHAD │ ISNIN │ ...       │              │
│     ├─────────┼───────┼──────┼───────┼───────────┤              │
│     │ 6:30am  │ Ahmad │  -   │ Siti  │ ...       │              │
│     │ 8:30am  │  -    │ Ali  │  -    │ ...       │              │
│     └─────────────────────────────────────────────┘              │
│                    ↓                                             │
│  4. [KLIK SEL] → Modal Tugasan dibuka                            │
│                    ↓                                             │
│  5. Modal:                                                       │
│     • Maklumat platform/hari/masa                                │
│     • Pilih host dari dropdown                                   │
│     • Semak konflik automatik                                    │
│     • Tambah catatan admin                                       │
│                    ↓                                             │
│  6. [SAVE] → Cipta/Kemas kini LiveSchedule                       │
│                    ↓                                             │
│  7. Hantar notifikasi kepada host                                │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Aliran 4: Import Jadual secara Bulk

```
┌─────────────────────────────────────────────────────────────────┐
│                  IMPORT JADUAL CSV                               │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  1. Admin → Live Schedule Calendar                               │
│                    ↓                                             │
│  2. Klik "Import CSV"                                            │
│                    ↓                                             │
│  3. [DOWNLOAD TEMPLATE] → Dapatkan format CSV                    │
│                    ↓                                             │
│  4. Isi CSV dengan data jadual                                   │
│                    ↓                                             │
│  5. Muat naik CSV ke sistem                                      │
│                    ↓                                             │
│  6. Sistem paparkan pratonton:                                   │
│     • Baris valid ✓                                              │
│     • Baris tidak valid ✗ dengan sebab                           │
│                    ↓                                             │
│  7. [CONFIRM IMPORT] → Proses dan simpan                         │
│                    ↓                                             │
│  8. Paparan hasil: X imported, Y skipped                         │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Aliran 5: Pantau Sesi Live

```
┌─────────────────────────────────────────────────────────────────┐
│                   PANTAU SESI LIVE                               │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  1. Admin → Live Sessions Index                                  │
│                    ↓                                             │
│  2. Lihat statistik:                                             │
│     • Total Sessions                                             │
│     • Upcoming                                                   │
│     • Live Now (aktif)                                           │
│     • Completed                                                  │
│                    ↓                                             │
│  3. Gunakan penapis untuk cari sesi tertentu                     │
│                    ↓                                             │
│  4. [VIEW] → Lihat butiran sesi                                  │
│     • Maklumat penuh sesi                                        │
│     • Status semasa                                              │
│     • Analytics (jika ada)                                       │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Aliran 6: Semak Sesi Dimuat Naik

```
┌─────────────────────────────────────────────────────────────────┐
│                SEMAK SESI DIMUAT NAIK                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  1. Admin → Session Slots (Uploaded Sessions)                    │
│                    ↓                                             │
│  2. Lihat dashboard:                                             │
│     • Total Uploaded                                             │
│     • This Week                                                  │
│     • Total Hours                                                │
│     • Unique Hosts                                               │
│                    ↓                                             │
│  3. Filter mengikut host/platform/tarikh                         │
│                    ↓                                             │
│  4. [VIEW] → Modal butiran sesi:                                 │
│     • Screenshot                                                 │
│     • Masa sebenar                                               │
│     • Catatan host                                               │
│     • Info muat naik                                             │
│                    ↓                                             │
│  5. [EXPORT CSV] → Muat turun laporan                            │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Model & Hubungan Pangkalan Data

### Carta Hubungan

```
User (role='live_host')
  │
  ├── hasMany → LiveSchedule (live_host_id)
  │               │
  │               └── hasMany → LiveSession
  │
  ├── hasMany → LiveSession (live_host_id)
  │               │
  │               ├── hasOne → LiveAnalytics
  │               └── hasMany → LiveSessionAttachment
  │
  └── hasMany → LiveTimeSlot (created_by)

PlatformAccount
  │
  ├── hasMany → LiveSchedule
  ├── hasMany → LiveSession
  └── hasMany → LiveTimeSlot
```

### Model LiveSchedule

**Atribut**:
```php
protected $fillable = [
    'platform_account_id',  // FK: platform_accounts
    'live_host_id',         // FK: users (live host)
    'day_of_week',          // 0-6 (Ahad-Sabtu)
    'start_time',           // Format masa
    'end_time',             // Format masa
    'is_recurring',         // Boolean
    'is_active',            // Boolean
    'remarks',              // Catatan admin
    'created_by',           // FK: users (admin)
];
```

**Kaedah Penting**:
```php
// Semak jika jadual dicipta oleh admin
public function isAdminAssigned(): bool
{
    return $this->created_by !== $this->live_host_id;
}

// Dapatkan nama hari
public function getDayNameAttribute(): string
{
    return ['Sunday', 'Monday', ...][this->day_of_week];
}

// Dapatkan julat masa
public function getTimeRangeAttribute(): string
{
    return $this->start_time . ' - ' . $this->end_time;
}
```

### Model LiveSession

**Atribut**:
```php
protected $fillable = [
    'platform_account_id',
    'live_schedule_id',     // FK: live_schedules (nullable)
    'live_host_id',
    'title',
    'description',
    'status',               // scheduled|live|ended|cancelled
    'scheduled_start_at',   // DateTime
    'actual_start_at',      // DateTime
    'actual_end_at',        // DateTime
    'duration_minutes',
    'image_path',           // Laluan screenshot
    'video_link',           // URL video
    'remarks',
    'uploaded_at',          // DateTime
    'uploaded_by',          // FK: users
];
```

**Kaedah Status**:
```php
// Tukar status
public function startLive(): void;
public function endLive(): void;
public function cancel(): void;

// Semak status
public function isScheduled(): bool;
public function isLive(): bool;
public function isEnded(): bool;
public function isCancelled(): bool;

// Semak sumber
public function isAdminAssigned(): bool;
public function isUploaded(): bool;
```

### Model LiveTimeSlot

**Atribut**:
```php
protected $fillable = [
    'platform_account_id',  // null = global
    'day_of_week',          // null = all days
    'start_time',
    'end_time',
    'duration_minutes',     // Auto-calculated
    'is_active',
    'sort_order',
    'created_by',
];
```

**Scopes**:
```php
scopeActive($query)           // Hanya aktif
scopeOrdered($query)          // Ikut sort_order
scopeForPlatform($platformId) // Untuk platform tertentu
scopeForDay($day)             // Untuk hari tertentu
scopeGlobal($query)           // Slot global sahaja
```

---

## Sistem Notifikasi

### ScheduleAssignmentNotification

**Dihantar apabila**:
| Peristiwa | Jenis | Penerima |
|-----------|-------|----------|
| Host ditugaskan | 'assigned' | Host baru |
| Host dikeluarkan | 'removed' | Host lama |
| Jadual dikemas kini | 'updated' | Host semasa |

**Contoh Penggunaan**:
```php
$host->notify(new ScheduleAssignmentNotification($schedule, 'assigned'));
```

---

## Keselamatan & Kebenaran

### Middleware Route

```php
Route::middleware(['auth', 'role:admin,admin_livehost'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // Routes pengurusan live host
    });
```

### Role yang Dibenarkan

| Role | Akses |
|------|-------|
| admin | Penuh akses ke semua ciri |
| admin_livehost | Akses ke pengurusan live host sahaja |

---

## Jadual Pangkalan Data

| Jadual | Fungsi |
|--------|--------|
| `users` | Pengguna dengan role admin/admin_livehost/live_host |
| `platform_accounts` | Akaun platform yang disambung |
| `live_host_platform_account` | Junction table (many-to-many) |
| `live_schedules` | Jadual berulang/sekali |
| `live_sessions` | Sesi live streaming |
| `live_time_slots` | Slot masa yang dikonfigurasi |
| `live_analytics` | Metrik prestasi sesi |
| `live_session_attachments` | Fail lampiran sesi |
| `live_schedule_assignments` | Tugasan jadual (kurang digunakan) |

---

## Peraturan Pengesahan

### Live Host

| Medan | Peraturan |
|-------|-----------|
| name | required, string, max:255 |
| email | required, email, unique, max:255 |
| phone | nullable, string, max:20 |
| password | required (create), confirmed |
| status | required, in:active,inactive,suspended |

### Time Slot

| Medan | Peraturan |
|-------|-----------|
| start_time | required |
| end_time | required, after:start_time |
| - | Tiada pertindihan untuk platform/hari sama |

### Live Schedule

| Medan | Peraturan |
|-------|-----------|
| platform_account_id | required, exists |
| day_of_week | required, integer 0-6 |
| start_time | required |
| end_time | required, after:start_time |

---

## Ciri Import/Export

### Format CSV

**Pengekodan**: UTF-8 dengan BOM (untuk keserasian Excel)

### Import CSV

**Template Baris**:
```csv
Platform,Day,Time Slot,Host Name,Host Email,Remarks
AMARMIRZABEDAIE,SABTU,6:30am - 8:30am,Ahmad Razak,ahmad@example.com,Shift pagi
```

**Sokongan Nama Hari**:
| Inggeris | Melayu |
|----------|--------|
| Sunday | AHAD |
| Monday | ISNIN |
| Tuesday | SELASA |
| Wednesday | RABU |
| Thursday | KHAMIS |
| Friday | JUMAAT |
| Saturday | SABTU |

**Logik Padanan Host**:
1. Cari melalui email dahulu
2. Jika tiada padanan email, cari melalui nama

### Export CSV

**Lajur Output**:
| Lajur | Keterangan |
|-------|------------|
| Platform | Nama akaun platform |
| Day | Hari dalam minggu |
| Time Slot | Julat masa |
| Host Name | Nama host |
| Host Email | Email host |
| Remarks | Catatan |
| Status | Active/Inactive |

---

## Fail Berkaitan

### Views (Livewire Volt)

| Fail | Fungsi |
|------|--------|
| `admin/live-hosts-list.blade.php` | Senarai live host |
| `admin/live-hosts-create.blade.php` | Form cipta host |
| `admin/live-hosts-edit.blade.php` | Form edit host |
| `admin/live-hosts-show.blade.php` | Butiran host |
| `admin/live-schedule-calendar.blade.php` | Kalendar jadual |
| `admin/live-time-slots.blade.php` | Konfigurasi slot masa |
| `admin/live-sessions-index.blade.php` | Senarai sesi |
| `admin/live-sessions-show.blade.php` | Butiran sesi |
| `admin/uploaded-sessions.blade.php` | Sesi dimuat naik |

### Models

| Fail | Fungsi |
|------|--------|
| `app/Models/LiveSchedule.php` | Model jadual |
| `app/Models/LiveSession.php` | Model sesi |
| `app/Models/LiveTimeSlot.php` | Model slot masa |
| `app/Models/LiveAnalytics.php` | Model analytics |
| `app/Models/PlatformAccount.php` | Model akaun platform |

### Notifications

| Fail | Fungsi |
|------|--------|
| `app/Notifications/ScheduleAssignmentNotification.php` | Notifikasi tugasan jadual |

---

## Ringkasan Keupayaan Admin

### Admin Boleh

**Pengurusan Host**:
- ✓ Cipta live host baru
- ✓ Edit maklumat host
- ✓ Aktif/Nyahaktif/Gantung host
- ✓ Padam host (jika tiada tugasan)

**Pengurusan Slot Masa**:
- ✓ Seed slot masa default
- ✓ Cipta slot masa baru (global/specific)
- ✓ Edit slot masa
- ✓ Toggle aktif/tidak aktif
- ✓ Padam slot masa

**Pengurusan Jadual**:
- ✓ Tugaskan host ke slot masa
- ✓ Keluarkan host dari jadual
- ✓ Lihat konflik penjadualan
- ✓ Tambah catatan pada jadual
- ✓ Import jadual dari CSV
- ✓ Export jadual ke CSV

**Pemantauan Sesi**:
- ✓ Lihat semua sesi live
- ✓ Filter mengikut status/platform/tarikh
- ✓ Lihat sesi yang sedang live
- ✓ Lihat butiran sesi

**Sesi Dimuat Naik**:
- ✓ Lihat semua sesi yang dimuat naik
- ✓ Filter mengikut host/platform/tarikh
- ✓ Lihat screenshot dan butiran
- ✓ Export laporan ke CSV

---

## Nota Pembangunan

### Konvensyen Penting

1. **Admin vs Self**: Gunakan `created_by` untuk membezakan jadual admin dan self-scheduled
2. **Notifikasi**: Sentiasa hantar notifikasi apabila jadual diubah
3. **Konflik**: Semak konflik sebelum menyimpan tugasan
4. **CSV**: Gunakan UTF-8 BOM untuk keserasian Excel

### Isu Lazim

1. **Host tidak boleh dipadam**: Semak tugasan platform terlebih dahulu
2. **Import CSV gagal**: Pastikan format nama hari betul (Inggeris/Melayu)
3. **Konflik tidak dikesan**: Pastikan semakan berjalan sebelum simpan

---

*Dokumentasi ini dikemas kini pada Januari 2026*
