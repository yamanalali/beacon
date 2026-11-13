<?php

namespace Tests\Unit\Baseline;

use App\Support\Baseline\KoboXlsxReader;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KoboXlsxReaderTest extends TestCase
{
    #[Test]
    public function it_reads_headers_and_rows_from_fixture(): void
    {
        $path = base_path('tests/Fixtures/baseline_mse_sample.xlsx');
        $this->assertFileExists($path);

        $parsed = (new KoboXlsxReader)->read($path);
        $this->assertArrayHasKey('S', $parsed['headers']);
        $this->assertSame('اسم المحل أو المنشأة', $parsed['headers']['S']);
        $this->assertCount(1, $parsed['rows']);
        $this->assertSame('ركن المراقبة', $parsed['rows'][0]['S']);
        $this->assertSame('DA-1fcd8e6', $parsed['rows'][0]['D']);
    }
}
