<?php

/**
 * Third-Party Services Integration
 *
 * This integration manages connections to various third-party aviation services:
 * - Airline reservation systems (Amadeus, Sabre, Travelport)
 * - Ground handling service providers
 * - Catering and onboard services
 * - Fuel management systems
 * - Aircraft maintenance tracking
 * - Crew scheduling systems
 * - Customs and border protection
 * - Airport security systems
 */

class ThirdPartyServicesIntegration
{
    private $serviceProviders;
    private $apiCredentials;
    private $logger;
    private $cache;

    public function __construct()
    {
        $this->initializeServiceProviders();
        $this->loadApiCredentials();
        $this->initializeLogger();
        $this->initializeCache();
    }

    /**
     * Initialize service provider configurations
     */
    private function initializeServiceProviders()
    {
        $this->serviceProviders = [
            'amadeus' => [
                'name' => 'Amadeus',
                'base_url' => getenv('AMADEUS_API_URL') ?: 'https://api.amadeus.com',
                'auth_url' => 'https://api.amadeus.com/v1/security/oauth2/token',
                'endpoints' => [
                    'flight_search' => '/v2/shopping/flight-offers',
                    'booking' => '/v1/booking/flight-orders',
                    'pnr_retrieve' => '/v1/booking/flight-orders/{id}',
                    'seatmap' => '/v1/shopping/seatmaps'
                ]
            ],
            'sabre' => [
                'name' => 'Sabre',
                'base_url' => getenv('SABRE_API_URL') ?: 'https://api.sabre.com',
                'auth_url' => 'https://api.sabre.com/v2/auth/token',
                'endpoints' => [
                    'flight_search' => '/v3.2.0/shop/flights',
                    'booking' => '/v3.2.0/book/flights',
                    'pnr_retrieve' => '/v3.2.0/book/pnrs/{id}',
                    'seatmap' => '/v3.2.0/shop/seatmaps'
                ]
            ],
            'ground_handling' => [
                'name' => 'Ground Handling Services',
                'base_url' => getenv('GROUND_HANDLING_API_URL') ?: 'https://api.groundhandling.local',
                'endpoints' => [
                    'services' => '/v1/services',
                    'booking' => '/v1/bookings',
                    'status' => '/v1/status/{flight_id}',
                    'crew' => '/v1/crew/{flight_id}'
                ]
            ],
            'fuel_management' => [
                'name' => 'Fuel Management System',
                'base_url' => getenv('FUEL_API_URL') ?: 'https://api.fuel.local',
                'endpoints' => [
                    'pricing' => '/v1/pricing',
                    'orders' => '/v1/orders',
                    'inventory' => '/v1/inventory',
                    'delivery' => '/v1/delivery/{flight_id}'
                ]
            ],
            'catering' => [
                'name' => 'Catering Services',
                'base_url' => getenv('CATERING_API_URL') ?: 'https://api.catering.local',
                'endpoints' => [
                    'menu' => '/v1/menu',
                    'orders' => '/v1/orders',
                    'special_meals' => '/v1/special-meals',
                    'inventory' => '/v1/inventory'
                ]
            ],
            'maintenance' => [
                'name' => 'Aircraft Maintenance',
                'base_url' => getenv('MAINTENANCE_API_URL') ?: 'https://api.maintenance.local',
                'endpoints' => [
                    'schedule' => '/v1/schedule',
                    'work_orders' => '/v1/work-orders',
                    'parts' => '/v1/parts',
                    'certificates' => '/v1/certificates/{aircraft_id}'
                ]
            ],
            'crew_scheduling' => [
                'name' => 'Crew Scheduling System',
                'base_url' => getenv('CREW_API_URL') ?: 'https://api.crew.local',
                'endpoints' => [
                    'availability' => '/v1/crew/availability',
                    'assignments' => '/v1/assignments',
                    'fatigue' => '/v1/fatigue/{crew_id}',
                    'training' => '/v1/training/{crew_id}'
                ]
            ],
            'customs' => [
                'name' => 'Customs & Border Protection',
                'base_url' => getenv('CUSTOMS_API_URL') ?: 'https://api.customs.local',
                'endpoints' => [
                    'clearance' => '/v1/clearance',
                    'passengers' => '/v1/passengers',
                    'cargo' => '/v1/cargo',
                    'violations' => '/v1/violations'
                ]
            ]
        ];
    }

