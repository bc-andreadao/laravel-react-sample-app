<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Http\Response;
use Inertia\Inertia;

use GuzzleHttp\Exception\RequestException;

use GuzzleHttp\Client;

class MainController extends BaseController
{
    protected $baseURL;

    public function __construct()
    {
        $this->baseURL = env('APP_URL');
    }

    public function getAppClientId()
    {
        if (env('APP_ENV') === 'local') {
            return env('BC_LOCAL_CLIENT_ID');
        } else {
            return env('BC_APP_CLIENT_ID');
        }
    }

    public function getAppSecret(Request $request)
    {
        if (env('APP_ENV') === 'local') {
            return env('BC_LOCAL_SECRET');
        } else {
            return env('BC_APP_SECRET');
        }
    }

    public function getAccessToken(Request $request)
    {
        if (env('APP_ENV') === 'local') {
            return env('BC_LOCAL_ACCESS_TOKEN');
        } else if ($request->session()->has('access_token')) {
            return $request->session()->get('access_token');
        } else {
            return env('BC_APP_ACCESS_TOKEN');
        }
    }

    public function getStoreHash(Request $request)
    {
        if (env('APP_ENV') === 'local') {
            return env('BC_LOCAL_STORE_HASH');
        } else if ($request->session()->has('store_hash')) {
            return $request->session()->get('store_hash');
        } else {
            return env('BC_APP_STORE_HASH');
        }
    }

    public function install(Request $request): Response|RedirectResponse
    {
        if (!$request->has('code') || !$request->has('scope') || !$request->has('context')) {
            return redirect()->action([MainController::class, 'error'], 
                ['error_message' => 'Not enough information was passed to install this app.']);
        }

        try {
            $client = new Client();
            $result = $client->request('POST', 'https://login.bigcommerce.com/oauth2/token', [
                'json' => [
                    'client_id' => $this->getAppClientId(),
                    'client_secret' => $this->getAppSecret($request),
                    'redirect_uri' => $this->baseURL . '/auth/install',
                    'grant_type' => 'authorization_code',
                    'code' => $request->input('code'),
                    'scope' => $request->input('scope'),
                    'context' => $request->input('context'),
                ]
            ]);

            $statusCode = $result->getStatusCode();
            $data = json_decode($result->getBody(), true);

            if ($statusCode == 200) {
                $request->session()->put('store_hash', $data['context']);
                $request->session()->put('access_token', $data['access_token']);
                $request->session()->put('user_id', $data['user']['id']);
                $request->session()->put('user_email', $data['user']['email']);

                // If the merchant installed the app via an external link, redirect back to the 
                // BC installation success page for this app
                if ($request->has('external_install')) {
                    return Redirect::to('https://login.bigcommerce.com/app/' . $this->getAppClientId() . '/install/succeeded');
                }

                // For control panel installations, render your app's page
                return Redirect::to('/');
            }
        } catch (RequestException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $errorMessage = "An error occurred.";

            if ($e->hasResponse()) {
                if ($statusCode != 500) {
                    $errorMessage = $e->getResponse();
                }
            }

            // If the merchant installed the app via an external link, redirect back to the 
            // BC installation failure page for this app
            if ($request->has('external_install')) {
                return Redirect::to('https://login.bigcommerce.com/app/' . $this->getAppClientId() . '/install/failed');
            } else {
                return redirect()->action([MainController::class, 'error'], ['error_message' => $errorMessage]);
            }
        }
    }

    public function load(Request $request): RedirectResponse
    {
        $signedPayload = $request->input('signed_payload');
        if (!empty($signedPayload)) {
            $verifiedSignedRequestData = $this->verifySignedRequest($signedPayload, $request);
            if ($verifiedSignedRequestData !== null) {
                $request->session()->put('user_id', $verifiedSignedRequestData['user']['id']);
                $request->session()->put('user_email', $verifiedSignedRequestData['user']['email']);
                $request->session()->put('owner_id', $verifiedSignedRequestData['owner']['id']);
                $request->session()->put('owner_email', $verifiedSignedRequestData['owner']['email']);
                $request->session()->put('store_hash', $verifiedSignedRequestData['context']);
                
                \Log::info('BigCommerce load successful', [
                    'store_hash' => $verifiedSignedRequestData['context'],
                    'user_id' => $verifiedSignedRequestData['user']['id']
                ]);
            } else {
                \Log::error('BigCommerce signed request validation failed');
                return redirect()->action([MainController::class, 'error'], ['error_message' => 'The signed request from BigCommerce could not be validated.']);
            }
        } else {
            \Log::error('Empty signed payload received');
            return redirect()->action([MainController::class, 'error'], ['error_message' => 'The signed request from BigCommerce was empty.']);
        }

        $request->session()->regenerate();

        return Redirect::to('/');
    }

    public function error(Request $request): Response
    {
        $errorMessage = "Internal Application Error";

        if ($request->has('error_message')) {
            $errorMessage = $request->input('error_message');
        }

        return response()->view('error', [
            'errorMessage' => $errorMessage,
            'baseURL' => $this->baseURL
        ]);
    }

    private function verifySignedRequest($signedRequest, $appRequest)
    {
        list($encodedData, $encodedSignature) = explode('.', $signedRequest, 2);

        // decode the data
        $signature = base64_decode($encodedSignature);
        $jsonStr = base64_decode($encodedData);
        $data = json_decode($jsonStr, true);

        // confirm the signature
        $expectedSignature = hash_hmac('sha256', $jsonStr, $this->getAppSecret($appRequest), $raw = false);
        if (!hash_equals($expectedSignature, $signature)) {
            error_log('Bad signed request from BigCommerce!');
            return null;
        }
        return $data;
    }

    public function makeBigCommerceAPIRequest(Request $request, $endpoint)
    {
        $requestConfig = [
            'headers' => [
                'X-Auth-Client' => $this->getAppClientId(),
                'X-Auth-Token'  => $this->getAccessToken($request),
                'Content-Type'  => 'application/json',
            ]
        ];

        if ($request->method() === 'PUT') {
            $requestConfig['body'] = $request->getContent();
        }

        $client = new Client();
        $queryString = $request->getQueryString() ? "?{$request->getQueryString()}" : '';
        $result = $client->request($request->method(), 'https://api.bigcommerce.com/' . $this->getStoreHash($request) .'/'. $endpoint . $queryString, $requestConfig);
        
        return $result;
    }

    public function proxyBigCommerceAPIRequest(Request $request, string $endpoint)
    {
        if (strrpos($endpoint, 'v2') !== false) {
            // For v2 endpoints, add a .json to the end of each endpoint, to normalize against the v3 API standards
            $endpoint .= '.json';
        }

        $result = $this->makeBigCommerceAPIRequest($request, $endpoint);
        
        $rateLimitHeaders = [
            'X-Rate-Limit-Time-Reset-Ms' => $result->getHeader('X-Rate-Limit-Time-Reset-Ms')[0] ?? null,
            'X-Rate-Limit-Time-Window-Ms' => $result->getHeader('X-Rate-Limit-Time-Window-Ms')[0] ?? null,
            'X-Rate-Limit-Requests-Left' => $result->getHeader('X-Rate-Limit-Requests-Left')[0] ?? null,
            'X-Rate-Limit-Requests-Quota' => $result->getHeader('X-Rate-Limit-Requests-Quota')[0] ?? null,
        ];

        return response($result->getBody(), $result->getStatusCode())
            ->header('Content-Type', 'application/json')
            ->withHeaders($rateLimitHeaders);
    }
}
