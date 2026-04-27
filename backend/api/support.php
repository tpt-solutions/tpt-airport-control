<?php
/**
 * Customer Support Portal API
 *
 * Handles help desk tickets, documentation, and customer support operations
 */

require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Logger.php';
require_once __DIR__ . '/../services/SupportService.php';
require_once __DIR__ . '/../src/CorsHandler.php';

header('Content-Type: application/json');

// Handle CORS preflight
CorsHandler::handlePreflight();
CorsHandler::applyHeaders();

class SupportAPI
{
    private $supportService;
    private $logger;

    public function __construct()
    {
        $this->logger = new Logger('support_api');
        $this->supportService = new SupportService();
    }

    public function handleRequest()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $_SERVER['REQUEST_URI'];

        // Extract endpoint from path
        $endpoint = str_replace('/api/support/', '', parse_url($path, PHP_URL_PATH));
        $endpoint = trim($endpoint, '/');

        switch ($method) {
            case 'GET':
                $this->handleGet($endpoint);
                break;
            case 'POST':
                $this->handlePost($endpoint);
                break;
            case 'PUT':
                $this->handlePut($endpoint);
                break;
            default:
                $this->sendError('Method not allowed', 405);
        }
    }

    private function handleGet($endpoint)
    {
        $userId = $this->getCurrentUserId();

        switch ($endpoint) {
            case 'tickets':
                $this->getUserTickets($userId);
                break;
            case 'knowledge-base':
                $this->getKnowledgeBase();
                break;
            case 'faq':
                $this->getFAQ();
                break;
            case 'ticket':
                $ticketId = $_GET['id'] ?? null;
                $this->getTicket($userId, $ticketId);
                break;
            default:
                $this->sendError('Endpoint not found', 404);
        }
    }

    private function handlePost($endpoint)
    {
        $userId = $this->getCurrentUserId();
        $data = json_decode(file_get_contents('php://input'), true);

        switch ($endpoint) {
            case 'create-ticket':
                $this->createTicket($userId, $data);
                break;
            case 'add-comment':
                $this->addTicketComment($userId, $data);
                break;
            case 'submit-feedback':
                $this->submitFeedback($userId, $data);
                break;
            default:
                $this->sendError('Endpoint not found', 404);
        }
    }

    private function handlePut($endpoint)
    {
        $userId = $this->getCurrentUserId();
        $data = json_decode(file_get_contents('php://input'), true);

        switch ($endpoint) {
            case 'close-ticket':
                $ticketId = $data['ticket_id'] ?? null;
                $this->closeTicket($userId, $ticketId);
                break;
            case 'update-ticket':
                $this->updateTicket($userId, $data);
                break;
            default:
                $this->sendError('Endpoint not found', 404);
        }
    }

    private function getUserTickets($userId)
    {
        try {
            $tickets = $this->supportService->getUserTickets($userId);
            $this->sendResponse(['tickets' => $tickets]);
        } catch (Exception $e) {
            $this->logger->error("Failed to get user tickets", ['error' => $e->getMessage()]);
            $this->sendError('Failed to retrieve support tickets', 500);
        }
    }

    private function getTicket($userId, $ticketId)
    {
        if (!$ticketId) {
            $this->sendError('Ticket ID is required', 400);
            return;
        }

        try {
            $ticket = $this->supportService->getTicket($userId, $ticketId);
            $this->sendResponse(['ticket' => $ticket]);
        } catch (Exception $e) {
            $this->logger->error("Failed to get ticket", ['ticket_id' => $ticketId, 'error' => $e->getMessage()]);
            $this->sendError('Failed to retrieve ticket', 500);
        }
    }

    private function createTicket($userId, $data)
    {
        $required = ['subject', 'description', 'category'];
        
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $this->sendError("{$field} is required", 400);
                return;
            }
        }

        try {
            $ticket = $this->supportService->createTicket($userId, $data);
            
            $this->logger->info("Support ticket created", ['ticket_id' => $ticket['id'], 'user_id' => $userId]);

            $this->sendResponse([
                'message' => 'Ticket created successfully',
                'ticket' => $ticket
            ], 201);
        } catch (Exception $e) {
            $this->logger->error("Failed to create ticket", ['error' => $e->getMessage()]);
            $this->sendError($e->getMessage(), 400);
        }
    }

    private function addTicketComment($userId, $data)
    {
        $ticketId = $data['ticket_id'] ?? null;
        $comment = $data['comment'] ?? null;

        if (!$ticketId || !$comment) {
            $this->sendError('Ticket ID and comment are required', 400);
            return;
        }

        try {
            $commentResult = $this->supportService->addTicketComment($userId, $ticketId, $comment);
            
            $this->sendResponse([
                'message' => 'Comment added successfully',
                'comment' => $commentResult
            ]);
        } catch (Exception $e) {
            $this->logger->error("Failed to add comment", ['ticket_id' => $ticketId, 'error' => $e->getMessage()]);
            $this->sendError($e->getMessage(), 400);
        }
    }

    private function closeTicket($userId, $ticketId)
    {
        if (!$ticketId) {
            $this->sendError('Ticket ID is required', 400);
            return;
        }

        try {
            $this->supportService->closeTicket($userId, $ticketId);
            
            $this->sendResponse([
                'message' => 'Ticket closed successfully'
            ]);
        } catch (Exception $e) {
            $this->logger->error("Failed to close ticket", ['ticket_id' => $ticketId, 'error' => $e->getMessage()]);
            $this->sendError($e->getMessage(), 400);
        }
    }

    private function getKnowledgeBase()
    {
        try {
            $articles = $this->supportService->getKnowledgeBaseArticles();
            $this->sendResponse(['articles' => $articles]);
        } catch (Exception $e) {
            $this->sendError('Failed to retrieve knowledge base', 500);
        }
    }

    private function getFAQ()
    {
        try {
            $faq = $this->supportService->getFAQ();
            $this->sendResponse(['faq' => $faq]);
        } catch (Exception $e) {
            $this->sendError('Failed to retrieve FAQ', 500);
        }
    }

    private function submitFeedback($userId, $data)
    {
        $rating = $data['rating'] ?? null;
        $feedback = $data['feedback'] ?? '';

        if (!$rating) {
            $this->sendError('Rating is required', 400);
            return;
        }

        try {
            $this->supportService->submitFeedback($userId, $rating, $feedback);
            
            $this->sendResponse([
                'message' => 'Thank you for your feedback'
            ]);
        } catch (Exception $e) {
            $this->logger->error("Failed to submit feedback", ['error' => $e->getMessage()]);
            $this->sendError('Failed to submit feedback', 500);
        }
    }

    private function getCurrentUserId()
    {
        // Get user ID from session or JWT token
        return $_SESSION['user_id'] ?? 1; // Default to user ID 1 for demo
    }

    private function sendResponse($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }

    private function sendError($message, $statusCode = 400)
    {
        http_response_code($statusCode);
        echo json_encode(['error' => $message]);
        exit;
    }
}

// Handle the request
$api = new SupportAPI();
$api->handleRequest();
?>