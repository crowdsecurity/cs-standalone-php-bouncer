/* eslint-disable no-undef */
const {
    removeAllDecisions,
    runCacheAction,
    publicHomepageShouldBeBanWall,
} = require("../utils/helpers");
const { APPSEC_ENABLED, APPSEC_FALLBACK } = require("../utils/constants");

describe(`Should be ban by AppSec because of timeout`, () => {
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
        if (!["ban", "Constants::REMEDIATION_BAN"].includes(APPSEC_FALLBACK)) {
            const errorMessage = `AppSec fallback must be "ban" for this test (got ${APPSEC_FALLBACK})`;
            console.error(errorMessage);
            throw new Error(errorMessage);
        }
    });

    it("Should ban for home page as this is the appsec fallback remediation", async () => {
        await publicHomepageShouldBeBanWall();
    });
});