    /**
     * Load API credentials from environment
     */
    private function loadApiCredentials()
    {
        $this->apiCredentials = [
            'amadeus' => [
                'client_id' => getenv('AMADEUS_CLIENT_ID'),
                'client_secret' => getenv('AMADEUS_CLIENT_SECRET'),
                'access_token' => null,
                'expires_at' => null
            ],
            'sabre' => [
                'client_id' => getenv('SABRE_CLIENT_ID'),
                'client_secret' => getenv('SABRE_CLIENT_SECRET'),
                'access_token' => null,
                'expires_at' => null
            ],
            'ground_handling' => [
                'api_key' => getenv('GROUND_HANDLING_API_KEY'),
                'secret' => getenv('GROUND_HANDLING_SECRET')
            ],
            'fuel_management' => [
                'api_key' => getenv('FUEL_API_KEY'),
                'secret' => getenv('FUEL_SECRET')
            ],
            'catering' => [
                'api_key' => getenv('CATERING_API_KEY'),
                'secret' => getenv('CATERING_SECRET')
            ],
            'maintenance' => [
                'api_key' => getenv('MAINTENANCE_API_KEY'),
                'secret' => getenv('MAINTENANCE_SECRET')
            ],
            'crew_scheduling' => [
                'api_key' => getenv('CREW_API_KEY'),
                'secret' => getenv('CREW_SECRET')
            ],
            'customs' => [
                'api_key' => getenv('CUSTOMS_API_KEY'),
                'secret' => getenv('CUSTOMS_SECRET')
            ]
        ];
    }

    /**
     * Initialize logger
     */
    private function initializeLogger()
    {
        $this->logger = new Logger('third_party_services');
        $this->logger->pushHandler(new StreamHandler(__DIR__ . '/../logs/third_party_services.log', Logger::INFO));
    }

    /**
     * Initialize cache
     */
    private function initializeCache()
    {
        $this->cache = new Redis();
        $this->cache->connect(getenv('REDIS_HOST') ?: 'localhost', getenv('REDIS_PORT') ?: 6379);
        if ($password = getenv('REDIS_PASSWORD')) {
            $this->cache->auth($password);
        }
    }

    /**
     * Authenticate with Amadeus API
     */
    private function authenticateAmadeus()
    {
        if ($this->isTokenValid('amadeus')) {
            return $this->apiCredentials['amadeus']['access_token'];
        }

        $response = $this->makeApiRequest(
            $this->serviceProviders['amadeus']['auth_url'],
            'POST',
            [
                'grant_type' => 'client_credentials',
                'client_id' => $this->apiCredentials['amadeus']['client_id'],
                'client_secret' => $this->apiCredentials['amadeus']['client_secret']
            ],
            [],
            false
        );

        if ($response['status'] === 'success') {
            $this->apiCredentials['amadeus']['access_token'] = $response['data']['access_token'];
            $this->apiCredentials['amadeus']['expires_at'] = time() + $response['data']['expires_in'];
            return $response['data']['access_token'];
        }

        throw new Exception("Amadeus authentication failed: " . $response['message']);
    }

    /**
     * Authenticate with Sabre API
     */
    private function authenticateSabre()
    {
        if ($this->isTokenValid('sabre')) {
            return $this->apiCredentials['sabre']['access_token'];
        }

        $response = $this->makeApiRequest(
            $this->serviceProviders['sabre']['auth_url'],
            'POST',
            [
                'grant_type' => 'client_credentials',
                'client_id' => $this->apiCredentials['sabre']['client_id'],
                'client_secret' => $this->apiCredentials['sabre']['client_secret']
            ],
            [],
            false
        );

        if ($response['status'] === 'success') {
            $this->apiCredentials['sabre']['access_token'] = $response['data']['access_token'];
            $this->apiCredentials['sabre']['expires_at'] = time() + $response['data']['expires_in'];
            return $response['data']['access_token'];
        }

        throw new Exception("Sabre authentication failed: " . $response['message']);
    }

