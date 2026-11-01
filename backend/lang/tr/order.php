<?php

return [
    'status' => [
        'draft' => 'Taslak',
        'paid' => 'Ödendi',
        'shipped' => 'Kargoya verildi',
        'delivered' => 'Teslim edildi',
        'cancelled' => 'İptal edildi',
    ],
    'payment' => [
        'status' => [
            'pending' => 'Beklemede',
            'paid' => 'Ödendi',
            'failed' => 'Başarısız',
        ],
    ],
    'shipping' => [
        'method' => [
            'none' => 'Yok',
        ],
    ],
    // Stored Notifications keep the key and parameters and are translated when read (#80).
    'notification' => [
        'created' => '#:order numaralı siparişiniz başarıyla verildi.',
        'payment_confirmed' => '#:order numaralı siparişin ödemesi onaylandı.',
        'payment_failed' => '#:order numaralı siparişin ödemesi başarısız oldu. Lütfen ödeme bilgilerinizi kontrol edin.',
        'shipped' => '#:order numaralı siparişiniz kargoya verildi.',
        'delivered' => '#:order numaralı siparişiniz teslim edildi.',
        'cancelled' => '#:order numaralı siparişiniz iptal edildi.',
        'ready_for_supplier' => 'Hazırlanacak yeni bir siparişiniz var: #:order.',
    ],
];
