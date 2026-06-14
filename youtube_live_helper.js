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

    throw new Error('Browser executable tidak ditemukan. Install chromium/google-chrome di server atau set PUPPETEER_EXECUTABLE_PATH.');
}

function decodePayload() {
    const encoded = process.argv[2];
    if (!encoded) {
        throw new Error('Payload automasi YouTube tidak diberikan.');
    }

    return JSON.parse(Buffer.from(encoded, 'base64').toString('utf8'));
}

function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

function extractChannelId(url) {
    const match = String(url || '').match(/\/channel\/([^/]+)/i);
    return match ? match[1] : null;
}

function normalizeCookie(cookie) {
    if (!cookie || typeof cookie !== 'object') {
        return null;
    }

    const normalized = {
        name: cookie.name,
        value: cookie.value,
    };

    if (cookie.domain) {
        normalized.domain = cookie.domain;
    }
    if (cookie.path) {
        normalized.path = cookie.path;
    }
    if (typeof cookie.expires === 'number') {
        normalized.expires = cookie.expires;
    } else if (cookie.expirationDate) {
        normalized.expires = cookie.expirationDate;
    }
    if (typeof cookie.httpOnly === 'boolean') {
        normalized.httpOnly = cookie.httpOnly;
    }
    if (typeof cookie.secure === 'boolean') {
        normalized.secure = cookie.secure;
    }
    if (cookie.sameSite) {
        const sameSite = String(cookie.sameSite).toLowerCase();
        if (sameSite === 'strict') normalized.sameSite = 'Strict';
        if (sameSite === 'lax') normalized.sameSite = 'Lax';
        if (sameSite === 'no_restriction' || sameSite === 'none') normalized.sameSite = 'None';
    }
    if (cookie.url && !normalized.domain) {
        normalized.url = cookie.url;
    }

    if (!normalized.name || typeof normalized.value === 'undefined') {
        return null;
    }

    return normalized;
}

function loadCookies(cookiePath) {
    const raw = fs.readFileSync(cookiePath, 'utf8').trim();
    if (!raw) {
        return [];
    }

    const parsed = JSON.parse(raw);

    if (Array.isArray(parsed)) {
        return parsed.map(normalizeCookie).filter(Boolean);
    }

    if (parsed && Array.isArray(parsed.cookies)) {
        return parsed.cookies.map(normalizeCookie).filter(Boolean);
    }

    if (parsed && parsed.data && Array.isArray(parsed.data.cookies)) {
        return parsed.data.cookies.map(normalizeCookie).filter(Boolean);
    }

    throw new Error('Format cookie tidak dikenali. Gunakan JSON array cookie atau objek dengan properti cookies.');
}

async function clickByText(page, texts) {
    return page.evaluate((targetTexts) => {
        const textMatches = (value, expected) => value && value.toLowerCase().includes(expected.toLowerCase());
        const candidates = Array.from(document.querySelectorAll('button, a, div[role="button"], ytcp-button, tp-yt-paper-button'));

        for (const candidate of candidates) {
            const rawText = (candidate.innerText || candidate.textContent || '').trim();
            if (!rawText) {
                continue;
            }

            const matched = targetTexts.find((text) => textMatches(rawText, text));
            if (matched) {
                candidate.click();
                return { clicked: true, label: rawText };
            }
        }

        return { clicked: false, label: null };
    }, texts);
}

async function isUnsupportedBrowserPage(page) {
    return page.evaluate(() => {
        const text = (document.body?.innerText || '').toLowerCase();
        return text.includes('unsupported browser') || text.includes('skip to youtube studio');
    });
}

async function detectLiveStatus(page) {
    return page.evaluate(() => {
        const text = (document.body?.innerText || '').toLowerCase();

        const includesAny = (needles) => needles.some((needle) => text.includes(needle));

        const livePhrases = [
            "you're live",
            'you are live',
            'anda sedang live',
            'sedang live',
            'end stream',
            'akhiri streaming',
            'excellent connection',
            'stream health',
        ];

        const readyPhrases = [
            'go live',
            'mulai siaran',
            'live control room',
            'stream setup help',
            'siarkan langsung',
        ];

        return {
            isLive: includesAny(livePhrases),
            isReadyScreen: includesAny(readyPhrases),
            pageTextSample: text.slice(0, 5000),
        };
    });
}

