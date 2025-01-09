/* eslint-disable no-undef */
const {
    CURRENT_IP,
    FORCED_TEST_FORWARDED_IP,
    STREAM_MODE,
    GEOLOC_ENABLED,
    CLEAN_CACHE_DURATION,
    APPSEC_ENABLED,
    DEBUG_LOG_PATH,
} = require("../utils/constants");

const {
    publicHomepageShouldBeAccessible,
    publicHomepageShouldBeCaptchaWall,
    captchaIpForSeconds,
    removeAllDecisions,
    runCacheAction,
    deleteFileContent,
    getFileContent,
} = require("../utils/helpers");

describe(`Stream mode run`, () => {
    beforeAll(async () => {
        await removeAllDecisions();
    });

    it("Should have correct settings", async () => {
        if (!STREAM_MODE) {
            const errorMessage = `Stream mode must be enabled for this test`;
            console.error(errorMessage);
            throw new Error(errorMessage);
        }
        if (GEOLOC_ENABLED) {
            const errorMessage = "Geolocation MUST be disabled to test this.";
            console.error(errorMessage);
            throw new Error(errorMessage);
        }
        if (CLEAN_CACHE_DURATION !== "1") {
            const errorMessage = `clean_ip_cache_duration setting must be exactly 1 for this test`;
            console.error(errorMessage);
            throw new Error(errorMessage);
        }
        if (FORCED_TEST_FORWARDED_IP !== null) {
            const errorMessage = `A forced test forwarded ip MUST NOT be set."forced_test_forwarded_ip" setting was: ${FORCED_TEST_FORWARDED_IP}`;
            console.error(errorMessage);
            throw new Error(errorMessage);
        }
        if (APPSEC_ENABLED) {
            const errorMessage = `AppSec must be disabled for this test`;
            console.error(errorMessage);
            throw new Error(errorMessage);
        }
    });

    it("Should display the homepage with no remediation", async () => {
        await runCacheAction("clear");
        await publicHomepageShouldBeAccessible();
        // count origin: clean/bypass = 1
    });

    it("Should still bypass as cache has not been refreshed", async () => {
        await captchaIpForSeconds(15 * 60, CURRENT_IP);
        await publicHomepageShouldBeAccessible();
        // count origin: clean/bypass = 2
        await runCacheAction("show-origins-count");
        const originsCount = await page.$eval(
            "#origins-count",
            (el) => el.innerText,
        );
        // Counts depends on previous tests
        await expect(originsCount).toEqual(
            '{"clean":{"bypass":2}}',
        );
    });

    it("Should display a captcha wall after cache refresh", async () => {
        await runCacheAction("refresh");
        await publicHomepageShouldBeCaptchaWall();
        // The first refresh clear the cache (during the warmup), so we loose the first metrics
        // count origin: cscli/captcha = 1
        await runCacheAction("show-origins-count");
        const originsCount = await page.$eval(
            "#origins-count",
            (el) => el.innerText,
        );
        // Counts depends on previous tests
        await expect(originsCount).toEqual(
            '{"cscli":{"captcha":1}}',
        );
    });

    it("Should still display a captcha wall as cache has not been refreshed", async () => {
        await removeAllDecisions();
        await publicHomepageShouldBeCaptchaWall();
        // count origin: cscli/captcha = 2
        await runCacheAction("show-origins-count");
        const originsCount = await page.$eval(
            "#origins-count",
            (el) => el.innerText,
        );
        // Counts depends on previous tests
        await expect(originsCount).toEqual(
            '{"cscli":{"captcha":2}}',
        );
    });

    it("Should bypass after cache refresh", async () => {
        await runCacheAction("refresh");
        await publicHomepageShouldBeAccessible();
        // count origin: cscli/captcha = 2,clean/bypass = 3
        await runCacheAction("show-origins-count");
        const originsCount = await page.$eval(
            "#origins-count",
            (el) => el.innerText,
        );
        // Counts depends on previous tests
        await expect(originsCount).toEqual(
            '{"cscli":{"captcha":2},"clean":{"bypass":1}}',
        );
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
            '{"cscli":{"captcha":2},"clean":{"bypass":1}}',
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
                `{"name":"dropped","value":2,"unit":"request","labels":{"origin":"cscli","remediation":"captcha"}},{"name":"processed","value":3,"unit":"request"}`,
            ),
        );
        // Test that count has been reset
        await runCacheAction("show-origins-count");
        originsCount = await page.$eval(
            "#origins-count",
            (el) => el.innerText,
        );
        await expect(originsCount).toEqual(
            '{"cscli":{"captcha":0},"clean":{"bypass":0}}',
        );
        await deleteFileContent(DEBUG_LOG_PATH);
        logContent = await getFileContent(DEBUG_LOG_PATH);
        await expect(logContent).toBe("");
    });
});
