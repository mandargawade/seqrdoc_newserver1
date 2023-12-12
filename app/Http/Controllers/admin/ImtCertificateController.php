<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\models\imt\IMT;
use App\models\Config;
use App\models\SystemConfig;
use App\models\StudentTable;
use App\models\PrintingDetail;
use Session,TCPDF,TCPDF_FONTS,Auth,DB,PDF;

class ImtCertificateController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain); //$subdomain[0]="icat"   
        if($request->ajax()){
            $where_str    = "1 = ?";
            $where_params = array(1); 

            if (!empty($request->input('sSearch')))
            {
                $search     = $request->input('sSearch');
                $where_str .= " and ( username like \"%{$search}%\""
                . " or dob like \"%{$search}%\""
                . " or id_number like \"%{$search}%\""
                . " or name like \"%{$search}%\""
                . ")";
            }  
           $status=$request->get('status');
            // if($status==1)
            // {
            //     $status='1';
            //     $where_str.= " and (imts.status = $status)";
            // }
            // else if($status==0)
            // {
            //     $status='0';
            //     $where_str.=" and (imts.status= $status)";
            // }

            $auth_site_id=Auth::guard('admin')->user()->site_id;                                               
              //for serial number
            $iDisplayStart=$request->input('iDisplayStart'); 
            DB::statement(DB::raw('set @rownum='.$iDisplayStart));
            DB::statement(DB::raw('set @rownum=0'));   
            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'name','dob','birth_place','id_number','sex','marital_status','address','mother_name',];
            //'doi','occupation','file','photo','fingerprint'

            $font_master_count = IMT::select($columns)
                //  ->whereRaw($where_str, $where_params)
                 ->where('publish',1)
                //  ->where('site_id',$auth_site_id)
                 ->count();
                //  \DB::enableQueryLog();
            $fontMaster_list = IMT::select($columns)
                   ->where('publish',1);
                //    ->get();
                //    ->where('site_id',$auth_site_id)
                //    ->whereRaw($where_str, $where_params);
      
            if($request->get('iDisplayStart') != '' && $request->get('iDisplayLength') != ''){
                $fontMaster_list = $fontMaster_list->take($request->input('iDisplayLength'))
                ->skip($request->input('iDisplayStart'));
            }          
            // dd(DB::getQueryLog());
            if($request->input('iSortCol_0')){
                $sql_order='';
                for ( $i = 0; $i < $request->input('iSortingCols'); $i++ )
                {
                    $column = $columns[$request->input('iSortCol_' . $i)];
                    if(false !== ($index = strpos($column, ' as '))){
                        $column = substr($column, 0, $index);
                    }
                    $fontMaster_list = $fontMaster_list->orderBy($column,$request->input('sSortDir_'.$i));   
                }
            } 
            $fontMaster_list = $fontMaster_list->OrderBy('id','desc')->get();
             
            $response['iTotalDisplayRecords'] = $font_master_count;
            $response['iTotalRecords'] = $font_master_count;
            $response['sEcho'] = intval($request->input('sEcho'));
            $response['aaData'] = $fontMaster_list->toArray();
            
            return $response;
        }
        return view('admin.imt.index');
        // $data = IMT::orderBy('id', 'desc')->get();
        // return view('admin.imt.index',compact('data'));
    }
    public function allRecordAjax(){
        $data = IMT::orderBy('id', 'desc')->get();
        return $data;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.imt.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'dob' => 'required|date',
            'birth_place' => 'required',
            'card_number' => 'required',
            'sex' => 'required',
            'marital_status' => 'required',
            'address' => 'required',
            'mother_name' => 'required',
            'doi' => 'required|date',
            'occupation' => 'required',
            'photo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'fingerprint' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);
        // name dob birth_place id_number sex marital_status address mother_name doi occupation file photo fingerprint
        $data = new IMT();
        $data->name  = $request->name;
        $data->dob  = $request->dob;
        $data->birth_place  = $request->birth_place;
        $data->id_number  = $request->card_number;
        $data->sex  = $request->sex;
        $data->marital_status  = $request->marital_status;
        $data->address  = $request->address;
        $data->mother_name  = $request->mother_name;
        $data->doi  = $request->doi;
        $data->occupation  = $request->occupation;

		$root_path = public_path().'\\'.$subdomain[0];
        $image = $request->file('photo');
        $fingerprint = $request->file('fingerprint');
        $profileImage = '';
        $profileFingerPrint = '';

        if ($image) {
            $destinationPath = $root_path.'imt_images/photos/';
            $profileImage = date('YmdHis')."_".$request->card_number. "." . $image->getClientOriginalExtension();
            $image->move($destinationPath, $profileImage);
            $data['photo'] = "$profileImage";
        }
        if ($fingerprint) {
            $destinationPath = $root_path.'imt_images/fingerprints/';
            $profileFingerPrint = date('YmdHis')."_".$request->card_number. "." . $fingerprint->getClientOriginalExtension();
            $fingerprint->move($destinationPath, $profileFingerPrint);
            $data['fingerprint'] = "$profileFingerPrint";
        }
        $photo_display = $root_path.'imt_images\photos\\'.$profileImage;
        $finger_display =  $root_path.'imt_images\fingerprints\\'.$profileFingerPrint;
        $serial_no=$GUID=$request->card_number;
        $dt = date("_ymdHis");
        $str=$GUID.$dt;
        $codeContents = "Name:".$request->name."\n\n".strtoupper(md5($str));
        $encryptedString = strtoupper(md5($str));

        $admin_id = \Auth::guard('admin')->user()->toArray(); 
        $template_id=100;
        $cardDetails=$this->getNextCardNo('IMTC');
        $card_serial_no=$cardDetails->next_serial_no;    
        
        $certName = str_replace("/", "_", $GUID) .".pdf";				
        $myPath = $root_path.'imt\backend\pdf_file'; //with background image
        $fileVerificationPath=$myPath . DIRECTORY_SEPARATOR . $certName;
        $this->addCertificate($serial_no, $certName, $dt,$template_id,$admin_id);

        $username = $admin_id['username'];
        date_default_timezone_set('Asia/Kolkata');
        $print_datetime = date("Y-m-d H:i:s");
        

        $print_count = $this->getPrintCount($serial_no);
        $printer_name = "";
        $print_serial_no = $this->nextPrintSerial();
        $this->addPrintDetails($username, $print_datetime, $printer_name, $print_count, $print_serial_no, $serial_no,'IMTC',$admin_id,$card_serial_no);

        $pdfBig = new PDF('P', 'mm', array('210', '297'), true, 'UTF-8', false);
        $pdfBig::SetAuthor('SSSL');
        $pdfBig::SetTitle('Intifacc Mordern Technology');
        $pdfBig::SetSubject('');

        // remove default header/footer
        $pdfBig::setPrintHeader(false);
        $pdfBig::setPrintFooter(false);
        $pdfBig::SetAutoPageBreak(false, 0);


        // add spot colors
        $pdfBig::AddSpotColor('Spot Red', 30, 100, 90, 10);        // For Invisible
        $pdfBig::AddSpotColor('Spot Dark Green', 100, 50, 80, 45); // clear text on bottom red and in clear text logo
        $pdfBig::AddSpotColor('Spot Light Yellow', 0, 0, 100, 0);
        $pdfBig::AddPage();
        $pdfBig::SetFont('', '', 10, '', false);
        $pdfBig::SetFont('aealarabiya', '', 10);

		$pdfBig::MultiCell(0, 0, '<span style="font-size:10;">MAGACA<br>Name	الاسم</span>', 0, 'L', 0, 0, '15', '111', true, 0, true);
        $pdfBig::MultiCell(0, 0, '<span style="font-size:10;">TAARIIKHDA DHALASHADA<br>	Date of Birth	تاريخ الميلاد</span>', 0, 'L', 0, 0, '15', '122', true, 0, true);
        $pdfBig::MultiCell(0, 0, '<span style="font-size:10;">GOOBTA DHALASHADA<br>	Place of Birth	مكان الميلاد</span>', 0, 'L', 0, 0, '15', '133', true, 0, true);
        $pdfBig::MultiCell(0, 0, '<span style="font-size:10;">LAMBARKA KAARKA AQOONSIGA<br>	ID Card Number	رقم بطاقة الهوية</span>', 0, 'L', 0, 0, '15', '145', true, 0, true);
        $pdfBig::MultiCell(0, 0, '<span style="font-size:10;">JINSI<br>	Sex	الجنس</span>', 0, 'L', 0, 0, '15', '156', true, 0, true);
        $pdfBig::MultiCell(0, 0, '<span style="font-size:10;">XAALADDA GUURKA<br>Marital Status	الحالة الزوجية</span>', 0, 'L', 0, 0, '15', '167', true, 0, true);
        $pdfBig::MultiCell(0, 0, '<span style="font-size:10;">DEGGAN<br>Place of Residence	مكان الاقامة</span>', 0, 'L', 0, 0, '15', '178', true, 0, true);
        $pdfBig::MultiCell(0, 0, '<span style="font-size:10;">MAGACA HOOYADA<br>Name of Mother	اسم الأم</span>', 0, 'L', 0, 0, '15', '189', true, 0, true);
        $pdfBig::MultiCell(0, 0, '<span style="font-size:10;">TAARIIKHDA LA BIXIYAY<br>Date of Issue	تاريخ الاصدار</span>', 0, 'L', 0, 0, '15', '200', true, 0, true);
        $pdfBig::MultiCell(0, 0, '<span style="font-size:10;">SHAQADA<br>Occupation	المهنة</span>', 0, 'L', 0, 0, '15', '211', true, 0, true);

        $pdfBig::SetTextColor(255,255, 0);
        $pdfBig::Text(150, 152, $request->name);
        $pdfBig::Text(158, 163, $request->card_number);
        $pdfBig::SetTextColor(0);
        $pdfBig::MultiCell(0, 0, '<span style="font-size:10;color:blue;">'.$request->name.'</span>', 0, 'L', 0, 0, '80', '111.9', true, 0, true);
        $pdfBig::MultiCell(0, 0, '<span style="font-size:10;color:blue;">'.date("d-m-Y", strtotime($request->dob)).'</span>', 0, 'L', 0, 0, '80', '122.9', true, 0, true);
        $pdfBig::MultiCell(0, 0, '<span style="font-size:10;color:blue;">'.$request->birth_place.'</span>', 0, 'L', 0, 0, '80', '133.9', true, 0, true);
        $pdfBig::MultiCell(0, 0, '<span style="font-size:10;color:blue;">'.$request->card_number.'</span>', 0, 'L', 0, 0, '80', '145.9', true, 0, true);
        $pdfBig::MultiCell(0, 0, '<span style="font-size:10;color:blue;">'.$request->sex.'</span>', 0, 'L', 0, 0, '80', '156.9', true, 0, true);
        $pdfBig::MultiCell(0, 0, '<span style="font-size:10;color:blue;">'.$request->marital_status.'</span>', 0, 'L', 0, 0, '80', '167.9', true, 0, true);
        $pdfBig::MultiCell(0, 0, '<span style="font-size:10;color:blue;">'.$request->address.'</span>', 0, 'L', 0, 0, '80', '178.9', true, 0, true);
        $pdfBig::MultiCell(0, 0, '<span style="font-size:10;color:blue;">'.$request->mother_name.'</span>', 0, 'L', 0, 0, '80', '189.9', true, 0, true);
        $pdfBig::MultiCell(0, 0, '<span style="font-size:10;color:blue;">'.date("d-m-Y", strtotime($request->doi)).'</span>', 0, 'L', 0, 0, '80', '200.9', true, 0, true);
        $pdfBig::MultiCell(0, 0, '<span style="font-size:10;color:blue;">'.$request->occupation.'</span>', 0, 'L', 0, 0, '80', '211.9', true, 0, true);
      
		$pdfBig::image($photo_display,  151.3, 113,38, 37.8,"",'','C',true,3600);
		$pdfBig::setPageMark();	
		$pdfBig::image($finger_display, 151.3, 170,38, 37.8,"",'','C',true,3600); 
		$pdfBig::setPageMark();
				

        $pdfBig::MultiCell(0, 0, '<span style="font-size:10;font-weight: 10px;"><b>Duqa Magaalada<br>The Mayor		الجنس</b></span>', 0, 'C', 0, 0, '10', '225', true, 0, true);
        $pdfBig::MultiCell(0, 0, '<span style="font-size:10;font-weight: 10px;"><b>Yuusuf Xvseen Jimcaale<br>Signature</b></span>', 0, 'C', 0, 0, '10', '235', true, 0, true);
         //1D Barcode
        $style1Da = array(
            'position' => '',
            'align' => 'C',
            'stretch' => true,
            'fitwidth' => true,
            'cellfitalign' => '',
            'border' => false,
            'hpadding' => 'auto',
            'vpadding' => 'auto',
            'fgcolor' => array(0,0,0),
            'bgcolor' => false, //array(255,255,255),
            'text' => true,
            'font' => 'helvetica',
            'fontsize' => 9,
            'stretchtext' => 7
        );
        $barcodex = 12;
        $barcodey = 257;
        $barcodeWidth = 56;
        $barodeHeight = 13;
        $pdfBig::write1DBarcode($print_serial_no, 'C39', $barcodex, $barcodey, $barcodeWidth, $barodeHeight, 0.4, $style1Da, 'N');

        
        $pdfBig::write2DBarcode($encryptedString, 'QRCODE,Q', 155, 210, 30, 200, $style1Da, 'N');
        
        $certName1 = str_replace("/", "_", $GUID) .".pdf";        
        $myPath1 = $root_path.'imt\backend\tcpdf\examples'; //edit the path
        $fileVerificationPath=$myPath . DIRECTORY_SEPARATOR . $certName1;
        $pdfBig::output($myPath1 . DIRECTORY_SEPARATOR . $certName1, 'F');

        PDF::reset();
        $pdf = new PDF('P', 'mm', array('210', '297'), true, 'UTF-8', false);
        $pdf::SetAuthor('SSSL');
        $pdf::SetTitle('Intifacc Mordern Technology');
        $pdf::SetSubject('');

        // remove default header/footer
        $pdf::setPrintHeader(false);
        $pdf::setPrintFooter(false);
        $pdf::SetAutoPageBreak(false, 0);


        // add spot colors
        $pdf::AddSpotColor('Spot Red', 30, 100, 90, 10);        // For Invisible
        $pdf::AddSpotColor('Spot Dark Green', 100, 50, 80, 45); // clear text on bottom red and in clear text logo
        $pdf::AddSpotColor('Spot Light Yellow', 0, 0, 100, 0);

        //set background image
        $root_path = public_path().'\\'.$subdomain[0];
        $template_img_generate = $root_path.'\imt\backend\canvas\bg_images\background.png';

        $pdf::AddPage();
        $pdf::SetFont('', '', 10, '', false);
        $pdf::SetFont('aealarabiya', '', 10);
        $pdf::Image($template_img_generate, 0, 0, '210', '297', "PNG", '', 'R', true);

		$pdf::MultiCell(0, 0, '<span style="font-size:10;">MAGACA<br>Name	الاسم</span>', 0, 'L', 0, 0, '15', '111', true, 0, true);
        $pdf::MultiCell(0, 0, '<span style="font-size:10;">TAARIIKHDA DHALASHADA<br>	Date of Birth	تاريخ الميلاد</span>', 0, 'L', 0, 0, '15', '122', true, 0, true);
        $pdf::MultiCell(0, 0, '<span style="font-size:10;">GOOBTA DHALASHADA<br>	Place of Birth	مكان الميلاد</span>', 0, 'L', 0, 0, '15', '133', true, 0, true);
        $pdf::MultiCell(0, 0, '<span style="font-size:10;">LAMBARKA KAARKA AQOONSIGA<br>	ID Card Number	رقم بطاقة الهوية</span>', 0, 'L', 0, 0, '15', '145', true, 0, true);
        $pdf::MultiCell(0, 0, '<span style="font-size:10;">JINSI<br>	Sex	الجنس</span>', 0, 'L', 0, 0, '15', '156', true, 0, true);
        $pdf::MultiCell(0, 0, '<span style="font-size:10;">XAALADDA GUURKA<br>Marital Status	الحالة الزوجية</span>', 0, 'L', 0, 0, '15', '167', true, 0, true);
        $pdf::MultiCell(0, 0, '<span style="font-size:10;">DEGGAN<br>Place of Residence	مكان الاقامة</span>', 0, 'L', 0, 0, '15', '178', true, 0, true);
        $pdf::MultiCell(0, 0, '<span style="font-size:10;">MAGACA HOOYADA<br>Name of Mother	اسم الأم</span>', 0, 'L', 0, 0, '15', '189', true, 0, true);
        $pdf::MultiCell(0, 0, '<span style="font-size:10;">TAARIIKHDA LA BIXIYAY<br>Date of Issue	تاريخ الاصدار</span>', 0, 'L', 0, 0, '15', '200', true, 0, true);
        $pdf::MultiCell(0, 0, '<span style="font-size:10;">SHAQADA<br>Occupation	المهنة</span>', 0, 'L', 0, 0, '15', '211', true, 0, true);

        $pdf::SetTextColor(255,255, 0);
        $pdf::Text(150, 152, $request->name);
        $pdf::Text(158, 163, $request->card_number);
        $pdf::SetTextColor(0);
        $pdf::MultiCell(0, 0, '<span style="font-size:10;color:blue;">'.$request->name.'</span>', 0, 'L', 0, 0, '80', '111.9', true, 0, true);
        $pdf::MultiCell(0, 0, '<span style="font-size:10;color:blue;">'.date("d-m-Y", strtotime($request->dob)).'</span>', 0, 'L', 0, 0, '80', '122.9', true, 0, true);
        $pdf::MultiCell(0, 0, '<span style="font-size:10;color:blue;">'.$request->birth_place.'</span>', 0, 'L', 0, 0, '80', '133.9', true, 0, true);
        $pdf::MultiCell(0, 0, '<span style="font-size:10;color:blue;">'.$request->card_number.'</span>', 0, 'L', 0, 0, '80', '145.9', true, 0, true);
        $pdf::MultiCell(0, 0, '<span style="font-size:10;color:blue;">'.$request->sex.'</span>', 0, 'L', 0, 0, '80', '156.9', true, 0, true);
        $pdf::MultiCell(0, 0, '<span style="font-size:10;color:blue;">'.$request->marital_status.'</span>', 0, 'L', 0, 0, '80', '167.9', true, 0, true);
        $pdf::MultiCell(0, 0, '<span style="font-size:10;color:blue;">'.$request->address.'</span>', 0, 'L', 0, 0, '80', '178.9', true, 0, true);
        $pdf::MultiCell(0, 0, '<span style="font-size:10;color:blue;">'.$request->mother_name.'</span>', 0, 'L', 0, 0, '80', '189.9', true, 0, true);
        $pdf::MultiCell(0, 0, '<span style="font-size:10;color:blue;">'.date("d-m-Y", strtotime($request->doi)).'</span>', 0, 'L', 0, 0, '80', '200.9', true, 0, true);
        $pdf::MultiCell(0, 0, '<span style="font-size:10;color:blue;">'.$request->occupation.'</span>', 0, 'L', 0, 0, '80', '211.9', true, 0, true);

        $pdf::image($photo_display,151.3, 113,38, 37.8,"",'','C',true,3600);
		$pdf::image($finger_display,151.3, 170,38, 37.8,"",'','C',true,3600);

        $pdf::MultiCell(0, 0, '<span style="font-size:10;font-weight: 10px;"><b>Duqa Magaalada<br>The Mayor		الجنس</b></span>', 0, 'C', 0, 0, '10', '225', true, 0, true);
        $pdf::MultiCell(0, 0, '<span style="font-size:10;font-weight: 10px;"><b>Yuusuf Xvseen Jimcaale<br>Signature</b></span>', 0, 'C', 0, 0, '10', '235', true, 0, true);
         //1D Barcode
        $style1Da = array(
            'position' => '',
            'align' => 'C',
            'stretch' => true,
            'fitwidth' => true,
            'cellfitalign' => '',
            'border' => false,
            'hpadding' => 'auto',
            'vpadding' => 'auto',
            'fgcolor' => array(0,0,0),
            'bgcolor' => false, //array(255,255,255),
            'text' => true,
            'font' => 'helvetica',
            'fontsize' => 9,
            'stretchtext' => 7
        );
        $barcodex = 12;
        $barcodey = 257;
        $barcodeWidth = 56;
        $barodeHeight = 13;
        $pdf::write1DBarcode($print_serial_no, 'C39', $barcodex, $barcodey, $barcodeWidth, $barodeHeight, 0.4, $style1Da, 'N');

        

        $pdf::write2DBarcode($encryptedString, 'QRCODE,Q', 155, 210, 30, 200, $style1Da, 'N');

        
        
        $pdf::output($myPath . DIRECTORY_SEPARATOR . $certName, 'F');
        
        $data->file = $certName;
        $data->save();
        $link = '/imt/backend/tcpdf/examples/'.$certName;
       
        
        return view('admin.imt.show',['link' => $link]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        return view('admin.imt.show');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function addCertificate($serial_no, $certName, $dt,$template_id,$admin_id)
    {

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $file1 = public_path().'/backend/temp_pdf_file/'.$certName;
        $file2 = public_path().'/backend/pdf_file/'.$certName;
        
        //Updated by Mandar for api based pdf generation
        if(Auth::guard('admin')->user()){
            $auth_site_id=Auth::guard('admin')->user()->site_id;
        }else{
            $auth_site_id=$this->pdf_data['auth_site_id'];
        } 

        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();

        $pdfActualPath=public_path().'/'.$subdomain[0].'/backend/pdf_file/'.$certName;

        /*copy($file1, $file2);        
        $aws_qr = \File::copy($file2,$pdfActualPath);
        @unlink($file2);
		$source=\Config::get('constant.directoryPathBackward')."\\backend\\temp_pdf_file\\".$certName;
		$output=\Config::get('constant.directoryPathBackward').$subdomain[0]."\\backend\\pdf_file\\".$certName; 
		CoreHelper::compressPdfFile($source,$output);
        @unlink($file1);*/

        //Sore file on azure server

        $sts = '1';
        $datetime  = date("Y-m-d H:i:s");
        $ses_id  = $admin_id["id"];
        $certName = str_replace("/", "_", $certName);

        $get_config_data = Config::select('configuration')->first();
     
        $c = explode(", ", $get_config_data['configuration']);
        $key = "";


        $tempDir = public_path().'/backend/qr';
        $key = strtoupper(md5($serial_no.$dt)); 
        $codeContents = $key;
        $fileName = $key.'.png'; 
        
        $urlRelativeFilePath = 'qr/'.$fileName; 

        if($systemConfig['sandboxing'] == 1){
        $resultu = SbStudentTable::where('serial_no','T-'.$serial_no)->update(['status'=>'0']);
        // Insert the new record
        
        $result = SbStudentTable::create(['serial_no'=>'T-'.$serial_no,'certificate_filename'=>$certName,'template_id'=>$template_id,'key'=>$key,'path'=>$urlRelativeFilePath,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id]);
        }else{
        $resultu = StudentTable::where('serial_no',''.$serial_no)->update(['status'=>'0']);
        // Insert the new record
        
        $result = StudentTable::create(['serial_no'=>$serial_no,'certificate_filename'=>$certName,'template_id'=>$template_id,'key'=>$key,'path'=>$urlRelativeFilePath,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'template_type'=>2]);
        }
        
    }   

    public function getPrintCount($serial_no)
    {
        //Updated by Mandar for api based pdf generation
        if(Auth::guard('admin')->user()){
            $auth_site_id=Auth::guard('admin')->user()->site_id;
        }else{
            $auth_site_id=$this->pdf_data['auth_site_id'];
        }
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
        $numCount = PrintingDetail::select('id')->where('sr_no',$serial_no)->count();
        
        return $numCount + 1;
    
}    

    public function addPrintDetails($username, $print_datetime, $printer_name, $printer_count, $print_serial_no, $sr_no,$template_name,$admin_id,$card_serial_no)
    {
       
        $sts = 1;
        $datetime = date("Y-m-d H:i:s");
        $ses_id = $admin_id["id"];

        //Updated by Mandar for api based pdf generation
        if(Auth::guard('admin')->user()){
            $auth_site_id=Auth::guard('admin')->user()->site_id;
        }else{
            $auth_site_id=$this->pdf_data['auth_site_id'];
        }

        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();

        if($systemConfig['sandboxing'] == 1){
        $result = PrintingDetail::create(['username'=>$username,'print_datetime'=>$print_datetime,'printer_name'=>$printer_name,'print_count'=>$printer_count,'print_serial_no'=>$print_serial_no,'sr_no'=>'T-'.$sr_no,'template_name'=>$template_name,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'publish'=>1]);
        }else{
        $result = PrintingDetail::create(['username'=>$username,'print_datetime'=>$print_datetime,'printer_name'=>$printer_name,'print_count'=>$printer_count,'print_serial_no'=>$print_serial_no,'sr_no'=>$sr_no,'template_name'=>$template_name,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'publish'=>1]);    
        }
    }    

    public function nextPrintSerial()
    {
        $current_year = 'PN/' . $this->getFinancialyear() . '/';
        // find max
        $maxNum = 0;
        
        //Updated by Mandar for api based pdf generation
        if(Auth::guard('admin')->user()){
            $auth_site_id=Auth::guard('admin')->user()->site_id;
        }else{
            $auth_site_id=$this->pdf_data['auth_site_id'];
        }

        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
        $result = \DB::select("SELECT COALESCE(MAX(CONVERT(SUBSTR(print_serial_no, 10), UNSIGNED)), 0) AS next_num "
                . "FROM printing_details WHERE SUBSTR(print_serial_no, 1, 9) = '$current_year'");
        //get next num
        $maxNum = $result[0]->next_num + 1;
       
        return $current_year . $maxNum;
    }

    public function getFinancialyear()
    {
        $yy = date('y');
        $mm = date('m');
        $fy = str_pad($yy, 2, "0", STR_PAD_LEFT);
        if($mm > 3)
            $fy = $fy . "-" . ($yy + 1);
        else
            $fy = str_pad($yy - 1, 2, "0", STR_PAD_LEFT) . "-" . $fy;
        return $fy;
    }

    public function getNextCardNo($template_name)
    { 
        //Updated by Mandar for api based pdf generation
        if(Auth::guard('admin')->user()){
            $auth_site_id=Auth::guard('admin')->user()->site_id;
        }else{
            $auth_site_id=$this->pdf_data['auth_site_id'];
        
        }
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();

        if($systemConfig['sandboxing'] == 1){
        $result = \DB::select("SELECT * FROM sb_card_serial_numbers WHERE template_name = '$template_name'");
        }else{
        $result = \DB::select("SELECT * FROM card_serial_numbers WHERE template_name = '$template_name'");
        }
          
        return $result[0];
    }

    public function updateCardNo($template_name,$count,$next_serial_no)
    { 
        //Updated by Mandar for api based pdf generation
        if(Auth::guard('admin')->user()){
            $auth_site_id=Auth::guard('admin')->user()->site_id;
        }else{
            $auth_site_id=$this->pdf_data['auth_site_id'];
        }

        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
        if($systemConfig['sandboxing'] == 1){
        $result = \DB::select("UPDATE sb_card_serial_numbers SET card_count='$count',next_serial_no='$next_serial_no' WHERE template_name = '$template_name'");
        }else{
        $result = \DB::select("UPDATE card_serial_numbers SET card_count='$count',next_serial_no='$next_serial_no' WHERE template_name = '$template_name'");
        }
        
        return $result;
    }
}
