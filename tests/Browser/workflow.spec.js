import {test,expect} from '@playwright/test';
const secret=process.env.TEST_PASSWORD;
async function login(page,email='teacher@testchecker.invalid'){
    await page.goto('/login');await page.getByLabel('Email',{exact:true}).fill(email);
    await page.getByLabel('Kata sandi',{exact:true}).fill(secret);await page.getByRole('button',{name:'Masuk ke ruang kerja'}).click();
    await expect(page.getByRole('heading',{name:/Selamat datang,/})).toBeVisible();
}
test('teacher can review uncertain OCR, grade, finalize, and export',async({page})=>{
    const errors=[];page.on('pageerror',error=>errors.push(error.message));page.on('dialog',dialog=>dialog.accept());
    await login(page);await page.goto('/assessments/1/workspace');
    await page.getByRole('combobox',{name:'Pilih siswa',exact:true}).selectOption('1');
    await page.getByRole('button',{name:'Buka jawaban siswa'}).click();
    await expect(page.getByRole('heading',{name:'01 · Foto jawaban'})).toBeVisible();
    const sample=await page.evaluate(()=>{
        const canvas=document.createElement('canvas');canvas.width=700;canvas.height=800;
        const context=canvas.getContext('2d');context.fillStyle='#fffef7';context.fillRect(0,0,700,800);
        context.strokeStyle='#d9e3ec';for(let y=120;y<800;y+=45){context.beginPath();context.moveTo(35,y);context.lineTo(670,y);context.stroke();}
        context.fillStyle='#314857';context.font='20px sans-serif';context.fillText('LEMBAR JAWABAN SINTETIS UNTUK PENGUJIAN',40,65);
        context.font='30px cursive';context.fillText('1. A',60,170);context.fillText('2. Gagasan ini menjelaskan',60,290);context.fillText('   pokok bacaan.',60,340);
        return canvas.toDataURL('image/png').split(',')[1];
    });
    const image=Buffer.from(sample,'base64');
    await page.getByLabel('Pilih foto / PDF',{exact:true}).setInputFiles({name:'synthetic-answer.png',mimeType:'image/png',buffer:image});
    const encryptedRequest=page.waitForRequest(request=>request.url().endsWith('/read'));
    await page.getByRole('button',{name:'Baca halaman yang belum diproses'}).click();
    const transport=await encryptedRequest;expect(transport.headers()['x-photo-encrypted']).toBe('v1');expect(transport.postDataBuffer().includes(image)).toBe(false);
    await expect(page.getByText('Periksa kata terakhir pada foto.',{exact:true})).toBeVisible();
    await expect(page.getByRole('button',{name:'Nilai jawaban terkonfirmasi'})).toBeDisabled();
    const confirmations=page.getByLabel('Saya telah memeriksa jawaban ini, termasuk jika kosong.',{exact:true});
    await confirmations.nth(0).check();await confirmations.nth(1).check();
    await page.getByRole('button',{name:'Simpan hasil tinjauan',exact:true}).click();
    await expect(page.getByRole('button',{name:'Nilai jawaban terkonfirmasi'})).toBeEnabled();
    await page.getByRole('button',{name:'Nilai jawaban terkonfirmasi'}).click();
    await expect(page.getByText('Tambahkan bukti dari bacaan untuk memperkuat alasan.',{exact:true})).toBeVisible();
    await page.screenshot({path:'.local/qa-desktop.png',fullPage:true});
    await page.getByRole('button',{name:'Sahkan nilai akhir',exact:true}).click();
    await expect(page.locator('.stat')).toHaveText('87.50');
    await expect(page.locator('.photo-thumb')).toHaveCount(0);
    await page.goto('/assessments/1/report');await expect(page.getByRole('cell',{name:'87,50',exact:true})).toBeVisible();
    const downloadPromise=page.waitForEvent('download');await page.getByRole('link',{name:'Unduh Excel',exact:true}).click();
    const download=await downloadPromise;expect(download.suggestedFilename()).toBe('rekap-tes-1.xlsx');
    expect(errors).toEqual([]);
});
test('superadmin manages school sections and cannot open teacher assessment',async({page})=>{
    const errors=[];page.on('pageerror',error=>errors.push(error.message));
    await login(page,'admin@testchecker.invalid');
    for(const section of ['teachers','years','classrooms','students']){
        await page.goto('/admin?section='+section);await expect(page.getByRole('heading',{name:'Data yang rapi, kerja lebih mudah.'})).toBeVisible();
    }
    await page.goto('/admin?section=teachers');
    await page.getByLabel('Nama',{exact:true}).first().fill('Guru Baru');
    await page.getByLabel('Email',{exact:true}).first().fill('newteacher@testchecker.invalid');
    await page.getByLabel('Kata sandi awal',{exact:true}).fill(secret);
    await page.getByLabel('Ulangi kata sandi',{exact:true}).fill(secret);
    await page.getByRole('button',{name:'Buat akun guru',exact:true}).click();
    await expect(page.getByRole('status')).toContainText('Akun guru berhasil disimpan.');
    const response=await page.goto('/assessments/1');expect(response.status()).toBe(403);expect(errors).toEqual([]);
});
test('mobile teacher workspace fits viewport and preserves text after refresh',async({page})=>{
    await page.setViewportSize({width:390,height:844});await login(page);await page.goto('/assessments/1/workspace');
    await page.getByRole('combobox',{name:'Pilih siswa',exact:true}).selectOption('2');await page.getByRole('button',{name:'Buka jawaban siswa'}).click();
    const answer=page.getByLabel('Transkripsi / deskripsi visual terkonfirmasi',{exact:true}).nth(1);
    await answer.fill('Jawaban manual yang sudah diperiksa.');
    await page.getByLabel('Saya telah memeriksa jawaban ini, termasuk jika kosong.',{exact:true}).nth(1).check();
    await page.getByRole('button',{name:'Simpan hasil tinjauan',exact:true}).click();
    await expect(page.getByRole('status')).toContainText('Tinjauan disimpan.');
    await page.reload();await page.getByRole('button',{name:'Buka jawaban siswa'}).isEnabled();
    await page.getByRole('combobox',{name:'Pilih siswa',exact:true}).selectOption('2');await page.getByRole('button',{name:'Buka jawaban siswa'}).click();
    await expect(page.getByLabel('Transkripsi / deskripsi visual terkonfirmasi',{exact:true}).nth(1)).toHaveValue('Jawaban manual yang sudah diperiksa.');
    expect(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth)).toBe(true);
    await page.screenshot({path:'.local/qa-mobile.png',fullPage:true});
});

