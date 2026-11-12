<?php

declare(strict_types=1);

$GLOBALS['TL_LANG']['tl_nc_notification']['type']['gratbooking'] = 'GratBooking';
$GLOBALS['TL_LANG']['tl_nc_notification']['type']['gratbooking_booked'] = ['Termin gebucht', 'Bestätigung an den Kunden (##booking_email##) und Hinweis an dich (##admin_email##). Kalenderdatei: „Anhänge über Tokens“ = ##booking_ics##.'];
$GLOBALS['TL_LANG']['tl_nc_notification']['type']['gratbooking_rescheduled'] = ['Termin verschoben', 'Nach dem Umbuchen; alter Termin in ##previous_date##, ##previous_time##.'];
$GLOBALS['TL_LANG']['tl_nc_notification']['type']['gratbooking_cancelled'] = ['Termin abgesagt', 'Nach einer Absage durch den Kunden oder im Backend. Die Kalenderdatei entfernt den Termin aus dem Kalender des Kunden.'];
$GLOBALS['TL_LANG']['tl_nc_notification']['type']['gratbooking_reminder'] = ['Erinnerung an den Termin', 'Geht so viele Stunden vor dem Termin raus, wie im Kalender eingestellt.'];
