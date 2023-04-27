![CrowdSec Logo](https://raw.githubusercontent.com/crowdsecurity/cs-standalone-php-bouncer/main/docs/images/logo_crowdsec.png)

# CrowdSec standalone PHP bouncer

> The official standalone PHP bouncer for the CrowdSec LAPI

![project is maintained](https://img.shields.io/maintenance/yes/2023.svg)
![Version](https://img.shields.io/github/v/release/crowdsecurity/cs-standalone-php-bouncer?include_prereleases)
![project is maintained](https://img.shields.io/maintenance/yes/2023.svg)
[![Test suite](https://github.com/crowdsecurity/cs-standalone-php-bouncer/actions/workflows/test-suite.yml/badge.svg)](https://github.com/crowdsecurity/cs-standalone-php-bouncer/actions/workflows/test-suite.yml)
[![Coding standards](https://github.com/crowdsecurity/cs-standalone-php-bouncer/actions/workflows/coding-standards.yml/badge.svg)](https://github.com/crowdsecurity/cs-standalone-php-bouncer/actions/workflows/coding-standards.yml)
![Licence](https://img.shields.io/github/license/crowdsecurity/cs-standalone-php-bouncer)


:books: <a href="https://doc.crowdsec.net">Documentation</a>
:diamond_shape_with_a_dot_inside: <a href="https://hub.crowdsec.net">Hub</a>
:speech_balloon: <a href="https://discourse.crowdsec.net">Discourse Forum</a>


## Overview

This bouncer allows you to protect your PHP application from IPs that have been detected by CrowdSec. Depending on 
the decision taken by CrowdSec, user will either get denied (403) or have to fill a captcha (401).

It uses the [PHP `auto_prepend_file` mechanism](https://www.php.net/manual/en/ini.core.php#ini.auto-prepend-file) and
the [Crowdsec php bouncer library](https://github.com/crowdsecurity/php-cs-bouncer) to provide bouncer/IPS capability
directly in your PHP application.

It supports "ban" and "captcha" remediations, and all decisions of type Ip, Range or Country (geolocation).


## Usage

See [User Guide](https://github.com/crowdsecurity/cs-standalone-php-bouncer/blob/main/docs/USER_GUIDE.md)

## Installation

See [Installation Guide](https://github.com/crowdsecurity/cs-standalone-php-bouncer/blob/main/docs/INSTALLATION_GUIDE.md)


## Developer guide

See [Developer guide](https://github.com/crowdsecurity/cs-standalone-php-bouncer/blob/main/docs/DEVELOPER.md)


## License

[MIT](https://github.com/crowdsecurity/cs-standalone-php-bouncer/blob/main/LICENSE)
