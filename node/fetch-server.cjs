#!/usr/bin/env node

const path = require("path");
const fs = require("fs");
const express = require("express");
const puppeteerExtra = require("puppeteer-extra");
const StealthPlugin = require("puppeteer-extra-plugin-stealth");
const proxyChain = require("proxy-chain");
const crypto = require("crypto");

puppeteerExtra.use(StealthPlugin());

const app = express();
app.use(express.json());

let browser = null;
let anonymizedProxyUrl = null;

const UASModule = require("./utils/userAgents");
const UAS = UASModule.default;
const LANGSModule = require("./utils/langMap");
const LANGS = LANGSModule.default;

const COOKIES_DIR = path.resolve("./cookies");
if (!fs.existsSync(COOKIES_DIR)) fs.mkdirSync(COOKIES_DIR, { recursive: true });

// ----------------- Helpers -----------------
function sleep(ms) { return new Promise(res => setTimeout(res, ms)); }

function getRandomUA(geo) {
    if (!Array.isArray(UAS)) throw new Error("UAS недействителен");
    const list =
        geo && UAS.some(u => u.geo?.toUpperCase() === geo.toUpperCase())
            ? UAS.filter(u => u.geo?.toUpperCase() === geo.toUpperCase())
            : UAS;
    return list[Math.floor(Math.random() * list.length)];
}

