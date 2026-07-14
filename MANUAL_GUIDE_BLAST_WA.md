# Panduan Penggunaan WhatsApp Blast

Panduan ini digunakan untuk mengirim WhatsApp blast menggunakan template `blast_wa`.

## 1. Buka Aplikasi

Buka aplikasi melalui:

```text
https://wablast.lestarimemorialpark.com
```

Login menggunakan username dan password yang sudah diberikan.

## 2. Sinkron Template

1. Buka menu `Templates`.
2. Klik tombol `Sync Templates`.
3. Tunggu sampai proses selesai.
4. Pastikan template `blast_wa` tersedia.

Jika template belum muncul, klik `Sync Templates` sekali lagi.

## 3. Buat Campaign Baru

1. Buka menu `Create Campaign`.
2. Isi nama campaign.
3. Pilih template `blast_wa`.
4. Klik `Save Draft`.

Gunakan nama campaign yang mudah dikenali, misalnya:

```text
Blast Chao Du Juli 2026
```

## 4. Siapkan File Penerima

File penerima boleh menggunakan format:

- CSV
- XLSX

Contoh isi file:

```text
Nama Customer | Nomor WA
Budi          | 6281234567890
Ayu           | 6281234567891
```

Aturan nomor WhatsApp:

- Nomor harus diawali `62`.
- Jangan menggunakan awalan `0`.
- Jangan menggunakan `+62`.
- Jangan ada nomor yang sama lebih dari satu kali.

Contoh benar:

```text
6281234567890
```

Contoh salah:

```text
081234567890
+6281234567890
```

## 5. Upload File Penerima

1. Buka campaign yang sudah dibuat.
2. Upload file penerima.
3. Tunggu sampai ringkasan data muncul.

Periksa bagian ringkasan:

- Total data.
- Data valid.
- Data invalid.
- Data duplikat.
- Data yang bisa dikirim.

Jika kolom nomor atau nama belum sesuai, pilih kolom yang benar pada bagian mapping kolom.

## 6. Cek Preview

Sebelum mengirim, cek bagian preview.

Pastikan:

- Nama penerima benar.
- Nomor WhatsApp benar.
- Isi pesan sudah sesuai.
- Template yang digunakan adalah `blast_wa`.

## 7. Kirim Sekarang

1. Cek jumlah data yang bisa dikirim.
2. Centang konfirmasi persetujuan penerima.
3. Klik tombol kirim.
4. Konfirmasi pengiriman.

Setelah dikirim, campaign akan mulai diproses.

Catatan:

- Sistem hanya mengirim maksimal 250 penerima unik dalam 24 jam.
- Jika penerima lebih dari 250, sisanya akan lanjut otomatis setelah kuota tersedia.
- Tidak perlu upload ulang file.

Status penerima akan berubah secara bertahap, misalnya:

- queued
- accepted
- sent
- delivered
- read
- failed

## 8. Jadwalkan Pengiriman

Jika ingin mengirim nanti:

1. Pilih tanggal dan jam pengiriman.
2. Centang konfirmasi persetujuan penerima.
3. Klik tombol schedule.

Campaign yang masih terjadwal bisa diubah atau dibatalkan sebelum proses pengiriman dimulai.

## 9. Cek Hasil Pengiriman

Buka detail campaign untuk melihat:

- Jumlah terkirim.
- Jumlah delivered.
- Jumlah read.
- Jumlah failed.
- Alasan gagal jika ada.

## 10. Retry Pesan Gagal

Jika ada pesan gagal:

1. Buka detail campaign.
2. Klik `Retry Failed`.
3. Konfirmasi retry.

Sistem hanya akan mengirim ulang pesan yang gagal.

Pesan yang sudah berhasil tidak akan dikirim ulang.

## 11. Catatan Penting

- Pastikan file penerima sudah benar sebelum dikirim.
- Pastikan nomor WhatsApp menggunakan format `62`.
- Jangan menutup halaman saat upload file belum selesai.
- Jika ada status `failed`, cek alasan gagal di detail campaign.
- Jika template `blast_wa` tidak muncul, lakukan `Sync Templates`.
