<?php
require_once __DIR__ . '/../src/Slot.php';
require_once __DIR__ . '/../src/Participant.php';
require_once __DIR__ . '/../src/Reservation.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Email service for handling reservation confirmations and reminders
 */
class EmailService {
    private $reservationModel;
    private $slotModel;
    
    public function __construct($reservationModel = null, $slotModel = null) {
        $this->reservationModel = $reservationModel ?: new Reservation();
        $this->slotModel = $slotModel ?: new Slot();
    }
    
    /**
     * Configure PHPMailer with SMTP settings
     * Uses environment variables for Heroku compatibility
     */
    private function createMailer() {
        $mail = new PHPMailer(true);
        
        try {
            // Server settings
            if (getenv('SMTP_HOST')) {
                // Use SMTP for production (Heroku)
                $mail->isSMTP();
                $mail->Host       = getenv('SMTP_HOST');
                $mail->SMTPAuth   = true;
                $mail->Username   = getenv('SMTP_USERNAME');
                $mail->Password   = getenv('SMTP_PASSWORD');
                $mail->SMTPSecure = getenv('SMTP_SECURE') ?: PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = getenv('SMTP_PORT') ?: 587;
            } else {
                // Use local mail for development
                $mail->isMail();
            }
            
            // Default sender
            $mail->setFrom(
                getenv('SMTP_FROM') ?: 'r241004y@st.u-gakugei.ac.jp',
                '野﨑優晴'
            );
            $mail->addReplyTo(
                getenv('SMTP_FROM') ?: 'r241004y@st.u-gakugei.ac.jp',
                '野﨑優晴'
            );
            
            // Content
            $mail->isHTML(false);
            $mail->CharSet = 'UTF-8';
            
        } catch (Exception $e) {
            error_log("PHPMailer configuration error: " . $e->getMessage());
            throw $e;
        }
        
        return $mail;
    }
    
