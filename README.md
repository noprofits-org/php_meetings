# PHP AA Meetings Directory

A PHP-based static site generator that fetches AA meeting data from Seattle AA's TSML (Twelve Step Meeting List) feed and generates mobile-friendly HTML pages organized by date.

## Project Overview

This project creates a lightweight, accessible directory of AA meetings by:
- Fetching real-time meeting data from Seattle AA's TSML JSON feed
- Generating static HTML pages organized by date
- Categorizing meetings into Online, In-Person, and Hybrid sections
- Providing a mobile-first, touch-friendly interface

The goal is to improve AA meeting accessibility through clean, fast-loading static pages that work well on all devices.

## Key Implementation Details

### Data Source: TSML Cache

The project fetches data from Seattle AA's TSML cache file. During development, we discovered that what initially appeared to be a "cache file" is actually the **official TSML data source**:

```php
// The TSML cache URL is found in the HTML as a relative path
// Pattern: <div id="tsml-ui" data-src="/tsml-cache-cbdb25180b.json">
$tsmlCacheUrl = 'https://www.seattleaa.org/tsml-cache-cbdb25180b.json';
```

This is not a workaround - it's the correct approach. The cache file IS the intended API endpoint.

### Meeting Organization

Meetings are organized into three primary categories based on the `attendance_option` field:

1. **Online Meetings** - Virtual meetings with Zoom/conference URLs
2. **In-Person Meetings** - Physical locations only
3. **Hybrid Meetings** - Both physical location and virtual option

Within In-Person and Hybrid sections, meetings are further grouped by city/location for easier navigation.

### Data Processing

The `MeetingsFetcher` class:
- Detects meeting attendance type (when not explicitly set)
- Extracts city information from formatted addresses
- Filters out incomplete meetings (no address AND no conference URL)
- Groups meetings by date and category

## Technical Architecture

### Core Components

```
src/
├── MeetingsFetcher.php    # Fetches and processes TSML data
├── UrlUtils.php           # URL validation helpers
templates/
├── index.html.twig        # Main directory page
├── daily.html.twig        # Daily meeting pages
build.php                  # Main build script
```

### Build Process

The static site is regenerated weekly via GitHub Actions:
```bash
php build.php
```

This:
1. Fetches fresh TSML data
2. Processes ~1,660 meetings
3. Generates 35+ daily pages
4. Creates mobile-optimized CSS

## Current Implementation Status

### ✅ What's Working
- **Data Fetching**: Successfully pulls from TSML cache
- **Meeting Categorization**: Accurate Online/In-Person/Hybrid detection
- **City Detection**: Extracts cities from addresses (including zip codes)
- **Static Generation**: Creates clean HTML pages for 5 weeks of meetings
- **Mobile Design**: Touch-friendly 44px tap targets, responsive layout

### 📋 Fields Displayed
Each meeting shows:
- `name` - Meeting name (linked if URL available)
- `time_formatted` - Human-readable meeting time
- `conference_url` - Zoom/online meeting link
- `types` - Meeting types (O, C, D, etc.)
- `formatted_address` - Physical location
- `entity` - Provider organization with link

### 📊 Typical Build Stats
```
Meetings fetched: 1,660
Online: 408 | In-Person: 905 | Hybrid: 94
Cities detected: 94
Daily pages generated: 35
```

## TSML Detection Notes

For future development, here's what we learned about TSML detection:

**The Mystery**: Seattle AA uses relative URLs in their data-src attribute:
```html
<div id="tsml-ui" data-src="/tsml-cache-cbdb25180b.json">
```

**Correct Detection Pattern**:
```php
$pattern = '/<div id="tsml-ui"\s+data-src="([^"]+)"/i';
```

The cache file name includes a hash (`cbdb25180b`) that likely changes when data updates, so dynamic detection would be ideal for long-term maintenance.

## Technical Context for Future Development

### The TSML Detection Mystery
Seattle AA uses **relative URLs** in their data-src attribute, which was initially confusing. The correct pattern to detect would be:

```php
// Correct pattern for dynamic detection
$pattern = '/<div id="tsml-ui"\s+data-src="([^"]+)"/i';

// This would find: data-src="/tsml-cache-cbdb25180b.json"
// Which resolves to: https://www.seattleaa.org/tsml-cache-cbdb25180b.json
```

### Why the Cache URL is Correct
The `tsml-cache-cbdb25180b.json` file is **not a fallback** - it's the intended API endpoint. This became clear when we analyzed how the TSML UI JavaScript works. The cache represents the current authoritative data source.

