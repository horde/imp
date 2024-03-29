<?php
/**
 * Copyright 2002-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @category  Horde
 * @copyright 2002-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */

/**
 * Displays vCalendar/iCalendar data and provides an option to import the data
 * into a calendar source, if available.
 *
 * @author    Mike Cochrane <mike@graftonhall.co.nz>
 * @author    Chuck Hagenbuch <chuck@horde.org>
 * @author    Michael Slusarz <slusarz@horde.org>
 * @author    Gunnar Wrobel <wrobel@pardus.de>
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @category  Horde
 * @copyright 2002-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */
class IMP_Mime_Viewer_Itip extends Horde_Mime_Viewer_Base
{
    const AUTO_UPDATE_EVENT_REPLY = 'auto_update_eventreply';
    const AUTO_UPDATE_FB_PUBLISH  = 'auto_update_fbpublish';
    const AUTO_UPDATE_FB_REPLY    = 'auto_update_fbreply';
    const AUTO_UPDATE_TASK_REPLY  = 'auto_update_taskreply';

    /**
     * This driver's display capabilities.
     *
     * @var array
     */
    protected $_capability = array(
        'full' => true,
        'info' => false,
        'inline' => true,
        'raw' => false
    );

    /**
     * Metadata for the current viewer/data.
     *
     * @var array
     */
    protected $_metadata = array(
        'compressed' => false,
        'embedded' => false,
        'forceinline' => true
    );

    /**
     * Return the full rendered version of the Horde_Mime_Part object.
     *
     * @return array  See parent::render().
     */
    protected function _render()
    {
        global $page_output;

        $ret = $this->_renderInline();

        if (!empty($ret)) {
            reset($ret);
            $page_output->topbar = $page_output->sidebar = false;
            $mimecss = new Horde_Themes_Element('mime.css');
            $page_output->addStylesheet($mimecss->fs, $mimecss->uri);
            Horde::startBuffer();
            $page_output->header(array(
                'html_id' => 'htmlAllowScroll'
            ));
            echo $ret[key($ret)]['data'];
            $page_output->footer();
            $ret[key($ret)]['data'] = Horde::endBuffer();
        }

        return $ret;
    }

    /**
     * Return the rendered inline version of the Horde_Mime_Part object.
     *
     * @return array  See parent::render().
     */
    protected function _renderInline()
    {
        $GLOBALS['page_output']->growler = true;
        $data = $this->_mimepart->getContents();
        $mime_id = $this->_mimepart->getMimeId();

        // Parse the iCal file.
        $vCal = new Horde_Icalendar();
        if (!$vCal->parsevCalendar($data, 'VCALENDAR', $this->_mimepart->getCharset())) {
            $status = new IMP_Mime_Status(
                $this->_mimepart,
                _("The calendar data is invalid")
            );
            $status->action(IMP_Mime_Status::ERROR);
            return array(
                $mime_id => array(
                    'data' => '',
                    'status' => $status,
                    'type' => 'text/html; charset=UTF-8'
                )
            );
        }

        // Check if we got vcard data with the wrong vcalendar mime type.
        $imp_contents = $this->getConfigParam('imp_contents');
        $c = $vCal->getComponentClasses();
        if ((count($c) == 1) && !empty($c['horde_icalendar_vcard'])) {
            return $imp_contents->renderMIMEPart($mime_id, IMP_Contents::RENDER_INLINE, array('type' => 'text/x-vcard'));
        }

        $imple = $GLOBALS['injector']->getInstance('Horde_Core_Factory_Imple')->create('IMP_Ajax_Imple_ItipRequest', array(
            'ctype' => $this->_conf['type'],
            'mime_id' => $mime_id,
            'muid' => strval($imp_contents->getIndicesOb()),
        ));

        // Get the method type.
        try {
            $method = $vCal->getAttribute('METHOD');
        } catch (Horde_Icalendar_Exception $e) {
            $method = '';
        }

        $out = array();
        $exceptions = array();
        $components = $vCal->getComponents();
        foreach ($components as $key => $component) {
            switch ($component->getType()) {
            case 'vEvent':
                try {
                    if ($component->getAttribute('RECURRENCE-ID')) {
                        $exceptions[] = $this->_vEvent($component, $key, $method, $components);
                    }
                } catch (Horde_ICalendar_Exception $e) {
                    $out[] = $this->_vEvent($component, $key, $method, $components);
                }
                break;

            case 'vTodo':
                $out[] = $this->_vTodo($component, $key, $method);
                break;

            case 'vTimeZone':
                // Ignore them.
                break;

            case 'vFreebusy':
                $out[] = $this->_vFreebusy($component, $key, $method);
                break;

            // @todo: handle stray vcards here as well.
            default:
                $out[] = sprintf(_("Unhandled component of type: %s"), $component->getType());
                break;
            }
        }

        // If we don't have any other parts, any exceptions should be shown
        // since this is likely an update to a series instance such as a
        // cancellation etc...
        if (empty($out)) {
            $out = $exceptions;
        }

        $view = $this->_getViewOb();
        $view->formid = $imple->getDomId();
        $view->out = implode('', $out);

        return array(
            $mime_id => array(
                'data' => $view->render('base'),
                'type' => 'text/html; charset=UTF-8'
            )
        );
    }

