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
     * Get WP Toolkit security issues
     */
    public function get_wp_toolkit_security() {
        try {
            // First, get list of WordPress instances using the WP module
            $instances = $this->make_request('/WP/list_instances');

            // Check if WP module is not installed
            if (isset($instances['errors']) && is_array($instances['errors'])) {
                foreach ($instances['errors'] as $error) {
                    if (strpos($error, 'Failed to load module') !== false) {
                        return new \WP_Error(
                            'wp_toolkit_not_installed',
                            'WP Toolkit is not installed or accessible on this cPanel server'
                        );
                    }
                }
            }

            if (!isset($instances['data']) || !is_array($instances['data'])) {
                return new \WP_Error('no_instances', 'No WordPress installations found');
            }

            $security_issues = [];

            // Check each instance
            foreach ($instances['data'] as $instance) {
                $instance_id = $instance['id'];
                $domain = $instance['domain'];
                $path = $instance['path'];

                // Get security measures for this instance using WP module
                $security_response = $this->make_request(
                    '/WP/get_instance_security_status',
                    [
                        'instance_id' => $instance_id
                    ]
                );

                if (!isset($security_response['data']) || !is_array($security_response['data'])) {
                    continue;
                }

                // Check each security measure
                foreach ($security_response['data'] as $measure) {
                    $measure_name = $measure['name'] ?? 'unknown';
                    $measure_status = $measure['state'] ?? 'unknown';
                    $measure_title = $measure['title'] ?? $measure_name;
                    $measure_desc = $measure['description'] ?? '';

                    if ($measure_status !== 'active') {
                        $security_issues[] = [
                            'domain' => $domain,
                            'title' => $measure_title,
                            'status' => $measure_status,
                            'description' => $measure_desc,
                            'path' => $path
                        ];
                    }
                }
            }

            return $security_issues;

        } catch (\Exception $e) {
            return new \WP_Error('toolkit_api_error', $e->getMessage());
        }
    }

    /**
     * Make an API request to cPanel
     */
    private function make_request($endpoint, $query_params = []) {
        $url = $this->api_url . $endpoint;
        
        if (!empty($query_params)) {
            $url .= '?' . http_build_query($query_params);
        }

        $args = [
            'headers' => [
                'Authorization' => 'cpanel ' . $this->username . ':' . $this->token
            ],
            'timeout' => 30,
            'sslverify' => false
        ];

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        
        if (empty($body)) {
            throw new \Exception('Empty response from API');
        }

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response: ' . json_last_error_msg());
        }

        return $data;
    }
} 