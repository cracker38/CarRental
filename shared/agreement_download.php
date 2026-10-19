<?php
require __DIR__.'/../includes/app.php';
need();
if(user()['role']!=='Customer'&&!in_array(user()['role'],['System Administrator','Business Manager','Rental Officer'],true)){http_response_code(403);exit('Access denied');}
ensure_agreement_signature_schema();
$id=(int)($_GET['id']??0);
$a=q('SELECT a.*,r.reservation_code,r.pickup_datetime,r.return_datetime,r.pickup_location,r.return_location,r.estimated_days,r.estimated_amount,r.tax_amount,r.discount_amount,c.user_id,c.first_name,c.last_name,c.phone,c.email,c.identification_type,c.identification_number,v.make,v.model,v.registration_no FROM rental_agreements a JOIN reservations r USING(reservation_id) JOIN customers c ON c.customer_id=a.customer_id JOIN vehicles v ON v.vehicle_id=a.vehicle_id WHERE a.agreement_id=?',[$id])->fetch();
if(!$a||(user()['role']==='Customer'&&(int)$a['user_id']!==(int)user()['id'])){http_response_code(404);exit('Not found');}
if(empty($a['renter_signed_at'])||empty($a['staff_signed_at'])){http_response_code(409);exit('The agreement must be signed by both parties before it can be downloaded.');}

function agreement_pdf_safe_text($value){
 $text=(string)$value;
 if(function_exists('iconv')){
  $converted=iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$text);
  if($converted!==false)$text=$converted;
 }
 return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/',' ',$text);
}
function agreement_pdf_literal($value){
 return str_replace(["\\","(",")"],["\\\\","\\(","\\)"],agreement_pdf_safe_text($value));
}
function agreement_pdf_safe_literal($value){
 return str_replace(["\\","(",")"],["\\\\","\\(","\\)"],(string)$value);
}
function agreement_pdf_signature($value){
 if($value===null||$value==='')return null;
 $signature=validate_agreement_signature($value);
 return $signature;
}

$signatures=[
 'renter'=>agreement_pdf_signature($a['renter_signature_image']),
 'staff'=>agreement_pdf_signature($a['staff_signature_image'])
];
$pdfPages=[['content'=>'','images'=>[]]];
$pageIndex=0;
$y=48;
$newPage=function()use(&$pdfPages,&$pageIndex,&$y){
 $pdfPages[]=['content'=>'','images'=>[]];
 $pageIndex=count($pdfPages)-1;
 $y=48;
};
$put=function($value,$x,$top,$size=10,$bold=false,$alreadySafe=false)use(&$pdfPages,&$pageIndex){
 $font=$bold?'F2':'F1';
 $baseline=842-$top-$size;
 $literal=$alreadySafe?agreement_pdf_safe_literal($value):agreement_pdf_literal($value);
 $pdfPages[$pageIndex]['content'].='BT /'.$font.' '.$size.' Tf '.$x.' '.$baseline.' Td ('.$literal.") Tj ET\n";
};
$write=function($value,$size=10,$x=50,$bold=false)use(&$y,&$put,&$newPage){
 $available=max(24,(int)((545-$x)/($size*.52)));
 $text=agreement_pdf_safe_text((string)$value);
 $paragraphs=preg_split('/\R/',$text);
 foreach($paragraphs as $paragraph){
  $wrapped=wordwrap($paragraph,$available,"\n",true);
  foreach(explode("\n",$wrapped) as $line){
   if($y+($size+5)>790)$newPage();
   $put($line,$x,$y,$size,$bold,true);
   $y+=$size+5;
  }
 }
};
$ensure=function($height)use(&$y,$newPage){
 if($y+$height>790)$newPage();
};
$rule=function()use(&$pdfPages,&$pageIndex,&$y){
 $pdfPages[$pageIndex]['content'].='0.78 0.83 0.85 RG 0.7 w 50 '.(842-$y).' m 545 '.(842-$y).' l S'."\n";
 $y+=12;
};
$section=function($title)use(&$write,&$rule){
 $write($title,13,50,true);
 $rule();
};
$field=function($label,$value)use(&$write){
 $write($label,9,50,true);
 $write($value!==''&&$value!==null?$value:'Not provided',10,50,false);
};

$put('CRMS | CAR RENTAL MANAGEMENT',165,45,9,true);
$put('VEHICLE RENTAL AGREEMENT',136,68,18,true);
$put('Agreement '.(string)$a['agreement_number'].'   |   Reservation '.(string)$a['reservation_code'],120,98,10,false);
$y=126;
$rule();
$section('Renter and vehicle');
$field('Renter',trim($a['first_name'].' '.$a['last_name']));
$field('Identification',ucfirst(str_replace('_',' ',$a['identification_type']??'Not provided')).' '.($a['identification_number']??''));
$field('Contact',($a['phone']?:'').'   '.$a['email']);
$field('Vehicle',$a['make'].' '.$a['model'].' | '.$a['registration_no']);
$field('Rental period',$a['pickup_datetime'].' at '.$a['pickup_location'].' to '.$a['return_datetime'].' at '.$a['return_location'].' ('.($a['estimated_days']+0).' days)');
$section('Charges');
$field('Agreed daily rate',money($a['agreed_daily_rate']));
$field('Rental',money($a['estimated_amount']));
if((float)$a['discount_amount']>0)$field('Discount','-'.money($a['discount_amount']));
$field('Total invoice',money(max(0,(float)$a['estimated_amount']-(float)$a['discount_amount'])));
$field('Refundable security deposit',money($a['security_deposit']));
$section('Terms and conditions');
$write($a['terms_and_conditions']?:'No additional terms and conditions were recorded.',10,50,false);

