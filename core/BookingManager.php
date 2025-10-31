<?php
// core/BookingManager.php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Mailer.php';

class BookingManager {
    private $db;
    private $mailer;
    
    public function __construct() {
        $this->db = new Database();
        $this->mailer = new Mailer();
    }
    
    /**
     * Create a new booking
     */
    public function createBooking($data) {
        try {
            // 1. Validate event exists and has capacity
            $event = $this->getEventDetails($data['event_id']);
            if (!$event) {
                return ['success' => false, 'message' => 'Event not found'];
            }
            
            if ($event['status'] !== 'upcoming') {
                return ['success' => false, 'message' => 'Event is not available for booking'];
            }
            
            // 2. Check capacity
            $availableSlots = $event['max_capacity'] - $event['current_bookings'];
            if ($availableSlots <= 0) {
                return ['success' => false, 'message' => 'Event is fully booked'];
            }
            
            // 3. Check if user already booked this event
            if ($this->userHasBooking($data['user_id'], $data['event_id'])) {
                return ['success' => false, 'message' => 'You have already booked this event'];
            }
            
            // 4. Generate unique booking reference
            $bookingReference = $this->generateBookingReference();
            
            // 5. Create booking record
            $bookingId = $this->insertBooking([
                'event_id' => $data['event_id'],
                'user_id' => $data['user_id'],
                'booking_reference' => $bookingReference,
                'participant_name' => $data['participant_name'],
                'participant_email' => $data['participant_email'] ?? null,
                'participant_phone' => $data['participant_phone'] ?? null,
                'participant_age' => $data['participant_age'] ?? null,
                'player_type' => $data['player_type'] ?? 'self',
                'amount_paid' => $event['entry_fee'],
                'booking_status' => 'pending',
                'payment_status' => 'pending'
            ]);
            
            if (!$bookingId) {
                return ['success' => false, 'message' => 'Failed to create booking'];
            }
            
            // 6. Send confirmation email (even for pending bookings)
            $this->sendBookingConfirmationEmail($bookingId);
            
            return [
                'success' => true,
                'message' => 'Booking created successfully',
                'booking_id' => $bookingId,
                'booking_reference' => $bookingReference,
                'amount' => $event['entry_fee']
            ];
            
        } catch (Exception $e) {
            error_log("Booking Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'System error. Please try again.'];
        }
    }
    
    /**
     * Confirm booking after payment
     */
    public function confirmBooking($bookingId, $transactionId = null) {
        try {
            $sql = "UPDATE bookings SET 
                    booking_status = 'confirmed',
                    payment_status = 'paid',
                    updated_at = NOW()
                    WHERE booking_id = ?";
            
            $this->db->query($sql, [$bookingId]);
            
            // Send confirmation email
            $this->sendBookingConfirmationEmail($bookingId, true);
            
            return ['success' => true];
        } catch (Exception $e) {
            error_log("Confirm Booking Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to confirm booking'];
        }
    }
    
    /**
     * Cancel a booking
     */
    public function cancelBooking($bookingId, $userId) {
        try {
            // Verify booking belongs to user
            $sql = "SELECT * FROM bookings WHERE booking_id = ? AND user_id = ?";
            $booking = $this->db->query($sql, [$bookingId, $userId])->fetch();
            
            if (!$booking) {
                return ['success' => false, 'message' => 'Booking not found'];
            }
            
            if ($booking['booking_status'] === 'cancelled') {
                return ['success' => false, 'message' => 'Booking already cancelled'];
            }
            
            if ($booking['booking_status'] === 'completed') {
                return ['success' => false, 'message' => 'Cannot cancel completed booking'];
            }
            
            // Update booking status
            $sql = "UPDATE bookings SET 
                    booking_status = 'cancelled',
                    updated_at = NOW()
                    WHERE booking_id = ?";
            
            $this->db->query($sql, [$bookingId]);
            
            // If payment was made, initiate refund process
            if ($booking['payment_status'] === 'paid') {
                $this->initiateRefund($bookingId);
            }
            
            // Send cancellation email
            $this->sendCancellationEmail($bookingId);
            
            return ['success' => true, 'message' => 'Booking cancelled successfully'];
            
        } catch (Exception $e) {
            error_log("Cancel Booking Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to cancel booking'];
        }
    }
    
    /**
     * Get event details
     */
    private function getEventDetails($eventId) {
        $sql = "SELECT * FROM events WHERE event_id = ?";
        return $this->db->query($sql, [$eventId])->fetch();
    }
    
    /**
     * Check if user already has a booking for this event
     */
    private function userHasBooking($userId, $eventId) {
        $sql = "SELECT COUNT(*) as count FROM bookings 
                WHERE user_id = ? AND event_id = ? 
                AND booking_status IN ('pending', 'confirmed')";
        
        $result = $this->db->query($sql, [$userId, $eventId])->fetch();
        return $result['count'] > 0;
    }
    
    /**
     * Generate unique booking reference
     */
    private function generateBookingReference() {
        $prefix = 'CC';
        $timestamp = date('ymd');
        $random = strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
        return $prefix . $timestamp . $random;
    }
    
    /**
     * Insert booking into database
     */
    private function insertBooking($data) {
        $sql = "INSERT INTO bookings (
                    event_id, user_id, booking_reference, participant_name,
                    participant_email, participant_phone, participant_age,
                    player_type, amount_paid, booking_status, payment_status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $this->db->query($sql, [
            $data['event_id'],
            $data['user_id'],
            $data['booking_reference'],
            $data['participant_name'],
            $data['participant_email'],
            $data['participant_phone'],
            $data['participant_age'],
            $data['player_type'],
            $data['amount_paid'],
            $data['booking_status'],
            $data['payment_status']
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Get booking details by ID
     */
    public function getBookingDetails($bookingId) {
        $sql = "SELECT b.*, e.event_name, e.event_date, e.event_time, 
                e.location, e.venue_address, u.full_name as booked_by, u.email as user_email
                FROM bookings b
                JOIN events e ON b.event_id = e.event_id
                JOIN users u ON b.user_id = u.user_id
                WHERE b.booking_id = ?";
        
        return $this->db->query($sql, [$bookingId])->fetch();
    }
    
    /**
     * Get booking by reference
     */
    public function getBookingByReference($reference) {
        $sql = "SELECT b.*, e.event_name, e.event_date, e.event_time, 
                e.location, e.venue_address
                FROM bookings b
                JOIN events e ON b.event_id = e.event_id
                WHERE b.booking_reference = ?";
        
        return $this->db->query($sql, [$reference])->fetch();
    }
    
    /**
     * Send booking confirmation email
     */
    private function sendBookingConfirmationEmail($bookingId, $isPaid = false) {
        try {
            $booking = $this->getBookingDetails($bookingId);
            
            $subject = ($isPaid ? 'Payment Confirmed' : 'Booking Pending') . ' - ' . $booking['event_name'];
            
            $message = "
            <html>
            <body style='font-family: Arial, sans-serif;'>
                <h2>Crystal Chess Tournament Booking</h2>
                <p>Dear {$booking['booked_by']},</p>
                
                <p>Your booking for <strong>{$booking['event_name']}</strong> has been " . 
                ($isPaid ? 'confirmed and payment received' : 'created successfully') . ".</p>
                
                <div style='background: #f5f5f5; padding: 20px; margin: 20px 0;'>
                    <h3>Booking Details</h3>
                    <p><strong>Booking Reference:</strong> {$booking['booking_reference']}</p>
                    <p><strong>Participant:</strong> {$booking['participant_name']}</p>
                    <p><strong>Event Date:</strong> {$booking['event_date']} at {$booking['event_time']}</p>
                    <p><strong>Location:</strong> {$booking['location']}</p>
                    <p><strong>Amount:</strong> $" . number_format($booking['amount_paid'], 2) . "</p>
                    <p><strong>Status:</strong> " . ucfirst($booking['booking_status']) . "</p>
                </div>
                
                " . (!$isPaid ? "<p><strong>Note:</strong> Please complete your payment to confirm this booking.</p>" : "") . "
                
                <p>If you have any questions, please contact us.</p>
                
                <p>Best regards,<br>Crystal Chess Team</p>
            </body>
            </html>
            ";
            
            $this->mailer->send($booking['user_email'], $subject, $message);
            
        } catch (Exception $e) {
            error_log("Email Error: " . $e->getMessage());
        }
    }
    
    /**
     * Send cancellation email
     */
    private function sendCancellationEmail($bookingId) {
        try {
            $booking = $this->getBookingDetails($bookingId);
            
            $subject = 'Booking Cancelled - ' . $booking['event_name'];
            
            $message = "
            <html>
            <body style='font-family: Arial, sans-serif;'>
                <h2>Booking Cancellation Confirmation</h2>
                <p>Dear {$booking['booked_by']},</p>
                
                <p>Your booking for <strong>{$booking['event_name']}</strong> has been cancelled.</p>
                
                <div style='background: #f5f5f5; padding: 20px; margin: 20px 0;'>
                    <p><strong>Booking Reference:</strong> {$booking['booking_reference']}</p>
                    <p><strong>Event:</strong> {$booking['event_name']}</p>
                    <p><strong>Event Date:</strong> {$booking['event_date']}</p>
                </div>
                
                <p>If a payment was made, a refund will be processed within 5-7 business days.</p>
                
                <p>Best regards,<br>Crystal Chess Team</p>
            </body>
            </html>
            ";
            
            $this->mailer->send($booking['user_email'], $subject, $message);
            
        } catch (Exception $e) {
            error_log("Email Error: " . $e->getMessage());
        }
    }
    
    /**
     * Initiate refund (placeholder - implement with payment gateway)
     */
    private function initiateRefund($bookingId) {
        // TODO: Implement actual refund logic with payment gateway
        $sql = "UPDATE bookings SET payment_status = 'refunded' WHERE booking_id = ?";
        $this->db->query($sql, [$bookingId]);
    }
}