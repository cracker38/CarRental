<?php
require __DIR__.'/../includes/app.php';
need('System Administrator','Business Manager','Rental Officer');
$can=user()['role']!=='Business Manager';

if($can&&$_SERVER['REQUEST_METHOD']==='POST'){
    chk();
    $id=(int)($_POST['id']??0);
    $action=$_POST['act']??'';
    $reservationActions=['confirm'=>'confirmed','reject'=>'rejected','cancel'=>'cancelled','noshow'=>'no_show'];

    if($action==='create_invoice'){
        $pdo=db();
        try{
            $pdo->beginTransaction();
            $invoice=create_reservation_invoice($id,(int)user()['id']);
            audit('create_invoice','invoice',$invoice['invoice_id'],'Staff created or issued invoice for submitted rental application');
            $pdo->commit();
            flash($invoice['created']?'Invoice created and issued. The customer can now submit payment.':'Invoice is already available for this application.');
        }catch(Throwable $x){
            if($pdo->inTransaction())$pdo->rollBack();
            flash($x->getMessage(),'danger');
        }
    }elseif(isset($reservationActions[$action])){
        $pdo=db();
        try{
            $pdo->beginTransaction();
            q('SELECT reservation_id FROM reservations WHERE reservation_id=? FOR UPDATE',[$id])->fetch();
            $status=$reservationActions[$action];
            q('UPDATE reservations SET status=?,confirmed_by=IF(?="confirmed",?,confirmed_by),confirmed_at=IF(?="confirmed",NOW(),confirmed_at),cancelled_at=IF(?="confirmed",NULL,NOW()),cancellation_reason=? WHERE reservation_id=?',[$status,$status,user()['id'],$status,$status,$_POST['reason']??null,$id]);
            if($action!=='confirm'){
                q("UPDATE rental_agreements SET status='terminated' WHERE reservation_id=? AND status<>'active'",[$id]);
                q("UPDATE invoices SET status='void' WHERE reservation_id=? AND status IN('issued','draft')",[$id]);
            }
            audit($action,'reservation',$id);
            $pdo->commit();
            flash('Reservation '.$status.'.');
        }catch(Throwable $x){
            if($pdo->inTransaction())$pdo->rollBack();
            flash($x->getMessage(),'danger');
        }
    }
    header('Location: reservations.php');
    exit;
}

$statusFilter=$_GET['s']??'';
$reservations=q('SELECT r.*,c.first_name,c.last_name,v.make,v.model,v.registration_no,a.status agreement_status,a.agreement_id,i.invoice_id,i.invoice_number,i.status invoice_status,(SELECT COUNT(*) FROM payments p JOIN invoices pi USING(invoice_id) WHERE pi.reservation_id=r.reservation_id) payment_count FROM reservations r JOIN customers c USING(customer_id) JOIN vehicles v USING(vehicle_id) LEFT JOIN rental_agreements a USING(reservation_id) LEFT JOIN invoices i ON i.invoice_id=(SELECT MAX(ii.invoice_id) FROM invoices ii WHERE ii.reservation_id=r.reservation_id)'.($statusFilter?' WHERE r.status=?':'').' ORDER BY r.created_at DESC',$statusFilter?[$statusFilter]:[])->fetchAll();
head('Reservations');?>
<div class="mb-3 d-flex gap-2 flex-wrap">
<?php foreach([''=>'All','pending'=>'Pending','confirmed'=>'Confirmed','completed'=>'Completed','cancelled'=>'Cancelled'] as $key=>$label):?>
<a class="btn btn-sm <?=$statusFilter===$key?'btn-primary':'btn-outline-primary'?>" href="?s=<?=e($key)?>"><?=e($label)?></a>
<?php endforeach;?>
</div>
<div class="card"><div class="card-body p-0"><div class="table-responsive">
<table class="table table-hover align-middle"><thead><tr>
<th scope="col">Application</th><th scope="col">Customer</th><th scope="col">Vehicle</th><th scope="col">Period</th><th scope="col">Total</th><th scope="col">Invoice</th><th scope="col">Agreement</th><th scope="col">Payments</th><th scope="col">Status</th><th scope="col">Actions</th>
</tr></thead><tbody>
<?php foreach($reservations as $reservation):?>
<tr>
<td><strong><?=e($reservation['reservation_code'])?></strong></td>
<td><?=e($reservation['first_name'].' '.$reservation['last_name'])?></td>
<td><?=e($reservation['make'].' '.$reservation['model'])?><small class="d-block text-muted"><?=e($reservation['registration_no'])?></small></td>
<td class="small text-nowrap"><?=e($reservation['pickup_datetime'])?><br><?=e($reservation['return_datetime'])?></td>
<td class="text-nowrap"><?=money($reservation['estimated_amount']+$reservation['tax_amount']-$reservation['discount_amount'])?></td>
<td><?php if($reservation['invoice_id']):?><strong><?=e($reservation['invoice_number'])?></strong><br><?=badge($reservation['invoice_status'])?><?php else:?>Not created<?php endif;?></td>
<td><?php if($reservation['agreement_id']):?><?=badge($reservation['agreement_status'])?> <a class="btn btn-sm btn-outline-primary" aria-label="Open agreement for <?=e($reservation['reservation_code'])?>" href="<?=BASE?>shared/agreement.php?id=<?=$reservation['agreement_id']?>"><i class="bi bi-eye"></i></a><?php else:?>—<?php endif;?></td>
<td><?=e($reservation['payment_count'])?></td>
<td><?=badge($reservation['status'])?></td>
<td class="text-nowrap">
<?php if($can&&(!$reservation['invoice_id']||$reservation['invoice_status']==='draft')&&in_array($reservation['status'],['pending','confirmed'],true)):?>
<form method="post" class="d-inline"><?=csrf_f()?><input type="hidden" name="id" value="<?=$reservation['reservation_id']?>"><button name="act" value="create_invoice" class="btn btn-sm btn-primary">Create invoice</button></form>
<?php endif;?>
<?php if($can&&$reservation['status']==='pending'):?>
<form method="post" class="d-inline"><?=csrf_f()?><input type="hidden" name="id" value="<?=$reservation['reservation_id']?>"><button name="act" value="confirm" class="btn btn-sm btn-success">Confirm</button><button name="act" value="reject" class="btn btn-sm btn-outline-danger">Reject</button></form>
<?php elseif($can&&$reservation['status']==='confirmed'&&!q('SELECT 1 FROM rentals WHERE reservation_id=?',[$reservation['reservation_id']])->fetch()):?>
<form method="post" class="d-inline"><?=csrf_f()?><input type="hidden" name="id" value="<?=$reservation['reservation_id']?>"><button name="act" value="cancel" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Cancel this reservation?')">Cancel</button></form>
<?php endif;?>
</td></tr>
<?php endforeach;?>
<?php if(!$reservations):?><tr><td colspan="10" class="text-center text-muted py-4">No rental applications found.</td></tr><?php endif;?>
</tbody></table></div></div></div><?php foot();
