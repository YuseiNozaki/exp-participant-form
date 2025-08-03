#!/usr/bin/env php
<?php
/**
 * Cron job script for sending reminder emails
 * Should be scheduled to run daily at 12:00
 * 
 * Add to crontab:
 * 0 12 * * * /usr/bin/php /path/to/your/project/scripts/send_reminders.php
 */

// Set the working directory to the project root
chdir(dirname(__DIR__));

require_once 'src/EmailService.php';

try {
    $emailService = new EmailService();
    $result = $emailService->sendReminderEmails();
    
    $output = sprintf(
        "[%s] Reminder emails processed: %d sent, %d errors, %d total\n",
        date('Y-m-d H:i:s'),
        $result['sent'],
        $result['errors'], 
        $result['total']
    );
    
    echo $output;
    error_log($output);
    
    // Exit with error code if there were failures
    if ($result['errors'] > 0) {
        exit(1);
    }
    
} catch (Exception $e) {
    $error = sprintf(
        "[%s] ERROR in reminder email cron job: %s\n",
        date('Y-m-d H:i:s'),
        $e->getMessage()
    );
    
    echo $error;
    error_log($error);
    exit(1);
}
?>