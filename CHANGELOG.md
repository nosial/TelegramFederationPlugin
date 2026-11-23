# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).


## [1.0.1] - 2026-11-23

This update brings the project up to date with ncc 3.1.11 & Includes a rate limit handler

### Changed
 - Updated the project to use ncc 3.1.11, the assembly now includes the url, license, author,
   organization, product and copyright information
 - Added the project's icon to the README
 - A rate limit (`429`) of Telegram is honored for its `retry_after` by every process (through FederationLib's Redis
   cache, or APCu, or the process), notifications are dropped without a request meanwhile and the rate limit is only
   logged once

### Fixed
 - Corrected update_source to match the TelegramFederationPlugin repository name.



## [1.0.0] - 2026-10-06

Initial release of TelegramFederationPlugin
