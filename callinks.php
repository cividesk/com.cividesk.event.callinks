<?php

define('CALLINKS_NAME', 'com.cividesk.event.callinks');

function _callinks_links($event_id) {
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
      'icon' => 'google-calendar',
      'path' => $path,
    ];
  }

  return $links;
}

/**
 * Implementation of hook_civicrm_calendar
 */
function callinks_civicrm_calendar(&$info, &$timezone) {
  require_once('includes/civicrm_add_to_calendar.gcalendar.inc');
  foreach ($info as $id => $event) {
    $result = civicrm_api3('Event', 'get', [
      'id' => $event['event_id'],
      'sequential' => 1,
    ]);
    $confirm = $result['values'][0]['confirm_email_text'];

    //description should be confirmation text if any
    if ($confirm) {
      $info[$id]['description'] = $confirm;
    }

    //timezone
    if ($result['values'][0]['timezone']) {
      $tz = civicrm_tz_lookup();
      $timezone = $tz[$result['values'][0]['timezone']];
    }
  }
}

/**
 * Implementation of hook_civicrm_alterMailParams
 */
function callinks_civicrm_alterMailParams(&$params, $context = NULL) {
  if ($params['groupName'] == 'msg_tpl_workflow_event' && in_array($params['valueName'], ['event_online_receipt', 'event_offline_receipt'])) {
    if ($params['valueName'] == 'event_online_receipt') {
      $event_id = $params['tplParams']['event']['id'];
    }
    else {
      $event_id = $params['tplParams']['event_id'];
    }

    $extensionURL = CRM_Core_Config::singleton()->extensionsURL;
    $links = _callinks_links($event_id);

    // Calculate the replacement string
    $links_html = '';
    foreach ($links as $key  => $link) {
      if ($key == 'gcalendar') {
        $links_html .= "<a href='$link[path]' $target ";
      }
      $links_html .= " title='$link[title]'><img src='$extensionURL/common/com.cividesk.event.callinks/img/$link[icon].png'/></a> ";
    }

    //https://projects.cividesk.com/projects/3/tasks/5303
    //handle translation issue (email icons for calendars)
    $params['html'] = str_replace('>' . ts('Download iCalendar File') . '</a>', $links_html, $params['html']);
  }
}

/**
 * Implements hook_civicrm_alterContent().
 */
function callinks_civicrm_alterContent(&$content, $context, $tplName, &$object) {
  $extensionURL = CRM_Core_Config::singleton()->extensionsURL;
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

  $links = _callinks_links($object->{$key});

  // Calculate the replacement string
  $links_html = '';
  foreach ($links as $link) {
    $target = (substr($link['path'], 0, 4) == 'http') ? "target='_blank'" : '';
    $links_html .= "<a href='$link[path]' $target title='$link[title]' style='border-bottom: none;'>";
    // display link as a Font Awesome icon
    if (isset($link['icon'])) {
      $css = "float: left;";
      // Open external links in a new window/tab
      $links_html .= "<img style='$css' src='$extensionURL/common/com.cividesk.event.callinks/img/$link[icon].png'/>";
    }
    // TODO: display links as images from the img/ folder
    // else if (isset($links['image'])) {
    $links_html .= '</a>&nbsp;&nbsp;';
  }

  // Perform the replacement in the page - minimal anchor to be more robust against future changes
  $anchor = 'iCal_links-section">';    // <div class="action-link section iCal_links-section">
  $closed = '<\\/div>';                // </div>, but / is special char for regexp so escaped
  $pattern = '/' . $anchor . '.*?' . $closed . '/ms';  // ?: ungreedy, m: multiline, s: . includes newline chars
  $replacement = $anchor . $links_html . str_replace('\\', '', $closed);
  $content = preg_replace($pattern, $replacement, $content);
}
