/* eslint-disable no-undef */
const { goToPublicPage } = require("../utils/helpers");
const { CLEAN_CACHE_DURATION } = require("../utils/constants");

describe(`Should display errors`, () => {
    it("Should have correct settings", async () => {
        if (CLEAN_CACHE_DURATION !== "1") {
            const errorMessage = `clean_ip_cache_duration setting must be exactly 1 for this test`;
            console.error(errorMessage);
            throw new Error(errorMessage);
        }
    });
    it("Should display error (if settings ko or something wrong while bouncing)", async () => {
        await goToPublicPage();
        await expect(page).toHaveText("body", "Fatal error");
    });
});
