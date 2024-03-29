<?php
/**
 * Created by PhpStorm.
 * User: fredbradley
 * Date: 19/07/2018
 * Time: 16:04
 */

namespace FredBradley\SOCSICSParser;

use Carbon\Carbon;
use FredBradley\SOCSICSParser\Exceptions\FailedToLoadCalendar;
use FredBradley\SOCSICSParser\Traits\Cache;
use ICal\Event;
use ICal\ICal;

/**
 * Class CalendarEvents
 *
 * @package FredBradley\SOCS
 */
class CalendarEvents
{
    use Cache;
    /**
     * Constants for Time counting. Idea taken from Wordpress!
     */
    const MINUTE_IN_SECONDS = 60;

    const HOUR_IN_SECONDS = 60 * self::MINUTE_IN_SECONDS;

    const DAY_IN_SECONDS = 24 * self::HOUR_IN_SECONDS;

    const LARAVEL = 'laravel';

    const WORDPRESS = 'wordpress';
    /**
     * @var int
     */
    public int $minNumEvents = 5;

    /**
     * @var array
     */
    public array $events = [];
    /**
     * @var bool
     */
    public bool $ignoreCache = true;
    /**
     * @var array
     */
    public array $options = [
        'minNumEvents' => 5,
        'weeksAhead' => 15,
        'ignoreCache' => false,
        'cacheName' => 'calendar-cache',
    ];

    /**
     * @var string
     */
    protected string $icsUri='';

    /**
     * @var array
     */
    private array $unsetVars = [
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
    private int $weeksAhead = 15;

    /**
     * @var string
     */
    private string $siteType = '';

    /**
     * @var string
     */
    private string $cacheName = '';

    /**
     * @param  string  $icsUri
     * @param  array  $options
     *
     * @throws \FredBradley\SOCSICSParser\Exceptions\FailedToLoadCalendar
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
     * @param string $url
     * @return string (A url)
     */
    private function sanitizeWebcalUri(string $url): string
    {
        $uri = parse_url($url);
        if (in_array($uri[ 'scheme' ], ['http', 'https'])) {
            return $url;
        } else {
            $warning = sprintf("Your .ics file should be a 'http' or 'https' scheme. Not the %s which is what you have set. Please change this. We have changed it to 'http' for you.",
                $uri[ 'scheme' ]);
            error_log("Calendar Warning: " . $warning);
            $scheme = str_replace($uri[ 'scheme' ], "http", $uri[ 'scheme' ]);
            return $scheme . "://" . $uri[ 'host' ] . $uri[ 'path' ] . "?" . $uri[ 'query' ];
        }
    }

    /**
     * Tries to work out what type of system you're running on, to offer the best cacheing experience.
     *
     * @return void
     */
    private function sniffAndSetSiteType(): void
    {
        if (function_exists('add_action') && function_exists('set_transient') && defined('WPINC')) {
            $this->siteType = self::WORDPRESS;
        } elseif (class_exists('\Illuminate\Support\Facades\Cache')) {
            $this->siteType = self::LARAVEL;
        } else {
            $this->siteType = 'unknown';
        }
    }

    /**
     * This function loads the events into $this->events.
     * Depending on settings, it will try and load from the Cache first,
     * to save on page load speed.
     *
     * @return void
     * @throws \FredBradley\SOCSICSParser\Exceptions\FailedToLoadCalendar
     */
    public function loadEvents(): void
    {
        if ($this->fromCache() !== false && $this->ignoreCache === false) {
            $this->events = $this->fromCache();
        } else {
            $calendar = $this->loadCalendar();
            $this->events = $calendar->eventsFromRange(now(), now()->addWeeks($this->weeksAhead));

            $this->manipulateEventsObject();
            $this->saveCache($this->events);
        }
    }

    /**
     * Loads the Calendar into a PHP Object.
     *
     * If it fails, then depending on the site type,
     * it returns an error exception.
     *
     * @return ICal
     * @throws \FredBradley\SOCSICSParser\Exceptions\FailedToLoadCalendar
     */
    public function loadCalendar()
    {
        try {
            $iCal = new ICal($this->icsUri, [
                'defaultSpan' => 1,
                'defaultTimeZone' => 'UTC',
                'defaultWeekStart' => 'MO',
                'defaultCharacterReplacement' => false,
                'skipRecurrence' => false,
                'useTimeZoneWithRRules' => false,
            ]);
            return $iCal;
        } catch (\Exception $e) {
            throw new FailedToLoadCalendar($e->getMessage(), $e->getCode());
        }
    }

    /**
     * Takes the $this->events object and makes it a bit tidier.
     * Removes some variables in the object that we don't need.
     * Adds some variables which make reading the object easier.
     *
     * @return void
     */
    private function manipulateEventsObject(): void
    {
        foreach ($this->events as $event):
            $event->allDayEvent = $this->isAllDayEvent($event);
            $event->multiDayEvent = $this->isMoreThanOneday($event);
            $event->categories = $this->setCategoriesArray($event);
            $event->timeLabel = $this->timeLabel($event);
            $event->calendarName = $this->cacheName;
            $event->summary = html_entity_decode(html_entity_decode($event->summary));
            $event->location = html_entity_decode(html_entity_decode($event->location));

            foreach ($this->unsetVars as $var):
                unset($event->{$var});
            endforeach;
        endforeach;
    }

    /**
     * @param \ICal\Event $event
     *
     * @return bool
     */
    private function isAllDayEvent(Event $event): bool
    {
        if ($event->x_microsoft_cdo_alldayevent === "TRUE") {
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
    private function isMoreThanOneday(Event $event): bool
    {
        $start_ts = $event->dtstart_array[ 2 ];
        $end_ts = $event->dtend_array[ 2 ];

        if (($end_ts - $start_ts) > self::DAY_IN_SECONDS) {
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
    private function setCategoriesArray(Event $event): array
    {
        return explode(", ", $event->categories);
    }

    /**
     * @param \ICal\Event $event
     *
     * @return string
     */
    private function timeLabel(Event $event): string
    {
        $event_start_date = new \DateTime($event->dtstart_tz);
        $event_end_date = new \DateTime($event->dtend_tz);

        if ($this->isAllDayEvent($event)) {
            $time_label = "All Day";
            if (($event_start_date->format("Y-m-d") !== $event_end_date->format("Y-m-d")) && $this->isMoreThanOneDay($event)) {
                $time_label = "Multiday Event. Ends: " . $event_end_date->format("jS M");
            }
        } elseif ($event->dtstart === $event->dtend) {
            $time_label = $event_start_date->format("g:ia");
        } else {
            $time_label = $event_start_date->format("g:ia") . " - " . $event_end_date->format("g:ia");
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
    private function saveCache(array $input): bool
    {
        if ($this->siteType === 'wordpress') {
            delete_transient($this->cacheName);

            return set_transient($this->cacheName, $input, 6 * self::HOUR_IN_SECONDS);
        }

        if ($this->siteType === 'laravel') {
            return \Illuminate\Support\Facades\Cache::put($this->cacheName, $input, 60);
        }

        return false;
    }

}

