const {
    goToPublicPage,
    removeAllDecisions,
    runCacheAction,
    computeCurrentPageRemediation,
    publicHomepageShouldBeAccessible,
    fillInput,
    clickById,
    getTextById,
    captchaIpForSeconds,
    wait,
    deleteFileContent,
    getFileContent,
} = require("../utils/helpers");
const {
    APPSEC_ENABLED,
    APPSEC_TEST_URL,
    APPSEC_MALICIOUS_BODY,
    STREAM_MODE,
    FORCED_TEST_FORWARDED_IP,
    CURRENT_IP,
    CLEAN_CACHE_DURATION,
    DEBUG_LOG_PATH,
} = require("../utils/constants");

describe(`Should be ban by AppSec`, () => {
    beforeAll(async () => {
        await removeAllDecisions();
        await runCacheAction("clear");
    });

    it("Should have correct settings", async () => {
        if (!APPSEC_ENABLED) {
            const errorMessage = `AppSec must be enabled for this test`;
            console.error(errorMessage);
            throw new Error(errorMessage);
        }
        if (STREAM_MODE) {
            const errorMessage = `Stream mode must be disabled for this test`;
            console.error(errorMessage);
            throw new Error(errorMessage);
        }
        if (CLEAN_CACHE_DURATION !== "3") {
            const errorMessage = `clean_ip_cache_duration setting must be exactly 3 for this test (current is ${CLEAN_CACHE_DURATION})`;
            console.error(errorMessage);
            throw new Error(errorMessage);
        }
    });

    it("Should bypass for home page GET", async () => {
        await publicHomepageShouldBeAccessible();
        // count origin: clean_appsec/bypass = 1
        await runCacheAction("show-origins-count");
        const originsCount = await page.$eval(
            "#origins-count",
            (el) => el.innerText,
        );
        await expect(originsCount).toEqual(
            '{"clean_appsec":{"bypass":1}}',
        );
    });

    it("Should ban when access AppSec test page with GET", async () => {
        await goToPublicPage(APPSEC_TEST_URL);
        const remediation = await computeCurrentPageRemediation("Test AppSec");
        await expect(remediation).toBe("ban");
        // count origin: clean_appsec/bypass = 1, appsec/ban = 1
    });

    it("Should ban when access home page page with POST and malicious body", async () => {
        await goToPublicPage();
        const remediation = await computeCurrentPageRemediation();
        await expect(remediation).toBe("bypass");
        // count origin: clean_appsec/bypass = 2, appsec/ban = 1

        let appsecResult = await getTextById("appsec-result");
        await expect(appsecResult).toBe("INITIAL STATE");

        await fillInput("request-body", APPSEC_MALICIOUS_BODY);
        await clickById("appsec-post-button");
        await wait(1000);

        appsecResult = await getTextById("appsec-result");
        await expect(appsecResult).toBe("Response status: 403");
        // count origin: clean_appsec/bypass = 2, appsec/ban = 2
    });

    it("Should bypass when access home page page with POST and clean body", async () => {
        await goToPublicPage();
        const remediation = await computeCurrentPageRemediation();
        await expect(remediation).toBe("bypass");
        // count origin: clean_appsec/bypass = 3, appsec/ban = 2

        let appsecResult = await getTextById("appsec-result");
        await expect(appsecResult).toBe("INITIAL STATE");

        await fillInput("request-body", "OK");
        await clickById("appsec-post-button");
        await wait(1000);

        appsecResult = await getTextById("appsec-result");
        await expect(appsecResult).toBe("Response status: 200");
        // count origin: clean_appsec/bypass = 4, appsec/ban = 2
    });

    it("Should not use AppSec if LAPI remediation is not a bypass", async () => {
        await goToPublicPage(APPSEC_TEST_URL);
        let remediation = await computeCurrentPageRemediation("Test AppSec");
        await expect(remediation).toBe("ban");
        // count origin: clean_appsec/bypass = 4, appsec/ban = 3

        await captchaIpForSeconds(
            15 * 60,
            FORCED_TEST_FORWARDED_IP || CURRENT_IP,
        );
        // Wait because clean ip cache duration is 3 seconds
        await wait(2000);
        await goToPublicPage(APPSEC_TEST_URL);
        remediation = await computeCurrentPageRemediation("Test AppSec");
        await expect(remediation).toBe("captcha");
        // count origin: clean_appsec/bypass = 4, appsec/ban = 3, cscli/captcha = 1
    });

    it("Should push usage metrics", async () => {
        // Empty log file before test
        await deleteFileContent(DEBUG_LOG_PATH);
        let logContent = await getFileContent(DEBUG_LOG_PATH);
        await expect(logContent).toBe("");
        await runCacheAction("show-origins-count");
        let originsCount = await page.$eval(
            "#origins-count",
            (el) => el.innerText,
        );
        // Counts depends on previous tests
        await expect(originsCount).toEqual(
            '{"clean_appsec":{"bypass":4},"appsec":{"ban":3},"cscli":{"captcha":1}}',
        );

        await runCacheAction("push-usage-metrics");
        logContent = await getFileContent(DEBUG_LOG_PATH);
        await expect(logContent).toMatch(
            new RegExp(
                `"type":"LAPI_REM_CACHE_METRICS_LAST_SENT"`,
            ),
        );
        await expect(logContent).toMatch(
            new RegExp(
                `{"name":"dropped","value":3,"unit":"request","labels":{"origin":"appsec","remediation":"ban"}},{"name":"dropped","value":1,"unit":"request","labels":{"origin":"cscli","remediation":"captcha"}},{"name":"processed","value":8,"unit":"request"}`,
            ),
        );
        // Test that count has been reset
        await runCacheAction("show-origins-count");
        originsCount = await page.$eval(
            "#origins-count",
            (el) => el.innerText,
        );
        await expect(originsCount).toEqual(
            '{"clean_appsec":{"bypass":0},"appsec":{"ban":0},"cscli":{"captcha":0}}',
        );
        await deleteFileContent(DEBUG_LOG_PATH);
        logContent = await getFileContent(DEBUG_LOG_PATH);
        await expect(logContent).toBe("");
    });
});
