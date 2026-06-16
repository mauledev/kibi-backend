<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPermissionCategories();
        $this->seedPermissions();
        $this->seedRoles();
        $this->seedRolePermissions();
    }

    private function seedPermissionCategories(): void
    {
        $categories = [
            // Staff scope — Softlinkia internal operational roles
            ['scope' => 'staff', 'name' => 'support'],
            ['scope' => 'staff', 'name' => 'finance'],

            // Tenant scope — tenant-level operational roles
            ['scope' => 'tenant', 'name' => 'finance'],
            ['scope' => 'tenant', 'name' => 'hr'],

            // School scope — one category per school role
            ['scope' => 'school', 'name' => 'director'],
            ['scope' => 'school', 'name' => 'academic_coordinator'],
            ['scope' => 'school', 'name' => 'school_registrar'],
            ['scope' => 'school', 'name' => 'prefect'],
            ['scope' => 'school', 'name' => 'finance'],
            ['scope' => 'school', 'name' => 'hr'],
            ['scope' => 'school', 'name' => 'teacher'],
            ['scope' => 'school', 'name' => 'student'],
            ['scope' => 'school', 'name' => 'tutor'],
        ];

        foreach ($categories as $category) {
            DB::table('permission_categories')->insertOrIgnore([
                'uuid' => (string) Str::uuid(),
                'scope' => $category['scope'],
                'name' => $category['name'],
                'created_at' => now(),
            ]);
        }
    }

    private function seedPermissions(): void
    {
        $catId = fn (string $scope, string $name): int => (int) DB::table('permission_categories')
            ->where('scope', $scope)
            ->where('name', $name)
            ->value('id');

        $permissions = [
            // staff/finance — Softlinkia treasury (SaaS billing tenant → Softlinkia, distinct from school payment.*)
            ['scope' => 'staff', 'category' => 'finance', 'name' => 'View SaaS billing',          'slug' => 'billing.view'],
            ['scope' => 'staff', 'category' => 'finance', 'name' => 'Approve SaaS payments',      'slug' => 'billing.approve'],
            ['scope' => 'staff', 'category' => 'finance', 'name' => 'Refund SaaS payments',       'slug' => 'billing.refund'],
            ['scope' => 'staff', 'category' => 'finance', 'name' => 'Review SaaS payments',       'slug' => 'billing.review'],
            ['scope' => 'staff', 'category' => 'finance', 'name' => 'Return payment to operator', 'slug' => 'billing.return'],
            ['scope' => 'staff', 'category' => 'finance', 'name' => 'View billing metrics',       'slug' => 'billing.metrics'],
            ['scope' => 'staff', 'category' => 'finance', 'name' => 'Generate Owner remittances', 'slug' => 'remittance.create'],
            ['scope' => 'staff', 'category' => 'finance', 'name' => 'Assign batches to operators', 'slug' => 'batch.assign'],
            ['scope' => 'staff', 'category' => 'finance', 'name' => 'View audit log',             'slug' => 'audit.view'],

            // staff/support — Softlinkia support (tickets + temporary tenant linking)
            ['scope' => 'staff', 'category' => 'support', 'name' => 'View tickets',               'slug' => 'ticket.view'],
            ['scope' => 'staff', 'category' => 'support', 'name' => 'Create tickets',             'slug' => 'ticket.create'],
            ['scope' => 'staff', 'category' => 'support', 'name' => 'Resolve tickets',            'slug' => 'ticket.resolve'],
            ['scope' => 'staff', 'category' => 'support', 'name' => 'Escalate tickets',           'slug' => 'ticket.escalate'],
            ['scope' => 'staff', 'category' => 'support', 'name' => 'Temporary tenant linking',   'slug' => 'tenant.impersonate'],
            ['scope' => 'staff', 'category' => 'support', 'name' => 'View tenants',               'slug' => 'tenant.view'],

            // school/director — full school management
            ['scope' => 'school', 'category' => 'director', 'name' => 'View schools',           'slug' => 'school.view'],
            ['scope' => 'school', 'category' => 'director', 'name' => 'Update schools',         'slug' => 'school.update'],
            ['scope' => 'school', 'category' => 'director', 'name' => 'View roles',             'slug' => 'role.view'],
            ['scope' => 'school', 'category' => 'director', 'name' => 'Assign roles',           'slug' => 'role.assign'],
            ['scope' => 'school', 'category' => 'director', 'name' => 'Revoke roles',           'slug' => 'role.revoke'],
            ['scope' => 'school', 'category' => 'director', 'name' => 'Manage permissions',     'slug' => 'manage.permissions'],
            ['scope' => 'school', 'category' => 'director', 'name' => 'Create custom roles',    'slug' => 'roles.custom.create'],
            ['scope' => 'school', 'category' => 'director', 'name' => 'View users',             'slug' => 'user.view'],
            ['scope' => 'school', 'category' => 'director', 'name' => 'Create users',           'slug' => 'user.create'],
            ['scope' => 'school', 'category' => 'director', 'name' => 'Update users',           'slug' => 'user.update'],
            ['scope' => 'school', 'category' => 'director', 'name' => 'Suspend users',          'slug' => 'user.suspend'],
            ['scope' => 'school', 'category' => 'director', 'name' => 'Publish grades',         'slug' => 'grade.publish'],
            ['scope' => 'school', 'category' => 'director', 'name' => 'View grades',            'slug' => 'grade.view'],
            ['scope' => 'school', 'category' => 'director', 'name' => 'Approve payments',       'slug' => 'payment.approve'],
            ['scope' => 'school', 'category' => 'director', 'name' => 'View payments',          'slug' => 'payment.view'],
            ['scope' => 'school', 'category' => 'director', 'name' => 'Manage groups',          'slug' => 'group.manage'],
            ['scope' => 'school', 'category' => 'director', 'name' => 'View groups',            'slug' => 'group.view'],
            ['scope' => 'school', 'category' => 'director', 'name' => 'Manage subjects',        'slug' => 'subject.manage'],
            ['scope' => 'school', 'category' => 'director', 'name' => 'Send announcements',     'slug' => 'announcement.send'],
            ['scope' => 'school', 'category' => 'director', 'name' => 'View announcements',     'slug' => 'announcement.view'],

            // school/academic_coordinator
            ['scope' => 'school', 'category' => 'academic_coordinator', 'name' => 'Publish grades',     'slug' => 'academic_coordinator.grade.publish'],
            ['scope' => 'school', 'category' => 'academic_coordinator', 'name' => 'Create grades',      'slug' => 'academic_coordinator.grade.create'],
            ['scope' => 'school', 'category' => 'academic_coordinator', 'name' => 'Update grades',      'slug' => 'academic_coordinator.grade.update'],
            ['scope' => 'school', 'category' => 'academic_coordinator', 'name' => 'Delete grades',      'slug' => 'academic_coordinator.grade.delete'],
            ['scope' => 'school', 'category' => 'academic_coordinator', 'name' => 'View grades',        'slug' => 'academic_coordinator.grade.view'],
            ['scope' => 'school', 'category' => 'academic_coordinator', 'name' => 'Manage groups',      'slug' => 'academic_coordinator.group.manage'],
            ['scope' => 'school', 'category' => 'academic_coordinator', 'name' => 'View groups',        'slug' => 'academic_coordinator.group.view'],
            ['scope' => 'school', 'category' => 'academic_coordinator', 'name' => 'Manage subjects',    'slug' => 'academic_coordinator.subject.manage'],
            ['scope' => 'school', 'category' => 'academic_coordinator', 'name' => 'View users',         'slug' => 'academic_coordinator.user.view'],
            ['scope' => 'school', 'category' => 'academic_coordinator', 'name' => 'Send announcements', 'slug' => 'academic_coordinator.announcement.send'],
            ['scope' => 'school', 'category' => 'academic_coordinator', 'name' => 'View announcements', 'slug' => 'academic_coordinator.announcement.view'],

            // school/school_registrar — enrollment and records
            ['scope' => 'school', 'category' => 'school_registrar', 'name' => 'Create users',       'slug' => 'school_registrar.user.create'],
            ['scope' => 'school', 'category' => 'school_registrar', 'name' => 'Update users',       'slug' => 'school_registrar.user.update'],
            ['scope' => 'school', 'category' => 'school_registrar', 'name' => 'View users',         'slug' => 'school_registrar.user.view'],
            ['scope' => 'school', 'category' => 'school_registrar', 'name' => 'View grades',        'slug' => 'school_registrar.grade.view'],
            ['scope' => 'school', 'category' => 'school_registrar', 'name' => 'View groups',        'slug' => 'school_registrar.group.view'],
            ['scope' => 'school', 'category' => 'school_registrar', 'name' => 'View announcements', 'slug' => 'school_registrar.announcement.view'],

            // school/prefect — attendance and discipline
            ['scope' => 'school', 'category' => 'prefect', 'name' => 'View users',         'slug' => 'prefect.user.view'],
            ['scope' => 'school', 'category' => 'prefect', 'name' => 'View groups',        'slug' => 'prefect.group.view'],
            ['scope' => 'school', 'category' => 'prefect', 'name' => 'View announcements', 'slug' => 'prefect.announcement.view'],

            // school/finance — financial operations
            ['scope' => 'school', 'category' => 'finance', 'name' => 'Approve payments',   'slug' => 'finance.payment.approve'],
            ['scope' => 'school', 'category' => 'finance', 'name' => 'Create payments',    'slug' => 'finance.payment.create'],
            ['scope' => 'school', 'category' => 'finance', 'name' => 'Update payments',    'slug' => 'finance.payment.update'],
            ['scope' => 'school', 'category' => 'finance', 'name' => 'Delete payments',    'slug' => 'finance.payment.delete'],
            ['scope' => 'school', 'category' => 'finance', 'name' => 'View payments',      'slug' => 'finance.payment.view'],
            ['scope' => 'school', 'category' => 'finance', 'name' => 'Reject payments',    'slug' => 'finance.payment.reject'],
            ['scope' => 'school', 'category' => 'finance', 'name' => 'View users',         'slug' => 'finance.user.view'],
            ['scope' => 'school', 'category' => 'finance', 'name' => 'View announcements', 'slug' => 'finance.announcement.view'],

            // school/hr — human resources
            ['scope' => 'school', 'category' => 'hr', 'name' => 'Create users',       'slug' => 'hr.user.create'],
            ['scope' => 'school', 'category' => 'hr', 'name' => 'Update users',       'slug' => 'hr.user.update'],
            ['scope' => 'school', 'category' => 'hr', 'name' => 'Delete users',       'slug' => 'hr.user.delete'],
            ['scope' => 'school', 'category' => 'hr', 'name' => 'Suspend users',      'slug' => 'hr.user.suspend'],
            ['scope' => 'school', 'category' => 'hr', 'name' => 'View users',         'slug' => 'hr.user.view'],
            ['scope' => 'school', 'category' => 'hr', 'name' => 'Send announcements', 'slug' => 'hr.announcement.send'],
            ['scope' => 'school', 'category' => 'hr', 'name' => 'View announcements', 'slug' => 'hr.announcement.view'],

            // school/teacher
            ['scope' => 'school', 'category' => 'teacher', 'name' => 'Create grades',      'slug' => 'teacher.grade.create'],
            ['scope' => 'school', 'category' => 'teacher', 'name' => 'Update grades',      'slug' => 'teacher.grade.update'],
            ['scope' => 'school', 'category' => 'teacher', 'name' => 'View grades',        'slug' => 'teacher.grade.view'],
            ['scope' => 'school', 'category' => 'teacher', 'name' => 'View groups',        'slug' => 'teacher.group.view'],
            ['scope' => 'school', 'category' => 'teacher', 'name' => 'View announcements', 'slug' => 'teacher.announcement.view'],

            // school/student
            ['scope' => 'school', 'category' => 'student', 'name' => 'View grades',        'slug' => 'student.grade.view'],
            ['scope' => 'school', 'category' => 'student', 'name' => 'View announcements', 'slug' => 'student.announcement.view'],

            // school/tutor
            ['scope' => 'school', 'category' => 'tutor', 'name' => 'View grades',        'slug' => 'tutor.grade.view'],
            ['scope' => 'school', 'category' => 'tutor', 'name' => 'View announcements', 'slug' => 'tutor.announcement.view'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->insertOrIgnore([
                'uuid' => (string) Str::uuid(),
                'category_id' => $catId($permission['scope'], $permission['category']),
                'name' => $permission['name'],
                'slug' => $permission['slug'],
                'created_at' => now(),
            ]);
        }
    }

    private function seedRoles(): void
    {
        $catId = fn (string $scope, string $name): ?int => DB::table('permission_categories')
            ->where('scope', $scope)
            ->where('name', $name)
            ->value('id');

        $roles = [
            // Softlinkia staff — is_system_role = true, tenant_id = null
            [
                'tenant_id' => null,
                'category_id' => null,
                'name' => 'Superadmin',
                'slug' => 'superadmin',
                'hierarchy_level' => 1,
                'is_system_role' => true,
                'requires_2fa' => true,
            ],

            // Softlinkia staff operational roles — is_system_role = true, tenant_id = null,
            // bound to a staff-scoped category. Permissions managed via role_permissions.
            [
                'tenant_id' => null,
                'category_id' => $catId('staff', 'finance'),
                'name' => 'Tesorería Líder',
                'slug' => 'leader',
                'hierarchy_level' => 2,
                'is_system_role' => true,
                'requires_2fa' => true,
            ],
            [
                'tenant_id' => null,
                'category_id' => $catId('staff', 'finance'),
                'name' => 'Tesorería Operador',
                'slug' => 'operator',
                'hierarchy_level' => 3,
                'is_system_role' => true,
            ],
            [
                'tenant_id' => null,
                'category_id' => $catId('staff', 'support'),
                'name' => 'Soporte',
                'slug' => 'support',
                'hierarchy_level' => 3,
                'is_system_role' => true,
                'requires_2fa' => true,
            ],

            // Tenant-admin — no category, authority by Gate bypass / slug
            [
                'tenant_id' => null,
                'category_id' => null,
                'name' => 'Owner',
                'slug' => 'owner',
                'hierarchy_level' => 2,
                'is_system_role' => false,
            ],
            [
                'tenant_id' => null,
                'category_id' => null,
                'name' => 'School Manager',
                'slug' => 'school_manager',
                'hierarchy_level' => 3,
                'is_system_role' => false,
            ],

            // Tenant operational — scope = tenant
            [
                'tenant_id' => null,
                'category_id' => $catId('tenant', 'finance'),
                'name' => 'Tenant Finance',
                'slug' => 'tenant_finance',
                'hierarchy_level' => 4,
                'is_system_role' => false,
            ],
            [
                'tenant_id' => null,
                'category_id' => $catId('tenant', 'hr'),
                'name' => 'Tenant HR',
                'slug' => 'tenant_hr',
                'hierarchy_level' => 4,
                'is_system_role' => false,
            ],

            // School operational — one category per role
            [
                'tenant_id' => null,
                'category_id' => $catId('school', 'director'),
                'name' => 'Director',
                'slug' => 'director',
                'hierarchy_level' => 5,
                'is_system_role' => false,
            ],
            [
                'tenant_id' => null,
                'category_id' => $catId('school', 'academic_coordinator'),
                'name' => 'Academic Coordinator',
                'slug' => 'academic_coordinator',
                'hierarchy_level' => 6,
                'is_system_role' => false,
            ],
            [
                'tenant_id' => null,
                'category_id' => $catId('school', 'school_registrar'),
                'name' => 'School Registrar',
                'slug' => 'school_registrar',
                'hierarchy_level' => 6,
                'is_system_role' => false,
            ],
            [
                'tenant_id' => null,
                'category_id' => $catId('school', 'prefect'),
                'name' => 'Prefect',
                'slug' => 'prefect',
                'hierarchy_level' => 6,
                'is_system_role' => false,
            ],
            [
                'tenant_id' => null,
                'category_id' => $catId('school', 'finance'),
                'name' => 'Finance',
                'slug' => 'finance',
                'hierarchy_level' => 6,
                'is_system_role' => false,
            ],
            // Softlinkia staff role — operates cross-tenant.
            // Not used in MVP (Superadmin handles all treasury work) but kept
            // seeded for forward-compat once the Líder/Operador separation
            // from RF-160..189i lands.
            [
                'tenant_id' => null,
                'category_id' => null,
                'name' => 'Operador de Tesorería',
                'slug' => 'treasury_operator',
                'hierarchy_level' => 2,
                'is_system_role' => true,
            ],
            [
                'tenant_id' => null,
                'category_id' => $catId('school', 'hr'),
                'name' => 'HR',
                'slug' => 'hr',
                'hierarchy_level' => 6,
                'is_system_role' => false,
            ],
            [
                'tenant_id' => null,
                'category_id' => $catId('school', 'teacher'),
                'name' => 'Teacher',
                'slug' => 'teacher',
                'hierarchy_level' => 7,
                'is_system_role' => false,
            ],
            [
                'tenant_id' => null,
                'category_id' => $catId('school', 'student'),
                'name' => 'Student',
                'slug' => 'student',
                'hierarchy_level' => 8,
                'is_system_role' => false,
            ],
            [
                'tenant_id' => null,
                'category_id' => $catId('school', 'tutor'),
                'name' => 'Tutor',
                'slug' => 'tutor',
                'hierarchy_level' => 8,
                'is_system_role' => false,
            ],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->insertOrIgnore([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $role['tenant_id'],
                'category_id' => $role['category_id'],
                'name' => $role['name'],
                'slug' => $role['slug'],
                'hierarchy_level' => $role['hierarchy_level'],
                'is_system_role' => $role['is_system_role'],
                'requires_2fa' => $role['requires_2fa'] ?? false,
                'created_at' => now(),
            ]);
        }
    }

    private function seedRolePermissions(): void
    {
        $permissionId = fn (string $slug): int => (int) DB::table('permissions')->where('slug', $slug)->value('id');

        $roleId = fn (string $slug): int => (int) DB::table('roles')->where('slug', $slug)->whereNull('tenant_id')->value('id');

        $assign = function (string $roleSlug, array $permissionSlugs) use ($roleId, $permissionId): void {
            $rid = $roleId($roleSlug);
            foreach ($permissionSlugs as $slug) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $rid,
                    'permission_id' => $permissionId($slug),
                ]);
            }
        };

        // owner and school_manager have no role_permissions — authority is handled by Gate bypass.

        // Softlinkia staff operational roles
        $assign('operator', [
            'billing.view', 'billing.approve',
            'remittance.create',
        ]);

        $assign('leader', [
            'billing.view', 'billing.approve',
            'remittance.create',
            'billing.refund', 'billing.review', 'billing.return',
            'batch.assign', 'billing.metrics',
            'audit.view',
        ]);

        $assign('support', [
            'ticket.view', 'ticket.create', 'ticket.resolve', 'ticket.escalate',
            'tenant.impersonate', 'tenant.view',
        ]);

        $assign('director', [
            'school.view', 'school.update',
            'role.view', 'role.assign', 'role.revoke', 'manage.permissions', 'roles.custom.create',
            'user.view', 'user.create', 'user.update', 'user.suspend',
            'grade.publish', 'grade.view',
            'payment.approve', 'payment.view',
            'group.manage', 'group.view', 'subject.manage',
            'announcement.send', 'announcement.view',
        ]);

        $assign('academic_coordinator', [
            'academic_coordinator.grade.publish',
            'academic_coordinator.grade.create',
            'academic_coordinator.grade.update',
            'academic_coordinator.grade.delete',
            'academic_coordinator.grade.view',
            'academic_coordinator.group.manage',
            'academic_coordinator.group.view',
            'academic_coordinator.subject.manage',
            'academic_coordinator.user.view',
            'academic_coordinator.announcement.send',
            'academic_coordinator.announcement.view',
        ]);

        $assign('school_registrar', [
            'school_registrar.user.create',
            'school_registrar.user.update',
            'school_registrar.user.view',
            'school_registrar.grade.view',
            'school_registrar.group.view',
            'school_registrar.announcement.view',
        ]);

        $assign('prefect', [
            'prefect.user.view',
            'prefect.group.view',
            'prefect.announcement.view',
        ]);

        $assign('finance', [
            'finance.payment.approve',
            'finance.payment.create',
            'finance.payment.update',
            'finance.payment.delete',
            'finance.payment.view',
            'finance.payment.reject',
            'finance.user.view',
            'finance.announcement.view',
        ]);

        $assign('hr', [
            'hr.user.create',
            'hr.user.update',
            'hr.user.delete',
            'hr.user.suspend',
            'hr.user.view',
            'hr.announcement.send',
            'hr.announcement.view',
        ]);

        $assign('teacher', [
            'teacher.grade.create',
            'teacher.grade.update',
            'teacher.grade.view',
            'teacher.group.view',
            'teacher.announcement.view',
        ]);

        $assign('student', [
            'student.grade.view',
            'student.announcement.view',
        ]);

        $assign('tutor', [
            'tutor.grade.view',
            'tutor.announcement.view',
        ]);
    }
}
