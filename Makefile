.PHONY: install sync sync-check test lint fix examples guides guides-check clean

COMPOSER ?= composer
PHP ?= php

install:            ## install the dependencies and the dev tools
	$(COMPOSER) install

sync:               ## copy the snapshot and vectors from einvoice-js and regenerate src/Generated/Types.php
	sh scripts/sync.sh

sync-check:         ## CI: fail when snapshot, vectors or models are not what the pinned einvoice-js commit produces
	sh scripts/sync.sh --check

test:               ## PHPUnit with coverage when pcov/xdebug is available, parity included
	vendor/bin/phpunit

lint:               ## phpstan (level 8) + php-cs-fixer (PSR-12) over src, tests, examples and scripts
	vendor/bin/phpstan analyse --no-progress
	vendor/bin/php-cs-fixer fix --dry-run --diff

fix:                ## php-cs-fixer
	vendor/bin/php-cs-fixer fix

examples:           ## run every example (YONA_API_KEY; YONA_BASE_URL to point elsewhere)
	$(PHP) scripts/run_examples.php

guides:             ## examples/ -> guides/guides.json
	$(PHP) scripts/export_guides.php

guides-check:       ## fail when guides/guides.json is stale
	$(PHP) scripts/export_guides.php --check


clean:
	rm -rf .phpunit.cache .php-cs-fixer.cache coverage coverage.xml
