const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

async function run() {
    const dashboardUrl = 'https://studio.youtube.com/channel/UCwJTqySv4XgsYb1DEZQ1Beg/livestreaming/dashboard';
    const cookiesPath = path.join(__dirname, 'youtube-cookies.json');
    const screenshotPath = '/tmp/youtube-status.png';

    console.log('=== Memulai Bot YouTube Go-Live ===');

    if (!fs.existsSync(cookiesPath)) {
        console.error('Error: File youtube-cookies.json tidak ditemukan!');
        console.error('Harap export cookie akun Google Anda ke youtube-cookies.json di root project.');
        process.exit(1);
    }

    // Launch browser (headless, no-sandbox untuk root VPS)
    console.log('Membuka browser headless...');
    const browser = await puppeteer.launch({
        headless: 'new',
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-accelerated-2d-canvas',
            '--disable-gpu',
            '--window-size=1280,800'
        ]
    });

    const page = await browser.newPage();
    await page.setViewport({ width: 1280, height: 800 });

    try {
        // Load cookies
        console.log('Menginjeksi cookies...');
        const cookiesRaw = fs.readFileSync(cookiesPath, 'utf8');
        const cookies = JSON.parse(cookiesRaw);
        await page.setCookie(...cookies);

        console.log('Membuka dashboard YouTube Studio...');
        await page.goto(dashboardUrl, { waitUntil: 'networkidle2', timeout: 60000 });

        // Tunggu halaman stabil
        await page.waitForTimeout(5000);

        // Ambil screenshot untuk debug
        console.log(`Mengambil screenshot halaman...`);
        await page.screenshot({ path: screenshotPath });
        console.log(`Screenshot disimpan di: ${screenshotPath}`);

        // Cari tombol "Go Live" atau "Mulai Siaran"
        // YouTube Studio menggunakan tombol berformat teks atau ID tertentu.
        // Kita bisa cari elemen yang mengandung teks "Go Live" atau "Mulai"
        console.log('Mencari tombol Go Live...');
        const buttonText = await page.evaluate(() => {
            // Cari seluruh elemen tombol/div di halaman
            const elements = Array.from(document.querySelectorAll('button, div, ytcp-button'));
            const goLiveBtn = elements.find(el => {
                const txt = el.innerText ? el.innerText.toLowerCase() : '';
                return txt.includes('go live') || txt.includes('mulai siaran') || txt.includes('mulai');
            });

            if (goLiveBtn) {
                goLiveBtn.click();
                return 'Tombol ditemukan dan diklik!';
            }
            return 'Tombol Go Live tidak ditemukan atau sudah siaran.';
        });

        console.log(`Hasil pencarian tombol: ${buttonText}`);

        // Ambil screenshot final
        await page.waitForTimeout(5000);
        await page.screenshot({ path: screenshotPath });
        console.log('Screenshot final diperbarui.');

    } catch (err) {
        console.error('Terjadi kesalahan:', err);
    } finally {
        await browser.close();
        console.log('=== Selesai ===');
    }
}

run();
