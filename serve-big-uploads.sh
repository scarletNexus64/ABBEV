#!/bin/bash
# Lance `php artisan serve` avec des overrides PHP qui supportent
# l'upload de fichiers énormes via chunks (FilePond chunk 2-5 Mo,
# fichier total jusqu'à 5 Go).

cd "$(dirname "$0")"

HOST="${LARAVEL_HOST:-127.0.0.1}"
PORT="${LARAVEL_PORT:-8000}"

echo "==> php artisan serve --host=$HOST --port=$PORT (upload-friendly)"
echo "    upload_max_filesize = 5120M"
echo "    post_max_size       = 5120M"
echo "    memory_limit        = 1024M"
echo "    max_execution_time  = 3600"
echo "    max_input_time      = 3600"

exec php \
  -d upload_max_filesize=5120M \
  -d post_max_size=5120M \
  -d memory_limit=1024M \
  -d max_execution_time=3600 \
  -d max_input_time=3600 \
  -d max_file_uploads=50 \
  artisan serve --host="$HOST" --port="$PORT"
