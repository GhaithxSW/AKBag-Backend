<?php

namespace App\Services;

use App\Helpers\StringHelper;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

class YupooService
{
    use StringHelper;

    protected $baseUrl;

    protected $config;

    protected $logger;

    // Controls whether debug-level logs are emitted
    protected $debug = false;

    // Cache for existing image URLs to speed up duplicate detection
    protected $existingImageUrls = [];

    // Guzzle client for async operations
    protected $asyncClient;

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
                'request_delay' => 1, // Reduced from 2 to 1 second
                'image_download_delay' => 100000, // Reduced from 500ms to 100ms
                'batch_size' => 8,
                'concurrent_downloads' => 5,
                'bulk_insert_size' => 20,
                'skip_duplicate_check' => false,
                'progress_interval' => 10,
                'max_pages_per_album' => 50,
                'max_empty_pages' => 3,
                'page_request_delay' => 100000,
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
                'pool_size' => 10,
                'max_redirects' => 3,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.8,zh-CN;q=0.6,zh;q=0.5',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'DNT' => '1',
                    'Connection' => 'keep-alive',
                    'Upgrade-Insecure-Requests' => '1',
                ],
                'allow_redirects' => [
                    'max' => 3, // Reduced from 5 to 3
                    'strict' => false,
                    'referer' => true,
                    'protocols' => ['http', 'https'],
                    'track_redirects' => true,
                ],
            ],
        ], $this->config);

        $this->baseUrl = rtrim($this->config['base_url'], '/');

        // Initialize HTTP client with configured options
        $this->httpClient = Http::withOptions($this->config['http']);

        // Initialize async Guzzle client for batch operations
        $this->asyncClient = new Client([
            'timeout' => $this->config['http']['timeout'] ?? 30,
            'connect_timeout' => $this->config['http']['connect_timeout'] ?? 10,
            'verify' => $this->config['http']['verify'] ?? false,
            'headers' => $this->config['http']['headers'] ?? [],
            'allow_redirects' => [
                'max' => $this->config['http']['max_redirects'] ?? 3,
                'strict' => false,
                'referer' => true,
                'protocols' => ['http', 'https'],
                'track_redirects' => true,
            ],
        ]);

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
            'limit' => $limit,
        ]);

        // Log the full configuration being used
        $this->log('Using configuration: '.json_encode([
            'base_url' => $this->baseUrl,
            'http' => [
                'timeout' => $this->config['http']['timeout'] ?? 'default',
                'verify' => $this->config['http']['verify'] ?? 'default',
            ],
            'import' => [
                'max_albums' => $this->config['import']['max_albums'] ?? 'default',
            ],
        ], JSON_PRETTY_PRINT), 'debug');

        // Log the actual HTTP request being made
        $requestUrl = $this->baseUrl.(strpos($this->baseUrl, '?') === false ? '?' : '&').'page='.$page.($limit ? '&limit='.$limit : '');
        $this->log('Making HTTP request to: '.$requestUrl, 'debug');

        $this->log("Sending HTTP GET request to: {$this->baseUrl}", 'debug', [
            'page' => $page,
            'limit' => $limit,
            'headers' => $this->config['http']['headers'] ?? [],
        ]);

        $startTime = microtime(true);
        $response = $this->httpClient->get($this->baseUrl, [
            'page' => $page,
            'limit' => $limit,
        ]);
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        $this->log("Received HTTP response in {$duration}ms", 'debug', [
            'status' => $response->status(),
            'headers' => $response->headers(),
            'body_size' => strlen($response->body()),
        ]);

        // Log the full configuration being used
        $this->log('Using configuration: '.json_encode([
            'base_url' => $this->baseUrl,
            'http' => [
                'timeout' => $this->config['http']['timeout'] ?? 'default',
                'verify' => $this->config['http']['verify'] ?? 'default',
            ],
            'import' => [
                'max_albums' => $this->config['import']['max_albums'] ?? 'default',
            ],
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
                $url .= (strpos($url, '?') === false ? '?' : '&').'page='.$page;
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
            if (! empty($queryParams)) {
                $url .= (strpos($url, '?') === false ? '?' : '&').http_build_query($queryParams);
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
                $redirectCount = (int) ($response->getHeaderLine('X-Guzzle-Redirect-History-Count') ?: 0);

                $this->log("Received response: HTTP {$statusCode}", 'debug', [
                    'response_headers' => $responseHeaders,
                    'effective_uri' => $response->effectiveUri() ?? $url,
                    'redirects' => $redirectCount > 0 ? $redirectCount : 0,
                ]);

                if (! $response->successful()) {
                    $errorDetails = [
                        'status' => $response->status(),
                        'url' => $url,
                        'response' => substr($response->body(), 0, 500),
                    ];
                    throw new Exception('Failed to fetch albums: '.json_encode($errorDetails));
                }

                $html = $response->body();

                // Save the raw HTML for debugging
                $debugPath = 'yupoo_debug_'.time().'.html';
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

                $this->log('Request failed: '.$e->getMessage(), 'error', $errorDetails);
                throw new Exception('HTTP request failed: '.$e->getMessage(), $e->getCode(), $e);
            }

            $crawler = new Crawler($html);
            $albums = [];

            // Debug: Output the HTML structure for analysis
            $htmlSample = substr($html, 0, 2000);
            $this->log('HTML sample: '.$htmlSample, 'debug');

            // Try to find JSON data in the page first (common in modern Yupoo)
            if (preg_match('/window\.__INITIAL_STATE__\s*=\s*({.*?});/s', $html, $matches)) {
                try {
                    $jsonData = json_decode($matches[1], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $this->log('Found JSON data in page, attempting to extract albums');

                        return $this->extractAlbumsFromJson($jsonData);
                    }
                } catch (\Exception $e) {
                    $this->log('Error parsing JSON data: '.$e->getMessage(), 'warning');
                }
            }

            // First, try to find the album grid container
            $albumsContainer = $crawler->filter('.album__main, .album-list, .album-grid, .albums, .album-container, .album__list, .album__grid, .albumlist');

            if ($albumsContainer->count() === 0) {
                // If no container found, try to find album items directly
                $this->log('No album container found, trying to find album items directly', 'debug');
                $albumNodes = $crawler->filter('a[href*="/albums/"]');
            } else {
                // Find album items within the container
                $albumNodes = $albumsContainer->filter('a[href*="/albums/"]');

                // If no album links found in container, try to find album boxes
                if ($albumNodes->count() === 0) {
                    $this->log('No album links found in container, trying to find album boxes', 'debug');
                    $albumNodes = $albumsContainer->filter('.album-item, .album__item, .album-item__main');
                }
            }

            $albums = [];

            // Process each album node
            $albumNodes->each(function ($node) use (&$albums) {
                try {
                    $url = $node->attr('href');

                    // Skip if not a valid album URL
                    if (strpos($url, '/albums/') === false) {
                        return;
                    }

                    // Make URL absolute if needed
                    if (strpos($url, 'http') !== 0) {
                        $url = rtrim($this->baseUrl, '/').'/'.ltrim($url, '/');
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
                            $imageCount = (int) $matches[1];
                        }
                    }

                    // Get cover image URL if available
                    $coverImage = '';
                    if ($imgNode->count()) {
                        $coverImage = $imgNode->attr('src') ?? $imgNode->attr('data-src') ?? '';

                        // Handle data-srcset if available
                        if (empty($coverImage) && $imgNode->attr('data-srcset')) {
                            $srcset = explode(',', $imgNode->attr('data-srcset'));
                            if (! empty($srcset[0])) {
                                $coverImage = trim(explode(' ', $srcset[0])[0]);
                            }
                        }

                        // Make URL absolute if needed
                        if (! empty($coverImage) && strpos($coverImage, 'http') !== 0) {
                            $coverImage = rtrim($this->baseUrl, '/').'/'.ltrim($coverImage, '/');
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
                        'cover_image' => $coverImage,
                    ];

                    $this->log("Found album: {$title} ({$imageCount} images)", 'debug');

                } catch (\Exception $e) {
                    $this->log('Error processing album node: '.$e->getMessage(), 'warning');
                }
            });

            // If we found albums, return them
            if (! empty($albums)) {
                $this->log(sprintf('Found %d albums using direct parsing', count($albums)), 'debug');

                return $albums;
            }

            // Fallback to regex if no albums found
            $this->log('No albums found with DOM parsing, trying regex fallback', 'debug');

            // Look for album links in the HTML using regex as fallback
            if (preg_match_all('/<a[^>]*?href=[\'\"]([^\'\"]*?\/albums\/\d+)[\'\"][^>]*?>([\s\S]*?)<span[^>]*?>(\d+)<\/span>/i', $html, $matches, PREG_SET_ORDER)) {
                $this->log('Found '.count($matches).' albums using regex pattern');

                foreach ($matches as $match) {
                    $albumUrl = $match[1];
                    $titleHtml = $match[2];
                    $imageCount = (int) $match[3];

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
                        $albumUrl = 'https://297228164.x.yupoo.com'.ltrim($albumUrl, '/');
                    }

                    $albums[] = [
                        'title' => $title,
                        'url' => $albumUrl,
                        'image_count' => $imageCount,
                    ];
                }

                if (! empty($albums)) {
                    $this->log('Successfully extracted '.count($albums).' albums from HTML');

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
                                    $albumUrl = rtrim($this->baseUrl, '/').'/'.ltrim($albumUrl, '/');
                                }

                                // Clean up the title
                                $title = trim(html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                                // Only add if we have a valid title after cleaning
                                if (! empty(trim($title))) {
                                    $albums[] = [
                                        'title' => $title,
                                        'cover_image' => $imageUrl,
                                        'url' => $albumUrl,
                                    ];
                                    $this->log("Found album: {$title}", 'debug');
                                }
                            } catch (\Exception $e) {
                                $this->log('Error processing album node: '.$e->getMessage(), 'warning');
                            }
                        });

                        break; // Stop after first successful selector
                    }
                } catch (\Exception $e) {
                    $this->log("Error with selector '{$selector}': ".$e->getMessage(), 'debug');
                }
            }

            // If no albums found with standard methods, try to extract from the HTML structure
            if (empty($albums)) {
                $this->log('No albums found with standard methods, trying fallback extraction');

                // Try to find all links that might be albums
                $links = $crawler->filter('a');
                $this->log(sprintf('Found %d total links in the page', $links->count()), 'debug');

                $links->each(function ($link) use (&$albums) {
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
                                if (! empty($coverImage) && strpos($coverImage, 'http') !== 0) {
                                    $coverImage = rtrim($this->baseUrl, '/').'/'.ltrim($coverImage, '/');
                                }
                            }

                            $albums[] = [
                                'title' => $title,
                                'url' => strpos($href, 'http') === 0 ? $href : (rtrim($this->baseUrl, '/').'/'.ltrim($href, '/')),
                                'image_count' => 0,
                                'cover_image' => $coverImage,
                            ];

                            $this->log("Found album via link fallback: {$title}", 'debug');
                        }
                    } catch (\Exception $e) {
                        $this->log('Error in link fallback: '.$e->getMessage(), 'debug');
                    }
                });

                // Remove duplicates
                $albums = array_values(array_unique($albums, SORT_REGULAR));

                if (! empty($albums)) {
                    $this->log(sprintf('Found %d albums using fallback method', count($albums)), 'debug');

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
                                $albumUrl = rtrim($this->baseUrl, '/').'/'.ltrim($albumUrl, '/');
                            }

                            if (! empty(trim($title))) {
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
                $this->log('No albums found with standard selectors, trying JSON extraction');

                // Look for JSON data in the page
                if (preg_match('/"album_list"\s*:\s*(\[.*?\])/s', $html, $matches)) {
                    $albumList = json_decode($matches[1], true);

                    if (json_last_error() === JSON_ERROR_NONE && is_array($albumList)) {
                        foreach ($albumList as $album) {
                            if (! empty($album['url'])) {
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
                $this->log('HTML sample: '.$sampleHtml, 'error');

                // Also log some diagnostic information
                try {
                    $pageTitle = $crawler->filter('title')->text('No title found');
                    $this->log('Page title: '.$pageTitle, 'error');

                    // Try to find any error messages in the page
                    $errorElements = $crawler->filter('.error, .alert, .message, .notice');
                    if ($errorElements->count() > 0) {
                        $this->log('Possible error messages found:', 'error');
                        $errorElements->each(function ($node) {
                            $this->log('- '.trim($node->text()), 'error');
                        });
                    }
                } catch (\Exception $e) {
                    $this->log('Error getting page info: '.$e->getMessage(), 'error');
                }
            }

            // Add found images to the results
            $images = array_merge($images, $foundImages);

            // If still no images, try to extract from alternative JSON patterns
            if (empty($images)) {
                $this->log('No images found with standard selectors, trying alternative JSON patterns');

                // Look for various JSON patterns in the page
                $jsonPatterns = [
                    '/"photo_list"\s*:\s*(\[.*?\])/s',
                    '/"photos"\s*:\s*(\[.*?\])/s',
                    '/"items"\s*:\s*(\[.*?\])/s',
                    '/"list"\s*:\s*(\[.*?\])/s',
                ];

                foreach ($jsonPatterns as $pattern) {
                    if (preg_match($pattern, $html, $matches)) {
                        $this->log("Found potential JSON data with pattern: $pattern", 'debug');
                        try {
                            $jsonData = json_decode($matches[1], true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                                $this->extractImagesFromJson($jsonData, $images);
                                if (! empty($images)) {
                                    $this->log('Successfully extracted '.count($images)." images from JSON pattern: $pattern");
                                    break;
                                }
                            }
                        } catch (\Exception $e) {
                            $this->log('Error processing JSON data: '.$e->getMessage(), 'warning');
                        }
                    }
                }
            }

            if (empty($images)) {
                $this->log('No images found in the album', 'warning');
            } else {
                $this->log(sprintf('Found %d images in the album', count($images)));
            }

            return $images;

        } catch (\Exception $e) {
            $this->log('Error fetching album images: '.$e->getMessage(), 'error');
            throw $e;
        }
    }

    /**
     * Fetch images from all pages of a Yupoo album
     *
     * @param  string  $albumUrl  The URL of the Yupoo album
     * @param  callable|null  $progressCallback  Progress callback for page processing
     * @return array Array of image URLs from all pages
     *
     * @throws \Exception If there's an error fetching or processing the album
     */
    public function fetchAlbumImages($albumUrl, $progressCallback = null)
    {
        $allImages = [];
        $currentPage = 1;
        $maxPages = $this->config['import']['max_pages_per_album'] ?? 50;
        $emptyPageCount = 0;
        $maxEmptyPages = $this->config['import']['max_empty_pages'] ?? 3;

        $this->log("Starting multi-page fetch for album: {$albumUrl}", 'debug');

        while ($currentPage <= $maxPages) {
            try {
                // Progress callback for page processing
                if ($progressCallback) {
                    $progressCallback('pages', $currentPage, $currentPage, "Processing page {$currentPage}");
                }

                $this->log("Fetching page {$currentPage} of album: {$albumUrl}", 'debug');

                // Fetch images from current page
                $pageImages = $this->fetchAlbumImagesFromPage($albumUrl, $currentPage);

                if (empty($pageImages)) {
                    $emptyPageCount++;
                    $this->log("Page {$currentPage} returned no images (empty page count: {$emptyPageCount})", 'debug');

                    if ($emptyPageCount >= $maxEmptyPages) {
                        $this->log("Reached {$maxEmptyPages} consecutive empty pages, stopping pagination", 'debug');
                        break;
                    }
                } else {
                    $emptyPageCount = 0; // Reset counter when we find images
                    $pageImageCount = count($pageImages);
                    $this->log("Found {$pageImageCount} images on page {$currentPage}", 'debug');

                    // Merge images from this page, avoiding duplicates
                    $existingUrls = array_column($allImages, 'url');
                    foreach ($pageImages as $image) {
                        if (! in_array($image['url'], $existingUrls)) {
                            $allImages[] = $image;
                        }
                    }
                }

                $currentPage++;

                // Small delay between page requests to be respectful
                if ($currentPage <= $maxPages) {
                    usleep($this->config['import']['page_request_delay'] ?? 100000);
                }

            } catch (\Exception $e) {
                $this->log("Error fetching page {$currentPage}: ".$e->getMessage(), 'warning');

                // If first page fails, re-throw the exception
                if ($currentPage == 1) {
                    throw $e;
                }

                // For other pages, just log and continue
                $emptyPageCount++;
                if ($emptyPageCount >= $maxEmptyPages) {
                    $this->log('Too many consecutive page errors, stopping pagination', 'warning');
                    break;
                }

                $currentPage++;
            }
        }

        if ($currentPage > $maxPages) {
            $this->log("Reached maximum page limit ({$maxPages}) for album", 'warning');
        }

        $totalImages = count($allImages);
        $totalPagesProcessed = $currentPage - 1;
        $this->log("Multi-page fetch completed: {$totalImages} total images from {$totalPagesProcessed} pages", 'info');

        return $allImages;
    }

    /**
     * Fetch images from a specific page of a Yupoo album
     *
     * @param  string  $albumUrl  The URL of the Yupoo album
     * @param  int  $page  The page number to fetch
     * @return array Array of image URLs from the specified page
     *
     * @throws \Exception If there's an error fetching or processing the page
     */
    protected function fetchAlbumImagesFromPage($albumUrl, $page = 1)
    {
        try {
            $this->log("Fetching images from album: {$albumUrl} (page {$page})", 'debug');

            if (empty($albumUrl)) {
                $this->log('Empty album URL provided', 'error');

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
                    $path = '/'.$path;
                }
                $albumUrl = rtrim($this->baseUrl, '/').$path;
            }

            // Build query parameters including page
            $urlParts = parse_url($albumUrl);
            $query = [];
            if (! empty($urlParts['query'])) {
                parse_str($urlParts['query'], $query);
            }

            // Add required parameters
            if (! isset($query['uid'])) {
                $query['uid'] = '1';
            }

            // Add page parameter if not first page
            if ($page > 1) {
                $query['page'] = $page;
            }

            // Rebuild URL with all parameters
            $albumUrl = ($urlParts['scheme'] ?? 'https').'://'.$urlParts['host']
                .($urlParts['path'] ?? '')
                .'?'.http_build_query($query);

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
                    'track_redirects' => true,
                ],
            ])->get($albumUrl);

            if (! $response->successful()) {
                throw new \Exception('Failed to fetch album page: HTTP '.$response->status());
            }

            $html = $response->body();

            // Save the HTML for debugging
            $debugHtmlPath = storage_path('logs/yupoo_album_debug_'.md5($albumUrl).'.html');
            file_put_contents($debugHtmlPath, $html);
            $this->log('Saved album HTML to: '.$debugHtmlPath, 'debug');

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

            $this->log('Starting to search for JSON data in script tags', 'debug');

            // Look for common JSON patterns in script tags
            if (preg_match_all('/<script[^>]*>([^<]+)<\/script>/is', $html, $scriptMatches)) {
                $this->log('Found '.count($scriptMatches[1]).' script tags in the page', 'debug');

                foreach ($scriptMatches[1] as $index => $scriptContent) {
                    $this->log("Checking script tag #$index", 'debug');

                    // Try to find JSON data in the script content
                    $jsonPatterns = [
                        'window.__INITIAL_STATE__' => '/window\.__INITIAL_STATE__\s*=\s*({.+?});/is',
                        'photoList' => '/"photoList"\s*:\s*(\[.+?\])/is',
                        'photos' => '/"photos"\s*:\s*(\[.+?\])/is',
                    ];

                    foreach ($jsonPatterns as $patternName => $pattern) {
                        if (preg_match($pattern, $scriptContent, $jsonMatches)) {
                            $this->log("Found JSON data with pattern: $patternName", 'debug');

                            $jsonStr = $jsonMatches[1];
                            $jsonData = json_decode($jsonStr, true);

                            if (json_last_error() === JSON_ERROR_NONE) {
                                $this->log("Successfully decoded JSON data from $patternName", 'debug');
                                $this->log('JSON data structure: '.json_encode(array_keys_recursive($jsonData), JSON_PRETTY_PRINT), 'debug');

                                $this->extractImagesFromJson($jsonData, $images);
                                if (! empty($images)) {
                                    $this->log('Successfully extracted '.count($images).' images from JSON data');
                                    $jsonFound = true;
                                    break 2; // Break out of both loops
                                } else {
                                    $this->log('No images found in the JSON data', 'debug');
                                }
                            } else {
                                $this->log('Failed to decode JSON data: '.json_last_error_msg(), 'debug');
                            }
                        }
                    }
                }
            } else {
                $this->log('No script tags found in the page', 'debug');
            }

            // 2. If no JSON data found, try regex patterns
            if (! $jsonFound) {
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
                    '/<img[^>]+src=[\'\"]([^\'\"]+\\.(?:jpg|jpeg|png|gif|webp)[^\'\"]*?)[\'\"][^>]*>/i',
                ];

                foreach ($patterns as $patternIndex => $pattern) {
                    $this->log("Trying pattern #$patternIndex: ".substr($pattern, 0, 50).(strlen($pattern) > 50 ? '...' : ''), 'debug');

                    if (preg_match_all($pattern, $html, $urlMatches, PREG_SET_ORDER)) {
                        $this->log('Found '.count($urlMatches)." potential image URLs with pattern #$patternIndex", 'debug');

                        foreach ($urlMatches as $matchIndex => $match) {
                            $url = $match[1] ?? $match[0];
                            $originalUrl = $url;

                            if (empty($url)) {
                                $this->log("Empty URL found in match #$matchIndex", 'debug');

                                continue;
                            }

                            $this->log("Processing URL #$matchIndex: ".substr($url, 0, 100), 'debug');

                            $url = str_replace(['\\/', '\/'], '/', $url);
                            $url = html_entity_decode($url);

                            // Skip data URLs and known non-image patterns
                            if (strpos($url, 'data:') === 0) {
                                $this->log('Skipping data URL', 'debug');

                                continue;
                            }

                            if (preg_match('/(\\.svg|logo|icon|loading|placeholder|spinner|notaccess|_no_photo|_empty|_default)/i', $url)) {
                                $this->log("Skipping non-image URL: $url", 'debug');

                                continue;
                            }

                            // Make sure URL is absolute
                            if (strpos($url, '//') === 0) {
                                $url = 'https:'.$url;
                                $this->log("Converted protocol-relative URL to: $url", 'debug');
                            } elseif (strpos($url, 'http') !== 0) {
                                $base = rtrim($this->baseUrl, '/');
                                $url = $base.'/'.ltrim($url, '/');
                                $this->log("Converted relative URL to absolute: $url", 'debug');
                            }

                            // Fix Yupoo image URLs to get higher quality
                            $originalUrl = $url;
                            $url = preg_replace('/(\d+)_[a-z0-9]+\\.(jpg|jpeg|png|gif|webp)$/i', '$1.$2', $url);
                            $url = preg_replace('/_(?:square|thumb|small|medium|big)\\.(jpg|jpeg|png|gif|webp)(\\?.*)?$/i', '.$1', $url);

                            // Skip low-res filenames that are exactly square/small/medium/big/thumb
                            $basename = strtolower(basename(parse_url($url, PHP_URL_PATH)));
                            if (in_array($basename, ['square.jpg', 'small.jpg', 'medium.jpg', 'big.jpg', 'thumb.jpg'])) {
                                $this->log("Skipping low-res variant filename: $basename ($url)", 'debug');

                                continue;
                            }

                            if ($originalUrl !== $url) {
                                $this->log("Improved image quality URL: $originalUrl -> $url", 'debug');
                            }

                            // Check for duplicate URLs via map
                            if (! isset($addedMap[$url])) {
                                $this->log("Adding new image URL: $url", 'debug');
                                $imageIndex = count($images) + 1;
                                $imageName = $this->extractImageNameFromUrl($url) ?? 'Image '.str_pad($imageIndex, 3, '0', STR_PAD_LEFT);
                                $images[] = [
                                    'url' => $url,
                                    'title' => $imageName,
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

                if (! empty($images)) {
                    $this->log('Found '.count($images).' images using regex patterns');

                    return $images;
                }
            }

            // 3. If still no images, try HTML parsing with selectors
            if (empty($images)) {
                $this->log('No images found with JSON or regex, trying HTML selectors');

                // Log a sample of the HTML for debugging
                $htmlSample = substr($html, 0, 2000);
                $this->log('HTML sample (first 2000 chars): '.$htmlSample, 'debug');
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
                'img.image',
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
                                    $src = 'https:'.$src;
                                } elseif (strpos($src, 'http') !== 0) {
                                    $src = rtrim($this->baseUrl, '/').'/'.ltrim($src, '/');
                                }

                                if (strpos($src, 'x.yupoo.com/photo.yupoo.com') !== false) {
                                    $src = str_replace('x.yupoo.com/photo.yupoo.com', 'photo.yupoo.com', $src);
                                }

                                // Skip low-res filenames that are exactly square/small/medium/big/thumb
                                $basename = strtolower(basename(parse_url($src, PHP_URL_PATH)));
                                if (in_array($basename, ['square.jpg', 'small.jpg', 'medium.jpg', 'big.jpg', 'thumb.jpg'])) {
                                    $this->log("Skipping low-res variant filename (selector): $basename ($src)", 'debug');

                                    return;
                                }

                                // Only allow common image extensions
                                if (! preg_match('/\\.(jpg|jpeg|png|gif|webp)(\\?.*)?$/i', $src)) {
                                    $this->log("Skipping non-image URL: {$src}", 'debug');

                                    return;
                                }

                                // Use new intelligent title extraction
                                $imageIndex = count($foundImages) + 1;
                                $title = $this->generateMeaningfulImageName($node, $src, $imageIndex);

                                $foundImages[] = [
                                    'url' => $src,
                                    'title' => $title,
                                ];

                                $this->log("Added image: $src", 'debug');

                            } catch (\Exception $e) {
                                $this->log('Error processing image node: '.$e->getMessage(), 'warning');
                            }
                        });
                    }
                } catch (\Exception $e) {
                    $this->log("Error with selector '$selector': ".$e->getMessage(), 'warning');
                }
            }

            // Add found images to the results
            $images = array_merge($images, $foundImages);

            if (empty($images)) {
                $this->log('No images found in the album', 'warning');
            } else {
                $this->log(sprintf('Found %d images in the album', count($images)));
            }

            return $images;

        } catch (\Exception $e) {
            $this->log('Error in fetchAlbumImages: '.$e->getMessage(), 'error');
            throw $e;
        }
    }

    public function downloadImage($imageUrl, $type = 'images')
    {
        try {
            // Clean up the URL first
            $imageUrl = trim($imageUrl);

            // Handle protocol-relative URLs
            if (strpos($imageUrl, '//') === 0) {
                $imageUrl = 'https:'.$imageUrl;
            }

            // Fix common Yupoo URL issues
            if (strpos($imageUrl, 'x.yupoo.com/photo.yupoo.com') !== false) {
                $imageUrl = str_replace('x.yupoo.com/photo.yupoo.com', 'photo.yupoo.com', $imageUrl);
            }

            // Make sure URL is absolute and clean
            if (strpos($imageUrl, 'http') !== 0) {
                $imageUrl = rtrim($this->baseUrl, '/').'/'.ltrim($imageUrl, '/');
            }

            // Skip known non-image URLs and Yupoo placeholders/errors
            if (preg_match('/(\.svg|logo|icon|loading|placeholder|spinner|notaccess|_no_photo|_empty|_default)/i', $imageUrl)) {
                throw new Exception('Skipping non-image or placeholder URL: '.$imageUrl);
            }

            // Skip URLs that don't look like direct image links
            if (! preg_match('/\.(jpg|jpeg|png|gif|webp)(?:\?.*)?$/i', $imageUrl)) {
                throw new Exception('URL does not appear to be a direct image link: '.$imageUrl);
            }

            // Determine storage directory (relative to the public disk)
            // If a numeric type is passed (album id), nest under images/<album_id>
            if (is_numeric($type)) {
                $directory = 'images/'.$type;
            } else {
                // Prefer configured directory key, fallback to provided type
                $directory = $this->config['storage'][$type] ?? $type;
            }
            $directory = trim($directory, '/');

            // Generate a unique filename with proper extension
            $filename = $this->generateImageFilename($imageUrl);
            $relativePath = $directory.'/'.$filename;
            $fullPath = 'app/public/'.$relativePath;

            // Skip if already exists (use public disk)
            if (Storage::disk('s3')->exists($relativePath)) {
                $this->log("Skipping existing image: {$filename}");

                return $relativePath; // Return path relative to the public disk
            }

            $this->log("Downloading image: {$imageUrl}");

            // Ensure directory exists on the public disk
            if (! Storage::disk('s3')->exists($directory)) {
                Storage::disk('s3')->makeDirectory($directory, 0755, true);
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
                                'Referer: '.$this->baseUrl,
                                'Accept-Encoding: gzip, deflate, br',
                                'Connection: keep-alive',
                            ],
                            'ignore_errors' => true,
                        ],
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
                                throw new Exception("HTTP {$status_code} - ".$status_line);
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
                        IMAGETYPE_WEBP,
                    ];

                    if (! in_array($image_info[2], $allowed_types)) {
                        throw new Exception('Unsupported image type: '.($image_info[2] ?? 'unknown'));
                    }

                    // Log storage paths for debugging
                    $this->log("Saving image to storage path (public disk): {$relativePath}", 'debug');
                    $this->log('Saving to S3 path: '.$relativePath, 'debug');

                    // Save the image to S3 using AWS SDK directly to avoid ACL issues
                    $s3Client = new \Aws\S3\S3Client([
                        'version' => 'latest',
                        'region' => config('filesystems.disks.s3.region'),
                        'credentials' => [
                            'key' => config('filesystems.disks.s3.key'),
                            'secret' => config('filesystems.disks.s3.secret'),
                        ],
                    ]);
                    $bucket = config('filesystems.disks.s3.bucket');
                    try {
                        $s3Client->putObject([
                            'Bucket' => $bucket,
                            'Key' => $relativePath,
                            'Body' => $image,
                            'ContentType' => 'image/jpeg',
                        ]);
                        $saved = true;
                    } catch (\Exception $s3Exception) {
                        $this->log('S3 direct upload failed: '.$s3Exception->getMessage(), 'error');
                        $saved = false;
                    }

                    if (! $saved) {
                        $this->log('Failed to save image to S3. Check AWS credentials and bucket permissions.', 'error');
                        throw new Exception('Failed to save image to storage');
                    }

                    // Verify the file was actually written
                    if (! Storage::disk('s3')->exists($relativePath)) {
                        $this->log('File verification failed. File does not exist in S3: '.$relativePath, 'error');
                        throw new Exception('File was not saved correctly');
                    }

                    $fileSize = Storage::disk('s3')->size($relativePath);
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

            throw new Exception("Failed after {$maxRetries} attempts: ".($lastException ? $lastException->getMessage() : 'Unknown error'));
        } catch (\Exception $e) {
            $this->log("Error downloading image {$imageUrl}: ".$e->getMessage(), 'error');
            throw $e;
        }
    }

    /**
     * Load existing image URLs into cache for fast duplicate detection
     */
    protected function loadExistingImageUrls()
    {
        if (! empty($this->existingImageUrls)) {
            return; // Already loaded
        }

        try {
            $this->log('Loading existing image URLs for duplicate detection', 'debug');

            // Get existing URLs and filter out null/empty values
            $existingUrls = $this->imageModel
                ->whereNotNull('original_url')
                ->where('original_url', '!=', '')
                ->pluck('original_url')
                ->toArray();

            // Filter out any remaining non-string values and ensure uniqueness
            $validUrls = array_filter($existingUrls, function ($url) {
                return is_string($url) && ! empty(trim($url));
            });

            // Remove duplicates and flip for O(1) lookup
            $uniqueUrls = array_unique($validUrls);
            $this->existingImageUrls = array_flip($uniqueUrls);

            $this->log('Loaded '.count($this->existingImageUrls).' existing image URLs for duplicate detection', 'debug');

        } catch (\Exception $e) {
            $this->log('Error loading existing image URLs: '.$e->getMessage(), 'warning');
            $this->existingImageUrls = []; // Initialize as empty array on error
        }
    }

    /**
     * Check if images already exist in bulk (faster than individual queries)
     */
    protected function filterNewImages(array $imageUrls)
    {
        if ($this->config['import']['skip_duplicate_check'] ?? false) {
            $this->log('Skipping duplicate check as configured', 'debug');

            return $imageUrls;
        }

        try {
            $this->loadExistingImageUrls();

            $newImages = [];
            foreach ($imageUrls as $url) {
                // Ensure URL is a valid string before checking
                if (! is_string($url) || empty(trim($url))) {
                    $this->log('Skipping invalid URL: '.var_export($url, true), 'debug');

                    continue;
                }

                if (! isset($this->existingImageUrls[$url])) {
                    $newImages[] = $url;
                } else {
                    $this->log('Skipping duplicate image: '.substr($url, -50), 'debug');
                }
            }

            $duplicateCount = count($imageUrls) - count($newImages);
            if ($duplicateCount > 0) {
                $this->log("Filtered out {$duplicateCount} duplicate images from batch", 'debug');
            }

            return $newImages;

        } catch (\Exception $e) {
            $this->log('Error in filterNewImages: '.$e->getMessage().', falling back to no filtering', 'warning');

            // Fallback: return all URLs if filtering fails
            return array_filter($imageUrls, function ($url) {
                return is_string($url) && ! empty(trim($url));
            });
        }
    }

    /**
     * Download multiple images concurrently
     */
    public function downloadImagesBatch(array $imageUrls, $albumId = null, $progressCallback = null)
    {
        $type = $albumId ?? 'images';
        $batchSize = $this->config['import']['concurrent_downloads'] ?? 5;
        $results = [];

        $this->log('Starting batch download of '.count($imageUrls)." images with batch size {$batchSize}");

        // Process images in batches to avoid overwhelming the server
        $chunks = array_chunk($imageUrls, $batchSize);

        foreach ($chunks as $chunkIndex => $chunk) {
            $this->log('Processing batch '.($chunkIndex + 1).'/'.count($chunks));

            // Progress callback for download batches
            if ($progressCallback) {
                $processedImages = count($results);
                $progressCallback('download', $processedImages, count($imageUrls),
                    'Batch '.($chunkIndex + 1).'/'.count($chunks));
            }

            $promises = [];
            foreach ($chunk as $index => $imageUrl) {
                try {
                    // Clean up the URL
                    $imageUrl = $this->cleanImageUrl($imageUrl);

                    if (! $this->isValidImageUrl($imageUrl)) {
                        $this->log("Skipping invalid URL: {$imageUrl}", 'warning');

                        continue;
                    }

                    // Create async promise
                    $promises[$index] = $this->asyncClient->getAsync($imageUrl, [
                        'timeout' => 20,
                        'connect_timeout' => 10,
                        'headers' => [
                            'User-Agent' => $this->config['http']['headers']['User-Agent'] ?? 'Mozilla/5.0',
                            'Accept' => 'image/webp,image/apng,image/*,*/*;q=0.8',
                            'Referer' => $this->baseUrl,
                        ],
                    ]);

                } catch (\Exception $e) {
                    $this->log("Error creating promise for {$imageUrl}: ".$e->getMessage(), 'warning');
                }
            }

            if (empty($promises)) {
                continue;
            }

            // Wait for all promises to complete
            try {
                $responses = Utils::settle($promises)->wait();

                foreach ($responses as $index => $response) {
                    $imageUrl = $chunk[$index];

                    if ($response['state'] === 'fulfilled') {
                        try {
                            $httpResponse = $response['value'];
                            $statusCode = $httpResponse->getStatusCode();

                            if ($statusCode >= 200 && $statusCode < 300) {
                                $imageData = $httpResponse->getBody()->getContents();

                                if (! empty($imageData) && $this->isValidImageData($imageData)) {
                                    $savedPath = $this->saveImageData($imageData, $imageUrl, $type);
                                    if ($savedPath) {
                                        $results[] = [
                                            'url' => $imageUrl,
                                            'path' => $savedPath,
                                            'status' => 'success',
                                        ];

                                        // Add to existing URLs cache to prevent future duplicates
                                        $this->existingImageUrls[$imageUrl] = true;
                                    } else {
                                        $results[] = [
                                            'url' => $imageUrl,
                                            'path' => null,
                                            'status' => 'retry',
                                            'error' => 'Failed to save image data',
                                        ];
                                    }
                                } else {
                                    $results[] = [
                                        'url' => $imageUrl,
                                        'path' => null,
                                        'status' => 'error',
                                        'error' => 'Invalid or empty image data received',
                                    ];
                                }
                            } else {
                                // HTTP error status
                                $results[] = [
                                    'url' => $imageUrl,
                                    'path' => null,
                                    'status' => $statusCode >= 500 ? 'retry' : 'error', // Retry server errors
                                    'error' => "HTTP {$statusCode}",
                                ];
                            }

                        } catch (\Exception $e) {
                            $this->log("Error processing successful response for {$imageUrl}: ".$e->getMessage(), 'warning');
                            $results[] = [
                                'url' => $imageUrl,
                                'path' => null,
                                'status' => 'retry', // Most processing errors can be retried
                                'error' => $e->getMessage(),
                            ];
                        }
                    } else {
                        $error = $response['reason'] ?? 'Unknown error';
                        $errorMessage = $error instanceof \Exception ? $error->getMessage() : (string) $error;

                        // Classify error for retry logic
                        $shouldRetry = $this->shouldRetryError($errorMessage);

                        $this->log("Failed to download {$imageUrl}: {$errorMessage}".
                            ($shouldRetry ? ' (will retry)' : ' (permanent failure)'), 'warning');

                        $results[] = [
                            'url' => $imageUrl,
                            'path' => null,
                            'status' => $shouldRetry ? 'retry' : 'error',
                            'error' => $errorMessage,
                        ];
                    }
                }

            } catch (\Exception $e) {
                $this->log('Batch download error: '.$e->getMessage(), 'error');
            }

            // Small delay between batches to be respectful
            if ($chunkIndex < count($chunks) - 1) {
                usleep($this->config['import']['image_download_delay'] ?? 100000);
            }
        }

        // Handle retries for failed downloads
        $retryUrls = array_filter($results, fn ($r) => $r['status'] === 'retry');
        $maxRetries = $this->config['http']['retry_times'] ?? 3;

        if (! empty($retryUrls) && $maxRetries > 0) {
            $this->log('Retrying '.count($retryUrls).' failed downloads...');
            $retryResults = $this->retryFailedDownloads($retryUrls, $type, $maxRetries, $progressCallback);

            // Update results with retry outcomes
            foreach ($retryResults as $retryResult) {
                $originalIndex = array_search($retryResult['url'], array_column($results, 'url'));
                if ($originalIndex !== false) {
                    $results[$originalIndex] = $retryResult;
                }
            }
        }

        $successCount = count(array_filter($results, fn ($r) => $r['status'] === 'success'));
        $failedCount = count($results) - $successCount;

        $this->log("Batch download completed: {$successCount}/".count($imageUrls).' images downloaded successfully'.
            ($failedCount > 0 ? ", {$failedCount} failed" : ''));

        return $results;
    }

    /**
     * Retry failed downloads with exponential backoff
     */
    protected function retryFailedDownloads(array $failedResults, $type, $maxRetries, $progressCallback = null)
    {
        $retryResults = [];
        $retryUrls = array_map(fn ($r) => $r['url'], $failedResults);

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            if (empty($retryUrls)) {
                break;
            }

            $this->log("Retry attempt {$attempt}/{$maxRetries} for ".count($retryUrls).' images');

            // Exponential backoff: 1s, 2s, 4s, 8s...
            $backoffDelay = min(pow(2, $attempt - 1), 10); // Cap at 10 seconds
            if ($attempt > 1) {
                $this->log("Waiting {$backoffDelay}s before retry...");
                sleep($backoffDelay);
            }

            // Retry with smaller batch size to reduce load
            $retryBatchSize = max(1, intval(($this->config['import']['concurrent_downloads'] ?? 5) / 2));
            $chunks = array_chunk($retryUrls, $retryBatchSize);
            $currentAttemptResults = [];

            foreach ($chunks as $chunk) {
                $promises = [];

                foreach ($chunk as $imageUrl) {
                    try {
                        $promises[] = $this->asyncClient->getAsync($imageUrl, [
                            'timeout' => 30, // Longer timeout for retries
                            'connect_timeout' => 15,
                            'headers' => [
                                'User-Agent' => $this->config['http']['headers']['User-Agent'] ?? 'Mozilla/5.0',
                                'Accept' => 'image/webp,image/apng,image/*,*/*;q=0.8',
                                'Referer' => $this->baseUrl,
                            ],
                        ]);
                    } catch (\Exception $e) {
                        $this->log("Error creating retry promise for {$imageUrl}: ".$e->getMessage(), 'warning');
                    }
                }

                if (! empty($promises)) {
                    try {
                        $responses = Utils::settle($promises)->wait();

                        foreach ($responses as $index => $response) {
                            $imageUrl = $chunk[$index];

                            if ($response['state'] === 'fulfilled') {
                                $httpResponse = $response['value'];
                                $statusCode = $httpResponse->getStatusCode();

                                if ($statusCode >= 200 && $statusCode < 300) {
                                    $imageData = $httpResponse->getBody()->getContents();

                                    if (! empty($imageData) && $this->isValidImageData($imageData)) {
                                        $savedPath = $this->saveImageData($imageData, $imageUrl, $type);
                                        if ($savedPath) {
                                            $currentAttemptResults[] = [
                                                'url' => $imageUrl,
                                                'path' => $savedPath,
                                                'status' => 'success',
                                            ];
                                        } else {
                                            $currentAttemptResults[] = [
                                                'url' => $imageUrl,
                                                'path' => null,
                                                'status' => 'error',
                                                'error' => 'Failed to save image after retry',
                                            ];
                                        }
                                    } else {
                                        $currentAttemptResults[] = [
                                            'url' => $imageUrl,
                                            'path' => null,
                                            'status' => 'error',
                                            'error' => 'Invalid image data on retry',
                                        ];
                                    }
                                } else {
                                    $currentAttemptResults[] = [
                                        'url' => $imageUrl,
                                        'path' => null,
                                        'status' => 'error',
                                        'error' => "HTTP {$statusCode} on retry",
                                    ];
                                }
                            } else {
                                $error = $response['reason'] ?? 'Unknown error';
                                $currentAttemptResults[] = [
                                    'url' => $imageUrl,
                                    'path' => null,
                                    'status' => 'error',
                                    'error' => ($error instanceof \Exception ? $error->getMessage() : (string) $error).' (retry failed)',
                                ];
                            }
                        }

                    } catch (\Exception $e) {
                        $this->log('Retry batch error: '.$e->getMessage(), 'error');
                        // Mark all chunk URLs as failed
                        foreach ($chunk as $imageUrl) {
                            $currentAttemptResults[] = [
                                'url' => $imageUrl,
                                'path' => null,
                                'status' => 'error',
                                'error' => 'Retry batch failed: '.$e->getMessage(),
                            ];
                        }
                    }
                }

                // Small delay between retry chunks
                if (count($chunks) > 1) {
                    usleep(500000); // 500ms
                }
            }

            // Update retryResults with current attempt results
            foreach ($currentAttemptResults as $result) {
                $retryResults[$result['url']] = $result;
            }

            // Remove successful URLs from retry list for next attempt
            $retryUrls = array_filter($retryUrls, function ($url) use ($retryResults) {
                return ! isset($retryResults[$url]) || $retryResults[$url]['status'] !== 'success';
            });

            $successfulRetries = count(array_filter($currentAttemptResults, fn ($r) => $r['status'] === 'success'));
            $this->log("Retry attempt {$attempt} completed: {$successfulRetries} successful");
        }

        return array_values($retryResults);
    }

    /**
     * Determine if an error should be retried
     */
    protected function shouldRetryError($errorMessage)
    {
        $errorMessage = strtolower($errorMessage);

        // Network-related errors that can be retried
        $retryableErrors = [
            'timeout',
            'connection',
            'timed out',
            'network',
            'socket',
            'curl error',
            'ssl',
            'certificate',
            'reset',
            'aborted',
            'refused',
        ];

        foreach ($retryableErrors as $retryableError) {
            if (strpos($errorMessage, $retryableError) !== false) {
                return true;
            }
        }

        // HTTP status codes that can be retried (5xx server errors, 429 too many requests)
        if (preg_match('/http [5]\d{2}/', $errorMessage) || strpos($errorMessage, '429') !== false) {
            return true;
        }

        // Permanent errors that should not be retried
        $permanentErrors = [
            'not found',
            '404',
            'forbidden',
            '403',
            'unauthorized',
            '401',
            'bad request',
            '400',
        ];

        foreach ($permanentErrors as $permanentError) {
            if (strpos($errorMessage, $permanentError) !== false) {
                return false;
            }
        }

        // Default to retry for unknown errors
        return true;
    }

    /**
     * Clean and validate image URL
     */
    protected function cleanImageUrl($imageUrl)
    {
        $imageUrl = trim($imageUrl);

        // Handle protocol-relative URLs
        if (strpos($imageUrl, '//') === 0) {
            $imageUrl = 'https:'.$imageUrl;
        }

        // Fix common Yupoo URL issues
        if (strpos($imageUrl, 'x.yupoo.com/photo.yupoo.com') !== false) {
            $imageUrl = str_replace('x.yupoo.com/photo.yupoo.com', 'photo.yupoo.com', $imageUrl);
        }

        // Make sure URL is absolute
        if (strpos($imageUrl, 'http') !== 0) {
            $imageUrl = rtrim($this->baseUrl, '/').'/'.ltrim($imageUrl, '/');
        }

        return $imageUrl;
    }

    /**
     * Check if URL is valid for image download
     */
    protected function isValidImageUrl($imageUrl)
    {
        if (empty($imageUrl)) {
            return false;
        }

        // Skip known non-image URLs and placeholders
        if (preg_match('/(\.svg|logo|icon|loading|placeholder|spinner|notaccess|_no_photo|_empty|_default)/i', $imageUrl)) {
            return false;
        }

        // Must have image extension
        if (! preg_match('/\.(jpg|jpeg|png|gif|webp)(?:\?.*)?$/i', $imageUrl)) {
            return false;
        }

        return true;
    }

    /**
     * Validate image data
     */
    protected function isValidImageData($imageData)
    {
        if (empty($imageData)) {
            return false;
        }

        // Basic image validation
        $imageInfo = @getimagesizefromstring($imageData);
        if ($imageInfo === false) {
            return false;
        }

        $allowedTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];

        return in_array($imageInfo[2], $allowedTypes);
    }

    /**
     * Save image data to storage
     */
    protected function saveImageData($imageData, $imageUrl, $type = 'images')
    {
        try {
            // Determine storage directory
            if (is_numeric($type)) {
                $directory = 'images/'.$type;
            } else {
                $directory = $this->config['storage'][$type] ?? $type;
            }
            $directory = trim($directory, '/');

            // Generate filename
            $filename = $this->generateImageFilename($imageUrl);
            $relativePath = $directory.'/'.$filename;

            // Skip if already exists
            if (Storage::disk('s3')->exists($relativePath)) {
                return $relativePath;
            }

            // Ensure directory exists
            if (! Storage::disk('s3')->exists($directory)) {
                Storage::disk('s3')->makeDirectory($directory, 0755, true);
            }

            // Save the image to S3 using AWS SDK directly to avoid ACL issues
            $s3Client = new \Aws\S3\S3Client([
                'version' => 'latest',
                'region' => config('filesystems.disks.s3.region'),
                'credentials' => [
                    'key' => config('filesystems.disks.s3.key'),
                    'secret' => config('filesystems.disks.s3.secret'),
                ],
            ]);
            $bucket = config('filesystems.disks.s3.bucket');
            try {
                $s3Client->putObject([
                    'Bucket' => $bucket,
                    'Key' => $relativePath,
                    'Body' => $imageData,
                    'ContentType' => 'image/jpeg',
                ]);
                $saved = true;
            } catch (\Exception $s3Exception) {
                throw new \Exception('S3 direct upload failed: '.$s3Exception->getMessage());
            }

            if (! $saved) {
                throw new \Exception('Failed to save image to storage');
            }

            // Verify the file was saved
            if (! Storage::disk('s3')->exists($relativePath)) {
                throw new \Exception('File was not saved correctly');
            }

            return $relativePath;

        } catch (\Exception $e) {
            $this->log('Error saving image data: '.$e->getMessage(), 'error');

            return null;
        }
    }

    /**
     * Bulk insert images into database
     */
    public function bulkInsertImages(array $imageData, $albumId)
    {
        $batchSize = $this->config['import']['bulk_insert_size'] ?? 20;
        $chunks = array_chunk($imageData, $batchSize);
        $insertedCount = 0;

        foreach ($chunks as $chunk) {
            $insertData = [];

            foreach ($chunk as $image) {
                if (! empty($image['path']) && $image['status'] === 'success') {
                    $insertData[] = [
                        'album_id' => $albumId,
                        'title' => $this->cleanImageName($image['name'] ?? 'Image '.uniqid()),
                        'image_path' => $image['path'],
                        'description' => null,
                        'original_url' => $image['url'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if (! empty($insertData)) {
                $this->imageModel->insert($insertData);
                $insertedCount += count($insertData);
            }
        }

        $this->log("Bulk inserted {$insertedCount} images for album ID {$albumId}");

        return $insertedCount;
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
     * @param  array  $jsonData  The parsed JSON data from the page
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

                if ($found && is_array($current) && ! empty($current)) {
                    $albumData = $current;
                    $this->log('Found album data using path: '.implode(' -> ', $path), 'debug');
                    break;
                }
            }

            if (empty($albumData)) {
                $this->log('No album data found in JSON structure', 'debug');

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
                    if (! empty($album['url'])) {
                        if (strpos($album['url'], 'http') !== 0) {
                            $album['url'] = rtrim($this->baseUrl, '/').'/'.ltrim($album['url'], '/');
                        }
                        // Fix URLs with double /albums in the path
                        $album['url'] = preg_replace('|(/albums){2,}|', '/albums', $album['url']);
                    }

                    // Make sure cover image URL is absolute
                    if (! empty($album['cover_image']) && strpos($album['cover_image'], 'http') !== 0) {
                        $album['cover_image'] = rtrim($this->baseUrl, '/').'/'.ltrim($album['cover_image'], '/');
                    }

                    if (! empty($album['title'])) {
                        $albums[] = $album;
                        $this->log('Found album in JSON: '.$album['title'], 'debug');
                    }
                } catch (\Exception $e) {
                    $this->log('Error processing album item: '.$e->getMessage(), 'warning');

                    continue;
                }
            }

            $this->log(sprintf('Extracted %d albums from JSON data', count($albums)));

        } catch (\Exception $e) {
            $this->log('Error extracting albums from JSON: '.$e->getMessage(), 'error');
        }

        return $albums;
    }

    /**
     * Log a message
     */
    /**
     * Import albums with progress callback support
     *
     * @param  string|null  $baseUrl  The base Yupoo URL to import from
     * @param  int  $maxAlbums  Maximum number of albums to import (0 for no limit)
     * @param  callable|null  $progressCallback  Callback function for progress updates
     * @return array Import statistics
     */
    public function importAlbumsWithProgress($baseUrl = null, $maxAlbums = 0, $progressCallback = null)
    {
        return $this->importAlbums($baseUrl, $maxAlbums, $progressCallback);
    }

    /**
     * Import albums and their images from Yupoo
     *
     * @param  string|null  $baseUrl  The base Yupoo URL to import from
     * @param  int  $maxAlbums  Maximum number of albums to import (0 for no limit)
     * @param  callable|null  $progressCallback  Callback function for progress updates
     * @return array Import statistics
     */
    public function importAlbums($baseUrl = null, $maxAlbums = 0, $progressCallback = null)
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
            $this->log('Starting Yupoo import from: '.($baseUrl ?? $this->baseUrl));

            // Fetch all albums from Yupoo
            $albums = $this->fetchAlbums($baseUrl);

            if (empty($albums)) {
                $this->log('No albums found to import.', 'warning');

                return $stats;
            }

            $this->log(sprintf('Found %d albums to process', count($albums)));

            // Progress callback for album processing start
            if ($progressCallback) {
                $progressCallback('albums', 0, count($albums), 'Starting album processing...');
            }

            // Process each album
            foreach ($albums as $index => $albumData) {
                try {
                    // Check if we've reached the maximum number of albums to import
                    if ($maxAlbums > 0 && $stats['imported_albums'] >= $maxAlbums) {
                        $this->log("Reached maximum number of albums to import ($maxAlbums)", 'info');
                        break;
                    }

                    $stats['total_albums']++;

                    // Progress callback for current album
                    if ($progressCallback) {
                        $progressCallback('albums', $stats['total_albums'], count($albums),
                            'Processing: '.($albumData['title'] ?? 'Untitled Album'));
                    }

                    // Log the raw album data for debugging
                    $this->log('Raw album data: '.json_encode($albumData, JSON_UNESCAPED_UNICODE), 'debug');

                    // Extract album name and clean it (remove Chinese characters and extra spaces)
                    $albumName = $this->cleanAlbumName($albumData['title'] ?? 'Untitled Album');
                    $albumUrl = $albumData['url'] ?? null;

                    // Clean up the album URL to remove any duplicate /albums segments
                    if ($albumUrl) {
                        $albumUrl = preg_replace('|(/albums){2,}|', '/albums', $albumUrl);
                    }

                    $this->log(sprintf('Processing album %d/%d: %s (URL: %s)',
                        $stats['total_albums'],
                        count($albums),
                        $albumName,
                        $albumUrl
                    ));

                    if (! $albumUrl) {
                        $errorMsg = "Skipping album '{$albumName}': No URL provided";
                        $this->log($errorMsg, 'warning');
                        $stats['errors'][] = $errorMsg;
                        $stats['skipped_albums']++;

                        continue;
                    }

                    // Check if album already exists
                    $this->log('Checking if album exists: '.$albumName, 'debug');
                    $album = $this->albumModel->where('title', $albumName)->first();

                    $isNewAlbum = false;
                    if ($album) {
                        $this->log('Album already exists with ID: '.$album->id, 'debug');
                        $stats['skipped_albums']++;
                    } else {
                        // Create a default collection if none exists
                        $collection = $this->collectionModel->first();
                        if (! $collection) {
                            $collection = $this->collectionModel->create([
                                'name' => 'Default Collection',
                                'description' => 'Automatically created collection for imported albums',
                            ]);
                            $this->log('Created default collection: '.$collection->name, 'info');
                        }

                        // Create the album
                        $album = $this->albumModel->create([
                            'collection_id' => $collection->id,
                            'title' => $albumName,
                            'description' => null,
                            'cover_image' => null, // Will be set after importing images
                        ]);

                        $this->log('Created album: '.$album->title, 'info');
                        $stats['imported_albums']++;
                        $isNewAlbum = true;
                    }

                    // Now import images for this album using batch processing
                    $albumUrl = preg_replace('|(/albums){2,}|', '/albums', $albumData['url']);
                    $this->log("Processing album URL: $albumUrl", 'debug');

                    // Get images from the album (all pages)
                    $this->log("Fetching images for album: {$albumData['title']}", 'debug');
                    $albumImages = $this->fetchAlbumImages($albumUrl, $progressCallback);
                    $this->log('Found '.count($albumImages)." images across all pages in album: {$albumData['title']}", 'debug');

                    if (empty($albumImages)) {
                        $this->log('No images found in album: '.$album->title, 'warning');

                        continue;
                    }

                    $this->log(sprintf('Found %d images in album: %s',
                        count($albumImages),
                        $album->title
                    ));

                    // Extract image URLs for batch processing with error handling
                    $imageUrls = [];
                    try {
                        $imageUrls = array_map(function ($imageData) {
                            return $imageData['url'] ?? '';
                        }, $albumImages);

                        // Filter out empty URLs
                        $imageUrls = array_filter($imageUrls, function ($url) {
                            return ! empty($url) && is_string($url);
                        });

                    } catch (\Exception $e) {
                        $this->log('Error extracting image URLs: '.$e->getMessage(), 'warning');
                        $imageUrls = [];
                    }

                    if (empty($imageUrls)) {
                        $this->log('No valid image URLs found for album: '.$album->title, 'warning');

                        continue;
                    }

                    // Filter out duplicate images
                    $newImageUrls = $this->filterNewImages($imageUrls);
                    $duplicatesCount = count($imageUrls) - count($newImageUrls);

                    if ($duplicatesCount > 0) {
                        $this->log("Skipping {$duplicatesCount} duplicate images for album: ".$album->title);
                        $stats['skipped_images'] += $duplicatesCount;
                    }

                    if (empty($newImageUrls)) {
                        $this->log('No new images to download for album: '.$album->title);

                        continue;
                    }

                    // Batch download images
                    $this->log('Starting batch download of '.count($newImageUrls).' images');
                    $downloadResults = $this->downloadImagesBatch($newImageUrls, $album->id, $progressCallback);

                    // Prepare data for bulk insert
                    $imageDataForInsert = [];
                    $successfulDownloads = 0;
                    $failedDownloads = 0;

                    foreach ($downloadResults as $result) {
                        if ($result['status'] === 'success') {
                            // Find the original image data to get title/name
                            $originalImageData = null;
                            foreach ($albumImages as $img) {
                                if ($img['url'] === $result['url']) {
                                    $originalImageData = $img;
                                    break;
                                }
                            }

                            // Generate meaningful name if not found in original data
                            $imageName = $originalImageData['title'] ?? null;
                            if (empty($imageName) || $imageName === 'Imported from Yupoo') {
                                $imageName = $this->extractImageNameFromUrl($result['url']) ?? 'Image '.str_pad($successfulDownloads + 1, 3, '0', STR_PAD_LEFT);
                            }

                            $imageDataForInsert[] = [
                                'url' => $result['url'],
                                'path' => $result['path'],
                                'name' => $imageName,
                                'status' => 'success',
                            ];

                            $successfulDownloads++;
                        } else {
                            $errorMsg = sprintf('Failed to download image: %s - %s',
                                $result['url'],
                                $result['error'] ?? 'Unknown error'
                            );
                            $this->log($errorMsg, 'warning');
                            $stats['errors'][] = $errorMsg;
                            $failedDownloads++;
                        }
                    }

                    // Bulk insert successful images
                    if (! empty($imageDataForInsert)) {
                        $insertedCount = $this->bulkInsertImages($imageDataForInsert, $album->id);
                        $stats['imported_images'] += $insertedCount;

                        // Set album cover to first successfully imported image
                        if ($album->cover_image === null && ! empty($imageDataForInsert)) {
                            $album->update(['cover_image' => $imageDataForInsert[0]['path']]);
                        }

                        $this->log("Successfully imported {$insertedCount} images for album: ".$album->title);
                    }

                    if ($failedDownloads > 0) {
                        $this->log("Failed to download {$failedDownloads} images for album: ".$album->title, 'warning');
                    }

                } catch (\Exception $e) {
                    $errorMsg = sprintf('Error processing album %s: %s',
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
                'Import completed in %s seconds. Stats: %d albums processed (%d imported, %d skipped), %d images imported, %d images skipped',
                $totalTime,
                $stats['total_albums'],
                $stats['imported_albums'],
                $stats['skipped_albums'],
                $stats['imported_images'],
                $stats['skipped_images']
            ), 'info');

        } catch (\Exception $e) {
            $errorMsg = 'Fatal error during import: '.$e->getMessage();
            $this->log($errorMsg, 'error');
            $stats['errors'][] = $errorMsg;
        }

        return $stats;
    }

    /**
     * Clean album name by removing Chinese characters and extra spaces
     *
     * @param  string  $name
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
            $name = 'Untitled Album '.uniqid();
        }

        return $name;
    }

    /**
     * Extract images from JSON data
     *
     * @param  array  $jsonData  The JSON data to extract images from
     * @param  array  &$images  Reference to the images array to populate
     */
    protected function extractImagesFromJson($jsonData, &$images)
    {
        try {
            $this->log('Extracting images from JSON data', 'debug');

            // If the input is a string, try to decode it
            if (is_string($jsonData)) {
                $jsonData = json_decode($jsonData, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->log('Failed to decode JSON string: '.json_last_error_msg(), 'debug');

                    return;
                }
            }

            // If it's not an array at this point, we can't process it
            if (! is_array($jsonData) && ! is_object($jsonData)) {
                $this->log('JSON data is not an array or object', 'debug');

                return;
            }

            // Convert to array if it's an object
            if (is_object($jsonData)) {
                $jsonData = (array) $jsonData;
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
                ['data', 'album', 'photo_list'],
            ];

            // Also try direct access to common keys that might contain image data
            $directKeys = ['photo', 'image', 'img', 'picture', 'pic'];

            // First, try direct keys
            foreach ($directKeys as $key) {
                if (isset($jsonData[$key]) && is_string($jsonData[$key])) {
                    $imageName = $jsonData['title'] ?? $this->extractImageNameFromUrl($jsonData[$key]) ?? 'Image';
                    $this->processImageItem([
                        'url' => $jsonData[$key],
                        'title' => $imageName,
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
                    $items = is_object($current) ? (array) $current : $current;

                    $this->log(sprintf('Found %d items in JSON path: %s',
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

            $this->log(sprintf('Extracted %d images from JSON data', count($images)), 'debug');

        } catch (\Exception $e) {
            $this->log('Error extracting images from JSON: '.$e->getMessage(), 'error');
        }
    }

    /**
     * Process a single image item and add it to the images array
     *
     * @param  mixed  $item  The item to process (can be array or object)
     * @param  array  &$images  Reference to the images array to populate
     */
    protected function processImageItem($item, &$images)
    {
        try {
            if (is_string($item)) {
                // If the item is a string, treat it as a URL
                $imageName = $this->extractImageNameFromUrl($item) ?? 'Image';
                $this->addImageIfValid([
                    'url' => $item,
                    'title' => $imageName,
                ], $images);

                return;
            }

            // Convert to array if it's an object
            if (is_object($item)) {
                $item = (array) $item;
            }

            // If it's not an array at this point, we can't process it
            if (! is_array($item)) {
                return;
            }

            // Extract URL and title
            $imageUrl = null;
            $title = $item['title'] ?? null;

            // Try different possible URL fields
            $urlFields = [
                'url', 'imageUrl', 'src', 'image', 'photoUrl', 'photo_url',
                'img_src', 'imgUrl', 'image_src', 'original', 'original_url',
                'large', 'large_url', 'medium', 'medium_url', 'small', 'small_url',
                'thumb', 'thumbnail', 'thumbnail_url', 'thumb_url', 'img', 'pic', 'picture',
            ];

            foreach ($urlFields as $field) {
                if (! empty($item[$field]) && is_string($item[$field])) {
                    $imageUrl = $item[$field];
                    break;
                }
            }

            // If we found a URL, clean it up and add it to the images array
            if ($imageUrl) {
                // Generate meaningful title if not provided
                if (empty($title)) {
                    $title = $this->extractImageNameFromUrl($imageUrl) ?? 'Image';
                }

                $this->addImageIfValid([
                    'url' => $imageUrl,
                    'title' => $title,
                    'alt' => $item['alt'] ?? $item['description'] ?? $title,
                    'width' => $item['width'] ?? $item['img_width'] ?? null,
                    'height' => $item['height'] ?? $item['img_height'] ?? null,
                ], $images);
            }

        } catch (\Exception $e) {
            $this->log('Error processing image item: '.$e->getMessage(), 'warning');
        }
    }

    /**
     * Add an image to the images array if it's valid
     *
     * @param  array  $imageData  The image data to add
     * @param  array  &$images  Reference to the images array to populate
     */
    /**
     * Extract images from nested JSON structures recursively
     *
     * @param  mixed  $data  The data to search through
     * @param  array  &$images  Reference to the images array to populate
     * @param  int  $depth  Current recursion depth (to prevent infinite recursion)
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
                        $imageName = $this->extractImageNameFromUrl($value) ?? ucfirst($key) ?? 'Image';
                        $this->addImageIfValid([
                            'url' => $value,
                            'title' => $imageName,
                            'alt' => $key,
                        ], $images);
                    }
                }
            }
        }
    }

    /**
     * Add an image to the images array if it's valid
     *
     * @param  array  $imageData  The image data to add
     * @param  array  &$images  Reference to the images array to populate
     */
    protected function addImageIfValid($imageData, &$images)
    {
        $imageUrl = $imageData['url'] ?? '';
        $title = $imageData['title'] ?? $this->extractImageNameFromUrl($imageUrl) ?? 'Image';

        // Clean up the URL
        $imageUrl = trim($imageUrl);

        // Skip if URL is empty
        if (empty($imageUrl)) {
            return;
        }

        // Handle protocol-relative URLs
        if (strpos($imageUrl, '//') === 0) {
            $imageUrl = 'https:'.$imageUrl;
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
            $imageUrl = rtrim($this->baseUrl, '/').'/'.ltrim($imageUrl, '/');
        }

        $images[] = [
            'url' => $imageUrl,
            'title' => $title,
        ];

        $this->log("Added image: $imageUrl", 'debug');
    }

    /**
     * Clean image name
     *
     * @param  string  $name
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
     * Extract a meaningful image name from URL
     *
     * @param  string  $url  Image URL
     * @return string|null Extracted name or null if not extractable
     */
    protected function extractImageNameFromUrl($url)
    {
        if (empty($url)) {
            return null;
        }

        // Parse URL to get the path
        $path = parse_url($url, PHP_URL_PATH);
        if (empty($path)) {
            return null;
        }

        // Get filename without extension
        $filename = pathinfo($path, PATHINFO_FILENAME);

        // Skip if it's just a hash or meaningless ID
        if (preg_match('/^[a-f0-9]{8,}$/i', $filename) ||
            preg_match('/^(img|image|photo|pic)_?\d*$/i', $filename) ||
            strlen($filename) < 3) {
            return null;
        }

        // Clean up common Yupoo patterns
        $filename = preg_replace('/^(\d+)_[a-z0-9]+$/i', '$1', $filename);
        $filename = preg_replace('/_[a-z]$/', '', $filename);

        // Convert to readable format
        $name = str_replace(['_', '-', '.'], ' ', $filename);
        $name = preg_replace('/\s+/', ' ', $name);
        $name = trim($name);

        // Skip if still looks like a hash or ID
        if (preg_match('/^\d{8,}$/', str_replace(' ', '', $name))) {
            return null;
        }

        return ucwords($name);
    }

    /**
     * Extract image name from HTML context around the image element
     *
     * @param  Crawler  $node  Image node
     * @return string|null Extracted name or null if not found
     */
    protected function extractImageNameFromContext($node)
    {
        try {
            // Try alt text first (most reliable)
            $alt = $node->attr('alt');
            if (! empty($alt) && ! preg_match('/^\d+$/', $alt) && ! preg_match('/^(img|image|photo|pic)/i', $alt)) {
                return $this->cleanImageName($alt);
            }

            // Try title attribute
            $title = $node->attr('title');
            if (! empty($title) && ! preg_match('/^\d+$/', $title) && ! preg_match('/^(img|image|photo|pic)/i', $title)) {
                return $this->cleanImageName($title);
            }

            // Try to find text in parent elements
            $parent = $node;
            for ($i = 0; $i < 3; $i++) {
                try {
                    $parent = $parent->parents()->first();
                    if ($parent->count() === 0) {
                        break;
                    }

                    // Look for text in specific elements
                    $textElements = $parent->filter('h1, h2, h3, h4, h5, h6, .title, .name, .caption, p');
                    if ($textElements->count() > 0) {
                        $text = trim($textElements->first()->text());
                        if (! empty($text) && strlen($text) > 3 && strlen($text) < 100) {
                            // Skip if it looks like generic text
                            if (! preg_match('/^(image|photo|picture|img)/i', $text)) {
                                return $this->cleanImageName($text);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    break;
                }
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Generate a meaningful image name using multiple strategies
     *
     * @param  Crawler  $node  Image node
     * @param  string  $imageUrl  Image URL
     * @param  int  $index  Image index in album for fallback
     * @return string Generated name
     */
    protected function generateMeaningfulImageName($node, $imageUrl, $index = 1)
    {
        // Strategy 1: Extract from HTML context
        $nameFromContext = $this->extractImageNameFromContext($node);
        if (! empty($nameFromContext)) {
            return $nameFromContext;
        }

        // Strategy 2: Extract from URL
        $nameFromUrl = $this->extractImageNameFromUrl($imageUrl);
        if (! empty($nameFromUrl)) {
            return $nameFromUrl;
        }

        // Strategy 3: Use generic but meaningful name with index
        return 'Image '.str_pad($index, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Log a message with the specified log level
     *
     * @param  string  $message  The message to log
     * @param  string  $level  The log level (info, debug, warning, error)
     * @param  array  $context  Additional context data to include in the log
     */
    protected function log($message, $level = 'info', array $context = [])
    {
        // Suppress debug logs unless debug verbosity is enabled
        if (strtolower($level) === 'debug' && ! $this->debug) {
            return;
        }
        // Add timestamp and context to the message
        $timestamp = now()->toDateTimeString();

        // Convert context to string, handling large arrays
        $contextStr = '';
        if (! empty($context)) {
            if (is_array($context)) {
                // For large arrays, just show the keys
                if (count($context) > 5) {
                    $contextStr = ' '.json_encode(array_keys($context));
                } else {
                    $contextStr = ' '.json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                }
            } else {
                $contextStr = ' '.(string) $context;
            }
        }

        $logMessage = "[$timestamp] [YupooService][$level] $message$contextStr";

        // Log to Laravel's log file
        \Log::log($level, $logMessage);

        // If running in console, output to console
        if (app()->runningInConsole()) {
            // Use direct output if we can't get the output interface
            if (! app()->bound('console.output')) {
                echo "$logMessage\n";

                return;
            }

            try {
                $output = app('console.output');

                // Format the message for console output
                $formattedMessage = "<fg=blue>[$timestamp]</> <fg=cyan>[YupooService]</> ".
                                 "<fg=magenta>[$level]</> $message";

                // Add context if present
                if (! empty($context)) {
                    $formattedMessage .= "\n".json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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
                \Log::error('Error writing to console: '.$e->getMessage());
            }
        }
    }
}
