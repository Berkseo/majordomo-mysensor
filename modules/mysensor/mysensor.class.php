<?php
/**
 * MySesnor
 *
 * MySesnor
 *
 * @package project
 * @author Ivan Z. <ivan@jad.ru>
 * @copyright http://www.pstd.ru/ (c)
 * @version 0.1 (wizard, 9:33[Jul 08, 2015])
 */

include_once("intelhex.php");
include_once("crcs.php");
 
class mysensor extends module {
	public $tryTimeout = 2; // 2 second
	public $RxExpireTimeout = 21600; // 6*60*60 = 6 hours
	public $MY_CORE_MIN_VERSION = 2;
	public $node_bins = array();
	public $MySensor;	
	
	/**
	 * mySensor
	 *
	 * Module class constructor
	 *
	 * @access private
	 *        
	 */
	function mysensor() {		
		$this->name = "mysensor";
		$this->title = "MySensor";
		$this->module_category = "<#LANG_SECTION_DEVICES#>";
		$this->checkInstalled ();
	}
	
	/**
	 * saveParams
	 *
	 * Saving module parameters
	 *
	 * @access public
	 *        
	 */
	function saveParams($data = 1) {
		$p = array ();
		if (IsSet( $this->id )) {
			$p["id"] = $this->id;
		}
		if (IsSet( $this->view_mode )) {
			$p["view_mode"] = $this->view_mode;
		}
		if (IsSet( $this->edit_mode )) {
			$p["edit_mode"] = $this->edit_mode;
		}
		if (IsSet( $this->data_souce )) {
			$p["data_source"] = $this->data_source;
		}
		if (IsSet( $this->tab )) {
			$p["tab"] = $this->tab;
		}
		return parent::saveParams( $p );
	}
	/**
	 * getParams
	 *
	 * Getting module parameters from query string
	 *
	 * @access public
	 *        
	 */
	function getParams() {
		global $id;
		global $mode;
		global $view_mode;
		global $edit_mode;
		global $data_source;
		global $tab;
		if (isset( $id )) {
			$this->id = $id;
		}
		if (isset( $mode )) {
			$this->mode = $mode;
		}
		if (isset( $view_mode )) {
			$this->view_mode = $view_mode;
		}
		if (isset( $edit_mode )) {
			$this->edit_mode = $edit_mode;
		}
		if (isset( $data_source )) {
			$this->data_source = $data_source;
		}
		if (isset( $tab )) {
			$this->tab = $tab;
		}
	}
	/**
	 * Run
	 *
	 * Description
	 *
	 * @access public
	 *        
	 */
	function run() {
		global $session;
		
		$out = array ();
		if ($this->action == 'admin') {
			$this->admin( $out );
		} else {
			$this->usual( $out );
		}
		if (IsSet( $this->owner->action )) {
			$out['PARENT_ACTION'] = $this->owner->action;
		}
		if (IsSet( $this->owner->name )) {
			$out['PARENT_NAME'] = $this->owner->name;
		}
		$out['VIEW_MODE'] = $this->view_mode;
		$out['EDIT_MODE'] = $this->edit_mode;
		$out['MODE'] = $this->mode;
		$out['ACTION'] = $this->action;
		$out['DATA_SOURCE'] = $this->data_source;
		$out['TAB'] = $this->tab;
		if ($this->single_rec) {
			$out['SINGLE_REC'] = 1;
		}
		$this->data = $out;
		$p = new parser( DIR_TEMPLATES . $this->name . "/" . $this->name . ".html", $this->data, $this );
		$this->result = $p->result;
	}
	/**
	 * BackEnd
	 *
	 * Module backend
	 *
	 * @access public
	 *        
	 */
	function admin(&$out) {
		global $ajax;
		global $filter;
		global $atype;
		
		if ($ajax) {
			header( "HTTP/1.0: 200 OK\n" );
			header( 'Content-Type: text/html; charset=utf-8' );
			
			if ($atype == "incmode") {
				$this->getConfig ();
				echo $this->config['MS_INCLUSION_MODE'];
				exit ();
			}
			
			$limit = 50;
			
			// Find last midifed
			$filename = ROOT . 'debmes/log_*-cycle_mysensor.php.txt';
			foreach( glob( $filename ) as $file ) {
				$LastModified[] = filemtime( $file );
				$FileName[] = $file;
			}
			$files = array_multisort( $LastModified, SORT_NUMERIC, SORT_ASC, $FileName );
			$lastIndex = count( $LastModified ) - 1;
			
			// Open file
			$data = LoadFile( $FileName[$lastIndex] );
			
			$lines = explode( "\n", $data );
			$lines = array_reverse( $lines );
			$res_lines = array ();
			$total = count( $lines );
			$added = 0;
			for($i = 0; $i < $total; $i ++) {
				if (trim( $lines[$i] ) == '') {
					continue;
				}
				
				if ($filter && preg_match( '/' . preg_quote( $filter ) . '/is', $lines[$i] )) {
					$res_lines[] = $lines[$i];
					$added ++;
				} elseif (! $filter) {
					$res_lines[] = $lines[$i];
					$added ++;
				}
				
				if ($added >= $limit) {
					break;
				}
			}
			
			echo implode( "<br/>", $res_lines );
			exit ();
		}
		if (isset( $this->data_source ) && ! $_GET['data_source'] && ! $_POST['data_source']) {
			$out['SET_DATASOURCE'] = 1;
		}
		
		$this->getConfig ();
		$out['MS_CONTYPE'] = $this->config['MS_CONTYPE'];
		$out['MS_HOST'] = $this->config['MS_HOST'];
		$out['MS_PORT'] = $this->config['MS_PORT'];
		$out['MS_SERIAL'] = $this->config['MS_SERIAL'];
		$out['MS_MEASURE'] = $this->config['MS_MEASURE'];
		$out['MS_AUTOID'] = $this->config['MS_AUTOID'];
		$out['MS_NEXTID'] = $this->config['MS_NEXTID'];
		$out['MS_INCLUSION_MODE'] = $this->config['MS_INCLUSION_MODE'];
		
		if ($out['MS_CONTYPE'] == "") {
			$out['MS_CONTYPE'] = 0;
			$this->config['MS_CONTYPE'] = $out['MS_CONTYPE'];
			$this->saveConfig ();
		}
		if ($out['MS_HOST'] == "") {
			$out['MS_HOST'] = '10.9.0.253';
			$this->config['MS_HOST'] = $out['MS_HOST'];
			$this->saveConfig ();
		}
		if ($out['MS_PORT'] == "") {
			$out['MS_PORT'] = '5003';
			$this->config['MS_PORT'] = $out['MS_PORT'];
			$this->saveConfig ();
		}
		if ($out['MS_SERIAL'] == "") {
			$out['MS_SERIAL'] = '/dev/ttyMySensorsGateway';
			$this->config['MS_SERIAL'] = $out['MS_SERIAL'];
			$this->saveConfig ();
		}
		if ($out['MS_MEASURE'] == "") {
			$out['MS_MEASURE'] = 'M';
			$this->config['MS_MEASURE'] = $out['MS_MEASURE'];
			$this->saveConfig ();
		}
		if ($out['MS_AUTOID'] == "") {
			$out['MS_AUTOID'] = 1;
			$this->config['MS_AUTOID'] = $out['MS_AUTOID'];
			$this->saveConfig ();
		}
		if ($out['MS_NEXTID'] == "") {
			$out['MS_NEXTID'] = 10;
			$this->config['MS_NEXTID'] = $out['MS_NEXTID'];
			$this->saveConfig ();
		}
		
		if ($this->data_source == 'mysensors' || $this->data_source == '') {
			if ($this->view_mode == 'update_settings') {
				global $ms_contype;
				global $ms_host;
				global $ms_port;
				global $ms_serial;
				global $ms_measure;
				global $ms_autoid;
				global $ms_nextid;
				
				$this->config['MS_CONTYPE'] = $ms_contype;
				$this->config['MS_HOST'] = $ms_host;
				$this->config['MS_PORT'] = ( int ) $ms_port;
				$this->config['MS_SERIAL'] = $ms_serial;
				$this->config['MS_MEASURE'] = $ms_measure;
				$this->config['MS_AUTOID'] = $ms_autoid;
				$this->config['MS_NEXTID'] = $ms_nextid;
				$this->saveConfig ();				
				
				setGlobal('cycle_mysensorControl', 'restart');
				
				$this->redirect( "?" );
			}
			
			if ($this->view_mode == '' || $this->view_mode == 'search_ms') {
				if ($this->tab == 'firmware') {
					$this->search_bins( $out );
				} else if ($this->tab == 'mesh') {
					$this->search_mesh( $out );
				} else if ($this->tab == 'log') {
					$this->search_log( $out );
				} else {
					$this->search_ms( $out );
				}
			}
			if ($this->view_mode == 'node_edit') {
				$this->edit_ms( $out, $this->id );
			}
			if ($this->view_mode == 'inc_mode') {
				$NId = 0;
				$SId = 0;
				$mType = 3;
				$ack = 0;
				$SubType = 5;
				
				if ($this->config['MS_INCLUSION_MODE'] == 0)
					$val = 1;
				else
					$val = 0;
				$this->config['MS_INCLUSION_MODE'] = $val;
				$this->saveConfig ();
				
				$this->cmd( "$NId;$SId;$mType;$ack;$SubType;" . $val );
				$this->redirect( "?" );
			}
			if ($this->view_mode == 'node_delete') {
				$this->delete_ms( $this->id );
				$this->redirect( "?" );
			}
			if ($this->view_mode == 'bin_edit') {
				$this->edit_bin( $out, $this->id );
			}
			if ($this->view_mode == 'bin_delete') {
				$this->delete_bin( $this->id );
				$this->redirect( "?data_source=$this->data_source&tab=firmware" );
			}
			if ($this->view_mode == 'sensor_add') {
				$this->add_sensor( $out, $this->id );
			}
			if ($this->view_mode == 'sensor_delete') {
				$this->delete_sensor( $this->id );
				
				global $pid;
				$this->redirect( "?data_source=$this->data_source&view_mode=node_edit&id=$pid&tab=sensors" );
			}
			if ($this->view_mode == 'presentation_clean') {
				$this->clean_presentation( $this->id );
				$this->redirect( "?data_source=$this->data_source&view_mode=node_edit&id=$this->id&tab=presentation" );
			}
		}
	}
	/**
	 * FrontEnd
	 *
	 * Module frontend
	 *
	 * @access public
	 *        
	 */
	function usual(&$out) {
		$this->admin( $out );
	}
	/**
	 * Search nodes
	 *
	 * @access public
	 *        
	 */
	function search_ms(&$out) {
		require (DIR_MODULES . $this->name . '/ms_search.inc.php');
	}
	/**
	 * Search bins
	 *
	 * @access public
	 *        
	 */
	function search_bins(&$out) {
		require (DIR_MODULES . $this->name . '/ms_bins.inc.php');
	}
	/**
	 * Search mesh
	 *
	 * @access public
	 *        
	 */
	function search_mesh(&$out) {
		require (DIR_MODULES . $this->name . '/ms_mesh.inc.php');
	}
	/**
	 * Search log
	 *
	 * @access public
	 *        
	 */
	function search_log(&$out) {
		require (DIR_MODULES . $this->name . '/ms_log.inc.php');
	}
	/**
	 * Search sensors
	 *
	 * @access public
	 *        
	 */
	function node_sensors(&$out, $id) {
		require (DIR_MODULES . $this->name . '/sensors_search.inc.php');
	}
	/**
	 * Edit/add
	 *
	 * @access public
	 *        
	 */
	function edit_ms(&$out, $id) {
		require (DIR_MODULES . $this->name . '/ms_edit.inc.php');
	}
	/**
	 * Delete node
	 *
	 * @access public
	 *        
	 */
	function delete_ms($id) {
		$rec = SQLSelectOne( "SELECT * FROM msnodes WHERE ID='$id'" );
		// some action for related tables
		SQLExec( "DELETE FROM msnodesens WHERE NID='" . $rec['NID'] . "'" );
		SQLExec( "DELETE FROM msnodeval WHERE NID='" . $rec['NID'] . "'" );
		SQLExec( "DELETE FROM msnodes WHERE ID='" . $rec['ID'] . "'" );
	}
	/**
	 * Edit/add
	 *
	 * @access public
	 *        
	 */
	function edit_bin(&$out, $id) {
		require (DIR_MODULES . $this->name . '/bin_edit.inc.php');
	}
	/**
	 * Delete node
	 *
	 * @access public
	 *        
	 */
	function delete_bin($id) {
		$rec = SQLSelectOne( "SELECT * FROM msbins WHERE ID='$id'" );
		// some action for related tables
		SQLExec( "DELETE FROM msbins WHERE ID='" . $rec['ID'] . "'" );
	}
	/**
	 * Add sensor
	 *
	 * @access public
	 *        
	 */
	function add_sensor(&$out, $id) {
		require (DIR_MODULES . $this->name . '/sensor_add.inc.php');
	}
	/**
	 * Delete sensor
	 *
	 * @access public
	 *        
	 */
	function delete_sensor($id) {
		$rec = SQLSelectOne( "SELECT * FROM msnodeval WHERE ID='$id'" );
		// some action for related tables
		SQLExec( "DELETE FROM msnodeval WHERE ID='" . $rec['ID'] . "'" );
	}
	/**
	 * Handle property object
	 *
	 * @access public
	 *        
	 */
	function propertySetHandle($object, $property, $value) {
		$properties = SQLSelect( "SELECT ID FROM msnodeval WHERE LINKED_OBJECT LIKE '" . DBSafe( $object ) . "' AND LINKED_PROPERTY LIKE '" . DBSafe( $property ) . "'" );
		$total = count( $properties );
		if ($total) {
			for($i = 0; $i < $total; $i ++) {
				$this->setProperty( $properties[$i]['ID'], $value );
			}
		}
	}
	/**
	 * Clean presentation
	 *
	 * @access public
	 *        
	 */
	function clean_presentation($id) {
		global $nid;
		
		SQLExec( "DELETE FROM msnodesens WHERE NID='" . $nid . "'" );
	}
	/**
	 * Receive Presentation
	 *
	 * @access public
	 *        
	 */
	function Presentation($arr) {
		// Node
		$NId = $arr[0];
		if ($NId == "") return;
		
		$SId = $arr[1];		
		$SubType = $arr[4];
		$info = $arr[5];
		
		// Log
		$Ack = $arr[3];
		echo date("Y-m-d H:i:s")." Presentation: Node:$NId; Sensor:$SId; Ack:$Ack; Sub:$SubType; Msg:$info\n";
		
		$node = SQLSelectOne( "SELECT * FROM msnodes WHERE NID LIKE '".DBSafe($NId)."';" );
		if (! $node['ID'])
			if (! $this->RegistNewNode( $node, $NId ))
				return;
			
			// Arduino Node
		if ($SId == 255) {
			$node['PROT'] = $arr[5];
			
			if ($node['LASTREBOOT'] == 0)
				$node['LASTREBOOT'] = date( 'Y-m-d H:i:s' );
			
			SQLUpdate( 'msnodes', $node );
		} else {
			// Sensor
			$sens = SQLSelectOne( "SELECT * FROM msnodesens WHERE NID LIKE '".DBSafe($NId)."' AND SID LIKE '".DBSafe($SId)."' AND SUBTYPE LIKE '".DBSafe( $SubType )."';" );
			if (! $sens['ID']) {
				$sens['NID'] = $NId;
				$sens['SID'] = $SId;
				$sens['SUBTYPE'] = $SubType;
				$sens['INFO'] = $info;
				$sens['ID'] = SQLInsert( 'msnodesens', $sens );
			} else {
				$sens['INFO'] = $info;
				SQLUpdate( 'msnodesens', $sens );
			}
		}
	}
	/**
	 * Receive Set
	 *
	 * @access public
	 *        
	 */
	function Set($arr) {
		// Node
		$NId = $arr[0];
		$SId = $arr[1];
		$SubType = $arr[4];
		$val = $arr[5];
		if ($NId == "")	return;
		
		// Log
		$Ack = $arr[3];
		echo date("Y-m-d H:i:s")." Set: Node:$NId; Sensor:$SId; Ack:$Ack; Sub:$SubType; Msg:$val\n";
		
		$node = SQLSelectOne( "SELECT * FROM msnodes WHERE NID LIKE '".DBSafe( $NId )."';" );
		if (! $node['ID'])
			if (! $this->RegistNewNode( $node, $NId ))
				return;
			
			// Sensor
		$sens = SQLSelectOne( "SELECT * FROM msnodeval WHERE NID LIKE '".DBSafe( $NId )."' AND SID LIKE '".DBSafe( $SId )."' AND SUBTYPE LIKE '".DBSafe( $SubType )."';" );
		if (! $sens['ID']) {
			$sens['NID'] = $NId;
			$sens['SID'] = $SId;
			$sens['SUBTYPE'] = $SubType;
			$sens['ID'] = SQLInsert( 'msnodeval', $sens );
		}
		
		// Delete ACK
		if ($arr[3] == 1) {
			SQLExec( "DELETE FROM mssendstack WHERE NID='" . $NId . "' AND SID='" . $SId . "' AND MType='" . $arr[2] . "' AND SUBTYPE='" . $SubType . "' AND MESSAGE='" . $val . "' AND SENDRX=0" );
		}
		
		// echo date("Y-m-d H:i:s")." Proc 3\n";
		// echo print_r($sens, true)."\n";
		
		// Set
		$sens['UPDATED'] = date( 'Y-m-d H:i:s' );
		$sens['VAL'] = $val;
		SQLUpdate( 'msnodeval', $sens );
		
		// echo "set:".print_r($sens)."\n";
		// echo date("Y-m-d H:i:s")." Proc 4\n";
		
		if ($sens['LINKED_OBJECT'] && $sens['LINKED_PROPERTY']) {
			// echo date("Y-m-d H:i:s")." Start set\n";
			// echo "Set ".$sens['LINKED_OBJECT'].'.'.$sens['LINKED_PROPERTY']."=".$val."\n";
			setGlobal( $sens['LINKED_OBJECT'] . '.' . $sens['LINKED_PROPERTY'], $val, array($this->name => '0') );
			// echo date("Y-m-d H:i:s")." End set\n";
		}
	}
	/**
	 * Title
	 *
	 * Description
	 *
	 * @access public
	 *        
	 */
	function setProperty($prop_id, $value, $set_linked = 0) {
		$rec = SQLSelectOne( "SELECT * FROM msnodeval WHERE msnodeval.id=$prop_id" );
		if (! $rec['ID'])
			return 0;
		
		$rec['UPDATED'] = date( 'Y-m-d H:i:s' );
		
		// Set to node value
		if ($rec['VAL'] != $value) {
			// Not set for rollback --- $rec['VAL'] = $value;
			SQLUpdate( 'msnodeval', $rec );
		}
		
		$this->cmd( $rec['NID'] . ";" . $rec['SID'] . ";1;" . $rec['ACK'] . ";" . $rec['SUBTYPE'] . ";" . $value );
	}
	/**
	 * Title
	 *
	 * Description
	 *
	 * @access public
	 *        
	 */
	function cmd($str) {
		$arr = explode( ';', $str, 6 );
		
		// For sleep node
		$sendrx = 0;
		$rec = SQLSelectOne( "SELECT devtype FROM msnodes WHERE nid=" . $arr[0] );
		
		if ($rec['devtype'] == 1) {
			$sendrx = 1;
			$expire = time () + $this->RxExpireTimeout;
		} else {
			$sendrx = 0;
			$expire = time () + $this->tryTimeout;
		}
		
		$data['NID'] = $arr[0];
		$data['SID'] = $arr[1];
		$data['MType'] = $arr[2];
		$data['ACK'] = $arr[3];
		$data['SUBTYPE'] = $arr[4];
		$data['MESSAGE'] = $arr[5];
		$data['EXPIRE'] = $expire;
		$data['SENDRX'] = $sendrx;
		SQLInsert( 'mssendstack', $data );
		
		// DebMes("Prepare send: ".print_r($data, true));
	}
	/**
	 * Receive req
	 *
	 * @access public
	 *        
	 */
	function req($arr) {
		
		// Node
		$NId = $arr[0];
		$SId = $arr[1];
		$mType = 1; // $arr[2];
		$Ack = $arr[3];
		$SubType = $arr[4];
		if ($NId == "")	return;
		
		// Log		
		echo date("Y-m-d H:i:s")." Req: Node:$NId; Sensor:$SId; Ack:$Ack; Sub:$SubType; Msg:$val\n";
		
		$node = SQLSelectOne( "SELECT * FROM msnodes WHERE NID LIKE '".DBSafe( $NId )."';" );
		if (! $node['ID'])
			if (! $this->RegistNewNode( $node, $NId ))
				return;
			
		// Sensor
		$sens = SQLSelectOne( "SELECT * FROM msnodeval WHERE NID LIKE '".DBSafe( $NId )."' AND SID LIKE '".DBSafe( $SId )."' AND SUBTYPE LIKE '".DBSafe( $SubType )."';" );
		if (! $sens['ID']) {
			$sens['NID'] = $NId;
			$sens['SID'] = $SId;
			$sens['ACK'] = $Ack;
			$sens['SUBTYPE'] = $SubType;
			$sens['ID'] = SQLInsert( 'msnodeval', $sens );
		}
		
		// Req
		$val = $sens['VAL'];
		if ($sens['LINKED_OBJECT'] && $sens['LINKED_PROPERTY']) {
			$val = getGlobal( $sens['LINKED_OBJECT'] . '.' . $sens['LINKED_PROPERTY'] );
			// echo "Get from: ".$sens['LINKED_OBJECT'].".".$sens['LINKED_PROPERTY']." = ".$val."\n";
		}
		// echo "Set: ".$val."\n";
		
		$this->cmd( "$NId;$SId;$mType;" . $sens['ACK'] . ";$SubType;" . $val );
		return false;
	}
	/**
	 * Receive Set
	 *
	 * @access public
	 *        
	 */
	function Internal($arr) {
		$this->getConfig();
		
		// Node
		$NId = $arr[0];
		$SubType = $arr[4];
		$val = $arr[5];
		if ($NId == "")	return;
		
		// Log		
		$SId = $arr[1];
		$Ack = $arr[3];
		echo date("Y-m-d H:i:s")." Internal: Node:$NId; Sensor:$SId; Ack:$Ack; Sub:$SubType; Msg:$val\n";
			
		// Skip tester present
		if ($NId == 255) { // ($NId == 0) ||
			$node = false;
		} else {
			$node = SQLSelectOne( "SELECT * FROM msnodes WHERE NID LIKE '".DBSafe( $NId )."';" );
			if (! $node['ID'])
				if (! $this->RegistNewNode( $node, $NId ))
					return;
		}
		
		if ($node['LASTREBOOT'] == 0)
			$node['LASTREBOOT'] = date( 'Y-m-d H:i:s' );
		
		switch ($SubType) {
			// Battery
			case I_BATTERY_LEVEL :
				if ($node) {
					$node['BATTERY'] = $val;
					SQLUpdate( 'msnodes', $node );
					
					if ($node['BAT_OBJECT'] && $node['BAT_PROPERTY']) {
						setGlobal( $node['BAT_OBJECT'] . '.' . $node['BAT_PROPERTY'], $val, array($this->name => '0') );
					}
				}
				break;
			
			// Time
			case I_TIME :
				$this->cmd( $NId . ";255;3;0;" . I_TIME . ";" . time () );
				break;
			
			// Version
			case I_VERSION :
				if ($node) {
					$node['VER'] = $val;
					SQLUpdate( 'msnodes', $node );
				}
				break;
			
			// Request data
			case I_ID_REQUEST :
				if (($this->config['MS_AUTOID'] == '') || ($this->config['MS_AUTOID'] == 1)) {
					$nextid = $this->config['MS_NEXTID'];
					
					// Check ready has
					while ( true ) {
						$node = SQLSelectOne( "SELECT * FROM msnodes WHERE NID LIKE '".DBSafe( $nextid )."';" );
						if ($node['ID']) {
							$nextid ++;
							continue;
						}
						break;
					}
					
					if ($nextid < 255) {
						// Send new id
						$this->cmd( "255;255;3;0;" . I_ID_RESPONSE . ";" . $nextid );
						echo "Req new ID: $nextid\n";
					} else {
						echo "Req new ID: out of range\n";
					}
				} else {
					echo "Req new ID: rejected\n";
				}
				break;
			
			// INCLUSION MODE
			case I_INCLUSION_MODE :
				$this->config['MS_INCLUSION_MODE'] = $val;
				$this->saveConfig ();
				break;
			
			// CONFIG
			case I_CONFIG :
				if ($node) {
					$node['PID'] = $val;
					$node['LASTREBOOT'] = date( 'Y-m-d H:i:s' );
					SQLUpdate( 'msnodes', $node );
				}
				
				// Send ansver - metric
				$this->cmd( $NId . ";255;3;0;" . I_CONFIG . ";" . $this->config['MS_MEASURE'] );
				break;
			
			// LOG_MESSAGE
			case I_LOG_MESSAGE :
				echo date( "Y-m-d H:i:s" ) . "Log message ID:" . $NId . " $val";
			
			// SKETCH_NAME
			case I_SKETCH_NAME :
				if ($node) {
					$node['SKETCH'] = $val;
					SQLUpdate( 'msnodes', $node );
				}
				break;
			
			// SKETCH_VERSION
			case I_SKETCH_VERSION :
				if ($node) {
					$node['VER'] = $val;
					SQLUpdate( 'msnodes', $node );
				}
				break;
			
			// I_GATEWAY_READY
			case I_GATEWAY_READY :
				break;
			
			// I_REQUEST_SIGNING
			case I_SIGNING_PRESENTATION :
				switch ($val) {
					case - 1 :
						break;
					
					default :
						echo date( "Y-m-d H:i:s" ) . " Unknow SIGNING_PRESENTATION ID:" . $NId . " Sub:" . $SubType . " Val:" . $val . "\n";
						break;
				}
				
				break;
			
			// I_DISCOVER_RESPONSE
			case I_DISCOVER_RESPONSE :
				break;
						
			// I_HEARTBEAT_RESPONSE
			case I_HEARTBEAT_RESPONSE :
				if ($node) {
					$node['HEARTBEAT'] = date( 'Y-m-d H:i:s' );
					SQLUpdate( 'msnodes', $node );
					
					if ($node['HEARTBEAT_OBJECT'] && $node['HEARTBEAT_PROPERTY']) {
						setGlobal( $node['HEARTBEAT_OBJECT'] . '.' . $node['HEARTBEAT_PROPERTY'], $val, array($this->name => '0') );
					}
				}
				break;
			
			// I_PING
			case I_PING :
				// Send I_PONG
				$this->cmd( $NId . ";255;3;0;" . I_PONG . ";" . $val );
				break;
			
			// I_REGISTRATION_REQUEST
			case I_REGISTRATION_REQUEST :
				// Register request to GW
				$val = $val >= $this->MY_CORE_MIN_VERSION;
				$this->cmd( $NId . ";255;3;0;" . I_REGISTRATION_RESPONSE . ";" . $val );
				break;
				
			// I_DEBUG
			case I_DEBUG:
				echo date( "Y-m-d H:i:s" ) . " Debug: ID:".$NId." = ".$val."\n";
				break;
			
			default :
				echo date( "Y-m-d H:i:s" ) . " Unknow internal command: ID:" . $NId . " Sub:" . $SubType . " Val:" . $val . "\n";
				break;
			
			// @@@ 7 - FIND_PARENT
			// 9 - LOG_MESSAGE
			// @@@ 14 - GATEWAY_READY
		}
	}
	/**
	 * STream packet
	 *
	 * @access public
	 *        
	 */
	function Stream($arr) {
		//$this->getConfig ();
		
		// Node
		$NId = $arr[0];		
		$SubType = $arr[4];
		$val = $arr[5];
		if ($NId == "")	return;
		
		// Log				
		$Ack = $arr[3];
		$SId = $arr[1];
		echo date("Y-m-d H:i:s")." Stream: Node:$NId; Sensor:$SId; Ack:$Ack; Sub:$SubType; Msg:$val\n";
		
		$node = SQLSelectOne( "SELECT * FROM msnodes WHERE NID LIKE '".DBSafe( $NId )."';" );
		if (! $node['ID'])
			if (! $this->RegistNewNode( $node, $NId ))
				return;
		
		switch ($SubType) {
			// Request new FW, payload contains current FW details
			case 0x00 :
				// Delete cashed bin
				unset($this->node_bins[$NId]);
				
				// Type
				$CType = substr( $val, 0, 4 );
				$CVer = substr( $val, 4, 4 );
				$CBloks = hexdec( substr( $val, 8, 4 ) );
				$CCrc = hexdec( substr( $val, 12, 4 ) );
				$BLh = hexdec( substr( $val, 16, 2 ) );
				$BLl = hexdec( substr( $val, 18, 2 ) );
				
				// Test version
/*				
				if (($CVer != "0100") || ($CVer != "01FF") || ($CVer != "FFFF")){
					echo date("Y-m-d H:i:s")." Unknow boot version $NId - $CVer\n";
					return;
				}
*/				
				// Test BLVer
				//if ($BLh != 1){
				//	echo date("Y-m-d H:i:s")." Error BL version $BLh.$BLl\n";
				//	return;
				//}
				
				echo date( "Y-m-d H:i:s" ) . " BL version=$BLh.$BLl\n";
				
				// Load bin									
				$rec = SQLSelectOne( "SELECT * FROM msbins WHERE ID=( SELECT FIRMWARE FROM msnodes WHERE NID LIKE '".DBSafe( $NId )."');" );
				if (!$rec['ID']){
					echo date("Y-m-d H:i:s")." Binary for $NId not found\n";
					return;
				}
				
				// Parse HEX
				$parser = new IntelHex();
				if (!$parser->Parse($rec['BIN'])) {
					echo date("Y-m-d H:i:s")." Error load bin $NId : $parser->LastError\n";
					return;
				}
				if ($parser->FirstAddr != 0){
					echo date("Y-m-d H:i:s")." Error load bin $NId : First adress $parser->FirstAddr\n";
					return;
				}
				$parser->NormalizePage(16);
				
				// Make CRC15
				$crc = crc16($parser->Data);
				
				// Sent to cashed
				$this->node_bins[$NId] = array(
					"data" => $parser->Data,
					"crc" => bin2hex($crc),
					"bloks" => bin2hex( pack("S", strlen($parser->Data)/16) )
				);
				
				// Send				
				$data = $CType."0100".$this->node_bins[$NId]["bloks"].$this->node_bins[$NId]["crc"];
				$this->MySensor->send($NId, 0, 4, 0, 1, $data);
				
				break;
				
			// Request FW block
			case 0x02:
				$ndata = $this->node_bins[$NId];
				if (empty($ndata)){
					echo date("Y-m-d H:i:s")." Cashed bin $NId : not found\n";
					return;
				}
				$size = strlen($ndata["data"]);
				
				// Type
				$CType = substr( $val, 0, 4 );
				$CVer = substr( $val, 4, 4 );
				$CBlok = substr( $val, 8, 4 );
				
				$Data2 = hex2bin(hex2bin($CBlok));
				$BlockP = unpack("S", $Data2[1])*16;

				// Test version
				if ($CVer != "0100"){
					echo date("Y-m-d H:i:s")." Unknow boot version $NId - $CVer\n";
					return;
				}
				
				if ($BlockP+16 > $size) {
					echo date("Y-m-d H:i:s")." Out of data $NId : $BlockP\n";
					return;
				}
				$data = $CType."0100".$CBlok.bin2hex( substr($ndata["data"], $BlockP, 16));
				$this->MySensor->send($NId, 0, 4, 0, 3, $data);
				
				// State
				if ($BlockP == 0){
					SQLExec ("DELETE FROM msnodestate WHERE NID=".$NId);
				} else {
					$state = Round(100-($BlockP*100 / $size), 2)."%";
					SQLExec ("INSERT INTO msnodestate (NID,state) VALUES ($NId,'".$state."') ON DUPLICATE KEY UPDATE state='".$state."';");
				}
				
				break;
			
			default :
				echo date( "Y-m-d H:i:s" ) . " Unknow stream command: ID: $NId; Sub:$SubType; Val:$val\n";
				break;
		}
	}
	/**
	 * Send data
	 *
	 * @access public
	 *        
	 */
	function doSend() {
		$rec=SQLSelectOne("SELECT * FROM mssendstack;");   
		if (!$rec['ID']) return;
		
		$expire = $rec['EXPIRE'] < time();
		  
		// Del not ACK packet
		if (($rec['ACK'] == 0) || $expire){
			// echo "Delete not ACK $ID\n";
			SQLExec("DELETE FROM mssendstack WHERE ID='".$rec['ID']."'");
		}
		  
		if ($expire){
			echo date( "Y-m-d H:i:s" )." Expire $ID ".date("Y-m-d H:i:s", $rec['EXPIRE'])." <> ".date("Y-m-d H:i:s")."\n";

			$sens=SQLSelectOne("SELECT * FROM msnodeval WHERE NID='".$rec['NID']."' AND SID='".$rec['SID']."' AND SUBTYPE='".$rec['SUBTYPE']."';"); 
			if ($sens['LINKED_OBJECT'] && $sens['LINKED_PROPERTY']) {
				echo date( "Y-m-d H:i:s" )." Expire send set rollback : ".$sens['LINKED_OBJECT'].'.'.$sens['LINKED_PROPERTY']."=".$sens['VAL']."\n";
				// Rollback value if not comin
				setGlobal($sens['LINKED_OBJECT'].'.'.$sens['LINKED_PROPERTY'], $rec['VAL'], array($this->name => '0'));
			}
			
			return;
		}
		
		// Send		
		if ($this->MySensor === false){
			echo date("Y-m-d H:i:s")." Error send: Not set MySensor class\n";
			return;
		}
		$this->MySensor->send($rec['NID'], $rec['SID'], $rec['MType'], $rec['ACK'], $rec['SUBTYPE'], $rec['MESSAGE']);
		
		//$sendwait[$rec['NID']] = time();
		//echo date("Y-m-d H:i:s")." Send:: ".$rec['NID'].",".$rec['SID'].",".$rec['MType'].",".$rec['ACK'].",".$rec['SUBTYPE'].",".$rec['MESSAGE']."\n";		
	}
		
