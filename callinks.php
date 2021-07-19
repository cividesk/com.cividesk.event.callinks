/**
 * Implements hook_civicrm_alterContent().
 */
function civicrm_addtocal_alterContent(&$content, $context, $tplName, &$object) {
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

  if (empty($object->{$key}) || strpos($content, 'iCal_links-section') === FALSE) {
    return;
  }


  $event_id = $object->{$key};
  $links = [];

  // iCal link (native CiviCRM)
  $links['ical'] = [
    'title' => t('iCalendar'),
    'icon' => '', // existing icon
    'path' => url('civicrm/event/ical', [
      'query' => ['reset' => 1, 'id' => $event_id],
    ]),
  ];

  // Google Calendar link
  include_once('includes/civicrm_add_to_calendar.gcalendar');
  if ($path = civicrm_add_to_calendar_build_gcalendar_url($event_id)) {
    $links['gcalendar'] = [
      'title' => t('Google Calendar'),
      'icon' =>
      'path' => $path,
    ];
  }

  // Calculate the replacement string
  $replacement = '';
  foreach ($links as $link) {
    $replacement .= "<a href='$link[path]' title='$link[title]'><img src=$link[icon] alt=$link[title]></a>";
  }

  // Perform the replacement in the page
  $anchor = '<div class="action-link section iCal_links-section">';
  $closed = '<\\/div>'; // escape \ because of the regexp
  $content = preg_replace($anchor.'(.*)'.$closed, $anchor.$replacement.$closed, $content);
}
