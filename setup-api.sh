#!/bin/bash
# Installation Sanctum + migrations + storage link + lancement serveur
# avec limites PHP tunées pour les uploads chunked de gros fichiers.

set -e
cd "$(dirname "$0")"

GREEN='\033[0;32m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${BLUE}===> 1. Install dependencies (Sanctum + chunk-upload déjà présent)${NC}"
composer require laravel/sanctum --no-interaction

echo -e "${BLUE}===> 2. Publish Sanctum config + chunk-upload config${NC}"
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider" --tag=sanctum-config --force || true
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider" --tag=sanctum-migrations --force || true
php artisan vendor:publish --provider="Pion\Laravel\ChunkUpload\Providers\ChunkUploadServiceProvider" --tag=config --force || true

echo -e "${BLUE}===> 3. Migrate database${NC}"
php artisan migrate --force

echo -e "${BLUE}===> 4. Storage symlink${NC}"
php artisan storage:link || true

echo -e "${BLUE}===> 5. Clear caches${NC}"
php artisan optimize:clear

echo -e "${GREEN}===> Setup terminé.${NC}"
echo ""
echo -e "${BLUE}Pour lancer le serveur avec les limites uploads tunées:${NC}"
echo -e "${GREEN}  ./serve-big-uploads.sh${NC}"
