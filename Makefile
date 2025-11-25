SHELL := /bin/bash

# --- env for the wrapper ---
export MOODLE_DOCKER_WWWROOT := $(CURDIR)/moodle
export MOODLE_DOCKER_DB      := mariadb
export MOODLE_DOCKER_WEB_PORT := 8000

DC := ./moodle-docker/bin/moodle-docker-compose

.PHONY: up down restart logs ps open clean nuke

up:
	$(DC) up -d

down:
	$(DC) down

restart: down up

logs:
	$(DC) logs -f

ps:
	docker ps --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}'

open:
	xdg-open http://localhost:$(MOODLE_DOCKER_WEB_PORT) || true

# careful: clean/nuke remove volumes (DB/files)
clean:
	$(DC) down -v

nuke:
	$(DC) down -v || true
	docker volume prune -f
