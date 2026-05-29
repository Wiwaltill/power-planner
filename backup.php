<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_admin();

if(isset($_GET['download_backup'])){
    $tmp = sys_get_temp_dir().'/powerplanner-backup-'.date('Ymd-His').'.zip';
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::CREATE|ZipArchive::OVERWRITE);

    $tables=['users','projects','devices','categories','brands','connectors','settings','circuits','plan_items'];
    foreach($tables as $table){
        try{
            $stmt=$pdo->query("SELECT * FROM `$table`");
            $zip->addFromString('database/'.$table.'.json', json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT));
        }catch(Exception $e){}
    }

    if(is_dir(__DIR__.'/../uploads')){
        $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../uploads'));
        foreach($it as $file){
            if(!$file->isDir()){
                $zip->addFile($file->getPathname(),'uploads/'.basename($file->getPathname()));
            }
        }
    }
    $zip->close();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="powerplanner-backup-'.date('Ymd-His').'.zip"');
    readfile($tmp);
    unlink($tmp);
    exit;
}
