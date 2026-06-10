<?php
require_once __DIR__ . '/../services/UserService.php';
require_once __DIR__ . '/../src/Auth.php';

class UserController {
    private $userService;

    public function __construct($pdo) {
        $this->userService = new UserService($pdo);
    }

    public function getUsers() {
        try {
            // Check permissions - only admins can list all users
            $currentUser = Auth::getCurrentUser();
            if (!$currentUser || !Auth::hasPermission('admin')) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            // Build filters
            $filters = [];
            $pagination = [];

            // Parse query parameters
            if (isset($_GET['role_id'])) {
                $filters['role_id'] = (int)$_GET['role_id'];
            }
            if (isset($_GET['is_active'])) {
                $filters['is_active'] = filter_var($_GET['is_active'], FILTER_VALIDATE_BOOLEAN);
            }
            if (isset($_GET['search'])) {
                $filters['search'] = $_GET['search'];
            }
            if (isset($_GET['role_name'])) {
                $filters['role_name'] = $_GET['role_name'];
            }

            // Pagination
            $pagination['page'] = max(1, (int)($_GET['page'] ?? 1));
            $pagination['limit'] = min(100, max(1, (int)($_GET['limit'] ?? 50)));
            $pagination['offset'] = ($pagination['page'] - 1) * $pagination['limit'];

            $result = $this->userService->getUsers($filters, $pagination);

            return $result;
        } catch (Exception $e) {
            http_response_code(500);
            return ['error' => 'Failed to retrieve users', 'message' => 'An error occurred. Please try again.'];
        }
    }

    public function getUser($id) {
        try {
            // Check permissions
            $currentUser = Auth::getCurrentUser();
            if (!$currentUser) {
                http_response_code(401);
                return ['error' => 'Authentication required'];
            }

            // Users can view their own profile, admins can view any
            if ($currentUser['id'] != $id && !Auth::hasPermission('admin')) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            $result = $this->userService->getUserById($id);

            return $result;
        } catch (Exception $e) {
            if ($e->getMessage() === 'User not found') {
                http_response_code(404);
            } else {
                http_response_code(500);
            }
            error_log('Controller error: ' . $e->getMessage());
            return ['error' => 'An error occurred. Please try again.'];
        }
    }

    public function getUserProfile($userId) {
        try {
            // Check permissions
            $currentUser = Auth::getCurrentUser();
            if (!$currentUser) {
                http_response_code(401);
                return ['error' => 'Authentication required'];
            }

            // Users can view their own profile, admins can view any
            if ($currentUser['id'] != $userId && !Auth::hasPermission('admin')) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            $result = $this->userService->getUserProfile($userId);

            return $result;
        } catch (Exception $e) {
            if ($e->getMessage() === 'User not found') {
                http_response_code(404);
            } else {
                http_response_code(500);
            }
            error_log('Controller error: ' . $e->getMessage());
            return ['error' => 'An error occurred. Please try again.'];
        }
    }

