<?php

namespace App\Services\Marketing;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Data\QRMatrix;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode as Generator;
use chillerlan\QRCode\QROptions;
use ReflectionClass;

/**
 * Kampaniya havolasi uchun QR kod (offline reklama: flayer, banner, stend).
 * Ranglar — sayt palitrasi: modullar siyoh-ko‘k, burchak belgilari lojuvard.
 */
final class QrCode
{
    private const INK = [23, 31, 64];

    private const LAPIS = [35, 67, 184];

    public static function png(string $url, int $scale = 16): string
    {
        return (new Generator(new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'outputBase64' => false,
            'eccLevel' => EccLevel::M,
            'scale' => max(4, min(40, $scale)),
            'quietzoneSize' => 3,
            'moduleValues' => self::colors(fn (array $rgb) => $rgb),
        ])))->render($url);
    }

    public static function svg(string $url): string
    {
        return (new Generator(new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'outputBase64' => false,
            'eccLevel' => EccLevel::M,
            'quietzoneSize' => 3,
            'drawLightModules' => false,
            'svgAddXmlHeader' => true,
            'moduleValues' => self::colors(fn (array $rgb) => vsprintf('#%02x%02x%02x', $rgb)),
        ])))->render($url);
    }

    /** Barcha "to‘q" modul turlari bir xil rangda; finder belgilari — lojuvard. */
    private static function colors(callable $format): array
    {
        $values = [];
        foreach ((new ReflectionClass(QRMatrix::class))->getConstants() as $name => $value) {
            if (! is_int($value) || ! str_starts_with($name, 'M_')) {
                continue;
            }
            if (str_ends_with($name, '_DARK') || $name === 'M_DARKMODULE' || $name === 'M_FINDER_DOT') {
                $values[$value] = $format(str_starts_with($name, 'M_FINDER') ? self::LAPIS : self::INK);
            }
        }

        return $values;
    }
}
