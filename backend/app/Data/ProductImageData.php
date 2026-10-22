<?php

namespace App\Data;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ProductImageData extends BaseData
{
    public function __construct(
        public int $id,
        public string $url,
        public string $thumbnail,
        public string $small,
        public string $large,
    ) {}

    public static function fromModel(Media $media): self
    {
        return new self(
            id: $media->id,
            url: $media->getUrl(),
            thumbnail: $media->getUrl('thumb'),
            small: $media->getUrl('small'),
            large: $media->getUrl('large'),
        );
    }
}