### Geographic Sub-grouping Logic
Within In-Person and Hybrid sections, meetings are organized by city using this logic:

```php
private function extractCityFromAddress($address): string
{
    // Parse city from "City, State ZIP" format
    // Handles edge cases like zip-only addresses
    // Groups nearby locations logically
}
```

## Future Considerations

### Potential Enhancements
1. **Dynamic TSML Detection** - Automatically find current cache URL from HTML
2. **Additional Regions** - Extend beyond Seattle AA to other TSML sources
3. **PWA Features** - Add offline support, installability
4. **Search/Filter** - Client-side meeting search functionality

### Comparison with Other Approaches

This project is part of a larger ecosystem of AA meeting accessibility tools:

- **Haskell/Hakyll Version** - Static site generator approach using functional programming
- **RecoverySource/Sober.page** - Python collectors + PostgreSQL + Hugo (database-centric)
- **This PHP Version** - Direct TSML consumption + static generation (simplest approach)

Each approach has trade-offs between complexity, features, and maintenance requirements.

## Related Projects Context

### Part of a Larger Effort
This project exists within a broader initiative to make AA meeting data AI-accessible:

- **Multiple Implementation Approaches**: Different technical stacks solving the same problem
- **Shared Goal**: Making recovery resources universally accessible
- **AI Integration Ready**: Clean HTML structure enables AI assistant consumption

### Comparison Matrix
| Approach | Language | Data Storage | Complexity | Maintenance |
|----------|----------|--------------|------------|-------------|
| This (PHP) | PHP/Twig | None (direct fetch) | Low | Minimal |
| Haskell/Hakyll | Haskell | None (static) | Medium | Low |
| RecoverySource | Python/Hugo | PostgreSQL | High | High |

## Why This Matters

### Accessibility Goals
Making AA meeting data easily accessible supports recovery by:
- **Reducing barriers** to finding meetings
- **Providing consistent**, reliable information
- **Working on all devices** and connection speeds
- **Enabling AI assistants** to help people find meetings

### Technical Philosophy
This implementation prioritizes:
- **Simplicity** over feature richness
- **Reliability** over complex functionality  
- **Mobile-first** design for real-world usage
- **Static generation** for maximum performance

## Development Setup

### Requirements
- PHP 7.4+
- Composer
- Twig templating engine

### Quick Start
```bash
# Install dependencies
composer install

# Build the site
php build.php

# View generated files
open output/index.html
```

### Project Structure
```
php_meetings/
├── src/
│   ├── MeetingsFetcher.php     # Core data processing
│   └── UrlUtils.php            # Helper utilities
├── templates/
│   ├── index.html.twig         # Main directory
│   └── daily.html.twig         # Daily pages
├── output/                     # Generated files (git ignored)
├── build.php                   # Build script
└── composer.json              # Dependencies
```

## Current Status: Production Ready

### What's Stable
- TSML data fetching and processing
- Meeting categorization (Online/In-Person/Hybrid)
- City extraction and grouping
- Mobile-responsive HTML generation
- GitHub Actions deployment pipeline

### Known Limitations
- Seattle AA region only (by design)
- English language only
- No client-side search functionality
- Cache URL hardcoded (could be dynamic)

## GitHub Actions Deployment

The site rebuilds automatically every Sunday via GitHub Actions:
```yaml
# .github/workflows/build-and-deploy.yml
schedule:
  - cron: '0 6 * * 0'  # Weekly on Sunday
```

This ensures meeting data stays current without manual intervention.

## Contributing

### Areas for Improvement
1. **Dynamic TSML Detection** - Parse HTML to find current cache URL
2. **Multi-region Support** - Extend to other TSML sources
3. **Enhanced Mobile UX** - PWA features, offline support
4. **Search Functionality** - Client-side meeting filtering

### Development Guidelines
- Maintain mobile-first design principles
- Keep build process simple and reliable
- Preserve accessibility standards
- Follow existing code patterns

## License

This project focuses on improving accessibility to publicly available AA meeting information. Meeting data remains property of the source organizations.

---

**Status**: Work in progress focused on improving AA meeting accessibility through static HTML generation.

**Live Example**: Generates 35+ daily pages with ~1,660 meetings, organized by attendance type and location.

**Contributions Welcome**: This is part of a larger effort to make recovery resources universally accessible.