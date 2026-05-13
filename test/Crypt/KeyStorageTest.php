<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @category  Horde
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */

namespace Horde\IMP\Test\Crypt;

use Horde\Imp\Crypt\CorruptKeyException;
use Horde\Imp\Crypt\KeyStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the KeyStorage encode/decode/validate/migrate logic.
 *
 * @author    Ralf Lang <lang@b1-systems.de>
 * @category  Horde
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */
#[CoversClass(KeyStorage::class)]
class KeyStorageTest extends TestCase
{
    private KeyStorage $storage;

    private const SAMPLE_CERT = "-----BEGIN CERTIFICATE-----\nMIIBkTCB+wIJAKHBfpHYsb5oMA0GCSqGSIb3DQEBCwUAMBExDzANBgNVBAMMBnRl\nc3RDQTAeFw0yNDAxMDEwMDAwMDBaFw0yNTAxMDEwMDAwMDBaMBExDzANBgNVBAMM\nBnRlc3RDQTBcMA0GCSqGSIb3DQEBAQUAAktAMEgCQQC7o96lu5Fqo1bCdHFjKSmP\nvqMM9MnFN0k1Nfbb3LYKi7NoFXCcPwDXiAVA2B5cFhPfPMKGCHoGfMKAoqPfn9NA\ngMBAAEwDQYJKoZIhvcNAQELBQADQQBiKLRsY/KsLupjPDj16DsGNEDi4IZ2epOt\nOKI98Rkwpv0U6r9DiagkmA00qDHrwwIhNjei5rnr/L+4MH4VqpBs\n-----END CERTIFICATE-----";

    private const SAMPLE_PRIVATE_KEY = "-----BEGIN RSA PRIVATE KEY-----\nMIIBogIBAAJBALuj3qW7kWqjVsJ0cWMpKY++owz0ycU3STU19tvctgqLs2gVcJw/\nANeIBUDYHlwWE988woYIegZ8woCio9+f00CAwEAAQJAV89FbLEnISm/X6DxP+iCm\nt4S3LP3V0q4SKBF6j0ggVz1dlMphXYECiEGOFCXIH06Ksb7RQbvLXe/IAPjbV0Gn\nQIhAN6tl3xn7pGKdK0f8bI5Y15u3A5EkVpH9g/r4eOGF1qDAiEA2TW+K9nnF3f4\nE7LgGBsLbT9URgO3e5H/qDIr9OJuAvcCIBaECqBOU/JiEq0rVdcSr9hj7UZtwFc\nFOj5mBTHllVdAiEAw7zyVALNz1K03y5m+l3MO2z4HkJP5eB3SAuqRGHCVf0CIHAG\nX9l66vcCyX8FXGS+gD6G3qiyWj3mJBPbJPmH3mPD\n-----END RSA PRIVATE KEY-----";

    private const SAMPLE_PGP_PUBLIC = "-----BEGIN PGP PUBLIC KEY BLOCK-----\n\nmQENBGV0AAoBCADJ7KF0m2ltP4U2VVwjBkXm/RUIbFPGSJmDNgV1THMXwvT1sDfC\nhM5OUQZ5gkvw9ORBpFxSmFqHeh08dOoLBE8zC8IJmVR3Fd/XhJBpG0FkVepKNe7o\nPcRR4wGZ/T5r0k0LAqFvKBw8xNZFNneFMc4AXqGJEVrxaVq0K8yCFb/Q+SJojrGP\nZA+NBcOF5qR0qOF1ZQMEJADBJMbFARgZfFdMMXmPmr0FGWyCYI+YIv2Ev7pEAQGP\njCHh2FdE/RB0gA==\n=ABCD\n-----END PGP PUBLIC KEY BLOCK-----";

    private const SAMPLE_PGP_PRIVATE = "-----BEGIN PGP PRIVATE KEY BLOCK-----\n\nlQOYBGV0AAoBCADJ7KF0m2ltP4U2VVwjBkXm/RUIbFPGSJmDNgV1THMXwvT1sDfC\nhM5OUQZ5gkvw9ORBpFxSmFqHeh08dOoLBE8zC8IJmVR3Fd/XhJBpG0FkVepKNe7o\nZA+NBcOF5qR0qOF1ZQMEJADBJMbFARgZfFdMMXmPmr0FGWyCYI+YIv2Ev7pEAQGP\njCHh2FdE/RB0gA==\n=EFGH\n-----END PGP PRIVATE KEY BLOCK-----";

