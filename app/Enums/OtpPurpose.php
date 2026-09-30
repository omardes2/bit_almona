<?php

namespace App\Enums;

enum OtpPurpose: string
{
    case PasswordReset = 'password_reset';
    case PhoneChange = 'phone_change';
    case PhoneVerification = 'phone_verification';
}
