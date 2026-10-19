<?php
require __DIR__.'/../includes/app.php';
need();
if(user()['role']!=='Customer'&&!in_array(user()['role'],['System Administrator','Business Manager','Rental Officer','Accountant/Cashier'],true)){http_response_code(403);exit('Access denied');}
ensure_agreement_signature_schema();
$id=(int)($_GET['id']??0);
$a=q('SELECT a.*,r.reservation_code,r.pickup_datetime,r.return_datetime,r.pickup_location,r.return_location,r.estimated_days,r.estimated_amount,r.tax_amount,r.discount_amount,r.status AS reservation_status,c.user_id,c.first_name,c.last_name,c.phone,c.email,c.identification_type,c.identification_number,v.make,v.model,v.registration_no,v.colour FROM rental_agreements a JOIN reservations r USING(reservation_id) JOIN customers c ON c.customer_id=a.customer_id JOIN vehicles v ON v.vehicle_id=a.vehicle_id WHERE a.agreement_id=?',[$id])->fetch();
if(!$a||(user()['role']==='Customer'&&(int)$a['user_id']!==(int)user()['id'])){http_response_code(404);exit('Not found');}
$inv=q('SELECT * FROM vw_invoice_balances WHERE reservation_id=? ORDER BY invoice_id DESC LIMIT 1',[$a['reservation_id']])->fetch();
$own=user()['role']==='Customer';
$staffSigner=in_array(user()['role'],['System Administrator','Business Manager','Rental Officer'],true);
$staffInvoiceCreator=in_array(user()['role'],['System Administrator','Business Manager','Rental Officer','Accountant/Cashier'],true);
$depositReceived=(float)q("SELECT COALESCE(SUM(amount),0) FROM deposit_transactions WHERE reservation_id=? AND transaction_type='received' AND status='completed'",[$a['reservation_id']])->fetchColumn();
$depositDue=(float)$a['security_deposit'];
$invoicePaid=$inv&&(float)$inv['outstanding_balance']<=0.009;
$depositPaid=$depositReceived+0.009>=$depositDue;
$paid=$invoicePaid&&$depositPaid;
$renterSigned=!empty($a['renter_signed_at'])&&!empty($a['renter_signed_name']);
$staffSigned=!empty($a['staff_signed_at'])&&!empty($a['staff_signed_name']);
$fullySigned=$renterSigned&&$staffSigned;
$requestedStep=(int)($_GET['step']??2);
$step=$fullySigned?4:(!$inv||$inv['status']==='draft'?2:(!$paid?($requestedStep>=3?3:2):($requestedStep>=4?4:3)));
$err='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    chk();
    $action=$_POST['action']??'';
    $pdo=db();
    try{
        if($action==='create_invoice'&&($own||$staffInvoiceCreator)){
            if($own&&empty($_POST['reviewed_terms']))throw new RuntimeException('Review and accept the agreement terms before creating your invoice.');
            $pdo->beginTransaction();
            $invoice=create_reservation_invoice((int)$a['reservation_id'],$own?null:(int)user()['id']);
            audit('create_invoice','invoice',$invoice['invoice_id'],$own?'Customer created invoice after agreement review':'Staff created invoice for submitted rental application');
            $pdo->commit();
            flash($invoice['created']?'Invoice created and is ready for payment.':'Invoice is already available for this application.');
        }elseif($action==='pay'&&$own){
            if(!$inv)throw new RuntimeException('Create your invoice after reviewing the agreement before submitting payment.');
            $pdo->beginTransaction();
            q('SELECT invoice_id FROM invoices WHERE invoice_id=? FOR UPDATE',[$inv['invoice_id']])->fetch();
            $currentInvoice=q('SELECT * FROM vw_invoice_balances WHERE invoice_id=?',[$inv['invoice_id']])->fetch();
            if(!$currentInvoice)throw new RuntimeException('The invoice for this agreement is no longer available.');
            if($currentInvoice['status']==='draft')throw new RuntimeException('Your invoice is awaiting rental-officer review. You can submit payment after it is issued.');
            if(in_array($currentInvoice['status'],['void','refunded'],true))throw new RuntimeException('This invoice cannot accept payments.');
            if((float)$currentInvoice['outstanding_balance']<=0.009){$pdo->commit();flash('The invoice is already paid. Continue to the signature step.','info');}
            else{
                $amount=filter_var($_POST['amount']??null,FILTER_VALIDATE_FLOAT);
                $method=$_POST['method']??'';
                $methods=['mobile_money','bank_transfer','card','cash'];
                $pending=(float)q("SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id=? AND status='pending'",[$inv['invoice_id']])->fetchColumn();
                if($amount===false||$amount<=0||$amount>(float)$currentInvoice['outstanding_balance']-$pending+0.001)throw new RuntimeException('Enter an amount up to the unpaid balance not already awaiting validation.');
                if(!in_array($method,$methods,true))throw new RuntimeException('Select a valid payment method.');
                $reference=trim($_POST['ref']??'');
                q("INSERT INTO payments(payment_number,invoice_id,amount,payment_method,transaction_reference,status,notes) VALUES(?,?,?,?,?,'pending','Customer payment awaiting validation')",[code('PAY'),$inv['invoice_id'],$amount,$method,$reference?:null]);
                $paymentId=(int)$pdo->lastInsertId();
                notify_role('Accountant/Cashier','payment_pending','Payment awaiting validation','A customer submitted a payment of '.money($amount).' for rental request '.$a['reservation_code'].'.','payment',$paymentId);
                notify_customer($a['customer_id'],'payment_submitted','Payment submitted','Your payment of '.money($amount).' has been submitted and is awaiting cashier validation.','payment',$paymentId);
                if($depositDue>0&&!q("SELECT 1 FROM deposit_transactions WHERE reservation_id=? AND transaction_type='received' AND status IN('pending','completed') LIMIT 1",[$a['reservation_id']])->fetch()){
                    q("INSERT INTO deposit_transactions(reservation_id,customer_id,transaction_type,amount,payment_method,reference_number,reason,status) VALUES(?,?,'received',?,?,?,'Rental security deposit','pending')",[$a['reservation_id'],$a['customer_id'],$depositDue,$method,$reference?:null]);
                    notify_role('Accountant/Cashier','deposit_pending','Security deposit awaiting validation','A security deposit was submitted for rental request '.$a['reservation_code'].'.','deposit',$a['reservation_id']);
                    notify_customer($a['customer_id'],'deposit_submitted','Security deposit submitted','Your security deposit is awaiting cashier validation.','deposit',$a['reservation_id']);
                }
                audit('submit_payment','agreement',$id,'Rental payment submitted for validation');
                $pdo->commit();
                flash('Payment submitted. It must be validated by the cashier before you can sign the agreement.','info');
            }
        }elseif($action==='deposit'&&$own){
            $pdo->beginTransaction();
            q('SELECT reservation_id FROM reservations WHERE reservation_id=? FOR UPDATE',[$a['reservation_id']])->fetch();
            $receivedNow=(float)q("SELECT COALESCE(SUM(amount),0) FROM deposit_transactions WHERE reservation_id=? AND transaction_type='received' AND status='completed'",[$a['reservation_id']])->fetchColumn();
            $depositAmount=max(0,$depositDue-$receivedNow);
            if($depositAmount<=0.009)throw new RuntimeException('No security deposit payment is currently due.');
            $method=$_POST['method']??'';
            if(!in_array($method,['mobile_money','bank_transfer','card','cash'],true))throw new RuntimeException('Select a valid payment method.');
            if(q("SELECT 1 FROM deposit_transactions WHERE reservation_id=? AND transaction_type='received' AND status='pending' LIMIT 1",[$a['reservation_id']])->fetch())throw new RuntimeException('Your security deposit is already awaiting cashier validation.');
            $reference=trim($_POST['ref']??'');
            q("INSERT INTO deposit_transactions(reservation_id,customer_id,transaction_type,amount,payment_method,reference_number,reason,status) VALUES(?,?,'received',?,?,?,'Rental security deposit','pending')",[$a['reservation_id'],$a['customer_id'],$depositAmount,$method,$reference?:null]);
            notify_role('Accountant/Cashier','deposit_pending','Security deposit awaiting validation','A security deposit was submitted for rental request '.$a['reservation_code'].'.','deposit',$a['reservation_id']);
            notify_customer($a['customer_id'],'deposit_submitted','Security deposit submitted','Your security deposit is awaiting cashier validation.','deposit',$a['reservation_id']);
            audit('submit_deposit','agreement',$id,'Security deposit submitted for validation');
            $pdo->commit();
            flash('Security deposit submitted. It must be validated by the cashier before you can sign.','info');
        }elseif($action==='sign_renter'&&$own){
            if(!$paid)throw new RuntimeException('Complete and receive cashier validation for the full rental payment and security deposit before signing.');
            if($renterSigned)throw new RuntimeException('Your renter signature has already been recorded.');
            $name=trim($_POST['name']??'');
            $signature=validate_agreement_signature($_POST['signature_image']??'');
            $customerName=trim($a['first_name'].' '.$a['last_name']);
            if(strcasecmp($name,$customerName)!==0||empty($_POST['agree']))throw new RuntimeException('Enter your full name exactly as shown and accept the agreement terms.');
            $pdo->beginTransaction();
            q("UPDATE rental_agreements SET renter_signed_name=?,renter_signed_at=NOW(),renter_signature_image=? WHERE agreement_id=? AND renter_signed_at IS NULL",[$customerName,$signature['data'],$id]);
            if((int)q('SELECT ROW_COUNT()')->fetchColumn()!==1)throw new RuntimeException('The renter signature has already been recorded. Reload the agreement to continue.');
            notify_role('Rental Officer','renter_signed','Agreement needs countersignature',$customerName.' signed the agreement for rental request '.$a['reservation_code'].'.','agreement',$id);
            audit('sign_as_renter','agreement',$id);
            $pdo->commit();
            flash('Your signature has been recorded. The rental representative must countersign before the agreement is complete.','info');
        }elseif($action==='sign_staff'&&$staffSigner){
            if(!$paid)throw new RuntimeException('The full invoice and security deposit must be validated before countersigning.');
            if(!$renterSigned)throw new RuntimeException('The renter must sign before the rental representative countersigns.');
            if($staffSigned)throw new RuntimeException('The rental representative signature has already been recorded.');
            $name=trim($_POST['name']??'');
            $signature=validate_agreement_signature($_POST['signature_image']??'');
            $staffName=trim(user()['name']??'');
            if($name===''||strcasecmp($name,$staffName)!==0||empty($_POST['agree']))throw new RuntimeException('Confirm your own full name and accept the agreement terms.');
            $pdo->beginTransaction();
            q("UPDATE rental_agreements SET staff_signed_by=?,staff_signed_name=?,staff_signed_at=NOW(),staff_signature_image=?,status='signed',signed_at=NOW() WHERE agreement_id=? AND renter_signed_at IS NOT NULL AND staff_signed_at IS NULL",[user()['id'],$staffName,$signature['data'],$id]);
            if(q('SELECT ROW_COUNT()')->fetchColumn()!==1)throw new RuntimeException('The agreement changed before your signature could be saved. Reload and try again.');
            notify_customer($a['customer_id'],'agreement_signed','Rental agreement complete','The rental officer has countersigned your agreement. It is complete and ready to download.','agreement',$id);
            audit('countersign','agreement',$id);
            $pdo->commit();
            flash('Both parties have signed. The completed agreement is ready to download.');
        }else{
            throw new RuntimeException('This action is not available for your account.');
        }
    }catch(Throwable $x){
        if($pdo->inTransaction())$pdo->rollBack();
        if($x instanceof PDOException){error_log('Rental agreement action failed: '.$x->getMessage());flash($x->getCode()==='23000'?'That transaction reference is already in use. Check the reference and try again.':'We could not process that request due to a database error. Please try again or contact the rental office.','danger');}
        else flash($x->getMessage(),'danger');
    }
    header('Location: agreement.php?id='.$id.'&step='.(in_array($action,['create_invoice','pay','deposit'],true)?3:4));exit;
}

