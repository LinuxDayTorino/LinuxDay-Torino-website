<?php
# Linux Day 2016 - Homepage
# Copyright (C) 2016 Valerio Bozzolan
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Affero General Public License for more details.
#
# You should have received a copy of the GNU Affero General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.

class Talk {

    // queryTalks() sets these variables
    private $title;
    public $track;
    private $start;
    private $end;
    private $event_id;
    private $talkers;
    public $hour;

    // These are used both in database queries (tracks.name) and displayed to the user!
    static $AREAS = ['Base', 'Dev', 'Sysadmin', 'Misc'];
    const HOURS = 4;

    function __construct() {
        self::prepareTalk($this);
    }

    static function prepareTalk(& $t) {
        if( isset( $t->talk_ID ) ) {
            $t->talk_ID   = (int) $t->talk_ID;
        }
        if( isset( $t->talk_hour ) ) {
            $t->talk_hour = (int) $t->talk_hour;
        }
    }

    /**
     * The human-readable name of the talk type (a.k.a. track), can be called statically.
     *
     * @param string|null $t track or null to use $this->track
     * @return string
     * @see Talk::$AREAS
     */
    function getTalkType($t = null) {
        if($t === null) {
            isset($this->talk)
            || error_die("Missing talk type");

            // Yay for recursion!
            return self::getTalkType($this->track);
        }

        return sprintf(
            _("Area %s"),
            $t
        );
    }

    /**
     * The human-readable talk hour, can be called statically.
     *
     * @param int|null $h hour or null to use $this->hour
     * @return string
     */
    function getTalkHour($h = null) {
        if( $h === null ) {
            isset( $this->hour )
            || error_die("Missing talk hour");

            return self::getTalkHour( $this->hour );
        }

        return sprintf( _("%d° ora"), $h );
    }

    function getTalkTitle() {
        return $this->title;
    }

    static function getWhereTracks() {
        $where = '';
        $count_minus_one = count(self::$AREAS)-1;
        for($i = 0; $i <= $count_minus_one; $i++) {
            $where .= ' tracks.name=\''.self::$AREAS[$i].'\'';
            if($i !== $count_minus_one) {
                $where .= ' OR';
            }
        }

        return $where;
    }

	static function queryTalks() {
	    $where = self::getWhereTracks();

		$talks = query_results(
			"SELECT ".
				"`events`.title, ".
				"tracks.name AS track, ".
				"`events`.`start`, ".
				"`events`.`end`, ".
				"`events`.`id` AS event_id ".
			" FROM `events`".
			" JOIN tracks ".
				"ON tracks.id=`events`.track ".
            " WHERE (".$where.") AND `events`.conference_id=".CONFERENCE_ID.
			" ORDER BY ".
				"`events`.`start`, tracks.name",
			'Talk'
		);

        $talkers = query_results(
            "SELECT ".
            "people.name, ".
            "events_people.event_id AS event ".
            " FROM events_people".
            " JOIN people ".
            "ON events_people.person_id=people.id ".
            " JOIN `events` ".
            "ON events_people.event_id=`events`.id ".
            " JOIN tracks ".
            "ON events.track=tracks.id ".
            " WHERE (".$where.") AND `events`.conference_id=".CONFERENCE_ID.
            " ORDER BY ".
            "events_people.event_id",
            NULL
        );

        $talkers_by_event_id = [];
        foreach($talkers as $talker) {
            $talkers_by_event_id[$talker->event][] = $talker->name;
        }
        // To avoid involuntary chaos and destruction...
        unset($talkers);
        unset($talker);

        // try to guess hours (and add talkers while we're at it)
        $start_times = [];
        foreach($talks as $talk) {
            $start_times[] = $talk->start;

            if(isset($talkers_by_event_id[$talk->event_id])) {
                $talk->talkers = $talkers_by_event_id[$talk->event_id];
            } else {
                $talk->talkers = [];
            }
        }
        $start_to_hour_counter = array_flip(array_values(array_unique($start_times)));

        foreach($talks as $talk) {
            $talk->hour = $start_to_hour_counter[$talk->start];
        }

        return $talks;
	}

    /**
     * Get talkers as an array.
     * It's always an array, it's never set to anything else or left uninitialized, but here is casted to array, too.
     *
     * @return array talkers.
     */
    public function getTalkers() {
        return (array) $this->talkers;
    }
}