    /**
     * Generate the html for a vFreebusy.
     */
    protected function _vFreebusy($vfb, $id, $method)
    {
        global $notification, $prefs, $registry;

        $desc = '';
        $sender = $vfb->getName();

        switch ($method) {
        case 'PUBLISH':
            $desc = _("%s has sent you free/busy information.");
            break;

        case 'REQUEST':
            $sender = $this->getConfigParam('imp_contents')->getHeader()->getHeader('From');
            $desc = _("%s requests your free/busy information.");
            break;

        case 'REPLY':
            $desc = _("%s has replied to a free/busy request.");
            break;
        }

        $view = $this->_getViewOb();
        $view->desc = sprintf($desc, $sender);

        try {
            $start = $vfb->getAttribute('DTSTART');
            $view->start = is_array($start)
                ? strftime($prefs->getValue('date_format'), mktime(0, 0, 0, $start['month'], $start['mday'], $start['year']))
                : strftime($prefs->getValue('date_format'), $start) . ' ' . date($prefs->getValue('twentyFour') ? ' G:i' : ' g:i a', $start);
        } catch (Horde_Icalendar_Exception $e) {}

        try {
            $end = $vfb->getAttribute('DTEND');
            if (is_array($end)) {
                $view->end = strftime(
                    $prefs->getValue('date_format'),
                    mktime(0, 0, 0, $end['month'], $end['mday'], $end['year'])
                );
            } elseif (is_int($end)) {
                $view->end = strftime($prefs->getValue('date_format'), $end) .
                    ' ' .
                    date($prefs->getValue('twentyFour') ? ' G:i' : ' g:i a', $end);
            } else {
                $view->end = '';
            }
        } catch (Horde_Icalendar_Exception $e) {}

        $options = array();
        switch ($method) {
        case 'PUBLISH':
        case 'REPLY':
            if ($registry->hasMethod('calendar/import_vfreebusy')) {
                if ($this->_autoUpdateReply(($method == 'PUBLISH') ? self::AUTO_UPDATE_FB_PUBLISH : self::AUTO_UPDATE_EVENT_REPLY, $sender)) {
                    try {
                        $registry->call('calendar/import_vfreebusy', array($vfb));
                        $notification->push(_("The user's free/busy information was sucessfully stored."), 'horde.success');
                    } catch (Horde_Exception $e) {
                        $notification->push(sprintf(_("There was an error importing user's free/busy information: %s"), $e->getMessage()), 'horde.error');
                    }
                } else {
                    $options['import'] = _("Remember the free/busy information.");
                }
            } else {
                $options['nosup'] = _("Reply with Not Supported Message");
            }
            break;

        case 'REQUEST':
            if ($registry->hasMethod('calendar/getFreeBusy')) {
                $options['reply'] = _("Reply with requested free/busy information.");
                $options['reply2m'] = _("Reply with free/busy for next 2 months.");
            } else {
                $options['nosup'] = _("Reply with Not Supported Message");
            }

            $options['deny'] = _("Deny request for free/busy information");
            break;
        }

        if (!empty($options)) {
            reset($options);
            $view->options = $options;
            $view->options_id = $id;
        }

        return $view->render('action');
    }

