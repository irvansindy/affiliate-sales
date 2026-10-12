# Design System

## Brand Direction

Bersih, profesional, dan terpercaya — karena aplikasi ini menyangkut uang. Prioritas: keterbacaan angka, hierarki jelas, dan aksi yang tidak ambigu. Hindari dekorasi berlebihan. Nuansa: *fintech dashboard* yang ringan.

Implementasi: Filament dengan custom theme (Tailwind CSS 4). Token di bawah dipetakan ke konfigurasi theme Filament dan CSS variables.

## Color Tokens

> Nilai di bawah adalah titik awal; sesuaikan dengan brand perusahaan bila sudah ada.

```css
:root {
  /* Brand */
  --primary:        #4F46E5; /* indigo-600 — aksi utama */
  --primary-hover:  #4338CA;
  --primary-soft:   #EEF2FF;
  --secondary:      #0F172A; /* slate-900 */

  /* Neutral */
  --background:     #F8FAFC;
  --surface:        #FFFFFF;
  --border:         #E2E8F0;
  --text:           #0F172A;
  --text-muted:     #64748B;

  /* Status */
  --success:        #16A34A;
  --warning:        #D97706;
  --danger:         #DC2626;
  --info:           #0284C7;
}

[data-theme="dark"] {
  --background: #0B1120;
  --surface:    #111827;
  --border:     #1F2937;
  --text:       #F1F5F9;
  --text-muted: #94A3B8;
}
```

### Warna status domain

| Entitas | Status | Warna |
|---------|--------|-------|
| Affiliator | pending | warning |
| Affiliator | active | success |
| Affiliator | suspended | danger |
| Komisi | pending | warning |
| Komisi | approved | info |
| Komisi | paid | success |
| Komisi | rejected / cancelled | danger |
| Payout | processing | info |
| Payout | paid | success |
| Payout | failed | danger |
| Prospek CRM | new / contacted | info |
| Prospek CRM | proposal / negotiation | warning |
| Prospek CRM | won | success |
| Prospek CRM | lost | danger |
| Tindak lanjut CRM | lewat jadwal | danger |
| Tindak lanjut CRM | selesai | success |

Status selalu ditampilkan sebagai **badge dengan teks**, bukan hanya warna.

## Typography

- Font: **Inter** (fallback: system-ui, sans-serif)
- Angka tabel/uang: gunakan `font-variant-numeric: tabular-nums`

| Token | Ukuran / Line height | Weight | Pemakaian |
|-------|---------------------|--------|-----------|
| Display | 32px / 40px | Bold | Angka utama dashboard |
| H1 | 28px / 36px | Semibold | Judul halaman |
| H2 | 20px / 28px | Semibold | Judul section/card |
| H3 | 16px / 24px | Semibold | Sub-judul |
| Body | 14px / 20px | Regular | Teks umum, tabel |
| Body-lg | 16px / 24px | Regular | Teks pada mobile |
| Caption | 12px / 16px | Medium | Label, metadata |

## Spacing

Skala 4px: `4, 8, 12, 16, 24, 32, 48, 64`

- Padding card: 16 (mobile) / 24 (desktop)
- Jarak antar section: 24–32
- Jarak label ke input: 4–8

## Radius & Shadows

- Radius: input/button 8px, card 12px, modal 16px, badge penuh (pill)
- Border: 1px solid `--border` sebagai pembeda utama
- Shadow:
  - `sm`: `0 1px 2px rgba(15,23,42,.06)` — card
  - `md`: `0 4px 12px rgba(15,23,42,.10)` — dropdown, popover
  - `lg`: `0 12px 32px rgba(15,23,42,.16)` — modal

## Components

Gunakan komponen Filament sebelum membuat komponen kustom.

