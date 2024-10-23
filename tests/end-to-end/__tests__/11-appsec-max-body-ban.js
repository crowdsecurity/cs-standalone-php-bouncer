const {
    goToPublicPage,
    removeAllDecisions,
    runCacheAction,
    computeCurrentPageRemediation,
    fillInput,
    clickById,
    getTextById,
    wait,
} = require("../utils/helpers");
const {
    APPSEC_ENABLED,
    APPSEC_MAX_BODY_SIZE,
    APPSEC_ACTION,
    STREAM_MODE,
    CLEAN_CACHE_DURATION,
} = require("../utils/constants");

describe(`Should work with ban as max body`, () => {
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
        if (APPSEC_ACTION !== "block") {
            const errorMessage = `AppSec action must be "block" for this test (got ${APPSEC_ACTION})`;
            console.error(errorMessage);
            throw new Error(errorMessage);
        }
    });

    it("Should ban when access home page page with POST and too big body", async () => {
        await goToPublicPage();
        const remediation = await computeCurrentPageRemediation();
        await expect(remediation).toBe("bypass");

        let appsecResult = await getTextById("appsec-result");
        await expect(appsecResult).toBe("INITIAL STATE");

        await fillInput(
            "request-body",
            "a".repeat(APPSEC_MAX_BODY_SIZE * 1024 + 1),
        );
        await clickById("appsec-post-button");
        await wait(1000);

        appsecResult = await getTextById("appsec-result");
        await expect(appsecResult).toBe("Response status: 403");
    });

    it("Should bypass when access home page page with POST and short body", async () => {
        await goToPublicPage();
        const remediation = await computeCurrentPageRemediation();
        await expect(remediation).toBe("bypass");

        let appsecResult = await getTextById("appsec-result");
        await expect(appsecResult).toBe("INITIAL STATE");

        await fillInput(
            "request-body",
            "a".repeat(APPSEC_MAX_BODY_SIZE * 1024),
        );
        await clickById("appsec-post-button");
        await wait(1000);

        appsecResult = await getTextById("appsec-result");
        await expect(appsecResult).toBe("Response status: 200");
    });
});
