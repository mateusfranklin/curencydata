<?php

namespace App\Http\Controllers;

use App\Models\Currencies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ApiController extends Controller
{
    //URL of list
    private $currencyWikiUrl;

    public function __construct()
    {
        $this->currencyWikiUrl = 'https://pt.wikipedia.org/wiki/ISO_4217';
    }

    public function index(Request $request)
    {
        $currencyList = $this->getCurrencyList();
        $data = $request->all();
        $allRequests = [];
        $return = [];

        foreach ($data as $key => $code) {
            switch ($key) {
                case 'code':
                    foreach ($currencyList as $currency) {
                        if ($currency['code'] == $data['code']) {
                            $return[] = $currency;
                        }
                    }
                    array_push($allRequests, $data['code']);
                    break;
                case 'code_list':
                    $code_list = array_map('trim', explode(',', $data['code_list']));
                    foreach ($code_list as $code) {
                        foreach ($currencyList as $currency) {
                            if ($currency['code'] == $code) {
                                $return[] = $currency;
                            }
                        }
                    }
                    array_push($allRequests, $code_list);
                    break;
                case 'number':
                    foreach ($currencyList as $currency) {
                        if ($currency['number'] == $data['number']) {
                            $return[] = $currency;
                        }
                    }
                    array_push($allRequests, $data['number']);
                    break;
                case 'number_list':
                    $number_list = array_map('trim', explode(',', $data['number_list']));
                    foreach ($number_list as $number) {
                        foreach ($currencyList as $currency) {
                            if ($currency['number'] == $number) {
                                $return[] = $currency;
                            }
                        }
                    }
                    array_push($allRequests, $number_list);
                    break;
            }
        }
        if (!empty($return)) {
            $currenciesData = new Currencies();
            $currenciesData->codes = serialize($allRequests);
            $currenciesData->result = serialize($return);
            $currenciesData->save();
        }

        return $return;
    }

    /**
     * Get currency list from Wikipedia
     * @return array
     */
    private function getCurrencyList()
    {
        $cachedList = Cache::get('currencyList');
        if ($cachedList) {
            return $cachedList;
        } else {
            $html = file_get_contents($this->currencyWikiUrl);
            $dom = new \DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new \DOMXPath($dom);
            $table = $xpath
                ->query('//table[@class="wikitable sortable"]')
                ->item(0);
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
                        'currency_location' => $this->getCurrencyLocation(
                            $xpath,
                            $cols->item(4)
                        ),
                    ];
                }
            }

            Cache::put('currencyList', $currencies, 600);

            return $currencies;
        }
    }

    /**
     * Get the list of countries that use the currency
     * @param \DOMXPath $xpath
     * @param \DOMNode $node
     * @return string
     */
    private function getCurrencyLocation($xpath, $cols)
    {
        $listOfUrl = [];
        $urls = $xpath->query('.//a', $cols);

        foreach ($urls as $url) {
            if (!empty($url->getAttribute('title'))) {
                $listOfUrl[] = [
                    'location' => $url->getAttribute('title'),
                    'icon' => $this->getIcon($url),
                ];
            }
        }
        return $listOfUrl;
    }

    /**
     * Get the icon of the country
     * @param \DOMNode $node
     * @return string
     */
    private function getIcon($item)
    {
        if (isset($item->previousSibling)) {
            switch ($item->previousSibling->nodeName) {
                case 'span':
                    return urldecode(
                        $item->previousSibling->firstChild->getAttribute('src')
                    );
                    break;
                case 'img':
                    return urldecode(
                        $item->previousSibling->getAttribute('src')
                    );
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
