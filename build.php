#!/usr/bin/env php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use AAMeetings\MeetingsFetcher;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

echo "Building calendar-based AA meetings site...\n";
echo "==========================================\n\n";

try {
    // Check if vendor directory exists
    if (!is_dir(__DIR__ . '/vendor')) {
        throw new Exception("Dependencies not installed. Please run 'composer install' first.");
    }
    
    // Initialize Twig
    echo "[1/5] Initializing template engine...\n";
    $loader = new FilesystemLoader(__DIR__ . '/templates');
    $twig = new Environment($loader);
    
    // Fetch meetings data
    echo "[2/5] Fetching meetings from Seattle AA API...\n";
    echo "      Using CORS proxy: https://cors-proxy-xi-ten.vercel.app/api/proxy\n";
    
    $fetcher = new MeetingsFetcher();
    $meetingsByDay = $fetcher->getMeetingsByCalendarDay();
    $calendarInfo = $fetcher->getCalendarInfo();
    
    // Count total meetings
    $totalMeetings = 0;
    foreach ($meetingsByDay as $dayMeetings) {
        $totalMeetings += count($dayMeetings);
    }
    
    if ($totalMeetings == 0) {
        throw new Exception("No meetings data received from API. The API might be down or the format may have changed.");
    }
    
    echo "      ✓ Successfully organized " . $totalMeetings . " meetings by calendar day\n\n";
    
    // Ensure output directory exists
    echo "[3/5] Preparing output directory...\n";
    if (!is_dir(__DIR__ . '/output')) {
        mkdir(__DIR__ . '/output', 0755, true);
        echo "      Created output directory\n";
    }
    
    $generatedAt = date('Y-m-d H:i:s');
    $pagesGenerated = 0;
    
    // Generate calendar landing page
    echo "[4/5] Generating calendar and day pages...\n";
    echo "      Generating calendar landing page...\n";
    
    $calendarHtml = $twig->render('calendar.html.twig', [
        'calendar' => $calendarInfo,
        'meetings_by_day' => $meetingsByDay,
        'total_meetings' => $totalMeetings,
        'generated_at' => $generatedAt
    ]);
    
    if (empty($calendarHtml)) {
        throw new Exception("Failed to generate calendar HTML. Template rendering failed.");
    }
    
    file_put_contents(__DIR__ . '/output/index.html', $calendarHtml);
    $pagesGenerated++;
    
    // Generate individual day pages
    for ($day = 1; $day <= $calendarInfo['days_in_month']; $day++) {
        $dayMeetings = $meetingsByDay[$day] ?? [];
        
        // Skip days with no meetings for cleaner output
        if (empty($dayMeetings)) {
            continue;
        }
        
        echo "      Generating day page for day $day (" . count($dayMeetings) . " meetings)...\n";
        
        // Calculate date information
        $date = DateTime::createFromFormat('Y-n-j', $calendarInfo['year'] . '-' . $calendarInfo['month_number'] . '-' . $day);
        $dayName = $date->format('l');
        $dateFormatted = $date->format('F j, Y');
        
        // Find previous and next days with meetings
        $prevDay = null;
        $nextDay = null;
        $prevDayName = '';
        $nextDayName = '';
        
        // Find previous day with meetings
        for ($i = $day - 1; $i >= 1; $i--) {
            if (!empty($meetingsByDay[$i])) {
                $prevDay = $i;
                $prevDate = DateTime::createFromFormat('Y-n-j', $calendarInfo['year'] . '-' . $calendarInfo['month_number'] . '-' . $i);
                $prevDayName = $prevDate->format('l, M j');
                break;
            }
        }
        
        // Find next day with meetings
        for ($i = $day + 1; $i <= $calendarInfo['days_in_month']; $i++) {
            if (!empty($meetingsByDay[$i])) {
                $nextDay = $i;
                $nextDate = DateTime::createFromFormat('Y-n-j', $calendarInfo['year'] . '-' . $calendarInfo['month_number'] . '-' . $i);
                $nextDayName = $nextDate->format('l, M j');
                break;
            }
        }
        
        $dayHtml = $twig->render('day.html.twig', [
            'day' => $day,
            'day_name' => $dayName,
            'date_formatted' => $dateFormatted,
            'meetings' => $dayMeetings,
            'generated_at' => $generatedAt,
            'prev_day' => $prevDay,
            'next_day' => $nextDay,
            'prev_day_name' => $prevDayName,
            'next_day_name' => $nextDayName
        ]);
        
        if (empty($dayHtml)) {
            echo "      ⚠️  Warning: Failed to generate HTML for day $day\n";
            continue;
        }
        
        file_put_contents(__DIR__ . '/output/day-' . $day . '.html', $dayHtml);
        $pagesGenerated++;
    }
    
    echo "      ✓ Generated " . $pagesGenerated . " pages total\n\n";
    
    // Final summary
    echo "[5/5] Build completion summary...\n";
    
    // Calculate file sizes
    $totalSize = 0;
    $files = glob(__DIR__ . '/output/*.html');
    foreach ($files as $file) {
        $totalSize += filesize($file);
    }
    
    echo "      ✓ Calendar landing page: " . realpath(__DIR__ . '/output/index.html') . "\n";
    echo "      ✓ Day pages: " . ($pagesGenerated - 1) . " pages generated\n";
    echo "      ✓ Total output size: " . number_format($totalSize) . " bytes\n\n";
    
    echo "==========================================\n";
    echo "✅ Calendar-based build completed successfully!\n";
    echo "  - Month: " . $calendarInfo['month'] . " " . $calendarInfo['year'] . "\n";
    echo "  - Total meetings: " . $totalMeetings . "\n";
    echo "  - Pages generated: " . $pagesGenerated . "\n";
    echo "  - Days with meetings: " . ($pagesGenerated - 1) . " out of " . $calendarInfo['days_in_month'] . "\n";
    echo "  - Output directory: " . realpath(__DIR__ . '/output') . "\n";
    echo "\nYour calendar-based static site is ready for deployment!\n";
    echo "Start with: " . realpath(__DIR__ . '/output/index.html') . "\n";
    
} catch (Exception $e) {
    echo "\n==========================================\n";
    echo "❌ Build failed!\n";
    echo "Error: " . $e->getMessage() . "\n";
    
    // Provide helpful troubleshooting tips based on the error
    if (strpos($e->getMessage(), 'composer install') !== false) {
        echo "\nTo fix this:\n";
        echo "1. Run: composer install\n";
        echo "2. Then try building again: php build.php\n";
    } elseif (strpos($e->getMessage(), 'Failed to fetch meetings') !== false) {
        echo "\nPossible causes:\n";
        echo "1. No internet connection\n";
        echo "2. CORS proxy is down (check https://cors-proxy-xi-ten.vercel.app/api/proxy)\n";
        echo "3. Seattle AA API is down or has changed\n";
        echo "\nTry running with verbose errors: php -d display_errors=1 build.php\n";
    } elseif (strpos($e->getMessage(), 'write output file') !== false) {
        echo "\nTo fix this:\n";
        echo "1. Check that you have write permissions in the current directory\n";
        echo "2. Try: chmod 755 .\n";
        echo "3. Then run the build again\n";
    }
    
    exit(1);
}