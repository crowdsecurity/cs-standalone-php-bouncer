/* eslint-disable no-undef */
const {
    removeAllDecisions,
    runCacheAction,
    publicHomepageShouldBeAccessible,
} = require("../utils/helpers");
const { APPSEC_ENABLED, APPSEC_FALLBACK } = require("../utils/constants");

describe(`Should be bypass by AppSec because of timeout`, () => {
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
            !["bypass", "Constants::REMEDIATION_BYPASS"].includes(
                APPSEC_FALLBACK,
            )
        ) {
            const errorMessage = `AppSec fallback must be "bypass" for this test (got ${APPSEC_FALLBACK})`;
            console.error(errorMessage);
            throw new Error(errorMessage);
        }
    });

    it("Should bypass for home page as this is the appsec fallback remediation", async () => {
        await publicHomepageShouldBeAccessible();
    });
});
