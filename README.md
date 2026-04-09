# ElevenPro POS - Sistema de Punto de Venta

Sistema POS (Point of Sale) web completo en PHP con arquitectura multi-tenant usando database-per-tenant.

## 🌐 URL del Sistema

**https://elevenpropos.com**

---

## 📋 Requisitos Técnicos

- **PHP**: 8.2 o superior
- **MySQL**: 5.7 o superior (recomendado 8.0+)
- **Servidor Web**: Apache con mod_rewrite habilitado
- **Extensiones PHP**: PDO, PDO_MySQL, GD, MBString, JSON

---

## 🗄️ Configuración de Base de Datos

### Datos de Conexión

```
Host: localhost
Puerto: 3306
Base de datos maestra: u108221933_elevenpro
Usuario: u108221933_elevenpro
Contraseña: Producers0587@
```

### Estructura de Bases de Datos

#### 1. Base Maestra (`u108221933_elevenpro`)
- `tenants` - Negocios registrados
- `subscriptions` - Suscripciones de los negocios
- `activity_log` - Registro de actividad
- `system_settings` - Configuración del sistema

#### 2. Bases de Datos por Tenant
Cada negocio tiene su propia base de datos con:
- `users` - Usuarios del sistema
- `categories` - Categorías de productos
- `products` - Productos
- `customers` - Clientes
- `transactions` - Transacciones/Ventas
- `transaction_items` - Items de transacciones
- `inventory_logs` - Registro de inventario
- `settings` - Configuración del negocio
- `payment_methods` - Métodos de pago
- `cash_register` - Caja registradora

---

## 🚀 Instalación

### Paso 1: Descargar y Extraer

```bash
# Descargar el repositorio
git clone https://github.com/tuusuario/elevenpro-pos.git

# O descargar el ZIP y extraer
unzip elevenpro-pos.zip -d /ruta/del/servidor/
```

### Paso 2: Configurar Base de Datos

```bash
# Importar base de datos maestra
mysql -u u108221933_elevenpro -p u108221933_elevenpro < scripts/database_master.sql

# Importar estructura de tenant (se usa automáticamente al crear negocios)
mysql -u u108221933_elevenpro -p < scripts/database_tenant.sql
```

### Paso 3: Configurar Variables de Entorno

```bash
# Copiar archivo de ejemplo
cp .env.example .env

# Editar con tus datos
nano .env
```

Variables importantes:
```
DB_HOST=localhost
DB_PORT=3306
DB_NAME=u108221933_elevenpro
DB_USER=u108221933_elevenpro
DB_PASS=Producers0587@

JWT_SECRET=tu_clave_secreta_segura_aqui
APP_URL=https://elevenpropos.com
```

### Paso 4: Instalar Dependencias

```bash
# Instalar Composer si no está instalado
# https://getcomposer.org/download/

# Instalar dependencias del proyecto
composer install
```

### Paso 5: Configurar Permisos

```bash
# Crear directorio de uploads
mkdir -p uploads

# Configurar permisos
chmod -R 755 uploads
chmod 644 .env
```

### Paso 6: Configurar Apache

Asegúrate de que el archivo `.htaccess` esté en la raíz del proyecto y que `mod_rewrite` esté habilitado:

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

---

## 📁 Estructura de Carpetas

```
pos-system/
├── src/                          # Código fuente PHP
│   ├── Config/                   # Configuración
│   │   ├── Database.php          # Conexión a BD
│   │   └── JWTConfig.php         # Configuración JWT
│   ├── Models/                   # Modelos de datos
│   │   ├── Model.php             # Modelo base
│   │   ├── Tenant.php            # Modelo de negocios
│   │   ├── User.php              # Modelo de usuarios
│   │   ├── Product.php           # Modelo de productos
│   │   ├── Transaction.php       # Modelo de transacciones
│   │   └── TransactionItem.php   # Modelo de items
│   ├── Controllers/              # Controladores
│   │   ├── AuthController.php    # Autenticación
│   │   ├── ProductController.php # Productos
│   │   └── SaleController.php    # Ventas
│   ├── Middleware/               # Middleware
│   │   └── AuthMiddleware.php    # Autenticación JWT
│   ├── Services/                 # Servicios
│   │   ├── FileUploadService.php # Subida de archivos
│   │   └── ReceiptService.php    # Generación de recibos
│   └── Utils/                    # Utilidades
│       ├── Response.php          # Respuestas API
│       └── Validator.php         # Validación
├── public/                       # Directorio público
│   ├── api/                      # API endpoints
│   │   └── index.php             # Router API
│   ├── assets/                   # Assets estáticos
│   │   ├── css/                  # Estilos
│   │   └── js/                   # JavaScript
│   └── uploads/                  # Archivos subidos
├── templates/                    # Plantillas HTML
│   └── pos/                      # Interfaz POS
│       ├── login.html            # Login
│       └── pos.html              # Punto de venta
├── scripts/                      # Scripts SQL
│   ├── database_master.sql       # Base maestra
│   └── database_tenant.sql       # Estructura tenant
├── .env                          # Variables de entorno
├── .env.example                  # Ejemplo de variables
├── .htaccess                     # Configuración Apache
├── composer.json                 # Dependencias PHP
└── README.md                     # Este archivo
```

