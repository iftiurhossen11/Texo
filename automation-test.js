const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const root = __dirname;
const screenshotDir = path.join(root, 'test-screenshots');
fs.mkdirSync(screenshotDir, { recursive: true });

const bravePaths = [
  'C:\\Program Files\\BraveSoftware\\Brave-Browser\\Application\\brave.exe',
  'C:\\Program Files (x86)\\BraveSoftware\\Brave-Browser\\Application\\brave.exe',
];
const bravePath = bravePaths.find((candidate) => fs.existsSync(candidate));

async function login(page, role) {
  await page.getByRole('button', { name: new RegExp(`Login as ${role}`, 'i') }).click();
  await page.getByRole('button', { name: 'Sign in', exact: true }).click();
  await page.getByText(/Welcome,/).waitFor({ state: 'visible', timeout: 15_000 });
}

async function logout(page) {
  await page.getByRole('button', { name: 'Log out', exact: true }).click();
  await page.getByRole('button', { name: 'Sign in', exact: true }).waitFor({ state: 'visible' });
}

async function documentSectionText(page) {
  return page.locator('section').filter({ hasText: 'Indexed Documents' }).first().innerText();
}

async function main() {
  const browser = await chromium.launch({
    headless: true,
    executablePath: bravePath,
  });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
  const results = [];

  try {
    await page.goto('http://127.0.0.1:5173', { waitUntil: 'networkidle' });
    await page.screenshot({ path: path.join(screenshotDir, '01-login.png'), fullPage: true });
    results.push(['Login screen loads', true]);

    await login(page, 'GM');
    await page.screenshot({ path: path.join(screenshotDir, '02-gm-dashboard.png'), fullPage: true });
    results.push(['GM login', true]);

    const emptyState = page.getByText('No matching passages found in indexed documents.');
    const background = await emptyState.evaluate((node) => {
      const box = node.closest('div');
      return {
        backgroundColor: getComputedStyle(box).backgroundColor,
        borderColor: getComputedStyle(box).borderColor,
      };
    });
    const darkBackground = background.backgroundColor === 'rgb(2, 6, 23)';
    results.push(['Retrieved sources empty state has dark background', darkBackground]);
    await page.screenshot({ path: path.join(screenshotDir, '03-gm-dark-sources.png'), fullPage: true });

    await page.getByPlaceholder(/Ask a question/i).fill('What is the AQL for major defects?');
    await page.getByRole('button', { name: 'Ask', exact: true }).click();
    await page.waitForFunction(
      () => /less than 2\.5|Access Denied|not found in the uploaded document/i.test(document.body.innerText),
      null,
      { timeout: 90_000 },
    );
    const gmBody = await page.locator('body').innerText();
    const gmAnswer = /less than 2\.5/i.test(gmBody) && gmBody.includes('aql-acceptable-quality-limits');
    results.push(['GM AQL answer and citation', gmAnswer]);
    await page.screenshot({ path: path.join(screenshotDir, '04-gm-aql-answer.png'), fullPage: true });

    const downloadPromise = page.waitForEvent('download', { timeout: 10_000 });
    await page.getByRole('button', { name: 'Export Chat', exact: true }).click();
    const download = await downloadPromise;
    await download.saveAs(path.join(screenshotDir, 'Texo_Chat_Export.pdf'));
    results.push(['Chat PDF export download', true]);

    await logout(page);
    await login(page, 'HR');
    await page.screenshot({ path: path.join(screenshotDir, '05-hr-dashboard.png'), fullPage: true });
    const hrDocuments = await documentSectionText(page);
    const hrOnlyAllowed = !hrDocuments.includes('BKMEA') && !hrDocuments.includes('QA');
    results.push(['HR cannot see QA/BKMEA documents', hrOnlyAllowed]);

    await logout(page);
    await login(page, 'Employee');
    await page.screenshot({ path: path.join(screenshotDir, '06-employee-dashboard.png'), fullPage: true });
    const employeeDocuments = await documentSectionText(page);
    const employeeOnlyGeneral = !/(QA|HR_TRAINING|PRODUCTION|SPINNING|WEAVING|KNITTING|COMPLIANCE_SUSTAINABILITY|IT_CYBERSECURITY)/.test(employeeDocuments);
    results.push(['Employee sees only General documents', employeeOnlyGeneral]);

    const failed = results.filter(([, passed]) => !passed);
    console.log(JSON.stringify({
      browser: bravePath ? 'Brave' : 'Playwright Chromium',
      screenshots: screenshotDir,
      results: Object.fromEntries(results),
      status: failed.length ? 'FAIL' : 'PASS',
    }, null, 2));
    process.exitCode = failed.length ? 1 : 0;
  } finally {
    await browser.close();
  }
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
