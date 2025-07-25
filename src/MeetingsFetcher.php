<?php
namespace AAMeetings;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class MeetingsFetcher
{
    private $client;
    private $corsProxy = 'https://cors-proxy-xi-ten.vercel.app/api/proxy';
    private $processStats = [];
    
    public function __construct()
    {
        $this->client = new Client(['timeout' => 30]);
    }
    
    /**
     * Detect TSML data source from Seattle AA website
     * CRITICAL: This is the ONLY way we get meeting data - no fallbacks
     */
    public function detectTSMLDataSource(): string
    {
        $websiteUrl = 'https://www.seattleaa.org';
        $proxiedUrl = $this->corsProxy . '?url=' . urlencode($websiteUrl);
        
        try {
            echo "Detecting TSML data source from Seattle AA website...\n";
            $response = $this->client->request('GET', $proxiedUrl);
            $html = (string) $response->getBody();
            
            // Save HTML for debugging
            file_put_contents(__DIR__ . '/../debug_seattle_aa.html', $html);
            echo "Saved HTML for debugging to debug_seattle_aa.html\n";
            
            // Try multiple patterns to detect TSML data source
            $patterns = [
                // Original pattern from requirements
                '/<div id="tsml-ui"\s+data-src="([^"]+\.json)[0-9?]*"/',
                // Alternative patterns
                '/data-src=["\']([^"\']+\.json)[^"\']*["\']/',
                '/tsml.*?data-src=["\']([^"\']+\.json)[^"\']*["\']/',
                '/wp-content\/[^"\']*tsml[^"\']*\.json/',
                // Look for any JSON file in wp-content
                '/wp-content\/[^"\']*\.json/',
                // Look for TSML in JavaScript
                '/var\s+tsml.*?["\']([^"\']+\.json)["\']/',
            ];
            
            foreach ($patterns as $i => $pattern) {
                echo "Trying pattern " . ($i + 1) . ": $pattern\n";
                if (preg_match($pattern, $html, $matches)) {
                    $detectedUrl = $matches[1];
                    echo "✓ TSML data source detected with pattern " . ($i + 1) . ": $detectedUrl\n";
                    return $detectedUrl;
                }
            }
            
            // Try to find known TSML cache file
            echo "Trying known TSML cache URL...\n";
            $knownCacheUrl = 'https://www.seattleaa.org/wp-content/tsml-cache-cbdb25180b.json';
            $testResponse = $this->client->request('GET', $this->corsProxy . '?url=' . urlencode($knownCacheUrl));
            if ($testResponse->getStatusCode() === 200) {
                $testData = json_decode($testResponse->getBody(), true);
                if (is_array($testData) && !empty($testData)) {
                    echo "✓ Found working TSML cache URL: $knownCacheUrl\n";
                    return $knownCacheUrl;
                }
            }
            
            throw new \Exception("CRITICAL ERROR: Could not detect TSML data source from Seattle AA website. Tried multiple patterns but none matched. Website structure may have changed. Check debug_seattle_aa.html for the actual HTML content.");
            
        } catch (GuzzleException $e) {
            throw new \Exception("CRITICAL ERROR: Failed to fetch Seattle AA website to detect TSML source: " . $e->getMessage());
        }
    }
    
