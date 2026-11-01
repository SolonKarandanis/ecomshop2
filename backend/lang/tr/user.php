<?php

return [
    'status' => [
        'active' => 'Aktif',
        'inactive' => 'Pasif',
        'pending' => 'Onay bekliyor',
    ],
    'awaiting_approval' => 'Tedarikçi hesabınız onay bekliyor. Bir yönetici onayladığında giriş yapabileceksiniz.',
    // Stored Notifications keep the key and parameters and are translated when read (#80).
    'notification' => [
        'supplier_awaiting_approval' => 'Tedarikçi :name kayıt oldu ve onay bekliyor.',
        'supplier_approved' => 'Tedarikçi hesabınız onaylandı.',
    ],
];
