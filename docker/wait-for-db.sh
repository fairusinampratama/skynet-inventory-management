#!/bin/sh
set -e

if [ -z "$DB_HOST" ]; then
    exit 0
fi

until php -r '
try {
    new PDO(
        sprintf("mysql:host=%s;port=%s;dbname=%s", getenv("DB_HOST"), getenv("DB_PORT") ?: 3306, getenv("DB_DATABASE")),
        getenv("DB_USERNAME"),
        getenv("DB_PASSWORD"),
    );
} catch (Throwable $exception) {
    exit(1);
}
' >/dev/null 2>&1; do
    echo "Waiting for database..."
    sleep 2
done
