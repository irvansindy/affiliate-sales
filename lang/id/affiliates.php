<?php

declare(strict_types=1);

return [
    'label' => 'Affiliator',
    'plural_label' => 'Affiliator',
    'name' => 'Nama',
    'email' => 'Email',
    'phone' => 'Nomor telepon',
    'status' => 'Status',
    'referral_code' => 'Kode referral',
    'approved_at' => 'Terakhir disetujui',
    'bank_name' => 'Bank',
    'bank_account_name' => 'Nama pemilik rekening',
    'bank_account_number' => 'Nomor rekening',
    'view' => 'Lihat detail',
    'statuses' => [
        'pending' => 'Menunggu persetujuan',
        'active' => 'Aktif',
        'suspended' => 'Ditangguhkan',
        'rejected' => 'Ditolak',
    ],
    'actions' => [
        'approve' => 'Setujui',
        'reactivate' => 'Aktifkan kembali',
        'reject' => 'Tolak',
        'suspend' => 'Tangguhkan',
    ],
    'confirmation' => 'Konfirmasi perubahan status affiliator. Perubahan akan dicatat dalam log audit.',
    'changed' => 'Status affiliator diperbarui.',
    'invalid_transition' => 'Status affiliator tidak dapat diubah dari kondisi saat ini.',
    'retry' => 'Muat ulang halaman untuk melihat status terbaru, lalu coba kembali.',
    'empty_heading' => 'Belum ada affiliator yang sesuai',
    'empty_description' => 'Pendaftaran baru akan muncul di sini. Jika memakai filter, hapus filter atau pencarian untuk melihat semua affiliator.',
    'registration_pending' => 'Pendaftaran berhasil. Akun Anda menunggu persetujuan admin. Anda dapat masuk setelah akun disetujui.',
];
