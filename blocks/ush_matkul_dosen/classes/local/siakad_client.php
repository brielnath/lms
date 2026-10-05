<?php
namespace block_ush_matkul_dosen\local;

defined('MOODLE_INTERNAL') || die();

use moodle_exception;

/**
 * Minimal SIAKAD REST client.
 */
class siakad_client {
    /** @var string */
    private $baseurl;
    /** @var string|null */
    private $token = null;

    public function __construct() {
        $this->baseurl = rtrim((string) (get_config('block_ush_matkul_dosen', 'apiurl')
            ?: 'https://siakad.sugenghartono.ac.id/api'), '/');
    }

    public function login(): void {
        $email = (string) (get_config('block_ush_matkul_dosen', 'apiemail') ?: getenv('SIAKAD_EMAIL'));
        $password = (string) (get_config('block_ush_matkul_dosen', 'apipassword') ?: getenv('SIAKAD_PASSWORD'));
        if ($email === '' || $password === '') {
            throw new moodle_exception('error_nocredentials', 'block_ush_matkul_dosen');
        }
        [$http, $json] = $this->request('/login', ['email' => $email, 'password' => $password]);
        $token = $json['token'] ?? $json['access_token'] ?? $json['data']['token'] ?? $json['data']['access_token'] ?? null;
        if (!$token) {
            throw new moodle_exception('error_login', 'block_ush_matkul_dosen', '', $http);
        }
        $this->token = $token;
    }

    /**
     * GET a JSON endpoint, retrying a few times on HTTP 429.
     *
     * @return array{0: int, 1: array|null}
     */
    public function get(string $path): array {
        for ($attempt = 0; ; $attempt++) {
            [$http, $json] = $this->request($path);
            if ($http !== 429 || $attempt >= 5) {
                return [$http, $json];
            }
            sleep(10);
        }
    }

    /**
     * @return array{0: int, 1: array|null}
     */
    private function request(string $path, ?array $post = null): array {
        $headers = ['Accept: application/json'];
        if ($this->token) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }
        $ch = curl_init($this->baseurl . $path);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 90,
        ];
        if ($post !== null) {
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($post);
        }
        $options[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $json = is_string($raw) ? json_decode($raw, true) : null;
        return [$http, is_array($json) ? $json : null];
    }
}
