<?php

declare(strict_types=1);

return [
    'label' => 'Produk',
    'plural_label' => 'Produk',
    'code' => 'Kode produk',
    'code_help' => 'Kode unik yang digunakan sistem checkout dan webhook.',
    'name' => 'Nama produk',
    'name_help' => 'Nama yang mudah dikenali oleh admin dan affiliator.',
    'landing_url' => 'URL halaman produk',
    'landing_url_help' => 'Tujuan link referral. Gunakan URL lengkap http atau https; kosongkan jika produk belum siap dipromosikan.',
    'type' => 'Tipe produk',
    'type_help' => 'Pilih kategori produk yang dijual.',
    'is_active' => 'Produk aktif',
    'is_active_help' => 'Produk nonaktif tetap tersimpan, tetapi tidak digunakan untuk transaksi baru.',
    'status' => 'Status',
    'updated_at' => 'Terakhir diperbarui',
    'types' => [
        'website' => 'Website',
        'mobile_app' => 'Mobile App',
    ],
    'statuses' => [
        'active' => 'Aktif',
        'inactive' => 'Nonaktif',
    ],
    'actions' => [
        'create' => 'Tambah produk',
        'edit' => 'Ubah',
    ],
    'empty_heading' => 'Belum ada produk',
    'empty_description' => 'Tambahkan produk pertama agar dapat dipilih saat mencatat transaksi.',
];