- **Button:** Primary, Secondary (outline), Ghost, Danger. Tinggi 40px (36px compact). Aksi destruktif memakai Danger + konfirmasi modal.
- **Input:** selalu ada label, helper text, dan pesan error di bawah field. Placeholder bukan pengganti label.
- **Stat Card:** label (caption), angka (Display), perubahan vs periode sebelumnya (opsional).
- **Table:** header sticky, kolom uang rata kanan, aksi di kolom terakhir, pagination, filter, dan pencarian. Pada mobile, kolom sekunder disembunyikan atau tabel jadi daftar kartu.
- **Badge:** untuk status (lihat warna status domain).
- **Modal / Slide-over:** konfirmasi aksi uang (approve komisi, tandai payout paid) wajib menampilkan ringkasan nominal.
- **Navigation:** sidebar (desktop), drawer (mobile). Item aktif memakai `--primary-soft` + teks `--primary`.
- **Copy-to-clipboard:** untuk kode dan link referral, dengan notifikasi "Tersalin".
- **Toast/Notification:** sukses 4 detik, error tetap sampai ditutup.

### Tampilan CRM

- Daftar kontak dan prospek memakai tabel Filament dengan pencarian, filter, pagination, serta kolom sekunder yang disederhanakan pada ponsel.
- Daftar prospek menampilkan kontak, produk, pemilik (hanya panel admin), tahap, dan jadwal tindak lanjut terdekat. Status ditulis pada badge; warna hanya membantu membedakan.
- Form tindak lanjut menampilkan tanggal/jam, jenis aktivitas, dan ringkasan. Tindak lanjut yang lewat jadwal terlihat jelas tanpa mengubah arti status transaksi atau komisi.
- Empty state menjelaskan tindakan berikutnya, misalnya “Belum ada prospek. Tambahkan kontak untuk mulai mencatat peluang penjualan.” Gunakan pola loading dan error yang sama dengan tabel lainnya.

### Format data

- Uang: `Rp 1.250.000` (tanpa desimal)
- Persen: `10%`
- Tanggal: `12 Okt 2026`; tanggal + jam: `12 Okt 2026, 14:30 WIB`
- Kosong: tampilkan `—`

## States

Setiap komponen interaktif dan tampilan data mendefinisikan:

- **Hover:** background lebih gelap/terang satu tingkat
- **Focus:** ring 2px `--primary` dengan offset 2px (wajib terlihat)
- **Active/pressed:** warna lebih gelap dari hover
- **Disabled:** opacity 50%, `cursor-not-allowed`, tanpa hover
- **Loading:** skeleton untuk tabel/card; spinner pada tombol (tombol dinonaktifkan)
- **Empty:** ilustrasi/ikon sederhana + penjelasan + aksi (mis. "Belum ada penjualan. Bagikan link referral Anda.")
- **Error:** pesan jelas + cara memperbaiki + tombol coba lagi

## Responsive Rules

- Mobile: `< 640px` — satu kolom, sidebar jadi drawer, tabel menjadi daftar kartu
- Tablet: `640–1024px` — dua kolom untuk stat card, sidebar collapsible
- Desktop: `> 1024px` — sidebar tetap, grid 4 kolom untuk stat card
- Pendekatan **mobile-first** (affiliator banyak memakai ponsel)
- Target sentuh minimal 44×44px
- Konten lebar (tabel) scroll di dalam container, bukan di body

## Accessibility

- Kontras teks minimal WCAG AA (4.5:1 teks normal, 3:1 teks besar)
- Informasi tidak boleh hanya dibedakan dengan warna
- Semua elemen interaktif dapat dijangkau dan dioperasikan dengan keyboard
- Gunakan elemen semantik (`button`, `nav`, `main`, `table`, `label`)
- Field form berasosiasi dengan label; error dibacakan lewat `aria-describedby`
- Modal memerangkap fokus dan dapat ditutup dengan `Esc`
- Hormati `prefers-reduced-motion`
- Ikon-only button wajib punya `aria-label`

## Do & Don't

- **Do:** reuse komponen, pakai token, tampilkan angka dengan format konsisten.
- **Don't:** hard-code warna hex di komponen, membuat varian tombol baru tanpa alasan, atau menampilkan angka uang tanpa format Rupiah.
