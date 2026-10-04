<?php

namespace Tests\Unit;

use Illuminate\Support\Carbon;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    public function test_relative_dates_stay_in_latin_script_after_locale_resets(): void
    {
        app()->setLocale('uz'); // Livewire har so‘rovda shunday qiladi
        $this->assertSame('2 kun avval', Carbon::now()->subDays(2)->diffForHumans());
    }
}