    /**
     * Fetch meetings from the detected TSML source
     * CRITICAL: No fallback data sources allowed
     */
    public function fetchMeetingsData(): array
    {
        $tsmlUrl = $this->detectTSMLDataSource();
        $proxiedUrl = $this->corsProxy . '?url=' . urlencode($tsmlUrl);
        
        try {
            echo "Fetching meetings data from TSML source...\n";
            $response = $this->client->request('GET', $proxiedUrl);
            $data = json_decode($response->getBody(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('CRITICAL ERROR: Failed to parse JSON from TSML source: ' . json_last_error_msg());
            }
            
            if (!is_array($data) || empty($data)) {
                throw new \Exception('CRITICAL ERROR: TSML source returned empty or invalid data');
            }
            
            echo "✓ Successfully fetched " . count($data) . " meetings from live TSML source\n";
            return $data;
            
        } catch (GuzzleException $e) {
            throw new \Exception('CRITICAL ERROR: Failed to fetch meetings from TSML source: ' . $e->getMessage());
        }
    }
    
    /**
     * Extract city from address field or region
     */
    private function extractCity(string $address, string $region = ''): string
    {
        if (!empty($address)) {
            // Split by comma and get the part before state
            $parts = array_map('trim', explode(',', $address));
            
            if (count($parts) >= 2) {
                // Get the second-to-last part (city is usually before state)
                $cityPart = $parts[count($parts) - 2];
                
                // Clean up common variations
                $cityPart = trim(str_replace(['WA', 'Washington'], '', $cityPart));
                
                if (!empty($cityPart) && $cityPart !== 'USA') {
                    return $cityPart;
                }
            }
        }
        
        // Fall back to region if address doesn't have city
        if (!empty($region)) {
            return $region;
        }
        
        return 'Unknown Location';
    }
    
    /**
     * Check if meeting should be included (has either address or conference URL)
     */
    private function shouldIncludeMeeting(array $meeting): bool
    {
        $hasAddress = !empty($meeting['formatted_address']);
        $hasConferenceUrl = !empty($meeting['conference_url']);
        
        return $hasAddress || $hasConferenceUrl;
    }
    
    /**
     * Get attendance option with fallback logic
     */
    private function getAttendanceOption(array $meeting): string
    {
        $option = $meeting['attendance_option'] ?? '';
        
        // If no attendance_option set, try to infer from other fields
        if (empty($option)) {
            $hasAddress = !empty($meeting['formatted_address']);
            $hasConferenceUrl = !empty($meeting['conference_url']);
            
            if ($hasAddress && $hasConferenceUrl) {
                return 'hybrid';
            } elseif ($hasConferenceUrl) {
                return 'online';
            } elseif ($hasAddress) {
                return 'in_person';
            }
        }
        
        return $option;
    }
    
    /**
     * Get all unique field names across all meetings
     */
    public function getAllFieldNames(array $meetings): array
    {
        $allFields = [];
        
        foreach ($meetings as $meeting) {
            if (is_array($meeting)) {
                $allFields = array_merge($allFields, array_keys($meeting));
            }
        }
        
        return array_unique($allFields);
    }
    
    /**
     * Organize meetings by date with attendance categorization
     */
    public function organizeMeetingsByDate(array $meetings): array
    {
        $byDate = [];
        $now = new \DateTime();
        $stats = [
            'total_processed' => 0,
            'skipped_incomplete' => 0,
            'online_count' => 0,
            'in_person_count' => 0,
            'hybrid_count' => 0,
            'cities_found' => []
        ];
        
        // Generate dates for current week and next 4 weeks
        for ($i = 0; $i < 35; $i++) {
            $date = clone $now;
            $date->modify("+$i days");
            $dateKey = $date->format('Y-m-d');
            $dayOfWeek = (int)$date->format('w'); // 0 = Sunday, 6 = Saturday
            
            $byDate[$dateKey] = [
                'date' => $dateKey,
                'day_name' => $date->format('l'),
                'formatted_date' => $date->format('F j, Y'),
                'day_of_week' => $dayOfWeek,
                'online' => [],
                'in_person' => [],
                'hybrid' => []
            ];
        }
        
        // Process each meeting
        foreach ($meetings as $meeting) {
            $stats['total_processed']++;
            
            // Skip meetings without address AND conference URL
            if (!$this->shouldIncludeMeeting($meeting)) {
                $stats['skipped_incomplete']++;
                continue;
            }
            
            $meetingDay = isset($meeting['day']) ? (int)$meeting['day'] : null;
            if ($meetingDay === null) continue;
            
            $attendanceOption = $this->getAttendanceOption($meeting);
            $city = $this->extractCity(
                $meeting['formatted_address'] ?? '', 
                $meeting['region'] ?? ''
            );
            
            // Track cities
            if (!in_array($city, $stats['cities_found'])) {
                $stats['cities_found'][] = $city;
            }
            
            // Add city and clean up meeting data
            $meeting['city'] = $city;
            $meeting['attendance_category'] = $attendanceOption;
            
            // Count by category
            switch ($attendanceOption) {
                case 'online':
                    $stats['online_count']++;
                    break;
                case 'in_person':
                    $stats['in_person_count']++;
                    break;
                case 'hybrid':
                    $stats['hybrid_count']++;
                    break;
            }
            
            // Assign to appropriate dates
            foreach ($byDate as $dateKey => &$dateInfo) {
                if ($dateInfo['day_of_week'] === $meetingDay) {
                    switch ($attendanceOption) {
                        case 'online':
                            $dateInfo['online'][] = $meeting;
                            break;
                        case 'in_person':
                            $dateInfo['in_person'][] = $meeting;
                            break;
                        case 'hybrid':
                            $dateInfo['hybrid'][] = $meeting;
                            break;
                    }
                }
            }
        }
        
        // Sort meetings within each category
        foreach ($byDate as &$dateInfo) {
            // Sort online meetings by time
            usort($dateInfo['online'], function($a, $b) {
                return strcmp($a['time'] ?? '', $b['time'] ?? '');
            });
            
            // Group and sort in-person and hybrid meetings by city, then time
            foreach (['in_person', 'hybrid'] as $category) {
                $dateInfo[$category] = $this->groupMeetingsByCity($dateInfo[$category]);
            }
        }
        
        // Store stats for reporting
        $this->processStats = $stats;
        
        return $byDate;
    }
    
    /**
     * Group meetings by city and sort
     */
    private function groupMeetingsByCity(array $meetings): array
    {
        $byCity = [];
        
        foreach ($meetings as $meeting) {
            $city = $meeting['city'];
            if (!isset($byCity[$city])) {
                $byCity[$city] = [];
            }
            $byCity[$city][] = $meeting;
        }
        
        // Sort cities alphabetically
        ksort($byCity);
        
        // Sort meetings within each city by time
        foreach ($byCity as &$cityMeetings) {
            usort($cityMeetings, function($a, $b) {
                return strcmp($a['time'] ?? '', $b['time'] ?? '');
            });
        }
        
        return $byCity;
    }
    
    /**
     * Get processing statistics
     */
    public function getProcessStats(): array
    {
        return $this->processStats ?? [];
    }
    
    /**
     * Organize meetings by city (for location pages)
     */
    public function organizeMeetingsByCity(array $meetings): array
    {
        $byCity = [];
        
        foreach ($meetings as $meeting) {
            $city = $this->extractCity($meeting['address'] ?? $meeting['formatted_address'] ?? '');
            
            if (!isset($byCity[$city])) {
                $byCity[$city] = [];
            }
            
            $dayOfWeek = isset($meeting['day']) ? (int)$meeting['day'] : 7;
            if (!isset($byCity[$city][$dayOfWeek])) {
                $byCity[$city][$dayOfWeek] = [];
            }
            
            $byCity[$city][$dayOfWeek][] = $meeting;
        }
        
        // Sort by day of week and then by venue within each day
        foreach ($byCity as $city => &$days) {
            ksort($days); // Sort by day of week
            
            foreach ($days as &$dayMeetings) {
                usort($dayMeetings, function($a, $b) {
                    $locationA = $a['location'] ?? $a['venue'] ?? '';
                    $locationB = $b['location'] ?? $b['venue'] ?? '';
                    return strcmp($locationA, $locationB);
                });
            }
        }
        
        // Sort cities alphabetically
        ksort($byCity);
        
        return $byCity;
    }
}