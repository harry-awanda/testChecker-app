<?php
namespace App\Http\Controllers;
use App\Models\Assessment;
use App\Services\{AssessmentAccess,ReportService};
use Illuminate\Http\Request;
class ReportController extends Controller {
    public function show(Request $request,Assessment $assessment,AssessmentAccess $access,ReportService $service) {
        $access->authorize($request->user(),$assessment);$rows=$service->rows($assessment);
        $scores=array_values(array_filter(array_column($rows,'score'),fn($score)=>$score!==null));
        return view('teacher.report',['assessment'=>$assessment,'rows'=>$rows,'average'=>$scores?round(array_sum($scores)/count($scores),2):null,'finalizedCount'=>count($scores)]);
    }
    public function export(Request $request,Assessment $assessment,string $format,AssessmentAccess $access,ReportService $service) {
        $access->authorize($request->user(),$assessment);abort_unless(in_array($format,['csv','xlsx'],true),404);
        $rows=$service->rows($assessment);$filename='rekap-tes-'.$assessment->id.'.'.$format;
        if($format==='xlsx')return response($service->xlsx($rows),200,['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','Content-Disposition'=>'attachment; filename="'.$filename.'"','Cache-Control'=>'no-store, private']);
        return response()->streamDownload(function()use($service,$rows) {
            $out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");
            foreach($service->cells($rows) as $row)fputcsv($out,array_map([$service,'safeCsvCell'],$row),',','"','');
            fclose($out);
        },$filename,['Content-Type'=>'text/csv; charset=UTF-8','Cache-Control'=>'no-store, private']);
    }
}
