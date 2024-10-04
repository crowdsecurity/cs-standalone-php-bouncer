/* eslint-disable no-undef */
const {
    removeAllDecisions,
    runCacheAction,
    publicHomepageShouldBeCaptchaWall,
    fillByName,
    publicHomepageShouldBeAccessible,
} = require("../utils/helpers");
const {
    APPSEC_ENABLED,
    APPSEC_FALLBACK,
    FORCED_TEST_FORWARDED_IP,
    CURRENT_IP,
} = require("../utils/constants");

describe(`Should be captcha by AppSec because of timeout`, () => {
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
        if (
            !["captcha", "Constants::REMEDIATION_CAPTCHA"].includes(
                APPSEC_FALLBACK,
            )
        ) {
            const errorMessage = `AppSec fallback must be "captcha" for this test (got ${APPSEC_FALLBACK})`;
            console.error(errorMessage);
            throw new Error(errorMessage);
        }
    });

    it("Should captcha for home page as this is the appsec fallback remediation", async () => {
        await publicHomepageShouldBeCaptchaWall();
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
});
