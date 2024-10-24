const {
    goToPublicPage,
    removeAllDecisions,
    runCacheAction,
    computeCurrentPageRemediation,
    getHtmlById,
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
    APPSEC_UPLOAD_TEST_URL,
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
        if (APPSEC_MAX_BODY_SIZE > 1024) {
            const errorMessage = `AppSec max size must less than "1024" for this test (got ${APPSEC_MAX_BODY_SIZE})`;
            console.error(errorMessage);
            throw new Error(errorMessage);
        }
    });

    it("Should ban when upload a too big image", async () => {
        await goToPublicPage(APPSEC_UPLOAD_TEST_URL);
        const remediation = await computeCurrentPageRemediation("Image Upload");
        await expect(remediation).toBe("bypass");

        let appsecResult = await getTextById("appsec-result");
        await expect(appsecResult).toBe("INITIAL STATE");

        await page
            .locator('input[name="image"]')
            .setInputFiles("./assets/too-big.jpg");
        await clickById("imageUpload");
        await wait(2000);

        appsecResult = await getTextById("appsec-result");
        await expect(appsecResult).toBe("403");
    });

    it("Should bypass when upload a small enough image", async () => {
        await goToPublicPage(APPSEC_UPLOAD_TEST_URL);
        const remediation = await computeCurrentPageRemediation("Image Upload");
        await expect(remediation).toBe("bypass");

        let appsecResult = await getTextById("appsec-result");
        await expect(appsecResult).toBe("INITIAL STATE");

        await page
            .locator('input[name="image"]')
            .setInputFiles("./assets/small-enough.jpg");
        await clickById("imageUpload");
        await wait(2000);

        appsecResult = await getHtmlById("appsec-result");
        await expect(appsecResult).toBe(
            '<img src="uploads/small-enough.jpg" alt="Uploaded Image">',
        );
    });
});