    private const SAMPLE_MULTI_CERT = "-----BEGIN CERTIFICATE-----\nMIIBkTCB+wIJAKHBfpHYsb5oMA0GCSqGSIb3DQEBCwUAMBExDzANBgNVBAMMBnRl\nc3RDQTBcMA0GCSqGSIb3DQEBAQUAAktAMEgCQQC7o96lu5Fqo1bCdHFjKSmP\n-----END CERTIFICATE-----\n-----BEGIN CERTIFICATE-----\nMIIBkTCB+wIJAKHBfpHYsb5oMA0GCSqGSIb3DQEBCwUAMBExDzANBgNVBAMMBnRl\nc3RDQTBcMA0GCSqGSIb3DQEBAQUAAktAMEgCQQC7o96lu5Fqo1bCdHFjKSmP\n-----END CERTIFICATE-----";

    protected function setUp(): void
    {
        $this->storage = new KeyStorage();
    }

    public function testEncodeReturnsBase64(): void
    {
        $encoded = $this->storage->encode(self::SAMPLE_CERT);

        $this->assertNotEquals(self::SAMPLE_CERT, $encoded);
        $this->assertNotFalse(base64_decode($encoded, true));
        $this->assertEquals(self::SAMPLE_CERT, base64_decode($encoded, true));
    }

    public function testEncodeEmptyReturnsEmpty(): void
    {
        $this->assertSame('', $this->storage->encode(''));
    }

    public function testDecodeBase64Pem(): void
    {
        $encoded = base64_encode(self::SAMPLE_PRIVATE_KEY);

        $result = $this->storage->decode($encoded);

        $this->assertEquals(self::SAMPLE_PRIVATE_KEY, $result);
    }

    public function testDecodeEmptyReturnsEmpty(): void
    {
        $this->assertSame('', $this->storage->decode(''));
    }

    public function testDecodeRawPemMigrates(): void
    {
        $prefs = new class {
            public string $lastPrefName = '';
            public string $lastValue = '';

            public function setValue(string $prefName, string $value): void
            {
                $this->lastPrefName = $prefName;
                $this->lastValue = $value;
            }
        };

        $result = $this->storage->decode(self::SAMPLE_PRIVATE_KEY, 'smime_private_key', $prefs);

        $this->assertEquals(self::SAMPLE_PRIVATE_KEY, $result);
        $this->assertSame('smime_private_key', $prefs->lastPrefName);
        $this->assertSame(base64_encode(self::SAMPLE_PRIVATE_KEY), $prefs->lastValue);
    }

    public function testDecodeRawPemWithoutPrefsDoesNotMigrate(): void
    {
        $result = $this->storage->decode(self::SAMPLE_PRIVATE_KEY);

        $this->assertEquals(self::SAMPLE_PRIVATE_KEY, $result);
    }

    public function testDecodeCorruptReturnsEmpty(): void
    {
        $corrupt = 'this is not a key at all, just garbage data ñ€£';

        $this->assertSame('', $this->storage->decode($corrupt));
    }

    public function testIsValidPemCert(): void
    {
        $this->assertTrue($this->storage->isValidPem(self::SAMPLE_CERT));
    }

    public function testIsValidPemPrivateKey(): void
    {
        $this->assertTrue($this->storage->isValidPem(self::SAMPLE_PRIVATE_KEY));
    }

    public function testIsValidPemPgpPublic(): void
    {
        $this->assertTrue($this->storage->isValidPem(self::SAMPLE_PGP_PUBLIC));
    }

    public function testIsValidPemPgpPrivate(): void
    {
        $this->assertTrue($this->storage->isValidPem(self::SAMPLE_PGP_PRIVATE));
    }

    public function testIsValidPemMultiBlock(): void
    {
        $this->assertTrue($this->storage->isValidPem(self::SAMPLE_MULTI_CERT));
    }

    public function testIsValidPemRejectsGarbage(): void
    {
        $this->assertFalse($this->storage->isValidPem('not a pem'));
        $this->assertFalse($this->storage->isValidPem(''));
        $this->assertFalse($this->storage->isValidPem('-----BEGIN FAKE-----'));
    }

    public function testIsBase64EncodedDetectsEncodedPem(): void
    {
        $encoded = base64_encode(self::SAMPLE_CERT);

        $this->assertTrue($this->storage->isBase64Encoded($encoded));
    }

    public function testIsBase64EncodedRejectsRawPem(): void
    {
        $this->assertFalse($this->storage->isBase64Encoded(self::SAMPLE_CERT));
    }

    public function testIsBase64EncodedRejectsEmpty(): void
    {
        $this->assertFalse($this->storage->isBase64Encoded(''));
    }

    public function testIsBase64EncodedRejectsBase64NonPem(): void
    {
        $encoded = base64_encode('just some regular text');

        $this->assertFalse($this->storage->isBase64Encoded($encoded));
    }

    public function testIsCorruptDetectsCorruption(): void
    {
        $this->assertTrue($this->storage->isCorrupt('corrupted data ñ€£'));
    }

    public function testIsCorruptReturnsFalseForEmpty(): void
    {
        $this->assertFalse($this->storage->isCorrupt(''));
    }

