.PHONY: build install shell validate format-sanity lint lint-all test test-coverage ci audit

DOCKER_COMPOSE ?= docker compose
PHP = $(DOCKER_COMPOSE) run --rm php

build:
	$(DOCKER_COMPOSE) build

install: build
	$(PHP) composer install

shell:
	$(DOCKER_COMPOSE) run --rm php bash

validate:
	$(PHP) composer validate --strict

format-sanity:
	$(PHP) composer format:sanity

lint:
	$(PHP) composer lint

lint-all:
	$(PHP) composer lint:all

test:
	$(PHP) composer test

test-coverage:
	$(PHP) composer test:coverage

audit:
	$(PHP) composer audit

ci:
	$(PHP) composer ci
