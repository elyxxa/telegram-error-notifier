<?php
namespace Webkonsulenterne\TelegramErrorNotifier;

class CpanelAPI {
    private $hostname;
    private $username;
    private $token;
    private $api_url;

    public function __construct($hostname, $username, $token) {
        $this->hostname = $hostname;
        $this->username = $username;
        $this->token = $token;
        $this->api_url = "https://{$hostname}:2083/execute";
    }

    /**
     * Get resource usage statistics from cPanel
     * 
     * @return array|WP_Error Array of usage stats or WP_Error on failure
     */
    public function get_resource_usage() {
        try {
            // Get disk usage
            $disk_response = $this->make_request('/Quota/get_quota_info');
            
            if (is_wp_error($disk_response)) {
                return $disk_response;
            }

            // Process disk usage
            if (isset($disk_response['data'])) {
                $megabytes_used = floatval($disk_response['data']['megabytes_used']);
                $megabyte_limit = floatval($disk_response['data']['megabyte_limit']);
                $inodes_used = intval($disk_response['data']['inodes_used']);
                $inode_limit = intval($disk_response['data']['inode_limit']);

                // Calculate percentages safely
                $disk_percentage = ($megabyte_limit > 0) ? ($megabytes_used / $megabyte_limit) * 100 : 0;
                $inode_percentage = ($inode_limit > 0) ? ($inodes_used / $inode_limit) * 100 : 0;

                return [
                    'disk' => [
                        'used' => $megabytes_used * 1024 * 1024, // Convert MB to bytes
                        'limit' => $megabyte_limit * 1024 * 1024,
                        'percentage' => $disk_percentage
                    ],
                    'inodes' => [
                        'used' => $inodes_used,
                        'limit' => $inode_limit,
                        'percentage' => $inode_percentage
                    ]
                ];
            }

            return new \WP_Error('cpanel_api_error', 'Invalid response format');

        } catch (\Exception $e) {
            return new \WP_Error('cpanel_api_error', $e->getMessage());
        }
    }

    /**
     * Make an API request to cPanel
     */
    private function make_request($endpoint) {
        $args = [
            'headers' => [
                'Authorization' => 'cpanel ' . $this->username . ':' . $this->token
            ],
            'timeout' => 30,
            'sslverify' => false  // Note: Enable in production if possible
        ];

        $url = $this->api_url . $endpoint;
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Making cPanel API request to: ' . $url);
            error_log('Request args: ' . print_r($args, true));
        }

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return new \WP_Error(
                'cpanel_api_error', 
                'API request failed with status code: ' . $status_code
            );
        }

        $body = wp_remote_retrieve_body($response);
        
        if (empty($body)) {
            return new \WP_Error('cpanel_api_error', 'Empty response from API');
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('cPanel API response: ' . $body);
        }

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error(
                'cpanel_api_error', 
                'Invalid JSON response: ' . json_last_error_msg()
            );
        }

        return $data;
    }
} 