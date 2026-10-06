<?PHP
        require 'ncc';

        // FederationLib provides the plugin system, it is installed on the system (net.nosial.federation)
        import('net.nosial.federation');

        $buildOutputPath = __DIR__ . DIRECTORY_SEPARATOR . '../target/release/net.nosial.telegram_federation_plugin.ncc';
        if(getenv('NCC_BUILD_OUTPUT_PATH'))
        {
            $buildOutputPath = getenv('NCC_BUILD_OUTPUT_PATH');
        }

        if(!file_exists($buildOutputPath))
        {
            throw new Exception('Build output not found: ' . $buildOutputPath);
        }

        import($buildOutputPath);

        // The tests change the plugin's configuration, which ConfigLib saves to disk. Its configuration files are kept in
        // target/ so that the configuration of a FederationLib server on the same machine (eg; a real bot token) is
        // never overwritten
        if(!getenv('CONFIGLIB_PATH'))
        {
            putenv('CONFIGLIB_PATH=' . __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'target' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'configlib');
        }

        require __DIR__ . '/TelegramFederationPlugin/Helpers/PluginConfiguration.php';
        require __DIR__ . '/TelegramFederationPlugin/Helpers/TestChat.php';
        require __DIR__ . '/TelegramFederationPlugin/Helpers/TestData.php';
        require __DIR__ . '/TelegramFederationPlugin/Helpers/NotificationAssertions.php';
        require __DIR__ . '/TelegramFederationPlugin/Helpers/PluginTestCase.php';
        require __DIR__ . '/TelegramFederationPlugin/Helpers/FederationServerTestCase.php';

        // LogLib2's handlers may interfere with tests, so we unregister them here.
        \LogLib2\Logger::unregisterHandlers();
