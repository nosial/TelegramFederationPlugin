all: target/debug/net.nosial.telegram_federation_plugin.ncc target/release/net.nosial.telegram_federation_plugin.ncc
target/debug/net.nosial.telegram_federation_plugin.ncc:
	ncc build --configuration debug --log-level debug
target/release/net.nosial.telegram_federation_plugin.ncc:
	ncc build --configuration release --log-level debug

TEST_COMPOSE = docker compose -f docker-compose.yml
SERVER_ENDPOINT ?= http://172.17.0.1:7000

# The Telegram test chat, from the environment (eg; CI secrets) or else from phpunit.xml, the same chat the tests use
PHPUNIT_ENV = php -r '$$c = @simplexml_load_file("phpunit.xml"); foreach($$c ? $$c->php->env : [] as $$e) { if((string)$$e["name"] === $$argv[1]) { echo $$e["value"]; } }'
TELEGRAM_TEST_BOT_TOKEN ?= $(shell $(PHPUNIT_ENV) TELEGRAM_TEST_BOT_TOKEN)
TELEGRAM_TEST_CHAT_ID ?= $(shell $(PHPUNIT_ENV) TELEGRAM_TEST_CHAT_ID)
TELEGRAM_TEST_TOPIC_ID ?= $(shell $(PHPUNIT_ENV) TELEGRAM_TEST_TOPIC_ID)
export TELEGRAM_TEST_BOT_TOKEN TELEGRAM_TEST_CHAT_ID TELEGRAM_TEST_TOPIC_ID

test-env:
	$(TEST_COMPOSE) build --pull
	$(TEST_COMPOSE) up -d
	@echo "Waiting for the test environment to be ready..."
	@for i in $$(seq 1 60); do \
		if curl -sf -o /dev/null "$(SERVER_ENDPOINT)/"; then \
			echo "The test environment is ready"; exit 0; \
		fi; \
		sleep 5; \
	done; \
	echo "The test environment did not become ready within 5 minutes"; $(TEST_COMPOSE) logs app; exit 1

test-env-down:
	$(TEST_COMPOSE) down -v

test: target/release/net.nosial.telegram_federation_plugin.ncc
	phpunit --configuration phpunit.xml

clean:
	rm -f target/debug/net.nosial.telegram_federation_plugin.ncc
	rm -f target/release/net.nosial.telegram_federation_plugin.ncc

.PHONY: all install clean test test-env test-env-down
