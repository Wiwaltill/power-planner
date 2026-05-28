<?php
require_once __DIR__ . '/../inc/auth.php'; require_once __DIR__ . '/../inc/helpers.php';
$user=require_login(); $pdo=db(); $projectId=(int)($_GET['project_id'] ?? 0); if(!user_project($projectId,(int)$user['id'])) json_response(['error'=>'Projekt nicht gefunden.'],404);
$method=$_SERVER['REQUEST_METHOD'];
if($method==='GET'){ $stmt=$pdo->prepare('SELECT i.*, c.name circuit_name FROM plan_items i JOIN circuits c ON c.id=i.circuit_id WHERE i.project_id=? ORDER BY i.id'); $stmt->execute([$projectId]); json_response($stmt->fetchAll()); }
$data=request_json();
if($method==='POST'){
  $deviceId=(int)($data['device_id'] ?? 0); $device=null;
  if($deviceId){ $stmt=$pdo->prepare('SELECT * FROM devices WHERE id=? AND user_id=?'); $stmt->execute([$deviceId,$user['id']]); $device=$stmt->fetch(); }
  $name=$device['name'] ?? trim($data['name'] ?? ''); if(!$name) json_response(['error'=>'Gerät fehlt.'],422);
  $circuitId=(int)($data['circuit_id'] ?? 0); $stmt=$pdo->prepare('SELECT id FROM circuits WHERE id=? AND project_id=?'); $stmt->execute([$circuitId,$projectId]); if(!$stmt->fetch()) json_response(['error'=>'Stromkreis fehlt.'],422);
  $stmt=$pdo->prepare('INSERT INTO plan_items (project_id,circuit_id,device_id,name,brand,category,quantity,phase,power_w,voltage_v,remarks) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
  $stmt->execute([$projectId,$circuitId,$deviceId ?: null,$name,$device['brand'] ?? ($data['brand'] ?? ''),$device['category'] ?? ($data['category'] ?? ''),(int)($data['quantity'] ?? 1),in_array($data['phase'] ?? 'L1',['L1','L2','L3']) ? $data['phase'] : 'L1',(int)($device['power_w'] ?? ($data['power_w'] ?? 0)),(int)($data['voltage_v'] ?? ($device['voltage_v'] ?? 230)),$data['remarks'] ?? '']);
  json_response(['id'=>(int)$pdo->lastInsertId()]);
}
if($method==='PATCH'){
  $id=(int)($_GET['id'] ?? 0); $fields=[]; $values=[];
  foreach(['circuit_id','quantity','phase','voltage_v','remarks'] as $field){ if(array_key_exists($field,$data)){ $fields[]="$field=?"; $values[]=$data[$field]; } }
  if(!$fields) json_response(['ok'=>true]); $values[]=$id; $values[]=$projectId;
  $pdo->prepare('UPDATE plan_items SET '.implode(',',$fields).' WHERE id=? AND project_id=?')->execute($values); json_response(['ok'=>true]);
}
if($method==='DELETE'){
  if(isset($_GET['all'])){ $pdo->prepare('DELETE FROM plan_items WHERE project_id=?')->execute([$projectId]); json_response(['ok'=>true]); }
  $id=(int)($_GET['id'] ?? 0); $pdo->prepare('DELETE FROM plan_items WHERE id=? AND project_id=?')->execute([$id,$projectId]); json_response(['ok'=>true]);
}
json_response(['error'=>'Methode nicht erlaubt.'],405);
