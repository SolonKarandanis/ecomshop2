<?php

return [
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
        'creation_error' => 'Değerlendirme oluşturulamadı!',
        'update_error' => 'Değerlendirme güncellenemedi!',
        'update_status_error' => 'Değerlendirmenin durumu güncellenemedi!',
        'update_admin_reply_error' => 'Yönetici yanıtı güncellenemedi!',
    ],
    'cart_exceptions' => [
        'save_cart' => 'Sepet kaydedilemedi',
        'update_items' => 'Sepetteki ürünler güncellenemedi',
        'delete_items' => 'Sepetteki ürünler silinemedi',
        'clear_cart' => 'Sepet boşaltılamadı',
        'empty_cart' => 'Sepet boş',
        'supplier_mismatch' => 'Sepetiniz aynı anda yalnızca tek bir tedarikçinin ürünlerini içerebilir. Bu ürünü eklemek için sepetinizi boşaltın.',
    ],
    'cart_notices' => [
        'saved_cart_replaced' => 'Daha önce kaydettiğiniz sepet farklı bir tedarikçiye aitti ve değiştirildi.',
    ],
    'order_exceptions' => [
        'create_order' => 'Yeni sipariş oluşturulamadı',
        'checkout' => 'Ödeme sırasında bir şeyler ters gitti!',
        'stripe_processing' => 'Stripe siparişi işlenirken bir şeyler ters gitti!',
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
