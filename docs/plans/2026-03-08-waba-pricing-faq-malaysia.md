# Soalan Lazim (FAQ) — Kos WhatsApp Business API (WABA) Malaysia

> Dikemas kini: 8 Mac 2026

---

## 1. Berapakah kos untuk 1 mesej yang dihantar?

Kos bergantung kepada **kategori mesej** yang dihantar. Berikut adalah kadar untuk **Malaysia** (per mesej):

| Kategori | USD | ~MYR (anggaran) |
|----------|-----|-----------------|
| **Marketing** (promosi, iklan) | $0.0860 | ~RM 0.39 |
| **Utility** (penghantaran sijil, pengesahan pesanan, notifikasi) | $0.0140 | ~RM 0.06 |
| **Authentication** (OTP, pengesahan log masuk) | $0.0140 | ~RM 0.06 |
| **Authentication-International** | $0.0418 | ~RM 0.19 |

**Contoh:** Jika kita hantar sijil kepada 100 pelajar menggunakan template utility:
- Kos = 100 × RM 0.06 = **~RM 6.00 sahaja**

**Nota:** Mulai 1 Julai 2025, Meta menukar model harga daripada "per-perbualan" kepada "per-mesej". Setiap mesej template yang berjaya dihantar akan dicaj mengikut kategori di atas.

---

## 2. Ada bayaran lain tak selain bayaran mesej?

### Bayaran Meta (WhatsApp):
- **Tiada yuran bulanan** daripada Meta/WhatsApp sendiri
- **Tiada caj setup**
- Hanya bayar berdasarkan mesej yang dihantar (pay-as-you-go)
- **1,000 mesej service percuma** setiap bulan (mesej balasan kepada pelanggan dalam 24 jam)

### Perbandingan dengan Respond.io:
| Item | WABA Direct (Sistem Kita) | Respond.io |
|------|---------------------------|------------|
| Yuran platform bulanan | **RM 0 (PERCUMA)** | ~RM 400-800/bulan |
| Kos per mesej utility | ~RM 0.06 | ~RM 0.06 (sama, Meta charge) |
| Kos per mesej marketing | ~RM 0.39 | ~RM 0.39 (sama, Meta charge) |
| WhatsApp Inbox | Dalam sistem sendiri | Dalam Respond.io |

**Kesimpulan:** Dengan menggunakan sistem kita sendiri (direct WABA integration), kita **jimat yuran platform bulanan** yang biasanya dikenakan oleh pihak ketiga seperti Respond.io, SleekFlow, dll. Kita hanya bayar kos mesej Meta sahaja.

---

## 3. Kalau kita blast dan customer reply, kena caj ke bila kita reply balik?

### Jawapan ringkas: **TIDAK** — reply dalam 24 jam adalah **PERCUMA**.

### Penjelasan terperinci:

1. **Kita hantar template (blast)** → Kena caj (cth: RM 0.06 untuk utility)
2. **Customer reply** → Ini membuka **customer service window** selama **24 jam**
3. **Kita reply balik dalam 24 jam** → **PERCUMA** (free-form message)
4. **Setiap kali customer reply lagi** → Window 24 jam **reset semula**

### Senario:
```
09:00  Kita hantar sijil (template utility)     → Caj RM 0.06
09:30  Pelajar reply "Terima kasih"             → PERCUMA (service window dibuka)
09:35  Kita reply "Sama-sama!"                  → PERCUMA (dalam 24 jam)
10:00  Pelajar tanya soalan lain                → PERCUMA (window reset 24 jam)
10:05  Kita jawab soalan                        → PERCUMA (dalam 24 jam)
```

**Penting:** Jika customer service window **sudah tamat** (lebih 24 jam tiada mesej dari pelanggan), kita perlu hantar **template message** semula untuk memulakan perbualan baru — dan ini akan dicaj.

---

## 4. Perlu download app WhatsApp ke atau guna inbox dalam sistem sahaja?

### Jawapan: **Guna WhatsApp Inbox dalam sistem sahaja. Tidak perlu download app.**

### Penjelasan:

- Sistem kita menggunakan **WhatsApp Cloud API** (Meta) secara langsung
- Semua mesej masuk dan keluar diuruskan melalui **WhatsApp Inbox** dalam sistem
- **TIDAK perlu** install atau buka app WhatsApp di telefon
- **TIDAK perlu** scan QR code
- Nombor WhatsApp Business yang digunakan **tidak boleh** digunakan di app WhatsApp pada masa yang sama (satu nombor = satu platform sahaja)

### Kelebihan menggunakan inbox dalam sistem:
- Semua mesej direkod dalam sistem untuk rujukan
- Boleh hantar mesej secara bulk/pukal
- Boleh track status penghantaran (sent, delivered, read)
- Berbilang admin boleh akses inbox yang sama
- Integrasi terus dengan modul sijil, pelajar, dan kelas

---

## Ringkasan Kos Bulanan (Anggaran)

| Item | Kos |
|------|-----|
| Platform Meta/WhatsApp | **PERCUMA** |
| Yuran platform pihak ketiga | **PERCUMA** (guna sistem sendiri) |
| 100 mesej utility (sijil) | ~RM 6 |
| 500 mesej utility (sijil) | ~RM 30 |
| 1,000 mesej utility (sijil) | ~RM 60 |
| Reply pelanggan (dalam 24j) | **PERCUMA** |

**Jumlah kos bergantung kepada jumlah mesej template yang dihantar sahaja.**

---

## Sumber Rujukan

- [WhatsApp Business Platform Pricing (Meta)](https://business.whatsapp.com/products/platform-pricing)
- [Meta Developer Docs - Pricing](https://developers.facebook.com/docs/whatsapp/pricing/)
- [FlowCall - WhatsApp API Pricing 2026](https://www.flowcall.co/blog/whatsapp-business-api-pricing-2026)
- [SleekFlow - Malaysia WhatsApp Business API](https://sleekflow.io/blog/malaysia-whatsapp-business-api-case-study)
