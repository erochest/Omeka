<?php 
require_once 'RouteTable.php';
/**
* Route model
*/
class Route extends Kea_Record
{
	public function setTableDefinition()
	{
		$this->setTableName('routes');
		$this->hasColumn("name", "string");
		$this->hasColumn("route", "string");
		$this->hasColumn("defaults", "array");
		$this->hasColumn("path", "string");
		$this->hasColumn("added", "timestamp");
		$this->hasColumn("static", "boolean",null,array('default'=>1));
		$this->hasColumn("active", "boolean",null,array('default'=>0));
		
		$this->index('static', array('fields' => array('active','static')));
	}
}
 
?>
