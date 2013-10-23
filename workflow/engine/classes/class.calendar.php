<?php
/**
 * class.calendar.php
 *
 * @package workflow.engine.classes
 *
 * ProcessMaker Open Source Edition
 * Copyright (C) 2004 - 2011 Colosa Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * For more information, contact Colosa Inc, 2566 Le Jeune Rd.,
 * Coral Gables, FL, 33134, USA, or email info@colosa.com.
 *
 *
 *
 * @name calendar
 * created 2010-03-22
 *
 * @author Hugo Loza <hugo@colosa.com> 2010-03-22 *
 */

require_once ("classes/model/CalendarDefinition.php");

/**
 * A Calendar object where it is defined Working Days, Business Hours and Holidays
 * A Calendar is applied to a User, Process or Task
 * Extends CalendarDefinition.
 *
 * @author Hugo Loza <hugo@colosa.com> 2010-03-22
 * @uses CalendarDefinition
 * @package workflow.engine.classes
 *
 */
class calendar extends CalendarDefinition
{

    /**
     *
     * @param string(32) $userUid
     * @param string(32) $proUid
     * @param string(32) $tasUid
     */
    function calendar ($userUid = NULL, $proUid = NULL, $tasUid = NULL)
    {
        $this->userUid = $userUid;
        $this->proUid = $proUid;
        $this->tasUid = $tasUid;
        $this->calendarLog = "";
        $this->setupCalendar( $userUid, $proUid, $tasUid );
    }

    /**
     * Small function used to add important information about the calcs and actions
     * to the log (that log will be saved)
     *
     * @name addCalendarLog
     * @param text $msg
     * @access public
     *
     */
    function addCalendarLog ($msg)
    {
        $this->calendarLog .= "\n" . date( "D M j G:i:s T Y" ) . ": " . $msg;
    }

    /**
     * setupCalendar is used to generate a valid instance of calendar using $userUid, $proUid and $tasUid
     * to find a valid calendar.
     * If there is no valid calendar then use the Default
     *
     * @name setupCalendar
     * @param string(32) $userUid
     * @param string(32) $proUid
     * @param string(32) $tasUid
     * @return void
     */
    function setupCalendar ($userUid, $proUid, $tasUid)
    {
        $calendarDefinition = $this->getCalendarFor( $userUid, $proUid, $tasUid );
        $this->calendarUid = $calendarDefinition['CALENDAR_UID'];
        $this->calendarDefinition = $calendarDefinition;
    }