head('Rental agreement '.$a['agreement_number']);
$fullName=trim($a['first_name'].' '.$a['last_name']);
$total=$inv&&$inv['status']!=='draft'?(float)$inv['total_amount']:max(0,(float)$a['estimated_amount']-(float)$a['discount_amount']);
$pendingPayments=$inv?q("SELECT payment_number,amount,payment_method,transaction_reference,payment_date FROM payments WHERE invoice_id=? AND status='pending' ORDER BY payment_id DESC",[$inv['invoice_id']])->fetchAll():[];
?>
<div class="rental-process mb-4 noprint" aria-label="Rental application progress">
<?php foreach([2=>'Agreement',3=>'Payment',4=>'Signatures'] as $number=>$label){$complete=($number===2)||($number===3&&$paid)||($number===4&&$fullySigned);$active=$number===$step&&!$complete;?>
<div class="rental-step<?=$complete?' complete':''?><?=$active?' active':''?>"><span><?=$complete?'<i class="bi bi-check-lg"></i>':$number?></span><div><b><?=e($label)?></b><small><?=$number===2?'Review your rental terms':($number===3?'Pay and await validation':'Renter and rental officer')?></small></div></div>
<?php }?>
</div>
<div class="row g-4"><div class="col-lg-8">
<article class="agr rental-agreement"><div class="agreement-heading"><div class="agreement-mark"><i class="bi bi-car-front-fill"></i></div><div><div class="small text-uppercase fw-semibold text-muted">CRMS · Car Rental Management</div><h2 class="text-center mb-1">Vehicle Rental Agreement</h2></div></div>
<p class="text-center text-muted">No. <?=e($a['agreement_number'])?> · Reservation <?=e($a['reservation_code'])?> · <?=badge($fullySigned?'signed':$a['status'])?></p>
<div class="table-responsive"><table class="table agreement-table"><tbody>
<tr><th scope="row">Renter</th><td><?=e($fullName)?></td></tr>
<tr><th scope="row">Identification</th><td><?=e(ucfirst(str_replace('_',' ',$a['identification_type']??'Not provided')).' '.($a['identification_number']??''))?></td></tr>
<tr><th scope="row">Contact</th><td><?=e($a['phone'])?> · <?=e($a['email']??'')?></td></tr>
<tr><th scope="row">Vehicle</th><td><?=e($a['make'].' '.$a['model'].' · '.$a['registration_no'])?></td></tr>
<tr><th scope="row">Rental period</th><td><?=e($a['pickup_datetime'])?> at <?=e($a['pickup_location'])?> to <?=e($a['return_datetime'])?> at <?=e($a['return_location'])?> (<?=e($a['estimated_days']+0)?> days)</td></tr>
</tbody></table></div>
<div class="table-responsive mt-3"><table class="table agreement-table agreement-costs"><thead><tr><th scope="col">Charge</th><th scope="col" class="text-end">Amount</th></tr></thead><tbody>
<tr><td>Daily rate</td><td class="text-end"><?=money($a['agreed_daily_rate'])?></td></tr>
<tr><td>Rental (<?=e($a['estimated_days']+0)?> days)</td><td class="text-end"><?=money($a['estimated_amount'])?></td></tr>
<?php if((float)$a['discount_amount']>0):?><tr><td>Discount</td><td class="text-end">-<?=money($a['discount_amount'])?></td></tr><?php endif;?>
<tr class="fw-bold"><td>Total invoice</td><td class="text-end"><?=money($total)?></td></tr>
<tr><td>Refundable security deposit</td><td class="text-end"><?=money($depositDue)?></td></tr>
</tbody></table></div>
<h3 class="h6 mt-4">Terms and conditions</h3><p class="small agreement-terms"><?=e($a['terms_and_conditions'])?></p>
<p class="small text-muted mt-3">Drawn signatures and typed names are visual electronic acknowledgments; they are not cryptographic or identity-verified digital signatures.</p>
<div class="signature-grid mt-4"><div class="signature-box"><div class="small text-muted text-uppercase fw-semibold">Renter</div><?php if($renterSigned&&!empty($a['renter_signature_image'])):?><img class="agreement-signature-image" src="<?=e($a['renter_signature_image'])?>" alt="Renter's drawn signature"><?php endif;?><strong><?=e($renterSigned?$a['renter_signed_name']:$fullName)?></strong><span><?=$renterSigned?'Signed electronically · '.e($a['renter_signed_at']):'Signature pending'?></span></div><div class="signature-box"><div class="small text-muted text-uppercase fw-semibold">Authorized rental representative</div><?php if($staffSigned&&!empty($a['staff_signature_image'])):?><img class="agreement-signature-image" src="<?=e($a['staff_signature_image'])?>" alt="Rental representative's drawn signature"><?php endif;?><strong><?=e($staffSigned?$a['staff_signed_name']:'CRMS Rental Office')?></strong><span><?=$staffSigned?'Countersigned electronically · '.e($a['staff_signed_at']):'Countersignature pending'?></span></div></div>
</article>
<div class="d-flex gap-2 flex-wrap mt-3 noprint">
<button type="button" onclick="window.print()" class="btn btn-outline-secondary"><i class="bi bi-printer me-1"></i>Print agreement</button>
<?php if($fullySigned):?><a class="btn btn-primary" href="<?=BASE?>shared/agreement_download.php?id=<?=$id?>"><i class="bi bi-download me-1"></i>Download signed PDF</a><?php endif;?>
</div></div>
<div class="col-lg-4 noprint">
<?php if($step===2){?><div class="card process-card"><div class="card-header"><span class="process-number">2</span> Review agreement</div><div class="card-body"><p>Review the rental period, charges and terms shown in the agreement before creating an invoice.</p>
<?php if(!$inv||$inv['status']==='draft'){if($own){?><form method="post" class="signature-form"><?=csrf_f()?><input type="hidden" name="action" value="create_invoice"><div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="reviewed_terms" id="reviewed-terms" value="1" required><label class="form-check-label" for="reviewed-terms">I have reviewed the agreement and confirm these rental details before creating my invoice.</label></div><button class="btn btn-accent w-100"><i class="bi bi-receipt me-2"></i>Create invoice and continue to payment</button></form>
<?php }elseif($staffInvoiceCreator){?><form method="post"><?=csrf_f()?><input type="hidden" name="action" value="create_invoice"><button class="btn btn-primary w-100"><i class="bi bi-receipt me-2"></i>Create invoice for customer</button></form>
<?php }else{?><div class="alert alert-info mb-0">This application is ready for invoice creation by the customer or an authorized staff member.</div><?php }}else{?><div class="alert alert-success"><strong><?=e($inv['invoice_number'])?></strong> is ready for payment.</div><a class="btn btn-accent w-100" href="<?=BASE?>shared/agreement.php?id=<?=$id?>&amp;step=3">Proceed to step 3 · Payment<i class="bi bi-arrow-right ms-2"></i></a><?php }?></div></div>
<?php }elseif($step===3){?><div class="card process-card"><div class="card-header"><span class="process-number">3</span> Rental payment</div><div class="card-body">
<div class="payment-summary"><div><span>Invoice balance</span><strong><?=money($inv['outstanding_balance'])?></strong></div><div><span>Security deposit</span><strong><?=money(max(0,$depositDue-$depositReceived))?></strong></div></div>
<?php if($paid){?><div class="alert alert-success mt-3 mb-3">Your rental payment and security deposit have been validated.</div><a class="btn btn-primary w-100" href="<?=BASE?>shared/agreement.php?id=<?=$id?>&amp;step=4">Proceed to step 4 · Sign agreement<i class="bi bi-arrow-right ms-2"></i></a>
<?php }else{if($inv['status']==='draft'){?><div class="alert alert-warning mt-3"><i class="bi bi-hourglass-split me-2"></i>Your invoice has been created and is visible to the rental office and cashier. Payment will be available as soon as a rental officer reviews and issues it.</div><?php }else{if($pendingPayments){?><div class="alert alert-info mt-3">Payment is awaiting cashier validation. You can submit any remaining unpaid balance below.</div><div class="table-responsive mb-3"><table class="table table-sm"><thead><tr><th scope="col">Payment</th><th scope="col">Amount</th><th scope="col">Method</th><th scope="col">Reference</th></tr></thead><tbody><?php foreach($pendingPayments as $payment):?><tr><td><?=e($payment['payment_number'])?></td><td><?=money($payment['amount'])?></td><td><?=e($payment['payment_method'])?></td><td><?=e($payment['transaction_reference']?:'—')?></td></tr><?php endforeach;?></tbody></table></div><?php }?>
<?php $pendingAmount=(float)q("SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id=? AND status='pending'",[$inv['invoice_id']])->fetchColumn();$remaining=max(0,(float)$inv['outstanding_balance']-$pendingAmount);if($remaining>0.009){?><form method="post" class="payment-form"><?=csrf_f()?><input type="hidden" name="action" value="pay"><label class="form-label" for="amount">Amount to pay</label><input id="amount" type="number" step="0.01" min="0.01" max="<?=e($inv['outstanding_balance'])?>" name="amount" value="<?=e($remaining)?>" class="form-control mb-3" required>
<label class="form-label" for="method">Payment method</label><select id="method" name="method" class="form-select mb-3"><option value="mobile_money">Mobile money</option><option value="bank_transfer">Bank transfer</option><option value="card">Card</option><option value="cash">Cash</option></select>
<label class="form-label" for="ref">Transaction reference</label><input id="ref" name="ref" class="form-control mb-3" autocomplete="off"><button type="submit" class="btn btn-accent w-100">Submit payment for validation</button></form>
<?php }elseif(!$invoicePaid){?><div class="alert alert-info mt-3">All remaining invoice payments are awaiting cashier validation.</div><?php }?>
<?php if(!$depositPaid&&$depositDue>0){$pendingDeposit=q("SELECT 1 FROM deposit_transactions WHERE reservation_id=? AND transaction_type='received' AND status='pending' LIMIT 1",[$a['reservation_id']])->fetch();if($pendingDeposit){?><div class="alert alert-info mt-3">The security deposit is awaiting cashier validation.</div><?php }elseif($invoicePaid){?><form method="post" class="payment-form mt-3"><?=csrf_f()?><input type="hidden" name="action" value="deposit"><p class="small">Security deposit due: <strong><?=money(max(0,$depositDue-$depositReceived))?></strong></p><label class="form-label" for="deposit-method">Payment method</label><select id="deposit-method" name="method" class="form-select mb-3"><option value="mobile_money">Mobile money</option><option value="bank_transfer">Bank transfer</option><option value="card">Card</option><option value="cash">Cash</option></select><label class="form-label" for="deposit-ref">Transaction reference</label><input id="deposit-ref" name="ref" class="form-control mb-3"><button class="btn btn-accent w-100">Submit security deposit</button></form><?php }else{?><p class="small text-muted mt-3">The security deposit is submitted together with your first rental payment.</p><?php }}?>
<p class="small text-muted mt-3 mb-0">Submitted payments are not complete until the cashier validates them.</p><?php }}?>
</div></div>
<?php }else{?><div class="card process-card"><div class="card-header"><span class="process-number">4</span> Signatures</div><div class="card-body">
<?php if(!$paid){?><div class="alert alert-warning">Step 4 unlocks after the full invoice and security deposit are validated.</div><a class="btn btn-outline-primary w-100" href="<?=BASE?>shared/agreement.php?id=<?=$id?>&amp;step=3">Return to step 3 · Payment</a>
<?php }elseif($own&&!$renterSigned){?><p>Confirm the agreement with your drawn renter signature. An authorized rental representative will countersign afterwards.</p><form method="post" class="signature-form draw-signature-form"><?=csrf_f()?><input type="hidden" name="action" value="sign_renter"><input type="hidden" name="signature_image"><label class="form-label" for="renter-name">Type your full name as shown in the agreement</label><input id="renter-name" name="name" class="form-control mb-3" value="<?=e($fullName)?>" autocomplete="name" required><label class="form-label" for="renter-signature">Draw your signature</label><canvas id="renter-signature" class="signature-canvas" aria-label="Draw renter signature"></canvas><div class="d-flex justify-content-between align-items-center mb-3"><small class="text-muted signature-hint" aria-live="polite">Use your mouse, finger, or stylus to sign.</small><button type="button" class="btn btn-sm btn-outline-secondary clear-signature">Clear</button></div><div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="agree" id="renter-agree" value="1" required><label class="form-check-label" for="renter-agree">I have read and accept the agreement terms.</label></div><button class="btn btn-primary w-100">Sign as renter</button></form>
<?php }elseif($staffSigner&&$renterSigned&&!$staffSigned){?><div class="alert alert-info">The renter has signed. Countersign to complete the agreement.</div><form method="post" class="signature-form draw-signature-form"><?=csrf_f()?><input type="hidden" name="action" value="sign_staff"><input type="hidden" name="signature_image"><label class="form-label" for="staff-name">Authorized representative</label><input id="staff-name" name="name" class="form-control mb-3" value="<?=e(user()['name'])?>" required><label class="form-label" for="staff-signature">Draw your signature</label><canvas id="staff-signature" class="signature-canvas" aria-label="Draw rental representative signature"></canvas><div class="d-flex justify-content-between align-items-center mb-3"><small class="text-muted signature-hint" aria-live="polite">Use your mouse, finger, or stylus to sign.</small><button type="button" class="btn btn-sm btn-outline-secondary clear-signature">Clear</button></div><div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="agree" id="staff-agree" value="1" required><label class="form-check-label" for="staff-agree">I confirm this rental agreement on behalf of CRMS.</label></div><button class="btn btn-primary w-100">Countersign agreement</button></form>
<?php }elseif($fullySigned){?><div class="alert alert-success"><i class="bi bi-patch-check-fill me-2"></i>Both parties have signed. Your completed agreement is ready.</div><a class="btn btn-primary w-100" href="<?=BASE?>shared/agreement_download.php?id=<?=$id?>"><i class="bi bi-download me-1"></i>Download signed PDF</a>
<?php }elseif($own&&$renterSigned){?><div class="alert alert-info">Your drawn signature is recorded. The authorized rental representative must countersign the agreement.</div>
<?php }else{?><div class="alert alert-info">This agreement is ready for the renter's signature. The customer must sign before the rental representative countersigns.</div><?php }?>
</div></div><?php }?>
</div></div>
<script>
document.querySelectorAll('.draw-signature-form').forEach(function(form){
 const canvas=form.querySelector('.signature-canvas');
 const context=canvas.getContext('2d');
 const field=form.querySelector('[name="signature_image"]');
 const hint=form.querySelector('.signature-hint');
 let drawing=false,drawn=false;
 function resize(){
  const rect=canvas.getBoundingClientRect(),ratio=Math.max(window.devicePixelRatio||1,1);
  const saved=drawn?canvas.toDataURL('image/png'):null;
  canvas.width=Math.round(rect.width*ratio);canvas.height=Math.round(160*ratio);
  context.setTransform(ratio,0,0,ratio,0,0);context.lineWidth=2.5;context.lineCap='round';context.lineJoin='round';context.strokeStyle='#16343d';
  if(saved){const image=new Image();image.onload=function(){context.drawImage(image,0,0,rect.width,160);};image.src=saved;}
 }
 function point(event){const rect=canvas.getBoundingClientRect();return{x:event.clientX-rect.left,y:event.clientY-rect.top};}
 canvas.addEventListener('pointerdown',function(event){event.preventDefault();canvas.setPointerCapture(event.pointerId);const p=point(event);context.beginPath();context.moveTo(p.x,p.y);context.lineTo(p.x+.1,p.y+.1);context.stroke();drawing=true;drawn=true;hint.textContent='Signature captured. You can clear it and sign again.';});
 canvas.addEventListener('pointermove',function(event){if(!drawing)return;const p=point(event);context.lineTo(p.x,p.y);context.stroke();});
 ['pointerup','pointercancel','lostpointercapture'].forEach(function(type){canvas.addEventListener(type,function(){drawing=false;});});
 form.querySelector('.clear-signature').addEventListener('click',function(){context.clearRect(0,0,canvas.width,canvas.height);drawn=false;field.value='';hint.textContent='Use your mouse, finger, or stylus to sign.';});
 form.addEventListener('submit',function(event){if(!drawn){event.preventDefault();hint.textContent='Please draw your signature before submitting.';return;}const exportCanvas=document.createElement('canvas');exportCanvas.width=1000;exportCanvas.height=260;const exportContext=exportCanvas.getContext('2d');exportContext.fillStyle='#ffffff';exportContext.fillRect(0,0,exportCanvas.width,exportCanvas.height);exportContext.drawImage(canvas,0,0,exportCanvas.width,exportCanvas.height);field.value=exportCanvas.toDataURL('image/jpeg',.88);});
 resize();
 window.addEventListener('resize',resize);
});
</script>
<?php foot();
