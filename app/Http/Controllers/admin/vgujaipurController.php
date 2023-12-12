<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Session,TCPDF,TCPDF_FONTS,Auth,DB,PDF;

class vgujaipurController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('admin.vgujaipur.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.vgujaipur.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $start = $request->start;
        $qty = $request->qty;
        $pdfBig = new PDF('P', 'mm', array('210', '297'), true, 'UTF-8', false);
        $pdfBig::SetAuthor('SSSL');
        $pdfBig::SetTitle('Vivekananda Global University');
        $pdfBig::SetSubject('');

        // remove default header/footer
        $pdfBig::setPrintHeader(false);
        $pdfBig::setPrintFooter(false);
        $pdfBig::SetAutoPageBreak(false, 0);


        // add spot colors
        $pdfBig::AddSpotColor('Spot Red', 30, 100, 90, 10);        // For Invisible
        $pdfBig::AddSpotColor('Spot Dark Green', 100, 50, 80, 45); // clear text on bottom red and in clear text logo
        $pdfBig::AddSpotColor('Spot Light Yellow', 0, 0, 100, 0);
        
        $x = 155;
        $y = 15;
        $a = 182;
        for ($i=1; $i <=  $qty ; $i++) {
            
        $pdfBig::AddPage();
            $pdfBig::SetXY($x,$y);
            $pdfBig::SetFont('Arial', 'B', 10, '', false);
            $pdfBig::Cell(0, 0, 'Serial Number : ', 0, false, 'L');
            $pdfBig::SetXY($a,$y);
            $pdfBig::SetFont('Arial', '', 10, '', false);
            $pdfBig::Cell(0, 0, $start++, 0, false, 'L');

        }
        $pdfBig::output('output','I');

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
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
}