    /**
     * getnextValidBusinessHoursrange is used recursivily to find a valid BusinessHour
     * for the given $date and $time.
     * This function use all the exeptions defined for
     * Working days, Business Hours and Holidays.
     *
     * @author Hugo Loza <hugo@colosa.com>
     * @name getNextValidBusinessHoursRange
     * @param date $date
     * @param time $time
     *
     * @var array $businessHoursA Object with all the infromation about the valid Business Hours found
     * $return array('DATE'=>$date,'TIME'=>$time,'BUSINESS_HOURS'=>$businessHoursA)
     */
    function getNextValidBusinessHoursRange ($date, $time)
    {
        $this->addCalendarLog( "================= Start : $date,$time ================" );

        //First Validate if is a valid date
        $sw_valid_date = false;
        $sw_date_changed = false;
        while (! $sw_valid_date) {
            $dateArray = explode( "-", $date );
            $hour = 0;
            $minute = 0;
            $second = 0;
            $month = $dateArray[1];
            $day = $dateArray[2];
            $year = $dateArray[0];
            $weekDay = date( "w", mktime( $hour, $minute, $second, $month, $day, $year ) );
            $weekDayLabel = date( "l", mktime( $hour, $minute, $second, $month, $day, $year ) );
            $dateInt = mktime( $hour, $minute, $second, $month, $day, $year );

            $this->addCalendarLog( "**** $weekDayLabel ($weekDay) * $date" );
            $sw_week_day = false;
            $sw_not_holiday = true;
        
            if (in_array( $weekDay, $this->calendarDefinition['CALENDAR_WORK_DAYS_A'] )) {
                $sw_week_day = true;
            }
            if (! $sw_week_day) {
                $this->addCalendarLog( "Valid working Dates: " . $this->calendarDefinition['CALENDAR_WORK_DAYS_A'] );
                $this->addCalendarLog( "- Non working Day" );
            }

            foreach ($this->calendarDefinition['HOLIDAY'] as $key => $holiday) {
                //Normalize Holiday date to SAME year of date


                $holidayStartA = explode( " ", $holiday['CALENDAR_HOLIDAY_START'] );
                $holidayStartA = explode( "-", $holidayStartA[0] );

                $normalizedHolidayStart = date( "Y-m-d", mktime( $hour, $minute, $second, $holidayStartA[1], $holidayStartA[2], $year ) );
                $normalizedHolidayStartInt = mktime( $hour, $minute, $second, $holidayStartA[1], $holidayStartA[2], $year );

                $holidayEndA = explode( " ", $holiday['CALENDAR_HOLIDAY_END'] );
                $holidayEndA = explode( "-", $holidayEndA[0] );
                $normalizedHolidayEnd = date( "Y-m-d", mktime( $hour, $minute, $second, $holidayEndA[1], $holidayEndA[2], $year ) );
                $normalizedHolidayEndInt = mktime( $hour, $minute, $second, $holidayEndA[1], $holidayEndA[2], $year );
                $sw_not_holiday_aux = true;
                if ($dateInt >= $normalizedHolidayStartInt && $dateInt <= $normalizedHolidayEndInt) {
                    $sw_not_holiday = false;
                    $sw_not_holiday_aux = false;
                }
                if (! $sw_not_holiday_aux) {
                    $this->addCalendarLog( "It is a holiday -> " . $holiday['CALENDAR_HOLIDAY_NAME'] . " ($normalizedHolidayStart - $normalizedHolidayEnd)" );
                }
            }
            $sw_valid_date = $sw_week_day && $sw_not_holiday;

            if (! $sw_valid_date) { //Go to next day
                $date = date( "Y-m-d", mktime( $hour, $minute + 1, $second, $month, $day + 1, $year ) );
                $sw_date_changed = true;
            } else {
                $this->addCalendarLog( "FOUND VALID DATE -> $date" );

                //We got a valid day, now get the valid Business Hours
                //Here Need to find a rule to get the most nea
                $businessHoursA = array ();
                $prevHour = "00:00";

                if ($sw_date_changed) { // If date has changed then Use the first available period
                    $time = "00:01";
                }

                foreach ($this->calendarDefinition['BUSINESS_DAY'] as $keyBH => $businessHours) {

                    // First the period may correspond to ALL or to the current week day
                    if (($businessHours['CALENDAR_BUSINESS_DAY'] == 7) || ($businessHours['CALENDAR_BUSINESS_DAY'] == $weekDay)) {
                        $this->addCalendarLog( "Validating ($time/$prevHour) From: " . $businessHours['CALENDAR_BUSINESS_START'] . " to " . $businessHours['CALENDAR_BUSINESS_END'] );

                        //Prev Hour
                        $prevHourA = explode( ":", $prevHour );
                        $prevHourSeconds = ($prevHourA[0] * 60 * 60) + ($prevHour[1] * 60);

                        $calendarBusinessStartA = explode( ":", $businessHours['CALENDAR_BUSINESS_START'] );
                        $calendarBusinessStartSeconds = ($calendarBusinessStartA[0] * 60 * 60) + ($calendarBusinessStartA[1] * 60);

                        $calendarBusinessEndA = explode( ":", $businessHours['CALENDAR_BUSINESS_END'] );
                        $calendarBusinessEndSeconds = ($calendarBusinessEndA[0] * 60 * 60) + ($calendarBusinessEndA[1] * 60);

                        $timeAuxA = explode( ":", $time );
                        $timeAuxSeconds = ($timeAuxA[0] * 60 * 60) + ($timeAuxA[1] * 60);

                        if (($timeAuxSeconds >= $prevHourSeconds) && ($timeAuxSeconds < $calendarBusinessEndSeconds)) {
                            $this->addCalendarLog( "*** FOUND VALID BUSINESS HOUR " . $businessHours['CALENDAR_BUSINESS_START'] . " - " . $businessHours['CALENDAR_BUSINESS_END'] );

                            if ($timeAuxSeconds < $calendarBusinessStartSeconds) { //Out of range then assign first hour
                                $this->addCalendarLog( "Set to default start hour to: " . $businessHours['CALENDAR_BUSINESS_START'] );
                                $time = $businessHours['CALENDAR_BUSINESS_START'];
                            }
                            $prevHour = $businessHours['CALENDAR_BUSINESS_END'];
                            $businessHoursA = $businessHours;
                        }

                    }
                }
            }

            if (empty( $businessHoursA )) {
                $this->addCalendarLog( "> No Valid Business Hour found for current date, go to next" );
                $date = date( "Y-m-d", mktime( $hour, $minute + 1, $second, $month, $day + 1, $year ) );
                $sw_date_changed = true;
                $sw_valid_date = false;
            }

        } 

        $newDate = $date;
        $newDate = $this->getIniDate($newDate);

        $rangeWorkHour = $this->getRangeWorkHours($newDate, $this->calendarDefinition['BUSINESS_DAY']);

        $return['DATE'] = $newDate;
        $return['TIME'] = $time;
        $return['BUSINESS_HOURS'] = $rangeWorkHour;

        return $return;
    }

