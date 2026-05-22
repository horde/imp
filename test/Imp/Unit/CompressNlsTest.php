<?php

declare(strict_types=1);

namespace Horde\Imp\Test\Unit;

use Horde_Compress_Zip;
use Horde_Nls;
use Horde\Compress\CompressFactory;
use Horde\Compress\Driver\Zip;
use Horde\Nls\Nls;
use IMP;
use PHPUnit\Framework\TestCase;

/**
 * Tests public methods in IMP that use Horde_Compress and Horde_Nls.
 *
 * Covers:
 * - IMP::numberFormat() — uses Horde_Nls::getLocaleInfo()
 * - ZIP compress with stream option (used by Contents/View and Application)
 * - ZIP decompress ZIP_LIST + ZIP_DATA (used by Mbox/Import)
 * - Horde_Nls::getLanguageISO() (used by Compose)
 * @coversNothing
 */
class CompressNlsTest extends TestCase
{
    public function testNumberFormatWithDecimals(): void
    {
        $result = IMP::numberFormat(1234.567, 2);

        $this->assertIsString($result);
        $this->assertStringContainsString('1', $result);
        $this->assertStringContainsString('234', $result);
    }

    public function testNumberFormatCeils(): void
    {
        $result = IMP::numberFormat(1234.9, 0);

        $this->assertIsString($result);
        $this->assertStringContainsString('1', $result);
        $this->assertStringContainsString('235', $result);
    }

    /**
     * Mimics Contents/View::downloadAll() pattern:
     * compress files with stream option, get a resource back.
     */
    public function testZipCompressWithStreamOption(): void
    {
        $zip = new Horde_Compress_Zip();
        $files = [
            ['data' => 'attachment content', 'name' => 'file.txt'],
        ];

        $result = $zip->compress($files, ['stream' => true]);

        $this->assertIsResource($result);
        $content = stream_get_contents($result);
        $this->assertNotEmpty($content);
        fclose($result);

        $list = $zip->decompress($content, ['action' => Horde_Compress_Zip::ZIP_LIST]);
        $this->assertCount(1, $list);
        $this->assertEquals('file.txt', $list[0]['name']);
    }

    /**
     * Mimics Mbox/Import ZIP decompression: list files then extract.
     */
    public function testZipDecompressListAndExtract(): void
    {
        $zip = new Horde_Compress_Zip();
        $payload = "From sender@example.com\nSubject: Test\n\nBody\n";
        $archive = $zip->compress([
            ['data' => $payload, 'name' => 'inbox.mbox'],
        ]);

        $info = $zip->decompress($archive, [
            'action' => Horde_Compress_Zip::ZIP_LIST,
        ]);
        $this->assertCount(1, $info);
        $this->assertEquals('inbox.mbox', $info[0]['name']);

        $extracted = $zip->decompress($archive, [
            'action' => Horde_Compress_Zip::ZIP_DATA,
            'info' => $info,
            'key' => 0,
        ]);
        $this->assertEquals($payload, $extracted);
    }

    /**
     * PSR-4 equivalent of the decompress pattern: ZIP_DATA returns array.
     */
    public function testPsr4ZipDecompressListAndExtract(): void
    {
        $factory = new CompressFactory();
        $zip = $factory->create('zip');
        $payload = "From sender@example.com\nSubject: Test\n\nBody\n";

        $legacyZip = new Horde_Compress_Zip();
        $archive = $legacyZip->compress([
            ['data' => $payload, 'name' => 'inbox.mbox'],
        ]);

        $info = $zip->decompress($archive, [
            'action' => Zip::ZIP_LIST,
        ]);
        $this->assertCount(1, $info);

        $result = $zip->decompress($archive, [
            'action' => Zip::ZIP_DATA,
            'info' => $info,
            'key' => 0,
        ]);
        $data = is_array($result) ? $result['data'] : $result;
        $this->assertEquals($payload, $data);
    }

    /**
     * Tests Horde_Nls::getLanguageISO($code) returns language name.
     * Used by IMP_Compose to resolve Accept-Language headers.
     */
    public function testGetLanguageISOReturnsName(): void
    {
        $result = Horde_Nls::getLanguageISO('en');
        $this->assertNotNull($result);
        $this->assertIsString($result);
        $this->assertStringContainsString('English', $result);
    }

    /**
     * PSR-4 equivalent: $nls->languages()->get($code).
     */
    public function testPsr4LanguagesGetReturnsName(): void
    {
        $nls = new Nls();
        $result = $nls->languages()->get('en');
        $this->assertNotNull($result);
        $this->assertIsString($result);
        $this->assertStringContainsString('English', $result);
    }
}
