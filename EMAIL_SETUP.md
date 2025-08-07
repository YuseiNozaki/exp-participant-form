# Email Configuration for Production (Heroku)

## Issue Fixed
Fixed the issue where confirmation emails were not being sent in production due to `/usr/sbin/sendmail: not found` error on Heroku.

## Solution
Replaced PHP's `mail()` function with PHPMailer library that supports SMTP authentication, which works reliably on Heroku.

## Required Environment Variables for Heroku

Set these environment variables in your Heroku app configuration:

```bash
# Using Heroku CLI
heroku config:set SMTP_HOST=smtp.gmail.com
heroku config:set SMTP_PORT=587
heroku config:set SMTP_USERNAME=your-email@gmail.com
heroku config:set SMTP_PASSWORD=your-app-password
heroku config:set SMTP_FROM=your-email@gmail.com
```

Or set them via the Heroku Dashboard under Settings > Config Vars.

## SMTP Providers

### Gmail
- **SMTP_HOST**: `smtp.gmail.com`
- **SMTP_PORT**: `587`
- **SMTP_USERNAME**: Your Gmail address
- **SMTP_PASSWORD**: Use an [App Password](https://support.google.com/accounts/answer/185833)
- **SMTP_FROM**: Your Gmail address

### SendGrid (Recommended for production)
- **SMTP_HOST**: `smtp.sendgrid.net`
- **SMTP_PORT**: `587`
- **SMTP_USERNAME**: `apikey`
- **SMTP_PASSWORD**: Your SendGrid API key
- **SMTP_FROM**: Your verified sender email

### Other providers
The solution works with any SMTP provider. Just configure the appropriate host, port, and credentials.

## Testing

After setting the environment variables, you can test email functionality using:

```bash
php scripts/test_email.php
```

## Backward Compatibility

The solution maintains backward compatibility:
- If SMTP environment variables are not set, it falls back to local `mail()` function
- Existing code continues to work without modification