    /**
     * Generate the HTML for a vEvent.
     */
    protected function _vEvent($vevent, $id, $method = 'PUBLISH', $components = array())
    {
        global $injector, $prefs, $registry, $notification;

        $attendees = null;
        $desc = '';
        $sender = $vevent->organizerName();
        $options = array();

        try {
            if (($attendees = $vevent->getAttribute('ATTENDEE')) &&
                !is_array($attendees)) {
                $attendees = array($attendees);
            }
        } catch (Horde_Icalendar_Exception $e) {}

        switch ($method) {
        case 'PUBLISH':
            $desc = _("%s wishes to make you aware of \"%s\".");
            if ($registry->hasMethod('calendar/import')) {
                $options['import'] = _("Add this to my calendar");
            }
            break;

        case 'REQUEST':
            // Check if this is an update.
            try {
                $calendars = $registry->call('calendar/listCalendars', array(true));
                $registry->call('calendar/export', array($vevent->getAttributeSingle('UID'), 'text/calendar', array(), $calendars));
                $desc = _("%s wants to notify you about changes in \"%s\".");
                $is_update = true;
            } catch (Horde_Exception $e) {
                $desc = _("%s wishes to make you aware of \"%s\".");
                $is_update = false;

                // Check that you are one of the attendees here.
                if (!empty($attendees)) {
                    $identity = $injector->getInstance('IMP_Identity');
                    for ($i = 0, $c = count($attendees); $i < $c; ++$i) {
                        $attendee = parse_url($attendees[$i]);
                        if (!empty($attendee['path']) &&
                            $identity->hasAddress($attendee['path'])) {
                            $desc = _("%s requests your presence at \"%s\".");
                            break;
                        }
                    }
                }
            }

            if ($is_update && $registry->hasMethod('calendar/replace')) {
                $options['accept-import'] = _("Accept and update in my calendar");
                $options['import'] = _("Update in my calendar");
            } elseif ($registry->hasMethod('calendar/import')) {
                $options['accept-import'] = _("Accept and add to my calendar");
                $options['import'] = _("Add to my calendar");
            }

            $options['accept'] = _("Accept request");
            $options['tentative'] = _("Tentatively Accept request");
            $options['deny'] = _("Deny request");
            // $options['delegate'] = _("Delegate position");
            break;

        case 'ADD':
            $desc = _("%s wishes to amend \"%s\".");
            if ($registry->hasMethod('calendar/import')) {
                $options['import'] = _("Update this event on my calendar");
            }
            break;

        case 'REFRESH':
            $desc = _("%s wishes to receive the latest information about \"%s\".");
            $options['send'] = _("Send Latest Information");
            break;

        case 'REPLY':
            $desc = _("%s has replied to the invitation to \"%s\".");
            $from = $this->getConfigParam('imp_contents')->getHeader()->getHeader('from');
            $sender = $from
                ? $from->getAddressList(true)->first()->bare_address
                : null;
            if ($registry->hasMethod('calendar/updateAttendee') &&
                $this->_autoUpdateReply(self::AUTO_UPDATE_EVENT_REPLY, $sender)) {
                try {
                    $registry->call('calendar/updateAttendee', array(
                        $vevent,
                        $sender
                    ));
                    $notification->push(_("Respondent Status Updated."), 'horde.success');
                } catch (Horde_Exception $e) {
                    $notification->push(sprintf(_("There was an error updating the event: %s"), $e->getMessage()), 'horde.error');
                }
            } else {
                $options['update'] = _("Update respondent status");
            }
            break;

        case 'CANCEL':
            try {
                $vevent->getAttributeSingle('RECURRENCE-ID');
                $params = $vevent->getAttribute('RECURRENCE-ID', true);
                foreach ($params as $param) {
                    if (array_key_exists('RANGE', $param)) {
                        $desc = _("%s has cancelled multiple instances of the recurring \"%s\".");
                    }
                    break;
                }
                if (empty($desc)) {
                    $desc = _("%s has cancelled an instance of the recurring \"%s\".");
                }
                if ($registry->hasMethod('calendar/replace')) {
                    $options['delete'] = _("Update in my calendar");
                }
            } catch (Horde_Icalendar_Exception $e) {
                $desc = _("%s has cancelled \"%s\".");
                if ($registry->hasMethod('calendar/delete')) {
                    $options['delete'] = _("Delete from my calendar");
                }
            }
            break;
        }

        $view = $this->_getViewOb();

        try {
            $start = $vevent->getAttribute('DTSTART');
            $view->start = is_array($start)
                ? strftime($prefs->getValue('date_format'), mktime(0, 0, 0, $start['month'], $start['mday'], $start['year']))
                : strftime($prefs->getValue('date_format'), $start) . ' ' . date($prefs->getValue('twentyFour') ? ' G:i' : ' g:i a', $start);
            $start_date = new Horde_Date($start);
        } catch (Horde_Icalendar_Exception $e) {
            $start = null;
        }

        try {
            $end = $vevent->getAttribute('DTEND');
            $view->end = is_array($end)
                ? strftime($prefs->getValue('date_format'), mktime(0, 0, 0, $end['month'], $end['mday'], $end['year']))
                : strftime($prefs->getValue('date_format'), $end) . ' ' . date($prefs->getValue('twentyFour') ? ' G:i' : ' g:i a', $end);
        } catch (Horde_Icalendar_Exception $e) {
            $end = null;
        }

        try {
            $summary = $vevent->getAttributeSingle('SUMMARY');
            $view->summary = $summary;
        } catch (Horde_Icalendar_Exception $e) {
            $summary = _("Unknown Meeting");
            $view->summary_error = _("None");
        }

        $view->desc = sprintf($desc, $sender, $summary);

        try {
            $view->desc2 = $vevent->getAttributeSingle('DESCRIPTION');
        } catch (Horde_Icalendar_Exception $e) {}

        try {
            $view->comment = $vevent->getAttributeSingle('COMMENT');
        } catch (Horde_Icalendar_Exception $e) {}

        try {
            $view->loc = $vevent->getAttributeSingle('LOCATION');
        } catch (Horde_Icalendar_Exception $e) {}

        try {
            $rrule = $vevent->getAttribute('RRULE');
        } catch (Horde_Icalendar_Exception $e) {
            $rrule = array();
        }
        if (!is_array($rrule)) {
            $recurrence = new Horde_Date_Recurrence($start_date);
            if (strpos($rrule, '=') !== false) {
                $recurrence->fromRRule20($rrule);
            } else {
                $recurrence->fromRRule10($rrule);
            }

            // Add exceptions
            try {
                $exdates = $vevent->getAttributeValues('EXDATE');
                if (is_array($exdates)) {
                    foreach ($exdates as $exdate) {
                        if (is_array($exdate)) {
                            $recurrence->addException(
                                (int)$exdate['year'],
                                (int)$exdate['month'],
                                (int)$exdate['mday']);
                        }
                    }
                }
            } catch (Horde_ICalendar_Exception $e) {}

            $view->recurrence = $recurrence->toString($prefs->getValue('date_format'), $prefs->getValue('time_format'));
            $view->exceptions = array();
            foreach ($components as $key => $component) {
                try {
                    if ($component->getAttribute('RECURRENCE-ID') && $component->getAttributeSingle('UID') == $vevent->getAttributeSingle('UID')) {
                        if ($ex = $this->_vEventException($component, $key, $method)) {
                            $view->exceptions[] = $ex;
                        }
                    }
                } catch (Horde_Icalendar_Exception $e) {}
            }
        }

        if (!empty($attendees)) {
            $view->attendees = $this->_parseAttendees($vevent, $attendees);
        }

        if (!is_null($start) &&
            !is_null($end) &&
            in_array($method, array('PUBLISH', 'REQUEST', 'ADD')) &&
            $registry->hasMethod('calendar/getFbCalendars') &&
            $registry->hasMethod('calendar/listEvents')) {
            try {
                $calendars = $registry->call('calendar/getFbCalendars');

                $vevent_start = new Horde_Date($start);
                $vevent_end = new Horde_Date($end);

                // Check if it's an all-day event.
                if (is_array($start)) {
                    $vevent_allDay = true;
                    $vevent_end = $vevent_end->sub(1);
                } else {
                    $vevent_allDay = false;
                    $time_span_start = $vevent_start->sub($prefs->getValue('conflict_interval') * 60);
                    $time_span_end = $vevent_end->add($prefs->getValue('conflict_interval') * 60);
                }

                $events = $registry->call('calendar/listEvents', array($start, $vevent_end, $calendars, false));

                // TODO: Check if there are too many events to show.
                $conflicts = array();
                foreach ($events as $calendar) {
                    foreach ($calendar as $event) {
                        // TODO: WTF? Why are we using Kronolith constants
                        // here?
                        if (in_array($event->status, array(Kronolith::STATUS_CANCELLED, Kronolith::STATUS_FREE))) {
                            continue;
                        }

                        if ($vevent_allDay || $event->isAllDay()) {
                            $type = 'collision';
                        } elseif (($event->end->compareDateTime($time_span_start) <= -1) ||
                                ($event->start->compareDateTime($time_span_end) >= 1)) {
                            continue;
                        } elseif (($event->end->compareDateTime($vevent_start) <= -1) ||
                                  ($event->start->compareDateTime($vevent_end) >= 1)) {
                            $type = 'nearcollision';
                        } else {
                            $type = 'collision';
                        }

                        $conflicts[] = array(
                            'collision' => ($type == 'collision'),
                            'range' => $event->getTimeRange(),
                            'title' => $event->getTitle()
                        );
                    }
                }

                if (!empty($conflicts)) {
                    $view->conflicts = $conflicts;
                }
            } catch (Horde_Exception $e) {}
        }

        if (!empty($options)) {
            reset($options);
            $view->options = $options;
            $view->options_id = $id;
        }

        return $view->render('action');
    }

