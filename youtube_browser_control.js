const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer-core');

function findBrowserExecutable() {
    const candidates = [
        process.env.PUPPETEER_EXECUTABLE_PATH,
        '/usr/bin/google-chrome-stable',
        '/usr/bin/google-chrome',
        '/usr/bin/chromium-browser',
        '/usr/bin/chromium',
    ].filter(Boolean);

    for (const candidate of candidates) {
        if (fs.existsSync(candidate)) {
            return candidate;
        }
    }

    throw new Error('Browser executable tidak ditemukan.');
}

function decodePayload() {
    const encoded = process.argv[2];
    if (!encoded) {
        throw new Error('Payload browser YouTube tidak diberikan.');
    }

    return JSON.parse(Buffer.from(encoded, 'base64').toString('utf8'));
}

function readState(statePath) {
    if (!statePath || !fs.existsSync(statePath)) {
        return {};
    }

    try {
        return JSON.parse(fs.readFileSync(statePath, 'utf8'));
    } catch (error) {
        return {};
    }
}

function writeState(statePath, state) {
    fs.mkdirSync(path.dirname(statePath), { recursive: true });
    fs.writeFileSync(statePath, JSON.stringify(state, null, 2));
}

function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

async function safeGoto(page, url) {
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await sleep(1500);
}

async function run() {
    const payload = decodePayload();
    fs.mkdirSync(payload.sessionDir, { recursive: true });
    fs.mkdirSync(path.dirname(payload.screenshotPath), { recursive: true });

    const state = readState(payload.statePath);
    const defaultUrl = 'https://accounts.google.com/';
    const targetUrl = payload.url || state.currentUrl || defaultUrl;

    const browser = await puppeteer.launch({
        headless: 'new',
        userDataDir: payload.sessionDir,
        executablePath: findBrowserExecutable(),
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
            '--window-size=1365,768',
        ],
    });

    const result = {
        success: false,
        action: payload.action,
        currentUrl: null,
        screenshot: payload.screenshotPath,
        message: 'Aksi browser belum dijalankan.',
    };

    try {
        const page = await browser.newPage();
        await page.setViewport({ width: 1365, height: 768 });
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36');
        await safeGoto(page, targetUrl);

        if (payload.action === 'click' || payload.action === 'click_type') {
            await page.mouse.click(Number(payload.x || 0), Number(payload.y || 0));
            await sleep(500);
        }

        if (payload.action === 'click_type' && payload.text) {
            await page.keyboard.type(String(payload.text), { delay: 25 });
            await sleep(500);
        }

        if ((payload.action === 'click_type' && payload.pressEnter) || payload.action === 'key') {
            await page.keyboard.press(payload.key || 'Enter');
            await sleep(2500);
        }

        if (payload.action === 'navigate' && payload.url) {
            await safeGoto(page, payload.url);
        }

        await page.screenshot({ path: payload.screenshotPath, fullPage: true });
        result.success = true;
        result.currentUrl = page.url();
        result.message = 'Aksi browser VPS selesai. Screenshot terbaru sudah disimpan.';
        writeState(payload.statePath, {
            currentUrl: result.currentUrl,
            updatedAt: new Date().toISOString(),
        });
    } catch (error) {
        result.success = false;
        result.message = error.message;
    } finally {
        await browser.close();
    }

    process.stdout.write(JSON.stringify(result));
}

run().catch((error) => {
    process.stdout.write(JSON.stringify({
        success: false,
        message: error.message,
    }));
});
