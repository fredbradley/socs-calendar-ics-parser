# SOCS .ics Calenar Parser
A PHP Script that parses an ICS file from SOCS, in a readable format to display elsewhere.

## Features
 * Piss easy to use.
 * Once you've included it in your composer package, just one line of code will get you a PHP array of Event Objects.
 * Compatibile with Wordpress transients, if you use Wordpress.
 * Compatible with Laravel Caching, if you use Laravel
 * Actively managed - if you have a feature or idea request, please let me know, or even better - submit a pull request.

## Requirements
This project requires PHP 7. I'm too lazy to write for PHP5.6 these days. Get with the program! 
## How to install
```
composer install fredbradley/socs-calendar-ics-parser
```

## Usage

### Options
  * `minNumEvents` (default: 5)
  * `ignoreCache` (default: false) Set to `true` if you don't want get the object from the cache. 
  * `cacheName` (default: 'calendar-cache') if you have more than one instance of this, you should probably give each instance a unique cache name, so they don't overwrite each other
  * `weeksAhead` (default: 15) how many weeks ahead to you want to get the events for
  
  ### Basic Example with Default Options
  ```
  $ics_url = "<YOUR URL FOR YOUR ICS HERE>";
  $calendar = new \FredBradley\SOCSICSParser\CalendarEvents( $ics_url );
  ```
  ### Example with custom options
  ```
  $ics_url = "<YOUR URL FOR YOUR ICS HERE>";
  $options = [
    'minNumEvents' => 10,
    'cacheName' => 'my-music-calendar'
  ];
  $calendar = new \FredBradley\SOCSICSParser\CalendarEvents( $ics_url, $options );
  ```