    /**
     * Generate the HTML for a vEvent.
     */
    protected function _vEventException($vevent, $id, $method = 'PUBLISH')
    {
        global $prefs, $registry;

        $attendees = null;
        $options = array();

        try {
            if (($attendees = $vevent->getAttribute('ATTENDEE')) &&
                !is_array($attendees)) {
                $attendees = array($attendees);
            }
        } catch (Horde_Icalendar_Exception $e) {}

        $view = $this->_getViewOb();

        try {
            $start = $vevent->getAttribute('DTSTART');
            $view->start = is_array($start)
                ? strftime($prefs->getValue('date_format'), mktime(0, 0, 0, $start['month'], $start['mday'], $start['year']))
                : strftime($prefs->getValue('date_format'), $start) . ' ' . date($prefs->getValue('twentyFour') ? ' G:i' : ' g:i a', $start);
        } catch (Horde_Icalendar_Exception $e) {
            $start = null;
        }

        try {
            $end = $vevent->getAttribute('DTEND');
            // Check for exceptions that are over and done with.
            $d = new Horde_Date($end);
            if ($d->timestamp() < time()) {
                return false;
            }
            $view->end = is_array($end)
                ? strftime($prefs->getValue('date_format'), mktime(0, 0, 0, $end['month'], $end['mday'], $end['year']))
                : strftime($prefs->getValue('date_format'), $end) . ' ' . date($prefs->getValue('twentyFour') ? ' G:i' : ' g:i a', $end);
        } catch (Horde_Icalendar_Exception $e) {
            $end = null;
        }

        try {
            $summary = $vevent->getAttribute('SUMMARY');
            $view->summary = $summary;
        } catch (Horde_Icalendar_Exception $e) {
            $summary = _("Unknown Meeting");
            $view->summary_error = _("None");
        }

        try {
            $view->desc2 = $vevent->getAttribute('DESCRIPTION');
        } catch (Horde_Icalendar_Exception $e) {}

        try {
            $view->loc = $vevent->getAttribute('LOCATION');
        } catch (Horde_Icalendar_Exception $e) {}

        if (!empty($attendees)) {
            $view->attendees = $this->_parseAttendees($vevent, $attendees);
        }

        if (!is_null($start) &&
            !is_null($end) &&
            in_array($method, array('PUBLISH', 'REQUEST', 'ADD')) &&
            $registry->hasMethod('calendar/getFbCalendars') &&
            $registry->hasMethod('calendar/listEvents')) {
            try {
                $calendars = $registry->call('calendar/getFbCalendars');
                $vevent_start = new Horde_Date($start);
                $vevent_end = new Horde_Date($end);

                // Check if it's an all-day event.
                if (is_array($start)) {
                    $vevent_allDay = true;
                    $vevent_end = $vevent_end->sub(1);
                } else {
                    $vevent_allDay = false;
                    $time_span_start = $vevent_start->sub($prefs->getValue('conflict_interval') * 60);
                    $time_span_end = $vevent_end->add($prefs->getValue('conflict_interval') * 60);
                }

                $events = $registry->call('calendar/listEvents', array($start, $vevent_end, $calendars, false));

                // TODO: Check if there are too many events to show.
                $conflicts = array();
                foreach ($events as $calendar) {
                    foreach ($calendar as $event) {
                        // TODO: WTF? Why are we using Kronolith constants
                        // here?
                        if (in_array($event->status, array(Kronolith::STATUS_CANCELLED, Kronolith::STATUS_FREE))) {
                            continue;
                        }

                        if ($vevent_allDay || $event->isAllDay()) {
                            $type = 'collision';
                        } elseif (($event->end->compareDateTime($time_span_start) <= -1) ||
                                ($event->start->compareDateTime($time_span_end) >= 1)) {
                            continue;
                        } elseif (($event->end->compareDateTime($vevent_start) <= -1) ||
                                  ($event->start->compareDateTime($vevent_end) >= 1)) {
                            $type = 'nearcollision';
                        } else {
                            $type = 'collision';
                        }

                        $conflicts[] = array(
                            'collision' => ($type == 'collision'),
                            'range' => $event->getTimeRange(),
                            'title' => $event->getTitle()
                        );
                    }
                }

                if (!empty($conflicts)) {
                    $view->conflicts = $conflicts;
                }
            } catch (Horde_Exception $e) {}
        }

        if (!empty($options)) {
            reset($options);
            $view->options = $options;
            $view->options_id = $id;
        }

        return $view->render('action');
    }

