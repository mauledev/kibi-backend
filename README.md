# School Admin - Backend

Sistema de administración de escuelas con arquitectura limpia.

## 📁 Estructura

```
app/Modules/
├── Auth/                    # Módulo Autenticación
│   ├── Domain/             # Lógica de negocio pura
│   │   ├── Entities/
│   │   ├── ValueObjects/
│   │   ├── Repositories/
│   │   └── Exceptions/
│   ├── Application/        # Orquestación
│   │   ├── UseCases/
│   │   ├── DTOs/
│   │   └── Services/
│   ├── Infrastructure/     # Implementaciones técnicas
│   │   ├── Repositories/
│   │   └── Services/
│   └── Presentation/       # HTTP Controllers
│       └── Http/
│
├── User/                    # Módulo Usuarios
│   ├── Domain/
│   ├── Application/
│   ├── Infrastructure/
│   └── Presentation/

app/Common/                 # Código compartido
app/Http/                   # Soporte HTTP
app/Models/                 # Modelos Eloquent (solo BD)

database/                   # Migraciones
tests/                      # Tests
docker/                     # Configuración Docker
```

## 🚀 Quick Start

```bash
# 1. Descargar proyecto
unzip school-admin-backend.zip
cd school-admin-backend

# 2. Setup
cp .env.example .env
docker-compose up -d
docker-compose exec app composer install
docker-compose exec app php artisan migrate

# 3. Acceder
# API: http://localhost:8000/api
# PostgreSQL: localhost:5432
```

## 🏗️ Arquitectura Limpia

### Domain Layer
- **Entities**: Lógica de negocio pura
- **ValueObjects**: Valores que encapsulan validaciones
- **Repositories**: Contratos (interfaces)
- **Exceptions**: Errores del dominio

### Application Layer
- **UseCases**: Orquestación de flujos
- **DTOs**: Transfer Objects
- **Services**: Servicios de aplicación

### Infrastructure Layer
- **Repositories**: Implementaciones (Eloquent)
- **Services**: Servicios externos

### Presentation Layer
- **Controllers**: Manejo HTTP (delgados)
- **Requests**: Validación
- **Resources**: Serialización

## 🧪 Testing

```bash
# Unit tests
docker-compose exec app php artisan test --filter=Unit

# Feature tests
docker-compose exec app php artisan test --filter=Feature
```

## 📝 Comandos útiles

```bash
docker-compose logs -f app           # Ver logs
docker-compose exec app bash          # Shell
docker-compose down                   # Detener
```

¡Happy coding! 🚀
