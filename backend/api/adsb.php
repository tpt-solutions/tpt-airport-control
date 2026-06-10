<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/../src/Logger.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }

    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../src/Middleware.php';
    require_once __DIR__ . '/../integrations/adsb-integration.php';

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? 'aircraft';

    switch ($method) {
        case 'GET':
            handleGet($action);
            break;
        case 'POST':
            handlePost($action);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    Logger::error('ADS-B API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

function handleGet($action) {
    global $pdo;

    try {
        $adsb = new ADSBIntegration($pdo);

        switch ($action) {
            case 'aircraft':
                Middleware::authenticate();
                Middleware::checkPermission('read', 'atc');

                // Get bounds from query parameters
                $bounds = null;
                if (isset($_GET['lamin'], $_GET['lomin'], $_GET['lamax'], $_GET['lomax'])) {
                    $bounds = [
                        'lamin' => (float)$_GET['lamin'],
                        'lomin' => (float)$_GET['lomin'],
                        'lamax' => (float)$_GET['lamax'],
                        'lomax' => (float)$_GET['lomax']
                    ];
                }

                $maxAge = (int)($_GET['max_age'] ?? 300); // 5 minutes default
                $aircraft = $adsb->getAircraftInBounds($bounds ?: [
                    'lamin' => -90, 'lomin' => -180,
                    'lamax' => 90, 'lomax' => 180
                ], $maxAge);

                echo json_encode(['aircraft' => $aircraft]);
                break;

            case 'flight_path':
                Middleware::authenticate();
                Middleware::checkPermission('read', 'atc');

                $icao24 = $_GET['icao24'] ?? null;
                $hours = (int)($_GET['hours'] ?? 24);

                if (!$icao24) {
                    http_response_code(400);
                    echo json_encode(['error' => 'ICAO24 identifier required']);
                    return;
                }

                $path = $adsb->getFlightPath($icao24, $hours);
                echo json_encode(['flight_path' => $path]);
                break;

            case 'conflicts':
                Middleware::authenticate();
                Middleware::checkPermission('read', 'atc');

                $bounds = null;
                if (isset($_GET['lamin'], $_GET['lomin'], $_GET['lamax'], $_GET['lomax'])) {
                    $bounds = [
                        'lamin' => (float)$_GET['lamin'],
                        'lomin' => (float)$_GET['lomin'],
                        'lamax' => (float)$_GET['lamax'],
                        'lomax' => (float)$_GET['lomax']
                    ];
                }

                if (!$bounds) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Bounds parameters required for conflict detection']);
                    return;
                }

                $conflicts = $adsb->detectConflicts($bounds);
                echo json_encode(['conflicts' => $conflicts]);
                break;

            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
                break;
        }
    } catch (Exception $e) {
        Logger::error('ADS-B GET error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to retrieve ADS-B data']);
    }
}

function handlePost($action) {
    global $pdo;

    try {
        $adsb = new ADSBIntegration($pdo);

        switch ($action) {
            case 'fetch_data':
                Middleware::authenticate();
                Middleware::checkPermission('write', 'atc');

                $input = json_decode(file_get_contents('php://input'), true);
                $bounds = $input['bounds'] ?? null;

                $result = $adsb->fetchRealtimeData($bounds);

                if ($result === false) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to fetch ADS-B data']);
                } else {
                    echo json_encode([
                        'message' => 'ADS-B data fetched successfully',
                        'aircraft_processed' => $result
                    ]);
                }
                break;

            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
                break;
        }
    } catch (Exception $e) {
        Logger::error('ADS-B POST error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to process ADS-B request']);
    }
}
?>
