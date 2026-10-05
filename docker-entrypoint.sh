#!/bin/sh
set -eu

port="${PORT:-80}"
case "$port" in
    ''|*[!0-9]*)
        echo "PORT must be a valid TCP port number." >&2
        exit 1
        ;;
esac
if [ "$port" -lt 1 ] || [ "$port" -gt 65535 ]; then
    echo "PORT must be between 1 and 65535." >&2
    exit 1
fi

session_cookie_secure="${BMS_SESSION_COOKIE_SECURE:-1}"
case "$session_cookie_secure" in
    0|1) ;;
    *)
        echo "BMS_SESSION_COOKIE_SECURE must be 0 or 1." >&2
        exit 1
        ;;
esac

sed -ri "s/^Listen 80$/Listen ${port}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \\*:80>/<VirtualHost *:${port}>/" \
    /etc/apache2/sites-available/000-default.conf
sed -ri "s/^session.cookie_secure = .*/session.cookie_secure = ${session_cookie_secure}/" \
    /usr/local/etc/php/conf.d/bms.ini
printf '\nSetEnvIf X-Forwarded-Proto https HTTPS=on\n' \
    > /etc/apache2/conf-available/bms-proxy-https.conf
a2enconf bms-proxy-https
cat > /etc/apache2/conf-available/bms-storage.conf <<'APACHE'
<Directory "/data/quarantine">
    Require all denied
</Directory>
<Directory "/data/sessions">
    Require all denied
</Directory>
APACHE
a2enconf bms-storage

storage_root=/data
mkdir -p \
    "$storage_root/uploads" \
    "$storage_root/resident-uploads" \
    "$storage_root/quarantine" \
    "$storage_root/sessions"

link_storage_dir() {
    source_dir="$1"
    target_dir="$2"

    mkdir -p "$target_dir"
    if [ -L "$source_dir" ]; then
        if [ "$(readlink "$source_dir")" != "$target_dir" ]; then
            echo "Unexpected storage symlink at $source_dir." >&2
            exit 1
        fi
    else
        if [ -d "$source_dir" ]; then
            cp -a "$source_dir/." "$target_dir/"
            rm -rf "$source_dir"
        fi
        ln -s "$target_dir" "$source_dir"
    fi
    chown www-data:www-data "$target_dir"
}

link_storage_dir /var/www/html/uploads "$storage_root/uploads"
link_storage_dir /var/www/html/Barangay_user/uploads "$storage_root/resident-uploads"
link_storage_dir /var/www/html/UPLOADS "$storage_root/quarantine"

mkdir -p "$storage_root/uploads/profile_pictures"
mkdir -p "$storage_root/uploads/quarantine"
chown www-data:www-data \
    "$storage_root/uploads/profile_pictures" \
    "$storage_root/uploads/quarantine" \
    "$storage_root/quarantine" \
    "$storage_root/sessions"
chmod 0700 "$storage_root/quarantine" "$storage_root/sessions"

if [ ! -e "$storage_root/uploads/.htaccess" ]; then
    cat > "$storage_root/uploads/.htaccess" <<'HTACCESS'
Options -Indexes -ExecCGI

<FilesMatch "(?i)\.(?:php[0-9]?|phtml|phar)$">
    Require all denied
</FilesMatch>
HTACCESS
fi
if [ ! -e "$storage_root/uploads/quarantine/.htaccess" ]; then
    mkdir -p "$storage_root/uploads/quarantine"
    cat > "$storage_root/uploads/quarantine/.htaccess" <<'HTACCESS'
Options -Indexes -ExecCGI
Require all denied
HTACCESS
fi
if [ ! -e "$storage_root/resident-uploads/.htaccess" ]; then
    cat > "$storage_root/resident-uploads/.htaccess" <<'HTACCESS'
Options -Indexes -ExecCGI

<FilesMatch "(?i)\.(?:php[0-9]?|phtml|phar)$">
    Require all denied
</FilesMatch>
HTACCESS
fi

exec apache2-foreground
