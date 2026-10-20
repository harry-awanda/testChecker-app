import {spawnSync} from 'node:child_process';
import {randomBytes} from 'node:crypto';
import {mkdirSync,rmSync} from 'node:fs';
import path from 'node:path';
const directory=path.resolve('storage/framework/testing');mkdirSync(directory,{recursive:true});
const database=path.join(directory,'browser.sqlite');
const env={...process.env,APP_ENV:'testing',APP_DEBUG:'false',APP_KEY:'base64:'+randomBytes(32).toString('base64'),
    APP_URL:'http://127.0.0.1:8011',APP_LOCALE:'id',APP_FALLBACK_LOCALE:'id',DB_CONNECTION:'sqlite',DB_DATABASE:database,
    SESSION_DRIVER:'database',SESSION_ENCRYPT:'true',SESSION_SECURE_COOKIE:'false',CACHE_STORE:'database',QUEUE_CONNECTION:'sync',
    LOG_CHANNEL:'null',GEMINI_BASE_URL:'http://127.0.0.1:8012/v1beta',TEST_PASSWORD:randomBytes(24).toString('hex'),
};
const prepare=spawnSync('php',['tools/browser-fixture.php'],{env,stdio:'inherit'});
if(prepare.status!==0)process.exit(prepare.status||1);
let result;
try {result=spawnSync(process.execPath,[path.resolve('node_modules/@playwright/test/cli.js'),'test',...process.argv.slice(2)],{env,stdio:'inherit'});}
finally {for(const suffix of ['','-wal','-shm','-journal'])rmSync(database+suffix,{force:true});}
process.exit(result?.status||0);
