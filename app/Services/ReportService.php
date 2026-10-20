<?php
namespace App\Services;
use App\Models\Assessment;
use Illuminate\Support\Facades\DB;
class ReportService {
    public function rows(Assessment $assessment):array {
        $roster=$assessment->locked_at
            ? DB::table('assessment_roster')->where('assessment_id',$assessment->id)->orderBy('student_name')->get()
            : $assessment->classroom->enrollments()->whereNull('ended_at')->whereHas('student',fn($q)=>$q->where('is_archived',false))->with('student')->get()->map(fn($e)=>(object)['student_id'=>$e->student_id,'student_name'=>$e->student->name,'student_code'=>$e->student->student_code]);
        $submissions=$assessment->submissions()->with(['effectiveRevision','latestRevision'])->get()->keyBy('student_id');
        return $roster->map(function($entry)use($submissions) {
            $submission=$submissions->get($entry->student_id);$effective=$submission?->effectiveRevision;$latest=$submission?->latestRevision;
            return [
                'student_code'=>$entry->student_code,'student_name'=>$entry->student_name,
                'score'=>$effective?->final_score,'effective_version'=>$effective?->version,
                'status'=>$latest ? config('testchecker.statuses.'.$latest->status) : 'Belum diproses',
                'pending_revision'=>$effective && $latest && $latest->id!==$effective->id,
                'finalized_at'=>$effective?->finalized_at?->format('d-m-Y H:i'),
            ];
        })->values()->all();
    }
    public function cells(array $rows):array {
        return array_merge([['Kode siswa','Nama siswa','Nilai final','Revisi berlaku','Status terbaru','Ada revisi berjalan','Disahkan pada']],array_map(fn($row)=>[
            $row['student_code'],$row['student_name'],$row['score'],$row['effective_version'],$row['status'],$row['pending_revision']?'Ya':'Tidak',$row['finalized_at']??'',
        ],$rows));
    }
    public function safeCsvCell(mixed $value):string {
        $value=(string)($value??'');
        return preg_match('/^(?:[\t\r\n]|[\s]*[=+\-@])/u',$value)?"'".$value:$value;
    }
    public function xlsx(array $rows):string {
        $xmlHeader='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $sheet=$xmlHeader.'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach($this->cells($rows) as $rowIndex=>$cells) {
            $sheet.='<row r="'.($rowIndex+1).'">';
            foreach($cells as $column=>$value) {
                $reference=chr(65+$column).($rowIndex+1);
                if($rowIndex>0 && in_array($column,[2,3],true) && $value!==null) $sheet.='<c r="'.$reference.'"><v>'.(float)$value.'</v></c>';
                else $sheet.='<c r="'.$reference.'" t="inlineStr"><is><t xml:space="preserve">'.htmlspecialchars((string)($value??''),ENT_XML1|ENT_QUOTES,'UTF-8').'</t></is></c>';
            }
            $sheet.='</row>';
        }
        $sheet.='</sheetData></worksheet>';
        return app(MemoryZip::class)->make([
            '[Content_Types].xml'=>$xmlHeader.'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>',
            '_rels/.rels'=>$xmlHeader.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml'=>$xmlHeader.'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Rekap nilai" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels'=>$xmlHeader.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>',
            'xl/worksheets/sheet1.xml'=>$sheet,
        ]);
    }
}
