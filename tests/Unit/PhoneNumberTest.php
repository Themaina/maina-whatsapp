<?php

namespace MainaSuperintelligence\Whatsapp\Tests\Unit;

use MainaSuperintelligence\Whatsapp\Supports\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

class PhoneNumberTest extends TestCase
{
    public static function numbers(): array
    {
        return [
            'local 07' => ['0712345678', '254712345678'],
            'local 01' => ['0112345678', '254112345678'],
            'no trunk 7' => ['712345678', '254712345678'],
            'no trunk 1' => ['112345678', '254112345678'],
            'e164' => ['+254712345678', '254712345678'],
            'digits only' => ['254712345678', '254712345678'],
            'double zero' => ['00254712345678', '254712345678'],
            'plus with trunk zero' => ['+254 0712 345 678', '254712345678'],
            'formatted' => ['(0712) 345-678', '254712345678'],
            'spaces' => ['0712 345 678', '254712345678'],
            'kenyan landline rejected' => ['0201234567', null],
            'too short' => ['12345', null],
            'letters' => ['call me', null],
            'empty' => ['', null],
            'null' => [null, null],
            'uk with plus' => ['+447911123456', '447911123456'],
            'uk without plus is ambiguous' => ['447911123456', null],
            '254 too long' => ['2547123456789', null],
        ];
    }

    #[DataProvider('numbers')]
    public function test_normalise(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::normalise($input, '254'));
    }

    public function test_without_a_default_country_only_international_numbers_work(): void
    {
        $this->assertNull(PhoneNumber::normalise('0712345678'));
        $this->assertNull(PhoneNumber::normalise('712345678'));
        $this->assertSame('254712345678', PhoneNumber::normalise('254712345678'));
        $this->assertSame('2348031234567', PhoneNumber::normalise('+234 803 123 4567'));
    }

    public function test_other_default_country_codes(): void
    {
        $this->assertSame('2348031234567', PhoneNumber::normalise('08031234567', '234'));
        $this->assertSame('256772123456', PhoneNumber::normalise('0772 123 456', '+256'));
    }

    public function test_variants_cover_common_storage_formats(): void
    {
        $variants = PhoneNumber::variants('254712345678');

        foreach (['254712345678', '+254712345678', '0712345678', '712345678'] as $expected) {
            $this->assertContains($expected, $variants);
        }
    }
}
