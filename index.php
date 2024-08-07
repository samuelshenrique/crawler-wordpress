<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\TransferStats;
use Psr\Http\Message\ResponseInterface;

require __DIR__ . '/vendor/autoload.php';

// BUSCANDO SITEMAP
$url = 'http://localhost:8014/sitemap.xml';

$client = new Client();
$response = $client->get($url);

$xml = $response->getBody()->getContents();
$dados = simplexml_load_string($xml);
$dados = json_encode($dados);
$dados = json_decode($dados, true);

$linksSitemap = [];
if (
    !empty($dados)
    && (isset($dados['sitemap']) && !empty($dados['sitemap']))
) {
    foreach ($dados['sitemap'] as $dado) {
        $linksSitemap[] = $dado['loc'];
    }
}

$linksSitemapRemover = [
    'http://localhost:8014/polo-sitemap2.xml',
    'http://localhost:8014/polo-sitemap3.xml',
    'http://localhost:8014/polo-sitemap4.xml',
    'http://localhost:8014/polo-sitemap5.xml',
    'http://localhost:8014/polo-sitemap6.xml',
    'http://localhost:8014/polo-sitemap7.xml',
];

foreach ($linksSitemapRemover as $linkParaRemover) {
    if (array_search($linkParaRemover, $linksSitemap) !== false) {
        $chave = array_search($linkParaRemover, $linksSitemap);
        unset($linksSitemap[$chave]);
    }
}

$arrayLinks = [];
if (!empty($linksSitemap)) {
    $arraySitemapsPromises = [];
    foreach ($linksSitemap as $linkSitemap) {
        $arraySitemapsPromises[$linkSitemap] = $client->getAsync($linkSitemap);
    }

    $responses = Utils::unwrap($arraySitemapsPromises);

    /** @var ResponseInterface $response */
    foreach ($responses as $key => $response) {
        $xmlSitemapAtual = $response->getBody()->getContents();
        $dadosLinksSitemapAtual = simplexml_load_string($xmlSitemapAtual);
        $dadosLinksSitemapAtual = json_encode($dadosLinksSitemapAtual);
        $dadosLinksSitemapAtual = json_decode($dadosLinksSitemapAtual, true);

        echo "PRONTO | SITEMAP - " . $key . PHP_EOL;

        if (
            !empty($dadosLinksSitemapAtual)
            && (isset($dadosLinksSitemapAtual['url']) && !empty($dadosLinksSitemapAtual['url']))
        ) {
            foreach ($dadosLinksSitemapAtual['url'] as $dado) {
                if (isset($dado['loc']) && !empty($dado['loc'])) {
                    $arrayLinks[] = $dado['loc'];
                }
            }
        }
    }

    echo PHP_EOL;
    echo PHP_EOL;
    echo PHP_EOL;
    echo PHP_EOL;
    echo "--------------------FINALIZADO OS SITEMAPS--------------------";
    echo PHP_EOL;
    echo PHP_EOL;
    echo PHP_EOL;
    echo PHP_EOL;
}

if (!empty($arrayLinks)) {

    $requests = function() use ($client, $arrayLinks) {
        foreach ($arrayLinks as $link) {
            yield function () use ($client, $link) {
                echo 'CARREGANDO REQUISIÇÃO | ' . $link;
                echo PHP_EOL;
                return $client->getAsync($link);
            };
        }
    };

    
    $pool = new Pool($client, $requests(), [
        'concurrency' => 10,
        'fulfilled' => function (Response $response, $index) {
            if ($response->getStatusCode() == 200) {
                echo "REQUISIÇÃO BEM SUCEDIDA | " . $index;
                echo PHP_EOL;
                echo PHP_EOL;
            }
        },
        'rejected' => function (RequestException $reason, $index) {
            echo 'REQUISIÇÃO COM FALHA | ' . $index;
            echo PHP_EOL;
            echo PHP_EOL;
            echo $reason->getMessage();
        }
    ]);

    $pool->promise()->wait();
}
