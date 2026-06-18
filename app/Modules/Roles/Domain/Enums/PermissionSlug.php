<?php

namespace App\Modules\Roles\Domain\Enums;

enum PermissionSlug: string
{
    // --- Role management ---
    case MANAGE_PERMISSIONS = 'manage.permissions';
    case ROLE_VIEW = 'role.view';
    case ROLE_ASSIGN = 'role.assign';
    case ROLE_REVOKE = 'role.revoke';

    // --- Custom roles ---
    case CUSTOM_ROLE_CREATE = 'custom_role.create';
    case CUSTOM_ROLE_UPDATE = 'custom_role.update';
    case CUSTOM_ROLE_DELETE = 'custom_role.delete';

    // --- Schools ---
    case SCHOOL_CREATE = 'school.create';
    case SCHOOL_VIEW = 'school.view';
    case SCHOOL_UPDATE = 'school.update';

    // --- Users ---
    case USER_VIEW = 'user.view';
    case USER_CREATE = 'user.create';
    case USER_UPDATE = 'user.update';
    case USER_SUSPEND = 'user.suspend';
    case USER_DELETE = 'user.delete';

    // --- Grades ---
    case GRADE_VIEW = 'grade.view';
    case GRADE_VIEW_ALL = 'grade.view_all';
    case GRADE_PUBLISH = 'grade.publish';
    case GRADE_CREATE = 'grade.create';
    case GRADE_UPDATE = 'grade.update';
    case GRADE_DELETE = 'grade.delete';

    // --- Groups ---
    case GROUP_VIEW = 'group.view';
    case GROUP_LIST = 'group.list';
    case GROUP_CREATE = 'group.create';
    case GROUP_UPDATE = 'group.update';
    case GROUP_DELETE = 'group.delete';

    // --- Payments ---
    case PAYMENT_VIEW = 'payment.view';
    case PAYMENT_APPROVE = 'payment.approve';
    case PAYMENT_CREATE = 'payment.create';
    case PAYMENT_UPDATE = 'payment.update';
    case PAYMENT_DELETE = 'payment.delete';
    case PAYMENT_REJECT = 'payment.reject';

    // --- Subjects & announcements ---
    case SUBJECT_MANAGE = 'subject.manage';
    case ANNOUNCEMENT_VIEW = 'announcement.view';
    case ANNOUNCEMENT_SEND = 'announcement.send';

    // --- Staff: billing ---
    case BILLING_VIEW = 'billing.view';
    case BILLING_APPROVE = 'billing.approve';
    case BILLING_REFUND = 'billing.refund';
    case BILLING_REVIEW = 'billing.review';
    case BILLING_RETURN = 'billing.return';
    case BILLING_METRICS = 'billing.metrics';

    // --- Staff: operations ---
    case REMITTANCE_CREATE = 'remittance.create';
    case BATCH_ASSIGN = 'batch.assign';
    case AUDIT_VIEW = 'audit.view';

    // --- Staff: support ---
    case TICKET_VIEW = 'ticket.view';
    case TICKET_CREATE = 'ticket.create';
    case TICKET_RESOLVE = 'ticket.resolve';
    case TICKET_ESCALATE = 'ticket.escalate';
    case TENANT_IMPERSONATE = 'tenant.impersonate';
    case TENANT_VIEW = 'tenant.view';
}
