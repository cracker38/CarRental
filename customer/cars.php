<?php require __DIR__.'/../includes/app.php';need('Customer');head('Rent a car');
$cats=q('SELECT * FROM vehicle_categories WHERE is_active=1')->fetchAll();$cf=(int)($_GET['cat']??0);
$v=q("SELECT v.*,c.category_name,(SELECT image_path FROM vehicle_images WHERE vehicle_id=v.vehicle_id ORDER BY is_primary DESC LIMIT 1) img FROM vehicles v JOIN vehicle_categories c USING(category_id) WHERE v.status='available'".($cf?' AND v.category_id='.$cf:'').' ORDER BY v.daily_rate')->fetchAll();?>
<form class="mb-3 d-flex gap-2"><select name="cat" class="form-select w-auto" onchange="this.form.submit()"><option value="0">All categories</option><?php foreach($cats as $c)echo '<option value="'.$c['category_id'].'"'.($cf==$c['category_id']?' selected':'').'>'.e($c['category_name']).'</option>';?></select></form>
<div class="row g-3"><?php foreach($v as $x):?><div class="col-md-6 col-xl-4"><div class="card h-100">
<div class="bg-light text-center py-4" style="color:var(--ink)"><?php if($x['img'])echo '<img src="'.BASE.e($x['img']).'" style="max-height:140px" class="img-fluid">';else echo '<i class="bi bi-car-front-fill" style="font-size:4rem"></i>';?></div>
<div class="card-body"><h5><?=e($x['make'].' '.$x['model'])?> <small class="text-muted"><?=$x['manufacture_year']?></small></h5>
<p class="small text-muted mb-2"><?=e($x['category_name'])?> · <?=ucfirst($x['transmission'])?> · <?=ucfirst($x['fuel_type'])?> · <?=$x['passenger_capacity']?> seats</p>
<div class="d-flex justify-content-between align-items-center"><b><?=money($x['daily_rate'])?> <small class="text-muted">/ day</small></b><a class="btn btn-accent btn-sm" href="rent.php?v=<?=$x['vehicle_id']?>">Rent this car</a></div></div></div></div>
<?php endforeach;if(!$v)echo '<p class="text-muted">No vehicles are available in this category right now.</p>';?></div><?php foot();
