![CrowdSec Logo](images/logo_crowdsec.png)

# CrowdSec standalone PHP bouncer


## Installation Guide

**Table of Contents**
<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->

- [Requirements](#requirements)
- [Installation](#installation)
  - [Prerequisite](#prerequisite)
    - [Install composer](#install-composer)
    - [Install GIT](#install-git)
    - [Install CrowdSec](#install-crowdsec)
  - [Server and bouncer setup](#server-and-bouncer-setup)
    - [Bouncer sources copy](#bouncer-sources-copy)
    - [Files permission](#files-permission)
    - [Settings file](#settings-file)
    - [`auto_prepend_file` directive](#auto_prepend_file-directive)
    - [Stream mode cron task](#stream-mode-cron-task)
    - [Cache pruning cron task](#cache-pruning-cron-task)
    - [Usage metrics push cron task](#usage-metrics-push-cron-task)
- [Upgrade](#upgrade)
  - [Before upgrading](#before-upgrading)
  - [Retrieve the last tag](#retrieve-the-last-tag)
  - [Checkout to last tag and update sources](#checkout-to-last-tag-and-update-sources)

<!-- END doctoc generated TOC please keep comment here to allow auto update -->


## Requirements

- PHP >= 7.2.5
- required PHP extensions: `ext-curl`, `ext-gd`, `ext-json`, `ext-mbstring`

## Installation

### Prerequisite

#### Install composer

Please follow [this documentation](https://getcomposer.org/download/) to install composer.

#### Install GIT

Please follow [this documentation](https://git-scm.com/book/en/v2/Getting-Started-Installing-Git) to install GIT.

#### Install CrowdSec 

To be able to use this bouncer, the first step is to install [CrowdSec v1](https://doc.crowdsec.net/docs/getting_started/install_crowdsec/). CrowdSec is only in charge of the "detection", and won't block anything on its own. You need to deploy a bouncer to "apply" decisions.

Please note that first and foremost a CrowdSec agent must be installed on a server that is accessible by this bouncer.

### Server and bouncer setup

Once you set up your server as below, every browser access to a PHP script will be bounced by the standalone bouncer.

You will have to :

- retrieve sources of the bouncer in some `/path/to/the/crowdsec-standalone-bouncer` folder
- give the correct permission for the folder that contains the bouncer
- copy the `scripts/settings.php.dist` file to a `scripts/settings.php` file and edit it.
- set an `auto_prepend_file` directive in your PHP setup.
- Optionally, if you want to use the standalone bouncer in stream mode, you will have to set a cron task to refresh 
  cache periodically.

#### Bouncer sources copy

- Create a folder that will contain the project sources: 

```bash
sudo mkdir -p /var/www/crowdsec-standalone-bouncer
```

We use here `/var/www/crowdsec-standalone-bouncer` but you can choose the path that suits your needs.

- Change permission to allow composer to be run in this folder. As you should run composer with your user, this 
  can be done with: 

```bash
sudo chown -R $(whoami):$(whoami) /var/www/crowdsec-standalone-bouncer
```

- Retrieve the last version of the bouncer:

```bash
composer create-project crowdsec/standalone-bouncer /var/www/crowdsec-standalone-bouncer --keep-vcs
```

Note that we have to keep the vcs data as we will use it to update the bouncer when a new version is available.

#### Files permission

The owner of the `/var/www/crowdsec-standalone-bouncer` folder should be your web-server owner (e.g. `www-data`) and the group should have the write permission on this folder.

You can achieve it by running commands like:

```bash
sudo chown -R www-data /var/www/crowdsec-standalone-bouncer
sudo chmod g+w /var/www/crowdsec-standalone-bouncer

```

#### Settings file

Please copy the `scripts/settings.php.dist` file to a `scripts/settings.php` file and fill the necessary settings in it 
(see [Configurations settings](../USER_GUIDE.md#configurations) for more details).

For a quick start, simply search for `YOUR_BOUNCER_API_KEY` in the `settings.php` file and set the bouncer key.
To obtain a bouncer key, you can run the `cscli` bouncer creation command:

```
sudo cscli bouncers add standalone-bouncer
```

#### `auto_prepend_file` directive

We will now describe how to set an `auto_prepend_file` directive in order to call the `scripts/bounce.php` for each php access.

Adding an `auto_prepend_file` directive can be done in different ways:

###### `.ini` file

You should add this line to a `.ini` file :

    auto_prepend_file = /var/www/crowdsec-standalone-bouncer/scripts/bounce.php

###### Nginx

If you are using Nginx, you should modify your Nginx configuration file by adding a `fastcgi_param` directive. The php block should look like below:

```
location ~ \.php$ {
    ...
    ...
    ...
    fastcgi_param PHP_VALUE "auto_prepend_file=/var/www/crowdsec-standalone-bouncer/scripts/bounce.php";
}
```

###### Apache

If you are using Apache, you should add this line to your `.htaccess` file:

    php_value auto_prepend_file "/var/www/crowdsec-standalone-bouncer/scripts/bounce.php"

or modify your `Virtual Host` accordingly:

```
<VirtualHost ...>
    ...
    ...
    php_value auto_prepend_file "/var/www/crowdsec-standalone-bouncer/scripts/bounce.php"
    
</VirtualHost>
```

#### Stream mode cron task

To use the stream mode, you first have to set the `stream_mode` setting value to `true` in your `script/settings.php` file. 

Then, you could edit the web server user (e.g. `www-data`) crontab: 

```shell
sudo -u www-data crontab -e
```

and add the following line

```shell
*/15 * * * * /usr/bin/php /var/www/crowdsec-standalone-bouncer/scripts/refresh-cache.php
```

In this example, cache is refreshed every 15 minutes, but you can modify the cron expression depending on your needs.

#### Cache pruning cron task

If you use the PHP file system as cache, you should prune the cache with a cron job:

```shell
sudo -u www-data crontab -e
```

and add the following line

```shell
0 0 * * * /usr/bin/php /var/www/crowdsec-standalone-bouncer/scripts/prune-cache.php
```

In this example, cache is pruned at midnight every day, but you can modify the cron expression depending on your needs.

#### Usage metrics push cron task

If you want to push usage metrics to the CrowdSec API, you should add a cron job:

```shell
sudo -u www-data crontab -e
```

and add the following line

```shell
0 0 * * * /usr/bin/php /var/www/crowdsec-standalone-bouncer/scripts/push-usage-metrics.php
```

## Upgrade

When a new release of the bouncer is available, you may want to update sources to the last version.

### Before upgrading

**Please look at the [CHANGELOG](https://github.com/crowdsecurity/cs-standalone-php-bouncer/blob/main/CHANGELOG.md) before upgrading in order to see the list of changes that could break your application.** 

To limit the risk of breaking your web application during upgrade, you can perform the following actions to disable bouncing:

- Remove the `auto_prepend_file` directive that point to the `bounce.php` file  and restart your web server
- Disable any scheduled cron task linked to bouncer feature

Alternatively, but a little more risky, you could disable bouncing by editing the `scripts/settings.php` file and set the value `'bouncing_disabled'` for the `'bouncing_level'` parameter.

Once the update is done, you can reactivate the bounce. You could look at the `/var/www/crowdsec-standalone-bouncer/scripts/.logs` to see if all is working as expected.

Below are the steps to take to upgrade your current bouncer: 

### Retrieve the last tag

As we kept the vcs data during installation (with the `--keep-vcs` flag), we can use git to get the last tagged sources:

```bash
cd /var/www/crowdsec-standalone-bouncer
git fetch
```

If you get an error message about "detected dubious ownership", you can run 

```bash
git config --global --add safe.directory /var/www/crowdsec-standalone-bouncer
```

You should see a list of tags (`vX.Y.Z` format )that have been published after your initial installation.

### Checkout to last tag and update sources

Once you have picked up the `vX.Y.Z` tag you want to try, you could switch to it and update composer dependencies:

```bash
git checkout vX.Y.Z && composer update
```

