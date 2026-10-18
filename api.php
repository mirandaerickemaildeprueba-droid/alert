<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function answer(int $code,array $data): never {http_response_code($code);echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST')answer(405,['ok'=>false,'error'=>'Solo POST']);
$raw=file_get_contents('php://input');if(strlen($raw)>2048)answer(413,['ok'=>false,'error'=>'Solicitud demasiado grande']);
$r=json_decode($raw,true);if(!is_array($r))answer(400,['ok'=>false,'error'=>'JSON inválido']);
// Cambia esta clave antes de publicar; usa una clave larga y no pública.
const CLAVE_GRUPO='CAMBIAR-POR-UNA-CLAVE-LARGA-ALEATORIA';
if(!is_string($r['codigo']??null)||!hash_equals(CLAVE_GRUPO,$r['codigo']))answer(403,['ok'=>false,'error'=>'Código incorrecto']);
$action=$r['accion']??'';
if($action==='verificar')answer(200,['ok'=>true]);
$path=__DIR__.'/private/alertas.txt';
if(!is_dir(dirname($path)))answer(500,['ok'=>false,'error'=>'Carpeta private no existe']);
$fh=@fopen($path,'c+');if($fh===false)answer(500,['ok'=>false,'error'=>'No se puede abrir el registro']);
if(!flock($fh,LOCK_EX)){fclose($fh);answer(500,['ok'=>false,'error'=>'Registro ocupado']);}
rewind($fh);$items=[];while(($line=fgets($fh))!==false){$o=json_decode($line,true);if(is_array($o)&&isset($o['id']))$items[]=$o;}
if($action==='registrar'){
 $tipo=$r['tipo']??'';if(!in_array($tipo,['prueba','movimiento brusco'],true)){flock($fh,LOCK_UN);fclose($fh);answer(400,['ok'=>false,'error'=>'Tipo inválido']);}
 $intensidad=$r['intensidad']??null;if(!is_numeric($intensidad)||$intensidad<0||$intensidad>500){flock($fh,LOCK_UN);fclose($fh);answer(400,['ok'=>false,'error'=>'Intensidad inválida']);}
 $lat=$r['lat']??null;$lon=$r['lon']??null;if(($lat!==null&&(!is_numeric($lat)||$lat< -90||$lat>90))||($lon!==null&&(!is_numeric($lon)||$lon< -180||$lon>180))){flock($fh,LOCK_UN);fclose($fh);answer(400,['ok'=>false,'error'=>'Coordenadas inválidas']);}
 $id=($items?max(array_column($items,'id')):0)+1;
 $item=['id'=>$id,'fecha'=>(new DateTimeImmutable('now',new DateTimeZone('America/Panama')))->format('Y-m-d H:i:s'),'origen'=>'Celular','tipo'=>$tipo,'intensidad'=>round((float)$intensidad,2),'lat'=>$lat===null?null:round((float)$lat,6),'lon'=>$lon===null?null:round((float)$lon,6)];
 fseek($fh,0,SEEK_END);fwrite($fh,json_encode($item,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");fflush($fh);flock($fh,LOCK_UN);fclose($fh);answer(200,['ok'=>true,'alerta'=>$item]);
}
flock($fh,LOCK_UN);fclose($fh);
if($action==='leer'){$desde=max(0,(int)($r['desde']??0));$out=array_values(array_filter($items,fn($i)=>$i['id']>$desde));answer(200,['ok'=>true,'alertas'=>array_slice($out,-100)]);}
answer(400,['ok'=>false,'error'=>'Acción inválida']);
