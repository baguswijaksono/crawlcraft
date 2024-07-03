<?php
class HttpClient {
    public function fetch($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
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

    public function __construct() {
        $this->queue = [];
        $this->visited = [];
    }

    public function addUrl($url) {
        if (!in_array($url, $this->visited) && !in_array($url, $this->queue)) {
            $this->queue[] = $url;
        }
    }

    public function getUrl() {
        return array_shift($this->queue);
    }

    public function markAsVisited($url) {
        $this->visited[] = $url;
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

class CrawlCraft {
    private $httpClient;
    private $urlManager;
    private $dataStorage;

    public function __construct($startUrl, $storageFile) {
        $this->httpClient = new HttpClient();
        $this->urlManager = new UrlManager();
        $this->dataStorage = new DataStorage($storageFile);
        $this->urlManager->addUrl($startUrl);
    }

    public function run() {
        while ($this->urlManager->hasMoreUrls()) {
            $url = $this->urlManager->getUrl();
            $html = $this->httpClient->fetch($url);
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
            foreach ($links as $link) {
                // Convert relative URLs to absolute URLs
                $absoluteUrl = $this->convertToAbsoluteUrl($url, $link);
                $this->urlManager->addUrl($absoluteUrl);
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
