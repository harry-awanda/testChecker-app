import {defineConfig} from '@playwright/test';
import {existsSync} from 'node:fs';
const channel=process.env.PLAYWRIGHT_CHANNEL || (process.platform==='win32'&&existsSync('C:/Program Files/Google/Chrome/Application/chrome.exe')?'chrome':undefined);
export default defineConfig({
    testDir:'tests/Browser',fullyParallel:false,workers:1,timeout:60000,
    expect:{timeout:10000},reporter:'list',
    use:{baseURL:'http://127.0.0.1:8011',browserName:'chromium',channel,trace:'retain-on-failure',screenshot:'only-on-failure'},
    webServer:[
        {command:'php -d post_max_size=16M -d memory_limit=512M -d max_execution_time=180 -S 127.0.0.1:8011 -t public tools/dev-server.php',url:'http://127.0.0.1:8011/login',reuseExistingServer:false,timeout:30000,stdout:'ignore',stderr:'ignore'},
        {command:'node tools/mock-gemini.mjs',url:'http://127.0.0.1:8012/health',reuseExistingServer:false,timeout:30000,stdout:'ignore',stderr:'ignore'},
    ],
});