    public function createUser() {
        try {
            // Check permissions - only admins can create users
            if (!Auth::hasPermission('admin')) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                http_response_code(400);
                return ['error' => 'Invalid JSON data'];
            }

            $result = $this->userService->createUser($data);

            http_response_code(201);
            return $result;
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'Missing required fields') ||
                str_contains($e->getMessage(), 'Validation failed') ||
                str_contains($e->getMessage(), 'already exists') ||
                str_contains($e->getMessage(), 'Password validation failed')) {
                http_response_code(400);
            } else {
                http_response_code(500);
            }
            error_log('Controller error: ' . $e->getMessage());
            return ['error' => 'An error occurred. Please try again.'];
        }
    }

    public function updateUser($id) {
        try {
            // Check permissions
            $currentUser = Auth::getCurrentUser();
            if (!$currentUser) {
                http_response_code(401);
                return ['error' => 'Authentication required'];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                http_response_code(400);
                return ['error' => 'Invalid JSON data'];
            }

            // Users can update their own profile, admins can update any
            if ($currentUser['id'] != $id && !Auth::hasPermission('admin')) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            // Non-admin users can only update certain fields
            if (!Auth::hasPermission('admin')) {
                $allowedFields = ['first_name', 'last_name', 'email'];
                $data = array_intersect_key($data, array_flip($allowedFields));
            }

            $result = $this->userService->updateUser($id, $data);

            return $result;
        } catch (Exception $e) {
            if ($e->getMessage() === 'User not found') {
                http_response_code(404);
            } elseif (str_contains($e->getMessage(), 'Validation failed') ||
                      str_contains($e->getMessage(), 'already exists')) {
                http_response_code(400);
            } else {
                http_response_code(500);
            }
            error_log('Controller error: ' . $e->getMessage());
            return ['error' => 'An error occurred. Please try again.'];
        }
    }

    public function updateProfile() {
        try {
            // Update current user's profile
            $currentUser = Auth::getCurrentUser();
            if (!$currentUser) {
                http_response_code(401);
                return ['error' => 'Authentication required'];
            }

            return $this->updateUser($currentUser['id']);
        } catch (Exception $e) {
            http_response_code(500);
            return ['error' => 'Failed to update profile', 'message' => 'An error occurred. Please try again.'];
        }
    }

    public function updatePassword($id) {
        try {
            // Check permissions
            $currentUser = Auth::getCurrentUser();
            if (!$currentUser) {
                http_response_code(401);
                return ['error' => 'Authentication required'];
            }

            // Users can update their own password, admins can update any
            if ($currentUser['id'] != $id && !Auth::hasPermission('admin')) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                http_response_code(400);
                return ['error' => 'Invalid JSON data'];
            }

            if (!isset($data['current_password']) || !isset($data['new_password'])) {
                http_response_code(400);
                return ['error' => 'Current password and new password are required'];
            }

            $result = $this->userService->updatePassword($id, $data['current_password'], $data['new_password']);

            return $result;
        } catch (Exception $e) {
            if ($e->getMessage() === 'User not found') {
                http_response_code(404);
            } elseif (str_contains($e->getMessage(), 'Current password is incorrect') ||
                      str_contains($e->getMessage(), 'New password validation failed')) {
                http_response_code(400);
            } else {
                http_response_code(500);
            }
            error_log('Controller error: ' . $e->getMessage());
            return ['error' => 'An error occurred. Please try again.'];
        }
    }

    public function deleteUser($id) {
        try {
            // Check permissions - only admins can delete users
            if (!Auth::hasPermission('admin')) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            $result = $this->userService->deleteUser($id);

            return $result;
        } catch (Exception $e) {
            if ($e->getMessage() === 'User not found') {
                http_response_code(404);
            } elseif (str_contains($e->getMessage(), 'Cannot delete admin users')) {
                http_response_code(403);
            } else {
                http_response_code(500);
            }
            error_log('Controller error: ' . $e->getMessage());
            return ['error' => 'An error occurred. Please try again.'];
        }
    }

    public function deactivateUser($id) {
        try {
            // Check permissions - only admins can deactivate users
            if (!Auth::hasPermission('admin')) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            $result = $this->userService->deactivateUser($id);

            return $result;
        } catch (Exception $e) {
            if ($e->getMessage() === 'User not found') {
                http_response_code(404);
            } elseif (str_contains($e->getMessage(), 'Cannot deactivate admin users')) {
                http_response_code(403);
            } else {
                http_response_code(500);
            }
            error_log('Controller error: ' . $e->getMessage());
            return ['error' => 'An error occurred. Please try again.'];
        }
    }

    public function activateUser($id) {
        try {
            // Check permissions - only admins can activate users
            if (!Auth::hasPermission('admin')) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            $result = $this->userService->activateUser($id);

            return $result;
        } catch (Exception $e) {
            if ($e->getMessage() === 'User not found') {
                http_response_code(404);
            } else {
                http_response_code(500);
            }
            error_log('Controller error: ' . $e->getMessage());
            return ['error' => 'An error occurred. Please try again.'];
        }
    }

    public function searchUsers() {
        try {
            // Check permissions - only admins can search users
            if (!Auth::hasPermission('admin')) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            if (!isset($_GET['q']) || empty(trim($_GET['q']))) {
                http_response_code(400);
                return ['error' => 'Search query is required'];
            }

            $searchTerm = trim($_GET['q']);
            $filters = [];

            $result = $this->userService->searchUsers($searchTerm, $filters);

            return $result;
        } catch (Exception $e) {
            http_response_code(500);
            return ['error' => 'Search failed', 'message' => 'An error occurred. Please try again.'];
        }
    }

    public function getUsersByRole($roleId) {
        try {
            // Check permissions - only admins can view users by role
            if (!Auth::hasPermission('admin')) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            $result = $this->userService->getUsersByRole($roleId);

            return $result;
        } catch (Exception $e) {
            http_response_code(500);
            return ['error' => 'Failed to retrieve users by role', 'message' => 'An error occurred. Please try again.'];
        }
    }

    public function getStatistics() {
        try {
            // Check permissions - only admins can view user statistics
            if (!Auth::hasPermission('admin')) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            $result = $this->userService->getUserStatistics();

            return $result;
        } catch (Exception $e) {
            http_response_code(500);
            return ['error' => 'Failed to retrieve statistics', 'message' => 'An error occurred. Please try again.'];
        }
    }

    public function getRoleDistribution() {
        try {
            // Check permissions - only admins can view role distribution
            if (!Auth::hasPermission('admin')) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            $result = $this->userService->getRoleDistribution();

            return $result;
        } catch (Exception $e) {
            http_response_code(500);
            return ['error' => 'Failed to retrieve role distribution', 'message' => 'An error occurred. Please try again.'];
        }
    }

    public function authenticateUser() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data || !isset($data['username']) || !isset($data['password'])) {
                http_response_code(400);
                return ['error' => 'Username and password are required'];
            }

            $result = $this->userService->authenticateUser($data['username'], $data['password']);

            return $result;
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'Invalid credentials') ||
                str_contains($e->getMessage(), 'Account is deactivated')) {
                http_response_code(401);
            } else {
                http_response_code(500);
            }
            error_log('Controller error: ' . $e->getMessage());
            return ['error' => 'An error occurred. Please try again.'];
        }
    }
}
?>
