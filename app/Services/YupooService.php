<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;
use Exception;
use App\Helpers\StringHelper;

class YupooService
{
    use StringHelper;
    protected $baseUrl;
    protected $config;

    protected $logger;
    // Controls whether debug-level logs are emitted
    protected $debug = false;
    
    public function __construct()
    {
        // Initialize logger
        $this->logger = app('log');
        
        $this->config = config('yupoo', []);
        
        // Set default configuration if not exists
        $this->config = array_merge([
            'base_url' => 'https://297228164.x.yupoo.com',
            'import' => [
                'max_albums' => 50,
                'albums_per_page' => 20,
                'request_delay' => 2,
                'image_download_delay' => 500000,
            ],
            'storage' => [
                'covers' => 'yupoo/covers',
                'images' => 'yupoo/images',
            ],
            'http' => [
                'timeout' => 30,
                'connect_timeout' => 10,
                'verify' => false,
                'retry_times' => 3,
                'retry_sleep' => 1000,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'Connection' => 'keep-alive',
                    'Upgrade-Insecure-Requests' => '1',
                    'Cache-Control' => 'max-age=0',
                    'TE' => 'Trailers',
                ],
                'allow_redirects' => [
                    'max' => 5,
                    'strict' => false,
                    'referer' => true,
                    'protocols' => ['http', 'https'],
                    'track_redirects' => true
                ],
            ],
        ], $this->config);
        
        $this->baseUrl = rtrim($this->config['base_url'], '/');
        
        // Initialize HTTP client with configured options
        $this->httpClient = Http::withOptions($this->config['http']);
        
        // Import required models
        $this->albumModel = app(\App\Models\Album::class);
        $this->imageModel = app(\App\Models\Image::class);
        $this->collectionModel = app(\App\Models\Collection::class);

