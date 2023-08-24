/* eslint-disable no-undef */
const { publicHomepageShouldBeAccessible } = require("../utils/helpers");
const { CLEAN_CACHE_DURATION } = require("../utils/constants");

describe(`Should not display errors`, () => {
    it("Should have correct settings", async () => {
        if (CLEAN_CACHE_DURATION !== "1") {
            const errorMessage = `clean_ip_cache_duration setting must be exactly 1 for this test`;
            console.error(errorMessage);
            throw new Error(errorMessage);
        }
    });

    it("Should not display error", async () => {
        await publicHomepageShouldBeAccessible();
        await expect(page).not.toHaveText("body", "Fatal error");
    });
});
