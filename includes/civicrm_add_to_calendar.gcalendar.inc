<?php

define('CIVICRM_GCALENDAR_BASE_PATH', 'https://www.google.com/calendar/render');

/**
 * Builds a Google Calendar URL for the given CiviCRM event ID.
 *
 * @param int $event_id
 *  The event ID
 *
 * @return string
 *  The Google Calendar template URL.
 */
function civicrm_add_to_calendar_build_gcalendar_url($event_id) {
  $events = civicrm_api3('Event', 'get', [
    'id' => $event_id,
    'api.LocBlock.get' => [
      'api.Address.get' => [
        'api.StateProvince.get' => [
          'api.Country.get' => 1,
        ],
      ],
    ],
  ]);

  if (!$events['count']) {
    return FALSE;
  }

  $event = $events['values'][$event_id];
  if (!$dates = civicrm_add_to_calendar_format_gcalendar_dates($event)) {
    return FALSE;
  }

  $args = [
    'action' => 'TEMPLATE',
    'text' => htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8'),
    'dates' => $dates,
    'details' => civicrm_add_to_calendar_format_gcalendar_details($event),
    'sprop' => 'website:' . $_SERVER['HTTP_HOST'],
    'website' => CRM_Utils_System::url('civicrm/event/info', ['id' => $event_id, 'reset' => 1], TRUE),
  ];

  if (CRM_Utils_Array::value('loc_block_id', $event) && $location = civicrm_add_to_calendar_format_gcalendar_location($event)) {
    $args['location'] = $location;
  }

  return CIVICRM_GCALENDAR_BASE_PATH .'?' . http_build_query($args);
}

/**
 * Builds event dates for Google Calendar from the given event info.
 *
 * @param array $event
 *  The event information array from CiviCRM API.
 *
 * @return string
 *  Start & end dates formatted as YYYYMMDDToHHmmSSZ/YYYYMMDDToHHmmSSZ.
 */
function civicrm_add_to_calendar_format_gcalendar_dates($event) {
  if (empty($event['start_date'])) {
    return '';
  }

  $dates = [$event['start_date']];

  // If end date is not available, set start date as default.
  $dates[] = empty($event['end_date']) ? $event['start_date'] : $event['end_date'];

  // Get site timezone.
  if (!empty($event['timezone'])) {
    $timezone = civicrm_tz_lookup();
    $tz = $timezone[$event['timezone']];
  }
  if (!$tz) {
    $tz =  CRM_Core_Config::singleton()->userSystem->getTimeZoneString();
  }

  foreach ($dates as $i => $date) {
    // Convert event date to GMT.
    $date = new DateTime($date, new DateTimeZone($tz));
    $date->setTimezone(new DateTimeZone('GMT'));
    $dates[$i] = $date->format('Ymd\THi00\Z');
  }

  return implode('/', $dates);
}

function civicrm_tz_lookup() {
  $tz = [
    'GMT-10:00' => 'Pacific/Honolulu',
    'GMT-05:00' => 'America/New_York',
    'GMT-06:00' => 'America/Chicago',
    'GMT-07:00' => 'America/Denver',
    'GMT-08:00' => 'America/Los_Angeles',
  ];
  return $tz;
}

/**
 * Builds event details for Google Calendar from the given event info.
 *
 * @param array $event
 *  The event information array from CiviCRM API.
 *
 * @return string $output
 *  The formatted and filtered event description.
 */
function civicrm_add_to_calendar_format_gcalendar_details($event) {
  $output = '';

  /* Drupal-specific functions ...
  if (!empty($event['description']) && TRUE) { // variable_get('civicrm_add_to_calendar_gcalendar_desc_enabled', TRUE)
    $format_id = variable_get('civicrm_add_to_calendar_gcalendar_desc_filter_format', 'civicrm_add_to_calendar_gcalendar');
    $output = check_markup($event['description'] . '<br>', $format_id);
  }

  if (TRUE) { // variable_get('civicrm_add_to_calendar_gcalendar_civi_link_enabled', TRUE)
    // Appends a link to the Civi event at the bottom of the description.
    $output .= ts('See event page: <a href="@url">@url</a>', [
      'domain' => CALLINKS_NAME,
      '@url' => CRM_Utils_System::url('civicrm/event/info', ['id' => $event['id'], 'reset' => 1], TRUE),
    ]);
  }
  */

  $output = $event['confirm_email_text'] ?? strip_tags($event['description'], '<b><a><u><strong>');
  return $output;
}

/**
 * Builds location string for Google Calendar event link.
 *
 * @param array $event
 *  The event information array from CiviCRM API.
 *
 * @return string
 *  The formatted location string.
 */
function civicrm_add_to_calendar_format_gcalendar_location($event) {
  // Merging nested event data.
  $addr = $event;
  foreach (['LocBlock', 'Address', 'StateProvince', 'Country'] as $obj_type) {
    $key = 'api.' . $obj_type . '.get';
    if (empty($addr[$key]) || !$addr[$key]['count']) {
      if (!isset($addr['location_type_id'])) {
        return FALSE;
      }

      break;
    }

    $addr = $addr[$key]['values'][0] + $addr;
  }

  // The following array is a template to build the location string. Items will
  // be separated by commas, while subitems will be separated by whitespaces.
  $format = [
    ['street_address'],
    ['supplemental_address_1'],
    ['supplemental_address_2'],
    ['city', 'abbreviation', 'postal_code'],
    ['name'],
  ];

  $lines = [];
  foreach ($format as $fields) {
    $line = [];
    foreach ($fields as $field) {
      if (!empty($addr[$field])) {
        $line[] = $addr[$field];
      }
    }

    if (!empty($line)) {
      $lines[] = implode(' ', $line);
    }
  }

  return htmlspecialchars(implode(', ', $lines), ENT_QUOTES, 'UTF-8');
}
