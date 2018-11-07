<?php

namespace App\Console\Components\Calendar;

use DateTime;
use Carbon\Carbon;
use App\CalendarList;
use App\Events\Calendar\Event as Event;
use App\Events\Calendar\EventsFetched;
use Google_Service_Calendar_CalendarList;
use Illuminate\Console\Command;
use Spatie\GoogleCalendar\Event as GoogleCalendarEvent;
use Spatie\GoogleCalendar\GoogleCalendarFactory;


class FetchCalendarEvents extends Command
{
    protected $signature = 'dashboard:fetch-calendar-events';

    protected $description = 'Fetch events from a Google Calendar';

    public function handle()
    {
        $calendarId = $calendarId ?? config('google-calendar.calendar_id');
        $calendarService = GoogleCalendarFactory::createForCalendarId($calendarId)->getService();
        $now = Carbon::now();
        $startDate = Carbon::parse('today');
        $endDate = $now->endOfWeek();

        $queryParameters = [
            'maxResults' => 10,
            'singleEvents' => 'true',
        ];

        foreach ($calendarService->calendarList->listCalendarList(['minAccessRole' => 'owner']) as $calendarListItem) {
            $calendarId = $calendarListItem->getId();

            $allEvents = collect(Event::get($startDate, $endDate, $queryParameters, $calendarId))
                ->map(function (GoogleCalendarEvent $event) {
                    $sortDate = $event->getSortDate();

                    $date =  $event->start->date
                        ? Carbon::createFromFormat('Y-m-d', $event->start->date)
                        : Carbon::parse($event->start->dateTime);

                    $attendees = collect($event->attendees)->map(function($element) {
                        $username = explode('@', $element->email);

                        return [
                            'name' => ucwords((implode(' ', explode('.', $username[0]))))
                        ];
                    });

                    return [
                        'attendees' => $attendees,
                        'description' => $event->description,
                        'date' => $date->format('Y-m-d'),
                        'name' => $event->name,
                    ];
                })
                ->unique('name')
                ->toArray();

            $events[$calendarListItem->getSummary()] = [
                'calendarName' => $calendarListItem->getSummary(),
                'events' => $allEvents,
            ];
        }

        event(new EventsFetched($events));
    }
}
