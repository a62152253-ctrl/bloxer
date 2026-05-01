<?php
/**
 * Email Helper - Team Invitations System
 * Handles sending team invitation emails
 */

class EmailHelper {
    
    /**
     * Send team invitation email
     */
    public static function sendTeamInvitation($team, $invitation, $inviter) {
        $subject = "You're invited to join {$team['name']} on Bloxer!";
        
        $invitation_url = "http://{$_SERVER['HTTP_HOST']}/controllers/collaboration/accept_invitation.php?token={$invitation['token']}";
        
        $html_body = self::getInvitationTemplate($team, $invitation, $inviter, $invitation_url);
        $text_body = self::getInvitationTextTemplate($team, $invitation, $inviter, $invitation_url);
        
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: Bloxer <noreply@bloxer.com>',
            'Reply-To: ' . $inviter['email']
        ];
        
        return mail($invitation['invited_email'], $subject, $html_body, implode("\r\n", $headers));
    }
    
    /**
     * HTML email template for team invitation
     */
    private static function getInvitationTemplate($team, $invitation, $inviter, $invitation_url) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Team Invitation - Bloxer</title>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                .button { display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .team-info { background: white; padding: 20px; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 30px; color: #666; font-size: 0.9em; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🚀 You're Invited!</h1>
                    <p>Join a team on Bloxer</p>
                </div>
                
                <div class='content'>
                    <h2>Hello!</h2>
                    <p><strong>{$inviter['username']}</strong> has invited you to join <strong>{$team['name']}</strong> on Bloxer!</p>
                    
                    <div class='team-info'>
                        <h3>{$team['name']}</h3>
                        <p>{$team['description']}</p>
                        <p><strong>Role:</strong> " . ucfirst($invitation['role']) . "</p>
                        <p><strong>Company:</strong> " . ($team['company_name'] ?: 'Independent Team') . "</p>
                    </div>
                    
                    <p>Click the button below to accept the invitation:</p>
                    
                    <div style='text-align: center;'>
                        <a href='{$invitation_url}' class='button'>Accept Invitation</a>
                    </div>
                    
                    <p><small>This invitation expires in 7 days.</small></p>
                    <p><small>If you didn't expect this invitation, you can safely ignore this email.</small></p>
                </div>
                
                <div class='footer'>
                    <p>Sent from Bloxer - The App Development Platform</p>
                    <p><a href='http://bloxer.com'>bloxer.com</a></p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Plain text email template
     */
    private static function getInvitationTextTemplate($team, $invitation, $inviter, $invitation_url) {
        return "
Team Invitation - Bloxer

Hello!

{$inviter['username']} has invited you to join {$team['name']} on Bloxer!

Team Details:
- Name: {$team['name']}
- Description: {$team['description']}
- Role: " . ucfirst($invitation['role']) . "
- Company: " . ($team['company_name'] ?: 'Independent Team') . "

To accept this invitation, visit:
{$invitation_url}

This invitation expires in 7 days.

If you didn't expect this invitation, you can safely ignore this email.

---
Sent from Bloxer - The App Development Platform
http://bloxer.com
        ";
    }
    
    /**
     * Send welcome email when someone joins a team
     */
    public static function sendTeamWelcomeEmail($team, $member, $inviter) {
        $subject = "Welcome to {$team['name']}!";
        
        $html_body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Welcome to Team - Bloxer</title>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                .footer { text-align: center; margin-top: 30px; color: #666; font-size: 0.9em; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎉 Welcome!</h1>
                    <p>You're now part of the team</p>
                </div>
                
                <div class='content'>
                    <h2>Congratulations!</h2>
                    <p>You've successfully joined <strong>{$team['name']}</strong> on Bloxer!</p>
                    
                    <p>What's next:</p>
                    <ul>
                        <li>Visit your team dashboard to start collaborating</li>
                        <li>Introduce yourself to team members</li>
                        <li>Check out ongoing projects</li>
                    </ul>
                    
                    <p>Team members can now:</p>
                    <ul>
                        <li>Share projects and code</li>
                        <li>Review each other's work</li>
                        <li>Manage deployments together</li>
                    </ul>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='http://{$_SERVER['HTTP_HOST']}/controllers/core/dashboard.php?page=teams' style='display: inline-block; background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px;'>Go to Team Dashboard</a>
                    </div>
                </div>
                
                <div class='footer'>
                    <p>Happy coding! 🚀</p>
                    <p>Sent from Bloxer - The App Development Platform</p>
                </div>
            </div>
        </body>
        </html>";
        
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: Bloxer <noreply@bloxer.com>'
        ];
        
        return mail($member['email'], $subject, $html_body, implode("\r\n", $headers));
    }
    
    /**
     * Generate secure invitation token
     */
    public static function generateInvitationToken() {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Validate email format
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
