#
#   TelegramFederationPlugin Test Docker Image
#
#   FederationLib's published image of its dev branch (the branch the plugin depends on) with TelegramFederationPlugin
#   built from source and included, the plugin is installed and enabled by FederationLib's docker-entrypoint.sh through
#   REQUIRE_PLUGINS and FEDERATION_PLUGINS (see docker-compose.yml). This image is only used by the test environment
#   and is never published.
#
#   Build with --pull, an outdated local copy of ghcr.io/nosial/federationlib:dev may predate the plugin system.
#
FROM ghcr.io/nosial/ncc:dev AS plugin_builder
WORKDIR /plugin
COPY . /plugin
RUN ncc build --configuration release

FROM ghcr.io/nosial/federationlib:dev AS test

LABEL org.opencontainers.image.title="TelegramFederationPlugin Test" \
      org.opencontainers.image.description="FederationServer Docker image with TelegramFederationPlugin, for testing only" \
      org.opencontainers.image.vendor="Nosial"

# Installed by docker-entrypoint.sh through REQUIRE_PLUGINS (see docker-compose.yml)
COPY --from=plugin_builder /plugin/target/release/net.nosial.telegram_federation_plugin.ncc /opt/federationlib/plugins/net.nosial.telegram_federation_plugin.ncc
