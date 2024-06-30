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

