# Estructura de Carpetas Backend

## 📁 Organización Actual

```
app/
├── Http/                               ⭐ CAPA PRESENTACIÓN (HTTP)
│   ├── Controllers/
│   │   ├── Auth/
│   │   │   └── AuthController.php      ✅ Login, Register, Logout
│   │   └── User/
│   │       └── UserController.php      ✅ CRUD usuarios
│   ├── Requests/
│   │   ├── Auth/
│   │   │   ├── LoginRequest.php        ✅ Validación login
│   │   │   └── RegisterRequest.php     ✅ Validación registro
│   │   └── User/
│   │       ├── CreateUserRequest.php   ✅ Validación crear
│   │       └── UpdateUserRequest.php   ✅ Validación actualizar
│   ├── Resources/
│   │   ├── Auth/
│   │   │   └── UserResource.php        ✅ Serializa User para auth
│   │   └── User/
│   │       ├── UserDetailResource.php  ✅ User con todos los campos
│   │       ├── UserListResource.php    ✅ User para listas (minimal)
│   │       └── UserCreateResource.php  ✅ User post-creación
│   ├── Response/
│   │   └── ApiResponse.php             ✅ Helper de respuestas
│   ├── Middleware/
│   │   ├── AuthMiddleware.php          (Listo para crear)
│   │   └── TenantMiddleware.php        (Listo para crear)
│   └── Controller.php                  ✅ Clase base
│
├── Modules/                            ⭐ LÓGICA DE NEGOCIO
│   ├── Auth/
│   │   ├── Domain/
│   │   │   ├── Entities/
│   │   │   │   └── User.php            ✅ Entidad
│   │   │   ├── ValueObjects/
│   │   │   │   └── Email.php           ✅ Email validado
│   │   │   ├── Repositories/
│   │   │   │   └── UserRepositoryInterface.php  ✅ Contrato
│   │   │   └── Exceptions/
│   │   │       ├── InvalidCredentialsException.php
│   │   │       ├── UserNotFoundException.php
│   │   │       └── UserAlreadyExistsException.php
│   │   ├── Application/
│   │   │   ├── UseCases/
│   │   │   │   ├── Login/
│   │   │   │   │   └── LoginUseCase.php ✅ Caso de uso login
│   │   │   │   ├── Register/
│   │   │   │   │   └── RegisterUseCase.php (Listo para crear)
│   │   │   │   └── ChangePassword/
│   │   │   │       └── ChangePasswordUseCase.php (Listo para crear)
│   │   │   ├── DTOs/
│   │   │   │   ├── LoginInput.php       ✅ DTO entrada
│   │   │   │   ├── LoginOutput.php      ✅ DTO salida
│   │   │   │   └── RegisterInput.php    ✅ DTO registro
│   │   │   └── Services/
│   │   │       └── (Listo para crear)
│   │   ├── Infrastructure/
│   │   │   ├── Repositories/
│   │   │   │   └── EloquentUserRepository.php  ✅ Implementación BD
│   │   │   └── Services/
│   │   │       └── (Listo para crear)
│   │   └── Presentation/
│   │       └── Routes/
│   │           └── api.php              (Listo para crear)
│   │
│   └── User/
│       ├── Domain/
│       │   ├── Entities/
│       │   ├── ValueObjects/
│       │   ├── Repositories/
│       │   └── Exceptions/
│       ├── Application/
│       │   ├── UseCases/
│       │   │   ├── CreateUser/
│       │   │   ├── UpdateUser/
│       │   │   ├── ListUsers/
│       │   │   ├── GetUser/
│       │   │   └── DeleteUser/
│       │   ├── DTOs/
│       │   └── Services/
│       ├── Infrastructure/
│       │   ├── Repositories/
│       │   └── Services/
│       └── Presentation/
│           └── Routes/
│
├── Common/                             ⭐ CÓDIGO COMPARTIDO
│   ├── Exceptions/
│   ├── ValueObjects/
│   ├── Interfaces/
│   └── Traits/
│
├── Models/
│   └── User.php                        ✅ Modelo Eloquent (solo BD)
│
├── Providers/
│   └── (Listo para crear - inyección de dependencias)
│
config/
├── app.php
└── database.php

routes/
├── api.php                             (Listo para crear - registra rutas)

database/
├── migrations/                         (Listo para crear)
├── factories/                          (Listo para crear)
└── seeders/                            (Listo para crear)

tests/
├── Unit/
│   ├── Modules/
│   │   ├── Auth/                       (Listo para crear)
│   │   └── User/                       (Listo para crear)
│   └── Common/                         (Listo para crear)
└── Feature/
    ├── Modules/
    │   ├── Auth/                       (Listo para crear)
    │   └── User/                       (Listo para crear)

docker/
├── nginx.conf                          ✅
└── php.ini                             ✅
```

## 🎯 Flujo de una Request

```
HTTP Request (POST /api/auth/login)
    ↓
routes/api.php                          # Enruta a controlador
    ↓
app/Http/Controllers/Auth/AuthController
    ↓
app/Http/Requests/Auth/LoginRequest    # Valida entrada
    ↓
app/Modules/Auth/Application/UseCases/Login/LoginUseCase
    ↓ Orquesta
app/Modules/Auth/Domain/
    ├─ Entities/User.php               # Lógica de negocio
    ├─ ValueObjects/Email.php          # Validación encapsulada
    └─ Repositories/UserRepositoryInterface  # Contrato
    ↓
app/Modules/Auth/Infrastructure/Repositories/EloquentUserRepository
    ↓
app/Models/User                         # BD (Eloquent)
    ↓
app/Http/Resources/Auth/UserResource   # Serializa a JSON
    ↓
app/Http/Response/ApiResponse::success()  # Respuesta estándar
    ↓
HTTP Response (200 OK + JSON)
```

## 📝 Patrón de Capas

### Domain (app/Modules/{Module}/Domain/)
- **NO depende** de Laravel
- **NO conoce** de HTTP ni BD
- Contiene lógica de negocio pura
- Reutilizable desde cualquier contexto

### Application (app/Modules/{Module}/Application/)
- Orquesta el flujo
- Usa Domain para lógica
- Independiente de HTTP
- Reutilizable desde API, CLI, Jobs, etc

### Infrastructure (app/Modules/{Module}/Infrastructure/)
- Implementa contratos del Domain
- Conoce de Eloquent/BD/APIs externas
- Intercambiable (cambiar implementación = cambiar un archivo)

### Presentation (app/Http/)
- Controllers delgados
- Validación (Requests)
- Serialización (Resources)
- Solo maneja HTTP concerns

## ✅ Implementado

- ✅ Módulo Auth completo (Domain, Application, Infrastructure)
- ✅ AuthController con login/register/logout
- ✅ Requests: LoginRequest, RegisterRequest
- ✅ Resources: UserResource, UserDetailResource, UserListResource, UserCreateResource
- ✅ UserController como plantilla para User CRUD
- ✅ ApiResponse helper estandarizado

## 🚀 Próximos Pasos

1. **Crear más Use Cases** (Register, ChangePassword, etc)
2. **Crear Tests** (Unit + Feature)
3. **Agregar más módulos** (School, Student, Grade, etc)
4. **Configurar rutas** en routes/api.php
5. **Crear Middleware** de autenticación
6. **Implementar Service Providers** para inyección de dependencias
