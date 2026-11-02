<?php

return [
    // An unexpected failure (a bug or an outage): the API's generic 500 message.
    'server_error' => 'Bizim tarafımızda bir sorun oluştu. Lütfen biraz sonra tekrar deneyin.',
    // Rate-limited endpoints (429); the Retry-After header says how long.
    'too_many_requests' => 'Çok fazla deneme yapıldı. Lütfen biraz bekleyip tekrar deneyin.',
    'export_orders' => [
        'limit_error' => 'Tek seferde 10.000\'den fazla sipariş dışa aktaramazsınız. Mevcut sonuç sayısı: :count',
    ],
    'update_profile' => [
        'email_taken' => 'Bu e-posta adresi başka bir hesap tarafından kullanılıyor.',
    ],
    'change_password' => [
        'wrong_current' => 'Girdiğiniz mevcut parola yanlış.',
    ],
    'submit_review' => [
        'not_eligible' => 'Bu ürünü değerlendiremezsiniz.',
    ],
    'cart_exceptions' => [
        'empty_cart' => 'Sepet boş',
        'supplier_mismatch' => 'Sepetiniz aynı anda yalnızca tek bir tedarikçinin ürünlerini içerebilir. Bu ürünü eklemek için sepetinizi boşaltın.',
    ],
    'cart_notices' => [
        'saved_cart_replaced' => 'Daha önce kaydettiğiniz sepet farklı bir tedarikçiye aitti ve değiştirildi.',
    ],
    'order_exceptions' => [
        'transition_not_allowed' => 'Bu sipariş mevcut durumundan güncellenemez.',
        'supplier_mismatch' => 'Bir sipariş yalnızca tek bir tedarikçinin ürünlerini içerebilir. Kaydetmeden önce diğer tedarikçinin ürünlerini çıkarın.',
    ],
    'payment_exceptions' => [
        'create_stripe_session' => 'Stripe oturumu oluşturulamadı',
        'retrieve_stripe_session' => 'Stripe oturumu alınamadı',
        'unsupported_payment_method' => 'Desteklenmeyen ödeme yöntemi: :method',
    ],
    'product_exceptions' => [
        'not_found' => ':id kimlikli ürün bulunamadı.',
        'ordered_cannot_be_deleted' => 'Bu ürün mevcut siparişlerde yer aldığı için silinemez. Katalogdan gizlemek için ürünü pasif hale getirin.',
    ],
    'supplier_products' => [
        'option_not_of_attribute' => 'Seçilen seçenek, seçilen özelliğe ait değil.',
        'duplicate_attribute_option' => 'Bu özellik seçeneği zaten eklendi.',
        'too_many_images' => 'Bir ürünün en fazla :max görseli olabilir.',
    ],
];
