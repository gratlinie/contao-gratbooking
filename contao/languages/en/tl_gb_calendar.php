<?php

declare(strict_types=1);

$L = &$GLOBALS['TL_LANG']['tl_gb_calendar'];
$L['title_legend'] = 'Calendar';
$L['google_legend'] = 'Google Calendar (service account)';
$L['zoom_legend'] = 'Zoom (server-to-server app)';
$L['organizer_legend'] = 'Host';
$L['rules_legend'] = 'Cancellation, reminder, management';
$L['publish_legend'] = 'Publishing';
$L['title'] = ['Title', 'Name in the back end.'];
$L['timezone'] = ['Time zone', 'The weekly hours of the appointment types apply in this time zone.'];
$L['googleCredentials'] = ['Service account key (JSON)', 'Paste the JSON file Google downloads when you create a key for the service account. It is not shown again after saving; leave empty to keep it, new content replaces it. Stored without versioning.'];
$L['googleAccount'] = ['Service account address', 'Share the calendar with this address in Google: calendar settings → “Share with specific people” → “Make changes to events”.'];
$L['googleCalendarId'] = ['Calendar for bookings', 'Calendar ID the bookings are written to – usually the Gmail address, otherwise under “Integrate calendar” → “Calendar ID”.'];
$L['googleBusyCalendars'] = ['Further busy calendars', 'One calendar ID per line; their events block slots as well (only busy/free is read). Share each of them, too. The calendar for bookings always counts.'];
$L['zoomAccountId'] = ['Account ID', 'From the server-to-server OAuth app in the Zoom App Marketplace (Develop → Build App).'];
$L['zoomClientId'] = ['Client ID', ''];
$L['zoomClientSecret'] = ['Client secret', 'App scopes: meeting:write:meeting:admin and meeting:delete:meeting:admin (or meeting:write:admin).'];
$L['organizerName'] = ['Name', 'Shown in the calendar file and as ##organizer_name##.'];
$L['organizerEmail'] = ['Email address', 'Recipient of the notes (##admin_email##) and organizer in the calendar file.'];
$L['cancelHours'] = ['Cancel/move until', 'Hours before the appointment; afterwards only directly.'];
$L['reminderHours'] = ['Reminder', 'Hours before the appointment, 0 = no reminder.'];
$L['managePage'] = ['Management page', 'Page with the module “Manage booking”; the link is ##manage_url## in the emails.'];
$L['published'] = ['Publish', 'Allow bookings on the website.'];
$L['googleOk'] = 'Google Calendar readable (service account %s).';
$L['zoomOk'] = 'Zoom sign-in successful.';
$L['new'] = ['New calendar', 'Create a new calendar'];
$L['edit'] = ['Edit', 'Edit calendar ID %s'];
$L['children'] = ['Appointment types', 'Appointment types of calendar ID %s'];
$L['delete'] = ['Delete', 'Delete calendar ID %s'];
$L['toggle'] = ['Publish', 'Publish/unpublish calendar ID %s'];
$L['show'] = ['Details', 'Show the details of calendar ID %s'];
