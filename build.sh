#!/bin/bash

# Exit immediately if any command fails
set -e

echo "Pulling latest code from Git..."
git pull origin development

echo "Rebuilding and restarting docker containers..."
# --build forces it to look for changes in your Dockerfile/code

docker compose -f staging-compose.yaml --env-file ./docker/staging/.env.dev  up -d --build

# run composer
docker exec -it pmo-backend-dev composer update

# run migration

docker exec -it pmo-backend-dev php artisan migrate



echo "Application updated successfully!"