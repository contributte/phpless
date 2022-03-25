.PHONY: dev-api dev-web

dev-api:
	php -S 0.0.0.0:8000 api/index.php

dev-web:
	PHPSTAN_URL=http://0.0.0.0:8000 npm run dev

