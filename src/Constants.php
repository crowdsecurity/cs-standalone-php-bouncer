<?php

declare(strict_types=1);

namespace CrowdSecStandalone;

use CrowdSecBouncer\Constants as LibConstants;

/**
 * Every constant of the project are set here.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2020+ CrowdSec
 * @license   MIT License
 */
class Constants extends LibConstants
{
    /** @var string The user agent suffix for CrowdSec api calls */
    public const USER_AGENT_SUFFIX = 'Standalone';
    /** @var string The last version of this bouncer */
    public const VERSION = 'v0.0.1';
}
