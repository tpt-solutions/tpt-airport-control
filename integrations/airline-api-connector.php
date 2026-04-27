<?php
/**
 * Airline API Connector
 *
 * Integrates with major airline GDS systems (Amadeus, Sabre)
 * Handles flight searches, bookings, schedules, and fare information
 */

class AirlineAPIConnector
{
    private $db;
    private $logger;
    private $apiKeys;
    private $cache;

    public function __construct($database, $logger, $apiKeys = [])
    {
        $this->db = $database;
        $this->logger = $logger;
        $this->apiKeys = $apiKeys;
        $this->cache = [];
    }

    /**
     * Search for flights
     */
    public function searchFlights($searchParams, $provider = 'auto')
    {
        try {
            $cacheKey = 'flight_search_' . md5(json_encode($searchParams));
            if (isset($this->cache[$cacheKey]) &&
                (time() - $this->cache[$cacheKey]['timestamp']) < 300) {
                return $this->cache[$cacheKey]['data'];
            }

            $results = null;

            switch ($provider) {
                case 'amadeus':
                    $results = $this->searchAmadeus($searchParams);
                    break;
                case 'sabre':
                    $results = $this->searchSabre($searchParams);
                    break;
                case 'auto':
                default:
                    $results = $this->searchAuto($searchParams);
                    break;
            }

            if ($results) {
                $this->cache[$cacheKey] = [
                    'timestamp' => time(),
                    'data' => $results
                ];

                // Store search results in database
                $this->storeFlightSearch($searchParams, $results);

                return $results;
            }

            return ['flights' => [], 'error' => 'No results found'];

        } catch (Exception $e) {
            $this->logger->error("Flight search failed", ['error' => $e->getMessage(), 'params' => $searchParams]);
            return ['flights' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * Search flights using Amadeus API
     */
    private function searchAmadeus($params)
    {
        if (!isset($this->apiKeys['amadeus'])) {
            throw new Exception("Amadeus API credentials not configured");
        }

        // Get access token
        $token = $this->getAmadeusToken();
        if (!$token) {
            throw new Exception("Failed to get Amadeus access token");
        }

        $url = "https://test.api.amadeus.com/v2/shopping/flight-offers?" .
               "originLocationCode={$params['origin']}&" .
               "destinationLocationCode={$params['destination']}&" .
               "departureDate={$params['departure_date']}&" .
               "adults={$params['passengers']}&" .
               "currencyCode=USD";

        if (isset($params['return_date'])) {
            $url .= "&returnDate={$params['return_date']}";
        }

        $headers = [
            "Authorization: Bearer {$token}",
            "Content-Type: application/json"
        ];

        $response = $this->makeAPIRequest($url, 'GET', $headers);
        if (!$response) return null;

        $data = json_decode($response, true);
        if (!$data || !isset($data['data'])) return null;

        return $this->formatAmadeusResults($data['data']);
    }

    /**
     * Search flights using Sabre API
     */
    private function searchSabre($params)
    {
        if (!isset($this->apiKeys['sabre'])) {
            throw new Exception("Sabre API credentials not configured");
        }

        $url = "https://api.sabre.com/v1/shop/flights?" .
               "origin={$params['origin']}&" .
               "destination={$params['destination']}&" .
               "departuredate={$params['departure_date']}&" .
               "passengercount={$params['passengers']}";

        if (isset($params['return_date'])) {
            $url .= "&returndate={$params['return_date']}";
        }

        $headers = [
            "Authorization: Bearer {$this->apiKeys['sabre']['token']}",
            "Content-Type: application/json"
        ];

        $response = $this->makeAPIRequest($url, 'GET', $headers);
        if (!$response) return null;

        $data = json_decode($response, true);
        if (!$data) return null;

        return $this->formatSabreResults($data);
    }

    /**
     * Auto-select best provider
     */
    private function searchAuto($params)
    {
        // Try Amadeus first
        try {
            return $this->searchAmadeus($params);
        } catch (Exception $e) {
            $this->logger->info("Amadeus search failed, trying Sabre", ['params' => $params]);
        }

        // Fallback to Sabre
        try {
            return $this->searchSabre($params);
        } catch (Exception $e) {
            $this->logger->error("All airline search providers failed", ['params' => $params]);
            return null;
        }
    }

    /**
     * Get Amadeus access token
     */
    private function getAmadeusToken()
    {
        $url = "https://test.api.amadeus.com/v1/security/oauth2/token";

        $postData = [
            'grant_type' => 'client_credentials',
            'client_id' => $this->apiKeys['amadeus']['client_id'],
            'client_secret' => $this->apiKeys['amadeus']['client_secret']
        ];

        $response = $this->makeAPIRequest($url, 'POST', [
            'Content-Type: application/x-www-form-urlencoded'
        ], http_build_query($postData));

        if (!$response) return null;

        $data = json_decode($response, true);
        return $data['access_token'] ?? null;
    }

    /**
     * Format Amadeus search results
     */
    private function formatAmadeusResults($flights)
    {
        $formatted = [];

        foreach ($flights as $flight) {
            $formatted[] = [
                'flight_number' => $flight['itineraries'][0]['segments'][0]['carrierCode'] .
                                 $flight['itineraries'][0]['segments'][0]['number'],
                'airline' => $flight['itineraries'][0]['segments'][0]['carrierCode'],
                'origin' => $flight['itineraries'][0]['segments'][0]['departure']['iataCode'],
                'destination' => $flight['itineraries'][0]['segments'][count($flight['itineraries'][0]['segments'])-1]['arrival']['iataCode'],
                'departure_time' => $flight['itineraries'][0]['segments'][0]['departure']['at'],
                'arrival_time' => $flight['itineraries'][0]['segments'][count($flight['itineraries'][0]['segments'])-1]['arrival']['at'],
                'duration' => $flight['itineraries'][0]['duration'],
                'stops' => count($flight['itineraries'][0]['segments']) - 1,
                'price' => [
                    'amount' => $flight['price']['total'],
                    'currency' => $flight['price']['currency']
                ],
                'segments' => array_map(function($segment) {
                    return [
                        'departure' => $segment['departure'],
                        'arrival' => $segment['arrival'],
                        'carrier' => $segment['carrierCode'],
                        'flight_number' => $segment['carrierCode'] . $segment['number'],
                        'aircraft' => $segment['aircraft']['code'] ?? null,
                        'duration' => $segment['duration']
                    ];
                }, $flight['itineraries'][0]['segments']),
                'source' => 'amadeus'
            ];
        }

        return ['flights' => $formatted];
    }

    /**
     * Format Sabre search results
     */
    private function formatSabreResults($data)
    {
        $formatted = [];

        if (isset($data['PricedItineraries'])) {
            foreach ($data['PricedItineraries'] as $itinerary) {
                $formatted[] = [
                    'flight_number' => $itinerary['AirItinerary']['OriginDestinationOptions']['OriginDestinationOption'][0]['FlightSegment'][0]['FlightNumber'],
                    'airline' => $itinerary['AirItinerary']['OriginDestinationOptions']['OriginDestinationOption'][0]['FlightSegment'][0]['MarketingAirline']['Code'],
                    'origin' => $itinerary['AirItinerary']['OriginDestinationOptions']['OriginDestinationOption'][0]['FlightSegment'][0]['DepartureAirport']['LocationCode'],
                    'destination' => $itinerary['AirItinerary']['OriginDestinationOptions']['OriginDestinationOption'][0]['FlightSegment'][count($itinerary['AirItinerary']['OriginDestinationOptions']['OriginDestinationOption'][0]['FlightSegment'])-1]['ArrivalAirport']['LocationCode'],
                    'departure_time' => $itinerary['AirItinerary']['OriginDestinationOptions']['OriginDestinationOption'][0]['FlightSegment'][0]['DepartureDateTime'],
                    'arrival_time' => $itinerary['AirItinerary']['OriginDestinationOptions']['OriginDestinationOption'][0]['FlightSegment'][count($itinerary['AirItinerary']['OriginDestinationOptions']['OriginDestinationOption'][0]['FlightSegment'])-1]['ArrivalDateTime'],
                    'price' => [
                        'amount' => $itinerary['AirItineraryPricingInfo']['ItinTotalFare']['TotalFare']['Amount'],
                        'currency' => $itinerary['AirItineraryPricingInfo']['ItinTotalFare']['TotalFare']['CurrencyCode']
                    ],
                    'source' => 'sabre'
                ];
            }
        }

        return ['flights' => $formatted];
    }

    /**
     * Create booking
     */
    public function createBooking($bookingData, $provider = 'auto')
    {
        try {
            switch ($provider) {
                case 'amadeus':
                    return $this->createAmadeusBooking($bookingData);
                case 'sabre':
                    return $this->createSabreBooking($bookingData);
                case 'auto':
                default:
                    // Try Amadeus first
                    try {
                        return $this->createAmadeusBooking($bookingData);
                    } catch (Exception $e) {
                        return $this->createSabreBooking($bookingData);
                    }
            }
        } catch (Exception $e) {
            $this->logger->error("Booking creation failed", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Create booking with Amadeus
     */
    private function createAmadeusBooking($data)
    {
        $token = $this->getAmadeusToken();
        if (!$token) {
            throw new Exception("Failed to get Amadeus access token");
        }

        $url = "https://test.api.amadeus.com/v1/booking/flight-orders";

        $headers = [
            "Authorization: Bearer {$token}",
            "Content-Type: application/json"
        ];

        $bookingPayload = $this->formatAmadeusBookingPayload($data);

        $response = $this->makeAPIRequest($url, 'POST', $headers, json_encode($bookingPayload));
        if (!$response) {
            throw new Exception("Booking request failed");
        }

        $result = json_decode($response, true);
        if (!$result) {
            throw new Exception("Invalid booking response");
        }

        // Store booking in database
        $this->storeBooking($data, $result, 'amadeus');

        return [
            'success' => true,
            'booking_id' => $result['id'] ?? null,
            'pnr' => $result['associatedRecords'][0]['reference'] ?? null,
            'status' => 'confirmed'
        ];
    }

    /**
     * Create booking with Sabre
     */
    private function createSabreBooking($data)
    {
        $url = "https://api.sabre.com/v1/trip/orders";

        $headers = [
            "Authorization: Bearer {$this->apiKeys['sabre']['token']}",
            "Content-Type: application/json"
        ];

        $bookingPayload = $this->formatSabreBookingPayload($data);

        $response = $this->makeAPIRequest($url, 'POST', $headers, json_encode($bookingPayload));
        if (!$response) {
            throw new Exception("Booking request failed");
        }

        $result = json_decode($response, true);

        // Store booking in database
        $this->storeBooking($data, $result, 'sabre');

        return [
            'success' => true,
            'booking_id' => $result['OrderId'] ?? null,
            'pnr' => $result['RecordLocator'] ?? null,
            'status' => 'confirmed'
        ];
    }

    /**
     * Format Amadeus booking payload
     */
    private function formatAmadeusBookingPayload($data)
    {
        return [
            'data' => [
                'type' => 'flight-order',
                'flightOffers' => [$data['flight_offer']],
                'travelers' => array_map(function($passenger) {
                    return [
                        'id' => $passenger['id'],
                        'dateOfBirth' => $passenger['date_of_birth'],
                        'name' => [
                            'firstName' => $passenger['first_name'],
                            'lastName' => $passenger['last_name']
                        ],
                        'gender' => $passenger['gender'],
                        'contact' => [
                            'emailAddress' => $passenger['email'],
                            'phones' => [[
                                'deviceType' => 'MOBILE',
                                'countryCallingCode' => '1',
                                'number' => $passenger['phone']
                            ]]
                        ]
                    ];
                }, $data['passengers']),
                'remarks' => [
                    'general' => [[
                        'subType' => 'GENERAL_MISCELLANEOUS',
                        'text' => 'Booking created via Flight Control System'
                    ]]
                ]
            ]
        ];
    }

    /**
     * Format Sabre booking payload
     */
    private function formatSabreBookingPayload($data)
    {
        return [
            'CreatePassengerNameRecordRQ' => [
                'TravelItineraryAddInfo' => [
                    'CustomerInfo' => [
                        'ContactNumbers' => [
                            'ContactNumber' => [
                                'Phone' => $data['passengers'][0]['phone'],
                                'PhoneUseType' => 'H'
                            ]
                        ],
                        'PersonName' => array_map(function($passenger) {
                            return [
                                'NameNumber' => '1.1',
                                'GivenName' => $passenger['first_name'],
                                'Surname' => $passenger['last_name']
                            ];
                        }, $data['passengers'])
                    ]
                ],
                'AirBook' => [
                    'OriginDestinationInformation' => [
                        'FlightSegment' => $data['flight_segments']
                    ]
                ]
            ]
        ];
    }

    /**
     * Get flight schedule
     */
    public function getFlightSchedule($airline, $flightNumber, $date)
    {
        try {
            // Try Amadeus first
            $token = $this->getAmadeusToken();
            if ($token) {
                $url = "https://test.api.amadeus.com/v2/schedule/flights?" .
                       "carrierCode={$airline}&flightNumber={$flightNumber}&scheduledDepartureDate={$date}";

                $headers = ["Authorization: Bearer {$token}"];
                $response = $this->makeAPIRequest($url, 'GET', $headers);

                if ($response) {
                    $data = json_decode($response, true);
                    return $this->formatScheduleData($data);
                }
            }

            // Fallback to Sabre
            $url = "https://api.sabre.com/v1/flights/{$airline}{$flightNumber}/schedule?date={$date}";
            $headers = ["Authorization: Bearer {$this->apiKeys['sabre']['token']}"];
            $response = $this->makeAPIRequest($url, 'GET', $headers);

            if ($response) {
                $data = json_decode($response, true);
                return $this->formatScheduleData($data);
            }

            return null;

        } catch (Exception $e) {
            $this->logger->error("Failed to get flight schedule", ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Format schedule data
     */
    private function formatScheduleData($data)
    {
        // Implementation depends on the API response format
        // This is a simplified version
        return $data;
    }

    /**
     * Store flight search results
     */
    private function storeFlightSearch($params, $results)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO flight_search_cache (
                    search_key, origin, destination, departure_date, return_date,
                    passengers, results, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");

            $searchKey = md5(json_encode($params));
            $stmt->execute([
                $searchKey,
                $params['origin'],
                $params['destination'],
                $params['departure_date'],
                $params['return_date'] ?? null,
                $params['passengers'],
                json_encode($results)
            ]);

        } catch (Exception $e) {
            $this->logger->error("Failed to store flight search", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Store booking data
     */
    private function storeBooking($bookingData, $apiResponse, $provider)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO airline_bookings (
                    booking_reference, provider, flight_data, passenger_data,
                    api_response, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");

            $stmt->execute([
                $apiResponse['id'] ?? $apiResponse['OrderId'] ?? uniqid(),
                $provider,
                json_encode($bookingData['flight_offer'] ?? $bookingData),
                json_encode($bookingData['passengers']),
                json_encode($apiResponse),
                'confirmed'
            ]);

        } catch (Exception $e) {
            $this->logger->error("Failed to store booking", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Make HTTP API request
     */
    private function makeAPIRequest($url, $method = 'GET', $headers = [], $postData = null)
    {
        $ch = curl_init();

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => array_merge([
                'User-Agent: Flight-Control-System/1.0'
            ], $headers)
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            if ($postData) {
                $options[CURLOPT_POSTFIELDS] = $postData;
            }
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            throw new Exception("API request failed: {$error}");
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new Exception("API request failed with HTTP {$httpCode}: {$response}");
        }

        return $response;
    }

    /**
     * Get airline information
     */
    public function getAirlineInfo($airlineCode)
    {
        try {
            // Try to get from database first
            $stmt = $this->db->prepare("SELECT * FROM airlines WHERE code = ?");
            $stmt->execute([$airlineCode]);
            $airline = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($airline) {
                return $airline;
            }

            // Fetch from API if not in database
            $token = $this->getAmadeusToken();
            if ($token) {
                $url = "https://test.api.amadeus.com/v1/reference-data/airlines?airlineCodes={$airlineCode}";
                $headers = ["Authorization: Bearer {$token}"];

                $response = $this->makeAPIRequest($url, 'GET', $headers);
                if ($response) {
                    $data = json_decode($response, true);
                    if ($data && isset($data['data'][0])) {
                        $airlineData = $data['data'][0];

                        // Store in database
                        $stmt = $this->db->prepare("
                            INSERT INTO airlines (name, code, country)
                            VALUES (?, ?, ?)
                            ON CONFLICT (code) DO UPDATE SET
                                name = EXCLUDED.name,
                                country = EXCLUDED.country
                        ");
                        $stmt->execute([
                            $airlineData['commonName'] ?? $airlineData['businessName'],
                            $airlineCode,
                            $airlineData['registeredCountryCode'] ?? null
                        ]);

                        return $airlineData;
                    }
                }
            }

            return null;

        } catch (Exception $e) {
            $this->logger->error("Failed to get airline info", ['error' => $e->getMessage()]);
            return null;
        }
    }
}
