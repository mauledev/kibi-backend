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
            ['name' => 'academic'],
            ['name' => 'financial'],
            ['name' => 'hr'],
            ['name' => 'communication'],
            ['name' => 'configuration'],
        ];

        foreach ($categories as $category) {
            DB::table('permission_categories')->insertOrIgnore([
                'uuid' => (string) Str::uuid(),
                'school_id' => null,
                'name' => $category['name'],
                'created_at' => now(),
            ]);
        }
    }

    private function seedPermissions(): void
    {
        $categoryId = fn (string $name): int => (int) DB::table('permission_categories')
            ->where('name', $name)
            ->value('id');

        $permissions = [
            // Academic
            ['category' => 'academic', 'name' => 'Publicar calificaciones',     'slug' => 'grade.publish'],
            ['category' => 'academic', 'name' => 'Crear calificaciones',         'slug' => 'grade.create'],
            ['category' => 'academic', 'name' => 'Actualizar calificaciones',    'slug' => 'grade.update'],
            ['category' => 'academic', 'name' => 'Eliminar calificaciones',      'slug' => 'grade.delete'],
            ['category' => 'academic', 'name' => 'Ver calificaciones',           'slug' => 'grade.view'],
            ['category' => 'academic', 'name' => 'Gestionar grupos',             'slug' => 'group.manage'],
            ['category' => 'academic', 'name' => 'Ver grupos',                   'slug' => 'group.view'],
            ['category' => 'academic', 'name' => 'Gestionar materias',           'slug' => 'subject.manage'],

            // Financial
            ['category' => 'financial', 'name' => 'Aprobar pagos',              'slug' => 'payment.approve'],
            ['category' => 'financial', 'name' => 'Crear pagos',                'slug' => 'payment.create'],
            ['category' => 'financial', 'name' => 'Actualizar pagos',           'slug' => 'payment.update'],
            ['category' => 'financial', 'name' => 'Eliminar pagos',             'slug' => 'payment.delete'],
            ['category' => 'financial', 'name' => 'Ver pagos',                  'slug' => 'payment.view'],
            ['category' => 'financial', 'name' => 'Rechazar pagos',             'slug' => 'payment.reject'],

            // HR
            ['category' => 'hr', 'name' => 'Suspender usuarios',               'slug' => 'user.suspend'],
            ['category' => 'hr', 'name' => 'Crear usuarios',                    'slug' => 'user.create'],
            ['category' => 'hr', 'name' => 'Actualizar usuarios',               'slug' => 'user.update'],
            ['category' => 'hr', 'name' => 'Eliminar usuarios',                 'slug' => 'user.delete'],
            ['category' => 'hr', 'name' => 'Ver usuarios',                      'slug' => 'user.view'],

            // Configuration
            ['category' => 'configuration', 'name' => 'Ver escuelas',          'slug' => 'school.view'],
            ['category' => 'configuration', 'name' => 'Crear escuelas',        'slug' => 'school.create'],
            ['category' => 'configuration', 'name' => 'Actualizar escuelas',   'slug' => 'school.update'],
            ['category' => 'configuration', 'name' => 'Ver roles',             'slug' => 'role.view'],
            ['category' => 'configuration', 'name' => 'Asignar roles',         'slug' => 'role.assign'],
            ['category' => 'configuration', 'name' => 'Revocar roles',         'slug' => 'role.revoke'],
            ['category' => 'configuration', 'name' => 'Otorgar permisos',      'slug' => 'permission.grant'],
            ['category' => 'configuration', 'name' => 'Revocar permisos',      'slug' => 'permission.revoke'],
            ['category' => 'configuration', 'name' => 'Gestionar permisos',    'slug' => 'manage.permissions'],
            ['category' => 'configuration', 'name' => 'Crear roles',           'slug' => 'role.create'],
            ['category' => 'configuration', 'name' => 'Actualizar roles',      'slug' => 'role.update'],
            ['category' => 'configuration', 'name' => 'Eliminar roles',        'slug' => 'role.delete'],

            // Communication
            ['category' => 'communication', 'name' => 'Enviar comunicados',    'slug' => 'announcement.send'],
            ['category' => 'communication', 'name' => 'Ver comunicados',       'slug' => 'announcement.view'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->insertOrIgnore([
                'uuid' => (string) Str::uuid(),
                'category_id' => $categoryId($permission['category']),
                'name' => $permission['name'],
                'slug' => $permission['slug'],
                'created_at' => now(),
            ]);
        }
    }

    private function seedRoles(): void
    {
        $roles = [
            // Softlinkia staff — is_system_role = true, tenant_id = null
            [
                'tenant_id' => null,
                'name' => 'Superadmin',
                'slug' => 'superadmin',
                'hierarchy_level' => 1,
                'is_system_role' => true,
            ],
            // Tenant roles — is_system_role = false (permissions managed via role_permissions)
            [
                'tenant_id' => null,
                'name' => 'Owner',
                'slug' => 'owner',
                'hierarchy_level' => 2,
                'is_system_role' => false,
            ],
            [
                'tenant_id' => null,
                'name' => 'Gestor de Escuelas',
                'slug' => 'gestor_escuelas',
                'hierarchy_level' => 3,
                'is_system_role' => false,
            ],
            [
                'tenant_id' => null,
                'name' => 'Director',
                'slug' => 'director',
                'hierarchy_level' => 4,
                'is_system_role' => false,
            ],
            [
                'tenant_id' => null,
                'name' => 'Coordinador Académico',
                'slug' => 'coordinador_academico',
                'hierarchy_level' => 5,
                'is_system_role' => false,
            ],
            [
                'tenant_id' => null,
                'name' => 'Control Escolar',
                'slug' => 'control_escolar',
                'hierarchy_level' => 5,
                'is_system_role' => false,
            ],
            [
                'tenant_id' => null,
                'name' => 'Prefectura',
                'slug' => 'prefectura',
                'hierarchy_level' => 6,
                'is_system_role' => false,
            ],
            [
                'tenant_id' => null,
                'name' => 'Finanzas',
                'slug' => 'finanzas',
                'hierarchy_level' => 6,
                'is_system_role' => false,
            ],
            // Softlinkia staff role — operates cross-tenant.
            // Not used in MVP (Superadmin handles all treasury work) but kept
            // seeded for forward-compat once the Líder/Operador separation
            // from RF-160..189i lands.
            [
                'tenant_id' => null,
                'name' => 'Operador de Tesorería',
                'slug' => 'treasury_operator',
                'hierarchy_level' => 2,
                'is_system_role' => true,
            ],
            [
                'tenant_id' => null,
                'name' => 'RRHH',
                'slug' => 'rrhh',
                'hierarchy_level' => 6,
                'is_system_role' => false,
            ],
            [
                'tenant_id' => null,
                'name' => 'Docente',
                'slug' => 'docente',
                'hierarchy_level' => 7,
                'is_system_role' => false,
            ],
            [
                'tenant_id' => null,
                'name' => 'Alumno',
                'slug' => 'alumno',
                'hierarchy_level' => 8,
                'is_system_role' => false,
            ],
            [
                'tenant_id' => null,
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
                'name' => $role['name'],
                'slug' => $role['slug'],
                'hierarchy_level' => $role['hierarchy_level'],
                'is_system_role' => $role['is_system_role'],
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

        // Gestor de Escuelas — full configuration + hr + all
        $assign('gestor_escuelas', [
            'manage.permissions',
            'role.view', 'role.assign', 'role.revoke', 'role.create', 'role.update', 'role.delete',
            'permission.grant', 'permission.revoke',
            'user.create', 'user.update', 'user.delete', 'user.suspend', 'user.view',
            'grade.publish', 'grade.create', 'grade.update', 'grade.delete', 'grade.view',
            'payment.approve', 'payment.create', 'payment.update', 'payment.delete', 'payment.view', 'payment.reject',
            'group.manage', 'group.view', 'subject.manage',
            'announcement.send', 'announcement.view',
        ]);

        // Director — manages school, permissions, teachers, grades
        $assign('director', [
            'manage.permissions',
            'role.view', 'role.assign', 'role.revoke',
            'permission.grant', 'permission.revoke',
            'user.create', 'user.update', 'user.view', 'user.suspend',
            'grade.publish', 'grade.create', 'grade.update', 'grade.delete', 'grade.view',
            'payment.approve', 'payment.view',
            'group.manage', 'group.view', 'subject.manage',
            'announcement.send', 'announcement.view',
        ]);

        // Coordinador Académico — academic focus
        $assign('coordinador_academico', [
            'role.view',
            'grade.publish', 'grade.create', 'grade.update', 'grade.delete', 'grade.view',
            'group.manage', 'group.view', 'subject.manage',
            'announcement.send', 'announcement.view',
            'user.view',
        ]);

        // Control Escolar — enrollment and records
        $assign('control_escolar', [
            'user.create', 'user.update', 'user.view',
            'grade.view',
            'group.view',
            'announcement.view',
        ]);

        // Prefectura — attendance and discipline
        $assign('prefectura', [
            'user.view',
            'group.view',
            'announcement.view',
        ]);

        // Finanzas — financial operations
        $assign('finanzas', [
            'payment.approve', 'payment.create', 'payment.update', 'payment.delete', 'payment.view', 'payment.reject',
            'user.view',
            'announcement.view',
        ]);

        // Treasury Operator — Softlinkia staff role.
        // Permissions for staff roles with `is_system_role = true` are
        // enforced in code (per architecture.md), not via `role_permissions`.
        // No assignments needed here.

        // RRHH — human resources
        $assign('rrhh', [
            'user.create', 'user.update', 'user.delete', 'user.view', 'user.suspend',
            'announcement.send', 'announcement.view',
        ]);

        // Docente — teacher access
        $assign('docente', [
            'grade.create', 'grade.update', 'grade.view',
            'group.view',
            'announcement.view',
        ]);

        // Alumno — student access
        $assign('alumno', [
            'grade.view',
            'announcement.view',
        ]);

        // Tutor — parent access
        $assign('tutor', [
            'grade.view',
            'announcement.view',
        ]);
    }
}
