<?php

declare(strict_types=1);

$L = &$GLOBALS['TL_LANG']['tl_gb_type'];
$L['title_legend'] = 'Appointment type';
$L['time_legend'] = 'Duration and planning';
$L['hours_legend'] = 'Bookable hours';
$L['meeting_legend'] = 'Location';
$L['publish_legend'] = 'Publishing';
$L['title'] = ['Title', 'E.g. “Free first consultation”.'];
$L['alias'] = ['Alias', 'For a direct link to this type (?termin=…); empty: from the title.'];
$L['description'] = ['Description', 'Short text in the selection and in the calendar file.'];
$L['duration'] = ['Duration', 'Minutes.'];
$L['slotStep'] = ['Interval', 'Distance between possible start times in minutes, e.g. 30 → 9:00, 9:30, 10:00.'];
$L['bufferBefore'] = ['Buffer before', 'Minutes that must be free before the appointment.'];
$L['bufferAfter'] = ['Buffer after', 'Minutes that must be free after the appointment.'];
$L['leadTime'] = ['Lead time', 'Bookable at the earliest this many hours from now.'];
$L['horizon'] = ['Horizon', 'Bookable at most this many days in advance.'];
$L['maxPerDay'] = ['Maximum per day', 'Appointments of this type per day, 0 = unlimited.'];
foreach (['Mo' => 'Monday', 'Tu' => 'Tuesday', 'We' => 'Wednesday', 'Th' => 'Thursday', 'Fr' => 'Friday', 'Sa' => 'Saturday', 'Su' => 'Sunday'] as $key => $day) {
    $L['hours'.$key] = [$day, 'Time windows like “09:00-12:00, 14:00-18:00”; empty = not bookable.'];
}
$L['zoom'] = ['Create a Zoom meeting', 'Create a meeting for every booking; the link is in confirmation, reminder and calendar.'];
$L['location'] = ['Location without Zoom', 'E.g. “Phone” or an address.'];
$L['payment_legend'] = 'Payment';
$L['shopProduct'] = ['Price (GratShop product)', 'Product of the type “direct purchase”; empty = free. The appointment is booked once paid and reserved for 30 minutes until then. Paid appointments can only be rescheduled online.'];
$L['free'] = '– free –';
$L['published'] = ['Publish', 'Offer in the booking module.'];
$L['hoursInvalid'] = 'Invalid time windows: %s – please use “09:00-12:00, 14:00-18:00”.';
$L['new'] = ['New type', 'Create a new appointment type'];
$L['edit'] = ['Edit', 'Edit type ID %s'];
$L['copy'] = ['Duplicate', 'Duplicate type ID %s'];
$L['cut'] = ['Move', 'Move type ID %s'];
$L['delete'] = ['Delete', 'Delete type ID %s'];
$L['toggle'] = ['Publish', 'Publish/unpublish type ID %s'];
$L['show'] = ['Details', 'Show the details of type ID %s'];
