<?php

namespace App\Support;

final class Toast
{
    /**
     * @return array{heading: string, text: string, variant: 'success'|'warning'|'danger', duration: int}
     */
    public static function success(string $heading, string $text, int $duration = 5000): array
    {
        return self::make($heading, $text, 'success', $duration);
    }

    /**
     * @return array{heading: string, text: string, variant: 'success'|'warning'|'danger', duration: int}
     */
    public static function warning(string $heading, string $text, int $duration = 5000): array
    {
        return self::make($heading, $text, 'warning', $duration);
    }

    /**
     * @return array{heading: string, text: string, variant: 'success'|'warning'|'danger', duration: int}
     */
    public static function danger(string $heading, string $text, int $duration = 5000): array
    {
        return self::make($heading, $text, 'danger', $duration);
    }

    /**
     * @param  'success'|'warning'|'danger'  $variant
     * @return array{heading: string, text: string, variant: 'success'|'warning'|'danger', duration: int}
     */
    public static function make(string $heading, string $text, string $variant, int $duration = 5000): array
    {
        if (self::textRepeatsHeading($heading, $text)) {
            $heading = self::defaultHeading($variant);
        }

        return [
            'heading' => $heading,
            'text' => $text,
            'variant' => $variant,
            'duration' => $duration,
        ];
    }

    /**
     * @param  'success'|'warning'|'danger'  $variant
     */
    private static function defaultHeading(string $variant): string
    {
        return match ($variant) {
            'success' => __('Success'),
            'warning' => __('Notice'),
            'danger' => __('Error'),
        };
    }

    private static function textRepeatsHeading(string $heading, string $text): bool
    {
        $normalizedHeading = self::normalize($heading);
        $normalizedText = self::normalize($text);

        if ($normalizedHeading === '' || $normalizedText === '') {
            return false;
        }

        return str_starts_with($normalizedText, $normalizedHeading);
    }

    private static function normalize(string $value): string
    {
        return mb_strtolower(trim($value, " \t\n\r\0\x0B.!?"));
    }
}
