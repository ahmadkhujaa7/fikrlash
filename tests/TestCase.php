<?php

namespace Tests;

use App\Contracts\SmsProvider;
use App\Models\Category;
use App\Models\User;
use App\Services\Sms\ArraySmsProvider;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
    }

    protected function sms(): ArraySmsProvider
    {
        return $this->app->make(SmsProvider::class);
    }

    protected function seedCategories(): void
    {
        $this->seed(CategorySeeder::class);
        Category::query()->get(); // keshni isitish shart emas — array cache
    }

    protected function admin(): User
    {
        return User::factory()->admin()->create();
    }
}