    public function testIsCorruptReturnsFalseForValidBase64(): void
    {
        $this->assertFalse($this->storage->isCorrupt(base64_encode(self::SAMPLE_CERT)));
    }

    public function testIsCorruptReturnsFalseForRawPem(): void
    {
        $this->assertFalse($this->storage->isCorrupt(self::SAMPLE_CERT));
    }

    public function testRoundTripCert(): void
    {
        $encoded = $this->storage->encode(self::SAMPLE_CERT);
        $decoded = $this->storage->decode($encoded);

        $this->assertSame(self::SAMPLE_CERT, $decoded);
    }

    public function testRoundTripPrivateKey(): void
    {
        $encoded = $this->storage->encode(self::SAMPLE_PRIVATE_KEY);
        $decoded = $this->storage->decode($encoded);

        $this->assertSame(self::SAMPLE_PRIVATE_KEY, $decoded);
    }

    public function testRoundTripPgpKey(): void
    {
        $encoded = $this->storage->encode(self::SAMPLE_PGP_PUBLIC);
        $decoded = $this->storage->decode($encoded);

        $this->assertSame(self::SAMPLE_PGP_PUBLIC, $decoded);
    }

    public function testRoundTripMultiCert(): void
    {
        $encoded = $this->storage->encode(self::SAMPLE_MULTI_CERT);
        $decoded = $this->storage->decode($encoded);

        $this->assertSame(self::SAMPLE_MULTI_CERT, $decoded);
    }

    public function testIsValidPemEcPrivateKey(): void
    {
        $ecKey = "-----BEGIN EC PRIVATE KEY-----\nMHQCAQEEIBkg4LVWM9nuwNSk3yByxZpYRTBnVpqR1fR3YJig0bOdoAcGBSuBBAAi\noWQDYgAE2a8kaFME9dggFaXhelIpix0+MljTFVRkratfHsSqHpSN\n-----END EC PRIVATE KEY-----";

        $this->assertTrue($this->storage->isValidPem($ecKey));
    }

    public function testIsValidPemDsaPrivateKey(): void
    {
        $dsaKey = "-----BEGIN DSA PRIVATE KEY-----\nMIIBugIBAAKBgQDRhGF7X4A0ZVlEg2ly5Hn2HQYH/MqSS+4qMB0\n-----END DSA PRIVATE KEY-----";

        $this->assertTrue($this->storage->isValidPem($dsaKey));
    }

    public function testIsValidPemOpensshPrivateKey(): void
    {
        $opensshKey = "-----BEGIN OPENSSH PRIVATE KEY-----\nb3BlbnNzaC1rZXktdjEAAAAABG5vbmUAAAAEbm9uZQAAAAAAAAAB\n-----END OPENSSH PRIVATE KEY-----";

        $this->assertTrue($this->storage->isValidPem($opensshKey));
    }

    public function testIsValidPemEcParameters(): void
    {
        $ecParams = "-----BEGIN EC PARAMETERS-----\nBggqhkjOPQMBBw==\n-----END EC PARAMETERS-----";

        $this->assertTrue($this->storage->isValidPem($ecParams));
    }

    public function testIsValidPemDhParameters(): void
    {
        $dhParams = "-----BEGIN DH PARAMETERS-----\nMIIBCAKCAQEA7+giV6afJJkD5Vqm7cHEP1M1\n-----END DH PARAMETERS-----";

        $this->assertTrue($this->storage->isValidPem($dhParams));
    }

    public function testRoundTripEcKey(): void
    {
        $ecKey = "-----BEGIN EC PRIVATE KEY-----\nMHQCAQEEIBkg4LVWM9nuwNSk3yByxZpYRTBnVpqR1fR3YJig0bOdoAcGBSuBBAAi\noWQDYgAE2a8kaFME9dggFaXhelIpix0+MljTFVRkratfHsSqHpSN\n-----END EC PRIVATE KEY-----";

        $encoded = $this->storage->encode($ecKey);
        $decoded = $this->storage->decode($encoded);

        $this->assertSame($ecKey, $decoded);
    }

    public function testDecodeStrictThrowsOnCorruptData(): void
    {
        $corrupt = 'this is not a key at all, just garbage data';

        $this->expectException(CorruptKeyException::class);
        $this->expectExceptionMessage('Corrupt key data in preference "pgp_public_key"');

        $this->storage->decode($corrupt, 'pgp_public_key', null, true);
    }

    public function testDecodeStrictDoesNotThrowOnValidData(): void
    {
        $encoded = base64_encode(self::SAMPLE_CERT);

        $result = $this->storage->decode($encoded, 'smime_public_key', null, true);

        $this->assertSame(self::SAMPLE_CERT, $result);
    }

    public function testDecodeStrictDoesNotThrowOnEmpty(): void
    {
        $result = $this->storage->decode('', 'pgp_public_key', null, true);

        $this->assertSame('', $result);
    }
}
