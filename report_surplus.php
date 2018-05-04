<?php
/*	Template file for creating Excel based reports
	
	Basically just the setup of the front page for consistency
*/

	require_once "db.inc.php";
	require_once "facilities.inc.php";
	require_once "vendor/autoload.php";

	$person = People::Current();


	if ( isset( $_REQUEST['action'])) {
		$disp = $_REQUEST['dispositionid'];
		if ( $disp == 0 ) {
			$dispList = Disposition::getDisposition( null, true );
			$dispDesc = __("All Mechanisms");
		} else {
			$dispList = Disposition::getDisposition( $disp, true );
			$dispDesc = $dispList[0]->Name;
		}

		if ( isset( $_REQUEST['startdate'] ) && $_REQUEST['startdate'] != "" ) { 
			$startDate = date( "Y-m-d", strtotime( $_REQUEST['startdate'] ));
			$startDesc = $startDate;
		} else {
			$startDate = "";
			$startDesc = __("Beginning of epoch");
		}

		if ( isset( $_REQUEST['enddate']) && $_REQUEST['enddate'] != "" ) {
			$endDate = date( "Y-m-d", strtotime( $_REQUEST['enddate'] ));
			$endDesc = $endDate;
		} else {
			$endDate = "";
			$endDesc = __("Now");
		}

        $dep = new Department();
        $depList = $dep->Search( true );

        $man = new Manufacturer();
        $manList = $man->GetManufacturerList( true );

        $dev = new Device();
        $dt = new DeviceTemplate();

		$workBook = new PHPExcel();
		
		$workBook->getProperties()->setCreator("openDCIM");
		$workBook->getProperties()->setLastModifiedBy("openDCIM");
		$workBook->getProperties()->setTitle("Data Center Inventory Export");
		$workBook->getProperties()->setSubject("Data Center Inventory Export");
		$workBook->getProperties()->setDescription("Export of the openDCIM database based upon user filtered criteria.");
		
		// Start off with the TPS Cover Page

		$workBook->setActiveSheetIndex(0);
		$sheet = $workBook->getActiveSheet();

	    $sheet->SetTitle('Front Page');
	    // add logo
	    $objDrawing = new PHPExcel_Worksheet_Drawing();
	    $objDrawing->setWorksheet($sheet);
	    $objDrawing->setName("Logo");
	    $objDrawing->setDescription("Logo");
	    $apath = __DIR__ . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR;
	    $objDrawing->setPath($apath . $config->ParameterArray['PDFLogoFile']);
	    $objDrawing->setCoordinates('A1');
	    $objDrawing->setOffsetX(5);
	    $objDrawing->setOffsetY(5);

	    $logoHeight = getimagesize( $apath . $config->ParameterArray['PDFLogoFile']);
	    $sheet->getRowDimension('1')->setRowHeight($logoHeight[1]);

	    // set the header of the print out
	    $header_range = "A1:B2";
	    $fillcolor = $config->ParameterArray['HeaderColor'];
	    $fillcolor = (strpos($fillcolor, '#') == 0) ? substr($fillcolor, 1) : $fillcolor;
	    $sheet->getStyle($header_range)
	        ->getFill()
	        ->getStartColor()
	        ->setRGB($fillcolor);

	    $org_font_size = 20;
	    $sheet->setCellValue('A2', $config->ParameterArray['OrgName']);
	    $sheet->getStyle('A2')
	        ->getFont()
	        ->setSize($org_font_size);
	    $sheet->getStyle('A2')
	        ->getFont()
	        ->setBold(true);
	    $sheet->getRowDimension('2')->setRowHeight($org_font_size + 2);
	    $sheet->setCellValue('A4', 'Report generated by \''
	        . $person->UserID
	        . '\' on ' . date('Y-m-d H:i:s'));

	    // Add text about the report itself
	    $sheet->setCellValue('A7', 'Notes');
	    $sheet->getStyle('A7')
	        ->getFont()
	        ->setSize(14);
	    $sheet->getStyle('A7')
	        ->getFont()
	        ->setBold(true);

	    $remarks = array( __("This report contains information about items that have been disposed/removed from the data center."),
	    		__("Each item is disosed through a specific mechanism or contract vehicle."),
	    		__("Criteria for this report:"), $dispDesc
	    		, __("Date Range:") . " $startDesc to $endDesc" );
	    $max_remarks = count($remarks);
	    $offset = 8;
	    for ($idx = 0; $idx < $max_remarks; $idx ++) {
	        $row = $offset + $idx;
	        $sheet->setCellValueExplicit('B' . ($row),
	            $remarks[$idx],
	            PHPExcel_Cell_DataType::TYPE_STRING);
	    }
	    $sheet->getStyle('B' . $offset . ':B' . ($offset + $max_remarks - 1))
	        ->getAlignment()
	        ->setWrapText(true);
	    $sheet->getColumnDimension('B')->setWidth(120);
	    $sheet->getTabColor()->setRGB($fillcolor);

	    // Now the real data for the report
	    $columnList = array( "Label"=>"A", "SerialNumber"=>"B", "AssetTag"=>"C", "Manufacturer"=>"D", "Model"=>"E", "Disposal Date"=>"F", "Disposed By"=>"G" );

	    foreach ( $dispList as $disp ) {
			$sheet = $workBook->createSheet();
			$sheet->setTitle( $disp->Name );

			$sheet->setCellValue( "B1", __("Disposition Mechanism"));
			$sheet->setCellValue( "A2", __("Name"));
			$sheet->setCellValue( "B2", $disp->Name );
			$sheet->setCellValue( "A3", __("Description"));
			$sheet->mergeCells( "B3:G3");
			$sheet->getStyle( "B3" )->getAlignment()->setWrapText(true);
			$sheet->setCellValue( "B3", $disp->Description );
			$sheet->setCellValue( "A4", __("Reference Number"));
			$sheet->setCellValue( "B4", $disp->ReferenceNumber );
			$sheet->setCellValue( "A5", __("Status"));
			$sheet->setCellValue( "B5", $disp->Status );


            foreach( $columnList as $fieldName=>$columnName ) {
                $cellAddr = $columnName."7";
  
                $sheet->setCellValue( $cellAddr, $fieldName );
            }
        
        	$currRow = 8;

        	$dispDevList = DispositionMembership::getDevices( $disp->DispositionID );
        	foreach( $dispDevList as $d ) {
        		if (( $startDate == "" || strtotime($startDate)<=strtotime($d->DispositionDate)) && ( $endDate == "" || strtotime($endDate)>=strtotime($d->DispositionDate))) {
	        		$dev->DeviceID = $d->DeviceID;
	        		$dev->GetDevice();

	                $sheet->setCellValue( $columnList["Label"].$currRow, $dev->Label );
	                $sheet->setCellValue( $columnList["SerialNumber"].$currRow, $dev->SerialNo );
	                $sheet->setCellValue( $columnList["AssetTag"].$currRow, $dev->AssetTag );

	                if ( $dev->TemplateID > 0 ) {
		                $dt->TemplateID = $dev->TemplateID;
		                $dt->GetTemplateByID();

		                $sheet->setCellValue( $columnList["Manufacturer"].$currRow, $manList[$dt->ManufacturerID]->Name );
		                $sheet->setCellValue( $columnList["Model"].$currRow, $dt->Model );
		            }

	                $sheet->setCellValue( $columnList["Disposal Date"].$currRow, $d->DispositionDate );
	                $sheet->setCellValue( $columnList["Disposed By"].$currRow, $d->DisposedBy );

	                $currRow++;
	            }
        	}

			// Autosize the columns
            foreach( $columnList as $i => $v ) {
                $sheet->getColumnDimension($v)->setAutoSize(true);
            }
		}			
		// Now finalize it and send to the client

		$workBook->setActiveSheetIndex(0);

		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header( sprintf( "Content-Disposition: attachment;filename=\"opendcim-%s.xlsx\"", date( "YmdHis" ) ) );
		
		$writer = new PHPExcel_Writer_Excel2007($workBook);
		$writer->save('php://output');

	} else {
		$dispList = Disposition::getDisposition();
?>
<!doctype html>
<html>
<head>
  <meta http-equiv="X-UA-Compatible" content="IE=Edge">
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  
  <title>GSPE DCIM Inventory Reporting</title>
  <link rel="stylesheet" href="css/inventory.php" type="text/css">
  <link rel="stylesheet" href="css/jquery-ui.css" type="text/css">
  <link rel="stylesheet" href="css/validationEngine.jquery.css" type="text/css">
  <!--[if lt IE 9]>
  <link rel="stylesheet"  href="css/ie.css" type="text/css" />
  <![endif]-->
  <script type="text/javascript" src="scripts/jquery.min.js"></script>
  <script type="text/javascript" src="scripts/jquery-ui.min.js"></script>
  <script type="text/javascript" src="scripts/jquery.timepicker.js"></script>
  <script type="text/javascript" src="scripts/jquery.validationEngine-en.js"></script>
  <script type="text/javascript" src="scripts/jquery.validationEngine.js"></script>

<script type="text/javascript">
$(function(){
	$('#auditform').validationEngine({});
	$('#startdate').datepicker({dateFormat: "yy-mm-dd"});
	$('#enddate').datepicker({dateFormat: "yy-mm-dd"});
});
</script>

</head>
<body>
<?php include( 'header.inc.php' ); ?>
<div class="page">
<?php
	include( 'sidebar.inc.php' );
echo '<div class="main" style="box-shadow: 10px 10px #1d388c;">
<div class="center"><div>
<h2>',__("Device Disposition Report"),'</h2>
<form method="post" id="auditform">
<div class="table">
	<div>
		<div><label for="dispositionid">',__("Disposal Mechanism"),':</div>
		<div><select name="dispositionid" id="dispositionid"><option value="0">All Mechanisms</option>';

	foreach( $dispList as $disp ) {
		print "<option value=\"$disp->DispositionID\">$disp->Name</option>\n";
	}

	echo '</select>
		</div>
	</div>
	<div>
		<div><label for="startdate">Start Date:</label></div>
		<div><input type="text" id="startdate" name="startdate"></div>
	</div>
	<div>
		<div><label for="enddate">End Date:</label></div>
		<div><input type="text" id="enddate" name="enddate"></div>
	</div>
	<div class="caption">
		<input type="submit" value="Generate" name="action">
	</div>
</div>
</form>

</div></div>
</div><!-- END div.main -->
</div><!-- END div.page -->
</body>
</html>';
}
?>