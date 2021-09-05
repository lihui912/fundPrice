#!/usr/bin/php
<?php
date_default_timezone_set('Asia/Kuala_Lumpur');
bcscale(8);

$linkTemplates = [];
$linkTemplates['fundPage'] = 'https://www.website.com/?fund=%s';
$linkTemplates['priceHistory'] = 'https://www.website.com/priceHistory?fund=%s';
$linkTemplates['fundReturn'] = 'https://www.website.com/?fundReturn=%s&paramFromDate=%s&paramToDate=%s';

$fundList = require('fundList.php');

// start
echo "Please wait..." . date('Y-m-d H:i:s') . PHP_EOL;

$priceListList = generatePriceListLink($linkTemplates, $fundList);
$latestPriceList = getPriceList($priceListList);
$outputText = generateOutput($latestPriceList);
writeOutputFile($outputText);
echo $outputText;
// end

function generatePriceListLink(array $linkTemplates = [], array $fundList = []): array {
    $fundPriceList = [];

    $today = new DateTime();
    $today->setTime(0, 0);

    $previousDay = clone $today;
    $previousDay->sub(new DateInterval('P1D'));

    foreach($fundList as $fund) {
        $fundPriceLink = sprintf($linkTemplates['priceHistory'], $fund['code']);
        $fundRefererLink = sprintf($linkTemplates['fundPage'], $fund['code']);
        $fundPriceList[$fund['code']] = ['priceLink' => $fundPriceLink, 'referer' => $fundRefererLink];
    }

    return $fundPriceList;
}

function getPriceList(array $linkList = []) {
    global $linkTemplates;
    $latestPriceList = [];
    foreach($linkList as $name => $thisLink) {
        $priceData = downloadLink($thisLink['priceLink'], $thisLink['referer']);
        $jsonObject = parseJson($priceData);

        $latestPriceList[$name] = getLatestPrice($jsonObject);
        
        $fundReturnLink = sprintf($linkTemplates['fundReturn'], $jsonObject[0]['fundid'], $jsonObject[1]['showDate'], $jsonObject[0]['showDate']);
        
        $returnData = downloadLink($fundReturnLink, $thisLink['referer']);
        $returnObject = parseJson($returnData);

        $latestPriceList[$name]['diff'] = [
            'fromDate' => $returnObject['fromDate'],
            'toDate' => $returnObject['toDate'],
            'fundReturns' => $returnObject['fundReturns'],
        ];
    }

    return $latestPriceList;
}

function downloadLink(string $url = '', string $referer = ''): string {
    usleep(random_int(1000, 1000000));

    $curl = curl_init();

    $urlComponents = parse_url($url);
    $origin = $urlComponents['scheme'] . '://' . $urlComponents['host'];

    if(true === empty($referer)) {
        curl_setopt($curl, CURLOPT_AUTOREFERER, true);
    } else {
        curl_setopt($curl, CURLOPT_REFERER, $referer);
    }
    curl_setopt($curl, CURLOPT_DNS_SHUFFLE_ADDRESSES, true);
    curl_setopt($curl, CURLOPT_POST, true);

    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => [
            'accept: application/json, text/plain, */*',
            'accept-encoding: gzip, deflate, br',
            'accept-language: en-US,en;q=0.9',
            'authority: ' . $urlComponents['host'],
            'cache-control: no-cache',
            'Content-Type: application/json',
            'origin: ' . $origin,
            'pragma: no-cache',
        ],
        CURLOPT_USERAGENT => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/93.0.4577.63 Safari/537.36',
      ]
    );

    $response = curl_exec($curl);

    unset($curl);

    return $response;
}

function parseJson(string $data = ''): array {
    $json = json_decode($data, true);
    return $json['data'];
}

function getLatestPrice(array $data): array {
    $result = [];
    $result['price'] = $data[0]['bidPrice'];
    $result['last'] = $data[1]['bidPrice'];
    $result['timestamp'] = $data[0]['showDate'];
    $result['name'] = $data[0]['fundName'];
    
    $date = new DateTime();
    $date->setTimezone(new DateTimeZone('Asia/Kuala_Lumpur'));
    $date->setTimestamp($data[0]['showDate'] / 1000);
    $result['date'] = $date->format('Y/m/d');
    
    $result['diff'] = diffPercentage($data[0]['bidPrice'], $data[1]['bidPrice']);

    return $result;
}

function diffPercentage(float $latestPrice = 0, float $previousPrice = 0): string {
    return bcmul(bcsub(bcdiv((string)$latestPrice, (string)$previousPrice), '1'), '100', 4);
}

function generateOutput(array $latestPriceList = []): string {
    $output = '';
    $output .= sprintf("%12s  %8s  %8s  %6s  %10s  %s" . PHP_EOL, 'Code', 'Price', 'Last', 'Diff%', 'Date', 'Name');
    foreach($latestPriceList as $code => $data) {
        $output .= sprintf("%12s  %8s  %8s  %6s  %10s  %s" . PHP_EOL, $code, $data['price'], $data['last'], $data['diff']['fundReturns'], $data['date'], $data['name']);
    }

    return $output;
}

function writeOutputFile(string $text = ''): void {
    $file = fopen(__DIR__ . '/' . date('Ymd') . '.txt', 'w');
    
    fwrite($file, $text);

    fwrite($file, PHP_EOL . 'Retrived at: ' . date('YmdHis') . PHP_EOL);
    
    fclose($file);
}