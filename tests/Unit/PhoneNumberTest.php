<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PhoneNumberTest extends TestCase
{
    #[DataProvider('validNumbers')]
    public function test_normalizes_different_formats_to_e164(string $input): void
    {
        $this->assertSame('+998901234567', PhoneNumber::normalize($input));
    }

    public static function validNumbers(): array
    {
        return [['+998901234567'], ['998901234567'], ['901234567'], ['+998 (90) 123-45-67'], ['90 123 45 67']];
    }

    #[DataProvider('invalidNumbers')]
    public function test_rejects_invalid_numbers(?string $input): void
    {
        $this->assertNull(PhoneNumber::normalize($input));
    }

    public static function invalidNumbers(): array
    {
        return [[null], [''], ['12345'], ['+79001234567'], ['+9989012345678'], ['abc']];
    }

    public function test_masks_and_formats(): void
    {
        $this->assertSame('+998 90 123 45 67', PhoneNumber::format('+998901234567'));
        $this->assertSame('+998 90 *** ** 67', PhoneNumber::mask('+998901234567'));
    }
}
