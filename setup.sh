#!/bin/bash
# ==============================================
# Moodle-Docker quick-start script -by ChatGPT, modified by Yue
# ==============================================

set -e

# Ensure run from the repo root
cd "$(dirname "$(realpath "$0")")"

# Define paths
export MOODLE_DOCKER_WWWROOT="$(pwd)/moodle"
export MOODLE_DOCKER_DB=mariadb

echo "Moodle root: $MOODLE_DOCKER_WWWROOT"
echo "Database: $MOODLE_DOCKER_DB"

# Move into docker folder
cd moodle-docker

# Start containers
echo "Starting Moodle-Docker environment..."
bin/moodle-docker-compose up -d
bin/moodle-docker-wait-for-db

# copy config file
cp config.docker-template.php $MOODLE_DOCKER_WWWROOT/config.php

# Check if Moodle already installed
if ! bin/moodle-docker-compose exec webserver php admin/cli/checks.php >/dev/null 2>&1; then
  echo "Installing Moodle (first-time setup)..."
  bin/moodle-docker-compose exec webserver php admin/cli/install_database.php \
    --agree-license \
    --fullname="Docker Moodle" \
    --shortname="docker_moodle" \
    --summary="Moodle site running in Docker" \
    --adminpass="test" \
    --adminemail="admin@example.com"
else
  echo "Moodle already installed."
fi

cd ..

echo ""
echo "Moodle running at: http://localhost:8000"
echo "   Username: admin"
echo "   Password: test"
