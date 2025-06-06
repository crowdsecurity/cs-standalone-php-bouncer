/* eslint-disable no-undef */
const fs = require("fs");

const { addDecision, deleteAllDecisions } = require("./watcherClient");
const { PHP_URL, TIMEOUT, PUBLIC_URL } = require("./constants");

const wait = async (ms) => new Promise((resolve) => setTimeout(resolve, ms));

jest.setTimeout(TIMEOUT);

const goToPublicPage = async (endpoint = PUBLIC_URL) => {
    return page.goto(`${PHP_URL}${endpoint}`);
};

const runCacheAction = async (actionType = "refresh", otherParams = "") => {
    await goToPublicPage(
        `/my-code/standalone-bouncer/tests/scripts/public/cache-actions.php?action=${actionType}${otherParams}`,
    );
    await page.waitForLoadState("networkidle");
    await expect(page).not.toMatchTitle(/404/);
    await expect(page).toMatchTitle(`Cache action: ${actionType}`);
};

const runGeolocationTest = async (ip, saveResult, brokenDb = false) => {
    let url = `/my-code/standalone-bouncer/tests/scripts/public/geolocation-test.php?ip=${ip}`;
    if (saveResult) {
        url += "&cache-duration=120";
    }
    if (brokenDb) {
        url += "&broken-db=1";
    }
    await goToPublicPage(`${url}`);
    await page.waitForLoadState("networkidle");
    await expect(page).not.toMatchTitle(/404/);
    await expect(page).toMatchTitle(`Geolocation for IP: ${ip}`);
};

const computeCurrentPageRemediation = async (
    accessibleTextInTitle = "Home page",
) => {
    const title = await page.title();
    if (title.includes(accessibleTextInTitle)) {
        return "bypass";
    }
    await expect(title).toContain("Oops");
    const description = await page.$eval(".desc", (el) => el.innerText);
    const banText = "cyber";
    const captchaText = "check";
    if (description.includes(banText)) {
        return "ban";
    }
    if (description.includes(captchaText)) {
        return "captcha";
    }

    throw Error("Current remediation can not be computed");
};

const publicHomepageShouldBeBanWall = async () => {
    await goToPublicPage();
    const remediation = await computeCurrentPageRemediation();
    await expect(remediation).toBe("ban");
};

const publicHomepageShouldBeCaptchaWall = async () => {
    await goToPublicPage();
    const remediation = await computeCurrentPageRemediation();
    await expect(remediation).toBe("captcha");
};

const publicHomepageShouldBeCaptchaWallWithoutMentions = async () => {
    await publicHomepageShouldBeCaptchaWall();
    await expect(page).not.toHaveText(
        ".main",
        "This security check has been powered by",
    );
};

const publicHomepageShouldBeCaptchaWallWithMentions = async () => {
    await publicHomepageShouldBeCaptchaWall();
    await expect(page).toHaveText(
        ".main",
        "This security check has been powered by",
    );
};

const publicHomepageShouldBeAccessible = async () => {
    await goToPublicPage();
    const remediation = await computeCurrentPageRemediation();
    await expect(remediation).toBe("bypass");
};

const banIpForSeconds = async (seconds, ip) => {
    await addDecision(ip, "ban", seconds);
    await wait(1000);
};

const captchaIpForSeconds = async (seconds, ip) => {
    await addDecision(ip, "captcha", seconds);
    await wait(1000);
};

const removeAllDecisions = async () => {
    await deleteAllDecisions();
    await wait(1000);
};

const getFileContent = async (filePath) => {
    if (fs.existsSync(filePath)) {
        return fs.readFileSync(filePath, "utf8");
    }
    return "";
};

const deleteFileContent = async (filePath) => {
    if (fs.existsSync(filePath)) {
        return fs.writeFileSync(filePath, "");
    }
    return false;
};

const fillInput = async (optionId, value) => {
    await page.fill(`[id=${optionId}]`, `${value}`);
};
const fillByName = async (name, value) => {
    await page.fill(`[name=${name}]`, `${value}`);
};

const selectElement = async (selectId, valueToSelect) => {
    await page.selectOption(`[id=${selectId}]`, `${valueToSelect}`);
};

const selectByName = async (selectName, valueToSelect) => {
    await page.selectOption(`[name=${selectName}]`, `${valueToSelect}`);
};

const clickById = async (id) => {
    await page.click(`#${id}`);
};

const getTextById = async (id) => {
    return page.locator(`#${id}`).innerText();
};

const getHtmlById = async (id) => {
    return page.locator(`#${id}`).innerHTML();
};

module.exports = {
    addDecision,
    wait,
    goToPublicPage,
    publicHomepageShouldBeBanWall,
    publicHomepageShouldBeCaptchaWall,
    publicHomepageShouldBeCaptchaWallWithoutMentions,
    publicHomepageShouldBeCaptchaWallWithMentions,
    publicHomepageShouldBeAccessible,
    banIpForSeconds,
    captchaIpForSeconds,
    removeAllDecisions,
    getFileContent,
    deleteFileContent,
    runCacheAction,
    runGeolocationTest,
    fillInput,
    fillByName,
    selectElement,
    selectByName,
    clickById,
    getTextById,
    computeCurrentPageRemediation,
    getHtmlById,
};
