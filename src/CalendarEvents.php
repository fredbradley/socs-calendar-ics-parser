<?php
/**
 * Created by PhpStorm.
 * User: fredbradley
 * Date: 19/07/2018
 * Time: 16:04
 */

namespace FredBradley\SOCSICSParser;

use ICal\ICal;
use ICal\Event;

/**
 * Class CalendarEvents
 *
 * @package FredBradley\SOCS
 */
class CalendarEvents {

    /**
     * Constants for Time counting. Idea taken from Wordpress!
     */
    const MINUTE_IN_SECONDS = 60;

    const HOUR_IN_SECONDS = 60 * self::MINUTE_IN_SECONDS;

    const DAY_IN_SECONDS = 24 * self::HOUR_IN_SECONDS;

    /**
     * @var int
     */
    public $minNumEvents = 5;

    /**
     * @var array
     */
    public $events = [];
    /**
     * @var bool
     */
    public $ignoreCache = true;
    /**
     * @var array
     */
    public $options = [
        'minNumEvents' => 5,
        'weeksAhead' => 15,
        'ignoreCache' => false,
        'cacheName' => 'calendar-cache',
    ];

    /**
     * @var string
     */
    protected $icsUri;

    /**
     * @var array
     */
    private $unsetVars = [
        'x_microsoft_cdo_alldayevent_array',
        'x_microsoft_cdo_alldayevent',
        'categories_array',
        'summary_array',
        'class_array',
        'location_array',
        'uid_array',
        'uid',
        'attendee',
        'organizer',
        'status',
        'lastmodified',
        'created',
        'transp',
        'sequence',
        'duration',
        'class',
        'dtstamp_array',
        'dtend_array',
        'dtstart_array',
    ];

    /**
     * @var int
     */
    private $weeksAhead = 15;

    /**
     * @var string
     */
    private $siteType = '';

    /**
     * @var string
     */
    private $cacheName = '';

    /**
     * CalendarEvents constructor.
     *
     * @param string $icsUri
     * @param array $options
     *
     * @throws \Exception
     */
    public function __construct(string $icsUri, array $options = [])
    {

        $this->icsUri = $this->sanitizeWebcalUri($icsUri);
        $this->options = array_merge($this->options, $options);
        foreach ($this->options as $key => $value) {
            $this->{$key} = $value;
        }

        $this->sniffAndSetSiteType();

        $this->loadEvents();
    }

    /**
     * Sanitizer. Our icsUri is expected to be a http or https request.
     *
     * @param $url
     * @return string (A url)
     */
    private function sanitizeWebcalUri($url)
    {
        $uri = parse_url($url);
        if (in_array($uri['scheme'], ['http', 'https'])) {
            return $url;
        } else {
            $warning = sprintf("Your .ics file should be a 'http' or 'https' scheme. Not the %s which is what you have set. Please change this. We have changed it to 'http' for you.",
                $uri['scheme']);
            error_log("Calendar Warning: ".$warning);
            $scheme = str_replace($uri['scheme'], "http", $uri['scheme']);
            return $scheme."://".$uri['host'].$uri['path']."?".$uri['query'];
        }

    }

    /**
     * Tries to work out what type of system you're running on, to offer the best cacheing experience.
     * @return void
     */
    private function sniffAndSetSiteType() {

        if ( function_exists( 'add_action' ) && function_exists( 'set_transient' ) ) {
            $this->siteType = 'wordpress';
        } elseif ( class_exists( '\Illuminate\Support\Facades\Cache' ) ) {
            $this->siteType = 'laravel';
        } else {
            $this->siteType = 'unknown';
        }
    }

    /**
     * This function loads the events into $this->events.
     * Depending on settings, it will try and load from the Cache first,
     * to save on page load speed.
     *
     * @throws \Exception
     * @return void
     */
    public function loadEvents() {

        if ( $this->fromCache() !== false && $this->ignoreCache === false ) {
            $this->events = $this->fromCache();
        } else {
            $this->events = $this->loadCalendar()->eventsFromRange( date( "Y-m-d 00:00:00" ),
                date( "Y-m-d 00:00:00", strtotime( "+" . $this->weeksAhead . " weeks" ) ) );
            $this->manipulateEventsObject();
            $this->saveCache( $this->events );
        }

    }

