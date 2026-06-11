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

---

## 🧠 MCP Server — Obsidian Integration

Claude Code lee el vault de Obsidian del proyecto via el MCP filesystem server. Esto permite usar las notas como contexto en prompts sin copiar información manualmente.

### Instalación en macOS

**1. Instalar globalmente**

```bash
npm install -g @modelcontextprotocol/server-filesystem@2025.11.25
```

**2. Localizar el archivo a parchear**

```bash
npm root -g
# Ejemplo: /Users/<tu-usuario>/.nvm/versions/node/v22.22.3/lib/node_modules
```

El archivo a editar es:
```
<npm-root>/@modelcontextprotocol/server-filesystem/dist/index.js
```

**3. Aplicar el parche**

Buscar la función `oninitialized` (línea ~564) y cambiar la condición:

```js
// ANTES
if (clientCapabilities?.roots) {

// DESPUÉS
if (false && clientCapabilities?.roots) { // patched: prevents vault path replacement via client roots
```

**4. Localizar el binario global**

```bash
which mcp-server-filesystem
# Ejemplo: /Users/<tu-usuario>/.nvm/versions/node/v22.22.3/bin/mcp-server-filesystem
```

**5. Configurar `~/.claude.json`**

Agregar la entrada en la sección `mcpServers` (crear el archivo si no existe):

```json
{
  "mcpServers": {
    "obsidian": {
      "type": "stdio",
      "command": "/Users/<tu-usuario>/.nvm/versions/node/v22.22.3/bin/mcp-server-filesystem",
      "args": ["/ruta/a/tu/vault/obsidian"],
      "env": {}
    }
  }
}
```

> **Importante:** usar el path absoluto al binario, **no** `npx`. El `npx` descarga una versión fresca sin el parche en cada arranque.

---

### Instalación en Windows

El parche y la versión son los mismos. Las diferencias son los paths.

**1. Instalar globalmente**

```powershell
npm install -g @modelcontextprotocol/server-filesystem@2025.11.25
```

**2. Localizar el archivo a parchear**

```powershell
npm root -g
# Sin nvm: C:\Users\<usuario>\AppData\Roaming\npm\node_modules
# Con nvm-windows: C:\Users\<usuario>\AppData\Roaming\nvm\v22.x.x\node_modules
```

El archivo a editar es:
```
<npm-root>\@modelcontextprotocol\server-filesystem\dist\index.js
```

Aplicar el mismo parche de la línea `if (false && clientCapabilities?.roots)`.

**3. Configurar `~/.claude.json`**

En Windows, el equivalente es `%USERPROFILE%\.claude.json`. Usar `node` como comando apuntando directamente al `index.js` del paquete, ya que los wrappers `.cmd` de npm pueden no funcionar como `command` en configs stdio:

```json
{
  "mcpServers": {
    "obsidian": {
      "type": "stdio",
      "command": "node",
      "args": [
        "C:/Users/<usuario>/AppData/Roaming/npm/node_modules/@modelcontextprotocol/server-filesystem/dist/index.js",
        "C:/ruta/a/tu/vault/obsidian"
      ],
      "env": {}
    }
  }
}
```

> **Nota:** usar forward slashes `/` en los paths dentro del JSON aunque estés en Windows.

---

### Verificación

Abrir una nueva sesión de Claude Code y ejecutar:

```
Lee el archivo Home.md dentro de la carpeta 00 - Index
```

Si Claude responde con el contenido real de la nota, el setup es correcto.