    /**
     * Generate the html for a vEvent.
     */
    protected function _vTodo($vtodo, $id, $method)
    {
        global $registry;
        global $notification;

        $desc = '';
        $options = array();

        try {
            $organizer = $vtodo->getAttribute('ORGANIZER', true);
            if (isset($organizer[0]['CN'])) {
                $sender = $organizer[0]['CN'];
            } else {
                $organizer = parse_url($vtodo->getAttribute('ORGANIZER'));
                $sender = $organizer['path'];
            }
        } catch (Horde_Icalendar_Exception $e) {
            $sender = _("An unknown person");
        }

        try {
            if (($attendees = $vtodo->getAttribute('ATTENDEE')) &&
                !is_array($attendees)) {
                $attendees = array($attendees);
            }
        } catch (Horde_Icalendar_Exception $e) {}

        switch ($method) {
        case 'PUBLISH':
            $desc = _("%s wishes to make you aware of \"%s\".");
            if ($registry->hasMethod('tasks/import')) {
                $options['import'] = _("Add this to my tasklist");
            }
            break;

        case 'REQUEST':
            $desc = _("%s wishes to assign you \"%s\".");
            if ($registry->hasMethod('tasks/import')) {
                $options['accept-import'] = _("Accept and add this to my tasklist");
                $options['import'] = _("Add this to my tasklist");
                $options['deny'] = _("Deny task assignment");
            }
            break;

        case 'REPLY':
            $desc = _("%s has replied to the assignment of task \"%s\".");
            $from = $this->getConfigParam('imp_contents')->getHeader()->getHeader('from');
            $sender = $from
                ? $from->getAddressList(true)->first()->bare_address
                : null;

            if ($registry->hasMethod('tasks/updateAttendee') &&
                $this->_autoUpdateReply(self::AUTO_UPDATE_TASK_REPLY, $sender)) {
                try {
                    $registry->call('tasks/updateAttendee', array(
                        $vtodo,
                        $sender
                    ));
                    $notification->push(_("Respondent Status Updated."), 'horde.success');
                } catch (Horde_Exception $e) {
                    $notification->push(sprintf(_("There was an error updating the task: %s"), $e->getMessage()), 'horde.error');
                }
            } elseif ($registry->hasMethod('tasks/updateAttendee')) {
                $options['update'] = _("Update respondent status");
            }
            break;
        }

        $view = $this->_getViewOb();

        try {
            $view->priority = intval($vtodo->getAttribute('PRIORITY'));
        } catch (Horde_Icalendar_Exception $e) {}

        try {
            $summary = $view->summary = $vtodo->getAttribute('SUMMARY');
        } catch (Horde_Icalendar_Exception $e) {
            $summary = _("Unknown Task");
            $view->summary_error = _("None");
        }

        $view->desc = sprintf($desc, $sender, $summary);

        try {
            $view->desc2 = $vtodo->getAttribute('DESCRIPTION');
        } catch (Horde_Icalendar_Exception $e) {}

        try {
            $view->percentComplete = $vtodo->getAttribute('PERCENT-COMPLETE');
        } catch (Horde_Icalendar_Exception $e) {}

        if (!empty($attendees)) {
            $view->attendees = $this->_parseAttendees($vtodo, $attendees);
        }

        if (!empty($options)) {
            reset($options);
            $view->options = $options;
            $view->options_id = $id;
        }

        return $view->render('action');
    }