    /**
     * Check if API token is still valid
     */
    private function isTokenValid($provider)
    {
        return isset($this->apiCredentials[$provider]['access_token']) &&
               isset($this->apiCredentials[$provider]['expires_at']) &&
               time() < ($this->apiCredentials[$provider]['expires_at'] - 300); // 5 minutes buffer
    }

    /**
     * Search flights using airline reservation systems
     */
    public function searchFlights($searchParams)
    {
        $this->logger->info("Searching flights", $searchParams);

        $results = [];

        // Try Amadeus first
        try {
            $amadeusResults = $this->searchAmadeusFlights($searchParams);
            $results['amadeus'] = $amadeusResults;
        } catch (Exception $e) {
            $this->logger->warning("Amadeus search failed: " . $e->getMessage());
        }

        // Try Sabre as backup
        try {
            $sabreResults = $this->searchSabreFlights($searchParams);
            $results['sabre'] = $sabreResults;
        } catch (Exception $e) {
            $this->logger->warning("Sabre search failed: " . $e->getMessage());
        }

        return $results;
    }

    /**
     * Search flights on Amadeus
     */
    private function searchAmadeusFlights($params)
    {
        $token = $this->authenticateAmadeus();
        $endpoint = $this->serviceProviders['amadeus']['base_url'] .
                   $this->serviceProviders['amadeus']['endpoints']['flight_search'];

        $queryParams = [
            'originLocationCode' => $params['origin'],
            'destinationLocationCode' => $params['destination'],
            'departureDate' => $params['departure_date'],
            'returnDate' => $params['return_date'] ?? null,
            'adults' => $params['passengers'] ?? 1,
            'currencyCode' => $params['currency'] ?? 'USD'
        ];

        $response = $this->makeApiRequest($endpoint, 'GET', $queryParams, [
            'Authorization: Bearer ' . $token
        ]);

        if ($response['status'] === 'success') {
            return $this->normalizeFlightResults($response['data'], 'amadeus');
        }

        throw new Exception("Amadeus flight search failed: " . $response['message']);
    }

    /**
     * Search flights on Sabre
     */
    private function searchSabreFlights($params)
    {
        $token = $this->authenticateSabre();
        $endpoint = $this->serviceProviders['sabre']['base_url'] .
                   $this->serviceProviders['sabre']['endpoints']['flight_search'];

        $queryParams = [
            'origin' => $params['origin'],
            'destination' => $params['destination'],
            'departuredate' => $params['departure_date'],
            'returndate' => $params['return_date'] ?? null,
            'passengercount' => $params['passengers'] ?? 1
        ];

        $response = $this->makeApiRequest($endpoint, 'GET', $queryParams, [
            'Authorization: Bearer ' . $token
        ]);

        if ($response['status'] === 'success') {
            return $this->normalizeFlightResults($response['data'], 'sabre');
        }

        throw new Exception("Sabre flight search failed: " . $response['message']);
    }

    /**
     * Normalize flight results from different providers
     */
    private function normalizeFlightResults($data, $provider)
    {
        $normalized = [];

        foreach ($data as $flight) {
            $normalized[] = [
                'provider' => $provider,
                'flight_number' => $flight['flight_number'] ?? $flight['flightNumber'],
                'airline' => $flight['airline'] ?? $flight['carrierCode'],
                'origin' => $flight['origin'] ?? $flight['departure']['iataCode'],
                'destination' => $flight['destination'] ?? $flight['arrival']['iataCode'],
                'departure_time' => $flight['departure_time'] ?? $flight['departure']['at'],
                'arrival_time' => $flight['arrival_time'] ?? $flight['arrival']['at'],
                'duration' => $flight['duration'] ?? $flight['totalDuration'],
                'price' => $flight['price'] ?? $flight['price']['total'],
                'currency' => $flight['currency'] ?? $flight['price']['currency'],
                'available_seats' => $flight['available_seats'] ?? $flight['numberOfBookableSeats']
            ];
        }

        return $normalized;
    }

