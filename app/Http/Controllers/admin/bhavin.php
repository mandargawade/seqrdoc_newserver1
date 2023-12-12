<?php
public function dbUploadfile(){
        $template_data = TemplateMaster::select('id','template_name')->where('id',1)->first()->toArray();
        // dd($template_data);
        //$fetch_degree_data = Degree::select('*')->take(10)->get()->toArray();
        
        //Original by Mandar
        //$fetch_degree_data_prog_spec = DB::select( DB::raw('SELECT DISTINCT CONCAT_WS(" ",TRIM(Programme_Name_E),TRIM(Specialization_E)) as prog_spec FROM gu_dc_2 WHERE status="0" LIMIT 18,5') );

        //Test by Bhavin
        //$fetch_degree_data_prog_spec = DB::select( DB::raw('SELECT DISTINCT CONCAT_WS(" ",TRIM(Programme_Name_E),TRIM(Specialization_E)) as prog_spec FROM gu_dc_2 WHERE status="0" and CONCAT_WS(" ",TRIM(Programme_Name_E),TRIM(Specialization_E)) = "MASTER OF BUSINESS ADMINISTRATION"') );

        //Test by Bhavin
        $fetch_degree_data_prog_spec = DB::select( DB::raw('SELECT DISTINCT CONCAT_WS(" ",TRIM(Programme_Name_E),TRIM(Specialization_E)) AS prog_spec, COUNT(guid) FROM gu_dc_3 WHERE STATUS="0" GROUP BY prog_spec ORDER BY COUNT(guid) ASC limit 0,8') );
        

      //  $fetch_degree_data = collect($fetch_degree_data)->map(function($x){ return (array) $x; })->toArray();
       //$fetch_degree_data_prog_spec = DB::select( DB::raw('SELECT DISTINCT CONCAT_WS(" ",TRIM(Programme_Name_E),TRIM(Specialization_E)) as prog_spec FROM gu_dc_2 WHERE Programme_Name_E ="BACHELOR OF SCIENCE"AND Specialization_E="Nursing" LIMIT 1') );
         /* $fetch_degree_data_prog_spec = DB::select( DB::raw('SELECT DISTINCT CONCAT_WS(" ",TRIM(Programme_Name_E),TRIM(Specialization_E)) as prog_spec FROM gu_dc') );*/
      /* $fetch_degree_data_prog_spec = DB::select( DB::raw('SELECT DISTINCT CONCAT_WS(" ",TRIM(Programme_Name_E),TRIM(Specialization_E)) as prog_spec FROM gu_dc_2 WHERE Programme_Name_E ="BACHELOR OF SCIENCE"AND Specialization_E="Nursing" LIMIT 1') );*/
      // print_r($fetch_degree_data_prog_spec);
//exit;
        foreach ($fetch_degree_data_prog_spec as $value) {
           $progSpec =$value->prog_spec;
            //$fetch_degree_data = (array)DB::select( DB::raw('SELECT * FROM gu_dc WHERE CONCAT_WS(" ",TRIM(Programme_Name_E),TRIM(Specialization_E)) ="'.$value->prog_spec.'"'));
             $fetch_degree_data = (array)DB::select( DB::raw('SELECT * FROM gu_dc_3 WHERE CONCAT_WS(" ",TRIM(Programme_Name_E),TRIM(Specialization_E)) ="'.$value->prog_spec.'" AND status="0"'));
            $fetch_degree_data = collect($fetch_degree_data)->map(function($x){ return (array) $x; })->toArray();
          // print_r($fetch_degree_data);
          /*  echo 'SELECT * FROM gu_dc_2 WHERE CONCAT_WS(" ",TRIM(Programme_Name_E),TRIM(Specialization_E)) ="'.$value->prog_spec.'" AND status="0"';*/
            //  print_r($fetch_degree_data);
            //   exit;
           // $fetch_degree_data1 = Degree::select('*')->where('Programme_Name_E','BACHELOR OF ARCHITECTURE')->get()->toArray();
          //  print_r($fetch_degree_data1);
          // exit;
        
   
       // $fetch_degree_data = Degree::select('*')->where('status','0')->get()->toArray();
        // dd($fetch_degree_data);
        // $fetch_degree_array[] = array_values($fetch_degree_data[0]);
            $fetch_degree_array=array();
        // dd($fetch_degree_data);
        $admin_id = \Auth::guard('admin')->user()->toArray();
        // dd($fetch_degree_data);
        // $fetch_degree_array[] = array_values($fetch_degree_data[0]);
        foreach ($fetch_degree_data as $key => $value) {
            $fetch_degree_array[$key] = array_values($fetch_degree_data[$key]);
        }
        // dd($fetch_degree_array);
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $systemConfig = SystemConfig::select('sandboxing','printer_name')->where('site_id',$auth_site_id)->first();
        // dd($systemConfig);
        $printer_name = $systemConfig['printer_name'];

        $ghostImgArr = array();
        //set fonts
        $arial = TCPDF_FONTS::addTTFfont(public_path().'/'.$subdomain[0].'/backend/canvas/fonts/Arial.TTF', 'TrueTypeUnicode', '', 96);
        $arialb = TCPDF_FONTS::addTTFfont(public_path().'/'.$subdomain[0].'/backend/canvas/fonts/Arial Bold.TTF', 'TrueTypeUnicode', '', 96);

        $krutidev100 = TCPDF_FONTS::addTTFfont(public_path().'/'.$subdomain[0].'/backend/canvas/fonts/K100.TTF', 'TrueTypeUnicode', '', 96); 
        $krutidev101 = TCPDF_FONTS::addTTFfont(public_path().'/'.$subdomain[0].'/backend/canvas/fonts/K101.TTF', 'TrueTypeUnicode', '', 96);
        $HindiDegreeBold = TCPDF_FONTS::addTTFfont(public_path().'/'.$subdomain[0].'/backend/canvas/fonts/KRUTI_DEV_100__BOLD.TTF', 'TrueTypeUnicode', '', 96); 
        $arialNarrowB = TCPDF_FONTS::addTTFfont(public_path().'/'.$subdomain[0].'/backend/canvas/fonts/ARIALNB.TTF', 'TrueTypeUnicode', '', 96);
        $timesNewRomanBI = TCPDF_FONTS::addTTFfont(public_path().'/'.$subdomain[0].'/backend/canvas/fonts/Times-New-Roman-Bold-Italic.TTF', 'TrueTypeUnicode', '', 96);
        $timesNewRomanI = TCPDF_FONTS::addTTFfont(public_path().'/'.$subdomain[0].'/backend/canvas/fonts/Times New Roman_I.TTF', 'TrueTypeUnicode', '', 96);
        // dd(count($fetch_degree_array));

        $pdfBig = new TCPDF('P', 'mm', array('210', '297'), true, 'UTF-8', false);

        $pdfBig->setPrintHeader(false);
        $pdfBig->setPrintFooter(false);
        $pdfBig->SetAutoPageBreak(false, 0);
      //  $pdfBig->SetProtection(array('modify', 'copy','annot-forms','fill-forms','extract','assemble'),'',null,0,null);

        $log_serial_no = 1;


        for($excel_row = 0; $excel_row < count($fetch_degree_array); $excel_row++)
        {   

            //profile photo
                $extension = '';
                if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][2].'.jpg.jpg')){
                    $extension = '.jpg.jpg';
                  
                }else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][2].'.jpeg.jpg')){
                    $extension = '.jpeg.jpg';

                }else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][2].'.png.jpg')){
                    $extension = '.png.jpg';
                }else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][2].'.jpg')){
                    $extension = '.jpg';
                }
                else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][2].'.jpeg')){
                    $extension = '.jpeg';
                }else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][2].'.png')){
                    $extension = '.png';
                }
        
                if(!empty($extension)){
                   $profile_path = public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][2].$extension;
                }else{
                     if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][1].'.jpg.jpg')){
                        $extension = '.jpg.jpg';
                    }else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][1].'.jpeg.jpg')){
                        $extension = '.jpeg.jpg';
                    }else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][1].'.png.jpg')){
                        $extension = '.png.jpg';
                    }else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][1].'.jpg')){
                        $extension = '.jpg';
                    }
                    else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][1].'.jpeg')){
                        $extension = '.jpeg';
                    }else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][1].'.png')){
                        $extension = '.png';
                    }

                    if(!empty($extension)){
                    $profile_path = public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][1].$extension;
                    }else{
                     $profile_path ='';  
                    }
                }
            if(!empty($profile_path)){

            \File::copy(public_path().'/'.$subdomain[0].'/galgotia_cutomImages/CE_Sign.png',public_path().'/'.$subdomain[0].'/galgotia_cutomImages/CE_Sign_'.$excel_row.'.png');

            \File::copy(public_path().'/'.$subdomain[0].'/galgotia_cutomImages/VC_Sign.png',public_path().'/'.$subdomain[0].'/galgotia_cutomImages/VC_Sign_'.$excel_row.'.png');
            
            // dd($Orientation);
            $pdf = new TCPDF('P', 'mm', array('210', '297'), true, 'UTF-8', false);

            $pdf->SetCreator('TCPDF');
            $pdf->SetAuthor('TCPDF');
            $pdf->SetTitle('Certificate');
            $pdf->SetSubject('');

            // remove default header/footer
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetAutoPageBreak(false, 0);
            $pdf->AddSpotColor('Spot Red', 30, 100, 90, 10);        // For Invisible
            $pdf->AddSpotColor('Spot Dark Green', 100, 50, 80, 45);


            $pdfBig->AddSpotColor('Spot Red', 30, 100, 90, 10);        // For Invisible
            $pdfBig->AddSpotColor('Spot Dark Green', 100, 50, 80, 45); // clear text on bottom red and in clear text logo

            $pdf->AddPage();
            // dd($fetch_degree_array[$excel_row]);
            if($fetch_degree_array[$excel_row][10] == 'DB'){
                $template_img_generate = public_path().'/'.$subdomain[0].'/backend/canvas/bg_images/DB_Light Background.jpg';
            }
            else if($fetch_degree_array[$excel_row][10] == 'DIP'){
                $template_img_generate = public_path().'/'.$subdomain[0].'/backend/canvas/bg_images/DIP_Background_lite.jpg';
            }
            else if($fetch_degree_array[$excel_row][10] == 'INT'){
                $template_img_generate = public_path().'/'.$subdomain[0].'/backend/canvas/bg_images/INT_lite background.jpg';
            }
            else if($fetch_degree_array[$excel_row][10] == 'DO'){
                 $template_img_generate = public_path().'/'.$subdomain[0].'/backend/canvas/bg_images/DO_Background Lite.jpg';   
            } else if($fetch_degree_array[$excel_row][10] == 'NU'){
                $template_img_generate = public_path().'/'.$subdomain[0].'/backend/canvas/bg_images/GU Nursing background_lite.jpg';   
            }
        
            // dd($template_img_generate);
            $pdf->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'R', true);
           
            // $print_serial_no = $this->nextPrintSerial();

            $pdfBig->AddPage();

           //  $pdfBig->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'R', true);

         //   $pdfBig->SetProtection($permissions = array('modify', 'copy', 'annot-forms', 'fill-forms', 'extract', 'assemble'), '', null, 0, null);

            $print_serial_no = $this->nextPrintSerial();

            $pdfBig->SetCreator('TCPDF');

            $pdf->SetTextColor(0, 0, 0, 100, false, '');
            // dd($rowData);
            $serial_no = trim($fetch_degree_array[$excel_row][0]);

            $pdfBig->SetTextColor(0, 0, 0, 100, false, '');

                //set enrollment no
                $enrollment_font_size = '8';
                $enrollmentx= 26.7;
                // $enrollmenty = 14.1;
                $enrollmenty = 10.8;
                $enrollmentstr = trim($fetch_degree_array[$excel_row][1]);

                $pdf->SetFont($arial, '', $enrollment_font_size, '', false);
                $pdf->SetXY($enrollmentx, $enrollmenty);
                $pdf->Cell(0, 0, $enrollmentstr, 0, false, 'L');


                $pdfBig->SetFont($arial, '', $enrollment_font_size, '', false);
                $pdfBig->SetXY($enrollmentx, $enrollmenty);
                $pdfBig->Cell(0, 0, $enrollmentstr, 0, false, 'L');


                //set serial No
                $serial_no_split = (string)trim($fetch_degree_array[$excel_row][3]);
                $serialx = 186.4;
                $serialx = 181;
                $serialy = 10.9;
                for($i=0;$i<strlen($serial_no_split);$i++)
                { 
                    $get_last_four_digits = strlen($serial_no_split) - 4;
                    
                    $serial_font_size = 8;
                    if($i == 0){
                        $serialx = $serialx;
                    }
                    else{
                        if($i <= $get_last_four_digits){
                            if($serial_no_split[$i-1] == '/'){
                                $serialx = $serialx + (0.9);
                            }
                            else{
                                $serialx = $serialx + (1.7);
                            }
                        }
                        else{
                            $serialx = $serialx + (2.1);
                        }
                    }
                    if($i >= $get_last_four_digits){
                        
                        $serial_font_size = $serial_font_size + ($i - $get_last_four_digits) + 1;
                        $serialy = $serialy - 0.3;
                   
                    }
                    $serialstr = $serial_no_split[$i];

                    $pdf->SetFont($arial, '', $serial_font_size, '', false);
                    $pdf->SetXY($serialx, $serialy);
                    $pdf->Cell(0, 0, $serialstr, 0, false, 'L');

                    $pdfBig->SetFont($arial, '', $serial_font_size, '', false);
                    $pdfBig->SetXY($serialx, $serialy);
                    $pdfBig->Cell(0, 0, $serialstr, 0, false, 'L');
                }



                //qr code    
                //name // enrollment // degree // branch // cgpa // guid
                // dd($fetch_degree_array[$excel_row]);
                if($fetch_degree_array[$excel_row][10] == 'NU'){
                     $codeContents = trim($fetch_degree_array[$excel_row][4])."\n".trim($fetch_degree_array[$excel_row][1])."\n".trim($fetch_degree_array[$excel_row][11])."\n".trim($fetch_degree_array[$excel_row][13])."\n".$fetch_degree_array[$excel_row][17]."\n\n".md5(trim($fetch_degree_array[$excel_row][0]));
                }else{
                    if(is_float($fetch_degree_array[$excel_row][6])){
                        $cgpaFormat=number_format(trim($fetch_degree_array[$excel_row][6]),2);
                    }else{
                        $cgpaFormat=trim($fetch_degree_array[$excel_row][6]);
                    }
                    if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP'){
                        $codeContents = trim($fetch_degree_array[$excel_row][4])."\n".trim($fetch_degree_array[$excel_row][1])."\n".trim($fetch_degree_array[$excel_row][11])."\n".trim($fetch_degree_array[$excel_row][13])."\n".$cgpaFormat."\n\n".md5(trim($fetch_degree_array[$excel_row][0]));
                    }
                    else if($fetch_degree_array[$excel_row][10] == 'DO'){
                        $codeContents = trim($fetch_degree_array[$excel_row][4])."\n".trim($fetch_degree_array[$excel_row][1])."\n".trim($fetch_degree_array[$excel_row][11])."\n".$cgpaFormat."\n\n".md5(trim($fetch_degree_array[$excel_row][0]));
                    }

                }
                $codePath = strtoupper(md5(rand()));
                $qr_code_path = public_path().'/'.$subdomain[0].'/backend/canvas/images/qr/'.$codePath.'.png';
                $qrCodex = 5.3;
                $qrCodey = 17.9;
                $qrCodeWidth =26.3;
                $qrCodeHeight = 25.3;
                        
                \QrCode::size(75.6)
                    ->format('png')
                    ->generate($codeContents, $qr_code_path);
                $pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);


                $pdfBig->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);                

                /* //profile photo
                $extension = '';
                  if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][2]).'.jpg.jpg'){
                    $extension = '.jpg.jpg';
                }else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][2]).'.jpeg.jpg'){
                    $extension = '.jpeg.jpg';
                }else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][2]).'.png.jpg'){
                    $extension = '.png.jpg';
                }else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][2]).'.jpg'){
                    $extension = '.jpg';
                }
                else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][2]).'.jpeg'){
                    $extension = '.jpeg';
                }else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][2]).'.png'){
                    $extension = '.png';
                }
        
                if(!empty($extension)){
                   $profile_path = public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][2].$extension;
                }else{
                     if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][1]).'.jpg.jpg'){
                        $extension = '.jpg.jpg';
                    }else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][1]).'.jpeg.jpg'){
                        $extension = '.jpeg.jpg';
                    }else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][1]).'.png.jpg'){
                        $extension = '.png.jpg';
                    }else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][1]).'.jpg'){
                        $extension = '.jpg';
                    }
                    else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][1]).'.jpeg'){
                        $extension = '.jpeg';
                    }else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][1]).'.png'){
                        $extension = '.png';
                    }

                    if(!empty($extension)){
                    $profile_path = public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][1].$extension;
                    }else{
                     $profile_path ='';  
                    }
                }*/
                // dd($profile_path);
                $profilex = 181;
                $profiley = 19.8;
                $profileWidth = 22.2;
                // $profileHeight = 26; 
                $profileHeight = 26.6;
                $pdf->image($profile_path,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);

                $pdfBig->image($profile_path,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);

                //invisible data
                $invisible_font_size = '10';

                $invisible_degreex = 7.3;
                $invisible_degreey = 48.3;
                $invisible_degreestr = trim($fetch_degree_array[$excel_row][11]);
                $pdfBig->SetOverprint(true, true, 0);
                // $pdfBig->SetTextColor(0,0,48,0, false, '');
                $pdfBig->SetTextSpotColor('Spot Red', 100);
                $pdfBig->SetFont($arial, '', $invisible_font_size, '', false);
                $pdfBig->SetXY($invisible_degreex, $invisible_degreey);
                $pdfBig->Cell(0, 0, $invisible_degreestr, 0, false, 'L');


                // $pdf->SetOverprint(true, true, 0);
                // $pdf->SetTextSpotColor('Spot Red', 100);
                // // $pdf->SetTextColor(0,0,48,0, false, '');
                // $pdf->SetFont($arial, '', $invisible_font_size, '', false);
                // $pdf->SetXY($invisible_degreex, $invisible_degreey);
                // $pdf->Cell(0, 0, $invisible_degreestr, 0, false, 'L');



                // $invisible1x = 7.3;
                // $invisible1y = 45.5;
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP' || $fetch_degree_array[$excel_row][10] == 'NU'){
                    $invisible1y = 51.9;
                    $invisible1str = trim($fetch_degree_array[$excel_row][13]);
                    // $pdfBig->SetTextColor(0,0,48,0, false, '');
                    $pdfBig->SetTextSpotColor('Spot Red', 100);
                    $pdfBig->SetFont($arial, '', $invisible_font_size, '', false);
                    $pdfBig->SetXY($invisible_degreex, $invisible1y);
                    $pdfBig->Cell(0, 0, $invisible1str, 0, false, 'L');

                //     $pdf->SetTextColor(0,0,48,0, false, '');
                //     $pdf->SetTextSpotColor('Spot Red', 100);
                //     $pdf->SetFont($arial, '', $invisible_font_size, '', false);
                //     $pdf->SetXY($invisible_degreex, $invisible1y);
                //     $pdf->Cell(0, 0, $invisible1str, 0, false, 'L');
                }

                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP'){
                    // $invisible2y = 48.8;
                    $invisible2y = 55.1;
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $invisible2y = 51.9;   
                }else if($fetch_degree_array[$excel_row][10] == 'NU'){
                        $invisible2y = 56.1;
                }

                if($fetch_degree_array[$excel_row][10] == 'NU'){
                   $invisible2str = $fetch_degree_array[$excel_row][17];
                }else{
                    if(is_float($fetch_degree_array[$excel_row][6])){
                        $cgpaFormat=number_format(trim($fetch_degree_array[$excel_row][6]),2);
                    }else{
                        $cgpaFormat=trim($fetch_degree_array[$excel_row][6]);
                    }
                   $invisible2str = 'CGPA '.$cgpaFormat;  
                }

                // $pdfBig->SetTextColor(0,0,48,0, false, '');
                $pdfBig->SetTextSpotColor('Spot Red', 100);
                $pdfBig->SetFont($arial, '', $invisible_font_size, '', false);
                $pdfBig->SetXY($invisible_degreex, $invisible2y);
                $pdfBig->Cell(0, 0, $invisible2str, 0, false, 'L');

                // $pdf->SetTextColor(0,0,48,0, false, '');
                // $pdf->SetTextSpotColor('Spot Red', 100);
                // $pdf->SetFont($arial, '', $invisible_font_size, '', false);
                // $pdf->SetXY($invisible_degreex, $invisible2y);
                // $pdf->Cell(0, 0, $invisible2str, 0, false, 'L');

                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP'){
                    // $invisible3y = 52.3;
                    $invisible3y = 58.2;
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $invisible3y = 55.1;
                }else if($fetch_degree_array[$excel_row][10] == 'NU'){
                        $invisible3y = 59.7;
                }
                $invisible3str = trim($fetch_degree_array[$excel_row][8]); 
                // $pdfBig->SetTextColor(0,0,48,0, false, '');
                $pdfBig->SetTextSpotColor('Spot Red', 100);
                $pdfBig->SetFont($arial, '', $invisible_font_size, '', false);
                $pdfBig->SetXY($invisible_degreex, $invisible3y);
                $pdfBig->Cell(0, 0, $invisible3str, 0, false, 'L');

                // $pdf->SetTextColor(0,0,48,0, false, '');
                // $pdf->SetTextSpotColor('Spot Red', 100);
                // $pdf->SetFont($arial, '', $invisible_font_size, '', false);
                // $pdf->SetXY($invisible_degreex, $invisible3y);
                // $pdf->Cell(0, 0, $invisible3str, 0, false, 'L');


                //invisible data profile name
                $invisible_profile_font_size = '10';
                $invisible_profile_name1x = 175.9;
                // $invisible_profile_name1y = 47.1;
                $invisible_profile_name1y = 47.6;
                $invisible_profile_name1str = strtoupper(trim($fetch_degree_array[$excel_row][4]));
                // $pdf->SetTextColor(255,255,132);
                // $pdfBig->SetTextColor(0,0,48,0, false, '');
                $pdfBig->SetTextSpotColor('Spot Red', 100);
                $pdfBig->SetFont($arial, '', $invisible_profile_font_size, '', false);
                $pdfBig->SetXY($invisible_profile_name1x, $invisible_profile_name1y);
                $pdfBig->Cell(28.8, 0, $invisible_profile_name1str, 0, false, 'R');

                // $pdf->SetTextColor(0,0,48,0, false, '');
                // $pdf->SetTextSpotColor('Spot Red', 100);
                // $pdf->SetFont($arial, '', $invisible_profile_font_size, '', false);
                // $pdf->SetXY($invisible_profile_name1x, $invisible_profile_name1y);
                // $pdf->Cell(28.8, 0, $invisible_profile_name1str, 0, false, 'R');

                // $invisible_profile_name2x = 162.3;
                $invisible_profile_name2x = 186.6;
                // $invisible_profile_name2y = 50.4;
                $invisible_profile_name2y = 50.8;
                $invisible_profile_name2str = trim($fetch_degree_array[$excel_row][1]);
                // $pdfBig->SetTextColor(0,0,48,0, false, '');
                $pdfBig->SetTextSpotColor('Spot Red', 100);
                $pdfBig->SetFont($arial, '', $invisible_profile_font_size, '', false);
                $pdfBig->SetXY($invisible_profile_name2x, $invisible_profile_name2y);
                $pdfBig->Cell(18, 0, $invisible_profile_name2str, 0, false, 'R');
                // $pdfBig->SetOverprint(false, false, 0);

                // $pdf->SetTextColor(0,0,48,0, false, '');
                // $pdf->SetTextSpotColor('Spot Red', 100);
                // $pdf->SetFont($arial, '', $invisible_profile_font_size, '', false);
                // $pdf->SetXY($invisible_profile_name2x, $invisible_profile_name2y);
                // $pdf->Cell(18, 0, $invisible_profile_name2str, 0, false, 'R');
                // $pdf->SetOverprint(false, false, 0);

               
                //enrollment no inside round
                $enrollment_no_font_size = '7';
                // $enrollment_nox = 183.7;
                // $enrollment_noy = 65.7;
                $enrollment_nox = 184.8;
                $enrollment_noy = 66;
                // $enrollment_nox = 184.4;
                // $enrollment_noy = 65.7;
                $enrollment_nostr = trim($fetch_degree_array[$excel_row][1]);

                $pdf->SetFont($arialNarrowB, '', $enrollment_no_font_size, '', false);
                $pdf->SetTextColor(0,0,0,8,false,'');
                $pdf->SetXY(186, $enrollment_noy);
                $pdf->Cell(12, 0, $enrollment_nostr, 0, false, 'C');

                $pdfBig->SetFont($arialNarrowB, '', $enrollment_no_font_size, '', false);
                $pdfBig->SetTextColor(0,0,0,8,false,'');
                $pdfBig->SetXY(186, $enrollment_noy);
                $pdfBig->Cell(12, 0, $enrollment_nostr, 0, false, 'C');



                //profile name
                $profile_name_font_size = '20';
                $profile_namex = 71.7;
                // $profile_namey = 84.2;
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP' || $fetch_degree_array[$excel_row][10] == 'NU'){
                    $profile_namey = 83.4;
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $profile_namey = 85;
                }
                $profile_namestr = strtoupper(trim($fetch_degree_array[$excel_row][4]));

                $pdf->SetFont($timesNewRomanBI, '', $profile_name_font_size, '', false);
                $pdf->SetTextColor(0,92,88,7,false,'');
                $pdf->SetXY(10, $profile_namey);
                $pdf->Cell(190, 0, $profile_namestr, 0, false, 'C');

                $pdfBig->SetFont($timesNewRomanBI, '', $profile_name_font_size, '', false);
                $pdfBig->SetTextColor(0,92,88,7,false,'');
                $pdfBig->SetXY(10, $profile_namey);
                $pdfBig->Cell(190, 0, $profile_namestr, 0, false, 'C');


                //degree name
                $degree_name_font_size = '20';
                $degree_namex = 55;
                // $degree_namey = 100;
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'DIP' || $fetch_degree_array[$excel_row][10] == 'NU'){
                    $degree_namey = 99.4;
                }
                else if($fetch_degree_array[$excel_row][10] == 'INT'){
                    $degree_name_font_size = '14';
                    $degree_namey = 103.5;
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $degree_namey = 104.5;
                }
                // $degree_namestr = 'Integrated(BACHELOR OF TECHNOLOGY in Electronics & Communication Engineering)-';
                $degree_namestr = trim($fetch_degree_array[$excel_row][11]);

                if($fetch_degree_array[$excel_row][10] != 'DIP'){

                $pdf->SetFont($timesNewRomanBI, '', $degree_name_font_size, '', false);
                $pdf->SetTextColor(0,92,88,7,false,'');
                $pdf->SetXY(10, $degree_namey);
                $pdf->Cell(190, 0, $degree_namestr, 0, false, 'C');


                $pdfBig->SetFont($timesNewRomanBI, '', $degree_name_font_size, '', false);
                $pdfBig->SetTextColor(0,92,88,7,false,'');
                $pdfBig->SetXY(10, $degree_namey);
                $pdfBig->Cell(190, 0, $degree_namestr, 0, false, 'C');
                }

                //branch name
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP' || $fetch_degree_array[$excel_row][10] == 'NU'){
                    if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'NU'){
                        $branch_name_font_size = '18';
                        $branch_namey = 114.2;
                    }
                    else if($fetch_degree_array[$excel_row][10] == 'INT'){
                        $branch_name_font_size = '14';
                        $branch_namey = 111.5;
                    }else if ( $fetch_degree_array[$excel_row][10] == 'DIP') {
                        $branch_name_font_size = '20';
                        $branch_namey = 99.5;
                    }
                    // $branch_namex = 71.4;
                    // $branch_namey = 108.4;
                    // $branch_namex = 74.5;
                    $branch_namex = 80;
                    $branch_namestr = trim($fetch_degree_array[$excel_row][13]);

                    $pdf->SetFont($timesNewRomanBI, '', $branch_name_font_size, '', false);
                    $pdf->SetTextColor(0,92,88,7,false,'');
                    $pdf->SetXY(10, $branch_namey);
                    $pdf->Cell(190, 0, $branch_namestr, 0, false, 'C');


                    $pdfBig->SetFont($timesNewRomanBI, '', $branch_name_font_size, '', false);
                    $pdfBig->SetTextColor(0,92,88,7,false,'');
                    $pdfBig->SetXY(10, $branch_namey);
                    $pdfBig->Cell(190, 0, $branch_namestr, 0, false, 'C');
                }

                //grade
                $grade_font_size = '17';

                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'NU'){
                    // $gradex = 62.6;
                    // $gradey = 133.1;
                    $gradey = 137.2;
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    // $gradex = 68.9;
                    $gradey = 133.3;
                }elseif ($fetch_degree_array[$excel_row][10] == 'DIP') {
                    $gradey = 132.3;
                }
                $divisionStr= '';

                if($fetch_degree_array[$excel_row][10] == 'NU'){
                    /* echo $fetch_degree_array[$excel_row][17];
                        exit;*/
                    if(strpos($fetch_degree_array[$excel_row][17], 'With') !== false){

                         $gradestr = $fetch_degree_array[$excel_row][17].' ';
                    }else{
                        $divisionStr= ' division ';
                        $gradestr = $fetch_degree_array[$excel_row][17]; 
                    }
                   
                }else{
                if(is_float($fetch_degree_array[$excel_row][6])){
                $gradestr = 'CGPA '. number_format(trim($fetch_degree_array[$excel_row][6]),2).' ';
                }else{
                $gradestr = 'CGPA '. trim($fetch_degree_array[$excel_row][6]).' ';    
                }
                }
                $instr = $divisionStr.'in ';
                $datestr = trim($fetch_degree_array[$excel_row][8]);


                $grade_str_result = $this->GetStringPositions(
                    array(
                        array($gradestr, $timesNewRomanBI, '', $grade_font_size), 
                        array($instr, $timesNewRomanI, '', $grade_font_size),
                        array($datestr, $timesNewRomanBI, '', $grade_font_size)
                    ),$pdf
                );
                

                $pdf->SetFont($timesNewRomanBI, '', $grade_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($grade_str_result[0], $gradey);
                $pdf->Cell(0, 0, $gradestr, 0, false, 'L');

                $pdfBig->SetFont($timesNewRomanBI, '', $grade_font_size, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY($grade_str_result[0], $gradey);
                $pdfBig->Cell(0, 0, $gradestr, 0, false, 'L');


                $pdf->SetFont($timesNewRomanI, '', $grade_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($grade_str_result[1], $gradey);
                $pdf->Cell(0, 0, $instr, 0, false, 'L');

                $pdfBig->SetFont($timesNewRomanI, '', $grade_font_size, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY($grade_str_result[1], $gradey);
                $pdfBig->Cell(0, 0, $instr, 0, false, 'L');


                $pdf->SetFont($timesNewRomanBI, '', $grade_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($grade_str_result[2], $gradey);
                $pdf->Cell(0, 0, $datestr, 0, false, 'L');

                $pdfBig->SetFont($timesNewRomanBI, '', $grade_font_size, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY($grade_str_result[2], $gradey);
                $pdfBig->Cell(0, 0, $datestr, 0, false, 'L');


                //micro line name
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP'|| $fetch_degree_array[$excel_row][10] == 'NU'){
                    $microlinestr = strtoupper(str_replace(' ','',$fetch_degree_array[$excel_row][4].$fetch_degree_array[$excel_row][11].$fetch_degree_array[$excel_row][13]));
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $microlinestr = strtoupper(str_replace(' ','',$fetch_degree_array[$excel_row][4].$fetch_degree_array[$excel_row][11]));
                }

                $microlinestr = preg_replace('/\s+/', '', $microlinestr); //added by Mandar
                $textArray = imagettfbbox(1.4, 0, public_path().'/'.$subdomain[0].'/backend/canvas/fonts/Arial Bold.TTF', $microlinestr);
                $strWidth = ($textArray[2] - $textArray[0]);
                $strHeight = $textArray[6] - $textArray[1] / 1.4;
                
              /*  if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP'|| $fetch_degree_array[$excel_row][10] == 'NU'){
                    $latestWidth = 557;
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $latestWidth = 564;
                }*/
                 $latestWidth = 553;
                
                //Updated by Mandar
                $microlinestrLength=strlen($microlinestr);

                //width per character
                $microLinecharacterWd =$strWidth/$microlinestrLength;

                //Required no of characters required in string to match width
                 $microlinestrCharReq=$latestWidth/$microLinecharacterWd;
                $microlinestrCharReq=round($microlinestrCharReq);
               // echo '<br>';
                //No of time string should repeated
                 $repeateMicrolineStrCount=$latestWidth/$strWidth;
                 $repeateMicrolineStrCount=round($repeateMicrolineStrCount)+1;

                //Repeatation of string 
                 $microlinestrRep = str_repeat($microlinestr, $repeateMicrolineStrCount);
               // echo strlen($microlinestrRep);
                //Cut string in required characters (final string)
                $array = substr($microlinestrRep,0,$microlinestrCharReq);
                $wd = '';
                $last_width = 0;
                $message = array();
               /* for($i=1;$i<=1000;$i++){

                    if($i * $strWidth > $latestWidth){
                        $wd = $i * $strWidth;
                        $last_width =$wd - $strWidth;
                        $extraWidth = $latestWidth - $last_width;
                        $stringLength = strlen($microlinestr);
                        $extraCharacter = intval($stringLength * $extraWidth / $strWidth);
                        $message[$i]  = mb_substr($microlinestr, 0,$extraCharacter);
                        break;
                    }
                    $message[$i] = $microlinestr.'';
                }

                $horizontal_line = array();
                foreach ($message as $key => $value) {
                    $horizontal_line[] = $value;
                }
                

                $string = implode(',', $horizontal_line);
                $array = str_replace(',', '', $string);*/
                /*$pdf->SetFont($arialb, '', 1.2, '', false);
                $pdf->SetTextColor(0, 0, 0);*/
                $pdf->SetFont($arialb, 'B', 1.2, '', false);
                $pdf->SetTextColor(0, 0, 0, 100);
                $pdf->StartTransform();
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'NU'){
                    // $pdf->SetXY(36.8, 144);
                    $pdf->SetXY(36.8, 146.6);
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $pdf->SetXY(36.8, 145.5);
                }else if ($fetch_degree_array[$excel_row][10] == 'DIP') {
                    $pdf->SetXY(36.8, 143.5);
                }
                $pdf->Cell(0, 0, $array, 0, false, 'L');

                $pdf->StopTransform();


                /*$pdfBig->SetFont($arialb, '', 1.2, '', false);
                $pdfBig->SetTextColor(0, 0, 0);*/
                $pdfBig->SetFont($arialb, 'B', 1.2, '', false);
                $pdfBig->SetTextColor(0, 0, 0, 100);
                $pdfBig->StartTransform();
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'NU'){
                    // $pdf->SetXY(36.8, 144);
                    $pdfBig->SetXY(36.8, 146.6);
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $pdfBig->SetXY(36.8, 145.5);
                }else if ($fetch_degree_array[$excel_row][10] == 'DIP') {
                    $pdfBig->SetXY(36.8, 143.5);
                }
                $pdfBig->Cell(0, 0, $array, 0, false, 'L');

                $pdfBig->StopTransform();

                if($fetch_degree_array[$excel_row][10] == 'NU'){
                   $microlineEnrollment = strtoupper(str_replace(' ','',$fetch_degree_array[$excel_row][1].strtoupper($fetch_degree_array[$excel_row][17]).$fetch_degree_array[$excel_row][8])); 
                }else{
                  if(is_float($fetch_degree_array[$excel_row][6])){
                        $cgpaFormat=number_format(trim($fetch_degree_array[$excel_row][6]),2);
                    }else{
                        $cgpaFormat=trim($fetch_degree_array[$excel_row][6]);
                    }
                  $microlineEnrollment = strtoupper(str_replace(' ','',$fetch_degree_array[$excel_row][1].'CGPA'.$cgpaFormat.$fetch_degree_array[$excel_row][8]));  
                }


                $microlineEnrollment = preg_replace('/\s+/', '', $microlineEnrollment); //added by Mandar
                //$microlineEnrollment = strtoupper(str_replace(' ','',$fetch_degree_array[$excel_row][1].'CGPA'.number_format($fetch_degree_array[$excel_row][6],2).$fetch_degree_array[$excel_row][8]));
                $textArrayEnrollment = imagettfbbox(1.4, 0, public_path().'/'.$subdomain[0].'/backend/canvas/fonts/Arial Bold.TTF', $microlineEnrollment);
                $strWidthEnrollment = ($textArrayEnrollment[2] - $textArrayEnrollment[0]);
                $strHeightEnrollment = $textArrayEnrollment[6] - $textArrayEnrollment[1] / 1.4;
                
              //  $latestWidthEnrollment = 627;

                    $latestWidthEnrollment = 595;
                /*}
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $latestWidthEnrollment = 627;                
                }*/
                //Updated by Mandar
                $microlineEnrollmentstrLength=strlen($microlineEnrollment);

                //width per character
                $microlineEnrollmentcharacterWd =$strWidthEnrollment/$microlineEnrollmentstrLength;

                //Required no of characters required in string to match width
                $microlineEnrollmentCharReq=$latestWidthEnrollment/$microlineEnrollmentcharacterWd;
                $microlineEnrollmentCharReq=round($microlineEnrollmentCharReq);

                //No of time string should repeated
                 $repeatemicrolineEnrollmentCount=$latestWidthEnrollment/$strWidthEnrollment;
                 $repeatemicrolineEnrollmentCount=round($repeatemicrolineEnrollmentCount)+1;

                //Repeatation of string 
                 $microlineEnrollmentstrRep = str_repeat($microlineEnrollment, $repeatemicrolineEnrollmentCount);
                
                //Cut string in required characters (final string)
                $arrayEnrollment = substr($microlineEnrollmentstrRep,0,$microlineEnrollmentCharReq);

                $wdEnrollment = '';
                $last_widthEnrollment = 0;
                $messageEnrollment = array();
                /*for($i=1;$i<=1000;$i++){

                    if($i * $strWidthEnrollment > $latestWidthEnrollment){
                        $wdEnrollment = $i * $strWidthEnrollment;
                        $last_widthEnrollment =$wdEnrollment - $strWidthEnrollment;
                        $extraWidth = $latestWidthEnrollment - $last_widthEnrollment;
                        $stringLength = strlen($microlineEnrollment);
                        $extraCharacter = intval($stringLength * $extraWidth / $strWidthEnrollment);
                        $messageEnrollment[$i]  = mb_substr($microlineEnrollment, 0,$extraCharacter);
                        break;
                    }
                    $messageEnrollment[$i] = $microlineEnrollment.'';
                }

                $horizontal_lineEnrollment = array();
                foreach ($messageEnrollment as $key => $value) {
                    $horizontal_lineEnrollment[] = $value;
                }
                
                $stringEnrollment = implode(',', $horizontal_lineEnrollment);
                $arrayEnrollment = str_replace(',', '', $stringEnrollment);*/

                /*$pdf->SetFont($arialb, '', 1.2, '', false);
                $pdf->SetTextColor(0, 0, 0);*/
                $pdf->SetFont($arialb, 'B', 1.2, '', false);
                $pdf->SetTextColor(0, 0, 0, 100);
                $pdf->StartTransform();
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP'|| $fetch_degree_array[$excel_row][10] == 'NU'){
                    // $pdf->SetXY(36.4, 219);
                    $pdf->SetXY(36.4, 216.6);
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $pdf->SetXY(36.4, 216);
                }
                
                $pdf->Cell(0, 0, $arrayEnrollment, 0, false, 'L');

                $pdf->StopTransform();


                /*$pdfBig->SetFont($arialb, '', 1.2, '', false);
                $pdfBig->SetTextColor(0, 0, 0);*/
                $pdfBig->SetFont($arialb, 'B', 1.2, '', false);
                $pdfBig->SetTextColor(0, 0, 0, 100);
                $pdfBig->StartTransform();
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP'|| $fetch_degree_array[$excel_row][10] == 'NU'){
                    // $pdf->SetXY(36.4, 219);
                    $pdfBig->SetXY(36.4, 216.6);
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $pdfBig->SetXY(36.4, 216);
                }
                
                $pdfBig->Cell(0, 0, $arrayEnrollment, 0, false, 'L');

                $pdfBig->StopTransform();




                //profile name in hindi
                $profile_name_hindi_font_size = '25';
                $profile_name_hidix = 85.1;
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'NU'){
                    // $profile_name_hidiy = 155.8;
                    $profile_name_hidiy = 156.5;
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $profile_name_hidiy = 159;
                }else if ($fetch_degree_array[$excel_row][10] == 'DIP') {
                   $profile_name_hidiy = 161;
                }
                $profile_name_hindistr = trim($fetch_degree_array[$excel_row][5]);

                $pdf->SetFont($krutidev101, '', $profile_name_hindi_font_size, '', false);
                $pdf->SetTextColor(0,92,88,7,false,'');
                $pdf->SetXY(10, $profile_name_hidiy);
                $pdf->Cell(190, 0, $profile_name_hindistr, 0, false, 'C');

                $pdfBig->SetFont($krutidev101, '', $profile_name_hindi_font_size, '', false);
                $pdfBig->SetTextColor(0,92,88,7,false,'');
                $pdfBig->SetXY(10, $profile_name_hidiy);
                $pdfBig->Cell(190, 0, $profile_name_hindistr, 0, false, 'C');

                //date in hindi (make whole string)
                $date_font_size =  '20';
                if($fetch_degree_array[$excel_row][10] == 'DIP'){
                $str = 'rhu o"khZ; fMIyksek ikBîØe ';
                $hindiword_str = ' ' ; 
                }else{
                $str = 'dks bl mikf/k dh çkfIr gsrq fofue; fofgr vis{kkvksa dks ';
                $hindiword_str = 'esa' ; 
                }
                $date_hindistr = trim($fetch_degree_array[$excel_row][9]).' ';
               

                $strx = 20;
                $date_hindix = 159;
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'NU'){
                    // $date_hindiy = 167.2;
                    $date_hindiy = 168.4;
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $date_hindiy = 170.6;
                }else if($fetch_degree_array[$excel_row][10] == 'DIP'){
                    $date_hindiy = 180.4;
                }

                $result = $this->GetStringPositions(
                    array(
                        array($str, $krutidev100, '', $date_font_size), 
                        array($date_hindistr, $krutidev101, '', $date_font_size),
                        array($hindiword_str, $krutidev100, '', $date_font_size)
                    ),$pdf
                );

                $pdf->SetFont($krutidev100, '', $date_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($result[0], $date_hindiy);
                $pdf->Cell(0, 0, $str, 0, false, 'L');

                $pdfBig->SetFont($krutidev100, '', $date_font_size, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY($result[0], $date_hindiy);
                $pdfBig->Cell(0, 0, $str, 0, false, 'L');

                $pdf->SetFont($krutidev101, '', $date_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($result[1], $date_hindiy);
                $pdf->Cell(0, 0, $date_hindistr, 0, false, 'L');

                $pdfBig->SetFont($krutidev101, '', $date_font_size, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY($result[1], $date_hindiy);
                $pdfBig->Cell(0, 0, $date_hindistr, 0, false, 'L');

                $pdf->SetFont($krutidev100, '', $date_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($result[2], $date_hindiy);
                $pdf->Cell(0, 0, $hindiword_str, 0, false, 'L');


                $pdfBig->SetFont($krutidev100, '', $date_font_size, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY($result[2], $date_hindiy);
                $pdfBig->Cell(0, 0, $hindiword_str, 0, false, 'L');



                //grade in hindi
                $grade_hindix = 37.5;
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT'){
                    // $grade_hindiy = 176.2;
                    $grade_hindiy = 177.5;
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $grade_hindiy = 181.7;
                }elseif ($fetch_degree_array[$excel_row][10] == 'DIP') {
                    $grade_hindix = 40.5;
                    $grade_hindiy = 188.5;
                }elseif ($fetch_degree_array[$excel_row][10] == 'NU') {

                    if(strpos($fetch_degree_array[$excel_row][17], 'With') !== false){
                       
                        $grade_hindiy = 178.8;
                        $grade_hindix = 31.5; 
                    }else if($fetch_degree_array[$excel_row][17]=="First"){
                       $grade_hindiy = 178.8;
                        $grade_hindix = 49.5; 
                    }else{
                       $grade_hindiy = 178.8;
                        $grade_hindix = 46.5; 
                    }
                    
                }
                if ($fetch_degree_array[$excel_row][10] == 'DIP') {

                $pdf->SetFont($krutidev100, '', $date_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($grade_hindix-6, $grade_hindiy);
                $pdf->Cell(0, 0, 'esa', 0, false, 'L'); 
                
                $pdfBig->SetFont($krutidev100, '', $date_font_size, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY($grade_hindix-6, $grade_hindiy);
                $pdfBig->Cell(0, 0, 'esa', 0, false, 'L'); 
                }


                if($fetch_degree_array[$excel_row][10] == 'NU'){
                  $grade_hindistr = trim($fetch_degree_array[$excel_row][18]);
                }else{
                  $grade_hindistr = 'lh-th-ih-,- '.trim($fetch_degree_array[$excel_row][7]);  
                }

                $pdf->SetFont($krutidev101, '', $date_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($grade_hindix, $grade_hindiy);
                $pdf->Cell(0, 0, $grade_hindistr, 0, false, 'L');

                $pdfBig->SetFont($krutidev101, '', $date_font_size, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY($grade_hindix, $grade_hindiy);
                $pdfBig->Cell(0, 0, $grade_hindistr, 0, false, 'L');


                //degree name in hindi
                $degree_hindi_font_size = '25';
                if($fetch_degree_array[$excel_row][10] == 'INT'){
                    $degree_hindi_font_size = '18';
                    $degree_hindiy = 188.8;
                }
                $degree_hindix = 66;
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'DIP'){
                    $degree_hindiy = 185.2;
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $degree_hindiy = 192;
                }elseif ($fetch_degree_array[$excel_row][10] == 'NU') {
                    $degree_hindiy = 188.2;
                }

                if($fetch_degree_array[$excel_row][10] != 'DIP'){
                $degree_hindistr = trim($fetch_degree_array[$excel_row][12]);

                $pdf->SetFont($krutidev101, '', $degree_hindi_font_size, '', false);
                $pdf->SetTextColor(0,92,88,7,false,'');
                $pdf->SetXY(10, $degree_hindiy);
                $pdf->Cell(190, 0, $degree_hindistr, 0, false, 'C');

                $pdfBig->SetFont($krutidev101, '', $degree_hindi_font_size, '', false);
                $pdfBig->SetTextColor(0,92,88,7,false,'');
                $pdfBig->SetXY(10, $degree_hindiy);
                $pdfBig->Cell(190, 0, $degree_hindistr, 0, false, 'C');
                }
                //branch name in hindi
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP'|| $fetch_degree_array[$excel_row][10] == 'NU'){
                    if($fetch_degree_array[$excel_row][10] == 'DIP'){
                        $branch_hindi_font_size = '25';
                    }else {
                        $branch_hindi_font_size = '20';
                    }
                    $branch_hindiy = 196.5;
                    if($fetch_degree_array[$excel_row][10] == 'INT'){
                        $branch_hindi_font_size = '15';
                        $branch_hindiy = 196.8;
                    }elseif ($fetch_degree_array[$excel_row][10] == 'NU') {
                        $branch_hindiy = 199.8;
                    }
                    $branch_hindix = 75.2;
                    $branch_hindistr = trim($fetch_degree_array[$excel_row][14]);

                    $pdf->SetFont($krutidev101, '', $branch_hindi_font_size, '', false);
                    $pdf->SetTextColor(0,92,88,7,false,'');
                    $pdf->SetXY(10, $branch_hindiy);
                    $pdf->Cell(190, 0, $branch_hindistr, 0, false, 'C');

                    $pdfBig->SetFont($krutidev101, '', $branch_hindi_font_size, '', false);
                    $pdfBig->SetTextColor(0,92,88,7,false,'');
                    $pdfBig->SetXY(10, $branch_hindiy);
                    $pdfBig->Cell(190, 0, $branch_hindistr, 0, false, 'C');
                }

                //today date
                $today_date_font_size = '12';
                // $today_datex = 96.5;
                $today_datex = 95;
                $today_datey = 273.8;
                $todaystr = 'September, 2020';

                $pdf->SetFont($timesNewRomanBI, '', $today_date_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY(84, $today_datey);
                $pdf->Cell(47, 0, $todaystr, 0, false, 'C');

                $pdfBig->SetFont($timesNewRomanBI, '', $today_date_font_size, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY(84, $today_datey);
                $pdfBig->Cell(47, 0, $todaystr, 0, false, 'C');

                //1D Barcode
                $style1D = array(
                    'position' => '',
                    'align' => 'C',
                    'stretch' => false,
                    'fitwidth' => true,
                    'cellfitalign' => '',
                    'border' => false,
                    'hpadding' => 'auto',
                    'vpadding' => 'auto',
                    'fgcolor' => array(0,0,0),
                    'bgcolor' => false, //array(255,255,255),
                    'text' => false,
                    'font' => 'helvetica',
                    'fontsize' => 8,
                    'stretchtext' => 4
                );              
                // $barcodex = 80.8;
                $barcodex = 84;
                $barcodey = 278;
                $barcodeWidth = 46;
                $barodeHeight = 12;

                $pdf->SetAlpha(1);
                // Barcode CODE_39 - Roll no 
                $pdf->write1DBarcode(trim($fetch_degree_array[$excel_row][1]), 'C39', $barcodex, $barcodey, $barcodeWidth, $barodeHeight, 0.4, $style1D, 'N');

                $pdfBig->SetAlpha(1);
                // Barcode CODE_39 - Roll no 
                $pdfBig->write1DBarcode(trim($fetch_degree_array[$excel_row][1]), 'C39', $barcodex, $barcodey, $barcodeWidth, $barodeHeight, 0.4, $style1D, 'N');

                 $footer_name_font_size = '12';
                $footer_namex = 84.9;
                $footer_namey = 290.9;
                $footer_namestr = strtoupper(trim($fetch_degree_array[$excel_row][4]));



                $pdfBig->SetOverprint(true, true, 0);
                $pdfBig->SetFont($arialb, 'B', $footer_name_font_size, '', false);
                // $pdf->SetTextColor(234,234,234);
                $pdfBig->SetTextSpotColor('Spot Dark Green', 100);
                $pdfBig->SetXY(10, $footer_namey);
                $pdfBig->Cell(190, 0, $footer_namestr, 0, false, 'C');
                $pdfBig->SetOverprint(false, false, 0);
                //repeat line
                $repeat_font_size = '9.5';
                $repeatx= 0;

                    //name repeat line
                    // $name_repeaty = 246.7;
                    // $name_repeaty = 244;
                    $name_repeaty = 242.9;
                     if($fetch_degree_array[$excel_row][10] == 'NU'){
                        $cgpaFormat = strtoupper($fetch_degree_array[$excel_row][17]);
                    }else{
                        if(is_float($fetch_degree_array[$excel_row][6])){
                            $cgpaFormat='CGPA '.number_format(trim($fetch_degree_array[$excel_row][6]),2);
                        }else{
                            $cgpaFormat='CGPA '.trim($fetch_degree_array[$excel_row][6]);
                        }
                    }
                    $name_repeatstr = strtoupper(trim($fetch_degree_array[$excel_row][4])).' '.$cgpaFormat.' '.strtoupper(trim($fetch_degree_array[$excel_row][8])).' '; 
                    $name_repeatstr .= $name_repeatstr . $name_repeatstr . $name_repeatstr . $name_repeatstr . $name_repeatstr;


                    //degree repeat line
                    // $degree_repeaty = 250.8;
                    // $degree_repeaty = 248.8;
                    $degree_repeaty = 247;
                    $degree_repeatstr = strtoupper(trim($fetch_degree_array[$excel_row][11])).' '.strtoupper(trim($fetch_degree_array[$excel_row][13])).' '; 
                    $degree_repeatstr .= $degree_repeatstr . $degree_repeatstr . $degree_repeatstr . $degree_repeatstr . $degree_repeatstr . $degree_repeatstr . $degree_repeatstr . $degree_repeatstr . $degree_repeatstr . $degree_repeatstr;

                    //grade repeat line
                    $grade_repeaty = 251.1;
                    $grade_repeatstr = 'GALGOTIAS UNIVERSITY '; 
                    $grade_repeatstr .= $grade_repeatstr . $grade_repeatstr . $grade_repeatstr . $grade_repeatstr . $grade_repeatstr;

                    //date repeat line
                    // $date_repeaty = 254.9;
                    // $date_repeaty = 253.8;
                    $date_repeaty = 255.2;
                    $date_repeatstr = 'GALGOTIAS UNIVERSITY '; 
                    $date_repeatstr .= $date_repeatstr . $date_repeatstr . $date_repeatstr . $date_repeatstr . $date_repeatstr;


                    // for($d = 0; $d < 15; $d++){
                        //name repeat line
                        // $security_line .= $name_repeatstr . ' ';
                 //    if($fetch_degree_array[$excel_row][10] != 'DIP'){
                        $pdf->SetTextColor(0,0,0,7,false,'');
                        $pdf->SetFont($arialb, '', $repeat_font_size, '', false);
                        $pdf->StartTransform();
                        $pdf->SetXY($repeatx, $name_repeaty);
                        $pdf->Cell(0, 0, $name_repeatstr, 0, false, 'L');
                        $pdf->StopTransform();


                        $pdfBig->SetTextColor(0,0,0,7,false,'');
                        $pdfBig->SetFont($arialb, '', $repeat_font_size, '', false);
                        $pdfBig->StartTransform();
                        $pdfBig->SetXY($repeatx, $name_repeaty);
                        $pdfBig->Cell(0, 0, $name_repeatstr, 0, false, 'L');
                        $pdfBig->StopTransform();

                        //degree repeat line
                        // $degree_security_line .= $degree_repeatstr . ' ';

                        $pdf->SetFont($arialb, '', $repeat_font_size, '', false);
                        $pdf->StartTransform();
                        $pdf->SetXY($repeatx, $degree_repeaty);
                        $pdf->Cell(0, 0, $degree_repeatstr, 0, false, 'L');
                        $pdf->StopTransform();

                        $pdfBig->SetFont($arialb, '', $repeat_font_size, '', false);
                        $pdfBig->StartTransform();
                        $pdfBig->SetXY($repeatx, $degree_repeaty);
                        $pdfBig->Cell(0, 0, $degree_repeatstr, 0, false, 'L');
                        $pdfBig->StopTransform();
                 //   }
                        //grade repeat line
                        // $grade_security_line .= $grade_repeatstr . ' ';
/*
                        $pdf->SetFont($arialb, '', $repeat_font_size, '', false);
                        $pdf->StartTransform();
                        $pdf->SetXY($repeatx, $grade_repeaty);
                        $pdf->Cell(0, 0, $grade_repeatstr, 0, false, 'L');
                        $pdf->StopTransform();

                        $pdfBig->SetFont($arialb, '', $repeat_font_size, '', false);
                        $pdfBig->StartTransform();
                        $pdfBig->SetXY($repeatx, $grade_repeaty);
                        $pdfBig->Cell(0, 0, $grade_repeatstr, 0, false, 'L');
                        $pdfBig->StopTransform();
*/
                        //date repeat line
                        // $date_security_line .= $date_repeatstr . ' ';

                  /*      $pdf->SetFont($arialb, '', $repeat_font_size, '', false);
                        $pdf->StartTransform();
                        $pdf->SetXY($repeatx, $date_repeaty);
                        $pdf->Cell(0, 0, $date_repeatstr, 0, false, 'L');
                        $pdf->StopTransform();

                        $pdfBig->SetFont($arialb, '', $repeat_font_size, '', false);
                        $pdfBig->StartTransform();
                        $pdfBig->SetXY($repeatx, $date_repeaty);
                        $pdfBig->Cell(0, 0, $date_repeatstr, 0, false, 'L');
                        $pdfBig->StopTransform();*/
                    // }
                //ce sign visible
                /*$ce_sign_visible_path = public_path().'/'.$subdomain[0].'/galgotia_cutomImages/CE_Sign_'.$excel_row.'.png';
                // $uv_sign_visibllex = 168.5;
                // $ce_sign_visibllex = 30.8;
                $ce_sign_visibllex = 26;
                $ce_sign_visiblley = 243.7;
                // $ce_sign_visiblleWidth = 21;
                $ce_sign_visiblleWidth = 35;
                $ce_sign_visiblleHeight = 16;
                $pdf->image($ce_sign_visible_path,$ce_sign_visibllex,$ce_sign_visiblley,$ce_sign_visiblleWidth,$ce_sign_visiblleHeight,"",'','L',true,3600);

                $pdfBig->image($ce_sign_visible_path,$ce_sign_visibllex,$ce_sign_visiblley,$ce_sign_visiblleWidth,$ce_sign_visiblleHeight,"",'','L',true,3600);*/

                unlink(public_path().'/'.$subdomain[0].'/galgotia_cutomImages/CE_Sign_'.$excel_row.'.png');

                //vc sign visible
                $vc_sign_visible_path = public_path().'/'.$subdomain[0].'/galgotia_cutomImages/VC_Sign_'.$excel_row.'.png';
                // $uv_sign_visibllex = 168.5;
                $vc_sign_visibllex = 168.5;
                $vc_sign_visiblley = 243.7;
                $vc_sign_visiblleWidth = 21;
                $vc_sign_visiblleHeight = 16;
                $pdf->image($vc_sign_visible_path,$vc_sign_visibllex,$vc_sign_visiblley,$vc_sign_visiblleWidth,$vc_sign_visiblleHeight,"",'','L',true,3600);

                $pdfBig->image($vc_sign_visible_path,$vc_sign_visibllex,$vc_sign_visiblley,$vc_sign_visiblleWidth,$vc_sign_visiblleHeight,"",'','L',true,3600);

                unlink(public_path().'/'.$subdomain[0].'/galgotia_cutomImages/VC_Sign_'.$excel_row.'.png');
                

                // Ghost image
                $ghost_font_size = '13';
                $ghostImagex = 10;
                $ghostImagey = 278.8;
                $ghostImageWidth = 68;
                $ghostImageHeight = 9.8;
                $name = substr(str_replace(' ','',strtoupper($fetch_degree_array[$excel_row][4])), 0, 6);
                // dd($name);

                $tmpDir = $this->createTemp(public_path().'/backend/canvas/ghost_images/temp');
                // if(!array_key_exists($name, $ghostImgArr))
                // {
                    $w = $this->CreateMessage($tmpDir, $name ,$ghost_font_size,'');
                    // $ghostImgArr[$name] = $w;   
                // }
                // else{
                //     $w = $ghostImgArr[$name];
                // }

                $pdf->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $ghostImageWidth, $ghostImageHeight, "PNG", '', 'L', true, 3600);

                $pdfBig->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $ghostImageWidth, $ghostImageHeight, "PNG", '', 'L', true, 3600);

            // $withoutExt = preg_replace('/\\.[^.\\s]{3,4}$/', '', $excelfile);

            // dd($serial_no);
            // $template_name  = $FID['template_name'];
            $certName = str_replace("/", "_", $serial_no) .".pdf";
            // $myPath =    ().'/backend/temp_pdf_file';
            $myPath = public_path().'/backend/temp_pdf_file';
            $dt = date("_ymdHis");
            // print_r($pdf);
            // print_r("$tmpDir/" . $name."".$ghost_font_size.".png");
            $pdf->output($myPath . DIRECTORY_SEPARATOR . $certName, 'F');

            // dd($fetch_degree_array[$excel_row][0]);
            // Degree::where('GUID',$fetch_degree_array[$excel_row][0])->update(['status'=>'1']);

            //$this->addCertificate($serial_no, $certName, $dt,$template_data['id'],$admin_id);
            //Bhavin put hard code template_id = 5
            $this->addCertificate($serial_no, $certName, $dt,5,$admin_id);

            $username = $admin_id['username'];
            date_default_timezone_set('Asia/Kolkata');

            $content = "#".$log_serial_no." serial No :".$serial_no.PHP_EOL;
            $date = date('Y-m-d H:i:s').PHP_EOL;
            $print_datetime = date("Y-m-d H:i:s");
            

            $print_count = $this->getPrintCount($serial_no);
            $printer_name = /*'HP 1020';*/$printer_name;
            //$this->addPrintDetails($username, $print_datetime, $printer_name, $print_count, $print_serial_no, $serial_no,$template_data['template_name'],$admin_id);
            //Bhavin put hard code template name
            $this->addPrintDetails($username, $print_datetime, $printer_name, $print_count, $print_serial_no, $serial_no,'Degree Certificate',$admin_id);
            
             Degree::where('GUID',$fetch_degree_array[$excel_row][0])->update(['status'=>'1']);
            }else{
             Degree::where('GUID',$fetch_degree_array[$excel_row][0])->update(['status'=>'2']);;
              
            }

        }
        $msg = '';
        // if(is_dir($tmpDir)){
        //     rmdir($tmpDir);
        // }   
       // $file_name = $template_data['template_name'].'_'.date("Ymdhms").'.pdf';
        //print_r($fetch_degree_array);
      //  exit;
        $file_name =  str_replace("/", "_",'2020 '.$fetch_degree_array[0][11].' '.$fetch_degree_array[0][13].' '.$fetch_degree_array[0][10]).'.pdf';
        
        $auth_site_id=Auth::guard('admin')->user()->site_id;
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();


        $filename = public_path().'/backend/tcpdf/examples/'.$file_name;
        // $filename = 'C:\xampp\htdocs\seqr\public\backend\tcpdf\exmples\/'.$file_name;
        $pdfBig->output($filename,'F');

        $aws_qr = \File::copy($filename,public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/'.$file_name);
        @unlink($filename);
    }
            

    }