    /**
     * Translate the Participation status to string.
     *
     * @param string $value    The value of PARTSTAT.
     * @param string $default  The value to return as default.
     *
     * @return string   The translated string.
     */
    protected function _partstatToString($value, $default = null)
    {
        switch ($value) {
        case 'ACCEPTED':
            return _("Accepted");

        case 'DECLINED':
            return _("Declined");

        case 'TENTATIVE':
            return _("Tentatively Accepted");

        case 'DELEGATED':
            return _("Delegated");

        case 'COMPLETED':
            return _("Completed");

        case 'IN-PROCESS':
            return _("In Process");

        case 'NEEDS-ACTION':
        default:
            return is_null($default)
                ? _("Needs Action")
                : $default;
        }
    }

    /**
     * Get a Horde_View object.
     *
     * @return Horde_View  View object.
     */
    protected function _getViewOb()
    {
        $view = new Horde_View(array(
            'templatePath' => IMP_TEMPLATES . '/itip'
        ));
        $view->addHelper('Text');

        return $view;
    }

    /**
     */
    protected function _parseAttendees($data, $attendees)
    {
        $params = $data->getAttribute('ATTENDEE', true);
        $tmp = array();

        foreach ($attendees as $key => $val) {
            if (!empty($params[$key]['CN'])) {
                $attendee = $params[$key]['CN'];
            } else {
                $val = parse_url($val);
                $attendee = empty($val['path'])
                    ? _("Unknown")
                    : $val['path'];
            }

            $role = _("Required Participant");
            if (isset($params[$key]['ROLE'])) {
                switch ($params[$key]['ROLE']) {
                case 'CHAIR':
                    $role = _("Chair Person");
                    break;

                case 'OPT-PARTICIPANT':
                    $role = _("Optional Participant");
                    break;

                case 'NON-PARTICIPANT':
                    $role = _("Non Participant");
                    break;

                case 'REQ-PARTICIPANT':
                default:
                    // Already set above.
                    break;
                }
            }

            $status = _("Awaiting Response");
            if (isset($params[$key]['PARTSTAT'])) {
                $status = $this->_partstatToString($params[$key]['PARTSTAT'], $status);
            }

            $tmp[] = array(
                'attendee' => $attendee,
                'role' => $role,
                'status' => $status
            );
        }

        return $tmp;
    }

    /**
     * Determine if we are going to auto-update the reply.
     *
     * @param string $type    The type of reply. Must match one of the
     *                        'auto_update_*' configuration keys in the iTip
     *                        mime viewer configuration.
     * @param string $sender  The sender.
     *
     * @return boolean
     */
    protected function _autoUpdateReply($type, $sender)
    {
        if (!empty($this->_conf[$type])) {
            if (is_array($this->_conf[$type])) {
                $ob = new Horde_Mail_Rfc822_Address($sender);
                foreach ($this->_conf[$type] as $val) {
                    if ($ob->matchDomain($val)) {
                        return true;
                    }
                }
            } else {
                return true;
            }
        }

        return false;
    }

}
