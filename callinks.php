<?php

define('CALLINKS_NAME', 'com.cividesk.event.callinks');

/**
 * Implements hook_civicrm_alterContent().
 */
function callinks_civicrm_alterContent(&$content, $context, $tplName, &$object) {

  // Determine event_id property based on the page displayed
  switch ($tplName) {
    case 'CRM/Event/Page/EventInfo.tpl':
      $key = '_id';
      break;
    case 'CRM/Event/Form/Registration/ThankYou.tpl':
      $key = '_eventId';
      break;
    default:
      return;
  }

  // Abort if incorrect or not initialized propertly
  if (empty($object->{$key}) || strpos($content, 'iCal_links-section') === FALSE) {
    return;
  }

  // Initialize variables
  $event_id = $object->{$key};
  $links = [];

  // iCal link (native CiviCRM)
  $links['ical'] = [
    'title' => ts('Add to desktop calendar', ['domain' => CALLINKS_NAME]),
    'icon' => 'calendar-plus-o',
    'path' => CRM_Utils_System::url('civicrm/event/ical', ['reset' => 1, 'id' => $event_id]),
  ];

  // Google Calendar link
  require_once('includes/civicrm_add_to_calendar.gcalendar.inc');
  if ($path = civicrm_add_to_calendar_build_gcalendar_url($event_id)) {
    $links['gcalendar'] = [
      'title' => ts('Add to Google calendar', ['domain' => CALLINKS_NAME]),
      'icon' => 'google-plus-square',
      'path' => $path,
    ];
  }

  // Calculate the replacement string
  $links_html = '';
  foreach ($links as $link) {
    $css = "font-size: 32px; margin-right: 8px;";
    // Open external links in a new window/tab
    $target = (substr($link[path], 0, 4) == 'http') ? "target='_blank'" : '';
    $links_html .= "<a href='$link[path]' $target title='$link[title]'><i class='crm-i fa-$link[icon]' style='$css'></i></a>";
  }

  // Perform the replacement in the page - minimal anchor to be more robust against future changes
  $anchor = 'iCal_links-section">';    // <div class="action-link section iCal_links-section">
  $closed = '<\\/div>';                // </div>, but / is special char for regexp so escaped
  $pattern = '/' . $anchor . '.*?' . $closed . '/ms';  // ?: ungreedy, m: multiline, s: . includes newline chars
  $replacement = $anchor . $links_html . str_replace('\\', '', $closed);
  $content = preg_replace($pattern, $replacement, $content);
}
