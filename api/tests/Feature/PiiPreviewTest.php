<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MakesPdfs;
use Tests\TestCase;

class PiiPreviewTest extends TestCase
{
    use MakesPdfs;

    public function test_it_prints_the_redacted_text_and_cleans_up(): void
    {
        Storage::fake('local');
        $file = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($file, $this->makePdf(['Employee: Jordan Avery  SSN: 900-41-2217', 'GROSS PAY 2,640.00']));

        Artisan::call('pii:preview', ['path' => $file, '--counts' => true]);
        $output = Artisan::output();

        $this->assertStringContainsString('[NAME]', $output);
        $this->assertStringContainsString('[SSN]', $output);
        $this->assertStringContainsString('GROSS PAY 2,640.00', $output);
        $this->assertStringNotContainsString('Jordan', $output);
        $this->assertStringContainsString('"NAME":1', $output);
        $this->assertSame([], Storage::disk('local')->allFiles('pii-preview'));
        unlink($file);
    }
}
