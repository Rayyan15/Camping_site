<?php

namespace Tests\Unit;

use App\Services\WhatsApp\PhoneNumberNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNumberNormalizerTest extends TestCase
{
    /**
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public static function numbers(): array
    {
        return [
            'leading zero' => ['081234567890', '6281234567890'],
            'plus 62' => ['+62 812-3456-7890', '6281234567890'],
            'bare 62' => ['6281234567890', '6281234567890'],
            'no prefix' => ['81234567890', '6281234567890'],
            'spaces and dashes' => ['0812 3456-7890', '6281234567890'],
            'too short' => ['0812', null],
            'not mobile' => ['0215551234', null],
            'foreign' => ['+14155550123', null],
            'empty' => ['', null],
            'null' => [null, null],
            'letters' => ['abc', null],
        ];
    }

    #[DataProvider('numbers')]
    public function test_normalize(?string $raw, ?string $expected): void
    {
        $this->assertSame($expected, (new PhoneNumberNormalizer)->normalize($raw));
    }

    public function test_mask_hides_the_middle_digits(): void
    {
        $normalizer = new PhoneNumberNormalizer;

        $this->assertSame('62********890', $normalizer->mask('6281234567890'));
        $this->assertSame('-', $normalizer->mask(null));
        $this->assertSame('****', $normalizer->mask('1234'));
    }
}