async function run() {
    const payload = decodePayload();
    fs.mkdirSync(payload.sessionDir, { recursive: true });
    fs.mkdirSync(path.dirname(payload.screenshotPath), { recursive: true });

    const browser = await puppeteer.launch({
        headless: 'new',
        userDataDir: payload.sessionDir,
        executablePath: findBrowserExecutable(),
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
            '--window-size=1440,900',
        ],
    });

    let result = {
        success: false,
        status: 'unknown',
        message: 'Automasi YouTube belum dijalankan.',
        currentUrl: null,
        session_valid: false,
        clicked_go_live: false,
        screenshot: payload.screenshotPath,
    };

    try {
        const page = await browser.newPage();
        await page.setViewport({ width: 1440, height: 900 });
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36');

        if (payload.cookiePath && fs.existsSync(payload.cookiePath)) {
            const cookies = loadCookies(payload.cookiePath);
            if (cookies.length > 0) {
                await page.setCookie(...cookies);
            }
        }

        const dashboardUrl = payload.channelId
            ? `https://studio.youtube.com/channel/${payload.channelId}/livestreaming/dashboard`
            : 'https://studio.youtube.com/';

        await page.goto(dashboardUrl, { waitUntil: 'networkidle2', timeout: 90000 });
        await sleep(5000);

        if (await isUnsupportedBrowserPage(page)) {
            const skipButton = await clickByText(page, ['Skip to YouTube Studio']);
            if (skipButton.clicked) {
                await sleep(5000);
            }
        }

        result.currentUrl = page.url();

        const detectedChannelId = extractChannelId(result.currentUrl);
        if (!payload.channelId && detectedChannelId && !result.currentUrl.includes('/livestreaming/dashboard')) {
            await page.goto(`https://studio.youtube.com/channel/${detectedChannelId}/livestreaming/dashboard`, {
                waitUntil: 'networkidle2',
                timeout: 90000,
            });
            await sleep(5000);
            result.currentUrl = page.url();
        }

        if (page.url().includes('accounts.google.com') || page.url().includes('ServiceLogin')) {
            result = {
                ...result,
                status: 'login_required',
                message: `Session Google untuk ${payload.googleEmail || 'akun ini'} belum aktif. Login manual atau unggah cookie yang valid terlebih dahulu.`,
            };
            await page.screenshot({ path: payload.screenshotPath, fullPage: true });
            process.stdout.write(JSON.stringify(result));
            return;
        }

        result.session_valid = true;
        const liveStatus = await detectLiveStatus(page);
        result.is_live = liveStatus.isLive;

        if (payload.action === 'status') {
            result.success = true;
            result.status = liveStatus.isLive ? 'already_live' : 'not_live';
            result.message = liveStatus.isLive
                ? `YouTube masih live untuk ${payload.googleEmail || 'akun ini'}. Start streaming akan dilewati.`
                : 'YouTube tidak terdeteksi sedang live. Aman untuk lanjut start streaming.';
            await page.screenshot({ path: payload.screenshotPath, fullPage: true });
            result.currentUrl = page.url();
            process.stdout.write(JSON.stringify(result));
            return;
        }

        if (!payload.channelId) {
            const goLiveLink = await clickByText(page, ['Go live', 'Mulai siaran', 'Live']);
            if (goLiveLink.clicked) {
                await sleep(3000);
            }
        }

        const firstClick = await clickByText(page, [
            'Go live',
            'Mulai siaran',
            'Siarkan langsung',
            'Live Control Room',
        ]);

        if (firstClick.clicked) {
            result.clicked_go_live = true;
            result.status = 'ready';
            result.message = `Halaman Go Live berhasil dibuka${payload.googleEmail ? ` untuk ${payload.googleEmail}` : ''}.`;
        } else {
            result.status = 'session_ok';
            result.message = 'Session YouTube valid, tetapi tombol Go Live tidak ditemukan otomatis. Cek screenshot untuk menyesuaikan selector.';
        }

        await sleep(4000);
        await page.screenshot({ path: payload.screenshotPath, fullPage: true });
        result.currentUrl = page.url();
        result.success = result.session_valid;
    } catch (error) {
        result = {
            ...result,
            success: false,
            status: 'automation_failed',
            message: error.message,
        };
    } finally {
        await browser.close();
    }

    process.stdout.write(JSON.stringify(result));
}

run().catch((error) => {
    process.stdout.write(JSON.stringify({
        success: false,
        status: 'fatal_error',
        message: error.message,
    }));
});
