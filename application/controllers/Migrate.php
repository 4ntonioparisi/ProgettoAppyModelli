<?php

require_once "Secure_Controller.php";

class Migrate extends Secure_Controller
{
	public function __construct()
	{
		parent::__construct('migrate');

		$this->load->library('migration');
	}

	public function index()
	{
		$this->load->view('migrate/manage');
	}

	public function perform_migration()
	{
		if( ! $this->migration->latest())
		{
			echo json_encode(array('success' => false, 'message' => $this->lang->line('migrate_failed - ' . $this->migration->error_string())));
		}
		else
		{
			echo json_encode(array('success' => true, 'message' => $this->lang->line('migrate_success')));
		}
	}
}
?>
