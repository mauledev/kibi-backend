<?php

namespace App\Common\Audit\Events;

/**
 * Auditable events for the Auth module — login, session and credential actions.
 */
enum AuthAuditEvent: string implements AuditEvent
{
    case LOGIN = 'auth.login';
    case LOGIN_FAILED = 'auth.login_failed';
    case OAUTH_LOGIN = 'auth.oauth_login';
    case LOGOUT = 'auth.logout';
    case PASSWORD_RESET = 'auth.password_reset';
    case ACCOUNT_ACTIVATE = 'auth.account_activate';
}
