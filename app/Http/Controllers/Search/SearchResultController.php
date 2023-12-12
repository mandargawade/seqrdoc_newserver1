<?php
/**
 *
 *  Author : Ketan valand 
 *   Date  : 24/12/2019
 *   Use   : listing of User & create new user and update
 *
**/
namespace App\Http\Controllers\Search;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Models\DiplomaCMS;
use App\models\DiplomaMET;
use Auth;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class SearchResultController extends Controller
{
    /**
     * Display a listing of the Users.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        
        if($request['course'] == 'MET')
        {
            $result = DiplomaMET::select('id','faculty','department','year_of_study','academic_year','award','campus','name','college_number','gender','mathematics','electronics','hospital_plant_&_building_services','measurement_&_control','clinical_engineering_i','clinical_engineering_ii','field_attachment','trade_project','research_project','total_marks_(written_and_practical/900)','exam_marks_(60%)','cats_(40%)','total_(%)','remarks')->where('college_number', $request['college_number'])->get()->toArray();


        }else{
            $result = DiplomaCMS::select('id','faculty','department','year_of_study','academic_year','award','campus','name','college_number','gender','medicine_t','medicine_p','paediatrics_t','paediatrics_p','surgery_t','surgery_p','reproductive_health_t','reproductive_health_p','community_health_t','community_health_p','health_systems_management_t','health_systems_management_p','research_project','total_marks_(written_&_practical/1300)','exam_marks_(60%)','cats_(40%)','total_(%)','remarks')->where('college_number', $request['college_number'])->get()->toArray();
        }

        if($result){
            $response_html = '';
            if($request['course'] == 'MET'){
                $response_html = "<table>
                                      <tr>
                                        <th>FACULTY</th>
                                        <th>DEPARTMENT</th>
                                        <th>YEAR OF STUDY</th>
                                        <th>ACADEMIC YEAR</th>
                                        <th>AWARD</th>
                                        <th>CAMPUS</th>
                                        <th>NAME</th>
                                        <th>COLLEGE NUMBER</th>
                                        <th>GENDER</th>
                                        <th>MATHEMATICS</th>
                                        <th>ELECTRONICS</th>
                                        <th>HOSPITAL PLANT & BUILDING SERVICES</th>
                                        <th>MEASUREMENT & CONTROL</th>
                                        <th>CLINICAL ENGINEERING I</th>
                                        <th>CLINICAL ENGINEERING II</th>
                                        <th>FIELD ATTACHMENT</th>
                                        <th>TRADE PROJECT</th>
                                        <th>RESEARCH PROJECT</th>
                                        <th>TOTAL MARKS (WRITTEN AND PRACTICAL/900)</th>
                                        <th>EXAM MARKS (60%)</th>
                                        <th>CATs (40%)</th>
                                        <th>TOTAL (%)</th>
                                        <th>REMARKS</th>
                                      </tr>
                                      <tr>
                                            <td>".$result[0]['faculty']."</td>
                                            <td>".$result[0]['department']."</td>
                                            <td>".$result[0]['year_of_study']."</td>
                                            <td>".$result[0]['academic_year']."</td>
                                            <td>".$result[0]['award']."</td>
                                            <td>".$result[0]['campus']."</td>
                                            <td>".$result[0]['name']."</td>
                                            <td>".$result[0]['college_number']."</td>
                                            <td>".$result[0]['gender']."</td>
                                            <td>".$result[0]['mathematics']."</td>
                                            <td>".$result[0]['electronics']."</td>
                                            <td>".$result[0]['hospital_plant_&_building_services']."</td>
                                            <td>".$result[0]['measurement_&_control']."</td>
                                            <td>".$result[0]['clinical_engineering_i']."</td>
                                            <td>".$result[0]['clinical_engineering_ii']."</td>
                                            <td>".$result[0]['field_attachment']."</td>
                                            <td>".$result[0]['trade_project']."</td>
                                            <td>".$result[0]['research_project']."</td>
                                            <td>".$result[0]['total_marks_(written_and_practical/900)']."</td>
                                            <td>".$result[0]['exam_marks_(60%)']."</td>
                                            <td>".$result[0]['cats_(40%)']."</td>
                                            <td>".$result[0]['total_(%)']."</td>
                                            <td>".$result[0]['remarks']."</td>
                                   </tr>
                                </table>   ";
            }else{
                $response_html = "<table>
                                      <tr>
                                            <th>FACULTY</th>
                                            <th>DEPARTMENT</th>
                                            <th>YEAR OF STUDY</th>
                                            <th>ACADEMIC YEAR</th>
                                            <th>AWARD</th>
                                            <th>CAMPUS</th>
                                            <th>NAME</th>
                                            <th>COLLEGE NUMBER</th>
                                            <th>GENDER</th>
                                            <th>MEDICINE T</th>
                                            <th>MEDICINE P</th>
                                            <th>PAEDIATRICS T</th>
                                            <th>PAEDIATRICS P</th>
                                            <th>SURGERY T</th>
                                            <th>SURGERY P</th>
                                            <th>REPRODUCTIVE HEALTH T</th>
                                            <th>REPRODUCTIVE HEALTH P</th>
                                            <th>COMMUNITY HEALTH T</th>
                                            <th>COMMUNITY HEALTH P</th>
                                            <th>HEALTH SYSTEMS MANAGEMENT T</th>
                                            <th>HEALTH SYSTEMS MANAGEMENT P</th>
                                            <th>RESEARCH PROJECT</th>
                                            <th>TOTAL MARKS (WRITTEN & PRACTICAL/1300)</th>
                                            <th>EXAM MARKS (60%)</th>
                                            <th>CATs (40%)</th>
                                            <th>TOTAL (%)</th>
                                            <th>REMARKS</th>
                                      </tr>
                                      <tr>
                                            <td>".$result[0]['faculty']."</td>
                                            <td>".$result[0]['department']."</td>
                                            <td>".$result[0]['year_of_study']."</td>
                                            <td>".$result[0]['academic_year']."</td>
                                            <td>".$result[0]['award']."</td>
                                            <td>".$result[0]['campus']."</td>
                                            <td>".$result[0]['name']."</td>
                                            <td>".$result[0]['college_number']."</td>
                                            <td>".$result[0]['gender']."</td>
                                            <td>".$result[0]['medicine_t']."</td>
                                            <td>".$result[0]['medicine_p']."</td>
                                            <td>".$result[0]['paediatrics_t']."</td>
                                            <td>".$result[0]['paediatrics_p']."</td>
                                            <td>".$result[0]['surgery_t']."</td>
                                            <td>".$result[0]['surgery_p']."</td>
                                            <td>".$result[0]['reproductive_health_t']."</td>
                                            <td>".$result[0]['reproductive_health_p']."</td>
                                            <td>".$result[0]['community_health_t']."</td>
                                            <td>".$result[0]['community_health_p']."</td>
                                            <td>".$result[0]['health_systems_management_t']."</td>
                                            <td>".$result[0]['health_systems_management_p']."</td>
                                            <td>".$result[0]['research_project']."</td>
                                            <td>".$result[0]['total_marks_(written_&_practical/1300)']."</td>
                                            <td>".$result[0]['exam_marks_(60%)']."</td>
                                            <td>".$result[0]['cats_(40%)']."</td>
                                            <td>".$result[0]['total_(%)']."</td>
                                            <td>".$result[0]['remarks']."</td>
                                      </tr>
                                </table>";
            }

            $response_html .= '<style>
                                table {
                                  font-family: arial, sans-serif;
                                  border-collapse: collapse;
                                  width: 100%;
                                }

                                td, th {
                                  border: 1px solid #dddddd;
                                  text-align: left;
                                  padding: 8px;
                                }

                                tr:nth-child(even) {
                                  backgr';

            $message = array('type' => 'success', 'message' => $response_html);
        }else{
            $message = array('type' => 'fail', 'message' => 'OOPS..Record Not Find.!');
        }

        echo json_encode($message);
        
    }

}
