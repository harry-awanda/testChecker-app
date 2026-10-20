<?php
namespace App\Services;
// Minimal uncompressed ZIP writer for small XLSX exports; no temporary files.
class MemoryZip {
    public function make(array $files):string {
        $body='';$directory='';
        foreach($files as $name=>$content) {
            $offset=strlen($body);$length=strlen($content);$nameLength=strlen($name);$crc=crc32($content);
            $body.=pack('VvvvvvVVVvv',0x04034b50,20,0,0,0,33,$crc,$length,$length,$nameLength,0).$name.$content;
            $directory.=pack('VvvvvvvVVVvvvvvVV',0x02014b50,20,20,0,0,0,33,$crc,$length,$length,$nameLength,0,0,0,0,0,$offset).$name;
        }
        $count=count($files);
        return $body.$directory.pack('VvvvvVVv',0x06054b50,0,0,$count,$count,strlen($directory),strlen($body),0);
    }
}
