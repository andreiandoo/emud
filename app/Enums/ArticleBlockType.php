<?php

namespace App\Enums;

enum ArticleBlockType: string
{
    case RichText = 'rich_text';
    case Image = 'image';
    case Gallery = 'gallery';
    case Video = 'video';
    case PartsCarousel = 'parts_carousel';
    case Callout = 'callout';
    case Steps = 'steps';

    public function label(): string
    {
        return match ($this) {
            self::RichText => 'Text',
            self::Image => 'Imagine',
            self::Gallery => 'Galerie',
            self::Video => 'Video',
            self::PartsCarousel => 'Carusel de piese',
            self::Callout => 'Casetă de atenționare',
            self::Steps => 'Pași',
        };
    }
}
