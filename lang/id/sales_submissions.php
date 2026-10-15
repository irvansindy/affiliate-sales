<?php

declare(strict_types=1);

return [
    'label' => 'Pengajuan penjualan',
    'plural_label' => 'Pengajuan penjualan',
    'submit' => 'Ajukan penjualan',
    'approve' => 'Setujui dan catat transaksi',
    'reject' => 'Tolak pengajuan',
    'proof' => 'Bukti pembayaran',
    'view_proof' => 'Lihat bukti pembayaran',
    'claimed_ref' => 'Referensi pembayaran yang diklaim',
    'rejection_reason' => 'Alasan penolakan',
    'submitted_at' => 'Diajukan pada',
    'empty' => 'Belum ada pengajuan penjualan',
    'approved_success' => 'Pengajuan disetujui dan transaksi dicatat.',
    'rejected_success' => 'Pengajuan ditolak.',
    'rate_settings' => 'Pengaturan komisi',
    'rate' => 'Rate komisi (%)',
    'rate_help' => 'Berlaku hanya untuk komisi dari transaksi baru. Komisi lama tidak berubah.',
    'rate_saved' => 'Rate komisi berhasil diperbarui.',
    'save_rate' => 'Simpan rate',
    'status' => ['pending' => 'Menunggu verifikasi', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'],
    'validation' => [
        'proof' => 'Bukti pembayaran wajib berupa PDF, JPG, atau PNG (maksimal 5 MB).',
        'self_referral' => 'Penjualan kepada akun sendiri tidak dapat diajukan.',
        'affiliate' => 'Affiliator tidak aktif atau atribusi penjualan tidak valid.',
        'already_reviewed' => 'Pengajuan ini sudah diproses.',
    ],
];