    /**
     * Returns the $events array from the Cache.
     * Dependant on $siteType;
     *
     * @return bool|mixed
     */
    private function fromCache() {

        if ( $this->siteType === 'wordpress' ) {

            return get_transient( $this->cacheName );

        }

        if ( $this->siteType === 'laravel') {


            $output = \Illuminate\Support\Facades\Cache::get($this->cacheName);

            if ($output!==null) {
                return $output;
            }

        }

        return false;
    }

    /**
     * Loads the Calendar into a PHP Object.
     *
     * If it fails, then depending on the site type,
     * it returns an error exception.
     *
     * @return \ICal\ICal|string|\WP_Error
     */
    public function loadCalendar() {

        try {
            $iCal = new ICal( $this->icsUri, [
                'defaultSpan'                 => 1,
                'defaultTimeZone'             => 'UTC',
                'defaultWeekStart'            => 'MO',
                'defaultCharacterReplacement' => false,
                'skipRecurrence'              => false,
                'useTimeZoneWithRRules'       => false
            ] );

            return $iCal;

        } catch ( \Exception $e ) {
            if ( $this->siteType === 'wordpress' ) {
                return new \WP_Error( "400", "Could not retrieve calendar." );
            } else {
                return "ERROR, could not load calendar.";
            }
        }
    }

    /**
     * Takes the $this->events object and makes it a bit tidier.
     * Removes some variables in the object that we don't need.
     * Adds some variables which make reading the object easier.
     *
     * @return void
     */
    private function manipulateEventsObject() {

        foreach ( $this->events as $event ):
            $event->allDayEvent   = $this->isAllDayEvent( $event );
            $event->multiDayEvent = $this->isMoreThanOneday( $event );
            $event->categories    = $this->setCategoriesArray( $event );
            $event->timeLabel     = $this->timeLabel( $event );

            foreach ( $this->unsetVars as $var ):
                unset( $event->{$var} );
            endforeach;
        endforeach;
    }

    /**
     * @param \ICal\Event $event
     *
     * @return bool
     */
    private function isAllDayEvent( Event $event ) {

        if ( $event->x_microsoft_cdo_alldayevent === "TRUE" ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param \ICal\Event $event
     *
     * @return bool
     */
    private function isMoreThanOneday( Event $event ) {

        $start_ts = $event->dtstart_array[ 2 ];
        $end_ts   = $event->dtend_array[ 2 ];

        if ( ( $end_ts - $start_ts ) > self::DAY_IN_SECONDS ) {
            return true;
        } else {
            return false;
        }

    }

    /**
     * @param \ICal\Event $event
     *
     * @return array
     */
    private function setCategoriesArray( Event $event ) {

        $categories = explode( ", ", $event->categories );

        return $categories;
    }

    /**
     * @param \ICal\Event $event
     *
     * @return string
     */
    private function timeLabel( Event $event ) {

        $event_start_date = new \DateTime( $event->dtstart_tz );
        $event_end_date   = new \DateTime( $event->dtend_tz );

        if ( $this->isAllDayEvent( $event ) ) {
            $time_label = "All Day";
            if ( ( $event_start_date->format( "Y-m-d" ) !== $event_end_date->format( "Y-m-d" ) ) && $this->isMoreThanOneDay( $event ) ) {
                $time_label = "Multiday Event. Ends: " . $event_end_date->format( "jS M" );
            }
        } elseif ( $event->dtstart === $event->dtend ) {
            $time_label = $event_start_date->format( "G:ia" );
        } else {
            $time_label = $event_start_date->format( "G:ia" ) . " - " . $event_end_date->format( "G:ia" );
        }

        return '<i class="fa fa-fw fa-clock-o"></i>' . $time_label;
    }

    /**
     * Depending on $siteType, this will save the input into the appropriate cache.
     *
     * @param array $input
     *
     * @return bool
     */
    private function saveCache( array $input ) {

        if ( $this->siteType === 'wordpress' ) {
            delete_transient( $this->cacheName );

            return set_transient( $this->cacheName, $input, 6 * self::HOUR_IN_SECONDS );
        }

        if ( $this->siteType === 'laravel' ) {
            return \Illuminate\Support\Facades\Cache::put($this->cacheName, $input, 6 * self::HOUR_IN_SECONDS );
        }

        return false;
    }

}

