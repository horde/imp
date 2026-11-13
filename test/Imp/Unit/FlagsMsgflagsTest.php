<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @author     Torben Dannhauer <torben@dannhauer.de>
 * @category   Horde
 * @copyright  2026 The Horde Project
 * @license    http://www.horde.org/licenses/gpl GPL
 * @package    IMP
 * @subpackage UnitTests
 */

use PHPUnit\Framework\TestCase;

/**
 * @covers IMP_Flags::msgflags
 * @covers IMP_Api::msgflags
 */
class Imp_Unit_FlagsMsgflagsTest extends TestCase
{
    private mixed $previousPrefs = null;

    private mixed $previousInjector = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (!defined('IMP_BASE')) {
            define('IMP_BASE', dirname(__DIR__, 3));
        }
        $this->previousPrefs = $GLOBALS['prefs'] ?? null;
        $this->previousInjector = $GLOBALS['injector'] ?? null;
    }

    protected function tearDown(): void
    {
        $this->restoreGlobal('prefs', $this->previousPrefs);
        $this->restoreGlobal('injector', $this->previousInjector);
        parent::tearDown();
    }

    public function testMsgflagsReturnsUserKeywordsOnly(): void
    {
        $user = new IMP_Flag_User('To Do');
        $answered = new IMP_Flag_Imap_Answered();
        $GLOBALS['prefs'] = new Imp_Unit_FlagsMsgflagsPrefs(serialize([
            $user->id => $user,
            $answered->id => $answered,
        ]));

        $flags = new IMP_Flags();

        $this->assertSame(['to_do' => 'To Do'], $flags->msgflags());
    }

    public function testMsgflagsIsEmptyWhenPreferenceIsEmpty(): void
    {
        $GLOBALS['prefs'] = new Imp_Unit_FlagsMsgflagsPrefs('a:0:{}');

        $flags = new IMP_Flags();

        $this->assertSame([], $flags->msgflags());
    }

    public function testApiMsgflagsDelegatesToImpFlags(): void
    {
        $user = new IMP_Flag_User('Important');
        $GLOBALS['prefs'] = new Imp_Unit_FlagsMsgflagsPrefs(serialize([
            $user->id => $user,
        ]));
        $loaded = new IMP_Flags();
        $GLOBALS['injector'] = new Imp_Unit_FlagsMsgflagsInjector($loaded);

        $api = new IMP_Api();

        $this->assertSame(['important' => 'Important'], $api->msgflags());
        $this->assertContains('msgflags', $api->methods());
    }

    private function restoreGlobal(string $name, mixed $value): void
    {
        if ($value === null) {
            unset($GLOBALS[$name]);
            return;
        }
        $GLOBALS[$name] = $value;
    }
}

/**
 * Prefs stub that serves the stored msgflags value.
 */
class Imp_Unit_FlagsMsgflagsPrefs
{
    public function __construct(private string $msgflags)
    {
    }

    public function getValue(string $name): string
    {
        return $name === 'msgflags' ? $this->msgflags : '';
    }
}

/**
 * Injector stub that returns one IMP_Flags instance.
 */
class Imp_Unit_FlagsMsgflagsInjector
{
    public function __construct(private IMP_Flags $flags)
    {
    }

    public function getInstance(string $name): IMP_Flags
    {
        return $this->flags;
    }
}
