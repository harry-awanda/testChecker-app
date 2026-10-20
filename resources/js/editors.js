import {api,readDocument} from './api';
export function aiSettings(initialModel) {
    return {
        model: initialModel || '', models:[], message:'', busy:false,
        async run(action) { this.busy=true; this.message='Menghubungi Gemini…'; try { await action(); } catch(e){this.message=e.message;} finally{this.busy=false;} },
        async loadModels(){await this.run(async()=>{this.models=(await api('/settings/ai/models')).models;this.message='Pilih model, simpan pengaturan, lalu uji koneksi.';});},
        async testConnection(){await this.run(async()=>{this.message=(await api('/settings/ai/test','POST',{})).message;});},
        async testImage(input){await this.run(async()=>{const document=await readDocument(input.files[0]);if(document)this.message=(await api('/settings/ai/test-image','POST',{document})).message;});input.value='';},
    };
}
export function questionEditor(initialQuestions,assessmentId) {
    return {
        questions:initialQuestions, material:'', document:null, message:'', busy:false,
        add(){this.questions.push({number:this.questions.length+1,type:'essay',prompt:'',weight:10,options:[],answer_key:[],reference_answer:'',criteria:[{label:'Ketepatan jawaban',description:'',max_score:10}],scoring_mode:'all_or_nothing'});},
        remove(index){if(confirm('Hapus soal dari draf ini?'))this.questions.splice(index,1);},
        changeType(q){
            q.answer_key=[];q.criteria=[];q.options=[];
            if(['multiple_choice','complex_choice'].includes(q.type))q.options=[{key:'A',text:''},{key:'B',text:''}];
            else if(q.type==='true_false')q.answer_key=['true'];
            else q.criteria=[{label:'Ketepatan jawaban',description:'',max_score:q.weight}];
        },
        async readMaterial(input){try{this.document=await readDocument(input.files[0]);this.message=this.document?'Dokumen siap diproses.':'';}catch(e){this.message=e.message;input.value='';}},
        async generate(){
            if(this.questions.length&&!confirm('Ganti daftar soal pada layar dengan draf AI? Perubahan yang belum disimpan akan hilang.'))return;
            this.busy=true;this.message='Menyusun draf soal dan rubrik…';
            try{const result=await api('/assessments/'+assessmentId+'/draft','POST',{text:this.material,document:this.document,current_questions:this.questions});this.questions=result.questions;this.message='Draf siap. Tinjau semua soal, opsi, kunci, dan kriteria sebelum menyetujui.';this.document=null;}
            catch(e){this.message=e.message;}finally{this.busy=false;}
        },
    };
}
