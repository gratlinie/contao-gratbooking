<?php

declare(strict_types=1);

$GLOBALS['TL_LANG']['tl_nc_notification']['type']['gratbooking'] = 'GratBooking';
$GLOBALS['TL_LANG']['tl_nc_notification']['type']['gratbooking_booked'] = ['Appointment booked', 'Confirmation to the customer (##booking_email##) and a note to you (##admin_email##). Calendar file: attachment tokens = ##booking_ics##.'];
$GLOBALS['TL_LANG']['tl_nc_notification']['type']['gratbooking_rescheduled'] = ['Appointment moved', 'After rescheduling; old slot in ##previous_date##, ##previous_time##.'];
$GLOBALS['TL_LANG']['tl_nc_notification']['type']['gratbooking_cancelled'] = ['Appointment cancelled', 'After a cancellation by the customer or in the back end. The calendar file removes the appointment from the customer’s calendar.'];
$GLOBALS['TL_LANG']['tl_nc_notification']['type']['gratbooking_reminder'] = ['Appointment reminder', 'Sent as many hours before the appointment as set in the calendar.'];
