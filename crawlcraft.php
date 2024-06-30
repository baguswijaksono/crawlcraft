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

    // Add other methods to extract specific data
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
            $data = ['url' => $url, 'content' => $html];
            $this->dataStorage->save($data);

            // Extract and add new URLs to the queue
            $links = $parser->extractLinks();
            foreach ($links as $link) {
                $this->urlManager->addUrl($link);
            }

            $this->urlManager->markAsVisited($url);
        }
    }
}


