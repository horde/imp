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

namespace Horde\Imp\Crypt;

/**
 * Handles encoding, decoding, validation, and lazy-migration of
 * cryptographic key material stored in Horde preferences.
 *
 * Keys are stored base64-encoded to prevent charset corruption by
 * the prefs SQL backend. On read, detects legacy raw PEM values and
 * migrates them transparently.
 *
 * @author    Ralf Lang <lang@b1-systems.de>
 * @category  Horde
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */
class KeyStorage
{
    /**
     * PEM envelope markers recognized by this class.
     */
    private const PEM_TYPES = [
        'CERTIFICATE',
        'TRUSTED CERTIFICATE',
        'X509 CERTIFICATE',
        'RSA PRIVATE KEY',
        'DSA PRIVATE KEY',
        'EC PRIVATE KEY',
        'PRIVATE KEY',
        'ENCRYPTED PRIVATE KEY',
        'OPENSSH PRIVATE KEY',
        'RSA PUBLIC KEY',
        'PUBLIC KEY',
        'EC PARAMETERS',
        'DH PARAMETERS',
        'PGP PUBLIC KEY BLOCK',
        'PGP PRIVATE KEY BLOCK',
        'PGP MESSAGE',
        'PGP SIGNATURE',
    ];

    /**
     * Encode key data for safe storage in prefs.
     *
     * @param string $keyData Raw PEM key/certificate data.
     * @return string Base64-encoded value safe from charset mangling.
     */
    public function encode(string $keyData): string
    {
        if ($keyData === '') {
            return '';
        }

        return base64_encode($keyData);
    }

    /**
     * Decode key data read from prefs.
     *
     * Handles three formats:
     * - Empty string (no key stored)
     * - Base64-encoded PEM (new format, already migrated)
     * - Raw PEM (legacy format, triggers lazy migration)
     *
     * @param string $rawValue Value from prefs->getValue().
     * @param string $prefName Pref key name (for migration write-back).
     * @param object|null $prefs Prefs instance supporting setValue() for write-back.
     * @param bool $strict If true, throws CorruptKeyException instead of returning ''.
     * @return string Decoded PEM data, or '' if empty/corrupt.
     * @throws CorruptKeyException When $strict is true and data is corrupt.
     */
    public function decode(string $rawValue, string $prefName = '', ?object $prefs = null, bool $strict = false): string
    {
        if ($rawValue === '') {
            return '';
        }

        // Try base64 decode first (new format)
        $decoded = base64_decode($rawValue, true);
        if ($decoded !== false && $this->isValidPem($decoded)) {
            return $decoded;
        }

        // Check if it's raw PEM (legacy format)
        if ($this->isValidPem($rawValue)) {
            $this->migrate($rawValue, $prefName, $prefs);
            return $rawValue;
        }

        // Data is corrupt
        if ($strict) {
            throw new CorruptKeyException(
                sprintf('Corrupt key data in preference "%s"', $prefName)
            );
        }

        return '';
    }

    /**
     * Check whether a string is structurally valid PEM data.
     *
     * Validates one or more concatenated PEM blocks. Each block must
     * have matching BEGIN/END markers with a recognized type.
     *
     * @param string $data Data to validate.
     * @return bool True if the data contains at least one valid PEM block.
     */
    public function isValidPem(string $data): bool
    {
        if ($data === '') {
            return false;
        }

        $types = implode('|', array_map('preg_quote', self::PEM_TYPES));
        $pattern = '/-----BEGIN (' . $types . ')-----\s*.+?\s*-----END \1-----/s';

        return (bool) preg_match($pattern, $data);
    }

    /**
     * Detect whether a raw pref value is in the new base64-encoded format.
     *
     * @param string $rawValue Value from prefs.
     * @return bool True if the value is base64-encoded PEM.
     */
    public function isBase64Encoded(string $rawValue): bool
    {
        if ($rawValue === '') {
            return false;
        }

        $decoded = base64_decode($rawValue, true);
        if ($decoded === false) {
            return false;
        }

        return $this->isValidPem($decoded);
    }

    /**
     * Check whether a stored value is corrupt.
     *
     * A value is corrupt if it is non-empty but is neither valid
     * base64-encoded PEM nor raw PEM.
     *
     * @param string $rawValue Value from prefs.
     * @return bool True if the value is corrupt.
     */
    public function isCorrupt(string $rawValue): bool
    {
        if ($rawValue === '') {
            return false;
        }

        if ($this->isBase64Encoded($rawValue)) {
            return false;
        }

        if ($this->isValidPem($rawValue)) {
            return false;
        }

        return true;
    }

    /**
     * Lazy-migrate a raw PEM value to base64 encoding in prefs.
     *
     * @param string $rawPem The raw PEM data.
     * @param string $prefName The preference name.
     * @param object|null $prefs Prefs instance supporting setValue().
     */
    private function migrate(string $rawPem, string $prefName, ?object $prefs): void
    {
        if ($prefName === '' || $prefs === null) {
            return;
        }

        if (!method_exists($prefs, 'setValue')) {
            return;
        }

        $prefs->setValue($prefName, $this->encode($rawPem));
    }
}
