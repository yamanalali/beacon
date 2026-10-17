<?php

namespace Tests\Unit;

use App\Ai\Ocr\ImagePreprocessor;
use App\Ai\Ocr\ReceiptTextParser;
use App\Ai\Ocr\TesseractReceiptExtractor;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class OcrPreprocessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // missingLanguages() caches `--list-langs` in a static per tesseract path; other tests
        // (e.g. OcrArabicTotalTest) leave an "eng only" entry behind that would make ocrFile() skip.
        TesseractReceiptExtractor::flushLanguageCache();
    }

    protected function tearDown(): void
    {
        TesseractReceiptExtractor::flushLanguageCache();
        parent::tearDown();
    }

    private function fakeProcess(string $stdout, int $exit = 0): Process
    {
        return new Process(['php', '-r', 'fwrite(STDOUT, base64_decode($argv[1])); exit((int) $argv[2]);', base64_encode($stdout), (string) $exit]);
    }

    private function png(int $w = 200, int $h = 80): string
    {
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, 230, 230, 230));
        $path = tempnam(sys_get_temp_dir(), 'test_').'.png';
        imagepng($img, $path);

        return $path;
    }

    public function test_magick_args_rotate_upscale_and_deskew(): void
    {
        $p = new ImagePreprocessor;
        $small = $p->magickArgs(180, 400);
        $this->assertSame(['-rotate', '180', '-colorspace', 'Gray', '-resize', '200%'], array_slice($small, 0, 6));
        $this->assertContains('-deskew', $small);
        $this->assertContains('-normalize', $small);
        $large = $p->magickArgs(0, 2000);
        $this->assertNotContains('-rotate', $large);
        $this->assertNotContains('-resize', $large);
    }

    public function test_osd_rotation_parsed_and_low_confidence_ignored(): void
    {
        $make = fn (string $out) => new class($out, fn ($o) => $this->fakeProcess($o)) extends ImagePreprocessor
        {
            public function __construct(private string $out, private \Closure $f) {}

            protected function makeProcess(array $command): Process
            {
                return ($this->f)($this->out);
            }
        };
        $this->assertSame(180, $make("Rotate: 180\nOrientation confidence: 9.1\n")->osdRotation('/x.png'));
        $this->assertSame(0, $make("Rotate: 90\nOrientation confidence: 0.4\n")->osdRotation('/x.png'));
        $this->assertSame(0, $make('garbage')->osdRotation('/x.png'));
    }

    public function test_gd_fallback_produces_temp_file_and_cleanup_deletes_it(): void
    {
        config(['ai.ocr.magick_path' => false, 'ai.ocr.preprocess_osd' => false]);
        $src = $this->png(300);
        $p = new ImagePreprocessor;
        $out = $p->prepare($src);
        $this->assertNotSame($src, $out);
        $this->assertFileExists($out);
        $this->assertSame(600, getimagesize($out)[0]); // upscaled 2x
        $p->cleanup();
        $this->assertFileDoesNotExist($out);
        $this->assertFileExists($src);
        @unlink($src);
    }

    public function test_unreadable_image_falls_back_to_original(): void
    {
        config(['ai.ocr.magick_path' => false, 'ai.ocr.preprocess_osd' => false]);
        $bad = tempnam(sys_get_temp_dir(), 'bad_');
        file_put_contents($bad, 'not an image');
        $p = new ImagePreprocessor;
        $this->assertSame($bad, $p->prepare($bad));
        $p->cleanup();
        @unlink($bad);
    }

    public function test_extractor_uses_preprocessed_file_adds_psm_and_cleans_up(): void
    {
        config(['ai.ocr.preprocess' => true, 'ai.ocr.psm' => 6]);
        $pre = new class extends ImagePreprocessor
        {
            public string $tmp;

            public bool $cleaned = false;

            public function prepare(string $path): string
            {
                return $this->tmp = tempnam(sys_get_temp_dir(), 'pp_');
            }

            public function cleanup(): void
            {
                @unlink($this->tmp);
                $this->cleaned = true;
            }
        };
        $ext = new class(new ReceiptTextParser, $pre, fn ($o) => $this->fakeProcess($o)) extends TesseractReceiptExtractor
        {
            public array $cmd = [];

            public function __construct(ReceiptTextParser $p, ImagePreprocessor $pre, private \Closure $f)
            {
                parent::__construct($p, $pre);
            }

            protected function makeProcess(array $command): Process
            {
                $this->cmd = $command;

                return ($this->f)("ACME\nTotal: 10 USD");
            }

            protected function makeLanguageProcess(array $command): Process
            {
                // Don't depend on the host's tesseract install: report both required languages.
                return new Process(['php', '-r', 'echo "ara\neng\n";']);
            }
        };
        $this->assertSame("ACME\nTotal: 10 USD", $ext->ocrFile('/orig.png'));
        $this->assertSame($pre->tmp, $ext->cmd[1]);
        $this->assertSame(['--psm', '6'], array_slice($ext->cmd, 5, 2));
        $this->assertTrue($pre->cleaned);
        $this->assertFileDoesNotExist($pre->tmp);

        config(['ai.ocr.preprocess' => false, 'ai.ocr.psm' => null]);
        $ext->ocrFile('/orig.png');
        $this->assertSame('/orig.png', $ext->cmd[1]);
        $this->assertNotContains('--psm', $ext->cmd);
    }

    public function test_preprocess_is_on_by_default_only_in_local(): void
    {
        $this->assertFalse(config('ai.ocr.preprocess'));
    }
}
