# Despliegue en Hostinger (plan Business)

Dominio: **https://bodegalopez.larassolutions.es**

El sistema necesita PHP 8.3, MySQL, una tarea programada diaria (cron) y nada de colas: todo se procesa en la misma petición.

## 1. En hPanel

1. **Subdominio**: *Dominios → Subdominios* → crea `bodegalopez` en `larassolutions.es`.
2. **SSL**: *Seguridad → SSL* → instala el certificado gratuito para el subdominio.
3. **PHP**: *Avanzado → Configuración de PHP* → versión **8.3**. Extensiones activas: `bcmath`, `gd`, `intl`, `mbstring`, `pdo_mysql`, `zip`, `fileinfo`.
4. **Base de datos**: *Bases de datos → MySQL* → crea la base, el usuario y su contraseña. Anótalos.
5. **SSH**: *Avanzado → Acceso SSH* → actívalo. Anota host, puerto (normalmente `65002`) y usuario.
6. **Respaldos**: *Archivos → Copias de seguridad* → confirma que las copias diarias estén activas e incluyan la base de datos.

## 2. Por SSH

```bash
cd ~/domains/bodegalopez.larassolutions.es

# Código (si el repositorio es privado, usa un token o una llave de despliegue de GitHub).
git clone git@github.com:Jbernardo-2/Proximus.git proximus   # llave de despliegue en ~/.ssh
cd proximus
composer install --no-dev --optimize-autoloader

# Configuración: copia la plantilla de abajo en .env y completa los datos.
nano .env
php artisan key:generate --force

php artisan migrate --force
php artisan db:seed --force             # perfil de empresa y unidades de medida
# storage:link no funciona (Hostinger desactiva exec), el enlace se crea a mano:
ln -sfn ~/domains/bodegalopez.larassolutions.es/proximus/storage/app/public public/storage
php artisan app:create-admin          # pide nombre, correo y contraseña

# El subdominio debe servir la carpeta public del proyecto.
rm -rf ~/domains/bodegalopez.larassolutions.es/public_html
ln -s ~/domains/bodegalopez.larassolutions.es/proximus/public ~/domains/bodegalopez.larassolutions.es/public_html

php artisan config:cache && php artisan route:cache && php artisan view:cache
```

**Estilos y scripts**: el hosting compartido no tiene Node. Se compilan en la computadora (`npm run build`) y se sube la carpeta `public/build` a `~/domains/bodegalopez.larassolutions.es/proximus/public/build`.

## 3. Cron (hPanel → Avanzado → Cron Jobs)

Cada minuto (el plan no trae el comando crontab, se agrega desde hPanel):

```
cd /home/u375934362/domains/bodegalopez.larassolutions.es/proximus && php artisan schedule:run >> /dev/null 2>&1
```

## 4. Plantilla del `.env` de producción

```env
APP_NAME=Proximus
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://bodegalopez.larassolutions.es
APP_TIMEZONE=America/Tegucigalpa
APP_LOCALE=es
APP_FALLBACK_LOCALE=en

LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true

FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
CACHE_STORE=database

# Correo: cuenta creada en hPanel → Correos (se puede dejar en log al inicio).
MAIL_MAILER=log
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_SCHEME=smtps
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME="Proximus"

PROXIMUS_CURRENCY=HNL
PROXIMUS_ORDER_PREFIX=PED
```

## 5. Actualizar después

```bash
cd ~/domains/bodegalopez.larassolutions.es/proximus
php artisan down
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

Y vuelve a subir `public/build` si cambiaron estilos o scripts.

## 6. App Android

- Se compila con `flutter build apk --release --dart-define=SERVER_URL=https://bodegalopez.larassolutions.es`.
- Se firma con la llave de `C:\Users\elast\proximus-release\` (`proximus-release.jks` y `key.properties`). **Guarda una copia de esa carpeta fuera de la computadora**: sin ella no se pueden publicar actualizaciones de la app ya instalada.
