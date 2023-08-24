/* eslint-disable no-undef */
const {
    CURRENT_IP,
    CLEAN_CACHE_DURATION,
    FORCED_TEST_FORWARDED_IP,
    STREAM_MODE,
    DEBUG_MODE,
    GEOLOC_ENABLED,
    DEBUG_LOG_PATH,
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
            const errorMessage = `clean_ip_cache_duration setting must be exactly 3 for this test`;
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
    });

    it("Should display the homepage with no remediation", async () => {
        // Empty log file before test
        await deleteFileContent(DEBUG_LOG_PATH);
        let logContent = await getFileContent(DEBUG_LOG_PATH);
        await expect(logContent).toBe("");
        await publicHomepageShouldBeAccessible();
        logContent = await getFileContent(DEBUG_LOG_PATH);
        await expect(logContent).toMatch(
            new RegExp(
                `{"type":"LAPI_REM_CACHED_DECISIONS","ip":"${CURRENT_IP}","result":"miss"}`,
            ),
        );
        await deleteFileContent(DEBUG_LOG_PATH);
        logContent = await getFileContent(DEBUG_LOG_PATH);
        await expect(logContent).toBe("");
        await publicHomepageShouldBeAccessible();
        logContent = await getFileContent(DEBUG_LOG_PATH);
        await expect(logContent).toMatch(
            new RegExp(
                `{"type":"LAPI_REM_CACHED_DECISIONS","ip":"${CURRENT_IP}","result":"hit"}`,
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
    });

    it("Should refresh image", async () => {
        await runCacheAction(
            "captcha-phrase",
            `&ip=${FORCED_TEST_FORWARDED_IP || CURRENT_IP}`,
        );
        const phrase = await page.$eval("h1", (el) => el.innerText);
        await publicHomepageShouldBeCaptchaWall();
        await page.click("#refresh_link");
        await runCacheAction(
            "captcha-phrase",
            `&ip=${FORCED_TEST_FORWARDED_IP || CURRENT_IP}`,
        );
        const newPhrase = await page.$eval("h1", (el) => el.innerText);
        await expect(newPhrase).not.toEqual(phrase);
    });

    it("Should show error message", async () => {
        await publicHomepageShouldBeCaptchaWall();
        expect(await page.locator(".error").count()).toBeFalsy();
        await fillByName("phrase", "bad-value");
        await page.locator('button:text("CONTINUE")').click();
        expect(await page.locator(".error").count()).toBeTruthy();
    });

    it("Should solve the captcha", async () => {
        await runCacheAction(
            "captcha-phrase",
            `&ip=${FORCED_TEST_FORWARDED_IP || CURRENT_IP}`,
        );
        const phrase = await page.$eval("h1", (el) => el.innerText);
        await publicHomepageShouldBeCaptchaWall();
        await fillByName("phrase", phrase);
        await page.locator('button:text("CONTINUE")').click();
        await publicHomepageShouldBeAccessible();
    });

    it("Should display a ban wall", async () => {
        await banIpForSeconds(15 * 60, FORCED_TEST_FORWARDED_IP || CURRENT_IP);
        await publicHomepageShouldBeBanWall();
    });

    it("Should display back the homepage with no remediation", async () => {
        await removeAllDecisions();
        await publicHomepageShouldBeAccessible();
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