test('teacher can generate and approve an editable question rubric draft',async({page})=>{
    const errors=[];page.on('pageerror',error=>errors.push(error.message));page.on('dialog',dialog=>dialog.accept());
    await login(page);await page.goto('/assessments');await page.getByText('Buat tes baru',{exact:true}).click();
    await page.getByLabel('Judul tes',{exact:true}).fill('Tes Draf Browser');
    await page.getByLabel('Mata pelajaran',{exact:true}).fill('Bahasa Indonesia');
    await page.getByRole('button',{name:'Buat & siapkan soal'}).click();
    await page.getByText(/Bantu susun soal & rubrik dengan Gemini/).click();
    await page.getByLabel('Materi, kunci, rubrik, atau instruksi',{exact:true}).fill('Buat rubrik untuk soal: Jelaskan gagasan utama bacaan.');
    await page.getByRole('button',{name:'Buat draf untuk ditinjau',exact:true}).click();
    await expect(page.getByLabel('Pertanyaan / deskripsi visual',{exact:true})).toHaveValue('Jelaskan gagasan utama bacaan.');
    await page.getByLabel('Pertanyaan / deskripsi visual',{exact:true}).fill('Jelaskan gagasan utama dengan kalimat sendiri.');
    await page.getByRole('button',{name:'Setujui soal & rubrik'}).click();
    await expect(page.getByRole('heading',{name:'Tes Draf Browser',exact:true})).toBeVisible();
    await expect(page.getByText('Soal dan rubrik disetujui. Tes siap dikoreksi.',{exact:true})).toBeVisible();
    expect(errors).toEqual([]);
});

test('PDF pages can be rotated cropped and replaced without persistent browser storage',async({page})=>{
    page.on('dialog',dialog=>dialog.accept());await login(page);await page.goto('/assessments/1/workspace');
    await page.getByRole('combobox',{name:'Pilih siswa',exact:true}).selectOption('2');
    await page.getByRole('button',{name:'Buka jawaban siswa'}).click();
    const content='BT /F1 24 Tf 60 700 Td (1. A) Tj 0 -50 Td (2. Gagasan pokok bacaan.) Tj ET';
    const objects=[
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 600 800] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
        '<< /Length '+content.length+' >>\nstream\n'+content+'\nendstream',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];
    let pdf='%PDF-1.4\n';const offsets=[0];
    objects.forEach((object,index)=>{offsets.push(Buffer.byteLength(pdf));pdf+=(index+1)+' 0 obj\n'+object+'\nendobj\n';});
    const xref=Buffer.byteLength(pdf);pdf+='xref\n0 6\n0000000000 65535 f \n';
    for(const offset of offsets.slice(1))pdf+=String(offset).padStart(10,'0')+' 00000 n \n';
    pdf+='trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n'+xref+'\n%%EOF\n';
    await page.getByLabel('Pilih foto / PDF',{exact:true}).setInputFiles({name:'synthetic-answers.pdf',mimeType:'application/pdf',buffer:Buffer.from(pdf)});
    await expect(page.locator('.photo-thumb')).toHaveCount(1);
    const preview=page.locator('.photo-preview');await expect.poll(()=>preview.evaluate(image=>image.naturalHeight)).toBeGreaterThan(0);
    const initial=await preview.evaluate(image=>({width:image.naturalWidth,height:image.naturalHeight}));
    await page.getByRole('button',{name:'Putar 90',exact:false}).click();
    await expect.poll(()=>preview.evaluate(image=>image.naturalWidth)).toBe(initial.height);
    await page.getByLabel('Kiri',{exact:true}).fill('10');await page.getByLabel('Atas',{exact:true}).fill('10');
    await page.getByLabel('Lebar',{exact:true}).fill('80');await page.getByLabel('Tinggi',{exact:true}).fill('80');
    await page.getByRole('button',{name:'Terapkan potongan',exact:true}).click();
    await expect.poll(()=>preview.evaluate(image=>image.naturalWidth)).toBe(Math.round(initial.height*.8));
    await page.getByRole('button',{name:'Baca halaman yang belum diproses'}).click();
    await expect(page.getByText('Sudah dibaca',{exact:true})).toBeVisible();
    const replacement=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jP1kAAAAASUVORK5CYII=','base64');
    await page.getByLabel('Ganti foto halaman',{exact:true}).setInputFiles({name:'replacement.png',mimeType:'image/png',buffer:replacement});
    await expect(page.getByRole('button',{name:'Baca halaman yang belum diproses'})).toBeEnabled();
    await page.getByRole('button',{name:'Baca halaman yang belum diproses'}).click();
    await expect(page.getByText('Sudah dibaca',{exact:true})).toBeVisible();
    expect(await page.evaluate(()=>({local:localStorage.length,session:sessionStorage.length}))).toEqual({local:0,session:0});
    expect(await page.evaluate(async()=>await caches.keys())).toEqual([]);
});