    /**
     * Configure SMTP settings for PHP mail() function
     * Uses environment variables for Heroku compatibility
     * @deprecated - replaced by createMailer() method
     */
    private function configureSMTP() {
        // Check if we're in a production environment (Heroku)
        if (getenv('SMTP_HOST') !== false) {
            // Configure SMTP settings from environment variables
            ini_set('SMTP', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
            ini_set('smtp_port', getenv('SMTP_PORT') ?: '587');
            ini_set('sendmail_from', getenv('SMTP_FROM') ?: 'r241004y@st.u-gakugei.ac.jp');
            
            // For Windows systems or when sendmail_path is supported
            if (getenv('SMTP_USERNAME') && getenv('SMTP_PASSWORD')) {
                $sendmailPath = sprintf(
                    '"%s" -t -i -f %s',
                    getenv('SENDMAIL_PATH') ?: '/usr/sbin/sendmail',
                    getenv('SMTP_FROM') ?: 'r241004y@st.u-gakugei.ac.jp'
                );
                ini_set('sendmail_path', $sendmailPath);
            }
        }
        
        // Log the configuration for debugging
        error_log("SMTP configured - Host: " . ini_get('SMTP') . ", Port: " . ini_get('smtp_port'));
    }
    
    /**
     * Send confirmation email for a new reservation
     */
    public function sendConfirmationEmail($email, $name, $slotId) {
        $slot = $this->slotModel->findById($slotId);
        if (!$slot) {
            throw new Exception('Slot not found');
        }
        
        try {
            $mail = $this->createMailer();
            
            $mail->addAddress($email, $name);
            $mail->Subject = "【研究参加】予約確認 - " . $this->formatDate($slot['date']) . " " . $this->formatTime($slot['start_time']);
            $mail->Body = $this->buildConfirmationMessage($name, $slot);
            
            $result = $mail->send();
            
            if ($result) {
                error_log("Confirmation email sent successfully to: $email");
            } else {
                error_log("Failed to send confirmation email to: $email");
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Failed to send confirmation email to: $email - " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Send reminder emails for tomorrow's reservations
     * This should be called by a cron job daily at 12:00
     */
    public function sendReminderEmails() {
        $tomorrowReservations = $this->reservationModel->getTomorrowReservations();
        $sentCount = 0;
        $errorCount = 0;
        
        foreach ($tomorrowReservations as $reservation) {
            try {
                $this->sendReminderEmail($reservation);
                $sentCount++;
            } catch (Exception $e) {
                $errorCount++;
                error_log("Failed to send reminder email to {$reservation['email']}: " . $e->getMessage());
            }
        }
        
        // Log summary
        error_log("Reminder emails sent: $sentCount, errors: $errorCount");
        
        return [
            'sent' => $sentCount,
            'errors' => $errorCount,
            'total' => count($tomorrowReservations)
        ];
    }
    
    /**
     * Send individual reminder email
     */
    private function sendReminderEmail($reservation) {
        try {
            $mail = $this->createMailer();
            
            $mail->addAddress($reservation['email'], $reservation['name']);
            $mail->Subject = "【研究参加】明日の研究参加のご案内 - " . $this->formatDate($reservation['date']) . " " . $this->formatTime($reservation['start_time']);
            $mail->Body = $this->buildReminderMessage($reservation);
            
            $result = $mail->send();
            
            if (!$result) {
                throw new Exception("Failed to send email");
            }
            
            return $result;
            
        } catch (Exception $e) {
            throw new Exception("Failed to send reminder email: " . $e->getMessage());
        }
    }
    
    /**
     * Build confirmation email message
     */
    private function buildConfirmationMessage($name, $slot) {
        $date = $this->formatDate($slot['date']);
        $time = $this->formatTime($slot['start_time']);
        
        return "
{$name} 様

研究にご参加いただき、ありがとうございます。
以下の内容で予約を確認いたしました。

【予約詳細】
実験日時: {$date} {$time}〜（約30分）
実験形式: オンライン（Zoomを使用予定）

【実験前の準備】
・自室など、30分間は研究に集中できる環境をご用意ください。
・PCを使用しますのでご準備ください。

【重要事項】
・開始時間になりましたら、Zoomリンクにアクセスしてください。
・ZoomのカメラはOFFで構いません。実験の説明のために、画面共有をお願いすることがあります。
・途中での離脱は可能ですが、データが無効になる場合があります。

【Zoomリンク】
https://us06web.zoom.us/j/9018517024?pwd=pGkkBhvadpfedA20hrlXKF3PHvBN8g.1

ミーティングID: 901 851 7024
パスコード: 4GMEGL

【予約の変更・キャンセル】
・予約の変更やキャンセルは、お手数ですが、以下の連絡先までご連絡ください。

【お問い合わせ】
ご質問や予約変更などがございましたら、以下までご連絡ください。
メール: r241004y@st.u-gakugei.ac.jp
電話: 090-2236-0330

ご参加をお待ちしております。

東京学芸大学大学院 連合学校教育学研究科 博士課程
野﨑優晴
        ";
    }
    
    /**
     * Build reminder email message
     */
    private function buildReminderMessage($reservation) {
        $date = $this->formatDate($reservation['date']);
        $time = $this->formatTime($reservation['start_time']);
        
        return "
{$reservation['name']} 様

いよいよ明日は研究参加の日です。
改めて詳細をご案内いたします。

【予約詳細】
実験日時: {$date} {$time}〜（約30分間）
実験形式: オンライン（Zoom）

【Zoomリンク】
https://us06web.zoom.us/j/9018517024?pwd=pGkkBhvadpfedA20hrlXKF3PHvBN8g.1

ミーティングID: 901 851 7024
パスコード: 4GMEGL

【重要な注意事項】
✓ 実験開始時間になりましたら、Zoomにアクセスしてください。
✓ ZoomのカメラはOFFで構いません。実験の説明のために、画面共有をお願いすることがあります。
✓ 静かな環境でご参加ください。

【緊急連絡先】
予約のキャンセルや日程変更は、以下までご連絡ください。
電話: 090-2236-0330
メール: r241004y@st.u-gakugei.ac.jp

お忙しい中お時間をいただき、ありがとうございます。
明日お会いできることを楽しみにしております。

東京学芸大学大学院 連合学校教育学研究科 博士課程
野﨑優晴
        ";
    }
    
    /**
     * Build email headers
     */
    private function buildEmailHeaders() {
        return implode("\r\n", [
            "From: 野﨑優晴 <r241004y@st.u-gakugei.ac.jp>",
            "Reply-To: r241004y@st.u-gakugei.ac.jp",
            "Content-Type: text/plain; charset=UTF-8",
            "X-Mailer: PHP/" . phpversion()
        ]);
    }
    
    /**
     * Format date for display
     */
    private function formatDate($dateString) {
        $date = new DateTime($dateString);
        return $date->format('Y年n月j日（') . ['日', '月', '火', '水', '木', '金', '土'][$date->format('w')] . '）';
    }
    
    /**
     * Format time for display
     */
    private function formatTime($timeString) {
        $time = new DateTime("2000-01-01 $timeString");
        return $time->format('H:i');
    }
}
?>