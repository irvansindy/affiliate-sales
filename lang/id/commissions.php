<?php

declare(strict_types=1);

return [
    'label' => 'Komisi',
    'plural_label' => 'Komisi',
    'transaction' => 'Transaksi',
    'affiliate' => 'Affiliator',
    'product' => 'Produk',
    'base_amount' => 'Harga dasar sebelum PPN',
    'rate' => 'Tarif komisi',
    'amount' => 'Nominal komisi',
    'status' => 'Status',
    'approvable_at' => 'Batas masa tunggu',
    'ready_for_review_at' => 'Ditandai siap pada',
    'approved_at' => 'Disetujui pada',
    'statuses' => [
        'pending' => 'Menunggu',
        'approved' => 'Disetujui',
        'paid' => 'Dibayar',
        'rejected' => 'Ditolak',
        'cancelled' => 'Dibatalkan',
    ],
    'actions' => [
        'view' => 'Lihat komisi',
        'approve' => 'Setujui',
        'reject' => 'Tolak',
    ],
    'confirmation' => 'Nominal komisi: :amount. Pastikan transaksi telah ditinjau sebelum melanjutkan.',
    'changed' => 'Status komisi diperbarui.',
    'retry' => 'Muat ulang halaman dan tinjau status transaksi serta komisi.',
    'empty_heading' => 'Belum ada komisi',
    'empty_description' => 'Komisi penjualan awal akan muncul setelah transaksi tercatat.',
    'invalid_transition' => 'Status komisi tidak dapat diubah dari kondisi saat ini.',
    'not_ready' => 'Masa tunggu komisi belum selesai.',
    'marked_ready' => ':count komisi siap ditinjau.',
];