---

## 🔐 Autenticación

### Roles de Usuario

| Rol | Permisos |
|-----|----------|
| **Admin** | Acceso total al sistema |
| **Manager** | Ventas, productos, reportes, inventario |
| **Cashier** | Solo ventas y caja |

### Login

**URL**: `https://elevenpropos.com/templates/pos/login.html`

**Credenciales por defecto:**
- Email: `admin@elevenpropos.com`
- Contraseña: `password`
- Base de datos: `u108221933_elevenpro_tenant_1`

---

## 📡 API Endpoints

### Autenticación
- `POST /api/auth/login` - Login de usuario
- `POST /api/auth/login-tenant` - Login de administrador
- `POST /api/auth/register` - Registro de negocio
- `GET /api/auth/verify` - Verificar token
- `POST /api/auth/refresh` - Refrescar token
- `POST /api/auth/logout` - Cerrar sesión

### Productos
- `GET /api/products` - Listar productos
- `POST /api/products/create` - Crear producto
- `GET /api/products/:id` - Ver producto
- `PUT /api/products/:id/update` - Actualizar producto
- `DELETE /api/products/:id/delete` - Eliminar producto
- `POST /api/products/:id/image` - Subir imagen
- `POST /api/products/import` - Importar CSV
- `GET /api/products/export` - Exportar CSV

### Ventas
- `GET /api/sales` - Listar ventas
- `POST /api/sales/create` - Crear venta
- `GET /api/sales/:id` - Ver venta
- `POST /api/sales/:id/cancel` - Cancelar venta
- `GET /api/sales/:id/receipt` - Descargar recibo
- `GET /api/sales/today` - Ventas del día

---

## 🖨️ Impresión de Recibos

### PDF (mPDF)
Los recibos se generan automáticamente en formato PDF después de cada venta.

### ESC/POS (Impresoras Térmicas)
Para imprimir en impresoras térmicas compatible ESC/POS:
1. Conectar la impresora vía USB/Red
2. Configurar en el navegador
3. Usar la función de impresión del sistema

---

## 📱 Atajos de Teclado

| Tecla | Acción |
|-------|--------|
| `F10` | Buscar producto |
| `F12` | Cobrar |
| `ESC` | Cerrar modal |

---

## 🔧 Configuración Adicional

### Email (SMTP)
Editar `.env`:
```
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=tu-email@gmail.com
SMTP_PASS=tu-password
```

### Tamaño Máximo de Archivos
Editar `.env`:
```
UPLOAD_MAX_SIZE=5242880  # 5MB
```

---

## 🐛 Solución de Problemas

### Error 500 - Internal Server Error
1. Verificar logs de Apache: `/var/log/apache2/error.log`
2. Verificar permisos de carpetas
3. Verificar que `.env` esté configurado correctamente

### Error de Conexión a Base de Datos
1. Verificar credenciales en `.env`
2. Verificar que MySQL esté corriendo
3. Verificar que el usuario tenga permisos

### Error 404 - Not Found
1. Verificar que `mod_rewrite` esté habilitado
2. Verificar configuración de `.htaccess`

---

## 📞 Soporte

Para soporte técnico, contactar a:
- **Email**: soporte@elevenpropos.com
- **Web**: https://elevenpropos.com

---

## 📄 Licencia

Este proyecto es propietario. Todos los derechos reservados.

---

## 🙏 Créditos

Desarrollado por ElevenPro Team

**https://elevenpropos.com**
