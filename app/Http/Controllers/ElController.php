<?php

namespace App\Http\Controllers;

use App\Services\GetMeteringData;
use App\Services\GetSpotPrices;
use ErrorException;
use Tvup\ElOverblikApi\ElOverblikApiException;

class ElController extends Controller
{
    const TOKEN_FILENAME = 'eloverblik-token.serialized';

    /**
     * @var GetMeteringData
     */
    private $meteringDataService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(GetMeteringData $meteringDataService)
    {
        $this->meteringDataService = $meteringDataService;
    }

    public function get($refreshToken = null)
    {
        return $this->getPreliminaryInvoice($refreshToken);
    }

    public function delete($refreshToken)
    {
        if($refreshToken == 'MIT_LÆKRE_TOKEN_HER') {
            return response('Hov :) Du fik vist ikke læst, hvad jeg skrev', 200)
                ->header('Content-Type', 'text/plain');
        }

        $path = storage_path() . '/refresh_tokens/' . md5($refreshToken) . '-' . self::TOKEN_FILENAME;
        if (file_exists($path)) {
            $result = unlink($path);
        } else {
            $result = false;
        }
        $response = $result ? response('Data access-token deleted', 200) : response('Data access-token not found', 404);
        return $response->header('Content-Type', 'text/plain');
    }

    /**
     * @return mixed
     */
    private function getPreliminaryInvoice($refreshToken = null)
    {
        if($refreshToken == 'MIT_LÆKRE_TOKEN_HER') {
            return response('Hov :) Du fik vist ikke læst, hvad jeg skrev', 200)
                ->header('Content-Type', 'text/plain');
        }

        try {
            $meterData = $this->meteringDataService->getData($refreshToken);
            $new = new GetSpotPrices();
            $prices = $new->getData();

            list($subscriptions, $tariffs) = $this->meteringDataService->getCharges($refreshToken);
        } catch (ElOverblikApiException $e) {
            return response($e->getMessage(), $e->getCode())
                ->header('Content-Type', 'text/plain');
        }

        $sum = 0;

        foreach ($meterData as $hour => $consumption) {
            //echo $hour . ': ' . $consumption . PHP_EOL;
            foreach ($tariffs as $tariff) {
                $theTarif = $tariff['prices'][0]['price'] * $consumption;
                //echo $tariff['name'] . ': ' . $theTarif . PHP_EOL;
                $sum = $sum + $theTarif;
            }

            $overhead = $consumption * 0.015;
            $spottotal = $consumption * ($prices[$hour]/1000);
            $sum = $sum + $overhead + $spottotal;
        }

        foreach ($subscriptions as $subscription) {
            $sum = $sum + $subscription['price'];
        }

        $elabonnement = 23.20;

        $sum = $sum + $elabonnement;

        $summaSummarum = $sum * 1.25;

        $summaSummarum = str_replace('.', ',', round($summaSummarum, 2));

        return response('kr. ' . $summaSummarum, 200)
            ->header('Content-Type', 'text/plain');
    }
}
