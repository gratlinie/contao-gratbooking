<?php

declare(strict_types=1);

$L = &$GLOBALS['TL_LANG']['tl_gb_booking'];
$L['booking_legend'] = 'Appointment';
$L['contact_legend'] = 'Contact';
$L['meeting_legend'] = 'Zoom and Google';
$L['log_legend'] = 'History';
$L['type'] = ['Type', ''];
$L['start'] = ['Start', ''];
$L['end'] = ['End', ''];
$L['status'] = ['Status', ''];
$L['statusOptions'] = ['confirmed' => 'booked', 'cancelled' => 'cancelled', 'pending' => 'awaiting payment'];
$L['firstname'] = ['First name', ''];
$L['lastname'] = ['Last name', ''];
$L['email'] = ['Email address', ''];
$L['phone'] = ['Phone', ''];
$L['message'] = ['Message', ''];
$L['zoomJoinUrl'] = ['Zoom link', ''];
$L['zoomPassword'] = ['Zoom passcode', ''];
$L['zoomMeetingId'] = ['Zoom meeting ID', ''];
$L['googleEventId'] = ['Google event ID', ''];
$L['createdAt'] = ['Booked on', ''];
$L['privacyAt'] = ['Privacy consent', ''];
$L['reminderSentAt'] = ['Reminder sent', ''];
$L['cancelledAt'] = ['Cancelled on', ''];
$L['edit'] = ['Edit', 'Edit booking ID %s'];
$L['cancel'] = ['Cancel', 'Cancel booking ID %s (deletes the Google event and Zoom meeting, the customer receives a cancellation)'];
$L['show'] = ['Details', 'Show the details of booking ID %s'];
$L['cancelConfirm'] = 'Really cancel this appointment? The customer receives a cancellation by email.';
$L['cancelledMsg'] = 'Booking %s cancelled.';
$L['alreadyCancelled'] = 'The booking is already cancelled.';