	function RegistNewNode(&$node, $NId) {
		$ms_autoid = $this->config['MS_AUTOID'];
		if ($ms_autoid == "0")
			return false;
			
			// Next id
		$nextid = $this->config['MS_NEXTID'];
		
		if ($NId >= $nextid) {
			$this->config['MS_NEXTID'] = $NId + 1;
			$this->saveConfig ();
		}
		
		// Set new
		$node['NID'] = $NId;
		$node['PID'] = 0;
		$node['TITLE'] = $NId;
		$node['ID'] = SQLInsert( 'msnodes', $node );
		
		return true;
	}
	
	/**
	 * Install
	 *
	 * Module installation routine
	 *
	 * @access private
	 *        
	 */
	function install($data = '') {
		parent::install ();
	}
	/**
	 * dbInstall
	 *
	 * Database installation routine
	 *
	 * @access private
	 *        
	 */
	function dbInstall($data) {
		
		// Send message
		SQLExec( "DROP TABLE IF EXISTS `mssendstack`;" );
		
		$sqlQuery = "CREATE TABLE IF NOT EXISTS `mssendstack`
               (`ID`  int(10) unsigned NOT NULL auto_increment,
                `NID` int(10) NOT NULL,
                `SID` int(10) NOT NULL,
                `MType` int(10) NOT NULL,
                `ACK` int(10) NOT NULL,
                `SUBTYPE` int(10) NOT NULL,
                `MESSAGE` varchar(32) NOT NULL,
                `EXPIRE` BIGINT NOT NULL,
                `SENDRX` int(10) NOT NULL,
                PRIMARY KEY (`ID`)
               ) ENGINE = MEMORY DEFAULT CHARSET=utf8;";
		
		SQLExec( $sqlQuery );
		
		// Node boot state
		//TODO make
		$sqlQuery = "CREATE TABLE IF NOT EXISTS `msnodestate`
               (`NID` int(10) NOT NULL,
				`State` varchar(32) NOT NULL,
			   PRIMARY KEY (`NID`)
               ) ENGINE = MEMORY DEFAULT CHARSET=utf8;";
		
		SQLExec( $sqlQuery );
		
		$data = <<<EOD
	msnodes: ID int(10) unsigned NOT NULL auto_increment
	msnodes: NID int(10) NOT NULL
	msnodes: PID int(10) NOT NULL
	msnodes: TITLE varchar(255) NOT NULL DEFAULT ''
	msnodes: BATTERY varchar(32) NOT NULL DEFAULT ''
	msnodes: HEARTBEAT datetime
	msnodes: SKETCH varchar(32) NOT NULL DEFAULT ''
	msnodes: VER varchar(32) NOT NULL DEFAULT ''
	msnodes: PROT varchar(32) NOT NULL DEFAULT ''
	msnodes: FIRMWARE int(10) NOT NULL DEFAULT -1
	msnodes: BAT_OBJECT varchar(255) NOT NULL DEFAULT ''
	msnodes: BAT_PROPERTY varchar(255) NOT NULL DEFAULT ''
	msnodes: HEARTBEAT_OBJECT varchar(255) NOT NULL DEFAULT ''
	msnodes: HEARTBEAT_PROPERTY varchar(255) NOT NULL DEFAULT ''
	msnodes: LOCATION_ID int(10) NOT NULL DEFAULT '0' 
	msnodes: LASTREBOOT datetime
	msnodes: DEVTYPE int(10) DEFAULT '0'

	msnodesens: ID int(10) unsigned NOT NULL auto_increment
	msnodesens: NID int(10) NOT NULL 
	msnodesens: SID int(10) NOT NULL    
	msnodesens: SUBTYPE int(10) NOT NULL
	msnodesens: INFO varchar(255) NOT NULL DEFAULT ''

	msnodeval: ID int(10) unsigned NOT NULL auto_increment
	msnodeval: NID int(10) NOT NULL  
	msnodeval: SID int(10) NOT NULL  
	msnodeval: SUBTYPE int(10) NOT NULL  
	msnodeval: VAL varchar(32) NOT NULL DEFAULT ''  
	msnodeval: UPDATED datetime
	msnodeval: LINKED_OBJECT varchar(255) NOT NULL DEFAULT ''
	msnodeval: LINKED_PROPERTY varchar(255) NOT NULL DEFAULT ''
	msnodeval: ACK int(3) unsigned NOT NULL DEFAULT '0'
	msnodeval: REQ int(3) unsigned NOT NULL DEFAULT '0'
	
	msbins: ID int(10) unsigned NOT NULL auto_increment
	msbins: TITLE varchar(255) NOT NULL DEFAULT ''
	msbins: VER int(10) NOT NULL DEFAULT 0
	msbins: BIN LONGBLOB
	msbins: CRC char(4)
	msbins: BLOKS char(4)
EOD;
		parent::dbInstall( $data );
	}
	// --------------------------------------------------------------------
}

?>