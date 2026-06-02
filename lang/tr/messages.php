<?php

declare(strict_types=1);

return [
    'title' => 'Çeviriler',
    'modes' => [
        'json' => 'JSON dosyaları',
        'php' => 'PHP dizi dosyaları',
    ],
    'actions' => [
        'save' => 'Kaydet',
        'add_key' => 'Anahtar ekle',
        'add_locale' => 'Dil ekle',
        'delete' => 'Sil',
        'rename' => 'Yeniden adlandır',
        'copy_from_reference' => 'Referanstan kopyala',
    ],
    'filters' => [
        'search' => 'Anahtar ara…',
        'missing_only' => 'Yalnızca eksikler',
    ],
    'columns' => [
        'key' => 'Anahtar',
        'target' => 'Hedef',
        'reference' => 'Referans',
    ],
    'messages' => [
        'saved' => 'Çeviriler kaydedildi.',
        'conflict' => 'Bu dosyalar siz yükledikten sonra diskte değişti. Devam etmek için yeniden yükleyin.',
        'nothing_to_save' => 'Kaydedilecek değişiklik yok.',
    ],
];
