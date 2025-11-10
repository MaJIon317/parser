#!/usr/bin/env node
/**
 * fetch.cjs
 *
 * Загружает одну страницу (с optional proxy) через Puppeteer
 * и возвращает JSON: { url, status, html }
 *
 * Использование:
 *   node fetch.cjs --url="https://example.com" --proxy="http://user:pass@host:port"
 */

const puppeteerExtra = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
const proxyChain = require('proxy-chain');
const minimist = require('minimist');
const crypto = require('crypto');

puppeteerExtra.use(StealthPlugin());

const argv = minimist(process.argv.slice(2), {
    string: ['url', 'proxy'],
    integer: ['timeout'],
    alias: { u: 'url', p: 'proxy', t: 'timeout' },
    default: { timeout: 30000 },
});

if (!argv.url) {
    console.error('Error: --url argument is required');
    process.exit(1);
}

const url = argv.url;
const rawProxy = argv.proxy || null;
const timeout = argv.timeout || 30000;

let browserInstance = null;
let anonymizedProxyUrl = null;

// --- Random UA pool ---
const UAS = [
    { ua: "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126.0.0.0 Safari/537.36", viewport: { width: 1366, height: 768 }, platform: "Win32", language: "en-US" },
    { ua: "Mozilla/5.0 (iPad; CPU OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/17.0 Mobile/15E148 Safari/605.1.15", viewport: { width: 768, height: 1024 }, platform: "iPad", language: "en-US" },
];

function getRandomUA() {
    return UAS[Math.floor(Math.random() * UAS.length)];
}

function isChallenge(html) {
    return /Just a moment|Checking your browser|challenges.cloudflare.com|cf-browser-verification/i.test(html);
}

(async () => {
    try {
        // --- Proxy (optional)
        if (rawProxy) {
            try {
                anonymizedProxyUrl = await proxyChain.anonymizeProxy(rawProxy);
            } catch (e) {
                console.error('[fetch] Failed to anonymize proxy:', e);
            }
        }

        const launchArgs = [
            '--no-sandbox', '--disable-setuid-sandbox',
            '--disable-dev-shm-usage', '--disable-extensions',
            '--disable-gpu', '--disable-background-networking',
            '--ignore-certificate-errors',
        ];
        if (anonymizedProxyUrl) launchArgs.push(`--proxy-server=${anonymizedProxyUrl}`);

        browserInstance = await puppeteerExtra.launch({ headless: false, args: launchArgs });
        const page = await browserInstance.newPage();
        const uaObj = getRandomUA();
        await setupPage(page, uaObj);

        let statusCode = null;
        page.on('response', async (response) => {
            if (response.url() === url) {
                statusCode = response.status();
            }
        });

        await page.goto(url, { waitUntil: 'networkidle2', timeout });
        let html = await page.content();

        if (isChallenge(html)) {
            html = await autoSolveChallenge(page);
        }

        const result = {
            id: crypto.createHash('md5').update(url).digest('hex'),
            url,
            status: statusCode || 0,
            html: html.toString('utf8'),
        };

        await new Promise((resolve) => {
            process.stdout.write(JSON.stringify(result, null, 2), resolve);
        });
    } catch (err) {
        console.error('[fetch] Error:', err.message || err);
        process.exit(2);
    } finally {
        try { if (browserInstance) await browserInstance.close(); } catch {}
        try { if (anonymizedProxyUrl) await proxyChain.closeAnonymizedProxy(anonymizedProxyUrl, true); } catch {}
    }
})();

async function setupPage(page, uaObj) {
    await page.setUserAgent(uaObj.ua);
    await page.setViewport(uaObj.viewport);
    await page.evaluateOnNewDocument((platform, language) => {
        Object.defineProperty(navigator, 'platform', { get: () => platform });
        Object.defineProperty(navigator, 'languages', { get: () => [language] });
        Object.defineProperty(navigator, 'webdriver', { get: () => false });
    }, uaObj.platform, uaObj.language);
    page.setDefaultNavigationTimeout(timeout);
}

async function autoSolveChallenge(page) {
    const MAX_WAIT_MS = 2 * 60 * 1000;
    const start = Date.now();

    while (Date.now() - start < MAX_WAIT_MS) {
        const html = await page.content();
        if (!isChallenge(html)) break;

        await page.mouse.move(100 + Math.random() * 200, 100 + Math.random() * 200);
        await page.mouse.wheel({ deltaY: 50 + Math.random() * 50 });
        await new Promise(r => setTimeout(r, 2000));

        try { await page.reload({ waitUntil: 'networkidle2', timeout: 15000 }); } catch {}
    }

    return await page.content();
}
