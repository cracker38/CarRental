<?php
require __DIR__.'/../includes/app.php';
need();
if(user()['role']!=='Customer'&&!in_array(user()['role'],['System Administrator','Business Manager','Rental Officer'],true)){http_response_code(403);exit('Access denied');}
ensure_agreement_signature_schema();
$id=(int)($_GET['id']??0);
$a=q('SELECT a.*,r.reservation_code,r.pickup_datetime,r.return_datetime,r.pickup_location,r.return_location,r.estimated_days,r.estimated_amount,r.tax_amount,r.discount_amount,c.user_id,c.first_name,c.last_name,c.phone,c.email,c.identification_type,c.identification_number,c.driving_license_number,v.make,v.model,v.registration_no FROM rental_agreements a JOIN reservations r USING(reservation_id) JOIN customers c ON c.customer_id=a.customer_id JOIN vehicles v ON v.vehicle_id=a.vehicle_id WHERE a.agreement_id=?',[$id])->fetch();
if(!$a||(user()['role']==='Customer'&&(int)$a['user_id']!==(int)user()['id'])){http_response_code(404);exit('Not found');}
if(empty($a['renter_signed_at'])||empty($a['staff_signed_at'])){http_response_code(409);exit('The agreement must be signed by both parties before it can be downloaded.');}
$filename=preg_replace('/[^A-Za-z0-9_-]/','-',(string)$a['agreement_number']);
$fullName=trim($a['first_name'].' '.$a['last_name']);
$total=(float)$a['estimated_amount']+(float)$a['tax_amount']-(float)$a['discount_amount'];
header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: attachment; filename="rental-agreement-'.$filename.'.html"');
header('Cache-Control: private, no-store');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><title>Rental agreement <?=e($a['agreement_number'])?></title>
<style>
body{margin:0;padding:36px;background:#f3f6f8;color:#26383e;font:15px/1.6 "Segoe UI",Arial,sans-serif}
main{max-width:850px;margin:0 auto;padding:42px;background:#fff;border:1px solid #dfe7ea;border-radius:12px}
header{text-align:center;border-bottom:2px solid #c96335;padding-bottom:20px;margin-bottom:24px}
h1{margin:5px 0;color:#16343d;font-size:25px}h2{margin-top:28px;color:#16343d;font-size:18px}
.brand{color:#c96335;font-weight:bold;letter-spacing:2px}
.meta{color:#63777e}.terms{white-space:pre-line}
table{width:100%;border-collapse:collapse;margin:18px 0}
th,td{padding:10px 12px;border-bottom:1px solid #e3e9ed;text-align:left}
th{width:30%;background:#f3f7f8;color:#405961}
.amount{text-align:right}.total{font-weight:bold;color:#16343d}
.signatures{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:30px}
.signature{min-height:110px;padding:16px;border:1px solid #dfe7ea;border-radius:8px}
.signature label{display:block;color:#718087;font-size:12px;font-weight:bold;text-transform:uppercase}
.signature strong{display:block;margin-top:14px;color:#16343d}
.signed{color:#24724f;font-size:13px}
footer{margin-top:30px;border-top:1px solid #e3e9ed;padding-top:12px;color:#718087;font-size:12px;text-align:center}
@media print{body{padding:0;background:#fff}main{max-width:none;border:0;border-radius:0;padding:0}}
@media(max-width:600px){body{padding:12px}main{padding:20px}.signatures{grid-template-columns:1fr}}
</style></head><body><main>
<header><div class="brand">CRMS · CAR RENTAL MANAGEMENT</div><h1>Vehicle Rental Agreement</h1><div class="meta">Agreement <?=e($a['agreement_number'])?> · Reservation <?=e($a['reservation_code'])?></div></header>
<table><tbody>
<tr><th>Renter</th><td><?=e($fullName)?></td></tr>
<tr><th>Identification</th><td><?=e(ucfirst(str_replace('_',' ',$a['identification_type']??'Not provided')).' '.($a['identification_number']??''))?></td></tr>
<tr><th>Driving licence</th><td><?=e($a['driving_license_number']?:'Not provided')?></td></tr>
<tr><th>Contact</th><td><?=e($a['phone'])?> · <?=e($a['email']??'')?></td></tr>
<tr><th>Vehicle</th><td><?=e($a['make'].' '.$a['model'].' · '.$a['registration_no'])?></td></tr>
<tr><th>Rental period</th><td><?=e($a['pickup_datetime'])?> at <?=e($a['pickup_location'])?> to <?=e($a['return_datetime'])?> at <?=e($a['return_location'])?> (<?=e($a['estimated_days']+0)?> days)</td></tr>
</tbody></table>
<table><thead><tr><th>Charge</th><th class="amount">Amount</th></tr></thead><tbody>
<tr><td>Daily rate</td><td class="amount"><?=money($a['agreed_daily_rate'])?></td></tr>
<tr><td>Rental</td><td class="amount"><?=money($a['estimated_amount'])?></td></tr>
<tr><td>VAT</td><td class="amount"><?=money($a['tax_amount'])?></td></tr>
<?php if((float)$a['discount_amount']>0):?><tr><td>Discount</td><td class="amount">-<?=money($a['discount_amount'])?></td></tr><?php endif;?>
<tr class="total"><td>Total invoice</td><td class="amount"><?=money($total)?></td></tr>
<tr><td>Refundable security deposit</td><td class="amount"><?=money($a['security_deposit'])?></td></tr>
</tbody></table>
<h2>Terms and conditions</h2><div class="terms"><?=e($a['terms_and_conditions'])?></div>
<section class="signatures"><div class="signature"><label>Renter signature</label><strong><?=e($a['renter_signed_name'])?></strong><div class="signed">Electronically signed <?=e($a['renter_signed_at'])?></div></div>
<div class="signature"><label>Authorized rental representative</label><strong><?=e($a['staff_signed_name'])?></strong><div class="signed">Countersigned electronically <?=e($a['staff_signed_at'])?></div></div></section>
<footer>Completed and signed by both parties · CRMS · <?=date('Y')?><br>Electronic signatures are typed-name acknowledgments, not cryptographic or identity-verified digital signatures.</footer>
</main></body></html>
