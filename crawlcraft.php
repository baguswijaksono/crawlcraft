<?php
class HttpClient {
    private $userAgent;

    public function __construct($userAgent = 'Mozilla/5.0') {
        $this->userAgent = $userAgent;
    }

    public function fetch($url) {
        $retry = 3;
        while ($retry > 0) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
            $output = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
    
            if ($httpCode == 200) {
                return $output;
            } else {
                $retry--;
                sleep(1); // Delay before retrying
            }
        }
        throw new Exception("Failed to fetch URL after retries: $url");
    }

}

class HtmlParser {
    private $dom;
    private $xpath;

    public function __construct($html) {
        $this->dom = new DOMDocument();
        @$this->dom->loadHTML($html);
        $this->xpath = new DOMXPath($this->dom);
    }

    public function extractLinks() {
        $links = [];
        $nodes = $this->xpath->query("//a[@href]");
        foreach ($nodes as $node) {
            $links[] = $node->getAttribute("href");
        }
        return $links;
    }

    public function extractTitle() {
        $nodes = $this->xpath->query("//title");
        return $nodes->length > 0 ? $nodes->item(0)->nodeValue : null;
    }

    public function extractMetaDescription() {
        $nodes = $this->xpath->query("//meta[@name='description']");
        return $nodes->length > 0 ? $nodes->item(0)->getAttribute("content") : null;
    }

    public function extractHeadings() {
        $headings = [];
        for ($i = 1; $i <= 6; $i++) {
            $nodes = $this->xpath->query("//h$i");
            foreach ($nodes as $node) {
                $headings[] = $node->nodeValue;
            }
        }
        return $headings;
    }

    public function extractImages() {
        $images = [];
        $nodes = $this->xpath->query("//img[@src]");
        foreach ($nodes as $node) {
            $images[] = $node->getAttribute("src");
        }
        return $images;
    }

    public function extractParagraphs() {
        $paragraphs = [];
        $nodes = $this->xpath->query("//p");
        foreach ($nodes as $node) {
            $paragraphs[] = $node->nodeValue;
        }
        return $paragraphs;
    }
}

class UrlManager {
    private $queue;
    private $visited;
    private $depth;
    private $maxDepth;

    public function __construct($maxDepth = 3) {
        $this->queue = [];
        $this->visited = [];
        $this->depth = [];
        $this->maxDepth = $maxDepth;
    }

    public function addUrl($url, $depth = 0) {
        if (!in_array($url, $this->visited) && !in_array($url, $this->queue) && $depth <= $this->maxDepth) {
            $this->queue[] = $url;
            $this->depth[$url] = $depth;
        }
    }

    public function getUrl() {
        return array_shift($this->queue);
    }

    public function getDepth($url) {
        return $this->depth[$url];
    }

    public function markAsVisited($url) {
        $this->visited[] = $url;
        unset($this->depth[$url]);
    }

    public function hasMoreUrls() {
        return !empty($this->queue);
    }
}

class DataStorage {
    private $file;

    public function __construct($filename) {
        $this->file = $filename;
    }

    public function save($data) {
        file_put_contents($this->file, json_encode($data, JSON_PRETTY_PRINT));
    }
}

class Logger {
    private $logFile;

    public function __construct($logFile) {
        $this->logFile = $logFile;
    }

    public function log($message) {
        file_put_contents($this->logFile, $message . PHP_EOL, FILE_APPEND);
    }
}

class CrawlCraft {
    private $httpClient;
    private $urlManager;
    private $dataStorage;
    private $logger;
    private $rateLimit;
    private $lastRequestTime;

    public function __construct($startUrl, $storageFile, $logFile, $rateLimit = 1, $userAgent = 'Mozilla/5.0') {
        $this->httpClient = new HttpClient($userAgent);
        $this->urlManager = new UrlManager();
        $this->dataStorage = new DataStorage($storageFile);
        $this->logger = new Logger($logFile);
        $this->rateLimit = $rateLimit;
        $this->lastRequestTime = microtime(true);
        $this->urlManager->addUrl($startUrl);
    }

    public function run() {
        while ($this->urlManager->hasMoreUrls()) {
            $currentTime = microtime(true);
            if ($currentTime - $this->lastRequestTime < $this->rateLimit) {
                usleep(($this->rateLimit - ($currentTime - $this->lastRequestTime)) * 1000000);
            }

            $url = $this->urlManager->getUrl();
            try {
                $html = $this->httpClient->fetch($url);
            } catch (Exception $e) {
                $this->logger->log("Failed to fetch URL: $url - " . $e->getMessage());
                continue;
            }
            $this->lastRequestTime = microtime(true);

            $parser = new HtmlParser($html);

            // Extract and save data (customize as needed)
            $data = [
                'url' => $url,
                'title' => $parser->extractTitle(),
                'meta_description' => $parser->extractMetaDescription(),
                'headings' => $parser->extractHeadings(),
                'images' => $parser->extractImages(),
                'paragraphs' => $parser->extractParagraphs()
            ];
            $this->dataStorage->save($data);

            // Extract and add new URLs to the queue
            $links = $parser->extractLinks();
            $currentDepth = $this->urlManager->getDepth($url);
            foreach ($links as $link) {
                // Convert relative URLs to absolute URLs
                $absoluteUrl = $this->convertToAbsoluteUrl($url, $link);
                $this->urlManager->addUrl($absoluteUrl, $currentDepth + 1);
            }

            $this->urlManager->markAsVisited($url);
        }
    }

    private function convertToAbsoluteUrl($baseUrl, $relativeUrl) {
        // Use parse_url and build_url to handle relative URLs
        $parsedUrl = parse_url($baseUrl);
        if (strpos($relativeUrl, "http") === 0) {
            return $relativeUrl;
        }
        if ($relativeUrl[0] === '/') {
            return "{$parsedUrl['scheme']}://{$parsedUrl['host']}{$relativeUrl}";
        }
        return "{$parsedUrl['scheme']}://{$parsedUrl['host']}/{$relativeUrl}";
    }
}
?>
