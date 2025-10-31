<?php
/**
 * Mailer Class - Email Service
 * File: core/Mailer.php
 * UPDATED with Refund Notification
 */

class Mailer {
    private $fromEmail;
    private $fromName;
    
    public function __construct() {
        $this->fromEmail = 'noreply@crystalchess.com';
        $this->fromName = 'Crystal Chess';
    }
    
    /**
     * Send booking confirmation email
     */
    public function sendBookingConfirmation($data) {
        $to = $data['email'];
        $subject = 'Booking Confirmed - ' . $data['event_name'];
        
        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
                .content { background: #f9f9f9; padding: 30px; }
                .booking-ref { background: white; padding: 20px; margin: 20px 0; border-left: 4px solid #667eea; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎉 Booking Confirmed!</h1>
                </div>
                <div class='content'>
                    <p>Dear {$data['name']},</p>
                    <p>Your booking for <strong>{$data['event_name']}</strong> has been confirmed!</p>
                    
                    <div class='booking-ref'>
                        <h3>Booking Reference</h3>
                        <h2 style='color: #667eea; margin: 0;'>{$data['booking_reference']}</h2>
                    </div>
                    
                    <p>Please keep this reference number for your records. You can view your booking details anytime in your dashboard.</p>
                    
                    <p><strong>What's Next?</strong></p>
                    <ul>
                        <li>You'll receive a reminder email 2 days before the event</li>
                        <li>Check your dashboard for event updates</li>
                        <li>Bring your booking reference on the event day</li>
                    </ul>
                    
                    <p>See you at the tournament!</p>
                    <p><strong>Crystal Chess Team</strong></p>
                </div>
                <div class='footer'>
                    <p>&copy; 2025 Crystal Chess. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->send($to, $subject, $message);
    }
    
    /**
     * Send refund notification email
     */
    public function sendRefundNotification($data) {
        $to = $data['email'];
        $subject = 'Refund Processed - Crystal Chess';
        
        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); color: white; padding: 30px; text-align: center; }
                .content { background: #f9f9f9; padding: 30px; }
                .refund-box { background: white; padding: 20px; margin: 20px 0; border-left: 4px solid #f97316; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>↩️ Refund Processed</h1>
                </div>
                <div class='content'>
                    <p>Dear {$data['name']},</p>
                    <p>Your refund has been successfully processed.</p>
                    
                    <div class='refund-box'>
                        <h3>Refund Details</h3>
                        " . (isset($data['booking_reference']) ? "<p><strong>Booking Reference:</strong> {$data['booking_reference']}</p>" : "") . "
                        " . (isset($data['event_name']) ? "<p><strong>Event:</strong> {$data['event_name']}</p>" : "") . "
                        <p><strong>Refund Amount:</strong> <span style='color: #f97316; font-size: 24px; font-weight: bold;'>$" . number_format($data['amount'], 2) . "</span></p>
                    </div>
                    
                    <p><strong>What Happens Next?</strong></p>
                    <ul>
                        <li>The refund will appear in your account within 5-10 business days</li>
                        <li>The exact timing depends on your bank or payment provider</li>
                        <li>You'll see the refund on the original payment method used</li>
                    </ul>
                    
                    <p>If you have any questions, please don't hesitate to contact our support team.</p>
                    
                    <p>We hope to see you at future events!</p>
                    <p><strong>Crystal Chess Team</strong></p>
                </div>
                <div class='footer'>
                    <p>&copy; 2025 Crystal Chess. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->send($to, $subject, $message);
    }
    
    /**
     * Send password reset email
     */
    public function sendPasswordReset($data) {
        $to = $data['email'];
        $subject = 'Password Reset - Crystal Chess';
        
        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
                .content { background: #f9f9f9; padding: 30px; }
                .button { display: inline-block; padding: 15px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔒 Password Reset Request</h1>
                </div>
                <div class='content'>
                    <p>Dear {$data['name']},</p>
                    <p>We received a request to reset your password. Click the button below to create a new password:</p>
                    
                    <div style='text-align: center;'>
                        <a href='{$data['reset_link']}' class='button'>Reset Password</a>
                    </div>
                    
                    <p><strong>This link will expire in 1 hour.</strong></p>
                    
                    <p>If you didn't request this password reset, please ignore this email or contact support if you have concerns.</p>
                    
                    <p>Best regards,<br><strong>Crystal Chess Team</strong></p>
                </div>
                <div class='footer'>
                    <p>&copy; 2025 Crystal Chess. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->send($to, $subject, $message);
    }
    
    /**
     * Send event reminder email
     */
    public function sendEventReminder($data) {
        $to = $data['email'];
        $subject = 'Event Reminder - ' . $data['event_name'];
        
        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
                .content { background: #f9f9f9; padding: 30px; }
                .event-box { background: white; padding: 20px; margin: 20px 0; border-left: 4px solid #667eea; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>⏰ Event Reminder</h1>
                </div>
                <div class='content'>
                    <p>Dear {$data['name']},</p>
                    <p>This is a friendly reminder that your chess tournament is coming up soon!</p>
                    
                    <div class='event-box'>
                        <h3>{$data['event_name']}</h3>
                        <p><strong>📅 Date:</strong> {$data['event_date']}</p>
                        <p><strong>📍 Location:</strong> {$data['location']}</p>
                    </div>
                    
                    <p><strong>Reminder:</strong></p>
                    <ul>
                        <li>Arrive 15-30 minutes early for registration</li>
                        <li>Bring your booking reference</li>
                        <li>Review the tournament rules beforehand</li>
                    </ul>
                    
                    <p>We look forward to seeing you there!</p>
                    <p><strong>Crystal Chess Team</strong></p>
                </div>
                <div class='footer'>
                    <p>&copy; 2025 Crystal Chess. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->send($to, $subject, $message);
    }
    
    /**
     * Core send function
     */
    private function send($to, $subject, $message) {
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: {$this->fromName} <{$this->fromEmail}>" . "\r\n";
        
        // In production, use PHPMailer or a service like SendGrid
        // For demo, using mail() function
        $sent = mail($to, $subject, $message, $headers);
        
        // Log email
        error_log("Email sent to $to: $subject - " . ($sent ? 'Success' : 'Failed'));
        
        return $sent;
    }
    /**
 * Send email verification
 */
public function sendEmailVerification($data) {
    $to = $data['email'];
    $subject = 'Verify Your Email - Crystal Chess';
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
            .content { background: #f9f9f9; padding: 30px; }
            .button { display: inline-block; padding: 15px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🎉 Welcome to Crystal Chess!</h1>
            </div>
            <div class='content'>
                <p>Dear {$data['name']},</p>
                <p>Thank you for registering with Crystal Chess! Please verify your email address to activate your account.</p>
                
                <div style='text-align: center;'>
                    <a href='{$data['verification_link']}' class='button'>Verify Email Address</a>
                </div>
                
                <p>Or copy and paste this link into your browser:</p>
                <p style='background: #e9ecef; padding: 10px; word-break: break-all; font-size: 12px;'>
                    {$data['verification_link']}
                </p>
                
                <p><strong>This link will expire in 24 hours.</strong></p>
                
                <p>If you didn't create an account with Crystal Chess, please ignore this email.</p>
                
                <p>Best regards,<br><strong>Crystal Chess Team</strong></p>
            </div>
            <div class='footer'>
                <p>&copy; 2025 Crystal Chess. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return $this->send($to, $subject, $message);
}
    /**
     * Production-ready PHPMailer implementation (commented out for demo)
     */
    /*
    private function sendWithPHPMailer($to, $subject, $message) {
        require_once __DIR__ . '/../vendor/autoload.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com'; // Your SMTP host
            $mail->SMTPAuth = true;
            $mail->Username = 'your-email@gmail.com';
            $mail->Password = 'your-app-password';
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            
            // Recipients
            $mail->setFrom($this->fromEmail, $this->fromName);
            $mail->addAddress($to);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;
            
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("PHPMailer Error: {$mail->ErrorInfo}");
            return false;
        }
    }
    */
}