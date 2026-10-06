# TelegramFederationPlugin

A [FederationLib](https://git.n64.cc/nosial/federationlib) plugin that sends FederationLib's events to a Telegram chat
(and optionally a forum topic), such as reports being created or closed, entities being blacklisted or evidence being
classified. Notifications can contain inline buttons that open the records in a
[FederationWeb](https://git.n64.cc/nosial/federationweb) instance or on the FederationLib server.

## Table of contents

<!-- TOC -->
* [TelegramFederationPlugin](#telegramfederationplugin)
  * [Table of contents](#table-of-contents)
  * [What it does](#what-it-does)
    * [Inline buttons](#inline-buttons)
    * [Confidential content](#confidential-content)
    * [Delivery](#delivery)
  * [Configuration](#configuration)
    * [Setting up the bot](#setting-up-the-bot)
    * [Changing the configuration](#changing-the-configuration)
    * [Choosing the events](#choosing-the-events)
    * [Example configurations](#example-configurations)
      * [Moderation team](#moderation-team)
      * [Public transparency channel](#public-transparency-channel)
      * [Administration and security](#administration-and-security)
      * [Complete audit trail](#complete-audit-trail)
      * [Development and debugging](#development-and-debugging)
      * [Multiple chats](#multiple-chats)
  * [Installation](#installation)
    * [Docker](#docker)
  * [Building and testing](#building-and-testing)
    * [Test environment](#test-environment)
    * [Running the tests](#running-the-tests)
    * [Telegram test chat](#telegram-test-chat)
* [License](#license)
<!-- TOC -->

## What it does

The plugin listens on every FederationLib event, and sends the ones its configuration selects to Telegram:

| Event           | Handler               | Sent by default | Limited with      | Notification                                                    |
|-----------------|-----------------------|-----------------|-------------------|-----------------------------------------------------------------|
| `RECORD_CHANGE` | `RecordChangeHandler` | Disabled        | `record_changes`  | The current state of the changed record and its related records |
| `AUDIT_LOG`     | `AuditLogHandler`     | Every type      | `audit_logs`      | The audit log message and the records it refers to              |
| `CONTENT_SCAN`  | `ContentScanHandler`  | Disabled        | `content_scans`   | The author, resolved entities, classifications and content      |
| `QUERY_ENTITY`  | `QueryEntityHandler`  | Disabled        | `entity_queries`  | The queried entity, the requester and its active blacklists     |

Only audit log entries are sent by default, every type of them. The audit log describes every action on the server
once and says who did what, so nothing is missed or sent twice while setting the plugin up. Record changes, content
scans and entity queries have to be enabled explicitly, see [Choosing the events](#choosing-the-events) and
[Example configurations](#example-configurations).

Every notification starts with hashtags (eg; `#RECORD_CHANGE #REPORT_CLOSED #FEDERATION_SERVER`) so the chat can be
searched, followed by the event and the FederationLib server's name. A notification is sent the moment its event
happens, so the time Telegram shows for the message is the time of the event. Related records are shown by what
people know them by rather than their UUID, eg; an entity by its address and an operator by their name. Operator
access tokens are never included.

The plugin never changes or rejects anything, content scans and entity queries are only observed.

### Inline buttons

When `federation_web` is set (eg; `https://audit.nosial.net/`), notifications get buttons that open their records in
[FederationWeb](https://github.com/nosial/FederationWeb), and the records in the message link there as well:

| Record     | FederationWeb                     | FederationLib server (`federation_host`) |
|------------|-----------------------------------|------------------------------------------|
| Entity     | `/entities/{uuid}`                | `/entities/{uuid}`                       |
| Evidence   | `/evidence/{uuid}`                | `/evidence/{uuid}`                       |
| Report     | `/reports/{uuid}`                 | `/reports/{uuid}`                        |
| Blacklist  | `/blacklist/{uuid}`               | `/blacklist/{uuid}`                      |
| Operator   | `/operators/{uuid}`               | `/operators/{uuid}`                      |
| Audit log  | `/audit-log/{uuid}`               | `/audit/{uuid}`                          |
| Attachment | -                                 | `/attachments/{uuid}` (download)         |

`federation_host` is the public URL of the [FederationLib](https://github.com/nosial/FederationLib) server (eg; `https://federation.nosial.net/`).
It is used for the records when no [FederationWeb]([FederationWeb](https://github.com/nosial/FederationWeb)) instance is set,
and always for file attachments, which [FederationWeb]([FederationWeb](https://github.com/nosial/FederationWeb)) has no page for.
Without either, notifications have no buttons.

A notification has at most 8 buttons, two per row, eg; a closed report gets *View Report*, *View Reported Entity* (for
each entity of its evidence) and *View Assigned Operator*.

Telegram only accepts public URLs in buttons, if it rejects a button (eg; `http://127.0.0.1`) the notification is sent
again as plain text without buttons and a warning is logged.

### Confidential content

The text content and notes of evidence, the messages of reports and the content of scans are included up to
`max_content_length` characters, unless `include_content` is disabled. Content of **confidential** evidence (and the file
names of its attachments) is left out unless `include_confidential` is enabled as well, the notification says that the
content is confidential instead. Keep in mind that everyone in the chat can read the notifications.

### Delivery

Event handlers run synchronously in the request (or process) that produced the event, so the request waits for
Telegram (3 seconds to connect, 5 seconds at most). A notification that can't be sent is logged and never affects the
operation it is about.

Like LogLib2's Telegram handler, a target that fails in a way that won't resolve itself (an invalid bot token, chat or
topic, or Telegram being unreachable) isn't tried again for the rest of the process. Rate limits (`429`) and Telegram
server errors only drop the notification they happened to.

## Configuration

The plugin has its own [ConfigLib](https://github.com/nosial/ConfigLib) configuration named
`telegram_federation_plugin`. It is created with the default values the first time the plugin is used. Nothing is sent
until both `bot_token` and `chat_id` are set.

| Name                   | Environment Variable                              | Type    | Default Value              | Description                                                                         |
|------------------------|---------------------------------------------------|---------|----------------------------|-------------------------------------------------------------------------------------|
| `enabled`              | `TELEGRAM_FEDERATION_PLUGIN_ENABLED`              | boolean | `true`                     | Send notifications                                                                  |
| `bot_token`            | `TELEGRAM_FEDERATION_PLUGIN_BOT_TOKEN`            | string  | `null`                     | The token of the Telegram bot that sends the notifications                          |
| `chat_id`              | `TELEGRAM_FEDERATION_PLUGIN_CHAT_ID`              | string  | `null`                     | The chat the notifications are sent to, eg; `-1001234567890` or `@channel`          |
| `topic_id`             | `TELEGRAM_FEDERATION_PLUGIN_TOPIC_ID`             | integer | `null`                     | The forum topic of the chat the notifications are sent to                           |
| `api_endpoint`         | `TELEGRAM_FEDERATION_PLUGIN_API_ENDPOINT`         | string  | `https://api.telegram.org` | The Telegram Bot API endpoint, eg; a local Bot API server                           |
| `disable_notification` | `TELEGRAM_FEDERATION_PLUGIN_DISABLE_NOTIFICATION` | boolean | `false`                    | Send the notifications silently                                                     |
| `federation_web`       | `TELEGRAM_FEDERATION_PLUGIN_FEDERATION_WEB`       | string  | `null`                     | The public URL of a FederationWeb instance, eg; `https://audit.nosial.net/`         |
| `federation_host`      | `TELEGRAM_FEDERATION_PLUGIN_FEDERATION_HOST`      | string  | `null`                     | The public URL of the FederationLib server, eg; `https://federation.nosial.net/`    |
| `record_changes`       | `TELEGRAM_FEDERATION_PLUGIN_RECORD_CHANGES`       | list    | `[]`                       | The record change types that are sent, `*` for all, empty for none                  |
| `audit_logs`           | `TELEGRAM_FEDERATION_PLUGIN_AUDIT_LOGS`           | list    | `['*']`                    | The audit log types that are sent, `*` for all, empty for none                      |
| `content_scans`        | `TELEGRAM_FEDERATION_PLUGIN_CONTENT_SCANS`        | boolean | `false`                    | Send content scans                                                                  |
| `entity_queries`       | `TELEGRAM_FEDERATION_PLUGIN_ENTITY_QUERIES`       | boolean | `false`                    | Send entity queries                                                                 |
| `include_content`      | `TELEGRAM_FEDERATION_PLUGIN_INCLUDE_CONTENT`      | boolean | `true`                     | Include the content of evidence and scans and the messages of reports               |
| `include_confidential` | `TELEGRAM_FEDERATION_PLUGIN_INCLUDE_CONFIDENTIAL` | boolean | `false`                    | Include the content of confidential evidence as well                                |
| `max_content_length`   | `TELEGRAM_FEDERATION_PLUGIN_MAX_CONTENT_LENGTH`   | integer | `1000`                     | The maximum number of characters of each content (50 to 3000)                       |

### Setting up the bot

 1. Create a bot with [@BotFather](https://t.me/BotFather) (`/newbot`), it gives you the bot token.
 2. Add the bot to the group or channel the notifications are sent to. In a channel the bot has to be an administrator
    that can post messages, in a group with topics it has to be able to post in the topic.
 3. Find the chat ID: send a message that mentions the bot in the chat, then open
    `https://api.telegram.org/bot<token>/getUpdates` and read `chat.id`. Group and channel IDs start with `-100`. A
    public channel can also be given as `@channelname`.
 4. For a group with topics, the topic ID is the last number of a link to a message in the topic
    (`https://t.me/c/1234567890/42/100` is topic `42`). Leave `topic_id` empty for the *General* topic.

### Changing the configuration

There are three ways to configure the plugin, use whichever fits how FederationLib is deployed.

**Environment variables**, the usual choice for Docker. Every option has its own variable (see the table above), which
takes precedence over the configuration file. Lists are comma separated, `*` sends every type and an empty value sends
none:

```shell
TELEGRAM_FEDERATION_PLUGIN_BOT_TOKEN=123456:ABC-DEF
TELEGRAM_FEDERATION_PLUGIN_CHAT_ID=-1001234567890
TELEGRAM_FEDERATION_PLUGIN_RECORD_CHANGES=REPORT_CREATED,REPORT_CLOSED,BLACKLIST_CREATED
TELEGRAM_FEDERATION_PLUGIN_AUDIT_LOGS=
TELEGRAM_FEDERATION_PLUGIN_CONTENT_SCANS=false
```

**The `configlib` command**, on the machine (or in the container) FederationLib runs on:

```shell
configlib --config telegram_federation_plugin                                   # Shows the configuration
configlib --config telegram_federation_plugin --property chat_id --value -1001234567890
configlib --config telegram_federation_plugin --editor nano                     # Edits it as YAML, recommended for lists
```

**A YAML file**, imported with `configlib --config telegram_federation_plugin --import telegram.yml`, or used as the
configuration file by pointing the `CONFIGLIB_TELEGRAM_FEDERATION_PLUGIN` environment variable at it (eg; a file
mounted into the container), options it leaves out take their default values. The examples below are written in this
form.

In YAML, `*` has to be quoted (`'*'`), unquoted it is a YAML alias and the file won't load.

### Choosing the events

The record change types are `OPERATOR_CREATED`, `OPERATOR_UPDATED`, `OPERATOR_DISABLED`, `OPERATOR_ENABLED`,
`OPERATOR_DELETED`, `ENTITY_CREATED`, `ENTITY_UPDATED`, `ENTITY_REPUTATION_UPDATED`, `ENTITY_DELETED`,
`EVIDENCE_CREATED`, `EVIDENCE_UPDATED`, `EVIDENCE_CLASSIFIED`, `EVIDENCE_DELETED`, `ATTACHMENT_CREATED`,
`ATTACHMENT_DELETED`, `REPORT_CREATED`, `REPORT_OPERATOR_ASSIGNED`, `REPORT_CLOSED`, `REPORT_DELETED`,
`BLACKLIST_CREATED`, `BLACKLIST_EXTENDED`, `BLACKLIST_LIFTED` and `BLACKLIST_DELETED`.

The audit log types are `OPERATOR_CREATED`, `OPERATOR_DELETED`, `OPERATOR_DISABLED`, `OPERATOR_ENABLED`,
`OPERATOR_UPDATED`, `ATTACHMENT_UPLOADED`, `ATTACHMENT_DELETED`, `EVIDENCE_SUBMITTED`, `EVIDENCE_UPDATED`,
`EVIDENCE_DELETED`, `REPORT_GENERATED`, `REPORT_SUBMITTED`, `REPORT_OPERATOR_ASSIGNED`, `REPORT_CLOSED`,
`REPORT_DELETED`, `ENTITY_DELETED`, `ENTITY_BLACKLISTED`, `ENTITY_PUSHED`, `ENTITY_UPDATED`, `BLACKLIST_DELETED`,
`BLACKLIST_LIFTED`, `BLACKLIST_EXTENDED` and `OTHER`.

Things to keep in mind when narrowing them down:

 - **Most actions produce both** a record change and an audit log entry (eg; closing a report produces the
   `REPORT_CLOSED` record change and the `REPORT_CLOSED` audit log entry). When enabling record changes, choose one of
   the two for each action, or most actions are sent twice: record changes show the record itself with its related
   records, audit log entries show the audit message.
 - **Some types are frequent.** Every content scan creates the entities it finds (`ENTITY_CREATED`) and updates their
   reputation (`ENTITY_REPUTATION_UPDATED`), and evidence is created for every report that is generated.
 - **Content scans and entity queries are disabled by default.** When enabled, every scan or query request waits for
   Telegram (up to 5 seconds), and they are as frequent as the clients using the server. Only enable them on servers
   with little traffic or while debugging.
 - A content scan notification only contains the scanning rules and classifications of the plugins configured *before*
   this plugin in `FEDERATION_PLUGINS`, so list this plugin last.

### Example configurations

Only the options that differ from the defaults are shown, `bot_token` and `chat_id` are always required.

#### Moderation team

A chat where operators follow the reports they need to handle and the decisions that are made, each report and
blacklist once, with buttons that open them in FederationWeb.

```yaml
bot_token: '123456:ABC-DEF'
chat_id: '-1001234567890'
topic_id: 42
federation_web: 'https://audit.nosial.net/'
record_changes:
  - REPORT_CREATED
  - REPORT_OPERATOR_ASSIGNED
  - REPORT_CLOSED
  - EVIDENCE_CLASSIFIED
  - BLACKLIST_CREATED
  - BLACKLIST_EXTENDED
  - BLACKLIST_LIFTED
audit_logs: []
```

#### Public transparency channel

A public channel that announces blacklists. Nothing about operators or reports and no content, since anyone can read
it, and the buttons point to a public FederationWeb instance.

```yaml
bot_token: '123456:ABC-DEF'
chat_id: '@federation_blacklist'
disable_notification: true
federation_web: 'https://audit.nosial.net/'
record_changes:
  - BLACKLIST_CREATED
  - BLACKLIST_EXTENDED
  - BLACKLIST_LIFTED
audit_logs: []
include_content: false
```

#### Administration and security

A private chat for the server's administrators that records every change to operators and every deletion, from the
audit log so the message says who did what. The buttons open the records on the FederationLib server, as there is no
FederationWeb instance.

```yaml
bot_token: '123456:ABC-DEF'
chat_id: '-1001234567890'
federation_host: 'https://federation.nosial.net/'
audit_logs:
  - OPERATOR_CREATED
  - OPERATOR_UPDATED
  - OPERATOR_DISABLED
  - OPERATOR_ENABLED
  - OPERATOR_DELETED
  - ENTITY_DELETED
  - EVIDENCE_DELETED
  - ATTACHMENT_DELETED
  - REPORT_DELETED
  - BLACKLIST_DELETED
include_content: false
```

#### Complete audit trail

A muted chat with one topic that keeps everything that happens on the server, including confidential content, with
buttons for both FederationWeb and attachment downloads. The audit log is already sent completely by default, so only
the chat and the content options change. Only use this for a chat as trusted as the server itself.

```yaml
bot_token: '123456:ABC-DEF'
chat_id: '-1001234567890'
topic_id: 7
disable_notification: true
federation_web: 'https://audit.nosial.net/'
federation_host: 'https://federation.nosial.net/'
include_confidential: true
max_content_length: 3000
```

#### Development and debugging

Every event on a local server, including record changes, content scans and entity queries, so actions show up both
as a record change and as an audit log entry. Telegram doesn't accept local URLs in buttons, so no `federation_web` or
`federation_host` is set.

```yaml
bot_token: '123456:ABC-DEF'
chat_id: '-1001234567890'
record_changes:
  - '*'
content_scans: true
entity_queries: true
```

#### Multiple chats

The plugin sends every notification to a single chat and topic, events can't be routed to different chats or topics.
Choose the events for the chat's audience as in the examples above, the hashtags (eg; `#REPORT_CLOSED` or
`#AUDIT_LOG`) make a chat that receives many kinds of events easy to search.

## Installation

The plugin is loaded by FederationLib through the `plugins` configuration (`FEDERATION_PLUGINS`) using its package
name, `net.nosial.telegram_federation_plugin`. FederationLib (and ConfigLib and LogLib2) are provided by FederationLib
at runtime.

```shell
make target/release/net.nosial.telegram_federation_plugin.ncc
ncc install --package="$PWD/target/release/net.nosial.telegram_federation_plugin.ncc" --yes --reinstall
```

### Docker

The FederationLib docker image installs plugins listed in `REQUIRE_PLUGINS` before it initializes:

```yaml
environment:
  - REQUIRE_PLUGINS=/opt/plugins/net.nosial.telegram_federation_plugin.ncc   # Anything `ncc install` accepts
  - FEDERATION_PLUGINS=net.nosial.bayesian_plugin,net.nosial.telegram_federation_plugin
  - TELEGRAM_FEDERATION_PLUGIN_BOT_TOKEN=123456:ABC-DEF
  - TELEGRAM_FEDERATION_PLUGIN_CHAT_ID=-1001234567890
  - TELEGRAM_FEDERATION_PLUGIN_TOPIC_ID=42
  - TELEGRAM_FEDERATION_PLUGIN_FEDERATION_WEB=https://audit.nosial.net/
  - TELEGRAM_FEDERATION_PLUGIN_FEDERATION_HOST=https://federation.nosial.net/
```

## Building and testing

```shell
make                  # Builds target/release and target/debug
make test-env         # Starts the test environment (FederationLib with the plugin installed, see below)
make test             # Runs the tests (phpunit), requires the release build and the test environment
make test-env-down    # Removes the test environment and its data
make clean            # Removes the builds
```

The tests in `tests/TelegramFederationPlugin/Tests` follow the plugin's source:

| Tests                          | Runs against                                                                                    |
|--------------------------------|-------------------------------------------------------------------------------------------------|
| `Classes/`, `Objects/`         | The configuration, notifications and their formatting, nothing is sent                          |
| `Classes/TelegramClientTest`   | The Telegram Bot API, notifications are sent to the [test chat](#telegram-test-chat)            |
| `PluginTest`                   | FederationLib's plugin system, the events FederationLib dispatches to the plugin                |
| `TelegramFederationPluginTest` | How notifications are sent, failures never reach the event                                      |
| `FederationServer/`            | The [test environment](#test-environment), one test per event the server's plugin handles       |

### Test environment

The integration tests run against a live FederationLib server with the plugin installed, started with Docker Compose
(`docker-compose.yml`):

| Service   | Description                                                                                          | Port                       |
|-----------|------------------------------------------------------------------------------------------------------|----------------------------|
| `app`     | FederationLib's published `dev` image (`ghcr.io/nosial/federationlib:dev`) with the plugin installed | `7000` (`FEDERATION_PORT`) |
| `mariadb` | FederationLib's database                                                                             | -                          |
| `redis`   | FederationLib's cache                                                                                | -                          |

The `Dockerfile` builds the plugin from source and adds it to FederationLib's image. FederationLib's entrypoint
installs it and enables it through `REQUIRE_PLUGINS` and `FEDERATION_PLUGINS`, with every event enabled. The plugin
sends its notifications to the [test chat](#telegram-test-chat) if it's configured when the environment is started,
otherwise it's installed but sends nothing. The image is only used for testing and is never published.

 - `make test-env` builds with `--pull`, so the tests always run against the latest build of FederationLib's `dev`
   image rather than an outdated local copy. It then waits until FederationLib responds.
 - The plugin inside the container is the one built when the image was built, not `target/release`. Run
   `make test-env` again after changing the plugin, otherwise the tests against the server use the old plugin.
 - The plugin only sends a few types of events (see `docker-compose.yml`) to stay below Telegram's limits,
   `FederationServerTest` checks that each of them is sent to the [test chat](#telegram-test-chat) and that the
   operations are never affected by the plugin.
 - Nothing is persisted: `make test-env-down` removes the database.

If port `7000` is in use, start the environment on another port and point the tests at it:

```shell
FEDERATION_PORT=7001 make test-env SERVER_ENDPOINT=http://172.17.0.1:7001
SERVER_ENDPOINT=http://172.17.0.1:7001 make test
```

### Running the tests

The tests import FederationLib from the installed `net.nosial.federation` package. They're run against FederationLib's
`dev` branch, so the plugin is always tested against the latest working build of the server. Install FederationLib from
a checkout of that branch (its dependencies are installed with it), then the plugin package:

```shell
git clone --branch dev https://github.com/nosial/federationlib
(cd federationlib && ncc build --configuration release && ncc install --package="$PWD/target/release/net.nosial.federation.ncc" --yes --reinstall)
make target/release/net.nosial.telegram_federation_plugin.ncc
ncc install --package="$PWD/target/release/net.nosial.telegram_federation_plugin.ncc" --yes --reinstall
```

The tests are configured by `phpunit.xml`, an environment variable that is already set takes precedence:

| Environment Variable    | Default                            | Description                                                         |
|-------------------------|------------------------------------|---------------------------------------------------------------------|
| `SERVER_ENDPOINT`       | `http://172.17.0.1:7000`           | The FederationLib server of the test environment                    |
| `SERVER_ACCESS_TOKEN`   | `abcdefghijklmnopqrstuvwxyz123456` | The root operator's access token (`FEDERATION_ACCESS_TOKEN`)        |
| `NCC_BUILD_OUTPUT_PATH` | `target/release/...`               | The plugin `.ncc` package to import                                 |
| `CONFIGLIB_PATH`        | `target/tests/configlib`           | Where the plugin's configuration is saved while the tests change it |

The endpoints use the Docker host's bridge address (`172.17.0.1`), the same as FederationLib's tests, so they work
from the host and from a CI job's container. Where the bridge isn't reachable (eg; Docker Desktop), use
`http://127.0.0.1:7000`. A test fails rather than being skipped if the server is unreachable. The tests configure the
plugin through its environment variables, its configuration is kept in `target/` so that the configuration of a
FederationLib server on the same machine is never changed.

### Telegram test chat

`TelegramClientTest` sends a few notifications to a real Telegram chat, so that Telegram is known to accept their
HTML and buttons. It's configured with environment variables, and with repository secrets of the same name in CI
(GitHub and Forgejo: *Settings → Actions → Secrets*):

| Environment Variable      | Description                                                    |
|---------------------------|----------------------------------------------------------------|
| `TELEGRAM_TEST_BOT_TOKEN` | The token of the test bot                                      |
| `TELEGRAM_TEST_CHAT_ID`   | The chat the test bot sends to, the bot must be a member of it |
| `TELEGRAM_TEST_TOPIC_ID`  | Optional, the forum topic of the chat                          |

They can also be set in `phpunit.xml`, `make test-env` passes the same chat to the plugin in the test environment
(an environment variable that is already set takes precedence). The test environment has to be started again after
they change. Without them, the tests that send to the chat are skipped (eg; on forks, which don't get the secrets) and
the plugin in the test environment sends nothing.

Only the tests in `FederationServer/` read the chat, they check that the server's plugin sends the notifications of the
selected events and nothing for the others. A bot never receives its own messages, so a marker message is sent after
each operation and the messages since the previous marker are read by forwarding them (the forwarded copies are
deleted right away). The notifications are told apart from those of other test runs by the records they're about. The
plugin's own tests never read the chat, they send to a local port nothing listens on: the client stops sending to a
target it couldn't reach, which tells whether a notification was sent.

 - Use a bot and a chat that only exist for testing, never the ones of a FederationLib server. A leaked token is
   revoked with [@BotFather](https://t.me/BotFather) and only affects the test chat.
 - Telegram limits a bot to about 20 messages per minute in a group, the tests wait when they would exceed it. A forum
   topic keeps the test notifications apart from anything else in the chat.
 - The secrets are masked in the CI logs, but not in the uploaded reports. The plugin never logs the bot token, keep it
   that way.

# License

This project is licensed under the MIT License, see [LICENSE](LICENSE) for more information.
