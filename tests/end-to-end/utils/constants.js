const PUBLIC_URL =
    "/my-code/standalone-bouncer/tests/scripts/public/protected-page.php";
const APPSEC_TEST_URL =
    "/my-code/standalone-bouncer/tests/scripts/public/testappsec.php";
const APPSEC_MALICIOUS_BODY = "class.module.classLoader.resources.";
const FORCED_TEST_FORWARDED_IP =
    process.env.FORCED_TEST_FORWARDED_IP !== ""
        ? process.env.FORCED_TEST_FORWARDED_IP
        : null;
const GEOLOC_ENABLED = process.env.GEOLOC_ENABLED === "true";
const APPSEC_ENABLED = process.env.APPSEC_ENABLED === "true";
const STREAM_MODE = process.env.STREAM_MODE === "true";
const DEBUG_MODE = process.env.DEBUG_MODE === "true";
const GEOLOC_BAD_COUNTRY = "JP";
const JAPAN_IP = "210.249.74.42";
const FRANCE_IP = "78.119.253.85";
const WATCHER_LOGIN = "watcherLogin";
const WATCHER_PASSWORD = "watcherPassword";
const {
    BOUNCER_KEY,
    DEBUG,
    CURRENT_IP,
    LAPI_URL_FROM_PLAYWRIGHT,
    PROXY_IP,
    PHP_URL,
    AGENT_TLS_PATH,
    CLEAN_CACHE_DURATION,
    TIMEOUT,
} = process.env;
const AGENT_CERT_PATH = `${AGENT_TLS_PATH}/agent.pem`;
const AGENT_KEY_PATH = `${AGENT_TLS_PATH}/agent-key.pem`;
const CA_CERT_PATH = `${AGENT_TLS_PATH}/ca-chain.pem`;
const DEBUG_LOG_PATH = `${AGENT_TLS_PATH}/../my-code/standalone-bouncer/scripts/.logs/debug.log`;

module.exports = {
    APPSEC_TEST_URL,
    APPSEC_ENABLED,
    APPSEC_MALICIOUS_BODY,
    PHP_URL,
    BOUNCER_KEY,
    CLEAN_CACHE_DURATION,
    CURRENT_IP,
    DEBUG,
    DEBUG_MODE,
    DEBUG_LOG_PATH,
    FORCED_TEST_FORWARDED_IP,
    LAPI_URL_FROM_PLAYWRIGHT,
    PROXY_IP,
    PUBLIC_URL,
    TIMEOUT,
    WATCHER_LOGIN,
    WATCHER_PASSWORD,
    GEOLOC_ENABLED,
    GEOLOC_BAD_COUNTRY,
    STREAM_MODE,
    JAPAN_IP,
    FRANCE_IP,
    AGENT_CERT_PATH,
    AGENT_KEY_PATH,
    CA_CERT_PATH,
};
