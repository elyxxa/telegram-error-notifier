<?php
namespace Webkonsulenterne\TelegramErrorNotifier;

use GuzzleHttp\Exception\RequestException;

class CloudflareApi {
    private $settings;
    private $zone_id;
    private $client;
    private $api_base = 'https://api.cloudflare.com/client/v4/';

    public function __construct() {
        $this->settings = Settings::get_instance();
        $api_key = $this->settings->get('cloudflare_api_key', '');
        
        $this->client = new \GuzzleHttp\Client([
            'base_uri' => $this->api_base,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ],
        ]);

		if (empty($api_key)) {
			return;
		}

        $this->zone_id = $this->getZoneIdFromZoneName();
    }

    private function getZoneIdFromZoneName() {
        $zoneName = $this->remove_www(get_site_url());
        try {
            $response = $this->client->request('GET', 'zones', [
                'query' => ['name' => $zoneName]
            ]);

            $zones = json_decode($response->getBody()->getContents(), true);
            return isset($zones['result'][0]['id']) ? $zones['result'][0]['id'] : null;
        } catch (RequestException $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Error fetching zone ID: ' . $e->getMessage());
            }
            return null;
        }
    }

    private function remove_www($url) {
        $parsed_url = wp_parse_url($url);
        $host = $parsed_url['host'];
        return preg_replace('/^www\./', '', $host);
    }

    public function isCacheReserveEnabled() {
        if (!$this->zone_id) {
            return false;
        }

        $endpoint = "zones/{$this->zone_id}/cache/cache_reserve";
        
        try {
            $response = $this->client->request('GET', $endpoint);
            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['success']) && $data['success'] === true) {
                return $data['result']['value'] ?? false;
            }
        } catch (RequestException $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Error checking Cache Reserve status: ' . $e->getMessage());
            }
        }

        return false;
    }

    public function isUnderAttackMode() {
        if (!$this->zone_id) {
            return false;
        }

        $endpoint = "zones/{$this->zone_id}/settings/security_level";
        
        try {
            $response = $this->client->request('GET', $endpoint);
            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['success']) && $data['success'] === true) {
                return $data['result']['value'] === 'under_attack';
            }
        } catch (RequestException $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Error checking Under Attack Mode status: ' . $e->getMessage());
            }
        }

        return false;
    }

    public function validateApiKey() {
        try {
            $response = $this->client->request('GET', 'user/tokens/verify');
            return isset($response['success']) && $response['success'] === true;
        } catch (RequestException $e) {
            return false;
        }
    }
}
