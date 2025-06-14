<?php

class DLMSearchIlcorsaronero
{


    private $wurl = 'https://ilcorsaronero.link/';
    private $qurl = 'search?q=%s';

    public function __construct()
    {
        $this->qurl = $this->wurl . $this->qurl;
    }

    public function prepare($curl, $query)
    {
        $url = sprintf($this->qurl, urlencode($query));

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_FAILONERROR => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_REFERER => $this->wurl,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
    }


    public function parse($plugin, $response)
    {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        @$dom->loadHTML($response);
        $xpath = new DOMXPath($dom);

        $rows = $xpath->query("//table//tbody/tr");
        $res = 0;

        foreach ($rows as $row) {
            $titleNode = $xpath->query(".//th//a", $row)->item(0);
            if (!$titleNode) {
                echo "[WARN] No title found, skipping row\n";
                continue;
            }

            $cells = $xpath->query(".//td", $row);
            if ($cells->length < 6) {
                echo "[WARN] Unexpected number of <td>, got {$cells->length}, skipping row\n";
                continue;
            }

            $category = trim($cells->item(0)->nodeValue);
            $title = trim($titleNode->nodeValue);
            $page = 'https://ilcorsaronero.link' . $titleNode->getAttribute('href');
            $seeds = (int)trim($cells->item(1)->nodeValue);
            $leechs = (int)trim($cells->item(2)->nodeValue);
            $sizeText = trim($cells->item(3)->nodeValue);
            $datetime = trim($cells->item(4)->nodeValue);

            // Convert size to bytes
            if (stripos($sizeText, 'GiB') !== false) {
                $size = floatval($sizeText) * 1024 * 1024 * 1024;
            } elseif (stripos($sizeText, 'MiB') !== false) {
                $size = floatval($sizeText) * 1024 * 1024;
            } else {
                $size = 0;
            }

            // Fetch magnet link from detail page
            $curl = curl_init($page);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_URL => $page,
                CURLOPT_USERAGENT => 'Mozilla/5.0',
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $detailHtml = curl_exec($curl);
            curl_close($curl);

            preg_match('/href="(magnet:\?xt=urn:btih:[^"]+)"/', $detailHtml, $magnetMatch);
            $download = $magnetMatch[1] ?? '';

            // Normalize date to yyyy-mm-dd
            $datetime = date('Y-m-d', strtotime($datetime));
            $hash = md5($title);

            $plugin->addResult($title, $download, $size, $datetime, $page, $hash, $seeds, $leechs, $category);
            $res++;
        }

        return $res;
    }

}

?>
