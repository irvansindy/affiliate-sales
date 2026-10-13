<?php

declare(strict_types=1);

return [
    'label' => 'Transaksi',
    'plural_label' => 'Transaksi',
    'external_ref' => 'Nomor referensi',
    'external_ref_help' => 'Gunakan nomor invoice atau referensi pembayaran yang unik.',
    'product' => 'Produk',
    'affiliate' => 'Affiliator',
    'referral_code' => 'Kode referral affiliator',
    'referral_code_help' => 'Kosongkan bila penjualan tidak berasal dari referral.',
    'source' => 'Sumber',
    'purchase_type' => 'Tipe pembelian',
    'sale_type' => 'Jenis penjualan',
    'subscription_plan' => 'Paket subscription',
    'subscription_cycle' => 'Siklus pembayaran',
    'customer_name' => 'Nama pelanggan',
    'customer_email' => 'Email pelanggan',
    'base_amount' => 'Harga dasar sebelum PPN',
    'discount_amount' => 'Diskon',
    'tax_amount' => 'PPN',
    'total_paid' => 'Total dibayar',
    'total_paid_help' => 'Harga dasar dikurangi diskon, lalu ditambah PPN.',
    'paid_at' => 'Waktu pembayaran',
    'status' => 'Status transaksi',
    'review_status' => 'Perlu ditinjau',
    'review_note' => 'Alasan peninjauan',
    'statuses' => [
        'paid' => 'Dibayar',
        'refunded' => 'Dikembalikan',
        'cancelled' => 'Dibatalkan',
        'review' => 'Perlu ditinjau',
        'clear' => 'Tidak',
    ],
    'sources' => [
        'manual' => 'Input manual',
        'webhook' => 'Webhook',
    ],
    'purchase_types' => [
        'subscription' => 'Subscription',
        'one_time' => 'Beli lepas',
    ],
    'sale_types' => [
        'new' => 'Penjualan awal',
        'renewal' => 'Perpanjangan',
        'plan_change' => 'Perubahan paket',
    ],
    'actions' => [
        'create' => 'Catat transaksi',
        'view' => 'Lihat transaksi',
    ],
    'empty_heading' => 'Belum ada transaksi',
    'empty_description' => 'Catat transaksi manual pertama atau tunggu data dari sistem checkout.',
    'review' => [
        'unknown_referral' => 'Kode referral tidak ditemukan.',
        'inactive_affiliate' => 'Affiliator pemilik kode referral tidak aktif.',
        'self_referral' => 'Pelanggan adalah pemilik kode referral.',
    ],
    'validation' => [
        'discount_exceeds_base' => 'Diskon tidak boleh melebihi harga dasar.',
        'total_mismatch' => 'Total dibayar harus sama dengan harga dasar dikurangi diskon ditambah pajak.',
        'one_time_sale_type' => 'Pembelian beli lepas hanya dapat dicatat sebagai penjualan awal.',
        'initial_subscription_cycle' => 'Penjualan awal subscription harus berada pada siklus pembayaran pertama.',
    ],
];
