<?php
session_start();
$r_=str_replace('\\','/',realpath(__DIR__.'/..'));$d_=str_replace('\\','/',realpath($_SERVER['DOCUMENT_ROOT']));define('BASE',rtrim(substr($r_,strlen($d_)),'/').'/');
const DBC=['127.0.0.1','carrental','root',''];
function db(){static $p;if(!$p)$p=new PDO('mysql:host='.DBC[0].';dbname='.DBC[1].';charset=utf8mb4',DBC[2],DBC[3],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);return $p;}
function q($s,$a=[]){$t=db()->prepare($s);$t->execute($a);return $t;}
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES);}
function money($v){return 'RWF '.number_format((float)$v,0);}
function user(){return $_SESSION['u']??null;}
function need(...$r){if(!user()){header('Location: '.BASE.'index.php');exit;}if($r&&!in_array(user()['role'],$r)){http_response_code(403);exit('Access denied');}}
function csrf(){return $_SESSION['t']??($_SESSION['t']=bin2hex(random_bytes(16)));}
function chk(){if($_SERVER['REQUEST_METHOD']==='POST'&&!hash_equals(csrf(),$_POST['t']??''))exit('Invalid session token');}
function flash($m=null,$t='success'){if($m){$_SESSION['f']=[$m,$t];return;}$f=$_SESSION['f']??null;unset($_SESSION['f']);return $f;}
function audit($a,$et,$id,$d=''){q('INSERT INTO audit_logs(user_id,action,entity_type,entity_id,description,ip_address) VALUES(?,?,?,?,?,?)',[user()['id']??null,$a,$et,$id,$d,$_SERVER['REMOTE_ADDR']??null]);}
function code($p){return $p.date('ymd').strtoupper(substr(bin2hex(random_bytes(3)),0,6));}
function notify_user($userId,$type,$subject,$message,$entityType=null,$entityId=null,$customerId=null){
 if(!$userId)return;
 q("INSERT INTO notifications(user_id,customer_id,channel,notification_type,subject,message,related_entity_type,related_entity_id,status,sent_at) VALUES(?,?,'in_app',?,?,?,?,?,'sent',NOW())",[$userId,$customerId,$type,$subject,$message,$entityType,$entityId]);
}
function notify_customer($customerId,$type,$subject,$message,$entityType=null,$entityId=null){
 $customer=q('SELECT user_id FROM customers WHERE customer_id=?',[$customerId])->fetch();
 if($customer&&$customer['user_id'])notify_user($customer['user_id'],$type,$subject,$message,$entityType,$entityId,$customerId);
}
function notify_role($roleName,$type,$subject,$message,$entityType=null,$entityId=null){
 $users=q("SELECT u.user_id FROM users u JOIN roles r USING(role_id) WHERE (r.role_name=? OR (r.role_name='System Administrator' AND ?<>'rental')) AND u.account_status='active'",[$roleName,$entityType??''])->fetchAll(PDO::FETCH_COLUMN);
 foreach($users as $userId)notify_user($userId,$type,$subject,$message,$entityType,$entityId);
}
function notification_link($notification){
 $role=user()['role']??'';
 $type=$notification['related_entity_type']??'';
 $id=(int)($notification['related_entity_id']??0);
 if($role==='Customer'){
  if($type==='agreement'&&$id>0)return BASE.'shared/agreement.php?id='.$id;
  if($type==='customer')return BASE.'customer/my.php?tab=profile';
  $tab=['invoice'=>'inv','payment'=>'pay','deposit'=>'pay','reservation'=>'res','rental'=>'res'][$type]??null;
  return $tab?BASE.'customer/my.php?tab='.$tab:null;
 }
 if($type==='customer'&&in_array($role,['System Administrator','Business Manager','Rental Officer','Accountant/Cashier'],true))return BASE.'officer/customers.php';
 if($type==='agreement'&&in_array($role,['System Administrator','Business Manager','Rental Officer'],true))return BASE.'officer/agreements.php';
 if($type==='reservation'&&in_array($role,['System Administrator','Business Manager','Rental Officer'],true))return BASE.'officer/reservations.php';
 if($type==='rental'&&in_array($role,['System Administrator','Business Manager','Rental Officer'],true))return BASE.'officer/rentals.php';
 if($type==='rental'&&$role==='Accountant/Cashier')return BASE.'cashier/finance.php?tab=inv';
 if(in_array($type,['payment','deposit','invoice'],true)&&in_array($role,['System Administrator','Business Manager','Accountant/Cashier'],true)){
  $tab=['payment'=>'pay','deposit'=>'dep','invoice'=>'inv'][$type];
  return BASE.'cashier/finance.php?tab='.$tab.($type==='invoice'&&$id>0?'&view='.$id:'');
 }
 return null;
}
function create_reservation_invoice($reservationId,$issuerId=null){
 $reservation=q('SELECT r.*,v.make,v.model FROM reservations r JOIN vehicles v USING(vehicle_id) WHERE r.reservation_id=? FOR UPDATE',[$reservationId])->fetch();
 if(!$reservation)throw new RuntimeException('Rental application not found.');
 if(!in_array($reservation['status'],['pending','confirmed'],true))throw new RuntimeException('An invoice can only be created for an active rental application.');
 $existing=q('SELECT invoice_id,status FROM invoices WHERE reservation_id=? ORDER BY invoice_id DESC LIMIT 1 FOR UPDATE',[$reservationId])->fetch();
 if($existing){
  if($existing['status']==='draft'){
   q("UPDATE invoices SET status='issued',issued_by=?,issue_date=CURDATE(),due_date=CURDATE(),tax_amount=0,total_amount=GREATEST(0,subtotal-discount_amount),notes='Rental invoice created after agreement review' WHERE invoice_id=? AND status='draft'",[$issuerId,$existing['invoice_id']]);
   if((int)q('SELECT ROW_COUNT()')->fetchColumn()!==1)throw new RuntimeException('The invoice changed before it could be issued. Reload and try again.');
    q('UPDATE reservations SET tax_amount=0 WHERE reservation_id=?',[$reservationId]);
    notify_customer($reservation['customer_id'],'invoice_issued','Invoice ready','Your rental invoice is ready to review and pay.','invoice',$existing['invoice_id']);
    return ['invoice_id'=>(int)$existing['invoice_id'],'created'=>false,'issued'=>true];
  }
  if(!in_array($existing['status'],['issued','partially_paid','paid','overdue'],true))throw new RuntimeException('This application invoice has been voided or closed. Contact the rental office for assistance.');
  return ['invoice_id'=>(int)$existing['invoice_id'],'created'=>false,'issued'=>true];
 }
 $subtotal=(float)$reservation['estimated_amount'];
 $discount=(float)$reservation['discount_amount'];
 $tax=0;
 $total=max(0,$subtotal-$discount+$tax);
 q('UPDATE reservations SET tax_amount=0 WHERE reservation_id=?',[$reservationId]);
 q("INSERT INTO invoices(invoice_number,customer_id,reservation_id,issued_by,issue_date,due_date,subtotal,discount_amount,tax_amount,total_amount,status,notes) VALUES(?,?,?,?,CURDATE(),CURDATE(),?,?,?,?, 'issued','Rental invoice created after agreement review')",[code('INV'),$reservation['customer_id'],$reservationId,$issuerId,$subtotal,$discount,$tax,$total]);
 $invoiceId=(int)db()->lastInsertId();
 q("INSERT INTO invoice_items(invoice_id,item_type,description,quantity,unit_price,line_total) VALUES(?,'rental',?,?,?,?)",[$invoiceId,$reservation['make'].' '.$reservation['model'].' rental',$reservation['estimated_days'],$reservation['daily_rate'],$subtotal]);
 notify_customer($reservation['customer_id'],'invoice_issued','Invoice ready','Your rental invoice is ready to review and pay.','invoice',$invoiceId);
 return ['invoice_id'=>$invoiceId,'created'=>true,'issued'=>true];
}
function ensure_agreement_signature_schema(){
 static $checked=false;
 if($checked)return;
 $columns=['renter_signed_name'=>'VARCHAR(200) NULL','renter_signed_at'=>'DATETIME NULL','renter_signature_image'=>'MEDIUMTEXT NULL','staff_signed_by'=>'BIGINT UNSIGNED NULL','staff_signed_name'=>'VARCHAR(200) NULL','staff_signed_at'=>'DATETIME NULL','staff_signature_image'=>'MEDIUMTEXT NULL'];
 $existing=q("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='rental_agreements'")->fetchAll(PDO::FETCH_COLUMN);
 $add=[];
 foreach($columns as $name=>$definition)if(!in_array($name,$existing,true))$add[]='ADD COLUMN '.$name.' '.$definition;
 if($add)db()->exec('ALTER TABLE rental_agreements '.implode(', ',$add));
 $checked=true;
}
function validate_agreement_signature($data){
 if(!is_string($data)||strlen($data)>1000000||!preg_match('/^data:image\/jpeg;base64,([A-Za-z0-9+\/]+=*)$/D',$data,$matches))throw new RuntimeException('Draw your signature in the signature box before submitting.');
 $bytes=base64_decode($matches[1],true);
 $image=$bytes===false?false:getimagesizefromstring($bytes);
 if($image===false||$image['mime']!=='image/jpeg'||$image[0]<1||$image[0]>1200||$image[1]<1||$image[1]>400)throw new RuntimeException('The signature image is invalid. Clear it and draw your signature again.');
 return ['data'=>$data,'bytes'=>$bytes,'width'=>$image[0],'height'=>$image[1]];
}
function me(){
 $id=user()['id']??null;
 if(!$id)throw new RuntimeException('Your session has expired. Please sign in again.');
 $c=q('SELECT * FROM customers WHERE user_id=?',[$id])->fetch();
 if($c)return $c;
 $u=q('SELECT user_id,first_name,last_name,email,COALESCE(phone,\'\') phone FROM users WHERE user_id=?',[$id])->fetch();
 if(!$u)throw new RuntimeException('Your account could not be found. Please sign in again.');
 $orphan=null;
 if($u['email'])$orphan=q('SELECT customer_id FROM customers WHERE user_id IS NULL AND email=? AND first_name=? AND last_name=? ORDER BY customer_id LIMIT 1',[$u['email'],$u['first_name'],$u['last_name']])->fetchColumn();
 if($orphan)q('UPDATE customers SET user_id=? WHERE customer_id=? AND user_id IS NULL',[$id,$orphan]);
 else q('INSERT IGNORE INTO customers(user_id,customer_code,first_name,last_name,phone,email) VALUES(?,?,?,?,?,?)',[$id,code('CUS'),$u['first_name'],$u['last_name'],$u['phone'],$u['email']]);
 $c=q('SELECT * FROM customers WHERE user_id=?',[$id])->fetch();
 if(!$c)throw new RuntimeException('Your customer profile could not be created. Please contact support.');
 return $c;
}
function user_initials($name){
 $parts=preg_split('/\s+/u',trim((string)$name),-1,PREG_SPLIT_NO_EMPTY);
 if(!$parts)return 'U';
 $first=function_exists('mb_substr')?mb_substr($parts[0],0,1,'UTF-8'):substr($parts[0],0,1);
 $last=count($parts)>1?(function_exists('mb_substr')?mb_substr($parts[count($parts)-1],0,1,'UTF-8'):substr($parts[count($parts)-1],0,1)):'';
 return strtoupper($first.$last);
}
function badge($s){$m=['active'=>'success','available'=>'success','paid'=>'success','completed'=>'success','confirmed'=>'success','signed'=>'success','processed'=>'success','closed'=>'secondary','pending'=>'warning','issued'=>'info','partially_paid'=>'warning','requested'=>'warning','approved'=>'info','scheduled'=>'info','returned'=>'info','rented'=>'primary','reserved'=>'info','overdue'=>'danger','rejected'=>'danger','cancelled'=>'secondary','failed'=>'danger','maintenance'=>'danger','void'=>'secondary'];return '<span class="badge text-bg-'.($m[$s]??'secondary').'">'.e(str_replace('_',' ',$s)).'</span>';}
const STAFF=['System Administrator','Business Manager','Rental Officer','Accountant/Cashier'];
function menu(){$A='System Administrator';$M='Business Manager';$R='Rental Officer';$C='Accountant/Cashier';$U='Customer';$I='Vehicle Inspector/Maintenance Staff';
return [['admin/dashboard.php','Dashboard','speedometer2',[$A]],['manager/dashboard.php','Dashboard','speedometer2',[$M]],['officer/dashboard.php','Dashboard','speedometer2',[$R]],['cashier/dashboard.php','Dashboard','speedometer2',[$C]],['customer/dashboard.php','Dashboard','speedometer2',[$U]],
['customer/cars.php','Rent a car','car-front',[$U]],['customer/my.php?tab=res','My reservations','calendar-check',[$U]],['customer/my.php?tab=inv','My invoices','receipt',[$U]],['customer/my.php?tab=pay','My payments','credit-card',[$U]],['customer/my.php?tab=profile','My profile','person-circle',[$U]],
['officer/reservations.php','Reservations','calendar-check',[$A,$M,$R]],['officer/agreements.php','Agreements','file-earmark-text',[$A,$M,$R]],['officer/rentals.php','Handover & returns','key',[$A,$M,$R]],['officer/customers.php','Customers','people',[$A,$M,$R,$C]],
['admin/vehicles.php','Vehicles','car-front',[$A,$M,$R,$I]],['admin/drivers.php','Drivers','person-vcard',[$A,$M]],['admin/maintenance.php','Maintenance','tools',[$A,$M,$I]],
['cashier/finance.php?tab=inv','Invoices','receipt',[$A,$M,$C]],['cashier/finance.php?tab=pay','Payments','cash-coin',[$A,$M,$C]],['cashier/finance.php?tab=ref','Refunds','arrow-counterclockwise',[$A,$M,$C]],['cashier/finance.php?tab=dep','Deposits','safe',[$A,$M,$C]],
['manager/reports.php','Reports','bar-chart-line',[$A,$M]],['admin/users.php','Users','person-gear',[$A]],['admin/audit.php','Audit log','shield-check',[$A]],['admin/settings.php','Settings','gear',[$A]]];}
function head($title){$u=user();$name=trim((string)($u['name']??''));$initials=user_initials($name);$cur=substr(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),strlen(BASE)).(isset($_GET['tab'])?'?tab='.$_GET['tab']:'');$f=flash();$alertType=$f&&in_array($f[1],['success','danger','warning','info','primary','secondary','light','dark'],true)?$f[1]:'info';$notificationCount=(int)one("SELECT COUNT(*) FROM notifications WHERE user_id=? AND channel='in_app' AND status<>'read'",[$u['id']]);$recentNotifications=q("SELECT notification_id,notification_type,subject,message,related_entity_type,related_entity_id,status,created_at FROM notifications WHERE user_id=? AND channel='in_app' ORDER BY notification_id DESC LIMIT 5",[$u['id']])->fetchAll();?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($title)?> | CRMS</title>
<script>window.CRMS_BASE='<?=BASE?>';window.CRMS_CSRF='<?=e(csrf())?>';</script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?=BASE?>assets/style.css" rel="stylesheet"></head><body>
<div id="wrap"><nav id="side"><div class="brand"><i class="bi bi-car-front-fill"></i><span class="lbl">CRMS</span></div>
<?php foreach(menu() as [$h,$l,$i,$r]) { if($u && in_array($u['role'],$r)) { ?>
<a href="<?=BASE.$h?>" class="<?=$h===$cur?'on':''?>" title="<?=e($l)?>"><i class="bi bi-<?=$i?>"></i><span class="lbl"><?=e($l)?></span></a>
<?php } } ?><a href="<?=BASE?>logout.php" class="mt-auto signout-link"><i class="bi bi-box-arrow-left"></i><span class="lbl">Sign out</span></a></nav>
<main><header class="top"><button type="button" class="btn btn-light btn-sm" id="tg" aria-label="Toggle navigation"><i class="bi bi-layout-sidebar"></i></button><h1><?=e($title)?></h1>
<div class="dropdown ms-auto notification-dropdown"><button class="btn btn-light position-relative notification-trigger<?=$notificationCount?' has-unread':''?>" type="button" id="notificationMenu" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications, <?=$notificationCount?> unread"><i class="bi bi-bell-fill notification-bell-icon"></i><?php if($notificationCount):?><span id="notificationUnreadBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger"><?=e($notificationCount>99?'99+':$notificationCount)?></span><?php endif;?></button><div class="dropdown-menu dropdown-menu-end notification-menu" aria-labelledby="notificationMenu"><div class="d-flex justify-content-between align-items-center px-3 py-2"><strong>Notifications</strong><?php if($notificationCount):?><span id="notificationUnreadSummary" class="small text-muted"><?=$notificationCount?> unread</span><?php endif;?></div><div class="dropdown-divider"></div><?php if(!$recentNotifications):?><p class="small text-muted px-3 mb-2">You have no notifications.</p><?php else:foreach($recentNotifications as $notification):$notificationTarget=notification_link($notification);?><form method="post" action="<?=BASE?>shared/notifications.php" class="notification-open-form" data-notification-form><?=csrf_f()?><input type="hidden" name="action" value="open_notification"><input type="hidden" name="id" value="<?=e($notification['notification_id'])?>"><button type="submit" class="dropdown-item notification-item<?=$notification['status']==='read'?'':' unread'?>" aria-label="<?=e($notification['subject']?:'Update')?><?=e($notificationTarget?'':' (mark read only)')?>"><strong><?=e($notification['subject']?:'Update')?></strong><span><?=e($notification['message'])?></span><small><?=e($notification['created_at'])?><?=$notificationTarget?'':' · Mark read only'?></small></button></form><?php endforeach;endif;?><div class="dropdown-divider"></div><a class="dropdown-item text-center small" href="<?=BASE?>shared/notifications.php">View all notifications</a></div></div>
<div class="dropdown"><button class="profile-link profile-menu-button" type="button" id="profileMenu" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Open account menu for <?=e($name)?>"><span class="profile-avatar"><?=e($initials)?></span><span class="profile-copy small text-end"><b><?=e($name)?></b><br><span class="text-muted"><?=e($u['role'])?></span></span><i class="bi bi-chevron-down profile-chevron"></i></button><ul class="dropdown-menu dropdown-menu-end profile-dropdown" aria-labelledby="profileMenu"><?php if($u['role']==='Customer'){?><li><a class="dropdown-item" href="<?=BASE?>customer/my.php?tab=profile"><i class="bi bi-person-circle me-2"></i>My profile</a></li><li><hr class="dropdown-divider"></li><?php }?><li><a class="dropdown-item signout-item" href="<?=BASE?>logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sign out</a></li></ul></div></header><div class="content">
<?php if($f){?><div class="alert alert-<?=$alertType?> alert-dismissible"><?=e($f[0])?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Dismiss notification"></button></div><?php }}
function foot(){?></div><footer class="app-footer"><span>&copy; <?=date('Y')?> CRMS · Car Rental Management System</span><span>Reliable journeys, professionally managed.</span></footer></main></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?=BASE?>assets/app.js"></script></body></html><?php }
function csrf_f(){return '<input type="hidden" name="t" value="'.csrf().'">';}
function dashfile(){$m=['System Administrator'=>'admin/dashboard.php','Business Manager'=>'manager/dashboard.php','Rental Officer'=>'officer/dashboard.php','Accountant/Cashier'=>'cashier/dashboard.php','Customer'=>'customer/dashboard.php','Vehicle Inspector/Maintenance Staff'=>'admin/maintenance.php'];return BASE.($m[user()['role']]??'logout.php');}
function u($h){static $m=['rentals.php'=>'officer/','reservations.php'=>'officer/','agreements.php'=>'officer/','customers.php'=>'officer/','finance.php'=>'cashier/','reports.php'=>'manager/','cars.php'=>'customer/','my.php'=>'customer/','vehicles.php'=>'admin/','drivers.php'=>'admin/','maintenance.php'=>'admin/','users.php'=>'admin/','audit.php'=>'admin/','settings.php'=>'admin/'];return BASE.($m[strtok($h,'?')]??'').$h;}
function kpi($l,$v,$i,$h=null){echo '<div class="col-6 col-xl-3"><div class="card kpi p-3 flex-row align-items-center gap-3">'.($h?'<a href="'.u($h).'" class="stretched-link"></a>':'').'<div class="ico"><i class="bi bi-'.$i.'"></i></div><div><div class="text-muted small">'.$l.'</div><div class="v">'.$v.'</div></div></div></div>';}
function tiles($a){echo '<div class="row g-2 mb-4">';foreach($a as [$h,$l,$i])echo '<div class="col-6 col-md-4 col-xl"><a class="tile" href="'.u($h).'"><i class="bi bi-'.$i.'"></i><span>'.$l.'</span></a></div>';echo '</div>';}
function fld($n,$l,$v='',$t='text',$o=null,$c='col-md-3',$r=false){echo '<div class="'.$c.'"><label class="form-label small">'.$l.'</label>';if($o){echo '<select name="'.$n.'" class="form-select">';foreach($o as $k=>$x)echo '<option value="'.e($k).'"'.((string)$k===(string)$v?' selected':'').'>'.e($x).'</option>';echo '</select>';}else echo '<input type="'.$t.'" name="'.$n.'" value="'.e($v).'" class="form-control"'.($r?' required':'').($t==='number'?' step="any"':'').'>';echo '</div>';}
function panel($title,$cols,$rows,$link=null,$empty='Nothing to show.'){echo '<div class="card h-100"><div class="card-header d-flex justify-content-between">'.e($title).($link?'<a class="small" href="'.u($link).'">View all</a>':'').'</div><div class="card-body table-responsive p-0">';if(!$rows){echo '<p class="text-muted p-3 mb-0">'.e($empty).'</p>';}else{echo '<table class="table table-sm table-hover align-middle mb-0"><thead><tr>';foreach($cols as $c)echo '<th scope="col">'.e($c).'</th>';echo '</tr></thead><tbody>';foreach($rows as $r){echo '<tr>';foreach($r as $c)echo '<td>'.$c.'</td>';echo '</tr>';}echo '</tbody></table>';}echo '</div></div>';}
function one($s,$a=[]){return q($s,$a)->fetchColumn();}
