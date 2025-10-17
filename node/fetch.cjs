#!/usr/bin/env node
/**
 * fetch.cjs
 * Usage:
 *  node fetch.cjs '<URL>' [--proxy='http://user:pass@host:port'|'socks5://host:port'] [--output=path] [--timeout=30000]
 *
 * Outputs page HTML to stdout unless --output is provided.
 *
 * Dependencies:
 *   puppeteer-extra puppeteer-extra-plugin-stealth proxy-chain minimist
 */

const fs = require('fs');
const path = require('path');
const puppeteerExtra = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
const proxyChain = require('proxy-chain');
const minimist = require('minimist');

puppeteerExtra.use(StealthPlugin());

(async () => {
    const argv = minimist(process.argv.slice(2), {
        string: ['proxy', 'output'],
        integer: ['timeout'],
        alias: { p: 'proxy', o: 'output', t: 'timeout' },
        default: { timeout: 30000 },
    });

    const url = argv._[0];
    if (!url) {
        console.error('URL required. Usage: node fetch.cjs <url> [--proxy="http://user:pass@host:port"] [--output=path]');
        process.exit(1);
    }

    const rawProxy = argv.proxy || process.env.HTTP_PROXY || process.env.http_proxy || null;
    const outputFile = argv.output ? String(argv.output) : null;
    const timeout = parseInt(argv.timeout, 10) || 30000;

    let anonymizedProxyUrl;
    try {
        // If we have a proxy with credentials, use proxy-chain to create an anonymized local proxy.
        if (rawProxy) {
            // proxyChain.anonymizeProxy accepts a proxy URL and returns a new URL like http://127.0.0.1:xxxxx
            anonymizedProxyUrl = await proxyChain.anonymizeProxy(rawProxy);
            console.error(`[fetch.cjs] Using anonymized proxy: ${anonymizedProxyUrl}`);
        }
    } catch (err) {
        console.error('[fetch.cjs] Failed to anonymize proxy:', err);
        process.exit(2);
    }

    // Puppeteer launch options (common args to help avoid some sandbox/probing issues)
    const launchArgs = [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-dev-shm-usage',
        '--disable-extensions',
        '--disable-gpu',
        '--disable-background-networking',
        '--disable-sync',
        '--disable-translate',
        '--disable-default-apps',
        '--disable-infobars',
        '--ignore-certificate-errors',
        '--disable-features=site-per-process,IsolateOrigins,site-per-process',
    ];

    if (anonymizedProxyUrl) {
        // pass proxy server to Chromium
        // proxyChain returns http://127.0.0.1:xxxxx (works for socks/http)
        const urlObj = new URL(anonymizedProxyUrl);
        launchArgs.push(`--proxy-server=${urlObj.protocol}//${urlObj.hostname}:${urlObj.port}`);
    }

    const browser = await puppeteerExtra.launch({
        headless: true,
        args: launchArgs,
    });

    // ensure we will close anonymized proxy on exit
    const cleanup = async () => {
        try {
            if (anonymizedProxyUrl) {
                await proxyChain.closeAnonymizedProxy(anonymizedProxyUrl, true);
                console.error('[fetch.cjs] Closed anonymized proxy.');
            }
        } catch (e) {
            // ignore
        }
        try { await browser.close(); } catch (e) {}
    };
    process.on('exit', cleanup);
    process.on('SIGINT', () => { cleanup().then(() => process.exit(0)); });
    process.on('SIGTERM', () => { cleanup().then(() => process.exit(0)); });

    try {
        const page = await browser.newPage();

        // Recommended headers / UA
        const userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36';
        await page.setUserAgent(userAgent);

        // Standardize viewport
        await page.setViewport({ width: 1366, height: 768 });

        // Extra headers to look like a real browser
        await page.setExtraHTTPHeaders({
            'accept-language': 'en-US,en;q=0.9',
            'upgrade-insecure-requests': '1',
        });

        // Some anti-bot detectors check navigator properties. Stealth plugin handles many.
        // But additionally we patch a few values that might leak in certain setups.
        await page.evaluateOnNewDocument(() => {
            // Pass the Webdriver test.
            Object.defineProperty(navigator, 'webdriver', { get: () => false });

            // Languages
            Object.defineProperty(navigator, 'languages', { get: () => ['en-US', 'en'] });

            // Permissions
            const originalQuery = window.navigator.permissions.query;
            window.navigator.permissions.query = (parameters) =>
                parameters.name === 'notifications'
                    ? Promise.resolve({ state: Notification.permission })
                    : originalQuery(parameters);
        });

        // Optional: set reasonable timeout for navigation
        page.setDefaultNavigationTimeout(timeout);

        // go to page
        console.error(`[fetch.cjs] Navigating to ${url} (timeout ${timeout} ms) ...`);
        await page.goto(url, { waitUntil: 'networkidle2', timeout });

        // Try to wait for the LD+JSON script that contains product data (short wait, non-blocking)
        try {
            await page.waitForSelector('script[type="application/ld+json"]', { timeout: 8000 });
            console.error('[fetch.cjs] Found ld+json script(s).');
        } catch (e) {
            console.error('[fetch.cjs] ld+json script not found within 8s — continuing anyway.');
        }

        // If page uses infinite JS rendering, optionally scroll to bottom to trigger lazy loads
        const autoScroll = async (page, maxScrolls = 10) => {
            for (let i = 0; i < maxScrolls; i++) {
                await page.evaluate(() => window.scrollBy(0, window.innerHeight));
                await new Promise(resolve => setTimeout(resolve, 500)); // заменили waitForTimeout
            }
        };
        await autoScroll(page, 6);

        // Detect common bot block / captcha frames and log a warning
        const frameUrls = page.frames().map(f => f.url()).filter(Boolean);
        const hasCaptcha = frameUrls.some(u => /captcha|recaptcha|hcaptcha/i.test(u));
        if (hasCaptcha) {
            console.error('[fetch.cjs] WARNING: page contains captcha frames (may require manual solving).');
        }

        // Get the final HTML content
        const content = await page.content();

        if (outputFile) {
            const outPath = path.resolve(process.cwd(), outputFile);
            fs.writeFileSync(outPath, content, 'utf8');
            console.log(`[fetch.cjs] Saved HTML to ${outPath}`);
        } else {
            // print to stdout
            console.log(content);
        }

        await cleanup();
        process.exit(0);
    } catch (err) {
        console.error('[fetch.cjs] Error while fetching:', err);
        await cleanup();
        process.exit(3);
    }
})();