    /**
     * Book flight through third-party system
     */
    public function bookFlight($bookingData, $provider = 'amadeus')
    {
        $this->logger->info("Booking flight via $provider", ['flight' => $bookingData['flight_number']]);

        switch ($provider) {
            case 'amadeus':
                return $this->bookAmadeusFlight($bookingData);
            case 'sabre':
                return $this->bookSabreFlight($bookingData);
            default:
                throw new Exception("Unsupported booking provider: $provider");
        }
    }

    /**
     * Book flight on Amadeus
     */
    private function bookAmadeusFlight($data)
    {
        $token = $this->authenticateAmadeus();
        $endpoint = $this->serviceProviders['amadeus']['base_url'] .
                   $this->serviceProviders['amadeus']['endpoints']['booking'];

        $response = $this->makeApiRequest($endpoint, 'POST', $data, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]);

        if ($response['status'] === 'success') {
            return [
                'status' => 'confirmed',
                'pnr' => $response['data']['id'],
                'booking_reference' => $response['data']['associatedRecords'][0]['reference'] ?? null
            ];
        }

        throw new Exception("Amadeus booking failed: " . $response['message']);
    }

    /**
     * Book flight on Sabre
     */
    private function bookSabreFlight($data)
    {
        $token = $this->authenticateSabre();
        $endpoint = $this->serviceProviders['sabre']['base_url'] .
                   $this->serviceProviders['sabre']['endpoints']['booking'];

        $response = $this->makeApiRequest($endpoint, 'POST', $data, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]);

        if ($response['status'] === 'success') {
            return [
                'status' => 'confirmed',
                'pnr' => $response['data']['pnr'],
                'booking_reference' => $response['data']['locator'] ?? null
            ];
        }

        throw new Exception("Sabre booking failed: " . $response['message']);
    }

    /**
     * Get ground handling services
     */
    public function getGroundHandlingServices($flightId)
    {
        $endpoint = $this->serviceProviders['ground_handling']['base_url'] .
                   $this->serviceProviders['ground_handling']['endpoints']['services'];

        $response = $this->makeApiRequest($endpoint, 'GET', ['flight_id' => $flightId], [
            'X-API-Key: ' . $this->apiCredentials['ground_handling']['api_key']
        ]);

        if ($response['status'] === 'success') {
            return $response['data'];
        }

        throw new Exception("Ground handling services request failed: " . $response['message']);
    }

    /**
     * Book ground handling services
     */
    public function bookGroundHandlingService($serviceData)
    {
        $endpoint = $this->serviceProviders['ground_handling']['base_url'] .
                   $this->serviceProviders['ground_handling']['endpoints']['booking'];

        $response = $this->makeApiRequest($endpoint, 'POST', $serviceData, [
            'X-API-Key: ' . $this->apiCredentials['ground_handling']['api_key'],
            'Content-Type: application/json'
        ]);

        if ($response['status'] === 'success') {
            return $response['data'];
        }

        throw new Exception("Ground handling booking failed: " . $response['message']);
    }

    /**
     * Get fuel pricing and availability
     */
    public function getFuelPricing($airport, $aircraftType, $quantity = null)
    {
        $endpoint = $this->serviceProviders['fuel_management']['base_url'] .
                   $this->serviceProviders['fuel_management']['endpoints']['pricing'];

        $params = [
            'airport' => $airport,
            'aircraft_type' => $aircraftType
        ];

        if ($quantity) {
            $params['quantity'] = $quantity;
        }

        $response = $this->makeApiRequest($endpoint, 'GET', $params, [
            'X-API-Key: ' . $this->apiCredentials['fuel_management']['api_key']
        ]);

        if ($response['status'] === 'success') {
            return $response['data'];
        }

        throw new Exception("Fuel pricing request failed: " . $response['message']);
    }

    /**
     * Order aircraft fuel
     */
    public function orderFuel($orderData)
    {
        $endpoint = $this->serviceProviders['fuel_management']['base_url'] .
                   $this->serviceProviders['fuel_management']['endpoints']['orders'];

        $response = $this->makeApiRequest($endpoint, 'POST', $orderData, [
            'X-API-Key: ' . $this->apiCredentials['fuel_management']['api_key'],
            'Content-Type: application/json'
        ]);

        if ($response['status'] === 'success') {
            return $response['data'];
        }

        throw new Exception("Fuel order failed: " . $response['message']);
    }

    /**
     * Get catering menu and options
     */
    public function getCateringMenu($flightClass, $specialRequirements = [])
    {
        $endpoint = $this->serviceProviders['catering']['base_url'] .
                   $this->serviceProviders['catering']['endpoints']['menu'];

        $params = ['class' => $flightClass];

        if (!empty($specialRequirements)) {
            $params['special_requirements'] = implode(',', $specialRequirements);
        }

        $response = $this->makeApiRequest($endpoint, 'GET', $params, [
            'X-API-Key: ' . $this->apiCredentials['catering']['api_key']
        ]);

        if ($response['status'] === 'success') {
            return $response['data'];
        }

        throw new Exception("Catering menu request failed: " . $response['message']);
    }

    /**
     * Order catering services
     */
    public function orderCatering($orderData)
    {
        $endpoint = $this->serviceProviders['catering']['base_url'] .
                   $this->serviceProviders['catering']['endpoints']['orders'];

        $response = $this->makeApiRequest($endpoint, 'POST', $orderData, [
            'X-API-Key: ' . $this->apiCredentials['catering']['api_key'],
            'Content-Type: application/json'
        ]);

        if ($response['status'] === 'success') {
            return $response['data'];
        }

        throw new Exception("Catering order failed: " . $response['message']);
    }

    /**
     * Get aircraft maintenance schedule
     */
    public function getMaintenanceSchedule($aircraftId)
    {
        $endpoint = $this->serviceProviders['maintenance']['base_url'] .
                   $this->serviceProviders['maintenance']['endpoints']['schedule'];

        $response = $this->makeApiRequest($endpoint, 'GET', ['aircraft_id' => $aircraftId], [
            'X-API-Key: ' . $this->apiCredentials['maintenance']['api_key']
        ]);

        if ($response['status'] === 'success') {
            return $response['data'];
        }

        throw new Exception("Maintenance schedule request failed: " . $response['message']);
    }

    /**
     * Get crew availability
     */
    public function getCrewAvailability($date, $route, $position)
    {
        $endpoint = $this->serviceProviders['crew_scheduling']['base_url'] .
                   $this->serviceProviders['crew_scheduling']['endpoints']['availability'];

        $params = [
            'date' => $date,
            'route' => $route,
            'position' => $position
        ];

        $response = $this->makeApiRequest($endpoint, 'GET', $params, [
            'X-API-Key: ' . $this->apiCredentials['crew_scheduling']['api_key']
        ]);

        if ($response['status'] === 'success') {
            return $response['data'];
        }

        throw new Exception("Crew availability request failed: " . $response['message']);
    }

    /**
     * Check customs clearance status
     */
    public function checkCustomsClearance($flightId, $passengerList = [])
    {
        $endpoint = $this->serviceProviders['customs']['base_url'] .
                   $this->serviceProviders['customs']['endpoints']['clearance'];

        $data = ['flight_id' => $flightId];

        if (!empty($passengerList)) {
            $data['passengers'] = $passengerList;
        }

        $response = $this->makeApiRequest($endpoint, 'POST', $data, [
            'X-API-Key: ' . $this->apiCredentials['customs']['api_key'],
            'Content-Type: application/json'
        ]);

        if ($response['status'] === 'success') {
            return $response['data'];
        }

        throw new Exception("Customs clearance check failed: " . $response['message']);
    }

    /**
     * Make API request with error handling and caching
     */
    private function makeApiRequest($url, $method = 'GET', $data = null, $headers = [], $useCache = true)
    {
        // Check cache first
        $cacheKey = md5($url . serialize($data));
        if ($useCache && $cached = $this->cache->get($cacheKey)) {
            return json_decode($cached, true);
        }

        $ch = curl_init();

        // Build URL with query parameters for GET requests
        if ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => array_merge([
                'Content-Type: application/json',
                'User-Agent: Flight-Control-System/1.0'
            ], $headers)
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            return ['status' => 'error', 'message' => $error];
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            $result = [
                'status' => 'success',
                'data' => json_decode($response, true),
                'http_code' => $httpCode
            ];

            // Cache successful responses for 5 minutes
            if ($useCache) {
                $this->cache->setex($cacheKey, 300, json_encode($result));
            }

            return $result;
        } else {
            return [
                'status' => 'error',
                'message' => "HTTP $httpCode: $response",
                'http_code' => $httpCode
            ];
        }
    }

    /**
     * Get service provider status
     */
    public function getServiceStatus()
    {
        $status = [];

        foreach ($this->serviceProviders as $key => $provider) {
            $status[$key] = [
                'name' => $provider['name'],
                'status' => 'unknown',
                'last_check' => null
            ];

            try {
                // Simple health check - try to access base URL
                $response = $this->makeApiRequest($provider['base_url'] . '/health', 'GET', [], [], false);

                if ($response['status'] === 'success' ||
                    ($response['http_code'] >= 200 && $response['http_code'] < 500)) {
                    $status[$key]['status'] = 'operational';
                } else {
                    $status[$key]['status'] = 'degraded';
                }
            } catch (Exception $e) {
                $status[$key]['status'] = 'down';
            }

            $status[$key]['last_check'] = date('Y-m-d H:i:s');
        }

        return $status;
    }

    /**
     * Comprehensive health check
     */
    public function healthCheck()
    {
        $health = [
            'overall_status' => 'healthy',
            'services' => $this->getServiceStatus(),
            'checks' => []
        ];

        // Check cache connectivity
        try {
            $this->cache->ping();
            $health['checks']['cache'] = ['status' => 'healthy'];
        } catch (Exception $e) {
            $health['checks']['cache'] = ['status' => 'unhealthy', 'error' => $e->getMessage()];
            $health['overall_status'] = 'unhealthy';
        }

        // Check API credentials
        foreach ($this->apiCredentials as $service => $creds) {
            if (empty($creds['api_key'] ?? $creds['client_id'] ?? null)) {
                $health['checks'][$service . '_credentials'] = ['status' => 'missing'];
                $health['overall_status'] = 'unhealthy';
            } else {
                $health['checks'][$service . '_credentials'] = ['status' => 'configured'];
            }
        }

        return $health;
    }
}

// Usage examples
/*
$thirdPartyServices = new ThirdPartyServicesIntegration();

// Search flights
$searchResults = $thirdPartyServices->searchFlights([
    'origin' => 'JFK',
    'destination' => 'LAX',
    'departure_date' => '2025-01-15',
    'passengers' => 2
]);

// Book flight
$bookingResult = $thirdPartyServices->bookFlight([
    'flight_number' => 'AA123',
    'passengers' => [...],
    'payment' => [...]
], 'amadeus');

// Get ground handling services
$groundServices = $thirdPartyServices->getGroundHandlingServices('AA123');

// Get fuel pricing
$fuelPricing = $thirdPartyServices->getFuelPricing('JFK', 'B737', 5000);

// Get catering menu
$cateringMenu = $thirdPartyServices->getCateringMenu('economy', ['vegetarian', 'gluten-free']);

// Check service status
$serviceStatus = $thirdPartyServices->getServiceStatus();

// Health check
$healthStatus = $thirdPartyServices->healthCheck();
*/
