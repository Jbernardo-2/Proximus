<?php

namespace App;

enum SecurityEvent: string
{
    case LoginFailed = 'auth.login_failed';
    case LoginDenied = 'auth.login_denied';
    case LoginSucceeded = 'auth.login_succeeded';
    case Logout = 'auth.logout';
    case TokenIssued = 'auth.token_issued';
    case TokenRevoked = 'auth.token_revoked';
    case DeviceRevoked = 'devices.revoked';
    case DeviceRestored = 'devices.restored';
    case UserCreated = 'users.created';
    case UserUpdated = 'users.updated';
    case CompanyProfileUpdated = 'company_profile.updated';
}
