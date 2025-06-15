<?php

namespace App\Services\api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\Session;
use Exception;

class ErpApiService
{
    private Client $client;
    private string $apiUrl;
    private array $headers;

    public function __construct()
    {
        $this->client = new Client();
        $this->apiUrl = env('ERP_API_URL', 'http://erpnext.localhost:8000');
        
        $sid = Session::get('frappe_sid');
        $this->headers = $sid ? 
            ['Cookie' => 'sid=' . $sid, 'Accept' => 'application/json'] :
            [
                'Authorization' => 'token ' . env('ERP_API_KEY') . ':' . env('ERP_API_SECRET'),
                'Accept' => 'application/json'
            ];
    }

    public function getResource(string $resource, array $params = []): array
    {
        try {
            $query = $this->prepareQueryParams($params);
            $response = $this->client->get("{$this->apiUrl}/api/resource/{$resource}", [
                'headers' => $this->headers,
                'query' => $query
            ]);
            
            $data = json_decode($response->getBody(), true)['data'] ?? [];
            return is_array($data) ? $data : [$data];
        } catch (GuzzleException $e) {
            throw new Exception("Failed to fetch resource {$resource}: " . $e->getMessage());
        }
    }

    public function updateResource(string $resource, array $data): bool
    {
        try {
            $response = $this->client->put("{$this->apiUrl}/api/resource/{$resource}", [
                'headers' => $this->headers,
                'json' => $data
            ]);
            
            return $response->getStatusCode() === 200;
        } catch (GuzzleException $e) {
            throw new Exception("Failed to update resource {$resource}: " . $e->getMessage());
        }
    }

    public function resourceExists(string $resource): bool
    {
        try {
            $response = $this->client->get("{$this->apiUrl}/api/resource/{$resource}", [
                'headers' => $this->headers
            ]);
            return $response->getStatusCode() === 200;
        } catch (GuzzleException) {
            return false;
        }
    }


    public function createResource(string $resource, array $data): bool
    {
        try {
            $response = $this->client->post("{$this->apiUrl}/api/resource/{$resource}", [
                'headers' => $this->headers,
                'json' => $data
            ]);
            
            return $response->getStatusCode() === 200;
        } catch (GuzzleException $e) {
            throw new Exception("Failed to create resource {$resource}: " . $e->getMessage());
        }
    }


    public function deleteResource(string $resource): bool
    {
        try {
            $response = $this->client->delete("{$this->apiUrl}/api/resource/{$resource}", [
                'headers' => $this->headers
            ]);
            
            return $response->getStatusCode() === 202;
        } catch (GuzzleException $e) {
            throw new Exception("Failed to delete resource {$resource}: " . $e->getMessage());
        }
    }

    public function getResourceByName(string $resource, string $name): ?array
    {
        try {
            $response = $this->client->get("{$this->apiUrl}/api/resource/{$resource}/{$name}", [
                'headers' => $this->headers
            ]);
            
            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody(), true)['data'] ?? null;
            }
            
            return null;
        } catch (GuzzleException $e) {
            return null;
        }
    }


    public function executeMethod(string $resource, string $method, array $data = []): array
    {
        try {
            $response = $this->client->post("{$this->apiUrl}/api/method/{$resource}.{$method}", [
                'headers' => $this->headers,
                'json' => $data
            ]);
            
            return json_decode($response->getBody(), true) ?? [];
        } catch (GuzzleException $e) {
            throw new Exception("Failed to execute method {$method} on {$resource}: " . $e->getMessage());
        }

    }


    private function prepareQueryParams(array $params): array
    {
        return array_map(function ($value) {
            return is_array($value) ? json_encode($value) : $value;
        }, $params);
    }
}