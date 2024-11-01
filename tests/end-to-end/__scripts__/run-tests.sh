#!/bin/bash
# Run test suite
# Usage: ./run-tests.sh  <type>  <file-list>
# type : host, docker or ci (default: host)
# file-list : a list of test files (default: empty so it will run all the tests)
# Example: ./run-tests.sh docker "./__tests__/1-live-mode.js"

YELLOW='\033[33m'
RESET='\033[0m'
if ! ddev --version >/dev/null 2>&1; then
    printf "%bDdev is required for this script. Please see docs/ddev.md.%b\n" "${YELLOW}" "${RESET}"
    exit 1
fi


TYPE=${1:-host}
FILE_LIST=${2:-""}


case $TYPE in
  "host")
    echo "Running with host stack"
    ;;

  "docker")
    echo "Running with ddev docker stack"
    ;;


  "ci")
    echo "Running in CI context"
    ;;

  *)
    echo "Unknown param '${TYPE}'"
    echo "Usage: ./run-tests.sh  <type>  <file-list>"
    exit 1
    ;;
esac


HOSTNAME=$(ddev exec printenv DDEV_HOSTNAME | sed 's/\r//g')
PHP_URL=https://$HOSTNAME
PROXY_IP=$(ddev find-ip ddev-router)
BOUNCER_KEY=$(ddev exec grep "'api_key'" /var/www/html/my-code/standalone-bouncer/scripts/settings.php | tail -1 | sed 's/api_key//g' | sed -e 's|[=>,"'\'']||g'  | sed s/'\s'//g)
GEOLOC_ENABLED=$(ddev exec grep -E "'enabled'.*,$" /var/www/html/my-code/standalone-bouncer/scripts/settings.php | sed 's/enabled//g' | sed -e 's|[=>,"'\'']||g'  | sed s/'\s'//g)
FORCED_TEST_FORWARDED_IP=$(ddev exec grep -E "'forced_test_forwarded_ip'.*,$" /var/www/html/my-code/standalone-bouncer/scripts/settings.php | sed 's/forced_test_forwarded_ip//g' | sed -e 's|[=>,"'\'']||g'  | sed s/'\s'//g)
CLEAN_CACHE_DURATION=$(ddev exec grep -E "'clean_ip_cache_duration'.*,$" /var/www/html/my-code/standalone-bouncer/scripts/settings.php | sed 's/clean_ip_cache_duration//g' | sed -e 's|[=>,"'\'']||g'  | sed s/'\s'//g)
STREAM_MODE=$(ddev exec grep -E "'stream_mode'.*,$" /var/www/html/my-code/standalone-bouncer/scripts/settings.php | sed 's/stream_mode//g' | sed -e 's|[=>,"'\'']||g'  | sed s/'\s'//g)
APPSEC_ENABLED=$(ddev exec grep -E "'use_appsec'.*,$" /var/www/html/my-code/standalone-bouncer/scripts/settings.php | sed 's/use_appsec//g' | sed -e 's|[=>,"'\'']||g'  | sed s/'\s'//g)
APPSEC_FALLBACK=$(ddev exec grep "'appsec_fallback_remediation'" /var/www/html/my-code/standalone-bouncer/scripts/settings.php | tail -1 | sed 's/appsec_fallback_remediation//g' | sed -e 's|[=>,"'\'']||g'  | sed s/'\s'//g)
APPSEC_ACTION=$(ddev exec grep "'appsec_body_size_exceeded_action'" /var/www/html/my-code/standalone-bouncer/scripts/settings.php | tail -1 | sed 's/appsec_body_size_exceeded_action//g' | sed -e 's|[=>,"'\'']||g'  | sed s/'\s'//g)
APPSEC_MAX_BODY_SIZE=$(ddev exec grep "'appsec_max_body_size_kb'" /var/www/html/my-code/standalone-bouncer/scripts/settings.php | tail -1 | sed 's/appsec_max_body_size_kb//g' | sed -e 's|[=>,"'\'']||g'  | sed s/'\s'//g)
DEBUG_MODE=$(ddev exec grep -E "'debug_mode'.*,$" /var/www/html/my-code/standalone-bouncer/scripts/settings.php | sed 's/debug_mode//g' | sed -e 's|[=>,"'\'']||g'  | sed s/'\s'//g)
JEST_PARAMS="--bail=true  --runInBand --verbose"
# If FAIL_FAST, will exit on first individual test fail
# @see CustomEnvironment.js
FAIL_FAST=true

