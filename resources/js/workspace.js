import {api,readDocument} from './api';
import pdfWorker from 'pdfjs-dist/build/pdf.worker.min.mjs?url';

async function imageFrom(url) {
    const image=new Image();
    await new Promise((resolve,reject)=>{image.onload=resolve;image.onerror=()=>reject(new Error('Gambar tidak dapat dibaca. Gunakan JPG, PNG, atau WebP.'));image.src=url;});
    if(image.naturalWidth>12000||image.naturalHeight>12000||image.naturalWidth*image.naturalHeight>40000000)throw new Error('Resolusi gambar terlalu besar. Perkecil menjadi maksimal 40 megapiksel.');
    return image;
}
function uuid() {
    if(crypto.randomUUID)return crypto.randomUUID();
    return '10000000-1000-4000-8000-100000000000'.replace(/[018]/g,c=>(Number(c)^crypto.getRandomValues(new Uint8Array(1))[0]&15>>Number(c)/4).toString(16));
}
function canvasUrl(canvas) {
    const result=canvas.toDataURL('image/jpeg',.9);canvas.width=0;canvas.height=0;
    if(result.split(',')[1].length>6990510)throw new Error('Gambar hasil olahan melebihi 5 MB. Perkecil gambar.');
    return result;
}
export function workspace(assessmentId,questions,roster=[]) {
    return {
        studentId:'',activeStudentId:'',activeStudentLabel:'',revision:null,submissionId:null,answers:[],grades:[],history:[],photos:[],selectedPhoto:0,
        crop:{left:0,top:0,width:100,height:100},busy:false,message:'',dirty:false,
        init(){
            this.unloadHandler=(event)=>{if(this.dirty||this.photos.length){event.preventDefault();event.returnValue='';}};
            window.addEventListener('beforeunload',this.unloadHandler);
            this.pageHideHandler=()=>{this.photos=[];};
            window.addEventListener('pagehide',this.pageHideHandler);
        },
        destroy(){window.removeEventListener('beforeunload',this.unloadHandler);window.removeEventListener('pagehide',this.pageHideHandler);this.photos=[];},
        question(id){return questions.find(q=>q.id===Number(id))||{number:'?',prompt:'',type:'essay',options:[],criteria:[],weight:0};},
        questionLabel(type){return {essay:'Esai',true_false:'Benar / salah',multiple_choice:'Pilihan ganda',complex_choice:'Pilihan ganda kompleks',diagram:'Diagram',code:'Kode program',calculation:'Langkah hitungan'}[type]||type;},
        statusLabel(status){return {pending_review:'Perlu ditinjau',ready:'Siap dinilai',graded:'Draf nilai',finalized:'Disahkan'}[status]||status;},
        async run(action){this.busy=true;this.message='Memproses…';try{await action();}catch(e){this.message=e.message;}finally{this.busy=false;}},
        apply(data){
            this.revision=data.revision;this.submissionId=data.submission_id;this.answers=data.revision.answers;
            this.grades=data.revision.grades.map(g=>({...g,overrideScore:g.score,overrideReason:'',criteria_scores:g.criteria_scores.map(c=>({...c,overrideScore:c.score}))}));
            this.history=data.history;this.dirty=false;
        },
        async openStudent(){
            if((this.dirty||this.photos.length)&&!confirm('Buka siswa ini? Perubahan teks yang belum disimpan dan foto pada tab akan dilepas.'))return;
            await this.run(async()=>{const data=await api('/assessments/'+assessmentId+'/submissions','POST',{student_id:Number(this.studentId)});this.photos=[];this.selectedPhoto=0;this.apply(data);this.activeStudentId=this.studentId;const active=roster.find(item=>Number(item.student_id)===Number(this.activeStudentId));this.activeStudentLabel=active?active.student_code+' / '+active.student_name:'';this.message='Jawaban siswa dibuka. Unggah foto atau lanjutkan tinjauan tersimpan.';});
        },
        async reload(){
            if(this.dirty&&!confirm('Buang perubahan teks yang belum disimpan dan muat data tersimpan?'))return;
            await this.run(async()=>{this.apply(await api('/revisions/'+this.revision.id));this.message='Progres teks dimuat kembali.';});
        },
        async createRevision(){
            if(!confirm('Buat revisi baru? Semua jawaban perlu ditinjau ulang; nilai lama tetap berlaku sampai revisi disahkan.'))return;
            await this.run(async()=>{this.photos=[];this.apply(await api('/submissions/'+this.submissionId+'/revise','POST',{}));this.message='Revisi baru siap. Periksa kembali seluruh jawaban sebelum dinilai.';});
        },
        async viewHistory(id){
            if(this.dirty&&!confirm('Buang perubahan teks yang belum disimpan?'))return;
            await this.run(async()=>{this.photos=[];this.apply(await api('/revisions/'+id));this.message='Menampilkan revisi '+this.revision.version+'.';});
        },
        async addFiles(input){
            const files=Array.from(input.files||[]);input.value='';
            await this.run(async()=>{
                for(const file of files) {
                    if(file.size>5*1024*1024)throw new Error('Maksimal 5 MB per berkas.');
                    if(file.type==='application/pdf') {
                        const pdfjs=await import('pdfjs-dist/build/pdf.mjs');pdfjs.GlobalWorkerOptions.workerSrc=pdfWorker;
                        const task=pdfjs.getDocument({data:new Uint8Array(await file.arrayBuffer()),isEvalSupported:false});
                        let pdf;
                        try {
                            pdf=await task.promise;
                            if(this.photos.length+pdf.numPages>10)throw new Error('Maksimal 10 halaman per siswa.');
                            for(let pageNumber=1;pageNumber<=pdf.numPages;pageNumber++){
                                const page=await pdf.getPage(pageNumber);const base=page.getViewport({scale:1});
                                const viewport=page.getViewport({scale:Math.min(2,1800/Math.max(base.width,base.height))});
                                const canvas=document.createElement('canvas');canvas.width=viewport.width;canvas.height=viewport.height;
                                await page.render({canvasContext:canvas.getContext('2d'),viewport}).promise;
                                this.pushPhoto(canvasUrl(canvas));page.cleanup();
                            }
                        } finally {await task.destroy();}
                    } else {
                        if(this.photos.length>=10)throw new Error('Maksimal 10 halaman per siswa.');
                        const doc=await readDocument(file);const url='data:'+doc.mime_type+';base64,'+doc.data;
                        await imageFrom(url);this.pushPhoto(url);
                    }
                }
                this.message='Foto siap. Sesuaikan area sebelum pembacaan. Jika mengunggah ulang setelah refresh, pertahankan urutan halaman semula.';
            });
        },
        pushPhoto(url){
            const index=this.photos.length;const id=this.revision.page_ids[index]||uuid();
            this.photos.push({id,url,processed:this.revision.page_ids.includes(id)});
            this.selectedPhoto=index;this.crop={left:0,top:0,width:100,height:100};
        },
        async replacePhoto(input,index){
            const file=input.files?.[0];input.value='';if(!file)return;
            if(!confirm('Ganti foto halaman ini? Setelah pembacaan baru, seluruh konfirmasi dan draf skor perlu ditinjau kembali.'))return;
            await this.run(async()=>{
                if(file.type==='application/pdf')throw new Error('Pengganti satu halaman harus berupa JPG, PNG, atau WebP.');
                const data=await readDocument(file);const url='data:'+data.mime_type+';base64,'+data.data;
                await imageFrom(url);this.photos[index].url=url;this.photos[index].processed=false;this.selectedPhoto=index;
                this.crop={left:0,top:0,width:100,height:100};this.message='Foto pengganti siap. Baca kembali halaman sebelum melanjutkan penilaian.';
            });
        },
        move(index,direction){const next=index+direction;if(next<0||next>=this.photos.length)return;[this.photos[index],this.photos[next]]=[this.photos[next],this.photos[index]];this.selectedPhoto=next;},
        removePhoto(index){this.photos.splice(index,1);this.selectedPhoto=Math.max(0,Math.min(this.selectedPhoto,this.photos.length-1));},
        async rotate(index){
            await this.run(async()=>{const image=await imageFrom(this.photos[index].url);const canvas=document.createElement('canvas');canvas.width=image.naturalHeight;canvas.height=image.naturalWidth;const context=canvas.getContext('2d');context.translate(canvas.width/2,canvas.height/2);context.rotate(Math.PI/2);context.drawImage(image,-image.naturalWidth/2,-image.naturalHeight/2);this.photos[index].url=canvasUrl(canvas);this.message='Gambar diputar.';});
        },
        async cropPhoto(){
            await this.run(async()=>{
                const c=this.crop;
                if(![c.left,c.top,c.width,c.height].every(Number.isFinite)||c.left<0||c.top<0||c.width<=0||c.height<=0||c.left+c.width>100||c.top+c.height>100)throw new Error('Area potong harus berada di dalam gambar.');
                const image=await imageFrom(this.photos[this.selectedPhoto].url);const canvas=document.createElement('canvas');
                canvas.width=Math.max(1,Math.round(image.naturalWidth*c.width/100));canvas.height=Math.max(1,Math.round(image.naturalHeight*c.height/100));
                canvas.getContext('2d').drawImage(image,image.naturalWidth*c.left/100,image.naturalHeight*c.top/100,canvas.width,canvas.height,0,0,canvas.width,canvas.height);
                this.photos[this.selectedPhoto].url=canvasUrl(canvas);this.crop={left:0,top:0,width:100,height:100};this.message='Potongan diterapkan; bagian di luar area telah dilepas dari foto di memori.';
            });
        },
        async readAll(skipConfirmation=false){
            if(!skipConfirmation&&(this.dirty||this.answers.some(a=>a.teacher_confirmed))&&!confirm('Pembacaan ulang membatalkan tinjauan dan draf skor. Lanjutkan?'))return;
            await this.run(async()=>{
                for(let index=0;index<this.photos.length;index++){
                    const photo=this.photos[index];if(photo.processed)continue;
                    this.message='Membaca halaman '+(index+1)+' dari '+this.photos.length+'…';
                    const [prefix,data]=photo.url.split(',');const mime_type=prefix.match(/^data:(.*);base64$/)[1];
                    const result=await api('/revisions/'+this.revision.id+'/read','POST',{edit_version:this.revision.edit_version,page_id:photo.id,document:{mime_type,data}});
                    this.apply(result);photo.processed=true;
                    if(result.page_unclear){this.message='Halaman '+(index+1)+' tidak terbaca jelas. '+result.reading_note+' Periksa atau ambil ulang foto.';return;}
                }
                this.message='Pembacaan selesai. Periksa seluruh transkripsi, pilihan siswa, serta bagian yang ditandai.';
            });
        },
        async reread(index){
            if(!confirm('Baca ulang halaman ini? Seluruh konfirmasi jawaban dan draf skor akan dibatalkan.'))return;
            this.photos[index].processed=false;await this.readAll(true);
        },
        releasePhotos(){if(confirm('Lepaskan semua foto dari memori tab? Untuk melihatnya lagi, unggah ulang dalam urutan semula.')){this.photos=[];this.selectedPhoto=0;this.message='Foto dilepas dari memori; transkripsi tersimpan tetap tersedia.';}},
        showSource(pageId){const index=this.photos.findIndex(p=>p.id===pageId);if(index<0){this.message='Foto sumber tidak berada di memori tab. Unggah ulang foto dalam urutan semula.';return;}this.selectedPhoto=index;},
        async saveReview(){
            await this.run(async()=>{
                this.apply(await api('/revisions/'+this.revision.id+'/review','PUT',{edit_version:this.revision.edit_version,answers:this.answers.map(a=>({question_id:a.question_id,transcription:a.transcription,selected_options:a.selected_options,teacher_confirmed:a.teacher_confirmed}))}));
                this.message=this.revision.status==='ready'?'Semua jawaban terkonfirmasi. Siap dinilai.':'Tinjauan disimpan. Masih ada jawaban yang perlu dikonfirmasi.';
            });
        },
        async gradeAll(){
            await this.run(async()=>{
                for(const question of questions){
                    if(this.grades.some(g=>g.question_id===question.id))continue;
                    this.message='Menilai soal '+question.number+'…';
                    this.apply(await api('/revisions/'+this.revision.id+'/grade/'+question.id,'POST',{edit_version:this.revision.edit_version}));
                }
                this.message='Draf nilai siap. Tinjau skor serta alasannya sebelum mengesahkan.';
            });
        },
        async overrideGrade(grade){
            if(!grade.overrideReason?.trim()){this.message='Alasan perubahan skor wajib diisi.';return;}
            await this.run(async()=>{
                const data={edit_version:this.revision.edit_version,score:grade.overrideScore,criteria_scores:grade.criteria_scores.map((c,index)=>({index,score:c.overrideScore})),reason:grade.overrideReason};
                this.apply(await api('/revisions/'+this.revision.id+'/grades/'+grade.id,'PUT',data));this.message='Perubahan skor dan alasannya disimpan.';
            });
        },
        async finalize(){
            if(!confirm('Sahkan nilai setelah meninjau seluruh skor dan pembahasan?'))return;
            await this.run(async()=>{this.apply(await api('/revisions/'+this.revision.id+'/finalize','POST',{edit_version:this.revision.edit_version}));this.photos=[];this.message='Nilai disahkan: '+Number(this.revision.final_score).toFixed(2)+'. Foto dilepas dari memori tab.';});
        },
    };
}