if($y+185>790)$newPage();
$section('Signatures');
$boxTop=$y;
$boxHeight=132;
$pdfPages[$pageIndex]['content'].='0.78 0.83 0.85 RG 0.7 w 50 '.(842-$boxTop-$boxHeight).' 238 '.$boxHeight.' re S 307 '.(842-$boxTop-$boxHeight).' 238 '.$boxHeight.' re S'."\n";
$put('RENTER SIGNATURE',62,$boxTop+12,8,true);
$put('AUTHORIZED RENTAL REPRESENTATIVE',319,$boxTop+12,8,true);
$imageKeys=[];
foreach(['renter','staff'] as $index=>$party){
 $signature=$signatures[$party];
 $x=$index===0?62:319;
 if($signature){
  $imageKey='Signature'.($index+1);
  $imageKeys[$imageKey]=$signature;
  $imageWidth=190;
  $imageHeight=min(45,$imageWidth*$signature['height']/$signature['width']);
  $bottom=842-($boxTop+31+$imageHeight);
  $pdfPages[$pageIndex]['content'].='q '.$imageWidth.' 0 0 '.$imageHeight.' '.$x.' '.$bottom.' cm /'.$imageKey.' Do Q'."\n";
  $pdfPages[$pageIndex]['images'][$imageKey]=true;
 }
}
$put($a['renter_signed_name'],62,$boxTop+88,9,true);
$put('Signed electronically: '.$a['renter_signed_at'],62,$boxTop+105,8,false);
$put($a['staff_signed_name'],319,$boxTop+88,9,true);
$put('Countersigned electronically: '.$a['staff_signed_at'],319,$boxTop+105,8,false);
$y=$boxTop+$boxHeight+14;
$write('Both parties completed this agreement electronically. These signatures are visual acknowledgments and are not cryptographic or identity-verified digital signatures.',8,50,false);

$objects=[1=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',2=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>'];
$nextObject=3;
$imageObjectIds=[];
foreach($imageKeys as $imageKey=>$signature){
 $imageObjectIds[$imageKey]=$nextObject;
 $bytes=$signature['bytes'];
 $objects[$nextObject]='<< /Type /XObject /Subtype /Image /Width '.$signature['width'].' /Height '.$signature['height'].' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($bytes)." >>\nstream\n".$bytes."\nendstream";
 $nextObject++;
}
$pageObjectIds=[];
$contentObjectIds=[];
foreach($pdfPages as $index=>$page){
 $contentObjectIds[$index]=$nextObject++;
 $pageObjectIds[$index]=$nextObject++;
}
$pagesTreeObjectId=$nextObject++;
$catalogObjectId=$nextObject++;
foreach($pdfPages as $index=>$page){
 $content=$page['content'];
 $objects[$contentObjectIds[$index]]='<< /Length '.strlen($content)." >>\nstream\n".$content."endstream";
 $resources='/Font << /F1 1 0 R /F2 2 0 R >>';
 if($page['images']){
  $xobjects=[];
  foreach(array_keys($page['images']) as $imageKey)$xobjects[]='/'.$imageKey.' '.$imageObjectIds[$imageKey].' 0 R';
  $resources.=' /XObject << '.implode(' ',$xobjects).' >>';
 }
 $objects[$pageObjectIds[$index]]='<< /Type /Page /Parent '.$pagesTreeObjectId.' 0 R /MediaBox [0 0 595 842] /Resources << '.$resources.' >> /Contents '.$contentObjectIds[$index].' 0 R >>';
}
$pageReferences=[];
foreach($pageObjectIds as $pageObjectId)$pageReferences[]=$pageObjectId.' 0 R';
$objects[$pagesTreeObjectId]='<< /Type /Pages /Kids ['.implode(' ',$pageReferences).'] /Count '.count($pageObjectIds).' >>';
$objects[$catalogObjectId]='<< /Type /Catalog /Pages '.$pagesTreeObjectId.' 0 R >>';
ksort($objects);
$pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
$offsets=[0];
foreach($objects as $objectId=>$object){
 $offsets[$objectId]=strlen($pdf);
 $pdf.=$objectId." 0 obj\n".$object."\nendobj\n";
}
$xrefOffset=strlen($pdf);
$count=max(array_keys($objects))+1;
$pdf.="xref\n0 ".$count."\n0000000000 65535 f \n";
for($objectId=1;$objectId<$count;$objectId++)$pdf.=sprintf("%010d 00000 n \n",$offsets[$objectId]??0);
$pdf.="trailer\n<< /Size ".$count." /Root ".$catalogObjectId." 0 R >>\nstartxref\n".$xrefOffset."\n%%EOF";
$filename=preg_replace('/[^A-Za-z0-9_-]/','-',(string)$a['agreement_number']);
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="rental-agreement-'.$filename.'.pdf"');
header('Content-Length: '.strlen($pdf));
header('Cache-Control: private, no-store');
echo $pdf;
