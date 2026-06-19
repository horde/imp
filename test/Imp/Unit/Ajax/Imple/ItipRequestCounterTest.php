<?php

/**
 * Tests for IMP COUNTER proposal handling in ItipRequest.
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 Horde LLC (http://www.horde.org)
 * @package   IMP
 */

require_once __DIR__ . '/../../../Stub/ItipRequest.php';
require_once __DIR__ . '/../../../Stub/Imap.php';

use PHPUnit\Framework\TestCase;

class Imp_Stub_Ajax_Imple_ItipRequestCounterAccept extends Imp_Stub_Ajax_Imple_ItipRequest
{
    protected function _handlevEvent($key, array $components, $mime_part)
    {
        return true;
    }
}

class Imp_Unit_Ajax_Imple_ItipRequestCounterTest extends TestCase
{
    private $_contents;
    private $_contentsData;
    private $_contentsFactory;
    private $_identity;
    private $_identityId = 'default';
    private $_mail;
    private $_mailbox;
    private $_imapFactory;
    private $_notifyStack = [];
    private $_registryCalls = [];
    private $_oldtz;

    protected function setUp(): void
    {
        $this->_oldtz = date_default_timezone_get();
        date_default_timezone_set('UTC');

        $injector = $this->getMockBuilder('Horde_Injector')
            ->disableOriginalConstructor()
            ->getMock();
        $injector->method('getInstance')
            ->willReturnCallback([$this, '_injectorGetInstance']);
        $GLOBALS['injector'] = $injector;

        $calendarApi = new class () {
            public function listCalendars($all = false)
            {
                return [];
            }
        };

        $registry = $this->getMockBuilder('Horde_Registry')
            ->disableOriginalConstructor()
            ->onlyMethods(['remoteHost', 'hasMethod', 'call', 'link', '__get'])
            ->getMock();
        $registry->method('remoteHost')
            ->willReturnCallback([$this, '_registryRemoteHost']);
        $registry->method('hasMethod')
            ->willReturnCallback([$this, '_registryHasMethod']);
        $registry->method('call')
            ->willReturnCallback([$this, '_registryCall']);
        $registry->method('link')
            ->willReturn('calendar/show');
        $registry->method('__get')
            ->willReturnCallback(function ($api) use ($calendarApi) {
                if ($api === 'calendar') {
                    return $calendarApi;
                }
            });
        $GLOBALS['registry'] = $registry;

        $notification = $this->getMockBuilder('Horde_Notification_Handler')
            ->disableOriginalConstructor()
            ->getMock();
        $notification->method('push')
            ->willReturnCallback([$this, '_notificationHandler']);
        $GLOBALS['notification'] = $notification;

        $GLOBALS['conf']['server']['name'] = 'localhost';
        $_SERVER['REMOTE_ADDR'] = 'localhost';

        $browser = $this->getMockBuilder('Horde_Browser')
            ->disableOriginalConstructor()
            ->getMock();
        $browser->method('hasFeature')
            ->willReturn(true);
        $browser->method('usingSSLConnection')
            ->willReturn(false);
        $GLOBALS['browser'] = $browser;
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->_oldtz);
        $this->_contents = null;
        $this->_contentsFactory = null;
        $this->_identity = null;
        $this->_mail = null;
        $this->_mailbox = null;
        $this->_imapFactory = null;
        $this->_notifyStack = [];
        $this->_registryCalls = [];
    }

    public function _injectorGetInstance($interface)
    {
        switch ($interface) {
            case 'Horde_Core_Hooks':
                return new Horde_Core_Hooks();

            case 'IMP_Contents':
                if (!isset($this->_contents)) {
                    $headers = new Horde_Mime_Headers();
                    $headers->addHeader(
                        'From',
                        '"Counter Attendee" <counter.attendee@example.com>'
                    );

                    $contents = $this->getMockBuilder('IMP_Contents')
                        ->disableOriginalConstructor()
                        ->getMock();
                    $contents->method('getMimePart')
                        ->willReturnCallback([$this, '_getMimePart']);
                    $contents->method('getHeader')
                        ->willReturn($headers);
                    $this->_contents = $contents;
                }
                return $this->_contents;

            case 'IMP_Factory_Contents':
                if (!isset($this->_contentsFactory)) {
                    $cf = $this->getMockBuilder('IMP_Factory_Contents')
                        ->disableOriginalConstructor()
                        ->getMock();
                    $cf->method('create')
                        ->willReturn($this->_injectorGetInstance('IMP_Contents'));
                    $this->_contentsFactory = $cf;
                }
                return $this->_contentsFactory;

            case 'IMP_Factory_Imap':
                if (!isset($this->_imapFactory)) {
                    $imap = $this->getMockBuilder('IMP_Factory_Imap')
                        ->disableOriginalConstructor()
                        ->getMock();
                    $imap->method('create')
                        ->willReturn(new IMP_Stub_Imap());
                    $this->_imapFactory = $imap;
                }
                return $this->_imapFactory;

            case 'IMP_Factory_Mailbox':
                if (!isset($this->_mailbox)) {
                    $mbox = $this->getMockBuilder('IMP_Factory_Mailbox')
                        ->disableOriginalConstructor()
                        ->getMock();
                    $mbox->method('create')
                        ->willReturn(new IMP_Mailbox('foo'));
                    $this->_mailbox = $mbox;
                }
                return $this->_mailbox;

            case 'IMP_Identity':
                if (!isset($this->_identity)) {
                    $identity = $this->getMockBuilder('Horde_Core_Prefs_Identity')
                        ->disableOriginalConstructor()
                        ->getMock();
                    $identity->method('setDefault')
                        ->willReturnCallback([$this, '_identitySetDefault']);
                    $identity->method('getDefault')
                        ->willReturnCallback([$this, '_identityGetDefault']);
                    $identity->method('getFromAddress')
                        ->willReturnCallback([$this, '_identityGetFromAddress']);
                    $identity->method('getDefaultFromAddress')
                        ->willReturn(new Horde_Mail_Rfc822_Address('"Organizer" <organizer@example.com>'));
                    $identity->method('getValue')
                        ->willReturnCallback([$this, '_identityGetValue']);
                    $this->_identity = $identity;
                }
                return $this->_identity;

            case 'IMP_Mail':
                if (!isset($this->_mail)) {
                    $this->_mail = new Horde_Mail_Transport_Mock();
                }
                return $this->_mail;

            case 'Horde_Browser':
                return $GLOBALS['browser'];
        }
    }

    public function _registryRemoteHost()
    {
        $remote = new stdClass();
        $remote->addr = '127.0.0.1';
        $remote->host = 'localhost';

        return $remote;
    }

    public function _registryHasMethod($method)
    {
        return in_array($method, [
            'calendar/acceptCounterProposal',
            'calendar/declineCounterProposal',
            'calendar/updateAttendee',
            'calendar/export',
            'calendar/replace',
            'calendar/import',
        ], true);
    }

    public function _registryCall($method, array $args = [])
    {
        $this->_registryCalls[] = [$method, $args];

        switch ($method) {
            case 'calendar/export':
                throw new Horde_Exception('Event not found');

            case 'calendar/import':
                return 'counter-event-uid';
        }
    }

    public function _getMimePart($id)
    {
        $part = new Horde_Mime_Part();
        $part->setContents($this->_contentsData);
        $part->setType('text/calendar');
        return $part;
    }

    public function _identitySetDefault($id)
    {
        $this->_identityId = $id;
    }

    public function _identityGetDefault()
    {
        return $this->_identityId;
    }

    public function _identityGetFromAddress($value = null)
    {
        return new Horde_Mail_Rfc822_Address('"Organizer" <organizer@example.com>');
    }

    public function _identityGetValue($value, $identity = null)
    {
        switch ($value) {
            case 'fullname':
                return 'Organizer';

            case 'replyto_addr':
                return 'organizer@example.com';
        }
    }

    public function _notificationHandler($msg, $code)
    {
        $this->_notifyStack[] = [$msg, $code];
    }

    public function testCounterDeclineSendsDeclineCounterMessage()
    {
        $this->_doRequest('counter-decline', $this->_getCounterCalendar());

        $this->assertNotEmpty($this->_notifyStack);
        $this->assertStringContainsString(
            'Decline counter sent.',
            (string) $this->_notifyStack[0][0]
        );
        $this->assertEquals('horde.success', $this->_notifyStack[0][1]);

        $mail = $GLOBALS['injector']->getInstance('IMP_Mail');
        $this->assertNotEmpty($mail->sentMessages);
        $this->assertEquals(
            'Decline Counter Proposal',
            $mail->sentMessages[0]['headers']['Subject']
        );
        $this->assertEquals(
            'counter.attendee@example.com',
            $this->_getMailHeaders()->getValue('To')
        );

        $declineCalls = array_filter(
            $this->_registryCalls,
            function ($call) {
                return $call[0] === 'calendar/declineCounterProposal';
            }
        );
        $this->assertCount(1, $declineCalls);
        $declineCall = reset($declineCalls);
        $this->assertSame('counter.attendee@example.com', $declineCall[1][1]);
        $this->assertTrue($declineCall[1][2]);
    }

    public function testCounterAcceptPassesAttendeeEmailToAcceptCounterProposal()
    {
        $this->_doRequest('counter-accept', $this->_getCounterCalendar(), 'default', true);

        $acceptCalls = array_filter(
            $this->_registryCalls,
            function ($call) {
                return $call[0] === 'calendar/acceptCounterProposal';
            }
        );
        $this->assertCount(1, $acceptCalls);
        $acceptCall = reset($acceptCalls);
        $this->assertSame('counter.attendee@example.com', $acceptCall[1][1]);
    }

    public function testCounterUpdateStoresProposalForCounterMethod()
    {
        $this->_doRequest('update', $this->_getCounterCalendar());

        $updateCalls = array_filter(
            $this->_registryCalls,
            function ($call) {
                return $call[0] === 'calendar/updateAttendee';
            }
        );
        $this->assertNotEmpty($updateCalls);
        $this->assertTrue($updateCalls[array_key_first($updateCalls)][1][2]);
        $this->assertStringContainsString(
            'Counter proposal recorded.',
            (string) $this->_notifyStack[0][0]
        );
        $this->assertEquals('horde.success', $this->_notifyStack[0][1]);
    }

    private function _getCounterCalendar()
    {
        $originalStart = new Horde_Date('20080926T110000');
        $originalEnd = new Horde_Date('20080926T120000');
        $proposedStart = new Horde_Date('20080927T140000');
        $proposedEnd = new Horde_Date('20080927T150000');

        $vCal = new Horde_Icalendar();
        $vCal->setAttribute('METHOD', 'COUNTER');
        $event = Horde_Icalendar::newComponent('VEVENT', $vCal);
        $event->setAttribute('UID', 'counter-uid-1001');
        $event->setAttribute('SUMMARY', 'Counter Proposal');
        $event->setAttribute('ORGANIZER', 'mailto:organizer@example.com', ['CN' => 'Organizer']);
        $event->setAttribute('DTSTART', $proposedStart->timestamp());
        $event->setAttribute('DTEND', $proposedEnd->timestamp());
        $event->setAttribute('ATTENDEE', 'mailto:counter.attendee@example.com', [
            'CN' => 'Counter Attendee',
            'PARTSTAT' => 'NEEDS-ACTION',
        ]);
        $vCal->addComponent($event);

        return $vCal->exportvCalendar();
    }

    private function _doRequest($action, $data, $identity = 'default', $stubHandlevEvent = false)
    {
        $vars = new Horde_Variables([
            'imple_submit' => ['imple_submit[0]' => $action],
            'identity' => $identity,
            'mailbox' => 'foo',
            'mime_id' => 1,
            'uid' => 1,
        ]);
        $this->_contentsData = $data;

        $imple = $stubHandlevEvent
            ? new Imp_Stub_Ajax_Imple_ItipRequestCounterAccept([])
            : new Imp_Stub_Ajax_Imple_ItipRequest([]);
        $imple->handle($vars);
    }

    private function _getMailHeaders()
    {
        $mail = $GLOBALS['injector']->getInstance('IMP_Mail');
        $this->assertNotEmpty($mail->sentMessages);
        $headers = Horde_Mime_Headers::parseHeaders($mail->sentMessages[0]['header_text']);
        $this->assertInstanceOf('Horde_Mime_Headers', $headers);
        return $headers;
    }
}
