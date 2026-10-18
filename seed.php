<?php // Run once in browser: http://localhost/crms/seed.php  then DELETE this file.
require __DIR__.'/includes/app.php';
$users=[['System Administrator','Admin','User','admin@crms.rw'],['Business Manager','Mary','Manager','manager@crms.rw'],['Rental Officer','Olivier','Officer','officer@crms.rw'],['Accountant/Cashier','Alice','Cashier','cashier@crms.rw'],['Customer','Jean','Customer','customer@crms.rw']];
foreach($users as [$r,$f,$l,$em]){
 $account=q('SELECT u.user_id,u.first_name,u.last_name,u.email,u.phone,ro.role_name FROM users u JOIN roles ro USING(role_id) WHERE u.email=?',[$em])->fetch();
 if(!$account){$rid=q('SELECT role_id FROM roles WHERE role_name=?',[$r])->fetchColumn();
  q('INSERT INTO users(role_id,first_name,last_name,email,phone,password_hash) VALUES(?,?,?,?,?,?)',[$rid,$f,$l,$em,'0788000000',password_hash('Pass@12345',PASSWORD_DEFAULT)]);
  $account=['user_id'=>db()->lastInsertId(),'first_name'=>$f,'last_name'=>$l,'email'=>$em,'phone'=>'0788000000','role_name'=>$r];}
 if($account['role_name']!=='Customer')continue;
 $profile=q('SELECT customer_id FROM customers WHERE user_id=?',[$account['user_id']])->fetchColumn();
 if(!$profile&&$account['email']){$profile=q('SELECT customer_id FROM customers WHERE user_id IS NULL AND email=? AND first_name=? AND last_name=? ORDER BY customer_id LIMIT 1',[$account['email'],$account['first_name'],$account['last_name']])->fetchColumn();
  if($profile)q('UPDATE customers SET user_id=? WHERE customer_id=? AND user_id IS NULL',[$account['user_id'],$profile]);}
 if(!$profile)q('INSERT INTO customers(user_id,customer_code,first_name,last_name,phone,email) VALUES(?,?,?,?,?,?)',[$account['user_id'],code('CUS'),$account['first_name'],$account['last_name'],(string)$account['phone'],$account['email']]);
}
if(!q('SELECT 1 FROM vehicles')->fetch()){
 foreach([['Economy','RAD 101 A','Toyota','Vitz',2018],['Sedan','RAE 202 B','Toyota','Corolla',2019],['SUV','RAF 303 C','Toyota','RAV4',2020],['Van','RAG 404 D','Toyota','Hiace',2019],['Luxury','RAH 505 E','Mercedes','E-Class',2021]] as [$c,$reg,$mk,$md,$y]){
 $id=q('SELECT category_id FROM vehicle_categories WHERE category_name=?',[$c])->fetchColumn();$rate=q('SELECT default_daily_rate FROM vehicle_categories WHERE category_id=?',[$id])->fetchColumn();
 q("INSERT INTO vehicles(category_id,registration_no,make,model,manufacture_year,transmission,daily_rate,passenger_capacity,current_mileage,fuel_level_percent) VALUES(?,?,?,?,?,'automatic',?,5,15000,80)",[$id,$reg,$mk,$md,$y,$rate]);}}
if(!q('SELECT 1 FROM drivers')->fetch())q("INSERT INTO drivers(driver_code,first_name,last_name,phone,license_number,license_expiry_date) VALUES('DRV001','Eric','Habimana','0788111222','DL-001','2028-12-31')");
echo 'Seeded. Logins use password Pass@12345: admin@/manager@/officer@/cashier@/customer@crms.rw. DELETE seed.php now.';
