# SOCS .ics Calendar Parser
A PHP Script that parses an ICS file from SOCS, in a readable format to display elsewhere.

## Features
 * Super easy to use.
 * Once you've included it in your composer package, just one line of code will get you a PHP array of Event Objects.
 * Compatible with Wordpress transients, if you use Wordpress.
 * Compatible with Laravel Caching, if you use Laravel.
 * Actively managed - if you have a feature or idea request, please let me know, or even better - submit a [pull request](https://github.com/fredbradley/socs-calendar-ics-parser/pulls).

## Requirements
This project requires PHP 7. I'm too lazy to write for PHP5.6 these days. Get with the program! 

## How to install
```
composer install fredbradley/socs-calendar-ics-parser
```
## Examples
### Wordpress Widget Example
This is written to work with Wordpress and even has Wordpress Caching (`set_transient`) functionality built in, to speed up page load times. 

If you want to see an example of a widget that uses this code, [please see this piece of code](https://github.com/cranleighschool/cranleigh-socs/blob/master/src/Widgets/UpcomingCalendarEventsWidget.php) in my 'cranleigh-socs' plugin. If you like you can install the entire [Cranleigh SOCS Plugin](https://github.com/cranleighschool/cranleigh-socs), but be aware it's written in Bootstrap 3 with HTML markup specifically for our sites. 

![Wordpress Example](https://user-images.githubusercontent.com/1639226/43061908-eca937b6-8e4e-11e8-8bf7-bd2ea0b2b6b5.png)

If you want some help customising it, I'd be happy to help. 


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
