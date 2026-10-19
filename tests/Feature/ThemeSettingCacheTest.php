<?php

namespace Tests\Feature;

use App\Models\ThemeSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ThemeSettingCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_are_read_once_and_refreshed_after_a_write(): void
    {
        ThemeSetting::set('site_name', 'Old Name');
        app()->forgetInstance(ThemeSetting::CACHE);

        DB::enableQueryLog();
        ThemeSetting::get('site_name');
        ThemeSetting::get('whatsapp_number');
        ThemeSetting::get('site_logo');
        $reads = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'theme_settings'))->count();
        $this->assertSame(1, $reads, 'one query serves every lookup in a request');

        ThemeSetting::set('site_name', 'New Name');
        $this->assertSame('New Name', ThemeSetting::get('site_name'), 'a write is visible immediately');
    }
}