        // Set debug verbosity from config, defaulting to app.debug
        $this->debug = (bool) (config('yupoo.logging.debug', config('app.debug')));
    }

    public function fetchAlbums($baseUrl = null, $page = 1, $limit = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? $this->config['base_url'] ?? 'https://297228164.x.yupoo.com', '/');
        
        $this->log("Fetching albums from: {$this->baseUrl}", 'info', [
            'page' => $page,
            'limit' => $limit
        ]);
        
        // Log the full configuration being used
        $this->log("Using configuration: " . json_encode([
            'base_url' => $this->baseUrl,
            'http' => [
                'timeout' => $this->config['http']['timeout'] ?? 'default',
                'verify' => $this->config['http']['verify'] ?? 'default',
            ],
            'import' => [
                'max_albums' => $this->config['import']['max_albums'] ?? 'default',
            ]
        ], JSON_PRETTY_PRINT), 'debug');
        
        // Log the actual HTTP request being made
        $requestUrl = $this->baseUrl . (strpos($this->baseUrl, '?') === false ? '?' : '&') . 'page=' . $page . ($limit ? '&limit=' . $limit : '');
        $this->log("Making HTTP request to: " . $requestUrl, 'debug');
        
        $this->log("Sending HTTP GET request to: {$this->baseUrl}", 'debug', [
            'page' => $page,
            'limit' => $limit,
            'headers' => $this->config['http']['headers'] ?? []
        ]);
        
        $startTime = microtime(true);
        $response = $this->httpClient->get($this->baseUrl, [
            'page' => $page,
            'limit' => $limit
        ]);
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        $this->log("Received HTTP response in {$duration}ms", 'debug', [
            'status' => $response->status(),
            'headers' => $response->headers(),
            'body_size' => strlen($response->body())
        ]);
        
        // Log the full configuration being used
        $this->log("Using configuration: " . json_encode([
            'base_url' => $this->baseUrl,
            'http' => [
                'timeout' => $this->config['http']['timeout'] ?? 'default',
                'verify' => $this->config['http']['verify'] ?? 'default',
            ],
            'import' => [
                'max_albums' => $this->config['import']['max_albums'] ?? 'default',
            ]
        ], JSON_PRETTY_PRINT), 'debug');
        
        try {
            // Use provided base URL or fall back to config
            $url = $baseUrl ?? $this->baseUrl;
            
            // Ensure the URL has the proper format
            $url = rtrim($url, '/');
            if (strpos($url, '/albums') === false) {
                $url .= '/albums';
            }
            
            $this->log("Fetching albums from: {$url} (Page: {$page}, Limit: {$limit})", 'debug');
            
            // Add page parameter if not first page
            if ($page > 1) {
                $url .= (strpos($url, '?') === false ? '?' : '&') . 'page=' . $page;
            }
            
            // Add pagination if needed
            $queryParams = [];
            if ($page > 1) {
                $queryParams['page'] = $page;
            }
            
            // Add timestamp to prevent caching
            $queryParams['_'] = time();
            
            // Configure HTTP client with proper headers to mimic a real browser
            $client = Http::withOptions([
                'verify' => false, // Only for development, remove in production
                'timeout' => 30,
                'connect_timeout' => 15,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Referer' => $this->baseUrl,
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'DNT' => '1',
                    'Connection' => 'keep-alive',
                    'Upgrade-Insecure-Requests' => '1',
                    'Cache-Control' => 'no-cache',
                    'Pragma' => 'no-cache',
                ],
                'allow_redirects' => [
                    'max' => 5,
                    'strict' => true,
                    'referer' => true,
                    'protocols' => ['http', 'https'],
                    'track_redirects' => true,
                ],
                'debug' => true,
            ]);
            
            // Add query parameters if any
            if (!empty($queryParams)) {
                $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($queryParams);
            }
            
            $this->log("Sending GET request to: {$url}", 'debug', [
                'headers' => $client->getOptions()['headers'] ?? [],
                'query_params' => $queryParams,
            ]);
            
            // Make the request with error handling
            try {
                $response = $client->get($url);
                
                // Log response details
                $responseHeaders = $response->headers();
                $statusCode = $response->status();
                
                // Get redirect count from headers if available
                $redirectCount = (int)($response->getHeaderLine('X-Guzzle-Redirect-History-Count') ?: 0);
                
                $this->log("Received response: HTTP {$statusCode}", 'debug', [
                    'response_headers' => $responseHeaders,
                    'effective_uri' => $response->effectiveUri() ?? $url,
                    'redirects' => $redirectCount > 0 ? $redirectCount : 0,
                ]);
                
                if (!$response->successful()) {
                    $errorDetails = [
                        'status' => $response->status(),
                        'url' => $url,
                        'response' => substr($response->body(), 0, 500),
                    ];
                    throw new Exception("Failed to fetch albums: " . json_encode($errorDetails));
                }
                
                $html = $response->body();
                
                // Save the raw HTML for debugging
                $debugPath = 'yupoo_debug_' . time() . '.html';
                Storage::disk('local')->put($debugPath, $html);
                $this->log("Saved debug HTML to storage/app/{$debugPath}", 'debug');
                
            } catch (\GuzzleHttp\Exception\RequestException $e) {
                $errorDetails = [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'request' => [
                        'uri' => $e->getRequest()->getUri(),
                        'method' => $e->getRequest()->getMethod(),
                        'headers' => $e->getRequest()->getHeaders(),
                    ],
                ];
                
                if ($e->hasResponse()) {
                    $errorDetails['response'] = [
                        'status' => $e->getResponse()->getStatusCode(),
                        'reason' => $e->getResponse()->getReasonPhrase(),
                        'headers' => $e->getResponse()->getHeaders(),
                        'body' => substr($e->getResponse()->getBody()->getContents(), 0, 500),
                    ];
                    $e->getResponse()->getBody()->rewind(); // Rewind the stream for potential later use
                }
                
                $this->log("Request failed: " . $e->getMessage(), 'error', $errorDetails);
                throw new Exception("HTTP request failed: " . $e->getMessage(), $e->getCode(), $e);
            }
            
            $crawler = new Crawler($html);
            $albums = [];
            
            // Debug: Output the HTML structure for analysis
            $htmlSample = substr($html, 0, 2000);
            $this->log("HTML sample: " . $htmlSample, 'debug');
            
            // Try to find JSON data in the page first (common in modern Yupoo)
            if (preg_match('/window\.__INITIAL_STATE__\s*=\s*({.*?});/s', $html, $matches)) {
                try {
                    $jsonData = json_decode($matches[1], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $this->log("Found JSON data in page, attempting to extract albums");
                        return $this->extractAlbumsFromJson($jsonData);
                    }
                } catch (\Exception $e) {
                    $this->log("Error parsing JSON data: " . $e->getMessage(), 'warning');
                }
            }
            
            // First, try to find the album grid container
            $albumsContainer = $crawler->filter('.album__main, .album-list, .album-grid, .albums, .album-container, .album__list, .album__grid, .albumlist');
            
            if ($albumsContainer->count() === 0) {
                // If no container found, try to find album items directly
                $this->log("No album container found, trying to find album items directly", 'debug');
                $albumNodes = $crawler->filter('a[href*="/albums/"]');
            } else {
                // Find album items within the container
                $albumNodes = $albumsContainer->filter('a[href*="/albums/"]');
                
                // If no album links found in container, try to find album boxes
                if ($albumNodes->count() === 0) {
                    $this->log("No album links found in container, trying to find album boxes", 'debug');
                    $albumNodes = $albumsContainer->filter('.album-item, .album__item, .album-item__main');
                }
            }
            
            $albums = [];
            
            // Process each album node
            $albumNodes->each(function($node) use (&$albums, $crawler) {
                try {
                    $url = $node->attr('href');
                    
                    // Skip if not a valid album URL
                    if (strpos($url, '/albums/') === false) {
                        return;
                    }
                    
                    // Make URL absolute if needed
                    if (strpos($url, 'http') !== 0) {
                        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($url, '/');
                    }
                    
                    // Try to get title from alt text, title attribute, or node text
                    $title = '';
                    $imgNode = $node->filter('img')->first();
                    
                    if ($imgNode->count()) {
                        $title = $imgNode->attr('alt') ?? $imgNode->attr('title') ?? '';
                    }
                    
                    // If no title from image, try to find a title element
                    if (empty($title)) {
                        $titleElement = $node->filter('.album-title, .title, .name, h3, h4, .album__title, .album__name')->first();
                        if ($titleElement->count()) {
                            $title = $titleElement->text();
                        } else {
                            // Fall back to link text
                            $title = $node->text();
                        }
                    }
                    
                    // Clean up the title
                    $title = $this->cleanAlbumName($title);
                    
                    // Get image count if available
                    $imageCount = 0;
                    $countNode = $node->filter('.album-count, .count, .photo-count, .image-count, .album__count');
                    
                    if ($countNode->count()) {
                        $countText = $countNode->text();
                        if (preg_match('/(\d+)/', $countText, $matches)) {
                            $imageCount = (int)$matches[1];
                        }
                    }
                    
                    // Get cover image URL if available
                    $coverImage = '';
                    if ($imgNode->count()) {
                        $coverImage = $imgNode->attr('src') ?? $imgNode->attr('data-src') ?? '';
                        
                        // Handle data-srcset if available
                        if (empty($coverImage) && $imgNode->attr('data-srcset')) {
                            $srcset = explode(',', $imgNode->attr('data-srcset'));
                            if (!empty($srcset[0])) {
                                $coverImage = trim(explode(' ', $srcset[0])[0]);
                            }
                        }
                        
                        // Make URL absolute if needed
                        if (!empty($coverImage) && strpos($coverImage, 'http') !== 0) {
                            $coverImage = rtrim($this->baseUrl, '/') . '/' . ltrim($coverImage, '/');
                        }
                    }
                    
                    // Skip if we don't have a valid title
                    if (empty($title)) {
                        $this->log("Skipping album with empty title: {$url}", 'debug');
                        return;
                    }
                    
                    $albums[] = [
                        'title' => $title,
                        'url' => $url,
                        'image_count' => $imageCount,
                        'cover_image' => $coverImage
                    ];
                    
                    $this->log("Found album: {$title} ({$imageCount} images)", 'debug');
                    
                } catch (\Exception $e) {
                    $this->log("Error processing album node: " . $e->getMessage(), 'warning');
                }
            });
            
            // If we found albums, return them
            if (!empty($albums)) {
                $this->log(sprintf("Found %d albums using direct parsing", count($albums)), 'debug');
                return $albums;
            }
            
            // Fallback to regex if no albums found
            $this->log("No albums found with DOM parsing, trying regex fallback", 'debug');
            
            // Look for album links in the HTML using regex as fallback
            if (preg_match_all('/<a[^>]*?href=[\'\"]([^\'\"]*?\/albums\/\d+)[\'\"][^>]*?>([\s\S]*?)<span[^>]*?>(\d+)<\/span>/i', $html, $matches, PREG_SET_ORDER)) {
                $this->log("Found " . count($matches) . " albums using regex pattern");
                
                foreach ($matches as $match) {
                    $albumUrl = $match[1];
                    $titleHtml = $match[2];
                    $imageCount = (int)$match[3];
                    
                    // Extract title from the HTML
                    if (preg_match('/>([^<]+)</', $titleHtml, $titleMatch)) {
                        $title = trim($titleMatch[1]);
                    } else {
                        $title = 'Untitled Album';
                    }
                    
                    // Clean up the title (remove extra whitespace, etc.)
                    $title = $this->cleanTitle($title);
                    
                    // Make sure URL is absolute
                    if (strpos($albumUrl, 'http') !== 0) {
                        $albumUrl = 'https://297228164.x.yupoo.com' . ltrim($albumUrl, '/');
                    }
                    
                    $albums[] = [
                        'title' => $title,
                        'url' => $albumUrl,
                        'image_count' => $imageCount,
                    ];
                }
                
                if (!empty($albums)) {
                    $this->log("Successfully extracted " . count($albums) . " albums from HTML");
                    return $albums;
                }
            }
            
            // Fall back to other selectors if the first method didn't work
            $selectors = [
                '.album-item',
                '.album__main',
                '.album-item__main',
                '.albumlist',
                '.album-list',
                '.album_item',
                '.albumitem',
                '.album-list-item',
                'div.album',
                'div.album-item',
                'a.album',
                'a.album-item',
                'div[class*="album"]',
                'div[class*="item"]',
                'div[class*="list"]',
                'div[class*="grid"]',
            ];
            
            $foundAlbums = false;
            
            foreach ($selectors as $selector) {
                try {
                    $albumNodes = $crawler->filter($selector);
                    $count = $albumNodes->count();
                    $this->log("Trying selector '{$selector}': found {$count} elements", 'debug');
                    
                    if ($count > 0) {
                        $this->log("Found albums using selector: {$selector}");
                        $foundAlbums = true;
                        
                        $albumNodes->each(function (Crawler $node) use (&$albums) {
                            try {
                                // Try to find the title in various locations
                                $title = $node->attr('title') ?: 
                                        $node->filter('img')->attr('alt') ?: 
                                        $node->filter('div.title, h3, h4, .album__title, .album-title')->text('Untitled Album');
                                        
                                // Clean the title by removing Chinese characters and special chars
                                $title = $this->cleanTitle($title);
                                        
                                // Find image URL
                                $imageNode = $node->filter('img');
                                $imageUrl = $imageNode->count() > 0 ? 
                                    ($imageNode->attr('src') ?: $imageNode->attr('data-src') ?: '') : '';
                                
                                // Find album URL
                                $albumUrl = $node->attr('href');
                                if (empty($albumUrl) && $node->filter('a')->count() > 0) {
                                    $albumUrl = $node->filter('a')->attr('href');
                                }
                                
                                // Make sure the URL is absolute
                                if ($albumUrl && strpos($albumUrl, 'http') !== 0) {
                                    $albumUrl = rtrim($this->baseUrl, '/') . '/' . ltrim($albumUrl, '/');
                                }
                                
                                // Clean up the title
                                $title = trim(html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                                
                                // Only add if we have a valid title after cleaning
                                if (!empty(trim($title))) {
                                    $albums[] = [
                                        'title' => $title,
                                        'cover_image' => $imageUrl,
                                        'url' => $albumUrl,
                                    ];
                                    $this->log("Found album: {$title}", 'debug');
                                }
                            } catch (\Exception $e) {
                                $this->log("Error processing album node: " . $e->getMessage(), 'warning');
                            }
                        });
                        
                        break; // Stop after first successful selector
                    }
                } catch (\Exception $e) {
                    $this->log("Error with selector '{$selector}': " . $e->getMessage(), 'debug');
                }
            }
            
            // If no albums found with standard methods, try to extract from the HTML structure
            if (empty($albums)) {
                $this->log("No albums found with standard methods, trying fallback extraction");
                
                // Try to find all links that might be albums
                $links = $crawler->filter('a');
                $this->log(sprintf("Found %d total links in the page", $links->count()), 'debug');
                
                $links->each(function($link) use (&$albums) {
                    try {
                        $href = $link->attr('href');
                        if ($href && strpos($href, '/albums/') !== false) {
                            $title = $link->text();
                            
                            // Try to get title from image alt if text is empty
                            if (empty(trim($title))) {
                                $img = $link->filter('img')->first();
                                if ($img->count()) {
                                    $title = $img->attr('alt') ?? $img->attr('title') ?? '';
                                }
                            }
                            
                            // Skip if we still don't have a title
                            if (empty(trim($title))) {
                                $title = 'Untitled Album';
                            }
                            
                            // Clean up the title
                            $title = $this->cleanAlbumName($title);
                            
                            // Get cover image if available
                            $coverImage = '';
                            $img = $link->filter('img')->first();
                            if ($img->count()) {
                                $coverImage = $img->attr('src') ?? $img->attr('data-src') ?? '';
                                if (!empty($coverImage) && strpos($coverImage, 'http') !== 0) {
                                    $coverImage = rtrim($this->baseUrl, '/') . '/' . ltrim($coverImage, '/');
                                }
                            }
                            
                            $albums[] = [
                                'title' => $title,
                                'url' => strpos($href, 'http') === 0 ? $href : (rtrim($this->baseUrl, '/') . '/' . ltrim($href, '/')),
                                'image_count' => 0,
                                'cover_image' => $coverImage
                            ];
                            
                            $this->log("Found album via link fallback: {$title}", 'debug');
                        }
                    } catch (\Exception $e) {
                        $this->log("Error in link fallback: " . $e->getMessage(), 'debug');
                    }
                });
                
                // Remove duplicates
                $albums = array_values(array_unique($albums, SORT_REGULAR));
                
                if (!empty($albums)) {
                    $this->log(sprintf("Found %d albums using fallback method", count($albums)), 'debug');
                    return $albums;
                }
                
                // Look for any divs that might contain album information
                $crawler->filter('div')->each(function (Crawler $node) use (&$albums) {
                    try {
                        $class = $node->attr('class') ?: '';
                        $hasImage = $node->filter('img')->count() > 0;
                        $hasLink = $node->filter('a')->count() > 0;
                        
                        // If this looks like an album container
                        if (($hasImage || str_contains($class, 'album') || str_contains($class, 'gallery')) && $hasLink) {
                            $title = $node->filter('img')->attr('alt') ?: 
                                    $node->filter('a')->attr('title') ?:
                                    'Untitled Album';
                            
                            $title = $this->cleanTitle($title);
                            $imageUrl = $node->filter('img')->attr('src') ?: 
                                       $node->filter('img')->attr('data-src') ?: '';
                            $albumUrl = $node->filter('a')->attr('href');
                            
                            if ($albumUrl && strpos($albumUrl, 'http') !== 0) {
                                $albumUrl = rtrim($this->baseUrl, '/') . '/' . ltrim($albumUrl, '/');
                            }
                            
                            if (!empty(trim($title))) {
                                $albums[] = [
                                    'title' => $title,
                                    'cover_image' => $imageUrl,
                                    'url' => $albumUrl,
                                ];
                                $this->log("Found album (fallback): {$title}", 'debug');
                            }
                        }
                    } catch (\Exception $e) {
                        // Silently continue to next node
                    }
                });
            }
            
            // If still no albums, try to extract from JSON data
            if (empty($albums)) {
                $this->log("No albums found with standard selectors, trying JSON extraction");
                
                // Look for JSON data in the page
                if (preg_match('/"album_list"\s*:\s*(\[.*?\])/s', $html, $matches)) {
                    $albumList = json_decode($matches[1], true);
                    
                    if (json_last_error() === JSON_ERROR_NONE && is_array($albumList)) {
                        foreach ($albumList as $album) {
                            if (!empty($album['url'])) {
                                $albums[] = [
                                    'title' => $album['title'] ?? 'Untitled Album',
                                    'cover_image' => $album['cover'] ?? $album['img'] ?? '',
                                    'url' => $album['url'],
                                ];
                            }
                        }
                    }
                }
            }
            
            // If we still don't have albums, log the HTML structure for debugging
            if (empty($albums)) {
                $errorMsg = "No albums could be found in the page. Please check the following:\n";
                $errorMsg .= "1. The URL is correct and accessible\n";
                $errorMsg .= "2. The page structure matches the expected format\n";
                $errorMsg .= "3. You're logged in if the page requires authentication\n\n";
                $errorMsg .= "Debug information has been saved to: storage/app/{$debugPath}";
                
                // Log the first 1000 characters of the HTML for inspection
                $sampleHtml = substr($html, 0, 1000);
                $this->log("HTML sample: " . $sampleHtml, 'error');
                
                // Also log some diagnostic information
                try {
                    $pageTitle = $crawler->filter('title')->text('No title found');
                    $this->log("Page title: " . $pageTitle, 'error');
                    
                    // Try to find any error messages in the page
                    $errorElements = $crawler->filter('.error, .alert, .message, .notice');
                    if ($errorElements->count() > 0) {
                        $this->log("Possible error messages found:", 'error');
                        $errorElements->each(function($node) {
                            $this->log("- " . trim($node->text()), 'error');
                        });
                    }
                } catch (\Exception $e) {
                    $this->log("Error getting page info: " . $e->getMessage(), 'error');
                }
            }
            
            // Add found images to the results
            $images = array_merge($images, $foundImages);
            
            // If still no images, try to extract from alternative JSON patterns
            if (empty($images)) {
                $this->log("No images found with standard selectors, trying alternative JSON patterns");
                
                // Look for various JSON patterns in the page
                $jsonPatterns = [
                    '/"photo_list"\s*:\s*(\[.*?\])/s',
                    '/"photos"\s*:\s*(\[.*?\])/s',
                    '/"items"\s*:\s*(\[.*?\])/s',
                    '/"list"\s*:\s*(\[.*?\])/s'
                ];
                
                foreach ($jsonPatterns as $pattern) {
                    if (preg_match($pattern, $html, $matches)) {
                        $this->log("Found potential JSON data with pattern: $pattern", 'debug');
                        try {
                            $jsonData = json_decode($matches[1], true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                                $this->extractImagesFromJson($jsonData, $images);
                                if (!empty($images)) {
                                    $this->log("Successfully extracted " . count($images) . " images from JSON pattern: $pattern");
                                    break;
                                }
                            }
                        } catch (\Exception $e) {
                            $this->log("Error processing JSON data: " . $e->getMessage(), 'warning');
                        }
                    }
                }
            }
            
            if (empty($images)) {
                $this->log("No images found in the album", 'warning');
            } else {
                $this->log(sprintf("Found %d images in the album", count($images)));
            }
            
            return $images;
            
        } catch (\Exception $e) {
            $this->log("Error fetching album images: " . $e->getMessage(), 'error');
            throw $e;
        }
    }
    
    /**
     * Fetch images from a Yupoo album
     * 
     * @param string $albumUrl The URL of the Yupoo album
     * @return array Array of image URLs
     * @throws \Exception If there's an error fetching or processing the album
     */
    public function fetchAlbumImages($albumUrl)
    {
        try {
            $this->log("Fetching images from album: {$albumUrl}", 'debug');
            
            if (empty($albumUrl)) {
                $this->log("Empty album URL provided", 'error');
                return [];
            }
            
            // Preserve and normalize the provided album URL (keep uid parameter if present)
            // Build an absolute URL when a relative path is provided.
            $originalInputUrl = $albumUrl;
            $parsed = parse_url($albumUrl);
            
            if (empty($parsed['scheme'])) {
                // Relative URL like "/albums/191..." or "albums/191..."
                $path = $albumUrl;
                if ($path[0] !== '/') {
                    $path = '/' . $path;
                }
                $albumUrl = rtrim($this->baseUrl, '/') . $path;
            }
            
            // Ensure uid parameter exists (Yupoo often expects uid=1)
            $urlParts = parse_url($albumUrl);
            $query = [];
            if (!empty($urlParts['query'])) {
                parse_str($urlParts['query'], $query);
            }
            if (!isset($query['uid'])) {
                $query['uid'] = '1';
                $rebuilt = ($urlParts['scheme'] ?? 'https') . '://' . $urlParts['host']
                    . ($urlParts['path'] ?? '')
                    . '?' . http_build_query($query);
                $albumUrl = $rebuilt;
            }
            
            $this->log("Using album URL: {$albumUrl} (from input: {$originalInputUrl})", 'debug');
            
            // Prepare Host from album URL for header
            $hostHeader = parse_url($albumUrl, PHP_URL_HOST) ?: '297228164.x.yupoo.com';

            $response = Http::withOptions([
                'verify' => false, // Only for development, remove in production
                'timeout' => 60,
                'connect_timeout' => 30,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.8,zh-CN;q=0.6,zh;q=0.5',
                    'Referer' => $albumUrl,
                    'Host' => $hostHeader,
                    'DNT' => '1',
                    'Connection' => 'keep-alive',
                    'Upgrade-Insecure-Requests' => '1',
                    'Cache-Control' => 'no-cache',
                    'Pragma' => 'no-cache',
                ],
                'allow_redirects' => [
                    'max' => 5,
                    'strict' => false,
                    'referer' => true,
                    'protocols' => ['http', 'https'],
                    'track_redirects' => true
                ]
            ])->get($albumUrl);
            
            if (!$response->successful()) {
                throw new \Exception("Failed to fetch album page: HTTP " . $response->status());
            }
            
            $html = $response->body();
            
            // Save the HTML for debugging
            $debugHtmlPath = storage_path('logs/yupoo_album_debug_' . md5($albumUrl) . '.html');
            file_put_contents($debugHtmlPath, $html);
            $this->log("Saved album HTML to: " . $debugHtmlPath, 'debug');
            
            // Skip password-protected albums
            if (stripos($html, 'This album is encrypted') !== false || stripos($html, '请输入相册密码') !== false) {
                $this->log('Album appears to be password-protected. Skipping extraction.', 'warning');
                return [];
            }
            
            // Initialize the crawler
            $crawler = new Crawler($html);
            
            // Try to extract images from the page content
            $images = [];
            $addedMap = []; // fast de-duplication map
            
            // 1. First try to find JSON data containing image information
            $jsonFound = false;
            
            $this->log("Starting to search for JSON data in script tags", 'debug');
            
            // Look for common JSON patterns in script tags
            if (preg_match_all('/<script[^>]*>([^<]+)<\/script>/is', $html, $scriptMatches)) {
                $this->log("Found " . count($scriptMatches[1]) . " script tags in the page", 'debug');
                
                foreach ($scriptMatches[1] as $index => $scriptContent) {
                    $this->log("Checking script tag #$index", 'debug');
                    
                    // Try to find JSON data in the script content
                    $jsonPatterns = [
                        'window.__INITIAL_STATE__' => '/window\.__INITIAL_STATE__\s*=\s*({.+?});/is',
                        'photoList' => '/"photoList"\s*:\s*(\[.+?\])/is',
                        'photos' => '/"photos"\s*:\s*(\[.+?\])/is'
                    ];
                    
                    foreach ($jsonPatterns as $patternName => $pattern) {
                        if (preg_match($pattern, $scriptContent, $jsonMatches)) {
                            $this->log("Found JSON data with pattern: $patternName", 'debug');
                            
                            $jsonStr = $jsonMatches[1];
                            $jsonData = json_decode($jsonStr, true);
                            
                            if (json_last_error() === JSON_ERROR_NONE) {
                                $this->log("Successfully decoded JSON data from $patternName", 'debug');
                                $this->log("JSON data structure: " . json_encode(array_keys_recursive($jsonData), JSON_PRETTY_PRINT), 'debug');
                                
                                $this->extractImagesFromJson($jsonData, $images);
                                if (!empty($images)) {
                                    $this->log("Successfully extracted " . count($images) . " images from JSON data");
                                    $jsonFound = true;
                                    break 2; // Break out of both loops
                                } else {
                                    $this->log("No images found in the JSON data", 'debug');
                                }
                            } else {
                                $this->log("Failed to decode JSON data: " . json_last_error_msg(), 'debug');
                            }
                        }
                    }
                }
            } else {
                $this->log("No script tags found in the page", 'debug');
            }
            
            // 2. If no JSON data found, try regex patterns
            if (!$jsonFound) {
                // Try to extract image URLs using regex patterns
                $patterns = [
                    // Direct Yupoo photo CDN links
                    '/(https?:\\/\\/photo\\.yupoo\\.com\\/[^\\s"\']+\\.(?:jpg|jpeg|png|gif|webp))/i',
                    // Common lazy-load attributes
                    '/data-src=[\'\"]([^\'\"]+\\.(?:jpg|jpeg|png|gif|webp)[^\'\"]*?)[\'\"]/i',
                    '/data-original=[\'\"]([^\'\"]+\\.(?:jpg|jpeg|png|gif|webp)[^\'\"]*?)[\'\"]/i',
                    '/data-origin-src=[\'\"]([^\'\"]+\\.(?:jpg|jpeg|png|gif|webp)[^\'\"]*?)[\'\"]/i',
                    '/data-lazy=[\'\"]([^\'\"]+\\.(?:jpg|jpeg|png|gif|webp)[^\'\"]*?)[\'\"]/i',
                    '/data-lazy-src=[\'\"]([^\'\"]+\\.(?:jpg|jpeg|png|gif|webp)[^\'\"]*?)[\'\"]/i',
                    // Background-image URLs
                    '/background-image:\\s*url\([\'\"]?([^\'\"\)]+\\.(?:jpg|jpeg|png|gif|webp)[^\'\"\)]*?)[\'\"]?\)/i',
                    // img src attributes
                    '/<img[^>]+src=[\'\"]([^\'\"]+\\.(?:jpg|jpeg|png|gif|webp)[^\'\"]*?)[\'\"][^>]*>/i'
                ];

                foreach ($patterns as $patternIndex => $pattern) {
                    $this->log("Trying pattern #$patternIndex: " . substr($pattern, 0, 50) . (strlen($pattern) > 50 ? '...' : ''), 'debug');

                    if (preg_match_all($pattern, $html, $urlMatches, PREG_SET_ORDER)) {
                        $this->log("Found " . count($urlMatches) . " potential image URLs with pattern #$patternIndex", 'debug');

                        foreach ($urlMatches as $matchIndex => $match) {
                            $url = $match[1] ?? $match[0];
                            $originalUrl = $url;

                            if (empty($url)) {
                                $this->log("Empty URL found in match #$matchIndex", 'debug');
                                continue;
                            }

                            $this->log("Processing URL #$matchIndex: " . substr($url, 0, 100), 'debug');

                            $url = str_replace(['\\/', '\/'], '/', $url);
                            $url = html_entity_decode($url);

                            // Skip data URLs and known non-image patterns
                            if (strpos($url, 'data:') === 0) {
                                $this->log("Skipping data URL", 'debug');
                                continue;
                            }

                            if (preg_match('/(\\.svg|logo|icon|loading|placeholder|spinner|notaccess|_no_photo|_empty|_default)/i', $url)) {
                                $this->log("Skipping non-image URL: $url", 'debug');
                                continue;
                            }

                            // Make sure URL is absolute
                            if (strpos($url, '//') === 0) {
                                $url = 'https:' . $url;
                                $this->log("Converted protocol-relative URL to: $url", 'debug');
                            } elseif (strpos($url, 'http') !== 0) {
                                $base = rtrim($this->baseUrl, '/');
                                $url = $base . '/' . ltrim($url, '/');
                                $this->log("Converted relative URL to absolute: $url", 'debug');
                            }

                            // Fix Yupoo image URLs to get higher quality
                            $originalUrl = $url;
                            $url = preg_replace('/(\d+)_[a-z0-9]+\\.(jpg|jpeg|png|gif|webp)$/i', '$1.$2', $url);
                            $url = preg_replace('/_(?:square|thumb|small|medium|big)\\.(jpg|jpeg|png|gif|webp)(\\?.*)?$/i', '.$1', $url);

                            // Skip low-res filenames that are exactly square/small/medium/big/thumb
                            $basename = strtolower(basename(parse_url($url, PHP_URL_PATH)));
                            if (in_array($basename, ['square.jpg','small.jpg','medium.jpg','big.jpg','thumb.jpg'])) {
                                $this->log("Skipping low-res variant filename: $basename ($url)", 'debug');
                                continue;
                            }

                            if ($originalUrl !== $url) {
                                $this->log("Improved image quality URL: $originalUrl -> $url", 'debug');
                            }

                            // Check for duplicate URLs via map
                            if (!isset($addedMap[$url])) {
                                $this->log("Adding new image URL: $url", 'debug');
                                $images[] = [
                                    'url' => $url,
                                    'title' => 'Imported from Yupoo',
                                ];
                                $addedMap[$url] = true;
                            } else {
                                $this->log("Skipping duplicate URL: $url", 'debug');
                            }
                        }
                    } else {
                        $this->log("No matches found for pattern #$patternIndex", 'debug');
                    }
                }

                if (!empty($images)) {
                    $this->log("Found " . count($images) . " images using regex patterns");
                    return $images;
                }
            }
            
            // 3. If still no images, try HTML parsing with selectors
            if (empty($images)) {
                $this->log("No images found with JSON or regex, trying HTML selectors");
                
                // Log a sample of the HTML for debugging
                $htmlSample = substr($html, 0, 2000);
                $this->log("HTML sample (first 2000 chars): " . $htmlSample, 'debug');
            }
            
            $selectors = [
                // Yupoo specific selectors
                '.album_photo img',
                '.photo-item img',
                '.photo_img',
                '.photo_img img',
                '.photo-list img',
                '.photo-item a',
                'a[href*="photo.yupoo.com"]',
                'img[src*="photo.yupoo.com"]',
                // More generic selectors
                'img:not([src*="logo"]):not([src*="icon"]):not([src*="avatar"])',
                'a[href$=".jpg"], a[href$=".jpeg"], a[href$=".png"], a[href$=".gif"]',
                '.photo a',
                '.photo-link',
                '.image-container img',
                '.gallery-item img',
                '.grid-item img',
                '.item img',
                'img.photo',
                'img.image'
            ];
            
            $foundImages = [];
            
            foreach ($selectors as $selector) {
                try {
                    if ($crawler->filter($selector)->count() > 0) {
                        $this->log("Found elements with selector: $selector", 'debug');
                        
                        $crawler->filter($selector)->each(function (Crawler $node) use (&$foundImages) {
                            try {
                                // Try to get image URL from different attributes
                                $src = null;
                                $href = null;
                            
                                if ($node->nodeName() === 'img') {
                                    $src = $node->attr('src');
                                    $dataSrc = $node->attr('data-src');
                                    $dataOriginal = $node->attr('data-original');
                                    $dataOriginSrc = $node->attr('data-origin-src');
                                    $dataLazy = $node->attr('data-lazy');
                                    $dataLazySrc = $node->attr('data-lazy-src');
                                    
                                    // Prefer higher-quality/lazy attributes when present
                                    $src = $dataOriginSrc ?? $dataOriginal ?? $dataLazySrc ?? $dataLazy ?? $dataSrc ?? $src;
                                } elseif ($node->nodeName() === 'a') {
                                    $href = $node->attr('href');
                                    // If it's a link, check if it points to an image
                                    if ($href && preg_match('/\\.(jpg|jpeg|png|gif|webp)(\\?.*)?$/i', $href)) {
                                        $src = $href;
                                    }
                                }
                                
                                if (empty($src)) {
                                    return; // Skip if no src
                                }
                                
                                $this->log("Found potential image URL: $src", 'debug');
                                
                                // Skip data URLs and known non-image patterns
                                if (strpos($src, 'data:') === 0 || 
                                    preg_match('/(\\.svg|logo|icon|loading|placeholder|spinner|notaccess|_no_photo|_empty|_default)/i', $src)) {
                                    $this->log("Skipping non-image URL: $src", 'debug');
                                    return;
                                }
                                
                                // Handle Yupoo's image URL patterns
                                if (strpos($src, 'photo.yupoo.com') !== false) {
                                    // Convert from thumbnail to full size for Yupoo
                                    $src = preg_replace('/(\d+)_[a-z0-9]+\\.(jpg|jpeg|png|gif|webp)$/i', '$1.$2', $src);
                                    $src = preg_replace('/_\\w+\\.(jpg|jpeg|png|gif|webp)(\\?.*)?$/i', '.$1', $src);
                                }
                                
                                // Make sure URL is absolute and fix double domains
                                if (strpos($src, '//') === 0) {
                                    $src = 'https:' . $src;
                                } elseif (strpos($src, 'http') !== 0) {
                                    $src = rtrim($this->baseUrl, '/') . '/' . ltrim($src, '/');
                                }
                                
                                if (strpos($src, 'x.yupoo.com/photo.yupoo.com') !== false) {
                                    $src = str_replace('x.yupoo.com/photo.yupoo.com', 'photo.yupoo.com', $src);
                                }
                                
                                // Skip low-res filenames that are exactly square/small/medium/big/thumb
                                $basename = strtolower(basename(parse_url($src, PHP_URL_PATH)));
                                if (in_array($basename, ['square.jpg','small.jpg','medium.jpg','big.jpg','thumb.jpg'])) {
                                    $this->log("Skipping low-res variant filename (selector): $basename ($src)", 'debug');
                                    return;
                                }

                                // Only allow common image extensions
                                if (!preg_match('/\\.(jpg|jpeg|png|gif|webp)(\\?.*)?$/i', $src)) {
                                    $this->log("Skipping non-image URL: {$src}", 'debug');
                                    return;
                                }
                                
                                $title = $this->cleanImageName($node->attr('alt') ?: 'Imported from Yupoo');
                                
                                $foundImages[] = [
                                    'url' => $src,
                                    'title' => $title,
                                ];
                                
                                $this->log("Added image: $src", 'debug');
                                
                            } catch (\Exception $e) {
                                $this->log("Error processing image node: " . $e->getMessage(), 'warning');
                            }
                        });
                    }
                } catch (\Exception $e) {
                    $this->log("Error with selector '$selector': " . $e->getMessage(), 'warning');
                }
            }
            
            // Add found images to the results
            $images = array_merge($images, $foundImages);
            
            if (empty($images)) {
                $this->log("No images found in the album", 'warning');
            } else {
                $this->log(sprintf("Found %d images in the album", count($images)));
            }
            
            return $images;
            
        } catch (\Exception $e) {
            $this->log("Error in fetchAlbumImages: " . $e->getMessage(), 'error');
            throw $e;
        }
}

    public function downloadImage($imageUrl, $type = 'images') {
        try {
            // Clean up the URL first
            $imageUrl = trim($imageUrl);
            
            // Handle protocol-relative URLs
            if (strpos($imageUrl, '//') === 0) {
                $imageUrl = 'https:' . $imageUrl;
            }
            
            // Fix common Yupoo URL issues
            if (strpos($imageUrl, 'x.yupoo.com/photo.yupoo.com') !== false) {
                $imageUrl = str_replace('x.yupoo.com/photo.yupoo.com', 'photo.yupoo.com', $imageUrl);
            }
            
            // Make sure URL is absolute and clean
            if (strpos($imageUrl, 'http') !== 0) {
                $imageUrl = rtrim($this->baseUrl, '/') . '/' . ltrim($imageUrl, '/');
            }
            
            // Skip known non-image URLs and Yupoo placeholders/errors
            if (preg_match('/(\.svg|logo|icon|loading|placeholder|spinner|notaccess|_no_photo|_empty|_default)/i', $imageUrl)) {
                throw new Exception('Skipping non-image or placeholder URL: ' . $imageUrl);
            }
            
            // Skip URLs that don't look like direct image links
            if (!preg_match('/\.(jpg|jpeg|png|gif|webp)(?:\?.*)?$/i', $imageUrl)) {
                throw new Exception('URL does not appear to be a direct image link: ' . $imageUrl);
            }
            
            // Determine storage directory (relative to the public disk)
            // If a numeric type is passed (album id), nest under images/<album_id>
            if (is_numeric($type)) {
                $directory = 'images/' . $type;
            } else {
                // Prefer configured directory key, fallback to provided type
                $directory = $this->config['storage'][$type] ?? $type;
            }
            $directory = trim($directory, '/');
            
            // Generate a unique filename with proper extension
            $filename = $this->generateImageFilename($imageUrl);
            $relativePath = $directory . '/' . $filename;
            $fullPath = 'app/public/' . $relativePath;
            
            // Skip if already exists (use public disk)
            if (Storage::disk('public')->exists($relativePath)) {
                $this->log("Skipping existing image: {$filename}");
                return $relativePath; // Return path relative to the public disk
            }
            
            $this->log("Downloading image: {$imageUrl}");
            
            // Ensure directory exists on the public disk
            if (!Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory, 0755, true);
            }
            
            // Download the image with retry logic
            $maxRetries = 2;
            $retryCount = 0;
            $lastException = null;
            
            while ($retryCount <= $maxRetries) {
                try {
                    $context = stream_context_create([
                        'ssl' => [
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                        ],
                        'http' => [
                            'timeout' => 20,
                            'header' => [
                                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                                'Accept: image/webp,image/apng,image/*,*/*;q=0.8',
                                'Accept-Language: en-US,en;q=0.9',
                                'Referer: ' . $this->baseUrl,
                                'Accept-Encoding: gzip, deflate, br',
                                'Connection: keep-alive',
                            ],
                            'ignore_errors' => true
                        ]
                    ]);
                    
                    $image = @file_get_contents($imageUrl, false, $context);
                    
                    if ($image === false) {
                        $error = error_get_last();
                        throw new Exception($error['message'] ?? 'Failed to download image');
                    }
                    
                    // Check HTTP status code from response headers
                    if (isset($http_response_header)) {
                        $status_line = $http_response_header[0];
                        if (preg_match('{HTTP\/\S*\s(\d{3})}', $status_line, $matches)) {
                            $status_code = $matches[1];
                            if ($status_code >= 400) {
                                throw new Exception("HTTP {$status_code} - " . $status_line);
                            }
                        }
                    }
                    
                    // Basic image validation
                    if (empty($image)) {
                        throw new Exception('Empty image content received');
                    }
                    
                    // Try to determine image type
                    $image_info = @getimagesizefromstring($image);
                    if ($image_info === false) {
                        throw new Exception('Invalid image data received');
                    }
                    
                    $allowed_types = [
                        IMAGETYPE_JPEG,
                        IMAGETYPE_PNG,
                        IMAGETYPE_GIF,
                        IMAGETYPE_WEBP
                    ];
                    
                    if (!in_array($image_info[2], $allowed_types)) {
                        throw new Exception('Unsupported image type: ' . ($image_info[2] ?? 'unknown'));
                    }
                    
                    // Log storage paths for debugging
                    $this->log("Saving image to storage path (public disk): {$relativePath}", 'debug');
                    $this->log("Full storage path: " . storage_path('app/public/' . $relativePath), 'debug');
                    
                    // Save the image to the public disk
                    $saved = Storage::disk('public')->put($relativePath, $image);
                    
                    if (!$saved) {
                        $this->log("Failed to save image to storage. Check permissions for: " . storage_path('app/public/' . $directory), 'error');
                        throw new Exception('Failed to save image to storage');
                    }
                    
                    // Verify the file was actually written
                    if (!Storage::disk('public')->exists($relativePath)) {
                        $this->log("File verification failed. File does not exist at: " . storage_path('app/public/' . $relativePath), 'error');
                        throw new Exception('File was not saved correctly');
                    }
                    
                    $fileSize = Storage::disk('public')->size($relativePath);
                    $this->log("Successfully saved image: {$filename} (Size: {$fileSize} bytes)", 'debug');
                    
                    // Return the relative path expected by Filament components
                    return $relativePath;
                    
                } catch (\Exception $e) {
                    $lastException = $e;
                    $retryCount++;
                    
                    if ($retryCount <= $maxRetries) {
                        $delay = min(pow(2, $retryCount), 5); // Max 5 second delay
                        $this->log("Retry {$retryCount}/{$maxRetries} after {$delay}s: {$e->getMessage()}", 'warning');
                        sleep($delay);
                        continue;
                    }
                    
                    throw $e;
                }
            }
            
            throw new Exception("Failed after {$maxRetries} attempts: " . ($lastException ? $lastException->getMessage() : 'Unknown error'));
            
        } catch (\Exception $e) {
            $this->log("Error downloading image {$imageUrl}: " . $e->getMessage(), 'error');
            throw $e;
        }
    }
    
    /**
     * Generate a unique filename for an image
     */
    protected function generateImageFilename($url, $title = null)
    {
        $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
        $hash = substr(md5($url), 0, 8);
        $slug = $title ? Str::slug(substr($title, 0, 50)) : 'image';
        
        return sprintf('%s_%s.%s', $slug, $hash, $extension);
    }
    
    /**
     * Log a message
     */
    /**
     * Extract albums from Yupoo's JSON data
     * 
     * @param array $jsonData The parsed JSON data from the page
     * @return array Array of album data
     */
    protected function extractAlbumsFromJson($jsonData)
    {
        $albums = [];
        
        try {
            // Try different possible JSON structures
            $possiblePaths = [
                ['albumList', 'albumList'],
                ['album', 'list'],
                ['albums'],
                ['data', 'albums'],
                ['data', 'albumList'],
                ['albumList', 'data'],
                ['album', 'data'],
                ['albumList', 'items'],
                ['items'],
                ['data'],
            ];
            
            $albumData = null;
            
            // Try each possible path to find the album data
            foreach ($possiblePaths as $path) {
                $current = $jsonData;
                $found = true;
                
                foreach ($path as $key) {
                    if (isset($current[$key])) {
                        $current = $current[$key];
                    } else {
                        $found = false;
                        break;
                    }
                }
                
                if ($found && is_array($current) && !empty($current)) {
                    $albumData = $current;
                    $this->log("Found album data using path: " . implode(' -> ', $path), 'debug');
                    break;
                }
            }
            
            if (empty($albumData)) {
                $this->log("No album data found in JSON structure", 'debug');
                return [];
            }
            
            // Process the album data
            foreach ($albumData as $item) {
                try {
                    $album = [
                        'title' => $this->cleanTitle($item['name'] ?? $item['title'] ?? 'Untitled Album'),
                        'url' => $item['url'] ?? $item['link'] ?? '',
                        'cover_image' => $item['cover'] ?? $item['image'] ?? $item['thumb'] ?? '',
                    ];
                    
                    // Make sure URL is absolute and fix double /albums in the path
                    if (!empty($album['url'])) {
                        if (strpos($album['url'], 'http') !== 0) {
                            $album['url'] = rtrim($this->baseUrl, '/') . '/' . ltrim($album['url'], '/');
                        }
                        // Fix URLs with double /albums in the path
                        $album['url'] = preg_replace('|(/albums){2,}|', '/albums', $album['url']);
                    }
                    
                    // Make sure cover image URL is absolute
                    if (!empty($album['cover_image']) && strpos($album['cover_image'], 'http') !== 0) {
                        $album['cover_image'] = rtrim($this->baseUrl, '/') . '/' . ltrim($album['cover_image'], '/');
                    }
                    
                    if (!empty($album['title'])) {
                        $albums[] = $album;
                        $this->log("Found album in JSON: " . $album['title'], 'debug');
                    }
                } catch (\Exception $e) {
                    $this->log("Error processing album item: " . $e->getMessage(), 'warning');
                    continue;
                }
            }
            
            $this->log(sprintf("Extracted %d albums from JSON data", count($albums)));
            
        } catch (\Exception $e) {
            $this->log("Error extracting albums from JSON: " . $e->getMessage(), 'error');
        }
        
        return $albums;
    }
    
    /**
     * Log a message
     */
    /**
     * Import albums and their images from Yupoo
     *
     * @param string|null $baseUrl The base Yupoo URL to import from
     * @param int $maxAlbums Maximum number of albums to import (0 for no limit)
     * @return array Import statistics
     */
    public function importAlbums($baseUrl = null, $maxAlbums = 0)
    {
        $startTime = microtime(true);
        $stats = [
            'total_albums' => 0,
            'imported_albums' => 0,
            'skipped_albums' => 0,
            'imported_images' => 0,
            'skipped_images' => 0,
            'errors' => [],
        ];

        try {
            $this->log("Starting Yupoo import from: " . ($baseUrl ?? $this->baseUrl));
            
            // Fetch all albums from Yupoo
            $albums = $this->fetchAlbums($baseUrl);
            
            if (empty($albums)) {
                $this->log("No albums found to import.", 'warning');
                return $stats;
            }
            
            $this->log(sprintf("Found %d albums to process", count($albums)));
            
            // Process each album
            foreach ($albums as $index => $albumData) {
                try {
                    // Check if we've reached the maximum number of albums to import
                    if ($maxAlbums > 0 && $stats['imported_albums'] >= $maxAlbums) {
                        $this->log("Reached maximum number of albums to import ($maxAlbums)", 'info');
                        break;
                    }
                    
                    $stats['total_albums']++;
                    
                    // Log the raw album data for debugging
                    $this->log("Raw album data: " . json_encode($albumData, JSON_UNESCAPED_UNICODE), 'debug');
                    
                    // Extract album name and clean it (remove Chinese characters and extra spaces)
                    $albumName = $this->cleanAlbumName($albumData['title'] ?? 'Untitled Album');
                    $albumUrl = $albumData['url'] ?? null;
                    
                    // Clean up the album URL to remove any duplicate /albums segments
                    if ($albumUrl) {
                        $albumUrl = preg_replace('|(/albums){2,}|', '/albums', $albumUrl);
                    }
                    
                    $this->log(sprintf("Processing album %d/%d: %s (URL: %s)", 
                        $stats['total_albums'], 
                        count($albums), 
                        $albumName,
                        $albumUrl
                    ));
                    
                    if (!$albumUrl) {
                        $errorMsg = "Skipping album '{$albumName}': No URL provided";
                        $this->log($errorMsg, 'warning');
                        $stats['errors'][] = $errorMsg;
                        $stats['skipped_albums']++;
                        continue;
                    }
                    
                    // Check if album already exists
                    $this->log("Checking if album exists: " . $albumName, 'debug');
                    $album = $this->albumModel->where('title', $albumName)->first();
                    
                    $isNewAlbum = false;
                    if ($album) {
                        $this->log("Album already exists with ID: " . $album->id, 'debug');
                        $stats['skipped_albums']++;
                    } else {
                        // Create a default collection if none exists
                        $collection = $this->collectionModel->first();
                        if (!$collection) {
                            $collection = $this->collectionModel->create([
                                'name' => 'Imported from Yupoo',
                                'description' => 'Automatically created collection for Yupoo imports'
                            ]);
                            $this->log("Created default collection: " . $collection->name, 'info');
                        }
                        
                        // Create the album
                        $album = $this->albumModel->create([
                            'collection_id' => $collection->id,
                            'title' => $albumName,
                            'description' => 'Imported from Yupoo',
                            'cover_image' => null // Will be set after importing images
                        ]);
                        
                        $this->log("Created album: " . $album->title, 'info');
                        $stats['imported_albums']++;
                        $isNewAlbum = true;
                    }
                    
                    
                    // Now import images for this album
                    // Fix URL to prevent duplicate /albums/ in the path
                    $albumUrl = preg_replace('|(/albums){2,}|', '/albums', $albumData['url']);
                    $this->log("Processing album URL: $albumUrl", 'debug');
                    
                    // Get images from the album
                    $this->log("Fetching images for album: {$albumData['title']}", 'debug');
                    $albumImages = $this->fetchAlbumImages($albumUrl);
                    $this->log("Found " . count($albumImages) . " images in album: {$albumData['title']}", 'debug');
                    
                    if (empty($albumImages)) {
                        $this->log("No images found in album: " . $album->title, 'warning');
                        continue;
                    }
                    
                    $this->log(sprintf("Found %d images in album: %s", 
                        count($albumImages), 
                        $album->title
                    ));
                    
                    // Process each image in the album
                    foreach ($albumImages as $imageData) {
                        try {
                            // Check if image already exists
                            $existingImage = $this->imageModel->where('original_url', $imageData['url'])->first();
                            
                            if ($existingImage) {
                                $this->log("Image already exists, skipping: " . $imageData['url'], 'debug');
                                $stats['skipped_images']++;
                                continue;
                            }
                            
                            // Download and save the image first
                            $savedPath = $this->downloadImage($imageData['url'], $album->id);
                            
                            if ($savedPath) {
                                // Create the image with the saved path
                                $image = $this->imageModel->create([
                                    'album_id' => $album->id,
                                    'title' => $this->cleanImageName($imageData['name'] ?? 'Image ' . uniqid()),
                                    'image_path' => $savedPath,
                                    'description' => 'Imported from Yupoo',
                                    'original_url' => $imageData['url']
                                ]);
                                
                                // If this is the first image, set it as the album cover
                                if ($album->cover_image === null) {
                                    $album->update(['cover_image' => $savedPath]);
                                }
                                
                                $stats['imported_images']++;
                                $this->log("Imported image: " . $image->title, 'debug');
                            } else {
                                throw new Exception("Failed to download image");
                            }
                            
                        } catch (\Exception $e) {
                            $errorMsg = sprintf("Error processing image: %s - %s", 
                                $imageData['url'] ?? 'unknown', 
                                $e->getMessage()
                            );
                            $this->log($errorMsg, 'error');
                            $stats['errors'][] = $errorMsg;
                            continue;
                        }
                        
                        // Add a small delay between image downloads to be nice to the server
                        usleep($this->config['import']['image_download_delay']);
                    }
                    
                } catch (\Exception $e) {
                    $errorMsg = sprintf("Error processing album %s: %s", 
                        $albumData['title'] ?? ($albumData['name'] ?? 'unknown'), 
                        $e->getMessage()
                    );
                    $this->log($errorMsg, 'error');
                    $stats['errors'][] = $errorMsg;
                    continue;
                }
                
                // Add a small delay between album processing
                sleep($this->config['import']['request_delay']);
            }
            
            $totalTime = round(microtime(true) - $startTime, 2);
            $this->log(sprintf(
                "Import completed in %s seconds. Stats: %d albums processed (%d imported, %d skipped), %d images imported, %d images skipped",
                $totalTime,
                $stats['total_albums'],
                $stats['imported_albums'],
                $stats['skipped_albums'],
                $stats['imported_images'],
                $stats['skipped_images']
            ), 'info');
            
        } catch (\Exception $e) {
            $errorMsg = "Fatal error during import: " . $e->getMessage();
            $this->log($errorMsg, 'error');
            $stats['errors'][] = $errorMsg;
        }
        
        return $stats;
    }
    
    /**
     * Clean album name by removing Chinese characters and extra spaces
     * 
     * @param string $name
     * @return string
     */
    protected function cleanAlbumName($name)
    {
        // Remove numbers and special characters from the beginning of the name
        $name = preg_replace('/^[\d\s\W]+/', '', $name);
        
        // Remove Chinese characters and anything in parentheses
        $name = preg_replace('/[\x{4e00}-\x{9fff}]+/u', '', $name);
        $name = preg_replace('/\s*\([^)]*\)\s*/', ' ', $name);
        
        // Clean up extra spaces and trim
        $name = preg_replace('/\s+/', ' ', $name);
        $name = trim($name, " -_\t\n\r\0\x0B");
        
        // If name is empty after cleaning, use a default
        if (empty($name)) {
            $name = 'Untitled Album ' . uniqid();
        }
        
        return $name;
    }
    
    /**
     * Extract images from JSON data
     * 
     * @param array $jsonData The JSON data to extract images from
     * @param array &$images Reference to the images array to populate
     */
    protected function extractImagesFromJson($jsonData, &$images)
    {
        try {
            $this->log("Extracting images from JSON data", 'debug');
            
            // If the input is a string, try to decode it
            if (is_string($jsonData)) {
                $jsonData = json_decode($jsonData, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->log("Failed to decode JSON string: " . json_last_error_msg(), 'debug');
                    return;
                }
            }
            
            // If it's not an array at this point, we can't process it
            if (!is_array($jsonData) && !is_object($jsonData)) {
                $this->log("JSON data is not an array or object", 'debug');
                return;
            }
            
            // Convert to array if it's an object
            if (is_object($jsonData)) {
                $jsonData = (array)$jsonData;
            }
            
            // Try different possible JSON structures
            $possiblePaths = [
                ['photoList', 'list'],
                ['photos', 'items'],
                ['album', 'photos'],
                ['data', 'photos'],
                ['data', 'items'],
                ['photos'],
                ['items'],
                ['list'],
                ['photoList'],
                ['album', 'photoList'],
                ['data', 'photo_list'],
                ['photo_list'],
                ['data', 'photoList'],
                ['data', 'list'],
                ['data', 'photo_list', 'list'],
                ['data', 'photoList', 'list'],
                ['data', 'album', 'photos'],
                ['data', 'album', 'photoList'],
                ['data', 'album', 'photo_list']
            ];
            
            // Also try direct access to common keys that might contain image data
            $directKeys = ['photo', 'image', 'img', 'picture', 'pic'];
            
            // First, try direct keys
            foreach ($directKeys as $key) {
                if (isset($jsonData[$key]) && is_string($jsonData[$key])) {
                    $this->processImageItem([
                        'url' => $jsonData[$key],
                        'title' => $jsonData['title'] ?? 'Imported from Yupoo'
                    ], $images);
                }
            }
            
            // Then try all possible paths
            foreach ($possiblePaths as $path) {
                $current = $jsonData;
                $validPath = true;
                
                // Traverse the path
                foreach ($path as $key) {
                    if (is_array($current) && array_key_exists($key, $current)) {
                        $current = $current[$key];
                    } elseif (is_object($current) && property_exists($current, $key)) {
                        $current = $current->$key;
                    } else {
                        $validPath = false;
                        break;
                    }
                }
                
                // If we found a valid array of items
                if ($validPath && (is_array($current) || is_object($current))) {
                    $items = is_object($current) ? (array)$current : $current;
                    
                    $this->log(sprintf("Found %d items in JSON path: %s", 
                        is_countable($items) ? count($items) : 1, 
                        implode(' -> ', $path)
                    ), 'debug');
                    
                    if (is_array($items) || is_object($items)) {
                        foreach ($items as $item) {
                            $this->processImageItem($item, $images);
                        }
                    }
                }
            }
            
            // If we still don't have any images, try to extract from the entire JSON structure
            if (empty($images)) {
                $this->extractImagesFromJsonRecursive($jsonData, $images);
            }
            
            $this->log(sprintf("Extracted %d images from JSON data", count($images)), 'debug');
            
        } catch (\Exception $e) {
            $this->log("Error extracting images from JSON: " . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Process a single image item and add it to the images array
     * 
     * @param mixed $item The item to process (can be array or object)
     * @param array &$images Reference to the images array to populate
     */
    protected function processImageItem($item, &$images)
    {
        try {
            if (is_string($item)) {
                // If the item is a string, treat it as a URL
                $this->addImageIfValid([
                    'url' => $item,
                    'title' => 'Imported from Yupoo'
                ], $images);
                return;
            }
            
            // Convert to array if it's an object
            if (is_object($item)) {
                $item = (array)$item;
            }
            
            // If it's not an array at this point, we can't process it
            if (!is_array($item)) {
                return;
            }
            
            // Extract URL and title
            $imageUrl = null;
            $title = $item['title'] ?? 'Imported from Yupoo';
            
            // Try different possible URL fields
            $urlFields = [
                'url', 'imageUrl', 'src', 'image', 'photoUrl', 'photo_url', 
                'img_src', 'imgUrl', 'image_src', 'original', 'original_url',
                'large', 'large_url', 'medium', 'medium_url', 'small', 'small_url',
                'thumb', 'thumbnail', 'thumbnail_url', 'thumb_url', 'img', 'pic', 'picture'
            ];
            
            foreach ($urlFields as $field) {
                if (!empty($item[$field]) && is_string($item[$field])) {
                    $imageUrl = $item[$field];
                    break;
                }
            }
            
            // If we found a URL, clean it up and add it to the images array
            if ($imageUrl) {
                $this->addImageIfValid([
                    'url' => $imageUrl,
                    'title' => $title,
                    'alt' => $item['alt'] ?? $item['description'] ?? $title,
                    'width' => $item['width'] ?? $item['img_width'] ?? null,
                    'height' => $item['height'] ?? $item['img_height'] ?? null
                ], $images);
            }
            
        } catch (\Exception $e) {
            $this->log("Error processing image item: " . $e->getMessage(), 'warning');
        }
    }
    
    /**
     * Add an image to the images array if it's valid
     * 
     * @param array $imageData The image data to add
     * @param array &$images Reference to the images array to populate
     */
    /**
     * Extract images from nested JSON structures recursively
     * 
     * @param mixed $data The data to search through
     * @param array &$images Reference to the images array to populate
     * @param int $depth Current recursion depth (to prevent infinite recursion)
     */
    protected function extractImagesFromJsonRecursive($data, &$images, $depth = 0)
    {
        // Prevent infinite recursion
        if ($depth > 10) {
            return;
        }
        
        // If it's an array or object, process each item
        if (is_array($data) || is_object($data)) {
            foreach ($data as $key => $value) {
                // Skip large binary data or other non-text data
                if (is_string($value) && strlen($value) > 1000) {
                    continue;
                }
                
                // If the value is an array or object, recurse into it
                if (is_array($value) || is_object($value)) {
                    $this->extractImagesFromJsonRecursive($value, $images, $depth + 1);
                }
                // If the value is a string that looks like a URL, check if it's an image
                elseif (is_string($value) && (strpos($value, 'http') === 0 || strpos($value, '//') === 0)) {
                    $ext = strtolower(pathinfo(parse_url($value, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'])) {
                        $this->addImageIfValid([
                            'url' => $value,
                            'title' => 'Imported from Yupoo',
                            'alt' => $key
                        ], $images);
                    }
                }
            }
        }
    }
    
    /**
     * Add an image to the images array if it's valid
     * 
     * @param array $imageData The image data to add
     * @param array &$images Reference to the images array to populate
     */
    protected function addImageIfValid($imageData, &$images)
    {
        $imageUrl = $imageData['url'] ?? '';
        $title = $imageData['title'] ?? 'Imported from Yupoo';
        
        // Clean up the URL
        $imageUrl = trim($imageUrl);
        
        // Skip if URL is empty
        if (empty($imageUrl)) {
            return;
        }
        
        // Handle protocol-relative URLs
        if (strpos($imageUrl, '//') === 0) {
            $imageUrl = 'https:' . $imageUrl;
        }
        
        // Fix common Yupoo URL issues
        if (strpos($imageUrl, 'x.yupoo.com/photo.yupoo.com') !== false) {
            $imageUrl = str_replace('x.yupoo.com/photo.yupoo.com', 'photo.yupoo.com', $imageUrl);
        }
        
        // Skip known non-image URLs and placeholders
        if (preg_match('/(\.svg|logo|icon|loading|placeholder|spinner|notaccess|_no_photo|_empty|_default)/i', $imageUrl)) {
            $this->log("Skipping non-image URL: $imageUrl", 'debug');
            return;
        }
        
        // Convert thumbnail to full size if needed (Yupoo specific)
        if (strpos($imageUrl, 'photo.yupoo.com') !== false) {
            $imageUrl = preg_replace('/(\d+)_[a-z0-9]+\.(jpg|jpeg|png|gif|webp)$/i', '$1.$2', $imageUrl);
            $imageUrl = preg_replace('/_\w+\.(jpg|jpeg|png|gif|webp)(\?.*)?$/i', '.$1', $imageUrl);
        }
        
        // Make sure URL is absolute
        if (strpos($imageUrl, 'http') !== 0) {
            $imageUrl = rtrim($this->baseUrl, '/') . '/' . ltrim($imageUrl, '/');
        }
        
        $images[] = [
            'url' => $imageUrl,
            'title' => $title
        ];
        
        $this->log("Added image: $imageUrl", 'debug');
    }
    
    /**
     * Clean image name
     * 
     * @param string $name
     * @return string
     */
    protected function cleanImageName($name)
    {
        // Remove file extension if present
        $name = pathinfo($name, PATHINFO_FILENAME);
        
        // Replace underscores and dashes with spaces
        $name = str_replace(['_', '-'], ' ', $name);
        
        // Clean up extra spaces and title case
        $name = preg_replace('/\s+/', ' ', $name);
        return ucwords(trim($name));
    }
    
    /**
     * Log a message with the specified log level
     *
     * @param string $message The message to log
     * @param string $level The log level (info, debug, warning, error)
     * @param array $context Additional context data to include in the log
     */
    protected function log($message, $level = 'info', array $context = [])
    {
        // Suppress debug logs unless debug verbosity is enabled
        if (strtolower($level) === 'debug' && !$this->debug) {
            return;
        }
        // Add timestamp and context to the message
        $timestamp = now()->toDateTimeString();
        
        // Convert context to string, handling large arrays
        $contextStr = '';
        if (!empty($context)) {
            if (is_array($context)) {
                // For large arrays, just show the keys
                if (count($context) > 5) {
                    $contextStr = ' ' . json_encode(array_keys($context));
                } else {
                    $contextStr = ' ' . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                }
            } else {
                $contextStr = ' ' . (string)$context;
            }
        }
        
        $logMessage = "[$timestamp] [YupooService][$level] $message$contextStr";
        
        // Log to Laravel's log file
        \Log::log($level, $logMessage);
        
        // If running in console, output to console
        if (app()->runningInConsole()) {
            // Use direct output if we can't get the output interface
            if (!app()->bound('console.output')) {
                echo "$logMessage\n";
                return;
            }
            
            try {
                $output = app('console.output');
                
                // Format the message for console output
                $formattedMessage = "<fg=blue>[$timestamp]</> <fg=cyan>[YupooService]</> " . 
                                 "<fg=magenta>[$level]</> $message";
                
                // Add context if present
                if (!empty($context)) {
                    $formattedMessage .= "\n" . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                }
                
                switch (strtolower($level)) {
                    case 'error':
                        $output->writeln("<error>$formattedMessage</error>");
                        break;
                    case 'warning':
                        $output->writeln("<comment>$formattedMessage</comment>");
                        break;
                    case 'debug':
                        if ($this->debug && $output->isVerbose()) {
                            $output->writeln("<fg=gray>$formattedMessage</>");
                        }
                        break;
                    default:
                        $output->writeln($formattedMessage);
                }
            } catch (\Exception $e) {
                // If anything goes wrong with console output, just log it
                \Log::error('Error writing to console: ' . $e->getMessage());
            }
        }
    }
}
