<?php
class PluginGoogleCalendar{
  public $filename = null;
  public $calendar = null;
  public $raw_data = null;
  public $file_get_contents = null;
  public $failure = true;
  public function getCompleteDateArray($dates_in_key){
    //Get start and end.
    $start = null;
    $end = null;
    foreach ($dates_in_key as $key => $value) {
      if($start === null){$start = $key;}
      $end = $key;
    }
    //Create complete array and insert values.
    $dates = array();
    for($i=0;$i<1000;$i++){
      if(array_key_exists($start, $dates_in_key)){
        $dates[$start] = $dates_in_key[$start];
      }else{
        $dates[$start] = null;
      }
      $start = date('Y-m-d', strtotime("+1 days", strtotime($start)));
      if($start > $end){
        break;
      }
    }
    return $dates;
  }
  public function init(){
    if(strtolower(wfPhpfunc::substr($this->filename, 0, 4)) != 'http'){
      return null;
    }
    try {
      $arrContextOptions=array(
          "ssl"=>array(
              "verify_peer"=>false,
              "verify_peer_name"=>false,
          ),
      );        
      $this->file_get_contents = file_get_contents($this->filename, false, stream_context_create($arrContextOptions));
    } catch (Exception $exc) {
      $this->failure = true;
      return null;
    }
    if($this->file_get_contents===false){
      $this->failure = true;
    }else{
      $this->failure = false;
    }
    $temp = explode("\n", $this->file_get_contents);
    //Fix where values are in multiple lines.
    $raw_data = array();
    $i = null;
    foreach ($temp as $key => $value) {
      if(wfPhpfunc::substr($value, 0, 1)==' '){
        $raw_data[$i] = $raw_data[$i].$value;
      }else{
        $i = $key;
        $raw_data[$i] = $value;
      }
    }
    $this->raw_data = $raw_data;
    $vcalendar = false;
    $calendar = array();
    $vevent = false;
    $event = array();
    $temp_calendar = null;
    $temp_event = null;
    foreach ($raw_data as $key => $value) {
      //Calendar header.
      if(wfPhpfunc::strstr($value, 'BEGIN:VEVENT') && sizeof($calendar) == 0 || wfPhpfunc::strstr($value, 'END:VCALENDAR') && sizeof($calendar) == 0){
        $vcalendar = false;
        $calendar = $temp_calendar;
      }
      if($vcalendar){
        $x = explode(":", $value);
        $temp_calendar[$x[0]] = $x[1];
      }
      if(wfPhpfunc::strstr($value, 'BEGIN:VCALENDAR')){
        $vcalendar = true;
        $temp_calendar = array();
      }
      //Events.
      if(wfPhpfunc::strstr($value, 'END:VEVENT')){
        $vevent = false;
        $event[] = $temp_event;
      }
      if($vevent){
        $i = wfPhpfunc::strstr($value, ':', true);
        $x[0] = $i;
        $x[1] = wfPhpfunc::substr($value, wfPhpfunc::strlen($x[0])+1);
        if(isset($x[1])){
          $temp_event[$x[0]] = $x[1];
        }else{
          $temp_event[$x[0]] = '';
        }
      }
      if(wfPhpfunc::strstr($value, 'BEGIN:VEVENT')){
        $vevent = true;
        $temp_event = array();
      }
    }
    $this->calendar = array('calendar' => $calendar, 'event' => $event);
    return true;
  }
  public function getCalendar(){
    return $this->calendar['calendar'];
  }
  public function getAllDayEvents(){
    $events = array();
    foreach ($this->calendar['event'] as $key => $value) {
      $r = new PluginWfArray($value);
      if($r->get('DTSTART;VALUE=DATE') && $r->get('DTEND;VALUE=DATE')){
        $events[] = array('start' => trim($r->get('DTSTART;VALUE=DATE')), 'end' => trim($r->get('DTEND;VALUE=DATE')), 'description' => trim($r->get('DESCRIPTION')), 'summary' => trim($r->get('SUMMARY')));
      }
    }
    return $events;
  }
  public function getAllTimeEvents(){
    $events = array();
    foreach ($this->calendar['event'] as $key => $value) {
      $r = new PluginWfArray($value);
      if($r->get('DTSTART') && $r->get('DTEND')){
        $events[] = array('start' => trim($r->get('DTSTART')), 'end' => trim($r->get('DTEND')), 'description' => trim($r->get('DESCRIPTION')), 'summary' => trim($r->get('SUMMARY')));
      }
    }
    return $events;
  }
  public function getMinutesPerDay(){
    $day_stat = array();
    foreach ($this->calendar['event'] as $key => $value) {
      if(!trim($value['SUMMARY'])){continue;}
      if(isset($value['DTSTART']) && isset($value['DTEND'])){
        $day = date('Y', strtotime(($value['DTSTART']))).'-'.date('m', strtotime($value['DTSTART'])).'-'.date('d', strtotime($value['DTSTART']));
        $diff = strtotime($value['DTEND'])-strtotime($value['DTSTART']);
        $diff = $diff/60;
        if(isset($day_stat[$day])){
          $day_stat[$day] += $diff;
        }else{
          $day_stat[$day] = $diff;
        }
      }else if(isset($value['DTSTART;VALUE=DATE']) && isset($value['DTEND;VALUE=DATE'])){
      }
    }
    ksort($day_stat);
    return ($day_stat);
  }
  public function getMinutesPerWeek(){
    $week_stat = array();
    foreach ($this->calendar['event'] as $key => $value) {
      if(!trim($value['SUMMARY'])){continue;}
      if(isset($value['DTSTART']) && isset($value['DTEND'])){
        $week = date('Y', strtotime(($value['DTSTART']))).':'.date('W', strtotime(($value['DTSTART'])));
        $diff = strtotime($value['DTEND'])-strtotime($value['DTSTART']);
        $diff = $diff/60;
        if(isset($week_stat[$week])){
          $week_stat[$week] += $diff;
        }else{
          $week_stat[$week] = $diff;
        }
      }else if(isset($value['DTSTART;VALUE=DATE']) && isset($value['DTEND;VALUE=DATE'])){
      }
    }
    ksort($week_stat);
    return $week_stat;
  }
  public function getMinutesPerMonth(){
    $month_stat = array();
    foreach ($this->calendar['event'] as $key => $value) {
      if(!trim($value['SUMMARY'])){continue;}
      if(isset($value['DTSTART']) && isset($value['DTEND'])){
        $month = date('Y', strtotime(($value['DTSTART']))).':'.date('m', strtotime(($value['DTSTART'])));
        $diff = strtotime($value['DTEND'])-strtotime($value['DTSTART']);
        $diff = $diff/60;
        if(isset($month_stat[$month])){
          $month_stat[$month] += $diff;
        }else{
          $month_stat[$month] = $diff;
        }
      }else if(isset($value['DTSTART;VALUE=DATE']) && isset($value['DTEND;VALUE=DATE'])){
      }
    }
    return $month_stat;
  }
  public function getMinutesPerWeekAndDay(){
    $return = array();
    $week_stat = $this->getMinutesPerWeek();
    $day_stat = $this->getMinutesPerDay();
    $return['hours'] = number_format(array_sum($day_stat)/60, 2);
    $return['minutes'] = array_sum($day_stat);
    $weeks = array();
    foreach ($week_stat as $key => $value) {
      $temp = array();
      $temp['hours'] = number_format($value/60, 2);
      $temp['minutes'] = $value;
      $days = explode(':', $key);
      $days = $this->getStartAndEndDate($days[1], $days[0]);
      foreach ($days as $key2 => $value2) {
        $hours = null;
        $minutes = null;
        $weekday = date('D', strtotime($value2));
        if(isset($day_stat[$value2])){
          $hours = number_format($day_stat[$value2]/60, 2);
          $minutes = $day_stat[$value2];
        }
        $temp['days'][] = array('date' => $value2, 'weekday' => $weekday, 'hours' => $hours, 'minutes' => $minutes);
      }
      $weeks[$key] = $temp;
    }
    krsort($weeks);
    $return['weeks'] = $weeks;
    return $return;
  }
  public function getStartAndEndDate($week, $year) {
    // Adding leading zeros for weeks 1 - 9.
    $date_string = $year . 'W' . sprintf('%02d', $week);
    $return[] = date('Y-m-d', strtotime($date_string . '7'));
    $return[] = date('Y-m-d', strtotime($date_string . '6'));
    $return[] = date('Y-m-d', strtotime($date_string . '5'));
    $return[] = date('Y-m-d', strtotime($date_string . '4'));
    $return[] = date('Y-m-d', strtotime($date_string . '3'));
    $return[] = date('Y-m-d', strtotime($date_string . '2'));
    $return[] = date('Y-m-d', strtotime($date_string));
    return $return;
  }
}