// improved isChallenge: checks CF markers AND heuristics for "real page"
function isChallenge(html) {
    if (!html || typeof html !== "string") return true;
    const lower = html.toLowerCase();

    return (
        lower.includes("/cdn-cgi/challenge-platform/") ||
        lower.includes("window._cf_chl_opt") ||
        lower.includes("__cf_chl_") ||
        lower.includes("cf-browser-verification") ||
        lower.includes("just a moment") ||
        lower.includes("checking your browser before accessing") ||
        lower.includes("enable javascript and cookies") ||
        lower.includes("data-cf-beacon") ||
        lower.includes("cf-chl-bypass") ||
        lower.includes("challenge-form") ||
        lower.includes("turnstile") ||
        lower.includes("captcha-container") ||
        (/<meta[^>]+http-equiv=["']?refresh["']?/i).test(html)
    );
}

function getCookieFilePath(domain) {
    return path.join(COOKIES_DIR, `${domain}.json`);
}
function loadCookies(domain) {
    const fp = getCookieFilePath(domain);
    if (!fs.existsSync(fp)) return [];
    try {
        const cookies = JSON.parse(fs.readFileSync(fp, "utf8"));
        const now = Date.now() / 1000;
        return cookies.filter(c => {
            if (c.name.startsWith('__cf_') || c.name === 'cf_clearance') return false;
            return !(c.expires && c.expires < now);
        });
    } catch {
        return [];
    }
}
function cleanCookies(cookies) {
    const now = Date.now() / 1000;
    return cookies.filter(c => {
        if (c.name.startsWith('__cf_') || c.name === 'cf_clearance') return false; // Cloudflare cookies
        if (c.expires && c.expires < now) return false; // expired
        return true;
    });
}
function saveCookies(domain, cookies) {
    try {
        fs.writeFileSync(getCookieFilePath(domain), JSON.stringify(cookies, null, 2), "utf8");
        console.log(`[fetch-server] ✅ Сохранено ${cookies.length} cookies для ${domain}`);
    } catch (e) {
        console.warn('[fetch-server] Не удалось сохранить cookies:', e.message);
    }
}

// ----------------- Browser -----------------
async function getBrowser(proxy) {
    if (browser && browser.isConnected()) return browser;

    console.log("[fetch-server] Запуск браузера...");
    if (proxy) {
        try {
            anonymizedProxyUrl = await proxyChain.anonymizeProxy(proxy);
        } catch (e) {
            console.warn('[fetch-server] Не удалось анонимизировать прокси:', e.message);
        }
    }

    const args = [
        "--no-sandbox",
        "--disable-setuid-sandbox",
        "--disable-dev-shm-usage",
        "--disable-extensions",
        "--disable-gpu",
        "--disable-background-networking",
        "--ignore-certificate-errors",
    ];
    if (anonymizedProxyUrl) args.push(`--proxy-server=${anonymizedProxyUrl}`);

    browser = await puppeteerExtra.launch({
        headless: true,
        args,
        defaultViewport: null,
    });
    return browser;
}

// ----------------- Page setup -----------------
async function setupPage(page, uaObj, language = "zh-CN") {
    try {
        if (uaObj && uaObj.ua) await page.setUserAgent(uaObj.ua);
        if (uaObj && uaObj.viewport) await page.setViewport(uaObj.viewport);
        await page.evaluateOnNewDocument((platform, language) => {
            try {
                Object.defineProperty(navigator, "platform", { get: () => platform });
                Object.defineProperty(navigator, "languages", { get: () => [language] });
                Object.defineProperty(navigator, "language", { get: () => language });
                Object.defineProperty(navigator, "webdriver", { get: () => false });
            } catch (e) {}
        }, uaObj?.platform || 'Win32', language);
        page.setDefaultNavigationTimeout(20000);
    } catch (e) {
        console.warn('[fetch-server] Предупреждение при setupPage:', e.message);
    }
}

// ----------------- Turnstile / click helper -----------------
async function clickTurnstile(page, maxAttempts = 6) {
    for (let attempt = 0; attempt < maxAttempts; attempt++) {
        try {
            const selectors = [
                'div#JHsxi5 label',
                'iframe[src*="turnstile"]',
                'iframe[src*="challenge"]',
                'input[type="checkbox"]',
                '.cf-turnstile, .cf-turnstile-checkbox, .cf-challenge, .turnstile-checkbox',
                'div.recaptcha-checkbox-border'
            ];

            const frames = page.frames();
            for (const f of frames) {
                const fu = f.url() || '';
                if (/turnstile|challenge|cdn-cgi/i.test(fu)) {
                    const inside = await f.$('label, input[type="checkbox"], .checkbox, .cf-turnstile');
                    if (inside) {
                        try {
                            const box = await inside.boundingBox();
                            if (box) {
                                await page.mouse.move(box.x + box.width/2, box.y + box.height/2, { steps: 6 });
                                await sleep(300 + Math.random() * 300);
                                await inside.click({ delay: 80 + Math.random() * 120 }).catch(()=>{});
                                console.log('[fetch-server] ✅ Клик по элементу внутри iframe');
                                return true;
                            } else {
                                await inside.click({ delay: 80 + Math.random() * 120 }).catch(()=>{});
                                console.log('[fetch-server] ✅ Клик по элементу внутри iframe (без bbox)');
                                return true;
                            }
                        } catch (e) { }
                    }
                }
            }

            for (const sel of selectors) {
                const el = await page.$(sel);
                if (!el) continue;
                try {
                    const box = await el.boundingBox().catch(()=>null);
                    if (box) {
                        await page.mouse.move(box.x + box.width/2, box.y + box.height/2, { steps: 6 });
                        await sleep(200 + Math.random() * 300);
                        await el.click({ delay: 80 + Math.random() * 120 }).catch(()=>{});
                        console.log('[fetch-server] ✅ Клик по элементу Turnstile');
                        return true;
                    } else {
                        await el.click({ delay: 80 + Math.random() * 120 }).catch(()=>{});
                        console.log('[fetch-server] ✅ Клик по элементу Turnstile (без bbox)');
                        return true;
                    }
                } catch (e) {}
            }
        } catch (err) {
            console.warn('[fetch-server] Попытка clickTurnstile не удалась:', err.message);
        }
        await sleep(800 + Math.random() * 1200);
    }
    console.warn('[fetch-server] Click Turnstile: элемент не найден / таймаут');
    return false;
}

// ----------------- waitForChallengePass -----------------
async function waitForChallengePass(page, opts = {}) {
    const {
        maxWaitMs = 20000,
        pollInterval = 1000,
        reloadEvery = 6,
    } = opts;

    const start = Date.now();
    let iter = 0;

    while (Date.now() - start < maxWaitMs) {
        iter++;
        let html = '';
        try { html = await page.content(); } catch (e) { html = ''; }

        if (!isChallenge(html)) {
            console.log('[fetch-server] Обнаружена обычная страница без проверки');
            return true;
        }

        console.log(`[fetch-server] Проверка ещё активна (итерация ${iter})`);

        try {
            const clicked = await clickTurnstile(page, 2);
            if (clicked) {
                await sleep(1200 + Math.random() * 1800);
                try { html = await page.content(); } catch (e) { }
                if (!isChallenge(html)) {
                    console.log('[fetch-server] Пройдено после клика по Turnstile');
                    return true;
                }
            }
        } catch (e) {
            console.warn('[fetch-server] Ошибка clickTurnstile:', e.message);
        }

        try {
            await page.mouse.move(100 + Math.random()*400, 100 + Math.random()*300, { steps: 6 });
            await page.mouse.wheel({ deltaY: 20 + Math.random()*80 });
        } catch (e) {}

        if (iter % reloadEvery === 0) {
            try {
                console.log('[fetch-server] Попытка периодической перезагрузки');
                await page.reload({ waitUntil: 'domcontentloaded', timeout: 30000 }).catch(()=>{});
            } catch (e) {
                console.warn('[fetch-server] Перезагрузка не удалась:', e.message);
            }
            await sleep(800 + Math.random()*1200);
        } else {
            await sleep(pollInterval + Math.random()*800);
        }
    }

    console.warn('[fetch-server] waitForChallengePass: таймаут');
    return false;
}

// ----------------- trySolveChallenge -----------------
async function trySolveChallenge(page, targetUrl, opts = {}) {
    const label = 'trySolveChallenge';
    let timerActive = false;
    try {
        console.time(label);
        timerActive = true;

        const { primaryMaxMs = 15000, homeMaxMs = 20000 } = opts;
        let homeUrl = null;

        try {
            const u = new URL(targetUrl);
            homeUrl = `${u.protocol}//${u.hostname}${u.port ? ':' + u.port : ''}/`;
        } catch (e) {
            console.warn('[fetch-server] Не удалось вычислить homeUrl:', e.message);
        }

        const ok = await waitForChallengePass(page, { maxWaitMs: primaryMaxMs, pollInterval: 1000, reloadEvery: 6 });
        console.log('[fetch-server] Результат проверки основной страницы:', ok);
        if (ok) return true;

        if (homeUrl) {
            console.log('[fetch-server] Переход на домашнюю страницу для попытки:', homeUrl);
            await page.goto(homeUrl, { waitUntil: 'domcontentloaded', timeout: 15000 }).catch(()=>{});
            const okHome = await waitForChallengePass(page, { maxWaitMs: homeMaxMs, pollInterval: 1200, reloadEvery: 4 });
            console.log('[fetch-server] Результат проверки домашней страницы:', okHome);
            if (okHome) {
                console.log('[fetch-server] Пройдено на home, возвращаемся к целевой странице для финальной проверки');
                await page.goto(targetUrl, { waitUntil: 'domcontentloaded', timeout: 15000 }).catch(()=>{});
                const finalOk = await waitForChallengePass(page, { maxWaitMs: primaryMaxMs, pollInterval: 1000, reloadEvery: 6 });
                console.log('[fetch-server] Финальная проверка целевой страницы:', finalOk);
                return finalOk;
            }
        }

        return false;
    } catch (e) {
        console.warn('[fetch-server] Ошибка trySolveChallenge:', e.message);
        return false;
    } finally {
        if (timerActive) console.timeEnd(label);
    }
}

function safeUnlink(filePath) {
    try {
        if (fs.existsSync(filePath)) {
            fs.unlinkSync(filePath);
            console.log(`[fetch-server] 🧹 Удалён файл: ${filePath}`);
        }
    } catch (e) {
        console.warn(`[fetch-server] ⚠️ Не удалось удалить ${filePath}: ${e.message}`);
    }
}


// ----------------- /fetch handler -----------------
app.post("/fetch", async (req, res) => {
    const { url, proxy, geo = "zh-CN" } = req.body;
    if (!url) return res.status(400).json({ error: "Отсутствует URL" });

    const start = Date.now();
    let page = null;

    try {
        const browserInstance = await getBrowser(proxy);
        page = await browserInstance.newPage();

        const domain = new URL(url).hostname;
        const oldCookies = loadCookies(domain);
        const safeCookies = cleanCookies(oldCookies);
        if (safeCookies.length) {
            try { await page.setCookie(...oldCookies); console.log(`[fetch-server] Загружено ${oldCookies.length} cookies для ${domain}`); } catch (e) { console.warn('[fetch-server] setCookie не удалось:', e.message); }
        }

        const uaObj = getRandomUA(geo);
        const language = LANGS[geo?.toUpperCase()] || "zh-CN";
        await setupPage(page, uaObj, language);

        let statusCode = 0;
        page.on("response", r => { try { if (r.url() === url) statusCode = r.status(); } catch(e){} });

        try {
            await page.goto(url, { waitUntil: "domcontentloaded", timeout: 30000 });
        } catch (err) {
            console.warn('[fetch-server] Ошибка загрузки, очищаем cookies и повторяем...');
            safeUnlink(getCookieFilePath(domain));
            await page.deleteCookie(...(await page.cookies()));
            await page.goto(url, { waitUntil: "domcontentloaded", timeout: 30000 });
        }

        const solved = await trySolveChallenge(page, url, { primaryMaxMs: 30000, homeMaxMs: 20000 });
        console.log('[fetch-server] trySolveChallenge результат:', solved);
        if (!solved) throw new Error("Cloudflare challenge не пройден");

        const html = await page.content();
        try {
            const cookies = await page.cookies();
            saveCookies(domain, cookies);
        } catch (e) {
            console.warn('[fetch-server] Не удалось получить/сохранить cookies:', e.message);
        }

        res.json({
            id: crypto.createHash("md5").update(url).digest("hex"),
            url,
            status: statusCode || 0,
            elapsedMs: Date.now() - start,
            html: html || null,
        });
    } catch (err) {
        console.error("[fetch-server] Ошибка:", err && err.message ? err.message : err);
        res.status(500).json({ error: err && err.message ? err.message : String(err) });
    } finally {
        try { if (page) await page.close(); } catch {}
    }
});

// ----------------- graceful shutdown -----------------
process.on("SIGINT", async () => {
    console.log("[fetch-server] Завершение работы...");
    try { if (browser) await browser.close(); } catch {}
    try { if (anonymizedProxyUrl) await proxyChain.closeAnonymizedProxy(anonymizedProxyUrl, true); } catch {}
    process.exit(0);
});

// 🕒 Плановый перезапуск браузера каждые 3 часа
setInterval(async () => {
    if (browser) {
        console.log('[fetch-server] Плановый перезапуск браузера...');
        try { await browser.close(); } catch {}
        browser = null;
    }
}, 1000 * 60 * 60 * 3); // каждые 3 часа

app.listen(3200, () => console.log("[fetch-server] Слушаем порт 3200"));