case $TYPE in
  "host")
    cd "../"
    DEBUG_STRING="PWDEBUG=1"
    YARN_PATH="./"
    COMMAND="yarn --cwd ${YARN_PATH} cross-env"
    LAPI_URL_FROM_PLAYWRIGHT=https://localhost:8080
    CURRENT_IP=$(ddev find-ip host)
    TIMEOUT=31000
    HEADLESS=false
    SLOWMO=150
    AGENT_TLS_PATH="../../../../cfssl"
    ;;

  "docker")
    DEBUG_STRING=""
    YARN_PATH="./my-code/standalone-bouncer/tests/end-to-end"
    COMMAND="ddev exec -s playwright yarn --cwd ${YARN_PATH} cross-env"
    LAPI_URL_FROM_PLAYWRIGHT=https://crowdsec:8080
    CURRENT_IP=$(ddev find-ip playwright)
    TIMEOUT=31000
    HEADLESS=true
    SLOWMO=0
    AGENT_TLS_PATH="/var/www/html/cfssl"
    ;;

  "ci")
    DEBUG_STRING="DEBUG=pw:api"
    YARN_PATH="./my-code/standalone-bouncer/tests/end-to-end"
    COMMAND="ddev exec -s playwright xvfb-run --auto-servernum -- yarn --cwd ${YARN_PATH} cross-env"
    LAPI_URL_FROM_PLAYWRIGHT=https://crowdsec:8080
    CURRENT_IP=$(ddev find-ip playwright)
    TIMEOUT=60000
    HEADLESS=true
    SLOWMO=0
    AGENT_TLS_PATH="/var/www/html/cfssl"
    ;;

  *)
    echo "Unknown param '${TYPE}'"
    echo "Usage: ./run-tests.sh  <type>  <file-list>"
    exit 1
    ;;
esac



# Run command

$COMMAND \
PHP_URL="$PHP_URL" \
$DEBUG_STRING \
BOUNCER_KEY="$BOUNCER_KEY" \
PROXY_IP="$PROXY_IP"  \
GEOLOC_ENABLED="$GEOLOC_ENABLED" \
APPSEC_ENABLED="$APPSEC_ENABLED" \
APPSEC_FALLBACK="$APPSEC_FALLBACK" \
APPSEC_ACTION="$APPSEC_ACTION" \
APPSEC_MAX_BODY_SIZE="$APPSEC_MAX_BODY_SIZE" \
STREAM_MODE="$STREAM_MODE" \
CLEAN_CACHE_DURATION="$CLEAN_CACHE_DURATION" \
DEBUG_MODE="$DEBUG_MODE" \
FORCED_TEST_FORWARDED_IP="$FORCED_TEST_FORWARDED_IP" \
LAPI_URL_FROM_PLAYWRIGHT=$LAPI_URL_FROM_PLAYWRIGHT \
CURRENT_IP="$CURRENT_IP" \
TIMEOUT=$TIMEOUT \
HEADLESS=$HEADLESS \
FAIL_FAST=$FAIL_FAST \
SLOWMO=$SLOWMO \
AGENT_TLS_PATH=$AGENT_TLS_PATH \
yarn --cwd $YARN_PATH test \
    "$JEST_PARAMS" \
    --json \
    --outputFile=./.test-results.json \
    "$FILE_LIST"
