<?php require __DIR__.'/../includes/app.php';need();$p=q('SELECT p.*,i.invoice_number,i.customer_id,c.first_name,c.last_name,c.user_id FROM payments p JOIN invoices i USING(invoice_id) JOIN customers c ON c.customer_id=i.customer_id WHERE p.payment_id=?',[(int)($_GET['id']??0)])->fetch();
if(!$p||(user()['role']==='Customer'&&$p['user_id']!=user()['id'])||user()['role']==='Rental Officer'){http_response_code(404);exit('Not found');}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Payment receipt | CRMS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"><link href="<?=BASE?>assets/style.css" rel="stylesheet"></head><body>
<main class="receipt-shell"><section class="card"><div class="card-body p-4 p-md-5">
<div class="receipt-heading"><i class="bi bi-car-front-fill"></i><div><h1 class="h4 mb-1">Payment receipt</h1><div class="text-muted small">Car Rental Management System</div></div></div>
<hr class="my-4"><div class="table-responsive"><table class="table receipt-table"><tbody>
<tr><th scope="row">Receipt number</th><td><?=e($p['payment_number'])?></td></tr>
<tr><th scope="row">Date</th><td><?=e($p['payment_date'])?></td></tr>
<tr><th scope="row">Received from</th><td><?=e($p['first_name'].' '.$p['last_name'])?></td></tr>
<tr><th scope="row">Invoice</th><td><?=e($p['invoice_number'])?></td></tr>
<tr><th scope="row">Payment method</th><td><?=e($p['payment_method'])?></td></tr>
<?php if($p['transaction_reference']):?><tr><th scope="row">Reference</th><td><?=e($p['transaction_reference'])?></td></tr><?php endif;?>
<tr><th scope="row">Status</th><td><?=badge($p['status'])?></td></tr>
</tbody></table></div><hr><div class="receipt-total">Amount paid: <?=money($p['amount'])?></div>
<button type="button" onclick="window.print()" class="btn btn-primary btn-sm mt-4 noprint"><i class="bi bi-printer me-1"></i>Print receipt</button>
</div></section><footer class="receipt-footer">&copy; <?=date('Y')?> CRMS · Thank you for choosing us.</footer></main></body></html>
