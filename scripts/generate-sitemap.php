<?php
require_once __DIR__ . '/../vendor/autoload.php';

class SitemapGenerator 
{
    private $baseUrl = 'https://noprofits.org/php_meetings/';
    private $outputDir;
    
    public function __construct() 
    {
        $this->outputDir = __DIR__ . '/../output';
    }
    
    public function generateSitemap(): void 
    {
        echo "Generating XML sitemap...\n";
        
        $sitemap = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>');
        
        // Add main index page
        $this->addUrl($sitemap, '', '1.0', 'daily', date('c'));
        
        // Add daily pages
        $dailyDir = $this->outputDir . '/daily/';
        if (is_dir($dailyDir)) {
            $files = scandir($dailyDir);
            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'html') {
                    $date = pathinfo($file, PATHINFO_FILENAME);
                    $this->addUrl($sitemap, 'daily/' . $file, '0.8', 'weekly', date('c'));
                }
            }
        }
        
        // Save sitemap
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $dom->loadXML($sitemap->asXML());
        
        $sitemapPath = $this->outputDir . '/sitemap.xml';
        file_put_contents($sitemapPath, $dom->saveXML());
        
        echo "✓ Sitemap generated: $sitemapPath\n";
        
        // Generate robots.txt
        $this->generateRobotsTxt();
    }
    
    private function addUrl($sitemap, $path, $priority, $changefreq, $lastmod) 
    {
        $url = $sitemap->addChild('url');
        $url->addChild('loc', $this->baseUrl . $path);
        $url->addChild('lastmod', $lastmod);
        $url->addChild('changefreq', $changefreq);
        $url->addChild('priority', $priority);
    }
    
    private function generateRobotsTxt(): void 
    {
        $robotsContent = "User-agent: *\n";
        $robotsContent .= "Allow: /\n";
        $robotsContent .= "Disallow: /vendor/\n";
        $robotsContent .= "Disallow: /src/\n";
        $robotsContent .= "Disallow: /scripts/\n";
        $robotsContent .= "Disallow: /templates/\n";
        $robotsContent .= "\n";
        $robotsContent .= "Sitemap: {$this->baseUrl}sitemap.xml\n";
        
        $robotsPath = $this->outputDir . '/robots.txt';
        file_put_contents($robotsPath, $robotsContent);
        
        echo "✓ Robots.txt generated: $robotsPath\n";
    }
}

// Run the generator
if (php_sapi_name() === 'cli') {
    $generator = new SitemapGenerator();
    $generator->generateSitemap();
}