    public function getIniDate ($iniDate, $calendarData = array())
    {
    	$calendarData = (count($calendarData)) ? $calendarData : $this->calendarDefinition;
    	$this->calendarDefinition = $calendarData;
    	$flagIniDate = true;
    
    	while ($flagIniDate) {
    		// 1 if it's a work day
    		$weekDay = date('w',strtotime($iniDate));
    		if ( !(in_array($weekDay, $calendarData['CALENDAR_WORK_DAYS_A'])) ) {
    			$iniDate = date('Y-m-d'.' 00:00:00' , strtotime('+1 day', strtotime($iniDate)));
    			continue;
    		}
    
    		// 2 if it's a holiday
    		$iniDateHolidayDay = $this->is_holiday($iniDate);
    		if ($iniDateHolidayDay) {
    			$iniDate = date('Y-m-d'.' 00:00:00' , strtotime('+1 day', strtotime($iniDate)));
    			continue;
    		}
    
    		// 3 if it's work time
    		$workHours = $this->nextWorkHours($iniDate, $weekDay);
    		if ( !($workHours['STATUS']) ) {
    			$iniDate = date('Y-m-d'.' 00:00:00' , strtotime('+1 day', strtotime($iniDate)));
    			continue;
    		} else {
    			$iniDate = $workHours['DATE'];
    		}
    
    		$flagIniDate = false;
    	}
    
    	return $iniDate;
    }

    public function nextWorkHours ($date, $weekDay, $workHours = array())
    {
    	$workHours = (count($workHours)) ? $workHours : $this->calendarDefinition['BUSINESS_DAY'];

    	$auxIniDate = explode(' ', $date);
    	$timeDate = $auxIniDate['1'];
    	$timeDate = (float)str_replace(':', '', ((strlen($timeDate) == 8) ? $timeDate : $timeDate.':00') );
    	$nextWorkHours = array();
    
    	$workHoursDay = array();
    	$tempWorkHoursDay = array();
    
    	foreach ($workHours as $value) {
    		if ($value['CALENDAR_BUSINESS_DAY'] == $weekDay) {
    			$rangeWorkHour = array();
    			$timeStart = $value['CALENDAR_BUSINESS_START'];
    			$timeEnd   = $value['CALENDAR_BUSINESS_END'];
    			$rangeWorkHour['START'] = ((strlen($timeStart) == 8) ? $timeStart : $timeStart.':00');
    			$rangeWorkHour['END']   = ((strlen($timeEnd) == 8) ? $timeEnd : $timeEnd.':00');
    
    			$workHoursDay[] = $rangeWorkHour;
    		}
    
    		if ($value['CALENDAR_BUSINESS_DAY'] == '7') {
    			$rangeWorkHour = array();
    			$timeStart = $value['CALENDAR_BUSINESS_START'];
    			$timeEnd   = $value['CALENDAR_BUSINESS_END'];
    			$rangeWorkHour['START'] = ((strlen($timeStart) == 8) ? $timeStart : $timeStart.':00');
    			$rangeWorkHour['END']   = ((strlen($timeEnd) == 8) ? $timeEnd : $timeEnd.':00');
    
    			$tempWorkHoursDay[] = $rangeWorkHour;
    		}
    	}
    
    	if ( !(count($workHoursDay)) ) {
    		$workHoursDay = $tempWorkHoursDay;
    	}
    
    	$countHours = count($workHoursDay);
    	if ($countHours) {
    		for ($i = 1; $i < $countHours; $i++) {
    			for ($j = 0; $j < $countHours-$i; $j++) {
    				$dataft = (float)str_replace(':', '', $workHoursDay[$j]['START']);
    				$datasc = (float)str_replace(':', '', $workHoursDay[$j+1]['END']);
    				if ($dataft > $datasc) {
    					$aux = $workHoursDay[$j+1];
    					$workHoursDay[$j+1] = $workHoursDay[$j];
    					$workHoursDay[$j] = $aux;
    				}
    			}
    		}
    
    		foreach ($workHoursDay as $value) {
    			$iniTime = (float)str_replace(':', '', ((strlen($value['START']) == 8) ? $value['START'] : $value['START'].':00'));
    			$finTime = (float)str_replace(':', '', ((strlen($value['END']) == 8) ? $value['END'] : $value['END'].':00'));
    
    			if ( $timeDate <= $iniTime ) {
    				$nextWorkHours['STATUS'] = true;
    				$nextWorkHours['DATE']   = $auxIniDate['0'] . ' ' . ((strlen($value['START']) == 8) ? $value['START'] : $value['START'].':00');
    				return $nextWorkHours;
    			} elseif ( ($iniTime <= $timeDate)  && ($timeDate < $finTime) ) {
    				$nextWorkHours['STATUS'] = true;
    				$nextWorkHours['DATE']   = $date;
    				return $nextWorkHours;
    			}
    		}
    	}
    
    	$nextWorkHours['STATUS'] = false;
    	return $nextWorkHours;
    }

    public function is_holiday ($date, $holidays = array())
    {
    	$holidays = (count($holidays)) ? $holidays : $this->calendarDefinition['HOLIDAY'];
    
    	$auxIniDate = explode(' ', $date);
    	$iniDate = $auxIniDate['0'];
    	$iniDate = strtotime($iniDate);
    
    	foreach ($holidays as $value) {
    		$holidayStartDate = strtotime(date('Y-m-d',strtotime($value['CALENDAR_HOLIDAY_START'])));
    		$holidayEndDate   = strtotime(date('Y-m-d',strtotime($value['CALENDAR_HOLIDAY_END'])));
    
    		if ( ($holidayStartDate <= $iniDate) && ($iniDate <= $holidayEndDate) ) {
    			return true;
    		}
    	}
    	return false;
    }

}
?>