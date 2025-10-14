const puppeteer = require('puppeteer');

(async () => {
    const url = process.argv[2];
    if (!url) process.exit(1);

    const browser = await puppeteer.launch({ headless: true });
    const page = await browser.newPage();

    await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36');
    await page.setJavaScriptEnabled(true);
    await page.goto(url, { waitUntil: 'networkidle2', timeout: 30000 });

    const content = await page.content();
    console.log(content);

    await browser.close();
})();
