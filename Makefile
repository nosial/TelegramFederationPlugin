all: target/debug/net.nosial.telegram_federation_plugin.ncc target/release/net.nosial.telegram_federation_plugin.ncc
target/debug/net.nosial.telegram_federation_plugin.ncc:
	ncc build --configuration debug --log-level debug
target/release/net.nosial.telegram_federation_plugin.ncc:
	ncc build --configuration release --log-level debug



clean:
	rm -f target/debug/net.nosial.telegram_federation_plugin.ncc
	rm -f target/release/net.nosial.telegram_federation_plugin.ncc

.PHONY: all install clean