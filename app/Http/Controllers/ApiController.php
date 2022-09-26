<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ApiController extends Controller
{
    private $currencyWikiUrl;

    public function __construct()
    {
        $this->currencyWikiUrl = 'https://pt.wikipedia.org/wiki/ISO_4217';
    }

    public function index(Request $request) {
        $currencyList = $this->getCurrencyList();
        $return = [];

        if ($request->code) {
            foreach ($currencyList as $currency) {
                if ($currency['code'] == $request->code) {
                    $return[] = $currency;
                }
            }
        }

        if ($request->number) {
            foreach ($currencyList as $currency) {
                if ($currency['number'] == $request->number) {
                    $return[] = $currency;
                }
            }
        }

        if ($request->code_list) {
            $codes = explode(',', $request->code_list);
            // print_r($codes);die;
            foreach($codes as $code) {
                foreach ($currencyList as $currency) {
                    if ($currency['code'] == trim($code)) {
                        $return[] = $currency;
                    }
                }
            }
        }

        if ($request->number_list) {
            $numbers = explode(',', $request->number_list);
            // print_r($numbers);die;
            foreach($numbers as $number) {
                foreach ($currencyList as $currency) {
                    if ($currency['number'] == trim($number)) {
                        $return[] = $currency;
                    }
                }
            }
        }

        return $return;
    }

    /**
     * Get currency list from Wikipedia
     * @return array
     */
    private function getCurrencyList()
    {
        $html = file_get_contents($this->currencyWikiUrl);
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $table = $xpath->query('//table[@class="wikitable sortable"]')->item(0);
        $rows = $xpath->query('.//tr', $table);
        $currencies = [];
        foreach ($rows as $row) {
            $cols = $xpath->query('.//td', $row);
            if ($cols->length > 0) {
                $currencies[] = [
                    'code' => $cols->item(0)->nodeValue,
                    'number' => $cols->item(1)->nodeValue,
                    'decimal' => $cols->item(2)->nodeValue,
                    'currency' => $cols->item(3)->nodeValue,
                    'currency_location' => $this->getCurrencyLocation($xpath, $cols->item(4)),
                ];
            }
        }
        return $currencies;
    }

    /**
     * Get the list of countries that use the currency
     * @param \DOMXPath $xpath
     * @param \DOMNode $node
     * @return string
     */
    private function getCurrencyLocation($xpath, $cols) {
        $listOfUrl = [];
        $urls = $xpath->query('.//a', $cols);

        foreach ($urls as $url) {
            $listOfUrl[] = [
                'location' => $url->getAttribute('title'),
                'icon' => $this->getIcon($url),
            ];
        }

        return $listOfUrl;
    }

    /**
     * Get the icon of the country
     * @param \DOMNode $node
     * @return string
     */
    private function getIcon($item) {
        if (isset($item->previousSibling)) {
            switch ($item->previousSibling->nodeName) {
                case 'span':
                    return urldecode($item->previousSibling->firstChild->getAttribute('src'));
                    break;
                case 'img':
                    return urldecode($item->previousSibling->getAttribute('src'));
                    break;
                case '#text':
                    return $this->getIcon($item->previousSibling);
                    break;
            }
        } else {
            return '';
        }
    }
}
