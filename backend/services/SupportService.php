<?php
/**
 * Customer Support Service
 *
 * Manages help desk tickets, knowledge base, FAQ, and customer support operations
 */

require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Logger.php';

class SupportService
{
    private $pdo;
    private $logger;

    // Ticket categories
    private $categories = [
        'technical' => 'Technical Issue',
        'billing' => 'Billing & Subscription',
        'feature_request' => 'Feature Request',
        'documentation' => 'Documentation',
        'general' => 'General Inquiry',
        'bug_report' => 'Bug Report'
    ];

    // Ticket priorities
    private $priorities = [
        'low' => 'Low',
        'normal' => 'Normal',
        'high' => 'High',
        'urgent' => 'Urgent'
    ];

    public function __construct()
    {
        $this->logger = new Logger('support_service');
        $this->connectDatabase();
    }

    private function connectDatabase()
    {
        try {
            $config = Config::getInstance();
            $dbConfig = $config->get('database');

            $this->pdo = new PDO(
                "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']}",
                $dbConfig['username'],
                $dbConfig['password']
            );
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (Exception $e) {
            $this->logger->error('Database connection failed', ['error' => $e->getMessage()]);
            throw new Exception('Database connection failed');
        }
    }

    /**
     * Create new support ticket
     */
    public function createTicket($userId, $ticketData)
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO support_tickets (
                    user_id, subject, description, category, priority, status, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $ticketId = uniqid('tkt_');
            $status = 'open';
            $priority = $ticketData['priority'] ?? 'normal';
            $now = date('Y-m-d H:i:s');

            $stmt->execute([
                $userId,
                $ticketData['subject'],
                $ticketData['description'],
                $ticketData['category'],
                $priority,
                $status,
                $now,
                $now
            ]);

            return [
                'id' => $ticketId,
                'subject' => $ticketData['subject'],
                'category' => $ticketData['category'],
                'priority' => $priority,
                'status' => $status,
                'created_at' => $now
            ];
        } catch (Exception $e) {
            $this->logger->error("Failed to create ticket", ['user_id' => $userId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get user's support tickets
     */
    public function getUserTickets($userId)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, subject, category, priority, status, created_at, updated_at
                FROM support_tickets
                WHERE user_id = ?
                ORDER BY created_at DESC
            ");
            
            $stmt->execute([$userId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("Failed to get user tickets", ['user_id' => $userId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get specific ticket with comments
     */
    public function getTicket($userId, $ticketId)
    {
        try {
            // Get ticket details
            $stmt = $this->pdo->prepare("
                SELECT * FROM support_tickets 
                WHERE id = ? AND user_id = ?
            ");
            
            $stmt->execute([$ticketId, $userId]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$ticket) {
                throw new Exception("Ticket not found or access denied");
            }

            // Get ticket comments
            $stmt = $this->pdo->prepare("
                SELECT id, user_id, comment, created_at, is_staff
                FROM support_ticket_comments
                WHERE ticket_id = ?
                ORDER BY created_at ASC
            ");
            
            $stmt->execute([$ticketId]);
            $ticket['comments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $ticket;
        } catch (Exception $e) {
            $this->logger->error("Failed to get ticket", ['ticket_id' => $ticketId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Add comment to ticket
     */
    public function addTicketComment($userId, $ticketId, $comment)
    {
        try {
            // Verify user has access to ticket
            $stmt = $this->pdo->prepare("
                SELECT 1 FROM support_tickets 
                WHERE id = ? AND user_id = ?
            ");
            
            $stmt->execute([$ticketId, $userId]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception("Ticket not found or access denied");
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO support_ticket_comments (
                    ticket_id, user_id, comment, is_staff, created_at
                ) VALUES (?, ?, ?, false, ?)
            ");

            $now = date('Y-m-d H:i:s');
            $stmt->execute([$ticketId, $userId, $comment, $now]);

            // Update ticket timestamp
            $stmt = $this->pdo->prepare("
                UPDATE support_tickets 
                SET updated_at = ? 
                WHERE id = ?
            ");
            
            $stmt->execute([$now, $ticketId]);

            return [
                'comment' => $comment,
                'created_at' => $now,
                'is_staff' => false
            ];
        } catch (Exception $e) {
            $this->logger->error("Failed to add comment", ['ticket_id' => $ticketId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Close support ticket
     */
    public function closeTicket($userId, $ticketId)
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE support_tickets 
                SET status = 'closed', closed_at = ?, updated_at = ?
                WHERE id = ? AND user_id = ?
            ");

            $now = date('Y-m-d H:i:s');
            $stmt->execute([$now, $now, $ticketId, $userId]);

            if ($stmt->rowCount() === 0) {
                throw new Exception("Ticket not found or access denied");
            }

            return true;
        } catch (Exception $e) {
            $this->logger->error("Failed to close ticket", ['ticket_id' => $ticketId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Update ticket
     */
    public function updateTicket($userId, $data)
    {
        $ticketId = $data['ticket_id'] ?? null;
        $fields = [];
        $params = [];

        if (isset($data['subject'])) {
            $fields[] = 'subject = ?';
            $params[] = $data['subject'];
        }

        if (isset($data['description'])) {
            $fields[] = 'description = ?';
            $params[] = $data['description'];
        }

        if (isset($data['category'])) {
            $fields[] = 'category = ?';
            $params[] = $data['category'];
        }

        if (isset($data['priority'])) {
            $fields[] = 'priority = ?';
            $params[] = $data['priority'];
        }

        if (empty($fields)) {
            throw new Exception("No fields to update");
        }

        $fields[] = 'updated_at = ?';
        $params[] = date('Y-m-d H:i:s');
        $params[] = $ticketId;
        $params[] = $userId;

        try {
            $stmt = $this->pdo->prepare("
                UPDATE support_tickets 
                SET " . implode(', ', $fields) . "
                WHERE id = ? AND user_id = ?
            ");

            $stmt->execute($params);

            return true;
        } catch (Exception $e) {
            $this->logger->error("Failed to update ticket", ['ticket_id' => $ticketId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get knowledge base articles
     */
    public function getKnowledgeBaseArticles()
    {
        $articles = [
            [
                'id' => 'kb_getting_started',
                'title' => 'Getting Started Guide',
                'category' => 'Getting Started',
                'content' => 'Learn how to get started with the Flight Control System.',
                'url' => '/docs/getting-started'
            ],
            [
                'id' => 'kb_subscription',
                'title' => 'Subscription Management',
                'category' => 'Billing',
                'content' => 'How to manage your subscription, upgrade or cancel.',
                'url' => '/docs/subscription'
            ],
            [
                'id' => 'kb_api_documentation',
                'title' => 'API Documentation',
                'category' => 'Developer',
                'content' => 'Complete API reference documentation.',
                'url' => '/docs/api'
            ],
            [
                'id' => 'kb_scenario_editor',
                'title' => 'Scenario Editor Guide',
                'category' => 'Features',
                'content' => 'How to create and customize your own scenarios.',
                'url' => '/docs/scenario-editor'
            ]
        ];

        return $articles;
    }

    /**
     * Get frequently asked questions
     */
    public function getFAQ()
    {
        return [
            [
                'question' => 'How do I reset my password?',
                'answer' => 'You can reset your password from the login page by clicking "Forgot Password".'
            ],
            [
                'question' => 'What payment methods do you accept?',
                'answer' => 'We accept all major credit cards, PayPal, and bank transfers for enterprise accounts.'
            ],
            [
                'question' => 'Can I cancel my subscription at any time?',
                'answer' => 'Yes, you can cancel your subscription at any time from your account settings.'
            ],
            [
                'question' => 'Is my data secure?',
                'answer' => 'Yes, all data is encrypted at rest and in transit. We comply with industry security standards.'
            ],
            [
                'question' => 'Do you offer educational discounts?',
                'answer' => 'Yes, we offer special pricing for educational institutions and flight schools.'
            ]
        ];
    }

    /**
     * Submit user feedback
     */
    public function submitFeedback($userId, $rating, $feedback = '')
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO user_feedback (
                    user_id, rating, feedback, created_at
                ) VALUES (?, ?, ?, ?)
            ");

            $stmt->execute([
                $userId,
                $rating,
                $feedback,
                date('Y-m-d H:i:s')
            ]);

            $this->logger->info("User feedback submitted", ['user_id' => $userId, 'rating' => $rating]);

            return true;
        } catch (Exception $e) {
            $this->logger->error("Failed to submit feedback", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get ticket categories
     */
    public function getCategories()
    {
        return $this->categories;
    }

    /**
     * Get ticket priorities
     */
    public function getPriorities()
    {
        return $this->priorities;
    }
}
?>