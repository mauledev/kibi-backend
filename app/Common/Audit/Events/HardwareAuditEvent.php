<?php

namespace App\Common\Audit\Events;

/**
 * Auditable events for the Hardware module — NFC/biometric gateway and access.
 */
enum HardwareAuditEvent: string implements AuditEvent
{
    case DEVICE_REGISTER = 'device.register';
    case DEVICE_DEACTIVATE = 'device.deactivate';
    case BIOMETRIC_ENROLL = 'biometric.enroll';
    case ACCESS_DENY = 'access.deny';
}
