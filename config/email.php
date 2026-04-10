<?php
/**
 * Email Configuration for ABED IDM Hub
 */

// Email settings
define('SMTP_HOST', 'smtp.gmail.com'); // Change to your SMTP server
define('SMTP_PORT', 587); // 587 for TLS, 465 for SSL
define('SMTP_USERNAME', 'your-email@gmail.com'); // Your email
define('SMTP_PASSWORD', 'your-app-password'); // App password for Gmail
define('SMTP_ENCRYPTION', 'tls'); // 'tls' or 'ssl'

define('FROM_EMAIL', 'noreply@abed-idm-hub.com');
define('FROM_NAME', 'ABED IDM Hub');

// Email templates
class EmailTemplates {
    public static function getAlertTemplate($alert_type, $project_code, $message, $severity) {
        $severity_colors = [
            'low' => '#17a2b8',
            'medium' => '#ffc107',
            'high' => '#fd7e14',
            'critical' => '#dc3545'
        ];

        $color = $severity_colors[$severity] ?? '#6c757d';

        return [
            'subject' => "ABED IDM Alert: " . ucfirst($alert_type) . " - " . $project_code,
            'html' => "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <div style='background-color: {$color}; color: white; padding: 20px; text-align: center;'>
                        <h2>ABED IDM Hub Alert</h2>
                        <h3>" . ucfirst($alert_type) . " Alert</h3>
                    </div>
                    <div style='padding: 20px; background-color: #f8f9fa;'>
                        <h4>Project: {$project_code}</h4>
                        <p><strong>Severity:</strong> " . ucfirst($severity) . "</p>
                        <p><strong>Message:</strong></p>
                        <div style='background-color: white; padding: 15px; border-left: 4px solid {$color}; margin: 10px 0;'>
                            {$message}
                        </div>
                        <p style='color: #6c757d; font-size: 14px;'>
                            This is an automated alert from the ABED IDM Hub system.
                        </p>
                    </div>
                    <div style='background-color: #343a40; color: white; padding: 10px; text-align: center; font-size: 12px;'>
                        <p>&copy; " . date('Y') . " ABED IDM Hub. All rights reserved.</p>
                    </div>
                </div>
            ",
            'text' => "
ABED IDM Hub Alert

Project: {$project_code}
Severity: " . ucfirst($severity) . "
Type: " . ucfirst($alert_type) . "

Message:
{$message}

This is an automated alert from the ABED IDM Hub system.
            "
        ];
    }

    public static function getMilestoneTemplate($project_code, $milestone_name, $due_date) {
        return [
            'subject' => "Milestone Due Soon: {$project_code} - {$milestone_name}",
            'html' => "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <div style='background-color: #0d6efd; color: white; padding: 20px; text-align: center;'>
                        <h2>Milestone Reminder</h2>
                    </div>
                    <div style='padding: 20px; background-color: #f8f9fa;'>
                        <h4>Project: {$project_code}</h4>
                        <p><strong>Milestone:</strong> {$milestone_name}</p>
                        <p><strong>Due Date:</strong> {$due_date}</p>
                        <div style='background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 10px 0; border-radius: 4px;'>
                            <strong>Action Required:</strong> Please review and update the milestone status.
                        </div>
                    </div>
                </div>
            ",
            'text' => "
Milestone Reminder

Project: {$project_code}
Milestone: {$milestone_name}
Due Date: {$due_date}

Action Required: Please review and update the milestone status.
            "
        ];
    }
}

/**
 * Send email using PHP mail() function (basic implementation)
 * For production, consider using PHPMailer or similar library
 */
function sendEmail($to, $subject, $html_content, $text_content = '') {
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>',
        'Reply-To: ' . FROM_EMAIL,
        'X-Mailer: PHP/' . phpversion()
    ];

    $message = $html_content;

    // Add text alternative if provided
    if ($text_content) {
        $boundary = md5(time());
        $headers[0] = 'MIME-Version: 1.0';
        $headers[1] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        $headers[2] = 'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>';

        $message = "--{$boundary}\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\n\n";
        $message .= $text_content . "\n\n";
        $message .= "--{$boundary}\n";
        $message .= "Content-Type: text/html; charset=UTF-8\n\n";
        $message .= $html_content . "\n\n";
        $message .= "--{$boundary}--";
    }

    return mail($to, $subject, $message, implode("\r\n", $headers));
}

/**
 * Send alert email to user
 */
function sendAlertEmail($user_email, $alert_type, $project_code, $message, $severity) {
    $template = EmailTemplates::getAlertTemplate($alert_type, $project_code, $message, $severity);
    return sendEmail($user_email, $template['subject'], $template['html'], $template['text']);
}

/**
 * Send milestone reminder email
 */
function sendMilestoneEmail($user_email, $project_code, $milestone_name, $due_date) {
    $template = EmailTemplates::getMilestoneTemplate($project_code, $milestone_name, $due_date);
    return sendEmail($user_email, $template['subject'], $template['html'], $template['text']);
}
?>