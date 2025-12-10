SHELL := /bin/bash

# --- env for the wrapper ---
export MOODLE_DOCKER_WWWROOT := $(CURDIR)/moodle
export MOODLE_DOCKER_DB      := mariadb
export MOODLE_DOCKER_WEB_PORT := 8000

BACKUP_DIR := $(HOME)/kiwi-backups

DC := ./moodle-docker/bin/moodle-docker-compose

.PHONY: up restart logs ps open clean nuke backup restore logs_db start stop

up:
	$(DC) up -d

stop:
	$(DC) stop

start:
	$(DC) start

restart:
	$(DC) stop
	$(DC) start

logs:
	$(DC) logs -f

logs_db:
	$(DC) logs db | tail -n 30

ps:
	docker ps --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}'

open:
	xdg-open http://localhost:$(MOODLE_DOCKER_WEB_PORT) || true

exec-web:
	$(DC) exec webserver bash

debug-tables:
	$(DC) exec webserver php /var/www/html/public/local/kiwilearner/classes/debug_tables.php

# careful: clean/nuke remove volumes (DB/files)
clean:
	$(DC) down


nuke:
	$(DC) down -v || true
	docker volume prune -f

backup:
	@set -e; \
	TIMESTAMP=$$(date +%Y-%m-%d_%H%M%S); \
	BACKUP_DIR="$$HOME/kiwi-backups"; \
	mkdir -p "$$BACKUP_DIR"; \
	echo "Creating backup in $$BACKUP_DIR with timestamp $$TIMESTAMP..."; \
	\
	docker exec moodle-docker-db-1 sh -c 'mysqldump -u"$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE"' \
	> "$$BACKUP_DIR/moodle-$$TIMESTAMP.sql"; \
	\
	docker exec moodle-docker-webserver-1 sh -c \
	'tar -C /var/www -czf - moodledata' \
	> "$$BACKUP_DIR/moodledata-$$TIMESTAMP.tar.gz"; \
	\
	cp moodle/config.php "$$BACKUP_DIR/config-$$TIMESTAMP.php"; \
	\
	echo; \
	echo "DB dump    : $$BACKUP_DIR/moodle-$$TIMESTAMP.sql"; \
	echo "moodledata : $$BACKUP_DIR/moodledata-$$TIMESTAMP.tar.gz"; \
	echo "config.php : $$BACKUP_DIR/config-$$TIMESTAMP.php"

restore:
	@set -e; \
	if [ -z "$(STAMP)" ]; then \
		echo "Usage: make restore STAMP=YYYY-MM-DD_HHMMSS"; \
		exit 1; \
	fi; \
	BACKUP_DIR="$$HOME/kiwi-backups"; \
	SQL="$$BACKUP_DIR/moodle-$(STAMP).sql"; \
	MD="$$BACKUP_DIR/moodledata-$(STAMP).tar.gz"; \
	CFG="$$BACKUP_DIR/config-$(STAMP).php"; \
	echo "Using backup files:"; \
	echo "  $$SQL"; \
	echo "  $$MD"; \
	echo "  $$CFG"; \
	for f in "$$SQL" "$$MD" "$$CFG"; do \
		if [ ! -f "$$f" ]; then echo "ERROR: Missing file: $$f"; exit 1; fi; \
	done; \
	echo; echo "== 1) Recreate database =="; \
	docker exec -i moodle-docker-db-1 sh -c 'mysql -u"$$MYSQL_USER" -p"$$MYSQL_PASSWORD" -e "DROP DATABASE IF EXISTS $$MYSQL_DATABASE; CREATE DATABASE $$MYSQL_DATABASE DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"'; \
	echo; echo "== 2) Import SQL =="; \
	cat "$$SQL" | docker exec -i moodle-docker-db-1 sh -c 'mysql -u"$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE"'; \
	echo; echo "== 3) Restore moodledata =="; \
	docker exec moodle-docker-webserver-1 sh -c 'rm -rf /var/www/moodledata/*'; \
	cat "$$MD" | docker exec -i moodle-docker-webserver-1 sh -c 'cd / && tar xzf -'; \
	echo; echo "== 4) Restore config.php =="; \
	cp "$$CFG" moodle/config.php; \
	echo; echo "Restore done."

