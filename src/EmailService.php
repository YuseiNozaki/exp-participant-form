<?php
require_once __DIR__ . '/../src/Slot.php';
require_once __DIR__ . '/../src/Participant.php';
require_once __DIR__ . '/../src/Reservation.php';

/**
 * Email service for handling reservation confirmations and reminders
 */
class EmailService {
    private $reservationModel;
    private $slotModel;
    
    public function __construct() {
        $this->reservationModel = new Reservation();
        $this->slotModel = new Slot();
    }
    
    /**
     * Send confirmation email for a new reservation
     */
    public function sendConfirmationEmail($email, $name, $slotId) {
        $slot = $this->slotModel->findById($slotId);
        if (!$slot) {
            throw new Exception('Slot not found');
        }
        
        $subject = "【視覚探索実験】予約確認 - " . $this->formatDate($slot['date']) . " " . $this->formatTime($slot['start_time']);
        
        $message = $this->buildConfirmationMessage($name, $slot);
        $headers = $this->buildEmailHeaders();
        
        $result = mail($email, $subject, $message, $headers);
        
        if (!$result) {
            error_log("Failed to send confirmation email to: $email");
        }
        
        return $result;
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
        $subject = "【視覚探索実験】明日の実験のご案内 - " . $this->formatDate($reservation['date']) . " " . $this->formatTime($reservation['start_time']);
        
        $message = $this->buildReminderMessage($reservation);
        $headers = $this->buildEmailHeaders();
        
        $result = mail($reservation['email'], $subject, $message, $headers);
        
        if (!$result) {
            throw new Exception("Failed to send email");
        }
        
        return $result;
    }
    
    /**
     * Build confirmation email message
     */
    private function buildConfirmationMessage($name, $slot) {
        $date = $this->formatDate($slot['date']);
        $time = $this->formatTime($slot['start_time']);
        
        return "
{$name} 様

視覚探索実験にご参加いただき、ありがとうございます。
以下の内容で予約を確認いたしました。

【予約詳細】
実験日時: {$date} {$time}〜（約1時間）
実験形式: オンライン（Zoomを使用予定）

【実験前の準備】
・静かな環境をご用意ください
・安定したインターネット接続を確保してください
・PCまたはタブレット（スマートフォンは推奨しません）
・イヤホンまたはヘッドフォン（推奨）

【重要事項】
・実験開始の5分前にはZoomリンクにアクセスしてください
・実験は約1時間を予定しています
・途中での離脱は可能ですが、データが無効になる場合があります

【予約の変更・キャンセル】
予約の変更・キャンセルは実験前日まで可能です。
当日の変更は直接お電話でご連絡ください。

【Zoomリンク】
実験前日に別途お送りいたします。

【お問い合わせ】
ご質問等がございましたら、以下までご連絡ください。
メール: experiment@university.edu
電話: 03-1234-5678（平日9:00-17:00）

実験前日にも再度ご案内メールをお送りします。
ご参加をお待ちしております。

視覚探索実験チーム
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

いよいよ明日は視覚探索実験の日です。
改めて実験詳細をご案内いたします。

【実験詳細】
実験日時: {$date} {$time}〜（約1時間）
実験形式: オンライン（Zoom）

【Zoomリンク】
https://zoom.us/j/1234567890
ミーティングID: 123 456 7890
パスコード: experiment

【重要な注意事項】
✓ 実験開始の5分前にはZoomにアクセスしてください
✓ 静かな環境でご参加ください
✓ PCまたはタブレットを推奨します
✓ イヤホン・ヘッドフォンを着用してください
✓ 約1時間の時間を確保してください

【準備チェックリスト】
□ 安定したインターネット接続
□ 静かな実験環境
□ PC/タブレットの準備
□ イヤホン/ヘッドフォンの準備
□ 約1時間の時間確保

【緊急連絡先】
実験当日にトラブルが発生した場合は、以下までご連絡ください。
電話: 03-1234-5678
メール: experiment@university.edu

お忙しい中お時間をいただき、ありがとうございます。
明日お会いできることを楽しみにしております。

視覚探索実験チーム
        ";
    }
    
    /**
     * Build email headers
     */
    private function buildEmailHeaders() {
        return implode("\r\n", [
            "From: 視覚探索実験チーム <noreply@experiment.university.edu>",
            "Reply-To: experiment@university.edu",
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