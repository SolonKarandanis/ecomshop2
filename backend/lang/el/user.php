<?php

return [
    'status' => [
        'active' => 'Ενεργός',
        'inactive' => 'Ανενεργός',
        'pending' => 'Σε αναμονή έγκρισης',
    ],
    'awaiting_approval' => 'Ο λογαριασμός προμηθευτή σας περιμένει έγκριση. Θα μπορείτε να συνδεθείτε μόλις τον εγκρίνει ένας διαχειριστής.',
    // Stored Notifications keep the key and parameters and are translated when read (#80).
    'notification' => [
        'supplier_awaiting_approval' => 'Ο προμηθευτής :name εγγράφηκε και περιμένει έγκριση.',
        'supplier_approved' => 'Ο λογαριασμός προμηθευτή σας εγκρίθηκε.',
    ],
];
