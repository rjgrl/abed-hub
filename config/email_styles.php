<?php
/**
 * Inline CSS blocks for transactional HTML emails (password recovery, etc.).
 * Templates live in handlers; styles mirror the app palette where practical.
 */

function email_transactional_css_recovery(): string
{
    return <<<'CSS'
body { font-family: Arial, sans-serif; }
.container { max-width: 600px; margin: 0 auto; background: #f5f7fa; padding: 20px; border-radius: 10px; }
.header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
.content { background: white; padding: 30px; border-radius: 0 0 10px 10px; }
.code-box { background: #f0f2f5; padding: 20px; text-align: center; border-radius: 8px; margin: 20px 0; }
.code { font-size: 32px; font-weight: bold; color: #667eea; letter-spacing: 5px; }
.warning { color: #dc3545; font-size: 12px; margin-top: 10px; }
.footer { text-align: center; color: #999; font-size: 12px; margin-top: 20px; }
CSS;
}

function email_transactional_css_password_changed(): string
{
    return <<<'CSS'
body { font-family: Arial, sans-serif; }
.container { max-width: 600px; margin: 0 auto; background: #f5f7fa; padding: 20px; border-radius: 10px; }
.header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
.content { background: white; padding: 30px; border-radius: 0 0 10px 10px; }
.success-message { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 8px; margin: 20px 0; }
.footer { text-align: center; color: #999; font-size: 12px; margin-top: 20px; }
CSS;
}
