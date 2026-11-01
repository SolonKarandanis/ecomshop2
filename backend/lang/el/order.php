<?php

return [
    'status' => [
        'draft' => 'Πρόχειρη',
        'paid' => 'Πληρωμένη',
        'shipped' => 'Απεσταλμένη',
        'delivered' => 'Παραδομένη',
        'cancelled' => 'Ακυρωμένη',
    ],
    'payment' => [
        'status' => [
            'pending' => 'Σε εκκρεμότητα',
            'paid' => 'Πληρώθηκε',
            'failed' => 'Απέτυχε',
        ],
    ],
    'shipping' => [
        'method' => [
            'none' => 'Κανένας',
        ],
    ],
    // Stored Notifications keep the key and parameters and are translated when read (#80).
    'notification' => [
        'created' => 'Η παραγγελία σας #:order υποβλήθηκε με επιτυχία.',
        'payment_confirmed' => 'Η πληρωμή για την παραγγελία #:order επιβεβαιώθηκε.',
        'payment_failed' => 'Η πληρωμή για την παραγγελία #:order απέτυχε. Ελέγξτε τα στοιχεία πληρωμής σας.',
        'shipped' => 'Η παραγγελία σας #:order απεστάλη.',
        'delivered' => 'Η παραγγελία σας #:order παραδόθηκε.',
        'cancelled' => 'Η παραγγελία σας #:order ακυρώθηκε.',
        'ready_for_supplier' => 'Έχετε νέα παραγγελία προς εκτέλεση: #:order.',
    ],
];
