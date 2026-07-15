<?php

/**
 * Manual API Testing Script
 * 
 * Ten skrypt pozwala na ręczne testowanie API bez uruchamiania pełnych testów PHPUnit.
 * Uruchom: php tests/Manual/ApiTestScript.php
 * 
 * Wymaga:
 * - Uruchomiony serwer Laravel: php artisan serve
 * - Utworzone użytkowniki i dane testowe w bazie
 */

class ApiTestScript
{
    private string $baseUrl;
    private ?string $token = null;
    private array $headers = [];

    public function __construct(string $baseUrl = 'http://localhost:8000')
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    public function register(string $email, string $password, string $name): array
    {
        $response = $this->post('/api/register', [
            'email' => $email,
            'password' => $password,
            'name' => $name,
        ]);

        if (isset($response['token'])) {
            $this->token = $response['token'];
            $this->headers['Authorization'] = 'Bearer ' . $this->token;
        }

        return $response;
    }

    public function login(string $email, string $password): array
    {
        $response = $this->post('/api/login', [
            'email' => $email,
            'password' => $password,
        ]);

        if (isset($response['token'])) {
            $this->token = $response['token'];
            $this->headers['Authorization'] = 'Bearer ' . $this->token;
        }

        return $response;
    }

    public function getActiveGames(?int $tournamentId = null): array
    {
        $url = '/api/game/active';
        if ($tournamentId) {
            $url .= '?tournamentId=' . $tournamentId;
        }
        return $this->get($url);
    }

    public function updateGame(array $gameData, array $achievements = [], array $legs = []): array
    {
        return $this->post('/api/game/update', [
            'game' => $gameData,
            'achievements' => $achievements,
            'legs' => $legs,
        ]);
    }

    private function get(string $endpoint): array
    {
        $ch = curl_init($this->baseUrl . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $this->buildHeaders(),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $httpCode,
            'data' => json_decode($response, true) ?? $response,
        ];
    }

    private function post(string $endpoint, array $data): array
    {
        $ch = curl_init($this->baseUrl . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $this->buildHeaders(),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $httpCode,
            'data' => json_decode($response, true) ?? $response,
        ];
    }

    private function buildHeaders(): array
    {
        $headers = [];
        foreach ($this->headers as $key => $value) {
            $headers[] = "$key: $value";
        }
        return $headers;
    }

    public function printResponse(string $title, array $response): void
    {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "  $title\n";
        echo str_repeat('=', 60) . "\n";
        echo "Status: {$response['status']}\n";
        echo "Response:\n";
        print_r($response['data']);
        echo "\n";
    }
}

// Przykład użycia
if (php_sapi_name() === 'cli') {
    echo "=== Manual API Test Script ===\n";
    echo "Upewnij się, że serwer Laravel jest uruchomiony: php artisan serve\n\n";

    $api = new ApiTestScript();

    // Przykład 1: Rejestracja użytkownika
    echo "1. Test rejestracji użytkownika...\n";
    $registerResponse = $api->register('test@example.com', 'password123', 'Test User');
    $api->printResponse('Rejestracja', $registerResponse);

    // Przykład 2: Logowanie
    echo "2. Test logowania...\n";
    $loginResponse = $api->login('test@example.com', 'password123');
    $api->printResponse('Logowanie', $loginResponse);

    // Przykład 3: Pobranie aktywnych gier (wymaga zalogowania i danych w bazie)
    if ($api->token) {
        echo "3. Test pobierania aktywnych gier...\n";
        $activeGamesResponse = $api->getActiveGames();
        $api->printResponse('Aktywne gry', $activeGamesResponse);
    }

    echo "\n=== Koniec testów ===\n";
    echo "\nMożesz modyfikować ten skrypt, aby testować różne scenariusze.\n";
}
