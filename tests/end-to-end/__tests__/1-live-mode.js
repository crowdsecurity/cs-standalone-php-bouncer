/* eslint-disable no-undef */
const {
    CURRENT_IP,
    CLEAN_CACHE_DURATION,
    FORCED_TEST_FORWARDED_IP,
    STREAM_MODE,
    DEBUG_MODE,
    GEOLOC_ENABLED,
    DEBUG_LOG_PATH, APPSEC_ENABLED,
} = require("../utils/constants");

const {
    publicHomepageShouldBeBanWall,
    publicHomepageShouldBeCaptchaWallWithMentions,
    publicHomepageShouldBeAccessible,
    publicHomepageShouldBeCaptchaWall,
    banIpForSeconds,
    captchaIpForSeconds,
    removeAllDecisions,
    wait,
    runCacheAction,
    fillByName,
    deleteFileContent,
    getFileContent,
} = require("../utils/helpers");
const { addDecision } = require("../utils/watcherClient");

describe(`Live mode run`, () => {
    beforeAll(async () => {
        await removeAllDecisions();
        await runCacheAction("clear");
    });

    it("Should have correct settings", async () => {
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
        if (GEOLOC_ENABLED) {
            const errorMessage = "Geolocation MUST be disabled to test this.";
            console.error(errorMessage);
            throw new Error(errorMessage);
        }
        if (!DEBUG_MODE) {
            const errorMessage = `Debug mode must be enabled for this test`;
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
        // Empty log file before test
        await deleteFileContent(DEBUG_LOG_PATH);
        let logContent = await getFileContent(DEBUG_LOG_PATH);
        await expect(logContent).toBe("");
        await publicHomepageShouldBeAccessible();
        // count origin: clean/bypass = 1
        logContent = await getFileContent(DEBUG_LOG_PATH);
        await expect(logContent).toMatch(
            new RegExp(
                `{"type":"LAPI_REM_CACHED_DECISIONS","ip":"${FORCED_TEST_FORWARDED_IP || CURRENT_IP
                }","result":"miss"}`,
            ),
        );
        await deleteFileContent(DEBUG_LOG_PATH);
        logContent = await getFileContent(DEBUG_LOG_PATH);
        await expect(logContent).toBe("");
        await publicHomepageShouldBeAccessible();
        // count origin: clean/bypass = 2
        logContent = await getFileContent(DEBUG_LOG_PATH);
        await expect(logContent).toMatch(
            new RegExp(
                `{"type":"LAPI_REM_CACHED_DECISIONS","ip":"${FORCED_TEST_FORWARDED_IP || CURRENT_IP
                }","result":"hit"}`,
            ),
        );
    });

    it("Should display a captcha wall with mentions", async () => {
        await captchaIpForSeconds(
            15 * 60,
            FORCED_TEST_FORWARDED_IP || CURRENT_IP,
        );
        // Wait because clean ip cache duration is 3 seconds
        await wait(2000);
        await publicHomepageShouldBeCaptchaWallWithMentions();
        // count origin: cscli/captcha = 1,clean/bypass = 2
    });

    it("Should refresh image", async () => {
        await runCacheAction(
            "captcha-phrase",
            `&ip=${FORCED_TEST_FORWARDED_IP || CURRENT_IP}`,
        );
        const phrase = await page.$eval("h1", (el) => el.innerText);
        await publicHomepageShouldBeCaptchaWall();
        // count origin: cscli/captcha = 2,clean/bypass = 2
        await page.click("#refresh_link");
        // count origin: cscli/captcha = 3,clean/bypass = 2
        await runCacheAction(
            "captcha-phrase",
            `&ip=${FORCED_TEST_FORWARDED_IP || CURRENT_IP}`,
        );
        const newPhrase = await page.$eval("h1", (el) => el.innerText);
        await expect(newPhrase).not.toEqual(phrase);
    });

    it("Should show error message", async () => {
        await publicHomepageShouldBeCaptchaWall();
        // count origin: cscli/captcha = 4,clean/bypass = 2
        expect(await page.locator(".error").count()).toBeFalsy();
        await fillByName("phrase", "bad-value");
        await page.locator('button:text("CONTINUE")').click();
        expect(await page.locator(".error").count()).toBeTruthy();
        // count origin: cscli/captcha = 5,clean/bypass = 2
        await runCacheAction("show-origins-count");
        const originsCount = await page.$eval(
            "#origins-count",
            (el) => el.innerText,
        );
        // Counts depends on previous tests
        await expect(originsCount).toEqual(
            '{"clean":{"bypass":2},"cscli":{"captcha":5}}',
        );
    });

    it("Should solve the captcha", async () => {
        await runCacheAction(
            "captcha-phrase",
            `&ip=${FORCED_TEST_FORWARDED_IP || CURRENT_IP}`,
        );
        const phrase = await page.$eval("h1", (el) => el.innerText);
        await publicHomepageShouldBeCaptchaWall();
        // count origin: cscli/captcha = 6,clean/bypass = 2
        await fillByName("phrase", phrase);
        await page.locator('button:text("CONTINUE")').click();
        // When solving, we are redirect to / that is not accessible (403), so it's not bounced and
        // the clean/bypassc ount does not increment
        // count origin: cscli/captcha = 6,clean/bypass = 2
        await publicHomepageShouldBeAccessible();
        // count origin: cscli/captcha = 6,clean/bypass = 3
        await runCacheAction("show-origins-count");
        originsCount = await page.$eval(
            "#origins-count",
            (el) => el.innerText,
        );
        // Counts depends on previous tests
        await expect(originsCount).toEqual(
            '{"clean":{"bypass":3},"cscli":{"captcha":6}}',
        );
    });

    it("Should display a ban wall", async () => {
        await banIpForSeconds(15 * 60, FORCED_TEST_FORWARDED_IP || CURRENT_IP);
        await publicHomepageShouldBeBanWall();
        // count origin: cscli/captcha = 6,clean/bypass = 3,cscli/ban = 1
    });

    it("Should display back the homepage with no remediation", async () => {
        await removeAllDecisions();
        await publicHomepageShouldBeAccessible();
        // count origin: cscli/captcha = 6,clean/bypass = 4,cscli/ban = 1
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
            '{"clean":{"bypass":4},"cscli":{"captcha":6,"ban":1}}',
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
                `{"name":"dropped","value":6,"unit":"request","labels":{"origin":"cscli","remediation":"captcha"}},{"name":"dropped","value":1,"unit":"request","labels":{"origin":"cscli","remediation":"ban"}},{"name":"processed","value":11,"unit":"request"}`,
            ),
        );
        // Test that count has been reset
        await runCacheAction("show-origins-count");
        originsCount = await page.$eval(
            "#origins-count",
            (el) => el.innerText,
        );
        await expect(originsCount).toEqual(
            '{"clean":{"bypass":0},"cscli":{"captcha":0,"ban":0}}',
        );
        await deleteFileContent(DEBUG_LOG_PATH);
        logContent = await getFileContent(DEBUG_LOG_PATH);
        await expect(logContent).toBe("");

    });

    it("Should fallback to the selected remediation for unknown remediation", async () => {
        await removeAllDecisions();
        await runCacheAction("clear");
        await addDecision(
            FORCED_TEST_FORWARDED_IP || CURRENT_IP,
            "mfa",
            15 * 60,
        );
        await wait(1000);
        await publicHomepageShouldBeCaptchaWall();
    });
});
