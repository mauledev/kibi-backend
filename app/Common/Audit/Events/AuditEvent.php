<?php

declare(strict_types=1);

namespace App\Common\Audit\Events;

/**
 * Marker contract for every per-module audit event enum.
 *
 * Audit events are string-backed enums grouped one per module under this
 * namespace. Extending BackedEnum lets AuditLogger and AuditEventRegistry
 * consume any module's events polymorphically (read `->value`, call `cases()`)
 * while each module keeps its own catalog. Values follow the `{model}.{verb}`
 * convention defined in docs/global-rules.md — see docs/audit.md.
 */
interface AuditEvent extends \BackedEnum {}
