<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../src/Slot.php';
require_once __DIR__ . '/../src/Participant.php';
require_once __DIR__ . '/../src/Reservation.php';
require_once __DIR__ . '/../src/EmailService.php';

/**
 * Simple API router for reservation system
 */
class ApiRouter {
    private $slotModel;
    private $participantModel;
    private $reservationModel;
    private $emailService;
    
    public function __construct() {
        $this->slotModel = new Slot();
        $this->participantModel = new Participant();
        $this->reservationModel = new Reservation();
        $this->emailService = new EmailService();
    }
    
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $path = str_replace('/api.php', '', $path);
        
        try {
            switch ($method) {
                case 'GET':
                    $this->handleGet($path);
                    break;
                case 'POST':
                    $this->handlePost($path);
                    break;
                case 'PUT':
                    $this->handlePut($path);
                    break;
                case 'DELETE':
                    $this->handleDelete($path);
                    break;
                default:
                    $this->sendError('Method not allowed', 405);
            }
        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 500);
        }
    }
    
    private function handleGet($path) {
        switch ($path) {
            case '/slots':
                $this->sendJson($this->slotModel->getAvailableSlots());
                break;
            case '/slots/all':
                $this->sendJson($this->slotModel->getSlotsWithReservationCount());
                break;
            case '/reservations':
                $this->sendJson($this->reservationModel->getAllWithDetails());
                break;
            default:
                $this->sendError('Endpoint not found', 404);
        }
    }
    
    private function handlePost($path) {
        $data = json_decode(file_get_contents('php://input'), true);
        
        switch ($path) {
            case '/reservations':
                $this->createReservation($data);
                break;
            case '/participants':
                $this->createParticipant($data);
                break;
            default:
                $this->sendError('Endpoint not found', 404);
        }
    }
    
    private function handlePut($path) {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (preg_match('/\/slots\/(\d+)\/toggle/', $path, $matches)) {
            $slotId = $matches[1];
            $result = $this->slotModel->toggleAvailability($slotId);
            $this->sendJson(['success' => $result]);
        } elseif (preg_match('/\/reservations\/(\d+)\/cancel/', $path, $matches)) {
            $reservationId = $matches[1];
            $result = $this->reservationModel->cancelReservation($reservationId);
            $this->sendJson(['success' => $result]);
        } else {
            $this->sendError('Endpoint not found', 404);
        }
    }
    
    private function createReservation($data) {
        if (!isset($data['slot_id']) || !isset($data['participant_name']) || !isset($data['participant_email'])) {
            $this->sendError('Missing required fields', 400);
            return;
        }
        
        // Validate email
        if (!filter_var($data['participant_email'], FILTER_VALIDATE_EMAIL)) {
            $this->sendError('Invalid email address', 400);
            return;
        }
        
        // Create or get participant
        $participantId = $this->participantModel->createOrGet(
            $data['participant_name'], 
            $data['participant_email']
        );
        
        // Create reservation
        $reservationId = $this->reservationModel->makeReservation($data['slot_id'], $participantId);
        
        if ($reservationId) {
            // Send confirmation email
            try {
                $this->emailService->sendConfirmationEmail(
                    $data['participant_email'], 
                    $data['participant_name'], 
                    $data['slot_id']
                );
            } catch (Exception $e) {
                error_log("Failed to send confirmation email: " . $e->getMessage());
            }
            
            $this->sendJson(['success' => true, 'reservation_id' => $reservationId]);
        } else {
            $this->sendError('Failed to create reservation', 500);
        }
    }
    
    private function createParticipant($data) {
        if (!isset($data['name']) || !isset($data['email'])) {
            $this->sendError('Missing required fields', 400);
            return;
        }
        
        $participantId = $this->participantModel->createOrGet($data['name'], $data['email']);
        $this->sendJson(['success' => true, 'participant_id' => $participantId]);
    }
    
    private function sendJson($data) {
        echo json_encode($data);
    }
    
    private function sendError($message, $code = 400) {
        http_response_code($code);
        echo json_encode(['error' => $message]);
    }
}

// Handle the request
$router = new ApiRouter();
$router->handleRequest();
?>