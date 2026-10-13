<?php

declare(strict_types=1);

return [
    'unavailable' => 'Webhook belum dikonfigurasi.',
    'unauthorized' => 'Autentikasi webhook gagal.',
    'invalid_payload' => 'Payload webhook tidak valid.',
    'unknown_product' => 'Produk tidak dikenal atau tipenya tidak sesuai.',
    'duplicate' => 'Event sudah pernah diproses.',
    'accepted' => 'Event diterima untuk diproses.',
    'processing_failed' => 'Pemrosesan webhook gagal. Periksa failed jobs untuk rincian.',
    'refund_not_allowed' => 'Status transaksi tidak mengizinkan refund.',
    'refund_exceeds_payment' => 'Nominal refund melebihi total pembayaran.',
    'sale_not_ready' => 'Transaksi pembayaran belum tersedia. Coba kirim ulang webhook refund.',
];
