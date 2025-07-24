<?php
namespace AAMeetings;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class MeetingsFetcher
{
    private $client;
    private $corsProxy = 'https://cors-proxy-xi-ten.vercel.app/api/proxy';
    
    public function __construct()
    {
        $this->client = new Client(['timeout' => 30]);
    }
    
    public function fetchSeattleMeetings(): array
    {
        $targetUrl = 'https://www.seattleaa.org/wp-content/tsml-cache-cbdb25180b.json';
        $url = $this->corsProxy . '?url=' . urlencode($targetUrl);
        
        try {
            $response = $this->client->request('GET', $url);
            $data = json_decode($response->getBody(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Failed to parse JSON: ' . json_last_error_msg());
            }
            
            return $data;
        } catch (GuzzleException $e) {
            throw new \Exception('Failed to fetch meetings: ' . $e->getMessage());
        }
    }
    
    public function processedMeetings(): array
    {
        $meetings = $this->fetchSeattleMeetings();
        $processed = [];
        
        foreach ($meetings as $meeting) {
            $processed[] = [
                'name' => $meeting['name'] ?? 'Unnamed Meeting',
                'address' => $meeting['formatted_address'] ?? 'Address not available',
                'day' => $meeting['day'] ?? null,
                'time' => $meeting['time'] ?? null,
                'time_formatted' => $meeting['time_formatted'] ?? null,
                'location' => $meeting['location'] ?? null,
                'region' => $meeting['region'] ?? null
            ];
        }
        
        // Sort by day and time
        usort($processed, function($a, $b) {
            if ($a['day'] == $b['day']) {
                return strcmp($a['time'] ?? '', $b['time'] ?? '');
            }
            return ($a['day'] ?? 7) - ($b['day'] ?? 7);
        });
        
        return $processed;
    }
    
    /**
     * Get meetings organized by calendar day for the current month
     */
    public function getMeetingsByCalendarDay(): array
    {
        $meetings = $this->processedMeetings();
        $now = new \DateTime();
        $currentMonth = (int)$now->format('n');
        $currentYear = (int)$now->format('Y');
        $daysInMonth = (int)date('t', mktime(0, 0, 0, $currentMonth, 1, $currentYear));
        
        // Initialize array for each day of the month
        $meetingsByDay = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $meetingsByDay[$day] = [];
        }
        
        // Group meetings by calendar day
        foreach ($meetings as $meeting) {
            if ($meeting['day'] !== null) {
                // Map day of week (0=Sunday, 6=Saturday) to calendar days in current month
                $dayOfWeek = (int)$meeting['day'];
                
                // Find all dates in the current month that match this day of week
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $date = mktime(0, 0, 0, $currentMonth, $day, $currentYear);
                    if ((int)date('w', $date) === $dayOfWeek) {
                        $meetingsByDay[$day][] = $meeting;
                    }
                }
            }
        }
        
        // Sort meetings within each day by time and location
        foreach ($meetingsByDay as $day => &$dayMeetings) {
            usort($dayMeetings, function($a, $b) {
                // First sort by location/region
                $locationCmp = strcmp($a['location'] ?? $a['region'] ?? '', $b['location'] ?? $b['region'] ?? '');
                if ($locationCmp !== 0) {
                    return $locationCmp;
                }
                // Then by time
                return strcmp($a['time'] ?? '', $b['time'] ?? '');
            });
        }
        
        return $meetingsByDay;
    }
    
    /**
     * Get calendar information for the current month
     */
    public function getCalendarInfo(): array
    {
        $now = new \DateTime();
        $currentMonth = (int)$now->format('n');
        $currentYear = (int)$now->format('Y');
        
        return [
            'month' => $now->format('F'),
            'year' => $currentYear,
            'month_number' => $currentMonth,
            'days_in_month' => (int)date('t', mktime(0, 0, 0, $currentMonth, 1, $currentYear)),
            'first_day_of_week' => (int)date('w', mktime(0, 0, 0, $currentMonth, 1, $currentYear))
        ];
    }
}