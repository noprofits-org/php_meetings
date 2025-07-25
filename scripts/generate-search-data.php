<?php
require_once __DIR__ . '/../vendor/autoload.php';

use AAMeetings\MeetingsFetcher;

class SearchDataGenerator 
{
    private $fetcher;
    private $outputDir;
    
    public function __construct() 
    {
        $this->fetcher = new MeetingsFetcher();
        $this->outputDir = __DIR__ . '/../data';
        
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
    }
    
    public function generateSearchData(): void 
    {
        echo "Generating search-ready data files...\n";
        
        try {
            // Fetch meetings data
            $meetings = $this->fetcher->fetchMeetingsData();
            $organizedMeetings = $this->fetcher->organizeMeetingsByDate($meetings);
            $processStats = $this->fetcher->getProcessStats();
            
            // Generate search index
            $searchIndex = $this->createSearchIndex($meetings);
            
            // Generate optimized meeting data
            $optimizedData = $this->createOptimizedData($organizedMeetings, $processStats);
            
            // Save files
            $this->saveSearchIndex($searchIndex);
            $this->saveOptimizedData($optimizedData);
            $this->saveBackupData($meetings);
            
            echo "✓ Search data files generated successfully\n";
            echo "  - Search index: " . count($searchIndex) . " entries\n";
            echo "  - Optimized data: " . count($optimizedData['meetings_by_date']) . " days\n";
            
        } catch (Exception $e) {
            echo "ERROR: Failed to generate search data: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
    
    private function createSearchIndex(array $meetings): array 
    {
        $index = [];
        
        foreach ($meetings as $meeting) {
            if (!$this->shouldIncludeMeeting($meeting)) {
                continue;
            }
            
            $searchEntry = [
                'id' => $meeting['slug'] ?? uniqid(),
                'name' => $meeting['name'] ?? '',
                'day' => (int)($meeting['day'] ?? 0),
                'time' => $meeting['time'] ?? '',
                'time_formatted' => $meeting['time_formatted'] ?? '',
                'city' => $this->extractCity($meeting['formatted_address'] ?? '', $meeting['region'] ?? ''),
                'address' => $meeting['formatted_address'] ?? '',
                'types' => isset($meeting['types']) && is_array($meeting['types']) ? $meeting['types'] : [],
                'attendance_option' => $this->getAttendanceOption($meeting),
                'conference_url' => $meeting['conference_url'] ?? '',
                'entity' => $meeting['entity'] ?? '',
                'searchable_text' => $this->createSearchableText($meeting),
                'coordinates' => [
                    'lat' => isset($meeting['latitude']) ? (float)$meeting['latitude'] : null,
                    'lng' => isset($meeting['longitude']) ? (float)$meeting['longitude'] : null
                ]
            ];
            
            $index[] = $searchEntry;
        }
        
        return $index;
    }
    
    private function createOptimizedData(array $organizedMeetings, array $processStats): array 
    {
        return [
            'meetings_by_date' => $organizedMeetings,
            'process_stats' => $processStats,
            'generated_at' => date('Y-m-d H:i:s T'),
            'total_meetings' => $processStats['online_count'] + $processStats['in_person_count'] + $processStats['hybrid_count']
        ];
    }
    
    private function createSearchableText(array $meeting): string 
    {
        $searchTerms = [
            $meeting['name'] ?? '',
            $meeting['formatted_address'] ?? '',
            $meeting['region'] ?? '',
            $meeting['entity'] ?? '',
            isset($meeting['types']) && is_array($meeting['types']) ? implode(' ', $meeting['types']) : ($meeting['types'] ?? ''),
            $meeting['notes'] ?? ''
        ];
        
        return strtolower(implode(' ', array_filter($searchTerms)));
    }
    
    private function shouldIncludeMeeting(array $meeting): bool 
    {
        $hasAddress = !empty($meeting['formatted_address']);
        $hasConferenceUrl = !empty($meeting['conference_url']);
        
        return $hasAddress || $hasConferenceUrl;
    }
    
    private function getAttendanceOption(array $meeting): string 
    {
        $option = $meeting['attendance_option'] ?? '';
        
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
    
    private function extractCity(string $address, string $region = ''): string 
    {
        if (!empty($address)) {
            $parts = array_map('trim', explode(',', $address));
            
            if (count($parts) >= 2) {
                $cityPart = $parts[count($parts) - 2];
                $cityPart = trim(str_replace(['WA', 'Washington'], '', $cityPart));
                
                if (!empty($cityPart) && $cityPart !== 'USA') {
                    return $cityPart;
                }
            }
        }
        
        if (!empty($region)) {
            return $region;
        }
        
        return 'Unknown Location';
    }
    
    private function saveSearchIndex(array $index): void 
    {
        $filePath = $this->outputDir . '/search-index.json';
        file_put_contents($filePath, json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "  Saved search index to: $filePath\n";
    }
    
    private function saveOptimizedData(array $data): void 
    {
        $filePath = $this->outputDir . '/meetings-optimized.json';
        file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "  Saved optimized data to: $filePath\n";
    }
    
    private function saveBackupData(array $meetings): void 
    {
        $filePath = $this->outputDir . '/meetings-backup.json';
        file_put_contents($filePath, json_encode($meetings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "  Saved backup data to: $filePath\n";
    }
}

// Run the generator
if (php_sapi_name() === 'cli') {
    $generator = new SearchDataGenerator();
    $generator->generateSearchData();
}