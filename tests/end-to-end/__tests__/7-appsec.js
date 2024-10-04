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
} = require("../utils/helpers");
const {
    APPSEC_ENABLED,
    APPSEC_TEST_URL,
    APPSEC_MALICIOUS_BODY,
    STREAM_MODE,
    FORCED_TEST_FORWARDED_IP,
    CURRENT_IP,
    CLEAN_CACHE_DURATION,
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
    });

    it("Should ban when access AppSec test page with GET", async () => {
        await goToPublicPage(APPSEC_TEST_URL);
        const remediation = await computeCurrentPageRemediation("Test AppSec");
        await expect(remediation).toBe("ban");
    });

    it("Should ban when access home page page with POST and malicious body", async () => {
        await goToPublicPage();
        const remediation = await computeCurrentPageRemediation();
        await expect(remediation).toBe("bypass");

        let appsecResult = await getTextById("appsec-result");
        await expect(appsecResult).toBe("INITIAL STATE");

        await fillInput("request-body", APPSEC_MALICIOUS_BODY);
        await clickById("appsec-post-button");
        await wait(1000);

        appsecResult = await getTextById("appsec-result");
        await expect(appsecResult).toBe("Response status: 403");
    });

    it("Should bypass when access home page page with POST and clean body", async () => {
        await goToPublicPage();
        const remediation = await computeCurrentPageRemediation();
        await expect(remediation).toBe("bypass");

        let appsecResult = await getTextById("appsec-result");
        await expect(appsecResult).toBe("INITIAL STATE");

        await fillInput("request-body", "OK");
        await clickById("appsec-post-button");
        await wait(1000);

        appsecResult = await getTextById("appsec-result");
        await expect(appsecResult).toBe("Response status: 200");
    });

    it("Should not use AppSec if LAPI remediation is not a bypass", async () => {
        await goToPublicPage(APPSEC_TEST_URL);
        let remediation = await computeCurrentPageRemediation("Test AppSec");
        await expect(remediation).toBe("ban");

        await captchaIpForSeconds(
            15 * 60,
            FORCED_TEST_FORWARDED_IP || CURRENT_IP,
        );
        // Wait because clean ip cache duration is 3 seconds
        await wait(2000);
        await goToPublicPage(APPSEC_TEST_URL);
        remediation = await computeCurrentPageRemediation("Test AppSec");
        await expect(remediation).toBe("captcha");
    });
});
