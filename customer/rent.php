<?php require __DIR__.'/../includes/app.php';need('Customer');$vid=(int)($_GET['v']??0);
$v=q("SELECT v.*,c.category_name FROM vehicles v JOIN vehicle_categories c USING(category_id) WHERE vehicle_id=? AND v.status='available'",[$vid])->fetch();if(!$v){flash('Vehicle is not available.','warning');header('Location: cars.php');exit;}
$c=me();$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){chk();$p=$_POST;
 $pu=strtotime($p['pickup_datetime']??'');$rt=strtotime($p['return_datetime']??'');
 $identType=$p['identification_type']??'';$identNumber=trim($p['identification_number']??'');
 $driverId=(int)($p['driver_id']??0);
 if(!$pu||!$rt||$rt<=$pu||$pu<=time())$err='Choose a future pickup and a later return date.';
 elseif($rt-$pu<7*60*60)$err='The rental period must be at least 7 hours.';
 elseif($driverId<1)$err='Choose a company driver for this rental. Customers cannot self-drive.';
 elseif(!in_array($identType,['national_id','passport','other'],true)||$identNumber==='')$err='Enter a valid identification type and number.';
 else{$pdo=db();try{$pdo->beginTransaction();
  $duplicate=q('SELECT customer_id FROM customers WHERE identification_type=? AND identification_number=? AND customer_id<>? LIMIT 1',[$identType,$identNumber,$c['customer_id']])->fetchColumn();
  if($duplicate)throw new RuntimeException('This identification number is already linked to another customer profile. Please check your details or contact the rental office.');
  q('UPDATE customers SET phone=?,address_line=?,city=?,identification_type=?,identification_number=? WHERE customer_id=?',[trim($p['phone']??''),trim($p['address_line']??''),trim($p['city']??''),$identType,$identNumber,$c['customer_id']]);
  if(!q("SELECT driver_id FROM drivers WHERE driver_id=? AND availability_status='available' FOR UPDATE",[$driverId])->fetchColumn())throw new RuntimeException('The selected company driver is no longer available. Please choose another driver.');
  $lk=q("SELECT status FROM vehicles WHERE vehicle_id=? FOR UPDATE",[$vid])->fetchColumn();
  $pickupAt=date('Y-m-d H:i:s',$pu);$returnAt=date('Y-m-d H:i:s',$rt);
  $ov=q("SELECT COUNT(*) FROM reservations WHERE vehicle_id=? AND status IN('pending','confirmed') AND pickup_datetime<? AND return_datetime>?",[$vid,$returnAt,$pickupAt])->fetchColumn();
  if($lk!=='available'||$ov)throw new Exception('Sorry, this vehicle is already booked for those dates. Try other dates or another car.');
  $driverOverlap=q("SELECT COUNT(*) FROM reservations WHERE requested_driver_id=? AND status IN('pending','confirmed') AND pickup_datetime<? AND return_datetime>?",[$driverId,$returnAt,$pickupAt])->fetchColumn();
  if($driverOverlap)throw new RuntimeException('The selected company driver is already reserved for those dates. Please choose another driver.');
  $days=ceil(($rt-$pu)/86400);$amt=$days*$v['daily_rate'];$tax=0;$dep=round($amt*0.2);
  $reservationCode=code('RES');
  q('INSERT INTO reservations(reservation_code,customer_id,vehicle_id,requested_driver_id,pickup_datetime,return_datetime,pickup_location,return_location,daily_rate,estimated_days,estimated_amount,tax_amount,security_deposit,customer_notes,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$reservationCode,$c['customer_id'],$vid,$driverId?:null,$pickupAt,$returnAt,trim($p['pickup_location']??''),trim($p['return_location']??''),$v['daily_rate'],$days,$amt,$tax,$dep,trim($p['customer_notes']??''),user()['id']]);$rid=$pdo->lastInsertId();
  $terms="1. Only a CRMS-assigned company driver may drive the rental vehicle. The renter must not drive or allow an unauthorized person to drive.\n2. The vehicle must be returned on the agreed date, time and place, with the same fuel level. Late returns are charged one extra daily rate per started day.\n3. The renter is liable for damage, loss, fines and cleaning costs beyond normal wear.\n4. The security deposit is held until return inspection and may be reduced by agreed deductions.\n5. Payments are validated by our cashier; the rental is confirmed after validation and signing.";
  q("INSERT INTO rental_agreements(agreement_number,reservation_id,customer_id,vehicle_id,agreed_daily_rate,security_deposit,terms_and_conditions,status) VALUES(?,?,?,?,?,?,?,'issued')",[code('AGR'),$rid,$c['customer_id'],$vid,$v['daily_rate'],$dep,$terms]);$aid=$pdo->lastInsertId();
  notify_role('Rental Officer','reservation_submitted','Rental request needs review',$c['first_name'].' '.$c['last_name'].' submitted rental request '.$reservationCode.'.','reservation',$rid);
  notify_customer($c['customer_id'],'reservation_submitted','Rental request received','Your rental request has been received. The rental office will review it and update you.','reservation',$rid);
  audit('reserve','reservation',$rid);$pdo->commit();header('Location: '.BASE.'shared/agreement.php?id='.$aid.'&step=2');exit;
 }catch(Throwable $x){if($pdo->inTransaction())$pdo->rollBack();$err=$x instanceof PDOException&&$x->getCode()==='23000'?'These details conflict with an existing customer record. Please verify your identification information.':$x->getMessage();}}}
$dr=q("SELECT * FROM drivers WHERE availability_status='available'")->fetchAll();$f=$_POST?:$c;
head('Rental request');function val($f,$k){return e($f[$k]??'');}?>
<div class="rental-process mb-4" aria-label="Rental application steps"><div class="rental-step active"><span>1</span><div><b>Application</b><small>Enter your rental details</small></div></div><div class="rental-step"><span>2</span><div><b>Agreement</b><small>Review the rental terms</small></div></div><div class="rental-step"><span>3</span><div><b>Payment</b><small>Pay and await validation</small></div></div><div class="rental-step"><span>4</span><div><b>Signatures</b><small>Renter and rental officer</small></div></div></div>
<?php if($err)echo '<div class="alert alert-danger">'.e($err).'</div>';?>
<form method="post" class="row g-4" data-rental-dates><?=csrf_f()?>
<div class="col-lg-4"><div class="card vehicle-summary"><div class="card-header">Selected vehicle</div><div class="card-body"><h5><?=e($v['make'].' '.$v['model'])?></h5><p class="small text-muted"><?=e($v['category_name'].' · '.$v['registration_no'])?></p><b><?=money($v['daily_rate'])?></b> per day<hr class="my-2"><div class="small text-muted">Your rental total and refundable security deposit will appear in the agreement after you submit your application.</div></div></div></div>
<div class="col-lg-8"><div class="card mb-3"><div class="card-header">Trip details</div><div class="card-body row g-3">
<div class="col-md-6"><label class="form-label">Pickup date & time</label><input type="datetime-local" id="pickup_datetime" name="pickup_datetime" class="form-control" value="<?=val($f,'pickup_datetime')?>" required></div>
<div class="col-md-6"><label class="form-label">Return date & time</label><input type="datetime-local" id="return_datetime" name="return_datetime" class="form-control" value="<?=val($f,'return_datetime')?>" required></div>
<div class="col-md-6"><label class="form-label">Pickup location</label><input name="pickup_location" class="form-control" value="<?=val($f,'pickup_location')?>" required></div>
<div class="col-md-6"><label class="form-label">Return location</label><input name="return_location" class="form-control" value="<?=val($f,'return_location')?>" required></div>
<div class="col-md-6"><label class="form-label">Company driver</label><select name="driver_id" class="form-select" required><option value="">Choose a company driver</option><?php foreach($dr as $d)echo '<option value="'.$d['driver_id'].'"'.((int)($f['driver_id']??0)===(int)$d['driver_id']?' selected':'').'>'.e($d['first_name'].' '.$d['last_name']).'</option>';?></select></div>
<div class="col-md-6"><label class="form-label">Notes</label><input name="customer_notes" class="form-control" value="<?=val($f,'customer_notes')?>"></div></div></div>
<div class="card mb-3"><div class="card-header">Your details</div><div class="card-body row g-3">
<div class="col-md-6"><label class="form-label">Phone</label><input name="phone" class="form-control" value="<?=val($f,'phone')?>" required></div>
<div class="col-md-6"><label class="form-label">Address</label><input name="address_line" class="form-control" value="<?=val($f,'address_line')?>" required></div>
<div class="col-md-6"><label class="form-label">City</label><input name="city" class="form-control" value="<?=val($f,'city')?>" required></div>
<div class="col-md-6"><label class="form-label">ID type</label><select name="identification_type" class="form-select"><?php foreach(['national_id'=>'National ID','passport'=>'Passport','other'=>'Other'] as $k=>$l)echo '<option value="'.$k.'"'.(($f['identification_type']??'')===$k?' selected':'').'>'.$l.'</option>';?></select></div>
<div class="col-md-6"><label class="form-label">ID number</label><input name="identification_number" class="form-control" value="<?=val($f,'identification_number')?>" required></div>
</div></div>
<button type="submit" class="btn btn-accent btn-lg"><i class="bi bi-send me-2"></i>Submit rental application</button></div></form><?php foot();
