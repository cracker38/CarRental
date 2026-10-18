<?php require __DIR__.'/../includes/app.php';need('Business Manager');head('Business dashboard');tiles([['reports.php','Reports','bar-chart-line'],['reservations.php','Reservations','calendar-check'],['rentals.php','Rentals','key'],['finance.php?tab=inv','Invoices','receipt'],['vehicles.php','Vehicles','car-front'],['maintenance.php','Maintenance','tools']]);
$rev=one("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status IN('completed','partially_refunded')");$tot=max(1,one("SELECT COUNT(*) FROM vehicles WHERE status<>'inactive'"));$out=one("SELECT COUNT(*) FROM vehicles WHERE status='rented'");
echo '<div class="row g-3 mb-4">';
kpi('Revenue collected',money($rev),'graph-up','reports.php');
kpi('Revenue this month',money(one("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status IN('completed','partially_refunded') AND DATE_FORMAT(payment_date,'%Y%m')=DATE_FORMAT(NOW(),'%Y%m')")),'calendar3');
kpi('Outstanding',money(one("SELECT COALESCE(SUM(outstanding_balance),0) FROM vw_invoice_balances WHERE status NOT IN('void','draft')")),'receipt','finance.php?tab=inv');
kpi('Fleet utilisation',round($out/$tot*100).'%','speedometer');
kpi('Active rentals',one("SELECT COUNT(*) FROM rentals WHERE status IN('active','overdue')"),'key','rentals.php');
kpi('Overdue rentals',one("SELECT COUNT(*) FROM rentals WHERE status IN('active','overdue') AND expected_return_datetime<NOW()"),'exclamation-triangle');
kpi('Pending reservations',one("SELECT COUNT(*) FROM reservations WHERE status='pending'"),'hourglass-split','reservations.php?s=pending');
kpi('Maintenance spend',money(one("SELECT COALESCE(SUM(cost),0) FROM maintenance_records WHERE status<>'cancelled'")),'tools');
echo '</div><div class="row g-4"><div class="col-lg-7"><div class="card h-100"><div class="card-header">Revenue, last 6 months</div><div class="card-body"><canvas id="ch" height="120"></canvas></div></div></div><div class="col-lg-5">';
panel('Top customers',['Customer','Rentals','Billed'],array_map(fn($x)=>[e($x['first_name'].' '.$x['last_name']),$x['n'],money($x['s'])],q("SELECT c.first_name,c.last_name,COUNT(*) n,SUM(r.final_amount) s FROM rentals r JOIN customers c USING(customer_id) GROUP BY c.customer_id ORDER BY s DESC LIMIT 5")->fetchAll()),'reports.php','No rentals yet.');
echo '</div></div>';$m=array_reverse(q("SELECT DATE_FORMAT(payment_date,'%Y-%m') m,SUM(amount) a FROM payments WHERE status IN('completed','partially_refunded') GROUP BY m ORDER BY m DESC LIMIT 6")->fetchAll());?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script><script>new Chart(ch,{type:'bar',data:{labels:<?=json_encode(array_column($m,'m'))?>,datasets:[{data:<?=json_encode(array_map('floatval',array_column($m,'a')))?>,backgroundColor:'#d9642b'}]},options:{plugins:{legend:{display:false}}}})</script><?php foot();
